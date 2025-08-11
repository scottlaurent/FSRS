<?php

namespace Scottlaurent\FSRS;

/**
 * FSRS Algorithm Parameters
 * 
 * Simple container class that holds the core parameters used throughout
 * the FSRS algorithm calculations. These parameters control the behavior
 * of difficulty and stability calculations.
 * 
 * @package Scottlaurent\FSRS
 */
class Parameters
{
    /**
     * Create new Parameters instance
     * 
     * @param float $requestRetention Target retention rate (0.0-1.0, typically 0.9)
     * @param int $maximumInterval Maximum allowed interval between reviews (days)
     * @param array $w Array of 17 model weights that control algorithm behavior
     */
    public function __construct(public $requestRetention, public $maximumInterval, public $w) {}
}
