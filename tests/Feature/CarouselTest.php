<?php

namespace Tests\Feature;

use App\Models\{User, CarouselImage};
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CarouselTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /**
     * Test l'upload : vérifie le stockage physique
     */
    public function test_upload_physically_saves_file()
    {
        $file = UploadedFile::fake()->image('carousel.jpg', 1920, 1080);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/carousel/upload', [
            'image' => $file
        ]);

        $response->assertStatus(200);
        $filename = $response->json('data.filename');

        // Utilise bien Storage::disk('public') pour l'assertion
        Storage::disk('public')->assertExists('carousel/' . $filename);
    }

    /**
     * Test le store (pour couvrir StoreCarouselImageRequest)
     */
    public function test_store_creates_database_record()
    {
        $payload = [
            'title' => 'Mon titre',
            'image_url' => '/storage/carousel/mon_image.jpg',
            'sort_order' => 5,
            'is_active' => true
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/carousel', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('data.title', 'Mon titre');

        $this->assertDatabaseHas('carousel_images', ['title' => 'Mon titre']);
    }

    /**
     * Test la suppression et le nettoyage du disque
     */
    public function test_destroy_deletes_record_and_file()
    {
        // On simule un fichier existant
        Storage::disk('public')->put('carousel/test_del.jpg', 'content');

        $img = CarouselImage::create([
            'title' => 'A supprimer',
            'image_url' => '/storage/carousel/test_del.jpg'
        ]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/carousel/{$img->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('carousel_images', ['id' => $img->id]);

        // On vérifie que le fichier physique est bien parti
        Storage::disk('public')->assertMissing('carousel/test_del.jpg');
    }

    /**
     * Test de réordonnancement
     */
    public function test_reorder_updates_sort_orders()
    {
        $img1 = CarouselImage::create(['title' => 'A', 'image_url' => '1.jpg', 'sort_order' => 1]);
        $img2 = CarouselImage::create(['title' => 'B', 'image_url' => '2.jpg', 'sort_order' => 2]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/carousel/reorder', [
                'order' => [$img2->id, $img1->id]
            ]);

        $response->assertStatus(200);
        $this->assertEquals(0, $img2->fresh()->sort_order);
        $this->assertEquals(1, $img1->fresh()->sort_order);
    }
}
