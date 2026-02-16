<?php
namespace Tests\Unit;
use App\Models\CarouselImage;
use App\Models\ContactMessage;
use App\Models\CookiePreference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MiscModelTest extends TestCase {
    use DatabaseTransactions;

    public function test_carousel_active_scope() {
        CarouselImage::create(['title' => 'T', 'image_url' => '/', 'is_active' => true]);
        $this->assertGreaterThanOrEqual(1, CarouselImage::active()->count());
    }

    public function test_contact_unread_scope() {
        ContactMessage::create(['name' => 'T', 'email' => 't@t.com', 'message' => 'M', 'read_at' => null]);
        $this->assertGreaterThanOrEqual(1, ContactMessage::unread()->count());
    }

    public function test_cookie_preference_expiration() {
        $pref = new CookiePreference(['expires_at' => now()->subDay()]);
        $this->assertTrue($pref->isExpired());
    }
}
