<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadEventImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(public readonly Event $event)
    {
        $this->onQueue('enrichment');
    }

    public function handle(): void
    {
        $event = $this->event->fresh();

        if ($event === null || $event->image_url === null) {
            return;
        }

        if (str_starts_with($event->image_url, '/storage/')) {
            return;
        }

        $response = Http::timeout(15)->get($event->image_url);

        if ($response->failed()) {
            Log::warning('DownloadEventImageJob: failed to download image', [
                'event_id' => $event->id,
                'url' => $event->image_url,
                'status' => $response->status(),
            ]);

            return;
        }

        $contentType = $response->header('Content-Type');
        $ext = match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            default => 'jpg',
        };

        $path = "events/{$event->id}.{$ext}";
        Storage::disk('public')->put($path, $response->body());
        $event->update(['image_url' => Storage::disk('public')->url($path)]);

        Log::info('DownloadEventImageJob: image saved', [
            'event_id' => $event->id,
            'path' => $path,
        ]);
    }
}
