<?php

declare(strict_types=1);

namespace App\Enums;

enum EventCategory: string
{
    case Music = 'music';
    case Arts = 'arts';
    case Sports = 'sports';
    case Technology = 'technology';
    case Food = 'food';
    case Nightlife = 'nightlife';
    case Business = 'business';
    case Health = 'health';
    case Education = 'education';
    case Family = 'family';
    case Community = 'community';
    case Film = 'film';
    case Literature = 'literature';
    case Other = 'other';
}
