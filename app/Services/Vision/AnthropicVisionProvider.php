<?php

namespace App\Services\Vision;

use Anthropic\Beta\Messages\BetaBase64ImageSource;
use Anthropic\Beta\Messages\BetaImageBlockParam;
use Anthropic\Client;
use RuntimeException;

final class AnthropicVisionProvider implements VisionProvider
{
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function describeSheet(string $jpegPath, int $cellCount, array $hints): array
    {
        $bytes = @file_get_contents($jpegPath);
        if ($bytes === false) {
            throw new RuntimeException('Cannot read contact sheet: ' . $jpegPath);
        }

        $client = new Client(apiKey: $this->apiKey);

        $message = $client->beta->messages->create(
            model: $this->model,
            maxTokens: 16000,
            system: VisionPrompt::system(),
            messages: [[
                'role' => 'user',
                'content' => [
                    BetaImageBlockParam::with(
                        source: BetaBase64ImageSource::with(
                            data: base64_encode($bytes),
                            mediaType: 'image/jpeg',
                        ),
                    ),
                    ['type' => 'text', 'text' => VisionPrompt::user($cellCount, $hints)],
                ],
            ]],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => VisionPrompt::schema()]],
            // Server-side fallback: if the model declines for policy reasons, Anthropic
            // retries the same request on its default substitute model.
            betas: [self::FALLBACK_BETA],
            fallbacks: 'default',
        );

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('Claude declined to describe this contact sheet.');
        }
        if ($message->stopReason === 'max_tokens') {
            throw new RuntimeException('Claude response was cut off at max_tokens; try a smaller --sheet-size.');
        }

        $json = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $json = $block->text;
                break;
            }
        }
        if ($json === null) {
            throw new RuntimeException('Claude returned no text block.');
        }

        return VisionPrompt::parse($json, $cellCount);
    }
}
