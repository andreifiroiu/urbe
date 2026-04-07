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

        $parsedUrl = parse_url($event->image_url);
        if (($parsedUrl['scheme'] ?? '') !== 'https') {
            Log::warning('DownloadEventImageJob: skipping non-HTTPS URL', ['event_id' => $event->id]);

            return;
        }

        $response = Http::timeout(15)->withOptions(['max_filesize' => 5 * 1024 * 1024])->get($event->image_url);

        if ($response->failed()) {
            Log::warning('DownloadEventImageJob: failed to download image', [
                'event_id' => $event->id,
                'status' => $response->status(),
            ]);

            return;
        }

        // Validate by magic bytes rather than trusting the Content-Type header
        $body = $response->body();
        $ext = match (true) {
            str_starts_with($body, "\x89PNG") => 'png',
            str_starts_with($body, 'RIFF') && str_contains(substr($body, 8, 4), 'WEBP') => 'webp',
            str_starts_with($body, "\xFF\xD8\xFF") => 'jpg',
            default => null,
        };

        if ($ext === null) {
            Log::warning('DownloadEventImageJob: unrecognised image format', ['event_id' => $event->id]);

            return;
        }

        $path = "events/{$event->id}.{$ext}";
        Storage::disk('public')->put($path, $body);
        $event->update(['image_url' => Storage::disk('public')->url($path)]);

        Log::info('DownloadEventImageJob: image saved', [
            'event_id' => $event->id,
            'path' => $path,
        ]);
    }
}
