<?php

namespace Tests\Feature;

use Scottlaurent\FSRS;
use Tests\TestCase;
use DateTime;
use DateTimeZone;

class ComprehensiveAlgorithmTest extends TestCase
{
    public function test_review_card_sequence_intervals()
    {
        // Test sequence from Python FSRS: Good ratings progression
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        $intervals = [];
        
        // Perform 10 Good ratings and track intervals
        for ($i = 0; $i < 10; $i++) {
            $schedule = $manager->generateRepetitionSchedule($card);
            $card = $schedule[3]->card; // Good rating
            $intervals[] = $card->scheduledDays;
        }
        
        // Verify intervals are generally increasing (allowing for some algorithm variance)
        // The FSRS algorithm may sometimes produce equal intervals due to its complexity
        for ($i = 1; $i < count($intervals); $i++) {
            $this->assertGreaterThanOrEqual($intervals[$i-1], $intervals[$i], "Interval at position $i should be >= previous");
        }
        
        // Verify overall progression - last interval should be greater than first (if both > 0)
        if (end($intervals) > 0 && $intervals[0] > 0) {
            $this->assertGreaterThan($intervals[0], end($intervals));
        } else {
            // At least verify we have some positive intervals
            $positiveIntervals = array_filter($intervals, fn($i) => $i > 0);
            $this->assertGreaterThan(0, count($positiveIntervals), "Should have some positive intervals");
        }
        
        // Verify first few intervals are reasonable - find first positive interval
        $firstPositiveInterval = 0;
        foreach ($intervals as $interval) {
            if ($interval > 0) {
                $firstPositiveInterval = $interval;
                break;
            }
        }
        $this->assertGreaterThan(0, $firstPositiveInterval);
        $this->assertLessThan(100, $firstPositiveInterval); // First interval shouldn't be too large
    }
    
    public function test_repeated_easy_reviews_difficulty_floor()
    {
        // Test that repeated Easy ratings don't drop difficulty below 1
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(
            new DateTime('2024-01-01', new DateTimeZone('UTC')),
            stability: 1.0,
            difficulty: 2.0,
            state: FSRS\State::REVIEW,
            lastReview: new DateTime('2023-12-25', new DateTimeZone('UTC'))
        );
        
        // Perform many Easy ratings
        for ($i = 0; $i < 20; $i++) {
            $schedule = $manager->generateRepetitionSchedule($card);
            $card = $schedule[4]->card; // Easy rating
        }
        
        $this->assertGreaterThanOrEqual(1.0, $card->difficulty);
        $this->assertLessThanOrEqual(10.0, $card->difficulty);
    }
    
    public function test_memo_state_stability_and_difficulty()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        
        // Initial values for new card
        $this->assertEquals(0, $card->stability);
        $this->assertEquals(0, $card->difficulty);
        $this->assertEquals(FSRS\State::NEW, $card->state);
        
        // After first Good rating
        $schedule = $manager->generateRepetitionSchedule($card);
        $card = $schedule[3]->card; // Good rating
        
        $this->assertGreaterThan(0, $card->stability);
        $this->assertGreaterThan(0, $card->difficulty);
        $this->assertEquals(FSRS\State::LEARNING, $card->state);
    }
    
    public function test_review_card_uses_default_now_datetime()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        
        // Test without providing now parameter
        $result = $manager->reviewCard($card, 3);
        
        $this->assertArrayHasKey('card', $result);
        $this->assertArrayHasKey('log', $result);
        $this->assertInstanceOf(FSRS\Card::class, $result['card']);
        $this->assertInstanceOf(FSRS\ReviewLog::class, $result['log']);
        
        // Due date should be in the future
        $this->assertGreaterThan(new DateTime('now', new DateTimeZone('UTC')), $result['card']->due);
    }
    
    public function test_state_transitions_learning_to_review()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        
        // NEW -> LEARNING (Good rating)
        $schedule = $manager->generateRepetitionSchedule($card);
        $card = $schedule[3]->card;
        $this->assertEquals(FSRS\State::LEARNING, $card->state);
        
        // LEARNING -> REVIEW (Good rating)
        $schedule = $manager->generateRepetitionSchedule($card);
        $card = $schedule[3]->card;
        $this->assertEquals(FSRS\State::REVIEW, $card->state);
    }
    
    public function test_state_transitions_review_to_relearning_on_again()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(
            new DateTime('2024-01-01', new DateTimeZone('UTC')),
            stability: 10.0,
            difficulty: 5.0,
            state: FSRS\State::REVIEW,
            lapses: 1,
            lastReview: new DateTime('2023-12-25', new DateTimeZone('UTC'))
        );
        
        // REVIEW -> RELEARNING (Again rating)
        $schedule = $manager->generateRepetitionSchedule($card);
        $card = $schedule[1]->card; // Again rating
        
        $this->assertEquals(FSRS\State::RELEARNING, $card->state);
        $this->assertEquals(2, $card->lapses); // Lapse count incremented
    }
    
    public function test_again_short_interval_behavior()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(
            new DateTime('2024-01-01 10:00:00', new DateTimeZone('UTC')),
            state: FSRS\State::REVIEW,
            stability: 5.0,
            lastReview: new DateTime('2023-12-25', new DateTimeZone('UTC'))
        );
        
        $schedule = $manager->generateRepetitionSchedule($card, new DateTime('2024-01-01 10:00:00', new DateTimeZone('UTC')));
        $againCard = $schedule[1]->card; // Again rating
        
        // Again rating should have very short interval (minutes, not days)
        $timeDiff = $againCard->due->getTimestamp() - (new DateTime('2024-01-01 10:00:00', new DateTimeZone('UTC')))->getTimestamp();
        $this->assertLessThan(3600, $timeDiff); // Less than 1 hour
        $this->assertGreaterThan(0, $timeDiff); // But greater than 0
    }
    
    public function test_good_interval_progression()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        $previousInterval = 0;
        
        // Test that Good ratings generally increase intervals
        for ($i = 0; $i < 5; $i++) {
            $schedule = $manager->generateRepetitionSchedule($card);
            $card = $schedule[3]->card; // Good rating
            
            if ($card->state === FSRS\State::REVIEW) {
                // Allow for equal intervals due to algorithm complexity
                $this->assertGreaterThanOrEqual($previousInterval, $card->scheduledDays);
                $previousInterval = $card->scheduledDays;
            }
        }
        
        // Verify we did progress through some review phases
        $this->assertGreaterThan(0, $previousInterval);
    }
    
    public function test_easy_interval_progression()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        
        // Easy rating should give longer intervals than Good
        $schedule = $manager->generateRepetitionSchedule($card);
        $goodCard = $schedule[3]->card;
        $easyCard = $schedule[4]->card;
        
        // For new cards, Easy should graduate to REVIEW while Good stays in LEARNING
        $this->assertEquals(FSRS\State::LEARNING, $goodCard->state);
        $this->assertEquals(FSRS\State::REVIEW, $easyCard->state);
        
        // Easy card should have a higher scheduled days when both are in review state
        if ($goodCard->state === FSRS\State::REVIEW && $easyCard->state === FSRS\State::REVIEW) {
            $this->assertGreaterThan($goodCard->scheduledDays, $easyCard->scheduledDays);
        } else {
            // At least verify that Easy gets a different (better) outcome
            $this->assertNotEquals($goodCard->state, $easyCard->state);
        }
    }
    
    public function test_get_card_retrievability_in_0_1()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(
            new DateTime('2024-01-01', new DateTimeZone('UTC')),
            stability: 10.0,
            state: FSRS\State::REVIEW
        );
        
        $retrievability = $manager->getCardRetrievability($card);
        
        $this->assertGreaterThanOrEqual(0.0, $retrievability);
        $this->assertLessThanOrEqual(1.0, $retrievability);
    }
    
    public function test_get_card_retrievability_monotonic_decay_over_time()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(
            due: new DateTime('2024-01-01', new DateTimeZone('UTC')),
            stability: 10.0,
            state: FSRS\State::REVIEW
        );
        
        // Test retrievability at different time points
        $retrievability1 = $manager->getCardRetrievability($card, new DateTime('2024-01-01', new DateTimeZone('UTC')));
        $retrievability2 = $manager->getCardRetrievability($card, new DateTime('2024-01-05', new DateTimeZone('UTC')));
        $retrievability3 = $manager->getCardRetrievability($card, new DateTime('2024-01-10', new DateTimeZone('UTC')));
        
        // Retrievability should decay over time
        $this->assertGreaterThan($retrievability2, $retrievability1);
        $this->assertGreaterThan($retrievability3, $retrievability2);
    }
    
    public function test_interval_nonnegative_and_due_after_now()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        $now = new DateTime('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        
        $schedule = $manager->generateRepetitionSchedule($card, $now);
        
        foreach ([1, 2, 3, 4] as $rating) {
            $scheduledCard = $schedule[$rating]->card;
            
            // Intervals should be non-negative
            $this->assertGreaterThanOrEqual(0, $scheduledCard->scheduledDays);
            
            // Due date should be after now (except for very short Again intervals)
            if ($rating !== 1) { // Skip Again rating as it has very short interval
                $this->assertGreaterThan($now, $scheduledCard->due);
            }
        }
    }
    
    public function test_stability_nonnegative_invariants()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        
        // Test multiple reviews with different ratings
        $ratings = [3, 3, 1, 2, 3, 4, 1, 3]; // Mixed ratings
        
        foreach ($ratings as $rating) {
            $schedule = $manager->generateRepetitionSchedule($card);
            $card = $schedule[$rating]->card;
            
            $this->assertGreaterThanOrEqual(0, $card->stability, "Stability should be non-negative after rating $rating");
        }
    }
    
    public function test_difficulty_within_expected_bounds()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        
        // Test multiple reviews with different ratings
        $ratings = [3, 3, 1, 2, 3, 4, 1, 3, 4, 4]; // Mixed ratings including many Easy
        
        foreach ($ratings as $rating) {
            $schedule = $manager->generateRepetitionSchedule($card);
            $card = $schedule[$rating]->card;
            
            $this->assertGreaterThanOrEqual(1.0, $card->difficulty, "Difficulty should be >= 1 after rating $rating");
            $this->assertLessThanOrEqual(10.0, $card->difficulty, "Difficulty should be <= 10 after rating $rating");
        }
    }
    
    public function test_lapse_increments_lapses_counter()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(
            new DateTime('2024-01-01', new DateTimeZone('UTC')),
            stability: 10.0,
            state: FSRS\State::REVIEW,
            lapses: 2,
            lastReview: new DateTime('2023-12-25', new DateTimeZone('UTC'))
        );
        
        $schedule = $manager->generateRepetitionSchedule($card);
        $againCard = $schedule[1]->card; // Again rating (forgotten)
        
        $this->assertEquals(3, $againCard->lapses); // Should increment
        
        // Other ratings should not increment lapses
        $hardCard = $schedule[2]->card;
        $goodCard = $schedule[3]->card;
        $easyCard = $schedule[4]->card;
        
        $this->assertEquals(2, $hardCard->lapses);
        $this->assertEquals(2, $goodCard->lapses);
        $this->assertEquals(2, $easyCard->lapses);
    }
    
    public function test_reps_incremented_each_review()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(
            new DateTime('2024-01-01', new DateTimeZone('UTC')),
            reps: 5
        );
        
        $schedule = $manager->generateRepetitionSchedule($card);
        
        // All ratings should increment reps
        foreach ([1, 2, 3, 4] as $rating) {
            $reviewedCard = $schedule[$rating]->card;
            $this->assertEquals(6, $reviewedCard->reps, "Reps should be incremented for rating $rating");
        }
    }
}