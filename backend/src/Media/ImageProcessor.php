<?php

declare(strict_types=1);

namespace App\Media;

/**
 * Turns an uploaded image into the WebP copies pages show (GD: pure PHP, available on shared hosting). Each width in
 * $widths narrower than the original gets a copy, and the original's own width (up to the widest) always does, so a
 * small image is never blown up.
 */
final class ImageProcessor
{
    private const QUALITY = 80;

    /**
     * @param list<int> $widths
     *
     * @return list<int> the widths written, narrowest first
     */
    public function writeVariants(string $source, string $contentType, string $directory, array $widths): array
    {
        $image = match ($contentType) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/webp' => @imagecreatefromwebp($source),
            default => false,
        };
        if (false === $image) {
            throw new \RuntimeException('The image could not be read.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $targets = array_values(array_filter($widths, static fn (int $w) => $w < $width));
        $targets[] = min($width, max($widths));
        $targets = array_values(array_unique($targets));
        sort($targets);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Cannot create "%s".', $directory));
        }
        foreach ($targets as $target) {
            $resized = $target === $width ? $image : imagescale($image, $target, (int) round($height * $target / $width), \IMG_BICUBIC);
            if (false === $resized) {
                throw new \RuntimeException('The image could not be resized.');
            }
            // Transparent PNGs keep their transparency in WebP.
            imagepalettetotruecolor($resized);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            if (!imagewebp($resized, $directory.'/'.$target.'.webp', self::QUALITY)) {
                throw new \RuntimeException('The image could not be written.');
            }
        }

        return $targets;
    }
}
