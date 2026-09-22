<?php

namespace App\Services\Vision;

use InvalidArgumentException;

final class VisionProviderFactory
{
    /**
     * Build the configured provider from config('services.vision').
     *
     * @param string|null $provider "anthropic" or "openai"; null uses the configured default.
     */
    public static function make(?string $provider = null): VisionProvider
    {
        $config = (array) config('services.vision', []);
        $slug = strtolower(trim((string) ($provider ?: ($config['provider'] ?? 'anthropic'))));

        return match ($slug) {
            'anthropic', 'claude' => self::anthropic((array) ($config['anthropic'] ?? [])),
            'openai', 'gpt' => self::openai((array) ($config['openai'] ?? [])),
            default => throw new InvalidArgumentException(
                'Unknown vision provider "' . $slug . '". Use "anthropic" or "openai".'
            ),
        };
    }

    private static function anthropic(array $config): VisionProvider
    {
        $key = (string) ($config['api_key'] ?? '');
        if ($key === '') {
            throw new InvalidArgumentException('ANTHROPIC_API_KEY is not set; add it to .env or use --provider=openai.');
        }

        return new AnthropicVisionProvider($key, (string) ($config['model'] ?? 'claude-opus-5'));
    }

    private static function openai(array $config): VisionProvider
    {
        $key = (string) ($config['api_key'] ?? '');
        if ($key === '') {
            throw new InvalidArgumentException('OPENAI_API_KEY is not set; add it to .env or use --provider=anthropic.');
        }

        return new OpenAiVisionProvider(
            $key,
            (string) ($config['model'] ?? 'gpt-6-astra'),
            (string) ($config['base_url'] ?? 'https://api.openai.com/v1'),
        );
    }
}
