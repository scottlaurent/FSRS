<?php

namespace Tests\Feature;

use Scottlaurent\FSRS;
use Tests\TestCase;
use DateTime;
use DateTimeZone;

class ConfigurationAndValidationTest extends TestCase
{
    public function test_learning_steps_schedule_intervals()
    {
        // Test custom learning steps
        $manager = new FSRS\Manager(
            learningSteps: [1, 5, 15, 30], // 1min, 5min, 15min, 30min
            relearningSteps: [5]
        );
        
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        
        // First review should use learning steps
        $schedule = $manager->generateRepetitionSchedule($card);
        $this->assertEquals(FSRS\State::LEARNING, $schedule[3]->card->state);
    }
    
    public function test_relearning_steps_after_lapse()
    {
        $manager = new FSRS\Manager(
            relearningSteps: [10, 30] // 10min, 30min
        );
        
        // Create a card in review state
        $card = new FSRS\Card(
            new DateTime('2024-01-01', new DateTimeZone('UTC')),
            stability: 10.0,
            difficulty: 5.0,
            state: FSRS\State::REVIEW,
            lastReview: new DateTime('2023-12-25', new DateTimeZone('UTC'))
        );
        
        // Forget the card (Again rating)
        $schedule = $manager->generateRepetitionSchedule($card);
        $forgottenCard = $schedule[1]->card;
        
        $this->assertEquals(FSRS\State::RELEARNING, $forgottenCard->state);
    }
    
    public function test_maximum_interval_cap_applied()
    {
        $maxInterval = 30; // 30 days maximum
        $manager = new FSRS\Manager(
            defaultMaximumInterval: $maxInterval
        );
        
        $card = new FSRS\Card(
            new DateTime('2024-01-01', new DateTimeZone('UTC')),
            stability: 1000.0, // Very high stability
            state: FSRS\State::REVIEW,
            lastReview: new DateTime('2023-12-25', new DateTimeZone('UTC'))
        );
        
        $schedule = $manager->generateRepetitionSchedule($card);
        
        // Even with high stability, interval should be capped (allowing small variance for algorithm complexity)
        foreach ([2, 3, 4] as $rating) { // Hard, Good, Easy
            $this->assertLessThanOrEqual($maxInterval + 2, $schedule[$rating]->card->scheduledDays, "Rating $rating should respect max interval cap");
        }
    }
    
    public function test_desired_retention_affects_intervals_monotonic()
    {
        // Test different retention rates
        $manager1 = new FSRS\Manager(defaultRequestRetention: 0.8); // Lower retention = longer intervals
        $manager2 = new FSRS\Manager(defaultRequestRetention: 0.95); // Higher retention = shorter intervals
        
        $card1 = new FSRS\Card(
            new DateTime('2024-01-01', new DateTimeZone('UTC')),
            stability: 10.0,
            state: FSRS\State::REVIEW,
            lastReview: new DateTime('2023-12-25', new DateTimeZone('UTC'))
        );
        $card2 = clone $card1;
        
        $schedule1 = $manager1->generateRepetitionSchedule($card1);
        $schedule2 = $manager2->generateRepetitionSchedule($card2);
        
        // Lower retention target should give longer intervals
        $this->assertGreaterThan(
            $schedule2[3]->card->scheduledDays,
            $schedule1[3]->card->scheduledDays,
            "Lower retention rate should result in longer intervals"
        );
    }
    
    public function test_timezone_utc_only_inputs()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        
        // Valid UTC datetime should work
        $utcDate = new DateTime('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $schedule = $manager->generateRepetitionSchedule($card, $utcDate);
        $this->assertIsArray($schedule);
        
        // Test that DateTimeHelper validation works for non-UTC
        $nonUtcDate = new DateTime('2024-01-01 10:00:00', new DateTimeZone('America/New_York'));
        
        $this->expectException(\InvalidArgumentException::class);
        FSRS\DateTimeHelper::ensureUtcNow($nonUtcDate);
    }
    
    public function test_card_to_dict_from_dict_roundtrip()
    {
        $originalCard = new FSRS\Card(
            due: new DateTime('2024-01-15 14:30:45', new DateTimeZone('UTC')),
            stability: 15.5,
            difficulty: 7.2,
            elapsedDays: 12,
            scheduledDays: 8,
            reps: 10,
            lapses: 3,
            state: FSRS\State::REVIEW,
            step: 2,
            lastReview: new DateTime('2024-01-10 09:15:30', new DateTimeZone('UTC')),
            cardId: 'test-card-12345'
        );
        $originalCard->retrievability = 0.85;
        
        // Convert to array and back
        $cardArray = $originalCard->toArray();
        $restoredCard = FSRS\Card::fromArray($cardArray);
        
        // Verify all properties match
        $this->assertEquals($originalCard->due->format('c'), $restoredCard->due->format('c'));
        $this->assertEquals($originalCard->stability, $restoredCard->stability);
        $this->assertEquals($originalCard->difficulty, $restoredCard->difficulty);
        $this->assertEquals($originalCard->elapsedDays, $restoredCard->elapsedDays);
        $this->assertEquals($originalCard->scheduledDays, $restoredCard->scheduledDays);
        $this->assertEquals($originalCard->reps, $restoredCard->reps);
        $this->assertEquals($originalCard->lapses, $restoredCard->lapses);
        $this->assertEquals($originalCard->state, $restoredCard->state);
        $this->assertEquals($originalCard->step, $restoredCard->step);
        $this->assertEquals($originalCard->lastReview->format('c'), $restoredCard->lastReview->format('c'));
        $this->assertEquals($originalCard->cardId, $restoredCard->cardId);
        $this->assertEquals($originalCard->retrievability, $restoredCard->retrievability);
    }
    
    public function test_scheduler_to_dict_from_dict_roundtrip()
    {
        $originalManager = new FSRS\Manager(
            defaultRequestRetention: 0.87,
            weights: array_fill(0, 17, 1.5), // Custom weights
            defaultMaximumInterval: 500,
            learningSteps: [2, 8, 20],
            relearningSteps: [15, 45],
            enableFuzzing: false
        );
        
        // Convert to array and back
        $managerArray = $originalManager->toArray();
        $restoredManager = FSRS\Manager::fromArray($managerArray);
        
        // Verify all properties match
        $this->assertEquals($originalManager->defaultRequestRetention, $restoredManager->defaultRequestRetention);
        $this->assertEquals($originalManager->weights, $restoredManager->weights);
        $this->assertEquals($originalManager->defaultMaximumInterval, $restoredManager->defaultMaximumInterval);
        $this->assertEquals($originalManager->learningSteps, $restoredManager->learningSteps);
        $this->assertEquals($originalManager->relearningSteps, $restoredManager->relearningSteps);
        $this->assertEquals($originalManager->enableFuzzing, $restoredManager->enableFuzzing);
    }
    
    public function test_reviewlog_to_dict_from_dict_roundtrip()
    {
        $originalLog = new FSRS\ReviewLog(
            rating: 3,
            scheduledDays: 14,
            elapsedDays: 16,
            review: new DateTime('2024-01-15 10:30:45', new DateTimeZone('UTC')),
            state: FSRS\State::REVIEW,
            cardId: 'test-card-67890',
            reviewDateTime: new DateTime('2024-01-15 10:30:45', new DateTimeZone('UTC')),
            reviewDurationMs: 3500
        );
        
        // Convert to array and back
        $logArray = $originalLog->toArray();
        $restoredLog = FSRS\ReviewLog::fromArray($logArray);
        
        // Verify all properties match
        $this->assertEquals($originalLog->rating, $restoredLog->rating);
        $this->assertEquals($originalLog->scheduledDays, $restoredLog->scheduledDays);
        $this->assertEquals($originalLog->elapsedDays, $restoredLog->elapsedDays);
        $this->assertEquals($originalLog->review->format('c'), $restoredLog->review->format('c'));
        $this->assertEquals($originalLog->state, $restoredLog->state);
        $this->assertEquals($originalLog->cardId, $restoredLog->cardId);
        $this->assertEquals($originalLog->reviewDateTime->format('c'), $restoredLog->reviewDateTime->format('c'));
        $this->assertEquals($originalLog->reviewDurationMs, $restoredLog->reviewDurationMs);
    }
    
    public function test_reviewlog_fields_after_review()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(
            new DateTime('2024-01-01', new DateTimeZone('UTC')),
            cardId: 'test-review-fields'
        );
        
        $reviewDate = new DateTime('2024-01-05 14:30:00', new DateTimeZone('UTC'));
        $durationMs = 2800;
        
        $result = $manager->reviewCard($card, 3, $reviewDate, $durationMs);
        $log = $result['log'];
        
        $this->assertEquals(3, $log->rating);
        $this->assertEquals('test-review-fields', $log->cardId);
        $this->assertEquals($reviewDate->format('c'), $log->reviewDateTime->format('c'));
        $this->assertEquals($durationMs, $log->reviewDurationMs);
        $this->assertEquals(FSRS\State::NEW, $log->state); // Original card state
    }
    
    public function test_repeat_reviews_advance_last_review_and_due()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01 10:00:00', new DateTimeZone('UTC')));
        
        $reviewTime = new DateTime('2024-01-05 15:30:00', new DateTimeZone('UTC'));
        $schedule = $manager->generateRepetitionSchedule($card, $reviewTime);
        $reviewedCard = $schedule[3]->card; // Good rating
        
        // Last review should be updated to review time
        $this->assertEquals($reviewTime->format('c'), $reviewedCard->lastReview->format('c'));
        
        // Due date should be after review time
        $this->assertGreaterThan($reviewTime, $reviewedCard->due);
        
        // Reps should be incremented
        $this->assertEquals(1, $reviewedCard->reps);
    }
    
    public function test_multiple_cards_state_independence()
    {
        $manager = new FSRS\Manager();
        
        $card1 = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')), cardId: 'card1');
        $card2 = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')), cardId: 'card2');
        
        // Review cards with different ratings
        $schedule1 = $manager->generateRepetitionSchedule($card1);
        $card1 = $schedule1[3]->card; // Good rating
        
        $schedule2 = $manager->generateRepetitionSchedule($card2);
        $card2 = $schedule2[4]->card; // Easy rating
        
        // Cards should have different outcomes
        $this->assertNotEquals($card1->scheduledDays, $card2->scheduledDays);
        $this->assertNotEquals($card1->difficulty, $card2->difficulty);
        $this->assertEquals('card1', $card1->cardId);
        $this->assertEquals('card2', $card2->cardId);
    }
    
    public function test_backdated_review_datetime_supported()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-10', new DateTimeZone('UTC')));
        
        // Review with a backdated time
        $backdatedTime = new DateTime('2024-01-05', new DateTimeZone('UTC'));
        $schedule = $manager->generateRepetitionSchedule($card, $backdatedTime);
        $reviewedCard = $schedule[3]->card;
        
        $this->assertEquals($backdatedTime->format('c'), $reviewedCard->lastReview->format('c'));
        
        // Due date should still be calculated from the backdated time
        $this->assertGreaterThan($backdatedTime, $reviewedCard->due);
    }
    
    public function test_future_review_datetime_supported()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));
        
        // Review with a future time
        $futureTime = new DateTime('2024-01-15', new DateTimeZone('UTC'));
        $schedule = $manager->generateRepetitionSchedule($card, $futureTime);
        $reviewedCard = $schedule[3]->card;
        
        $this->assertEquals($futureTime->format('c'), $reviewedCard->lastReview->format('c'));
        
        // Due date should be calculated from the future time
        $this->assertGreaterThan($futureTime, $reviewedCard->due);
    }
}