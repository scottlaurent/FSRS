<?php

namespace Tests\Feature;

use Scottlaurent\FSRS;
use Tests\TestCase;

class RetrievabilityTest extends TestCase
{
    public function test_retrievability_calculation_for_new_cards()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card();
        
        // New cards should have 0 retrievability
        $retrievability = $manager->getCardRetrievability($card);
        $this->assertEquals(0.0, $retrievability);
    }
    
    public function test_retrievability_calculation_for_review_cards()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card();
        
        // Set up a card with some stability in review state
        $card->state = FSRS\State::REVIEW;
        $card->stability = 10.0;
        $card->due = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        
        // Test retrievability at due date (should be high)
        $dueDate = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        $retrievabilityAtDue = $manager->getCardRetrievability($card, $dueDate);
        $this->assertGreaterThan(0.5, $retrievabilityAtDue);
        
        // Test retrievability well past due date (should be lower)
        $pastDueDate = new \DateTime('2024-01-15', new \DateTimeZone('UTC'));
        $retrievabilityPastDue = $manager->getCardRetrievability($card, $pastDueDate);
        $this->assertLessThan($retrievabilityAtDue, $retrievabilityPastDue);
        
        // Test retrievability before due date (should be higher)
        $beforeDueDate = new \DateTime('2023-12-28', new \DateTimeZone('UTC'));
        $retrievabilityBeforeDue = $manager->getCardRetrievability($card, $beforeDueDate);
        $this->assertGreaterThan($retrievabilityAtDue, $retrievabilityBeforeDue);
    }
    
    public function test_retrievability_with_different_stability_values()
    {
        $manager = new FSRS\Manager();
        
        // Create cards with different stability values
        $cardLowStability = new FSRS\Card();
        $cardLowStability->state = FSRS\State::REVIEW;
        $cardLowStability->stability = 5.0;
        $cardLowStability->due = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        
        $cardHighStability = new FSRS\Card();
        $cardHighStability->state = FSRS\State::REVIEW;
        $cardHighStability->stability = 20.0;
        $cardHighStability->due = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        
        // Test same time delay, different stability
        $testDate = new \DateTime('2024-01-10', new \DateTimeZone('UTC'));
        
        $retrievabilityLow = $manager->getCardRetrievability($cardLowStability, $testDate);
        $retrievabilityHigh = $manager->getCardRetrievability($cardHighStability, $testDate);
        
        // Higher stability should give higher retrievability after the same delay
        $this->assertGreaterThan($retrievabilityLow, $retrievabilityHigh);
    }
    
    public function test_review_card_method_returns_card_and_log()
    {
        $manager = new FSRS\Manager();
        $card = new FSRS\Card();
        $reviewDate = new \DateTime('2024-01-15 14:30:00', new \DateTimeZone('UTC'));
        
        $result = $manager->reviewCard($card, 3, $reviewDate, 1500);
        
        $this->assertArrayHasKey('card', $result);
        $this->assertArrayHasKey('log', $result);
        $this->assertInstanceOf(FSRS\Card::class, $result['card']);
        $this->assertInstanceOf(FSRS\ReviewLog::class, $result['log']);
        
        // Check that the log has the correct information
        $log = $result['log'];
        $this->assertEquals(3, $log->rating);
        $this->assertEquals($card->cardId, $log->cardId);
        $this->assertEquals(1500, $log->reviewDurationMs);
        $this->assertEquals($reviewDate->format('c'), $log->reviewDateTime->format('c'));
    }
    
    public function test_manager_configuration_with_custom_steps()
    {
        $manager = new FSRS\Manager(
            learningSteps: [1, 3, 7],
            relearningSteps: [5, 15],
            enableFuzzing: false
        );
        
        $this->assertEquals([1, 3, 7], $manager->learningSteps);
        $this->assertEquals([5, 15], $manager->relearningSteps);
        $this->assertFalse($manager->enableFuzzing);
    }
}