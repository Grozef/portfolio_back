<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CarouselImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CarouselTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);

        // On s'assure que le dossier public/carousel existe pour les tests
        if (!File::isDirectory(public_path('carousel'))) {
            File::makeDirectory(public_path('carousel'), 0755, true);
        }
    }

    /**
     * Test l'index et le filtrage des fichiers fantômes
     */
    public function test_index_filters_out_images_with_missing_files()
    {
        // 1. Une image avec un fichier réel
        $filename = 'exists.jpg';
        File::put(public_path('carousel/' . $filename), 'fake content');
        CarouselImage::create(['title' => 'Real', 'image_url' => '/carousel/'.$filename, 'is_active' => true, 'sort_order' => 1]);

        // 2. Une image dont le fichier a été supprimé manuellement
        CarouselImage::create(['title' => 'Ghost', 'image_url' => '/carousel/ghost.jpg', 'is_active' => true, 'sort_order' => 2]);

        $response = $this->getJson('/api/v1/carousel');

        // Seule l'image 'Real' doit apparaître
        $response->assertStatus(200)->assertJsonCount(1, 'data');

        // Nettoyage
        File::delete(public_path('carousel/' . $filename));
    }

    /**
     * Test l'upload et la création du dossier
     * Couvre: upload(), is_dir, mkdir, file_put_contents
     */
    public function test_upload_physically_saves_file()
    {
        $file = UploadedFile::fake()->image('carousel.jpg', 600, 600);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/carousel/upload', [
            'image' => $file
        ]);

        $response->assertStatus(200);
        $path = public_path($response->json('data.url'));
        $this->assertFileExists($path);

        @unlink($path); // Nettoyage
    }

    /**
     * Test le stockage en BDD
     * Couvre: store(), StoreCarouselImageRequest
     */
    public function test_store_creates_database_record()
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/carousel', [
            'title' => 'New Slide',
            'image_url' => '/carousel/image.jpg',
            'is_active' => true
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('carousel_images', ['title' => 'New Slide']);
    }

    /**
     * Test le réordonnancement
     * Couvre: reorder(), validation array, foreach loop
     */
    public function test_reorder_updates_sort_orders()
    {
        $img1 = CarouselImage::create(['title' => 'A', 'image_url' => '/c/1.jpg', 'sort_order' => 1]);
        $img2 = CarouselImage::create(['title' => 'B', 'image_url' => '/c/2.jpg', 'sort_order' => 2]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/carousel/reorder', [
            'order' => [$img2->id, $img1->id]
        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, $img2->fresh()->sort_order);
        $this->assertEquals(1, $img1->fresh()->sort_order);
    }

    /**
     * Test la suppression totale
     * Couvre: destroy(), unlink(), delete()
     */
    public function test_destroy_deletes_record_and_file()
    {
        $filename = 'delete_me.jpg';
        $path = public_path('carousel/' . $filename);
        File::put($path, 'content');
        $img = CarouselImage::create(['title' => 'Del', 'image_url' => '/carousel/'.$filename]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/carousel/{$img->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('carousel_images', ['id' => $img->id]);
        $this->assertFileDoesNotExist($path);
    }

    /**
     * Teste l'échec de l'upload
     */
public function test_upload_fails_gracefully_on_disk_error()
    {
        /** @var \App\Models\User $admin */
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('valid.jpg', 1200, 1200);

        $path = public_path('carousel');
        if (File::exists($path)) File::deleteDirectory($path);

        // Sabotage : On crée un fichier texte nommé 'carousel' pour empêcher la création du dossier
        File::put($path, 'BLOCK');

        $response = $this->actingAs($admin)->postJson('/api/v1/carousel/upload', [
            'image' => $file
        ]);

        // Nettoyage
        File::delete($path);
        File::makeDirectory($path, 0755, true);

        $response->assertStatus(500);
    }
}
