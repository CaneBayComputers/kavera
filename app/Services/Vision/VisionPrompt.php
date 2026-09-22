<?php

namespace App\Services\Vision;

use RuntimeException;

/**
 * The prompt, JSON schema and response parsing shared by every vision provider,
 * so Anthropic and OpenAI produce identical manifest fields.
 */
final class VisionPrompt
{
    public static function system(): string
    {
        return <<<'TXT'
You describe photographs and graphics for a website builder. You are shown a contact sheet: a grid of
images, each with a white number on a black label in its top left corner. Describe each numbered cell
independently. Never merge neighbouring cells or describe the sheet as a whole.

For every cell return:
- description: one or two plain sentences a web developer could use as alt text. Name the subject,
  setting and mood. Do not start with "An image of" or "A photo of".
- subjects: three to eight short lowercase nouns or noun phrases, most important first.
- colors: two to five dominant colors as a readable name followed by a hex code in parentheses,
  most dominant first, for example "deep navy blue (#1b2a49)".
- text: any clearly readable words or numbers in the image, exactly as written. Empty if none.
- people: the number of visible human beings, 0 if none.

Be specific and factual. If a cell is a logo, icon, diagram or screenshot, say so.
TXT;
    }

    /**
     * @param array<int, string> $hints
     */
    public static function user(int $cellCount, array $hints): string
    {
        $lines = [
            'This sheet has ' . $cellCount . ' numbered cells (1 to ' . $cellCount . ').',
            'Return one entry per cell in the JSON schema provided, in cell order.',
        ];

        $hintLines = [];
        foreach ($hints as $cell => $hint) {
            $hint = trim((string) $hint);
            if ($hint !== '') {
                $hintLines[] = $cell . ': ' . $hint;
            }
        }

        if (! empty($hintLines)) {
            $lines[] = '';
            $lines[] = 'Original file names, which may hint at the subject or intended use (treat as hints, not facts):';
            $lines = array_merge($lines, $hintLines);
        }

        return implode("\n", $lines);
    }

    /**
     * JSON schema for the whole response. Strict: every property required, no extras.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $stringList = ['type' => 'array', 'items' => ['type' => 'string']];

        return [
            'type' => 'object',
            'properties' => [
                'cells' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'cell' => ['type' => 'integer'],
                            'description' => ['type' => 'string'],
                            'subjects' => $stringList,
                            'colors' => $stringList,
                            'text' => $stringList,
                            'people' => ['type' => 'integer'],
                        ],
                        'required' => ['cell', 'description', 'subjects', 'colors', 'text', 'people'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['cells'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Turn the model's JSON text into a cell-number keyed array, validating the shape.
     *
     * @return array<int, array{description:string,subjects:list<string>,colors:list<string>,text:list<string>,people:int}>
     */
    public static function parse(string $json, int $cellCount): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded) || ! isset($decoded['cells']) || ! is_array($decoded['cells'])) {
            throw new RuntimeException('Vision model returned no "cells" array: ' . mb_substr($json, 0, 200));
        }

        $out = [];
        foreach ($decoded['cells'] as $cell) {
            if (! is_array($cell)) {
                continue;
            }
            $number = (int) ($cell['cell'] ?? 0);
            if ($number < 1 || $number > $cellCount) {
                continue;
            }
            $out[$number] = [
                'description' => trim((string) ($cell['description'] ?? '')),
                'subjects' => self::stringList($cell['subjects'] ?? []),
                'colors' => self::stringList($cell['colors'] ?? []),
                'text' => self::stringList($cell['text'] ?? []),
                'people' => max(0, (int) ($cell['people'] ?? 0)),
            ];
        }

        if (empty($out)) {
            throw new RuntimeException('Vision model returned no usable cells.');
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $list[] = $item;
            }
        }

        return array_values(array_unique($list));
    }
}
