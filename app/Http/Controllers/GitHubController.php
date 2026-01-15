<?php

namespace App\Http\Controllers;

use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controleur GitHubController - Integration GitHub.
 * 
 * Expose les repositories GitHub du portfolio via l'API.
 * Utilise le GitHubService pour communiquer avec l'API GitHub.
 *
 * @package App\Http\Controllers
 */
class GitHubController extends Controller
{
    /**
     * Service GitHub injecte.
     *
     * @var GitHubService
     */
    private GitHubService $githubService;

    /**
     * Constructeur avec injection de dependance.
     *
     * @param GitHubService $githubService
     */
    public function __construct(GitHubService $githubService)
    {
        $this->githubService = $githubService;
    }

    /**
     * Liste les repositories avec pagination.
     *
     * @param Request $request
     * @return JsonResponse Liste des repositories
     * 
     * @queryParam per_page integer Nombre par page (defaut: 30)
     * @queryParam page integer Numero de page (defaut: 1)
     */
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

    /**
     * Affiche un repository specifique avec son README.
     *
     * @param string $name Nom du repository
     * @return JsonResponse Detail du repository
     */
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

    /**
     * Liste les repositories epingles (pinned).
     *
     * @return JsonResponse Repositories epingles
     */
    public function pinned(): JsonResponse
    {
        $pinned = $this->githubService->getPinnedRepositories();

        return response()->json([
            'success' => true,
            'data' => $pinned,
        ]);
    }

    /**
     * Retourne le profil GitHub de l'utilisateur.
     *
     * @return JsonResponse Profil utilisateur
     */
    public function profile(): JsonResponse
    {
        $profile = $this->githubService->getUserProfile();

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    /**
     * Retourne les langages d'un repository.
     *
     * @param string $name Nom du repository
     * @return JsonResponse Langages avec pourcentages
     */
    public function languages(string $name): JsonResponse
    {
        $languages = $this->githubService->getLanguages($name);

        return response()->json([
            'success' => true,
            'data' => $languages,
        ]);
    }
}
