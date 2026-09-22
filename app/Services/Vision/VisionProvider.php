<?php

namespace App\Services\Vision;

interface VisionProvider
{
    /**
     * Short provider slug used in the manifest, e.g. "anthropic" or "openai".
     */
    public function name(): string;

    /**
     * Model identifier that will be recorded in the manifest.
     */
    public function model(): string;

    /**
     * Describe every numbered cell on a contact sheet.
     *
     * @param string $jpegPath Absolute path to the contact sheet JPEG.
     * @param int $cellCount Number of numbered cells on the sheet (1..N).
     * @param array<int, string> $hints Per-cell filename or folder hints, keyed by cell number.
     *
     * @return array<int, array{description:string,subjects:list<string>,colors:list<string>,text:list<string>,people:int}>
     *         keyed by cell number.
     */
    public function describeSheet(string $jpegPath, int $cellCount, array $hints): array;
}
