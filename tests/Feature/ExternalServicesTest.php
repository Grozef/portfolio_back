<?php
namespace Tests\Feature;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalServicesTest extends TestCase {
    public function test_github_profile_mocked() {
        $mock = $this->getMockBuilder(GitHubService::class)->disableOriginalConstructor()->getMock();
        $mock->expects($this->once())->method('getUserProfile')->willReturn(['login' => 'testuser']);
        $this->app->instance(GitHubService::class, $mock);

        $this->getJson('/api/v1/github/profile')->assertStatus(200)->assertJsonPath('data.login', 'testuser');
    }

    public function test_weather_mocked() {
        Http::fake(['api.openweathermap.org/*' => Http::response(['main' => ['temp' => 20]], 200)]);
        $this->getJson('/api/v1/weather')->assertStatus(200)->assertJsonPath('main.temp', 20);
    }
}
