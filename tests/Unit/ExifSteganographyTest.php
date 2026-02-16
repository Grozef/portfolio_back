<?php

namespace Tests\Unit;

use App\Services\ExifSteganographyService;
// use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExifSteganographyTest extends TestCase
{
    private ExifSteganographyService $service;
    private string $testImagePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExifSteganographyService();

        // Création d'une image JPEG minimale valide pour les tests
        $this->testImagePath = storage_path('app/test_easter_egg.jpg');
        $img = imagecreatetruecolor(10, 10);
        imagejpeg($img, $this->testImagePath);
        imagedestroy($img);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testImagePath)) {
            unlink($this->testImagePath);
        }
        parent::tearDown();
    }

    public function test_can_embed_and_read_secret_message()
    {
        $secret = "SECRET_CODE_123";

        // 1. On écrit le message
        $result = $this->service->addSecretMessage($this->testImagePath, $secret);
        $this->assertTrue($result);

        // 2. On le relit
        $data = $this->service->readSecretMessage($this->testImagePath);

        $this->assertIsArray($data);
        $this->assertEquals($secret, $data['message']);
        $this->assertEquals('Easter Egg Found! 🥚', $data['copyright']);
    }

    public function test_process_carousel_image_adds_default_secret()
    {
        $this->service->processCarouselImage($this->testImagePath);
        $data = $this->service->readSecretMessage($this->testImagePath);

        $this->assertStringContainsString('EXIF-2026-HIDDEN-TREASURE', $data['message']);
    }

    public function test_read_non_existent_file_returns_null()
    {
        $this->assertNull($this->service->readSecretMessage('non_existent.jpg'));
    }

    public function test_throws_exception_if_image_not_found()
{
    $this->expectException(\Exception::class);
    $this->service->addSecretMessage('path/to/fake/image.jpg', 'message');
}

public function test_handles_very_long_messages()
{
    // Un message de plus de 32768 octets pour forcer le passage dans le bloc 'else' du service
    $longMessage = str_repeat('A', 33000);

    $result = $this->service->addSecretMessage($this->testImagePath, $longMessage);
    $this->assertTrue($result);

    $data = $this->service->readSecretMessage($this->testImagePath);
    $this->assertEquals($longMessage, $data['message']);
}

}
