<?php

declare(strict_types=1);

use App\Enums\EventCategory;
use App\Models\Event;
use App\Services\Recommendation\DiversityFilter;

beforeEach(function () {
    $this->filter = new DiversityFilter;
});

it('caps events per category to the limit', function () {
    Event::factory()->count(5)->create(['category' => EventCategory::Music]);
    Event::factory()->count(5)->create(['category' => EventCategory::Sports]);

    $events = Event::all();
    $filtered = $this->filter->filter($events, maxPerCategory: 2);

    $musicCount = $filtered->where('category', EventCategory::Music)->count();
    $sportsCount = $filtered->where('category', EventCategory::Sports)->count();

    expect($musicCount)->toBeLessThanOrEqual(2);
    expect($sportsCount)->toBeLessThanOrEqual(2);
});

it('interleaves categories instead of clustering', function () {
    Event::factory()->count(3)->create(['category' => EventCategory::Music]);
    Event::factory()->count(3)->create(['category' => EventCategory::Sports]);

    $events = Event::all();
    $filtered = $this->filter->filter($events, maxPerCategory: 3);

    // The first two results should be from different categories
    $first = $filtered->values()[0]->category;
    $second = $filtered->values()[1]->category;

    expect($first)->not->toBe($second);
});

it('returns all events when within cap', function () {
    Event::factory()->count(2)->create(['category' => EventCategory::Music]);
    Event::factory()->count(2)->create(['category' => EventCategory::Arts]);

    $events = Event::all();
    $filtered = $this->filter->filter($events, maxPerCategory: 5);

    expect($filtered)->toHaveCount(4);
});

it('handles a single category', function () {
    Event::factory()->count(5)->create(['category' => EventCategory::Music]);

    $events = Event::all();
    $filtered = $this->filter->filter($events, maxPerCategory: 3);

    expect($filtered)->toHaveCount(3);
});

it('handles an empty collection', function () {
    $filtered = $this->filter->filter(collect());

    expect($filtered)->toBeEmpty();
});
