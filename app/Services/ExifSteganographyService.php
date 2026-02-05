<?php

namespace App\Services;

/**
 * EXIF Steganography Service
 *
 * Adds hidden messages to image EXIF metadata
 * Easter Egg #17: EXIF Secret
 */
class ExifSteganographyService
{
    /**
     * Add hidden message to image EXIF metadata
     *
     * @param string $imagePath Path to the image file
     * @param string $secretMessage The hidden message to embed
     * @param string $outputPath Optional output path (defaults to overwriting original)
     * @return bool Success status
     */
    public function addSecretMessage(string $imagePath, string $secretMessage, ?string $outputPath = null): bool
    {
        if (!file_exists($imagePath)) {
            throw new \Exception("Image file not found: {$imagePath}");
        }

        $outputPath = $outputPath ?? $imagePath;

        // Read existing EXIF data
        $exif = @exif_read_data($imagePath);

        // Prepare IPTC data with secret message
        $iptc = [
            '2#120' => $secretMessage,  // Caption/Abstract
            '2#116' => 'Easter Egg Found! 🥚',  // Copyright notice
            '2#105' => 'Portfolio Secret',  // Headline
            '2#040' => 'HIDDEN_MESSAGE',  // Special instructions
        ];

        // Convert IPTC array to binary format
        $iptcData = '';
        foreach ($iptc as $tag => $value) {
            $tag = substr($tag, 2);
            $iptcData .= $this->iptcMakeTag(2, $tag, $value);
        }

        // Read image data
        $imageData = file_get_contents($imagePath);

        // Find APP13 marker (Photoshop IPTC)
        $psHeader = chr(0xFF) . chr(0xED);
        $iptcHeader = "Photoshop 3.0\0";
        $iptcTag = chr(0x04) . chr(0x04);

        // Build IPTC block
        $iptcBlock = $iptcTag . pack('n', strlen($iptcData)) . $iptcData;
        $psData = $psHeader . pack('n', strlen($iptcHeader) + strlen($iptcBlock) + 2) .
                  $iptcHeader . $iptcBlock;

        // Check if image already has IPTC data
        if (strpos($imageData, $psHeader) !== false) {
            // Replace existing IPTC data
            $imageData = preg_replace(
                '/\xFF\xED.{2}Photoshop 3\.0\x00.*?\x04\x04.{2}.*?(?=\xFF|$)/s',
                $psData,
                $imageData,
                1
            );
        } else {
            // Insert IPTC data after JPEG header
            $imageData = substr_replace($imageData, $psData, 2, 0);
        }

        // Save modified image
        return file_put_contents($outputPath, $imageData) !== false;
    }

    /**
     * Read secret message from image EXIF metadata
     *
     * @param string $imagePath Path to the image file
     * @return array|null Secret message data or null if not found
     */
    public function readSecretMessage(string $imagePath): ?array
    {
        if (!file_exists($imagePath)) {
            return null;
        }

        $size = getimagesize($imagePath, $info);

        if (!isset($info['APP13'])) {
            return null;
        }

        $iptc = iptcparse($info['APP13']);

        if (!$iptc) {
            return null;
        }

        return [
            'message' => $iptc['2#120'][0] ?? null,
            'copyright' => $iptc['2#116'][0] ?? null,
            'headline' => $iptc['2#105'][0] ?? null,
            'instructions' => $iptc['2#040'][0] ?? null,
        ];
    }

    /**
     * Create IPTC tag in binary format
     *
     * @param int $rec Record number
     * @param int $tag Dataset number
     * @param string $value Value to encode
     * @return string Binary IPTC tag
     */
    private function iptcMakeTag(int $rec, int $tag, string $value): string
    {
        $length = strlen($value);
        $retval = chr(0x1C) . chr($rec) . chr($tag);

        if ($length < 0x8000) {
            $retval .= chr($length >> 8) . chr($length & 0xFF);
        } else {
            $retval .= chr(0x80) . chr(0x04) .
                      chr(($length >> 24) & 0xFF) .
                      chr(($length >> 16) & 0xFF) .
                      chr(($length >> 8) & 0xFF) .
                      chr($length & 0xFF);
        }

        return $retval . $value;
    }

    /**
     * Process carousel image with secret message
     *
     * @param string $imagePath Path to carousel image
     * @return bool Success status
     */
    public function processCarouselImage(string $imagePath): bool
    {
        $secretMessage = "Congratulations! You found the EXIF easter egg. Secret code: EXIF-2026-HIDDEN-TREASURE";

        return $this->addSecretMessage($imagePath, $secretMessage);
    }
}
