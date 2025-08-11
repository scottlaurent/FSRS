<?php

namespace Scottlaurent\FSRS;

/**
 * Scheduling Information Container
 * 
 * Simple container class that pairs a Card with its corresponding ReviewLog.
 * This is returned by the FSRS algorithm to provide both the updated card
 * state and the metadata about the review that would be recorded.
 * 
 * Used in the scheduling options array returned by generateRepetitionSchedule(),
 * where each possible rating (1-4) has its own SchedulingInfo object.
 * 
 * @package Scottlaurent\FSRS
 */
class SchedulingInfo
{
    /**
     * Create new SchedulingInfo instance
     * 
     * @param Card $card Updated card with new due date, stability, difficulty, etc.
     * @param ReviewLog $reviewLog Review metadata for this scheduling outcome
     */
    public function __construct(public Card $card, public ReviewLog $reviewLog) {}
}
