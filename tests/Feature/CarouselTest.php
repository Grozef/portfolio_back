<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\CarouselImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CarouselTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_returns_active_images()
    {
        CarouselImage::create(['title' => 'T', 'image_url' => 'https://t.fr', 'is_active' => true]);

        $response = $this->getJson('/api/v1/carousel');
        $response->assertStatus(200)->assertJsonStructure(['success', 'data']);
    }

    public function test_upload_works_for_admin()
    {
        $user = User::create(['name' => 'A', 'email' => 'admin_car@test.com', 'password' => 'p']);
        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($user)->postJson('/api/v1/carousel/upload', ['image' => $file]);

        $response->assertStatus(200);
        $this->assertArrayHasKey('url', $response->json('data'));

        // Nettoyage du fichier réel créé dans public/carousel
        $path = public_path($response->json('data.url'));
        if (file_exists($path)) @unlink($path);
    }
}
