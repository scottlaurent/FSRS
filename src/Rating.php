<?php

namespace Scottlaurent\FSRS;

/**
 * Review Rating Constants
 * 
 * Defines the four possible ratings a user can give during a card review.
 * These ratings directly affect how the FSRS algorithm updates difficulty,
 * stability, and calculates the next review interval.
 * 
 * Rating Guidelines:
 * - AGAIN (1): Complete failure to recall - triggers forgetting curve
 * - HARD (2): Recalled with significant difficulty - shorter interval
 * - GOOD (3): Normal recall with some hesitation - standard interval
 * - EASY (4): Perfect recall - longer interval
 * 
 * @package Scottlaurent\FSRS
 */
class Rating
{
    /** @var int Complete failure to recall - card will be forgotten */
    const AGAIN = 1;

    /** @var int Recalled with significant difficulty - shorter than normal interval */
    const HARD = 2;

    /** @var int Normal recall with some hesitation - standard interval */
    const GOOD = 3;

    /** @var int Perfect recall - longer than normal interval */
    const EASY = 4;
}
