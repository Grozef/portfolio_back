<?php
namespace Tests\Feature;

use App\Services\GitHubService;
use Tests\TestCase;

class GitHubTest extends TestCase
{
    public function test_github_profile_returns_data()
    {
        // Version native PHPUnit (Pas besoin de Mockery)
        $mock = $this->getMockBuilder(GitHubService::class)
                     ->disableOriginalConstructor()
                     ->getMock();

        $mock->expects($this->once())
             ->method('getUserProfile')
             ->willReturn(['login' => 'testuser']);

        // On injecte le mock dans le container de Laravel
        $this->app->instance(GitHubService::class, $mock);

        $response = $this->getJson('/api/v1/github/profile');
        $response->assertStatus(200)->assertJsonPath('data.login', 'testuser');
    }
}
