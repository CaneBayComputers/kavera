<?php

namespace App\Services\Vision;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI Responses API (POST /v1/responses) with a base64 image input and a
 * strict JSON schema output.
 */
final class OpenAiVisionProvider implements VisionProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        private readonly int $timeout = 180,
    ) {
    }

    public function name(): string
    {
        return 'openai';
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

        $payload = [
            'model' => $this->model,
            'instructions' => VisionPrompt::system(),
            'input' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_image',
                        'image_url' => 'data:image/jpeg;base64,' . base64_encode($bytes),
                        'detail' => 'high',
                    ],
                    ['type' => 'input_text', 'text' => VisionPrompt::user($cellCount, $hints)],
                ],
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'contact_sheet_descriptions',
                    'strict' => true,
                    'schema' => VisionPrompt::schema(),
                ],
            ],
        ];

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/') . '/responses', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'OpenAI request failed (HTTP ' . $response->status() . '): ' . mb_substr($response->body(), 0, 300)
            );
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('OpenAI returned a non-JSON body.');
        }

        if (($body['status'] ?? 'completed') === 'incomplete') {
            $reason = $body['incomplete_details']['reason'] ?? 'unknown';
            throw new RuntimeException('OpenAI response incomplete (' . $reason . '); try a smaller --sheet-size.');
        }

        $text = null;
        foreach ($body['output'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $content) {
                $type = $content['type'] ?? '';
                if ($type === 'refusal') {
                    throw new RuntimeException('OpenAI declined to describe this contact sheet: ' . ($content['refusal'] ?? ''));
                }
                if ($type === 'output_text') {
                    $text = (string) ($content['text'] ?? '');
                    break 2;
                }
            }
        }

        if ($text === null) {
            throw new RuntimeException('OpenAI returned no output_text.');
        }

        return VisionPrompt::parse($text, $cellCount);
    }
}
