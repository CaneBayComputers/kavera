<?php

namespace App\Services\Vision;

use GdImage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tiles a batch of images into one numbered grid JPEG so a vision model can
 * describe many images in a single request.
 */
final class ContactSheetBuilder
{
    private const FONT = 5; // GD built-in font: 9x15 px per glyph

    public function __construct(
        private readonly int $sheetSide = 1536,
        private readonly int $gutter = 8,
    ) {
    }

    /**
     * @param list<string> $paths Absolute paths, in cell order (cell 1 = first path).
     *
     * @return string Absolute path of the temporary JPEG. Caller deletes it.
     */
    public function build(array $paths): string
    {
        $count = count($paths);
        if ($count === 0) {
            throw new RuntimeException('Cannot build a contact sheet from zero images.');
        }

        $cols = (int) ceil(sqrt($count));
        $rows = (int) ceil($count / $cols);
        $cell = intdiv($this->sheetSide, $cols) - $this->gutter;

        $width = $cols * ($cell + $this->gutter) + $this->gutter;
        $height = $rows * ($cell + $this->gutter) + $this->gutter;

        $canvas = imagecreatetruecolor($width, $height);
        if (! $canvas) {
            throw new RuntimeException('Failed to allocate contact sheet canvas.');
        }
        $background = imagecolorallocate($canvas, 0x6b, 0x6b, 0x6b);
        imagefill($canvas, 0, 0, $background);

        foreach ($paths as $index => $path) {
            $col = $index % $cols;
            $row = intdiv($index, $cols);
            $x = $this->gutter + $col * ($cell + $this->gutter);
            $y = $this->gutter + $row * ($cell + $this->gutter);

            $this->placeImage($canvas, $path, $x, $y, $cell);
            $this->drawLabel($canvas, (string) ($index + 1), $x, $y);
        }

        $out = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'contact-sheet-' . Str::uuid()->toString() . '.jpg';

        if (! imagejpeg($canvas, $out, 85)) {
            imagedestroy($canvas);
            throw new RuntimeException('Failed to write contact sheet JPEG.');
        }
        imagedestroy($canvas);

        return $out;
    }

    private function placeImage(GdImage $canvas, string $path, int $x, int $y, int $cell): void
    {
        $contents = @file_get_contents($path);
        $src = $contents !== false ? @imagecreatefromstring($contents) : false;

        if (! $src) {
            // Unreadable image: leave the grey cell so the numbering still lines up.
            return;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);
            return;
        }

        $scale = min($cell / $srcW, $cell / $srcH);
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));
        $dstX = $x + intdiv($cell - $dstW, 2);
        $dstY = $y + intdiv($cell - $dstH, 2);

        imagecopyresampled($canvas, $src, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);
    }

    /**
     * White number on a black box, rendered at 2x so it stays legible after the
     * provider downsamples the sheet.
     */
    private function drawLabel(GdImage $canvas, string $text, int $x, int $y): void
    {
        $glyphW = imagefontwidth(self::FONT);
        $glyphH = imagefontheight(self::FONT);
        $pad = 3;
        $w = strlen($text) * $glyphW + $pad * 2;
        $h = $glyphH + $pad * 2;

        $label = imagecreatetruecolor($w, $h);
        if (! $label) {
            return;
        }
        $black = imagecolorallocate($label, 0, 0, 0);
        $white = imagecolorallocate($label, 255, 255, 255);
        imagefilledrectangle($label, 0, 0, $w - 1, $h - 1, $black);
        imagestring($label, self::FONT, $pad, $pad, $text, $white);

        imagecopyresized($canvas, $label, $x, $y, 0, 0, $w * 2, $h * 2, $w, $h);
        imagedestroy($label);
    }
}
