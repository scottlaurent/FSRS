<?php

namespace Scottlaurent\FSRS;

/**
 * FSRS (Free Spaced Repetition Scheduler) Manager
 * 
 * The main interface for the FSRS algorithm implementation. FSRS is a modern spaced repetition
 * algorithm that adapts to individual memory patterns and provides optimal review scheduling
 * based on the Two Components of Memory (DSR) model.
 * 
 * Key Concepts:
 * - Stability: Storage strength of memory; higher stability means slower forgetting
 * - Retrievability: Memory's retrieval strength; lower retrievability means higher forgetting probability
 * - Difficulty: Complexity of memorized material; affects stability increases
 * 
 * The algorithm follows these memory laws:
 * - More complex material results in lower stability increase
 * - Higher stability leads to lower stability increase (stabilization decay)
 * - Lower retrievability enables higher stability increase (stabilization curve)
 * 
 * @package Scottlaurent\FSRS
 * @author Scott Laurent
 * @link https://github.com/open-spaced-repetition/free-spaced-repetition-scheduler
 */
class Manager
{
    private FSRS $service;

    /**
     * Initialize the FSRS Manager with configuration parameters
     * 
     * @param float $defaultRequestRetention Target retention rate (0.0-1.0). Higher values create
     *                                      shorter intervals but better retention. Default 0.90 (90%)
     * @param array $weights Array of 17 model weights that control the algorithm's behavior.
     *                      These are trained parameters - use defaults unless you have custom training data
     * @param int $defaultMaximumInterval Maximum number of days between reviews (default: 36500 = ~100 years)
     * @param array $learningSteps Array of intervals (in minutes) for the learning phase when cards are new.
     *                            Default [1, 10] means 1 minute then 10 minutes
     * @param array $relearningSteps Array of intervals (in minutes) for the relearning phase when cards are forgotten.
     *                              Default [10] means 10 minutes
     * @param bool $enableFuzzing Add randomness to intervals to prevent review clustering.
     *                           Default true helps distribute reviews more naturally
     */
    public function __construct(
        public float $defaultRequestRetention = 0.90,
        public array $weights = [
            0.4872,
            1.4003,
            3.7145,
            13.8206,
            5.1618,
            1.2298,
            0.8975,
            0.031,
            1.6474,
            0.1367,
            1.0461,
            2.1072,
            0.0793,
            0.3246,
            1.587,
            0.2272,
            2.8755,
        ],
        public int $defaultMaximumInterval = 36500,
        public array $learningSteps = [1, 10],
        public array $relearningSteps = [10],
        public bool $enableFuzzing = true
    ) {
        $parameters = new Parameters($this->defaultRequestRetention, $this->defaultMaximumInterval, $this->weights);
        $this->service = new FSRS($parameters);
    }

    /**
     * Generate scheduling options for a card review
     * 
     * This is the core method that calculates how a card should be scheduled based on different
     * rating outcomes. It returns an array of SchedulingInfo objects for each possible rating.
     * 
     * The algorithm considers:
     * - Current card state (New, Learning, Review, Relearning)
     * - Card's stability and difficulty values
     * - Time elapsed since last review
     * - Target retention rate
     * 
     * @param Card $card The card being reviewed with its current state
     * @param \DateTime|null $repeatDate The date of the review (defaults to now)
     * 
     * @return array Associative array with keys 1-4 representing rating options:
     *               1 = Again (forgot completely)
     *               2 = Hard (remembered with significant difficulty)
     *               3 = Good (remembered after some hesitation)
     *               4 = Easy (remembered easily and quickly)
     *               Each value is a SchedulingInfo object containing the updated card and review log
     */
    public function generateRepetitionSchedule(Card $card, ?\DateTime $repeatDate = null): array
    {
        return $this->service->repeatCard($card, DateTimeHelper::ensureUtcNow($repeatDate));
    }

    /**
     * Calculate the retrievability (recall probability) of a card at a given time
     * 
     * Retrievability represents the likelihood that you can successfully recall the card's content
     * at a specific moment. It decreases exponentially over time based on the card's stability.
     * 
     * Formula: R(t) = 2^(-t/S) where:
     * - R(t) = Retrievability at time t
     * - t = Days since last review (can be negative for future dates)
     * - S = Card's stability value
     * 
     * @param Card $card The card to calculate retrievability for
     * @param \DateTime|null $now The time to calculate retrievability at (defaults to now)
     * 
     * @return float Retrievability value between 0.0 and 1.0:
     *               - 1.0 = 100% chance of recall
     *               - 0.5 = 50% chance of recall
     *               - 0.0 = 0% chance of recall (completely forgotten)
     *               New cards always return 0.0
     */
    public function getCardRetrievability(Card $card, ?\DateTime $now = null): float
    {
        $now = $now ?? new \DateTime('now', new \DateTimeZone('UTC'));
        
        if ($card->state === State::NEW || $card->stability <= 0) {
            return 0.0;
        }
        
        $elapsedDays = $now->diff($card->due)->days;
        if ($card->due > $now) {
            $elapsedDays = -$elapsedDays;
        }
        
        return $this->calculateRetrievability($card->stability, $elapsedDays);
    }

    /**
     * Convenience method to review a card and get both the updated card and review log
     * 
     * This method combines the scheduling generation with rating selection, providing a
     * simple interface for processing a review. It's equivalent to calling
     * generateRepetitionSchedule() and then selecting the appropriate rating.
     * 
     * @param Card $card The card being reviewed
     * @param int $rating The rating given by the user (1-4):
     *                   1 = Again (forgot completely)
     *                   2 = Hard (remembered with significant difficulty)
     *                   3 = Good (remembered after some hesitation)
     *                   4 = Easy (remembered easily and quickly)
     * @param \DateTime|null $reviewDate When the review occurred (defaults to now)
     * @param int|null $reviewDurationMs How long the review took in milliseconds (optional)
     * 
     * @return array Associative array with keys:
     *               - 'card': Updated Card object with new due date, stability, difficulty
     *               - 'log': ReviewLog object containing review metadata
     */
    public function reviewCard(Card $card, int $rating, ?\DateTime $reviewDate = null, ?int $reviewDurationMs = null): array
    {
        $reviewDate = $reviewDate ?? new \DateTime('now', new \DateTimeZone('UTC'));
        $schedule = $this->generateRepetitionSchedule($card, $reviewDate);
        $updatedCard = $schedule[$rating]->card;
        
        // Create review log
        $reviewLog = new ReviewLog(
            rating: $rating,
            scheduledDays: $card->scheduledDays,
            elapsedDays: $card->elapsedDays,
            review: $reviewDate,
            state: $card->state,
            cardId: $card->cardId,
            reviewDateTime: $reviewDate,
            reviewDurationMs: $reviewDurationMs
        );
        
        return [
            'card' => $updatedCard,
            'log' => $reviewLog
        ];
    }

    /**
     * Export the scheduler configuration to an array
     * 
     * This method allows you to serialize the FSRS configuration for storage or transfer.
     * Useful for saving user preferences, backup/restore functionality, or API responses.
     * 
     * @return array Associative array containing all configuration parameters:
     *               - defaultRequestRetention: Target retention rate
     *               - weights: Array of 17 model weights
     *               - defaultMaximumInterval: Maximum interval in days
     *               - learningSteps: Learning phase intervals in minutes
     *               - relearningSteps: Relearning phase intervals in minutes
     *               - enableFuzzing: Whether fuzzing is enabled
     */
    public function toArray(): array
    {
        return [
            'defaultRequestRetention' => $this->defaultRequestRetention,
            'weights' => $this->weights,
            'defaultMaximumInterval' => $this->defaultMaximumInterval,
            'learningSteps' => $this->learningSteps,
            'relearningSteps' => $this->relearningSteps,
            'enableFuzzing' => $this->enableFuzzing,
        ];
    }

    /**
     * Create a new Manager instance from a configuration array
     * 
     * This static method allows you to reconstruct a Manager with previously saved
     * configuration. Useful for loading user preferences or restoring from backup.
     * 
     * @param array $data Configuration array (typically from toArray() output)
     *                   Missing keys will use default values
     * 
     * @return self New Manager instance with the specified configuration
     */
    public static function fromArray(array $data): self
    {
        return new self(
            defaultRequestRetention: $data['defaultRequestRetention'] ?? 0.90,
            weights: $data['weights'] ?? [
                0.4872, 1.4003, 3.7145, 13.8206, 5.1618, 1.2298, 0.8975, 0.031,
                1.6474, 0.1367, 1.0461, 2.1072, 0.0793, 0.3246, 1.587, 0.2272, 2.8755
            ],
            defaultMaximumInterval: $data['defaultMaximumInterval'] ?? 36500,
            learningSteps: $data['learningSteps'] ?? [1, 10],
            relearningSteps: $data['relearningSteps'] ?? [10],
            enableFuzzing: $data['enableFuzzing'] ?? true
        );
    }

    /**
     * Calculate retrievability using the exponential forgetting curve
     * 
     * This implements the core FSRS forgetting formula: R(t) = 2^(-t/S)
     * The exponential decay models how memory strength decreases over time.
     * 
     * @param float $stability The card's stability value (higher = slower forgetting)
     * @param int $elapsedDays Days since last review (negative for future dates)
     * 
     * @return float Retrievability value between 0.0 and 1.0
     */
    private function calculateRetrievability(float $stability, int $elapsedDays): float
    {
        return 2 ** (-$elapsedDays / $stability);
    }
}
