<?php

namespace Scottlaurent\FSRS;

use DateInterval;
use DateTime;

/**
 * Scheduling Cards Container
 * 
 * Contains four Card instances representing the scheduling outcomes for each
 * possible rating (Again, Hard, Good, Easy). Each card is a clone of the
 * original with different scheduling parameters applied.
 * 
 * This class handles the state transitions and interval calculations that
 * occur when a card moves between different states based on user ratings.
 * 
 * @package Scottlaurent\FSRS
 */
class SchedulingCards
{
    public Card $again;

    public Card $hard;

    public Card $good;

    public Card $easy;

    /**
     * Create SchedulingCards by cloning the original card four times
     * 
     * Each clone represents what would happen if the user gave that specific rating.
     * The clones will be modified with different scheduling parameters.
     * 
     * @param Card $card Original card to clone for each rating outcome
     */
    public function __construct(Card $card)
    {
        $this->again = clone $card;
        $this->hard = clone $card;
        $this->good = clone $card;
        $this->easy = clone $card;
    }

    /**
     * Apply scheduling intervals and due dates to the cards
     * 
     * Sets the scheduledDays and due properties for each card based on
     * the calculated intervals. Again always gets a short retry (5 minutes),
     * while other ratings use their calculated intervals.
     * 
     * @param DateTime $now Base time for calculating due dates
     * @param float $hardInterval Calculated interval for Hard rating (days)
     * @param float $goodInterval Calculated interval for Good rating (days)
     * @param float $easyInterval Calculated interval for Easy rating (days)
     */
    public function schedule(DateTime $now, float $hardInterval, float $goodInterval, float $easyInterval): void
    {
        $this->again->scheduledDays = 0;
        $this->hard->scheduledDays = $hardInterval;
        $this->good->scheduledDays = $goodInterval;
        $this->easy->scheduledDays = $easyInterval;
        $this->again->due = clone $now->add(new DateInterval('PT5M'));
        $this->hard->due = $hardInterval > 0 ? clone $now->add(
            new DateInterval('P'.$hardInterval.'D')
        ) : clone $now->add(new DateInterval('PT10M'));
        $this->good->due = clone $now->add(new DateInterval('P'.$goodInterval.'D'));
        $this->easy->due = clone $now->add(new DateInterval('P'.$easyInterval.'D'));
    }

    /**
     * Update card states based on current state and rating outcomes
     * 
     * Applies the FSRS state machine logic to determine what state each
     * rating outcome should transition to. State transitions depend on
     * both the current state and the rating given.
     * 
     * @param int $state Current state of the original card
     */
    public function updateState(int $state): void
    {
        switch ($state) {
            case State::NEW:
                $this->updateCardsStateForNew();
                break;
            case State::LEARNING:
            case State::RELEARNING:
                $this->updateCardsStateForLearning($state);
                break;
            case State::REVIEW:
                $this->updateCardsStateForReview();
                break;
        }
    }

    /**
     * Handle state transitions for NEW cards
     * 
     * NEW cards transition as follows:
     * - Again/Hard/Good → LEARNING (continue learning process)
     * - Easy → REVIEW (skip learning, graduate directly)
     */
    private function updateCardsStateForNew(): void
    {
        $this->again->state = State::LEARNING;
        $this->hard->state = State::LEARNING;
        $this->good->state = State::LEARNING;
        $this->easy->state = State::REVIEW;
    }

    /**
     * Handle state transitions for LEARNING/RELEARNING cards
     * 
     * Learning cards transition as follows:
     * - Again/Hard → Stay in current state (LEARNING/RELEARNING)
     * - Good/Easy → REVIEW (graduate to long-term review)
     * 
     * @param int $state Current state (LEARNING or RELEARNING)
     */
    private function updateCardsStateForLearning(int $state): void
    {
        $this->again->state = $state;
        $this->hard->state = $state;
        $this->good->state = State::REVIEW;
        $this->easy->state = State::REVIEW;
    }

    /**
     * Handle state transitions for REVIEW cards
     * 
     * Review cards transition as follows:
     * - Again → RELEARNING (forgotten, increment lapse count)
     * - Hard/Good/Easy → Stay in REVIEW (continue long-term review)
     */
    private function updateCardsStateForReview(): void
    {
        $this->again->state = State::RELEARNING;
        $this->hard->state = State::REVIEW;
        $this->good->state = State::REVIEW;
        $this->easy->state = State::REVIEW;
        $this->again->lapses += 1;
    }
}
