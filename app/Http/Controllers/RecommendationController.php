<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
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

        $batch = $this->recommendationEngine->recommend($user);

        $recommendations = Event::whereIn('id', $batch->recommendedEventIds)->get();
        $discoveryEvents = Event::whereIn('id', $batch->discoveryEventIds)->get();

        return Inertia::render('Dashboard/Index', [
            'recommendations' => EventResource::collection($recommendations)->resolve(),
            'discoveryEvents' => EventResource::collection($discoveryEvents)->resolve(),
        ]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        $batch = $this->recommendationEngine->recommend($user);

        $recommendations = Event::whereIn('id', $batch->recommendedEventIds)->get();

        return response()->json([
            'recommendations' => EventResource::collection($recommendations),
            'discovery' => EventResource::collection(
                Event::whereIn('id', $batch->discoveryEventIds)->get(),
            ),
            'total_score' => $batch->totalScore,
        ]);
    }
}
