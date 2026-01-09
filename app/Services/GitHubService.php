<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GitHubService
{
    private string $baseUrl = 'https://api.github.com';
    private ?string $token;
    private string $username;

    public function __construct()
    {
        $this->token = config('services.github.token');
        $this->username = config('services.github.username');
    }

    public function getRepositories(int $perPage = 30, int $page = 1): array
    {
        $cacheKey = "github_repos_{$this->username}_{$perPage}_{$page}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($perPage, $page) {
            $response = $this->makeRequest("/users/{$this->username}/repos", [
                'per_page' => $perPage,
                'page' => $page,
                'sort' => 'updated',
                'direction' => 'desc',
            ]);

            if (!$response) {
                return [];
            }

            return array_map(fn($repo) => $this->formatRepository($repo), $response);
        });
    }

    public function getRepository(string $name): ?array
    {
        $cacheKey = "github_repo_{$this->username}_{$name}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($name) {
            $repo = $this->makeRequest("/repos/{$this->username}/{$name}");

            if (!$repo) {
                return null;
            }

            $readme = $this->getReadme($name);
            $languages = $this->getLanguages($name);

            return array_merge($this->formatRepository($repo), [
                'readme' => $readme,
                'languages' => $languages,
            ]);
        });
    }

    public function getReadme(string $repoName): ?string
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/repos/{$this->username}/{$repoName}/readme", [
                    'ref' => 'main',
                ]);

            if ($response->successful()) {
                $content = $response->json('content');
                return base64_decode($content);
            }

            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/repos/{$this->username}/{$repoName}/readme", [
                    'ref' => 'master',
                ]);

            if ($response->successful()) {
                $content = $response->json('content');
                return base64_decode($content);
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    public function getLanguages(string $repoName): array
    {
        $cacheKey = "github_languages_{$this->username}_{$repoName}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($repoName) {
            $response = $this->makeRequest("/repos/{$this->username}/{$repoName}/languages");

            if (!$response) {
                return [];
            }

            $total = array_sum($response);
            if ($total === 0) {
                return [];
            }

            $languages = [];

            foreach ($response as $lang => $bytes) {
                $languages[] = [
                    'name' => $lang,
                    'percentage' => round(($bytes / $total) * 100, 1),
                    'bytes' => $bytes,
                ];
            }

            usort($languages, fn($a, $b) => $b['percentage'] <=> $a['percentage']);

            return $languages;
        });
    }

    public function getUserProfile(): array
    {
        $cacheKey = "github_profile_{$this->username}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () {
            $profile = $this->makeRequest("/users/{$this->username}");

            if (!$profile) {
                return [];
            }

            return [
                'login' => $profile['login'] ?? '',
                'name' => $profile['name'] ?? '',
                'avatar_url' => $profile['avatar_url'] ?? '',
                'bio' => $profile['bio'] ?? '',
                'location' => $profile['location'] ?? '',
                'blog' => $profile['blog'] ?? '',
                'public_repos' => $profile['public_repos'] ?? 0,
                'followers' => $profile['followers'] ?? 0,
                'following' => $profile['following'] ?? 0,
                'created_at' => $profile['created_at'] ?? '',
                'html_url' => $profile['html_url'] ?? '',
            ];
        });
    }

    public function getPinnedRepositories(): array
    {
        $cacheKey = "github_pinned_{$this->username}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () {
            if (!$this->token) {
                return $this->getRepositories(6);
            }

            $query = <<<GQL
            {
                user(login: "{$this->username}") {
                    pinnedItems(first: 6, types: REPOSITORY) {
                        nodes {
                            ... on Repository {
                                name
                                description
                                url
                                stargazerCount
                                forkCount
                                primaryLanguage {
                                    name
                                    color
                                }
                            }
                        }
                    }
                }
            }
            GQL;

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->token}",
                'Content-Type' => 'application/json',
            ])->post('https://api.github.com/graphql', [
                'query' => $query,
            ]);

            if ($response->successful()) {
                $nodes = $response->json('data.user.pinnedItems.nodes') ?? [];
                return array_map(fn($node) => [
                    'name' => $node['name'],
                    'description' => $node['description'],
                    'url' => $node['url'],
                    'stars' => $node['stargazerCount'],
                    'forks' => $node['forkCount'],
                    'language' => $node['primaryLanguage']['name'] ?? null,
                    'language_color' => $node['primaryLanguage']['color'] ?? null,
                ], $nodes);
            }

            return $this->getRepositories(6);
        });
    }

    private function makeRequest(string $endpoint, array $query = []): ?array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}{$endpoint}", $query);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            report($e);
        }

        return null;
    }

    private function getHeaders(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Portfolio-App',
        ];

        if ($this->token) {
            $headers['Authorization'] = "Bearer {$this->token}";
        }

        return $headers;
    }

    private function formatRepository(array $repo): array
    {
        return [
            'id' => $repo['id'],
            'name' => $repo['name'],
            'full_name' => $repo['full_name'],
            'description' => $repo['description'],
            'html_url' => $repo['html_url'],
            'clone_url' => $repo['clone_url'],
            'homepage' => $repo['homepage'],
            'language' => $repo['language'],
            'stars' => $repo['stargazers_count'],
            'forks' => $repo['forks_count'],
            'watchers' => $repo['watchers_count'],
            'open_issues' => $repo['open_issues_count'],
            'is_fork' => $repo['fork'],
            'is_archived' => $repo['archived'],
            'topics' => $repo['topics'] ?? [],
            'created_at' => $repo['created_at'],
            'updated_at' => $repo['updated_at'],
            'pushed_at' => $repo['pushed_at'],
        ];
    }
}
