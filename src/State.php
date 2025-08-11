<?php

namespace Scottlaurent\FSRS;

/**
 * Card State Constants
 * 
 * Defines the four possible states a card can be in during its lifecycle.
 * Each state represents a different phase of the learning process and
 * affects how the FSRS algorithm calculates scheduling intervals.
 * 
 * State Transitions:
 * NEW → LEARNING → REVIEW (successful learning)
 *   ↓      ↓         ↓
 * LEARNING ← RELEARNING (when forgotten)
 * 
 * @package Scottlaurent\FSRS
 */
class State
{
    /** @var int Card has never been studied - initial state */
    const NEW = 0;

    /** @var int Card is in the learning phase with short intervals */
    const LEARNING = 1;

    /** @var int Card has graduated to long-term review with longer intervals */
    const REVIEW = 2;

    /** @var int Card was forgotten and returned to short-term relearning */
    const RELEARNING = 3;
}
