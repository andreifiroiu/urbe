<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Services\Recommendation\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecommendationController extends Controller
{
    public function __construct(
        private readonly RecommendationEngine $recommendationEngine,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $recommendations = $this->recommendationEngine->recommend($user);

        return Inertia::render('Dashboard/Index', [
            'recommendations' => EventResource::collection($recommendations),
        ]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        $recommendations = $this->recommendationEngine->recommend($user);

        return EventResource::collection($recommendations)->response();
    }
}
