<?php

namespace App\Http\Controllers;

use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubController extends Controller
{
    public function __construct(
        private GitHubService $githubService
    ) {}

    public function repositories(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 30);
        $page = $request->input('page', 1);

        $repositories = $this->githubService->getRepositories($perPage, $page);

        return response()->json([
            'success' => true,
            'data' => $repositories,
            'meta' => [
                'per_page' => $perPage,
                'page' => $page,
            ],
        ]);
    }

    public function repository(string $name): JsonResponse
    {
        $repository = $this->githubService->getRepository($name);

        if (!$repository) {
            return response()->json([
                'success' => false,
                'message' => 'Repository not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $repository,
        ]);
    }

    public function pinned(): JsonResponse
    {
        $pinned = $this->githubService->getPinnedRepositories();

        return response()->json([
            'success' => true,
            'data' => $pinned,
        ]);
    }

    public function profile(): JsonResponse
    {
        $profile = $this->githubService->getUserProfile();

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    public function languages(string $name): JsonResponse
    {
        $languages = $this->githubService->getLanguages($name);

        return response()->json([
            'success' => true,
            'data' => $languages,
        ]);
    }
}
