<?php

namespace Scottlaurent\FSRS;

/**
 * Difficulty and Stability Calculator - Core FSRS Mathematical Functions
 * 
 * This class implements the mathematical formulas that drive the FSRS algorithm.
 * It handles the calculation of memory parameters (difficulty and stability) and
 * their changes over time based on review outcomes and retrievability.
 * 
 * Key Mathematical Concepts:
 * - Exponential forgetting curve with power-law modifications
 * - Difficulty represents inherent complexity (1-10 scale)
 * - Stability represents memory retention strength (days)
 * - Retrievability represents current recall probability (0-1)
 * 
 * The formulas are based on extensive research into human memory and have been
 * optimized using large datasets of actual review performance.
 * 
 * @package Scottlaurent\FSRS
 */
class DifficultyStabilityCalculator
{
    /**
     * Initialize calculator with FSRS constants and parameters
     * 
     * @param float $DECAY The exponential decay constant (typically -0.5)
     * @param float $FACTOR Scaling factor derived from decay: 0.9^(1/DECAY) - 1
     * @param Parameters $p Model parameters containing the 17 trained weights
     */
    public function __construct(protected float $DECAY, protected float $FACTOR, protected Parameters $p) {}

    /**
     * Calculate initial stability for a new card based on first rating
     * 
     * Uses the first 4 model weights (w[0] through w[3]) which represent
     * the initial stability values for ratings 1-4. Ensures minimum stability of 0.1.
     * 
     * @param int $r Rating given (1=Again, 2=Hard, 3=Good, 4=Easy)
     * 
     * @return float Initial stability value (minimum 0.1 days)
     */
    public function initStability(int $r): float
    {
        return max($this->p->w[$r - 1], 0.1);
    }

    /**
     * Calculate initial difficulty for a new card based on first rating
     * 
     * Difficulty starts at w[4] and is adjusted by w[5] * (rating - 3).
     * This means Good (3) gives base difficulty, Easy (4) reduces it,
     * and Again/Hard (1,2) increase it. Clamped to 1-10 range.
     * 
     * @param int $r Rating given (1=Again, 2=Hard, 3=Good, 4=Easy)
     * 
     * @return float Initial difficulty value (1-10 scale)
     */
    public function initDifficulty(int $r): float
    {
        return min(max($this->p->w[4] - $this->p->w[5] * ($r - 3), 1), 10);
    }

    /**
     * Calculate retrievability using the FSRS forgetting curve formula
     * 
     * This implements the core forgetting formula: R(t) = (1 + FACTOR * t/S)^DECAY
     * This is a power-law modification of the exponential forgetting curve that
     * better models human memory decay patterns.
     * 
     * @param int $elapsedDays Number of days since last review
     * @param float $stability Current stability of the memory
     * 
     * @return float Retrievability (0-1), representing probability of successful recall
     */
    public function forgettingCurve(int $elapsedDays, float $stability): float
    {
        return (1 + $this->FACTOR * $elapsedDays / $stability) ** $this->DECAY;
    }

    /**
     * Calculate the next review interval based on stability and target retention
     * 
     * This inverts the forgetting curve to find when retrievability will drop
     * to the target retention level. The formula solves for t in:
     * requestRetention = (1 + FACTOR * t/S)^DECAY
     * 
     * @param float $s Current stability of the card
     * @param float $requestRetention Target retention rate (e.g., 0.9 for 90%)
     * @param int $maximumInterval Maximum allowed interval in days
     * 
     * @return int Next review interval in days (minimum 1, maximum as specified)
     */
    public function nextInterval(float $s, float $requestRetention, int $maximumInterval): int
    {
        $newInterval = $s / $this->FACTOR * (pow($requestRetention, 1 / $this->DECAY) - 1);

        return min(max(round($newInterval), 1), $maximumInterval);
    }

    /**
     * Calculate the next difficulty after a review
     * 
     * Difficulty changes based on the rating: w[6] * (rating - 3)
     * - Again/Hard (1,2): Difficulty increases (negative adjustment)
     * - Good (3): No change (zero adjustment)  
     * - Easy (4): Difficulty decreases (positive adjustment)
     * 
     * After adjustment, mean reversion is applied to prevent extreme values.
     * 
     * @param float $d Current difficulty of the card
     * @param int $r Rating given (1=Again, 2=Hard, 3=Good, 4=Easy)
     * 
     * @return float Next difficulty value (1-10 scale)
     */
    public function nextDifficulty(float $d, int $r): float
    {
        $nextD = $d - $this->p->w[6] * ($r - 3);

        return min(max($this->meanReversion($this->p->w[4], $nextD), 1), 10);
    }

    /**
     * Apply mean reversion to prevent difficulty from drifting to extremes
     * 
     * This blends the current value with the initial value using weight w[7]:
     * result = w[7] * initial + (1 - w[7]) * current
     * 
     * This prevents difficulty from becoming too extreme over many reviews.
     * 
     * @param float $init Initial/target value to revert toward
     * @param float $current Current value to apply reversion to
     * 
     * @return float Reversion-adjusted value
     */
    public function meanReversion(float $init, float $current): float
    {
        return $this->p->w[7] * $init + (1 - $this->p->w[7]) * $current;
    }

    /**
     * Calculate new stability after successful recall (Hard/Good/Easy ratings)
     * 
     * This complex formula models how memory strengthens after successful recall.
     * Key factors:
     * - Lower difficulty (11-d) increases stability gain
     * - Current stability affects gain via power law (s^-w[9])
     * - Lower retrievability at review time increases gain
     * - Hard rating applies penalty (w[15] < 1)
     * - Easy rating applies bonus (w[16] > 1)
     * 
     * @param float $d Current difficulty of the card
     * @param float $s Current stability of the card
     * @param float $r Retrievability at time of review
     * @param int $rating Rating given (2=Hard, 3=Good, 4=Easy)
     * 
     * @return float New stability value after successful recall
     */
    public function nextRecallStability(float $d, float $s, float $r, int $rating): float
    {
        $hardPenalty = $rating === Rating::HARD ? $this->p->w[15] : 1;
        $easyBonus = $rating === Rating::EASY ? $this->p->w[16] : 1;

        return $s * (
            1
            + exp($this->p->w[8])
            * (11 - $d)
            * pow($s, -$this->p->w[9])
            * (exp((1 - $r) * $this->p->w[10]) - 1)
            * $hardPenalty
            * $easyBonus
        );
    }

    /**
     * Calculate new stability after forgetting (Again rating)
     * 
     * When a card is forgotten, stability decreases based on several factors:
     * - Base multiplier w[11] (typically < 1)
     * - Difficulty penalty: harder cards lose more stability
     * - Current stability factor: higher stability provides some protection
     * - Retrievability factor: lower retrievability at review reduces penalty
     * 
     * @param float $d Current difficulty of the card
     * @param float $s Current stability of the card  
     * @param float $r Retrievability at time of review
     * 
     * @return float New (reduced) stability value after forgetting
     */
    public function nextForgetStability(float $d, float $s, float $r): float
    {
        return $this->p->w[11]
            * pow($d, -$this->p->w[12])
            * (pow($s + 1, $this->p->w[13]) - 1)
            * exp((1 - $r) * $this->p->w[14]);
    }
}
