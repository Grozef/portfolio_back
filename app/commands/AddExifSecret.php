<?php

namespace App\Commands;

use App\Services\ExifSteganographyService;
use Illuminate\Console\Command;

/**
 * Add EXIF Steganography Command
 *
 * Usage: php artisan exif:add-secret {image-path} {--message=}
 */
class AddExifSecret extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exif:add-secret
                            {image : Path to the image file (relative to public/)}
                            {--message= : Custom secret message (optional)}
                            {--output= : Output path (optional, defaults to overwriting)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add secret message to image EXIF metadata for Easter Egg #17';

    /**
     * Execute the console command.
     */
    public function handle(ExifSteganographyService $exifService): int
    {
        $imagePath = public_path($this->argument('image'));
        $customMessage = $this->option('message');
        $outputPath = $this->option('output') ? public_path($this->option('output')) : null;

        if (!file_exists($imagePath)) {
            $this->error("Image not found: {$imagePath}");
            return Command::FAILURE;
        }

        $this->info("Adding EXIF steganography to: {$imagePath}");

        try {
            if ($customMessage) {
                $success = $exifService->addSecretMessage($imagePath, $customMessage, $outputPath);
            } else {
                $success = $exifService->processCarouselImage($imagePath);
            }

            if ($success) {
                $this->info("✓ Secret message successfully embedded in EXIF metadata!");
                $this->line("");
                $this->line("To verify, users can:");
                $this->line("1. Download the image");
                $this->line("2. Right-click > Properties > Details");
                $this->line("3. Or use: exiftool {$imagePath}");
                $this->line("4. Or online: https://exif.regex.info/exif.cgi");

                return Command::SUCCESS;
            } else {
                $this->error("Failed to add EXIF data");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
