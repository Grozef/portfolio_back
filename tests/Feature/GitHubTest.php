<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GitHubTest extends TestCase
{
protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // On définit la config pour TOUS les tests de la classe
        config(['services.github.username' => 'testuser']);
        config(['services.github.token' => 'fake-token']);
    }

    /**
     * Test Profil + Service (getUserProfile)
     */
    public function test_github_profile_returns_real_service_data()
    {
        Http::fake([
            'https://api.github.com/users/*' => Http::response([
                'login' => 'testuser',
                'name' => 'Test User',
                'avatar_url' => 'https://avatars.com/1',
                'public_repos' => 10
            ], 200)
        ]);

        $response = $this->getJson('/api/v1/github/profile');

        $response->assertStatus(200)
                 ->assertJsonPath('data.login', 'testuser');
    }

    /**
     * Test Repositories + Formatting (getRepositories + formatRepository)
     */
    public function test_github_repositories_list_and_formatting()
    {
        Http::fake([
            'https://api.github.com/users/*/repos*' => Http::response([
                [
                    'id' => 1,
                    'name' => 'my-repo',
                    'full_name' => 'testuser/my-repo',
                    'stargazers_count' => 5,
                    'forks_count' => 2,
                    'watchers_count' => 5,
                    'open_issues_count' => 0,
                    'fork' => false,
                    'archived' => false,
                    'created_at' => '2026-01-01',
                    'updated_at' => '2026-01-01',
                    'pushed_at' => '2026-01-01',
                    'description' => 'Desc',
                    'html_url' => '...',
                    'clone_url' => '...',
                    'homepage' => '...',
                    'language' => 'PHP'
                ]
            ], 200)
        ]);

        $response = $this->getJson('/api/v1/github/repositories');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'my-repo');
    }

    /**
     * Test Pinned Repos (Couvre le bloc GraphQL du Service)
     */
public function test_github_pinned_repositories_uses_graphql()
    {
        Http::fake([
            '*/graphql' => Http::response([
                'data' => [
                    'user' => [
                        'pinnedItems' => [
                            'nodes' => [
                                [
                                    'name' => 'PinnedRepo',
                                    'description' => 'Pinned!',
                                    'url' => 'https://github.com/pinned',
                                    'stargazerCount' => 10,
                                    'forkCount' => 2,
                                    'primaryLanguage' => ['name' => 'Blade', 'color' => '#f00']
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/github/repositories/pinned');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.name', 'PinnedRepo');
    }

    /**
     * Test du fallback README : main échoue, master réussit.
     */
public function test_github_readme_fallback_from_main_to_master()
    {
        $repo = 'old-repo';

        Http::fake([
            // On utilise des wildcards (*) pour être sûr de matcher l'URL construite par le service
            "*/repos/testuser/{$repo}" => Http::response([
                'id' => 1, 'name' => $repo, 'full_name' => "testuser/$repo",
                'stargazers_count' => 0, 'forks_count' => 0, 'watchers_count' => 0,
                'open_issues_count' => 0, 'fork' => false, 'archived' => false,
                'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(), 'pushed_at' => now()->toIso8601String(),
                'description' => null, 'html_url' => '', 'clone_url' => '', 'homepage' => '', 'language' => 'PHP'
            ], 200),
            "*/repos/testuser/{$repo}/languages" => Http::response(['PHP' => 100], 200),
            "*/repos/testuser/{$repo}/readme?ref=main" => Http::response([], 404),
            "*/repos/testuser/{$repo}/readme?ref=master" => Http::response(['content' => base64_encode('Documentation Master')], 200),
        ]);

        $response = $this->getJson("/api/v1/github/repositories/{$repo}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.readme', 'Documentation Master');
    }

/**
     * Test des langages
     */
    public function test_github_languages_returns_formatted_percentages()
    {
        $repo = 'my-repo';
        Http::fake([
            "*/repos/testuser/{$repo}/languages" => Http::response([
                'PHP' => 8000,
                'JS' => 2000
            ], 200),
        ]);

        $response = $this->getJson("/api/v1/github/repositories/{$repo}/languages");

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.name', 'PHP')
                 // On change 80.0 en 80 pour satisfaire le typage JSON
                 ->assertJsonPath('data.0.percentage', 80);
    }

    /**
     * Test de l'échec de makeRequest (Exception ou 500).
     * Couvre les blocs catch et null returns (Lignes 270+).
     */
    public function test_github_service_handles_api_errors_gracefully()
    {
        Http::fake([
            'https://api.github.com/*' => Http::response([], 500)
        ]);

        $response = $this->getJson('/api/v1/github/profile');

        // Ton contrôleur renvoie un tableau vide [] en cas d'échec du service
        $response->assertStatus(200)
                 ->assertJsonPath('data', []);
    }
}
