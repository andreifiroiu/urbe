<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Event::upcoming()->orderBy('starts_at', 'asc');

        if ($request->filled('search')) {
            $searchIds = Event::search($request->string('search')->toString())
                ->keys();
            $query->whereIn('id', $searchIds);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('city')) {
            $query->where('city', $request->string('city')->toString());
        }

        $events = $query->paginate(20)->withQueryString();

        return Inertia::render('Events/Index', [
            'events' => EventResource::collection($events),
            'filters' => $request->only(['search', 'category', 'city']),
        ]);
    }

    public function show(Event $event): Response
    {
        return Inertia::render('Events/Show', [
            'event' => new EventResource($event),
        ]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $query = Event::upcoming()->orderBy('starts_at', 'asc');

        if ($request->filled('search')) {
            $searchIds = Event::search($request->string('search')->toString())
                ->keys();
            $query->whereIn('id', $searchIds);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('city')) {
            $query->where('city', $request->string('city')->toString());
        }

        $events = $query->paginate(20)->withQueryString();

        return EventResource::collection($events)->response();
    }

    public function apiShow(Event $event): JsonResponse
    {
        return (new EventResource($event))->response();
    }
}
