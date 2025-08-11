<?php

namespace Tests\Feature;

use Scottlaurent\FSRS;
use Tests\TestCase;

class SerializationTest extends TestCase
{
    public function test_card_serialization_roundtrip()
    {
        $originalCard = new FSRS\Card(
            due: new \DateTime('2024-01-15', new \DateTimeZone('UTC')),
            stability: 10.5,
            difficulty: 6.2,
            reps: 3,
            lapses: 1,
            state: FSRS\State::REVIEW,
            step: 2,
            cardId: 'test-card-123'
        );
        
        // Test array serialization
        $cardArray = $originalCard->toArray();
        $restoredCard = FSRS\Card::fromArray($cardArray);
        
        $this->assertEquals($originalCard->cardId, $restoredCard->cardId);
        $this->assertEquals($originalCard->due->format('c'), $restoredCard->due->format('c'));
        $this->assertEquals($originalCard->stability, $restoredCard->stability);
        $this->assertEquals($originalCard->difficulty, $restoredCard->difficulty);
        $this->assertEquals($originalCard->reps, $restoredCard->reps);
        $this->assertEquals($originalCard->lapses, $restoredCard->lapses);
        $this->assertEquals($originalCard->state, $restoredCard->state);
        $this->assertEquals($originalCard->step, $restoredCard->step);
        
        // Test JSON serialization
        $jsonString = $originalCard->toJson();
        $restoredFromJson = FSRS\Card::fromJson($jsonString);
        
        $this->assertEquals($originalCard->cardId, $restoredFromJson->cardId);
        $this->assertEquals($originalCard->due->format('c'), $restoredFromJson->due->format('c'));
    }
    
    public function test_review_log_serialization()
    {
        $reviewLog = new FSRS\ReviewLog(
            rating: 3,
            scheduledDays: 10,
            elapsedDays: 8,
            review: new \DateTime('2024-01-15 10:30:00', new \DateTimeZone('UTC')),
            state: FSRS\State::REVIEW,
            cardId: 'test-card-456',
            reviewDurationMs: 2500
        );
        
        // Test array serialization
        $logArray = $reviewLog->toArray();
        $restoredLog = FSRS\ReviewLog::fromArray($logArray);
        
        $this->assertEquals($reviewLog->cardId, $restoredLog->cardId);
        $this->assertEquals($reviewLog->rating, $restoredLog->rating);
        $this->assertEquals($reviewLog->scheduledDays, $restoredLog->scheduledDays);
        $this->assertEquals($reviewLog->reviewDurationMs, $restoredLog->reviewDurationMs);
        
        // Test JSON serialization
        $jsonString = $reviewLog->toJson();
        $restoredFromJson = FSRS\ReviewLog::fromJson($jsonString);
        
        $this->assertEquals($reviewLog->cardId, $restoredFromJson->cardId);
        $this->assertEquals($reviewLog->rating, $restoredFromJson->rating);
    }
    
    public function test_manager_serialization()
    {
        $manager = new FSRS\Manager(
            defaultRequestRetention: 0.85,
            learningSteps: [1, 5, 10],
            relearningSteps: [5, 10],
            enableFuzzing: false
        );
        
        // Test array serialization
        $managerArray = $manager->toArray();
        $restoredManager = FSRS\Manager::fromArray($managerArray);
        
        $this->assertEquals($manager->defaultRequestRetention, $restoredManager->defaultRequestRetention);
        $this->assertEquals($manager->learningSteps, $restoredManager->learningSteps);
        $this->assertEquals($manager->relearningSteps, $restoredManager->relearningSteps);
        $this->assertEquals($manager->enableFuzzing, $restoredManager->enableFuzzing);
    }
}