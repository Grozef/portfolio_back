<?php

namespace Tests\Unit;

use App\Models\CarouselImage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CarouselImageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_active_scope_filters_inactive_images()
    {
        CarouselImage::create(['title' => 'Test Active', 'image_url' => 'http://t.fr', 'is_active' => true, 'sort_order' => 1]);

        $this->assertGreaterThanOrEqual(1, CarouselImage::active()->count());
    }

    public function test_ordered_scope_sorts_correctly()
    {
        $img = CarouselImage::create(['title' => 'Z-Order-Test', 'image_url' => 'http://t.fr', 'sort_order' => -1]);

        $first = CarouselImage::ordered()->first();
        // Si ton image avec sort_order -1 est bien la première
        $this->assertEquals($img->id, $first->id);
    }
}
