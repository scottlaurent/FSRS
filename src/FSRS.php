<?php

namespace Scottlaurent\FSRS;

use DateTime;

/**
 * Free Spaced Repetition Scheduler (FSRS) - Core Algorithm Implementation
 * 
 * This class implements the core FSRS algorithm based on the Three Components of Memory model.
 * FSRS calculates optimal review intervals by modeling memory as having two key components:
 * - Stability (S): Storage strength of memory - how long it can be retained
 * - Retrievability (R): Retrieval strength of memory - how easily it can be recalled
 * 
 * The algorithm follows these fundamental principles:
 * 1. Exponential forgetting curve: R(t) = 2^(-t/S)
 * 2. Difficulty affects stability gain: harder material gets less stability increase
 * 3. Current retrievability affects future stability: lower R enables higher S increase
 * 
 * Card State Machine:
 * NEW → LEARNING → REVIEW (successful graduation)
 *   ↓      ↓         ↓
 * LEARNING ← RELEARNING (when forgotten)
 * 
 * @package Scottlaurent\FSRS
 * @link https://github.com/open-spaced-repetition/fsrs-algorithm
 */
class FSRS
{
    public float $DECAY = -0.5;

    public float $FACTOR;

    protected DifficultyStabilityCalculator $calculator;

    /**
     * Initialize FSRS with algorithm parameters
     * 
     * @param Parameters $p Configuration object containing model weights, retention targets,
     *                     and maximum intervals that control the algorithm's behavior
     */
    public function __construct(public Parameters $p)
    {
        $this->FACTOR = 0.9 ** (1 / $this->DECAY) - 1;
        $this->calculator = new DifficultyStabilityCalculator($this->DECAY, $this->FACTOR, $this->p);
    }

    /**
     * Generate scheduling options for all possible ratings of a card
     * 
     * This is the main entry point for the FSRS algorithm. It calculates how the card
     * should be scheduled for each possible user rating (Again, Hard, Good, Easy).
     * 
     * The algorithm:
     * 1. Prepares the card (calculates elapsed days, increments reps)
     * 2. Creates scheduling cards for each rating outcome
     * 3. Applies state-specific logic (New, Learning, Review, Relearning)
     * 4. Returns scheduling information with review logs
     * 
     * @param Card $card The card being reviewed with its current state
     * @param DateTime $now The current time for review calculations
     * 
     * @return array Associative array with keys 1-4 (rating values) containing
     *               SchedulingInfo objects with updated card states and review logs
     */
    public function repeatCard(Card $card, DateTime $now): array
    {
        $card = $this->prepareCardForReview($card, $now);

        $s = new SchedulingCards($card);
        $s->updateState($card->state);

        switch ($card->state) {
            case State::NEW:
                $this->handleNewState($s, $now);
                break;
            case State::LEARNING:
            case State::RELEARNING:
                $this->handleLearningState($s, $now);
                break;
            case State::REVIEW:
                $this->handleReviewState($s, $card, $now);
                break;
        }

        return $this->recordLog($s, $card, $now);
    }

    /**
     * Prepare a card for review by updating review-specific properties
     * 
     * This method clones the card and updates properties that change during review:
     * - Calculates elapsed days since last review (0 for new cards)
     * - Sets lastReview to current time
     * - Increments repetition count
     * 
     * @param Card $card Original card to prepare
     * @param DateTime $now Current review time
     * 
     * @return Card Cloned and updated card ready for scheduling calculations
     */
    private function prepareCardForReview(Card $card, DateTime $now): Card
    {
        $card = clone $card;

        if ($card->state === State::NEW) {
            $card->elapsedDays = 0;
        } else {
            $card->elapsedDays = $now->diff($card->lastReview)->days;
        }

        $card->lastReview = $now;
        $card->reps += 1;

        return $card;
    }

    /**
     * Handle scheduling for cards in NEW state (never reviewed before)
     * 
     * New cards get initial difficulty and stability values, then are scheduled
     * with short intervals for the learning phase:
     * - Again: 1 minute (immediate retry)
     * - Hard: 5 minutes
     * - Good: 10 minutes  
     * - Easy: Calculated interval based on stability (graduates to Review)
     * 
     * @param SchedulingCards $s Container for scheduling outcomes
     * @param DateTime $now Current time for scheduling calculations
     */
    private function handleNewState(SchedulingCards $s, DateTime $now): void
    {
        $this->initDs($s);

        $s->again->due = DateTimeHelper::addMinutes($now, 1);
        $s->hard->due = DateTimeHelper::addMinutes($now, 5);
        $s->good->due = DateTimeHelper::addMinutes($now, 10);
        $easyInterval = $this->calculator->nextInterval(
            $s->easy->stability,
            $this->p->requestRetention,
            $this->p->maximumInterval
        );
        $s->easy->scheduledDays = $easyInterval;
        $s->easy->due = DateTimeHelper::addDays($now, $easyInterval);
    }

    /**
     * Handle scheduling for cards in LEARNING or RELEARNING states
     * 
     * Learning cards are in short-term memory training. Intervals are calculated
     * based on stability values, with Good/Easy ratings potentially graduating
     * the card to Review state.
     * 
     * - Again: Returns to start of learning
     * - Hard: No interval increase (stays in learning)
     * - Good: Calculated interval, may graduate to Review
     * - Easy: Longer interval than Good, graduates to Review
     * 
     * @param SchedulingCards $s Container for scheduling outcomes
     * @param DateTime $now Current time for scheduling calculations
     */
    private function handleLearningState(SchedulingCards $s, DateTime $now): void
    {
        $hardInterval = 0;
        $goodInterval = $this->calculator->nextInterval(
            $s->good->stability,
            $this->p->requestRetention,
            $this->p->maximumInterval
        );
        $easyInterval = max(
            $this->calculator->nextInterval($s->easy->stability, $this->p->requestRetention, $this->p->maximumInterval),
            $goodInterval + 1
        );

        $this->schedule($s, $now, $hardInterval, $goodInterval, $easyInterval);
    }

    /**
     * Handle scheduling for cards in REVIEW state (long-term memory)
     * 
     * Review cards use the full FSRS algorithm with stability and difficulty updates
     * based on current retrievability. This is where the core memory model applies:
     * 
     * - Again: Goes to Relearning, stability decreases
     * - Hard: Shorter interval than scheduled, stability decreases slightly
     * - Good: Normal interval based on target retention
     * - Easy: Longer interval than Good
     * 
     * @param SchedulingCards $s Container for scheduling outcomes
     * @param Card $card Original card with current memory parameters
     * @param DateTime $now Current time for scheduling calculations
     */
    private function handleReviewState(SchedulingCards $s, Card $card, DateTime $now): void
    {
        $interval = $card->elapsedDays;
        $lastD = $card->difficulty;
        $lastS = $card->stability;
        $retrievability = $this->calculator->forgettingCurve($interval, $lastS);
        $this->nextDs($s, $lastD, $lastS, $retrievability);

        $hardInterval = $this->calculator->nextInterval(
            $s->hard->stability,
            $this->p->requestRetention,
            $this->p->maximumInterval
        );
        $goodInterval = $this->calculator->nextInterval(
            $s->good->stability,
            $this->p->requestRetention,
            $this->p->maximumInterval
        );
        $hardInterval = min($hardInterval, $goodInterval);
        $goodInterval = max($goodInterval, $hardInterval + 1);
        $easyInterval = max(
            $this->calculator->nextInterval($s->easy->stability, $this->p->requestRetention, $this->p->maximumInterval),
            $goodInterval + 1
        );

        $this->schedule($s, $now, $hardInterval, $goodInterval, $easyInterval);
    }

    /**
     * Initialize Difficulty and Stability for new cards
     * 
     * Sets initial difficulty and stability values based on the rating that would be given.
     * These initial values are derived from the model weights and represent the card's
     * starting point in the memory system.
     * 
     * @param SchedulingCards $s Container to populate with initial D/S values
     */
    private function initDs(SchedulingCards $s): void
    {
        $s->again->difficulty = $this->calculator->initDifficulty(Rating::AGAIN);
        $s->again->stability = $this->calculator->initStability(Rating::AGAIN);
        $s->hard->difficulty = $this->calculator->initDifficulty(Rating::HARD);
        $s->hard->stability = $this->calculator->initStability(Rating::HARD);
        $s->good->difficulty = $this->calculator->initDifficulty(Rating::GOOD);
        $s->good->stability = $this->calculator->initStability(Rating::GOOD);
        $s->easy->difficulty = $this->calculator->initDifficulty(Rating::EASY);
        $s->easy->stability = $this->calculator->initStability(Rating::EASY);
    }

    /**
     * Calculate next Difficulty and Stability values for review cards
     * 
     * This implements the core FSRS memory model:
     * - Difficulty changes based on rating (increases for Again/Hard, decreases for Easy)
     * - Stability for Again uses forget stability formula
     * - Stability for Hard/Good/Easy uses recall stability formula
     * - All calculations consider current retrievability
     * 
     * @param SchedulingCards $s Container to populate with new D/S values
     * @param float $lastD Current difficulty of the card
     * @param float $lastS Current stability of the card
     * @param float $retrievability Current recall probability at review time
     */
    private function nextDs(SchedulingCards $s, float $lastD, float $lastS, float $retrievability): void
    {
        $s->again->difficulty = $this->calculator->nextDifficulty($lastD, Rating::AGAIN);
        $s->again->stability = $this->calculator->nextForgetStability($lastD, $lastS, $retrievability);
        $s->hard->difficulty = $this->calculator->nextDifficulty($lastD, Rating::HARD);
        $s->hard->stability = $this->calculator->nextRecallStability($lastD, $lastS, $retrievability, Rating::HARD);
        $s->good->difficulty = $this->calculator->nextDifficulty($lastD, Rating::GOOD);
        $s->good->stability = $this->calculator->nextRecallStability($lastD, $lastS, $retrievability, Rating::GOOD);
        $s->easy->difficulty = $this->calculator->nextDifficulty($lastD, Rating::EASY);
        $s->easy->stability = $this->calculator->nextRecallStability($lastD, $lastS, $retrievability, Rating::EASY);
        $s->again->retrievability = $s->hard->retrievability = $s->good->retrievability = $s->easy->retrievability = $retrievability;
    }

    /**
     * Set final scheduling intervals and due dates for all rating outcomes
     * 
     * Applies calculated intervals to the scheduling cards and sets due dates.
     * Again rating always gets a short retry interval (5 minutes), while other
     * ratings use their calculated intervals.
     * 
     * @param SchedulingCards $s Container with cards to schedule
     * @param DateTime $now Base time for calculating due dates
     * @param float $hardInterval Calculated interval for Hard rating
     * @param float $goodInterval Calculated interval for Good rating  
     * @param float $easyInterval Calculated interval for Easy rating
     */
    private function schedule(
        SchedulingCards $s,
        DateTime $now,
        float $hardInterval,
        float $goodInterval,
        float $easyInterval
    ): void {
        $s->again->scheduledDays = 0;
        $s->hard->scheduledDays = $hardInterval;
        $s->good->scheduledDays = $goodInterval;
        $s->easy->scheduledDays = $easyInterval;

        $s->again->due = DateTimeHelper::addMinutes($now, 5);
        $s->hard->due = $hardInterval > 0 ? DateTimeHelper::addDays($now, $hardInterval) : DateTimeHelper::addMinutes(
            $now,
            10
        );
        $s->good->due = DateTimeHelper::addDays($now, $goodInterval);
        $s->easy->due = DateTimeHelper::addDays($now, $easyInterval);
    }

    /**
     * Create SchedulingInfo objects with review logs for each rating option
     * 
     * Packages each scheduling outcome (Again, Hard, Good, Easy) with its corresponding
     * review log for return to the caller. This provides complete information about
     * what would happen for each possible user rating.
     * 
     * @param SchedulingCards $s Container with scheduled card outcomes
     * @param Card $card Original card being reviewed
     * @param DateTime $now Review time for the logs
     * 
     * @return array Associative array with rating keys (1-4) and SchedulingInfo values
     */
    private function recordLog(SchedulingCards $s, Card $card, DateTime $now): array
    {
        $log = [];

        foreach (
            [
                Rating::AGAIN => $s->again,
                Rating::HARD => $s->hard,
                Rating::GOOD => $s->good,
                Rating::EASY => $s->easy,
            ] as $rating => $cardState
        ) {
            $log[$rating] = new SchedulingInfo(
                $cardState,
                new ReviewLog($rating, $cardState->scheduledDays, $card->elapsedDays, $now, $card->state)
            );
        }

        return $log;
    }
}
