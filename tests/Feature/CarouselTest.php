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
        // 1. On crée un admin (important si ta route est protégée par le middleware 'admin')
        $user = User::create([
            'name' => 'Admin Carousel',
            'email' => 'admin_car@test.com',
            'password' => 'password',
            'is_admin' => true // S'assurer qu'il a les droits
        ]);

        // 2. On génère une image factice aux bonnes dimensions (Largeur, Hauteur)
        // Ici 640x480 pour passer la barre des 400x300
        $file = UploadedFile::fake()->image('photo.jpg', 640, 480);

        $response = $this->actingAs($user)->postJson('/api/v1/carousel/upload', [
            'image' => $file
        ]);

        // 3. Assertions
        $response->assertStatus(200);
        $this->assertArrayHasKey('url', $response->json('data'));

        // Nettoyage
        $path = public_path($response->json('data.url'));
        if (file_exists($path)) @unlink($path);
    }
}
