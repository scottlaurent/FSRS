<?php

namespace Scottlaurent\FSRS;

use DateInterval;
use DateTime;
use DateTimeZone;

/**
 * DateTime Utility Helper
 * 
 * Provides utility functions for DateTime manipulation within the FSRS system.
 * Ensures consistent timezone handling (UTC) and provides convenient methods
 * for adding time intervals to dates.
 * 
 * All FSRS calculations should use UTC to avoid timezone-related issues
 * when scheduling reviews across different time zones.
 * 
 * @package Scottlaurent\FSRS
 */
class DateTimeHelper
{
    /**
     * Ensure a DateTime is in UTC timezone
     * 
     * If no DateTime is provided, creates a new DateTime with current time in UTC.
     * If a DateTime is provided, validates that it's already in UTC timezone.
     * 
     * @param DateTime|null $now DateTime to validate or null to create new UTC DateTime
     * 
     * @return DateTime DateTime guaranteed to be in UTC timezone
     * 
     * @throws \InvalidArgumentException If provided DateTime is not in UTC
     */
    public static function ensureUtcNow(?DateTime $now = null): DateTime
    {
        if ($now === null) {
            $now = new DateTime('now', new DateTimeZone('UTC'));
        }

        if ($now->getTimezone()->getName() !== 'UTC') {
            throw new \InvalidArgumentException('datetime must be timezone-aware and set to UTC');
        }

        return $now;
    }

    /**
     * Add minutes to a DateTime
     * 
     * Creates a new DateTime instance (clones the original) and adds the specified
     * number of minutes. Used for short-term scheduling in learning phases.
     * 
     * @param DateTime $time Base DateTime to add minutes to
     * @param int $minutes Number of minutes to add (can be negative)
     * 
     * @return DateTime New DateTime instance with minutes added
     */
    public static function addMinutes(DateTime $time, int $minutes): DateTime
    {
        if ($minutes >= 0) {
            return (clone $time)->add(new DateInterval('PT'.$minutes.'M'));
        } else {
            return (clone $time)->sub(new DateInterval('PT'.abs($minutes).'M'));
        }
    }

    /**
     * Add days to a DateTime
     * 
     * Creates a new DateTime instance (clones the original) and adds the specified
     * number of days. Used for long-term scheduling in review phases.
     * 
     * @param DateTime $time Base DateTime to add days to
     * @param int $days Number of days to add (can be negative)
     * 
     * @return DateTime New DateTime instance with days added
     */
    public static function addDays(DateTime $time, int $days): DateTime
    {
        if ($days >= 0) {
            return (clone $time)->add(new DateInterval('P'.$days.'D'));
        } else {
            return (clone $time)->sub(new DateInterval('P'.abs($days).'D'));
        }
    }
}
