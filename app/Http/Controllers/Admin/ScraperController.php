<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunScraperJob;
use App\Models\ScraperRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScraperController extends Controller
{
    public function index(Request $request): Response
    {
        $runs = ScraperRun::query()
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return Inertia::render('Admin/Scrapers', [
            'runs' => $runs,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $source = $request->input('source');

        RunScraperJob::dispatch($source);

        return redirect()->route('admin.scrapers.index')
            ->with('success', 'Scraper run queued' . ($source ? " for {$source}" : ' for all sources') . '.');
    }
}
