<?php

namespace Tests\Unit;

use Scottlaurent\FSRS\Manager;
use Scottlaurent\FSRS\Card;
use Tests\TestCase;

class ManagerTest extends TestCase
{
    public function test_manager_creation_with_default_parameters()
    {
        $manager = new Manager();
        
        $this->assertEquals(0.90, $manager->defaultRequestRetention);
        $this->assertEquals(36500, $manager->defaultMaximumInterval);
        $this->assertIsArray($manager->weights);
        $this->assertCount(17, $manager->weights);
    }
    
    public function test_manager_creation_with_custom_parameters()
    {
        $customWeights = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.0, 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7];
        $customRetention = 0.85;
        $customMaxInterval = 365;
        
        $manager = new Manager($customRetention, $customWeights, $customMaxInterval);
        
        $this->assertEquals($customRetention, $manager->defaultRequestRetention);
        $this->assertEquals($customMaxInterval, $manager->defaultMaximumInterval);
        $this->assertEquals($customWeights, $manager->weights);
    }
    
    public function test_generate_repetition_schedule_returns_scheduling_cards()
    {
        $manager = new Manager();
        $card = new Card(new \DateTime('2024-01-01', new \DateTimeZone('UTC')));
        
        $result = $manager->generateRepetitionSchedule($card);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey(1, $result); // Again
        $this->assertArrayHasKey(2, $result); // Hard  
        $this->assertArrayHasKey(3, $result); // Good
        $this->assertArrayHasKey(4, $result); // Easy
        
        // Each should have a card property
        $this->assertObjectHasProperty('card', $result[1]);
        $this->assertObjectHasProperty('card', $result[2]);
        $this->assertObjectHasProperty('card', $result[3]);
        $this->assertObjectHasProperty('card', $result[4]);
    }
    
    public function test_generate_repetition_schedule_with_review_date()
    {
        $manager = new Manager();
        $card = new Card(new \DateTime('2024-01-01', new \DateTimeZone('UTC')));
        $reviewDate = new \DateTime('2024-01-10', new \DateTimeZone('UTC'));
        
        $result = $manager->generateRepetitionSchedule($card, $reviewDate);
        
        $this->assertIsArray($result);
        $this->assertCount(4, $result);
    }
}