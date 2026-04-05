<?php

namespace App\Traits;

use Illuminate\Support\Carbon;

/**
 * Timezone Handling Trait
 *
 * Provides consistent timezone handling across the application.
 * All datetime operations use UTC internally and convert to
 * the configured timezone only for display purposes.
 */
trait HasTimezoneHandling
{
    /**
     * Get the application's default timezone.
     */
    public function getDefaultTimezone(): string
    {
        return config('subscriptions.timezone', config('app.timezone', 'UTC'));
    }

    /**
     * Create a Carbon instance in the application's timezone.
     */
    public function nowInAppTimezone(): Carbon
    {
        return now()->tz($this->getDefaultTimezone());
    }

    /**
     * Convert a UTC datetime to the application's timezone.
     */
    public function utcToAppTimezone(Carbon $datetime): Carbon
    {
        return $datetime->tz($this->getDefaultTimezone());
    }

    /**
     * Convert an application timezone datetime to UTC.
     */
    public function appTimezoneToUtc(Carbon $datetime): Carbon
    {
        return $datetime->tz('UTC');
    }

    /**
     * Format a datetime for display in the application's timezone.
     */
    public function formatForDisplay(Carbon $datetime, string $format = 'Y-m-d H:i:s'): string
    {
        return $this->utcToAppTimezone($datetime)->format($format);
    }

    /**
     * Check if a datetime is in UTC.
     */
    public function isUtc(Carbon $datetime): bool
    {
        return $datetime->timezoneName === 'UTC';
    }
}
