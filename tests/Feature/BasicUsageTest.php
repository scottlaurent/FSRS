<?php

namespace Tests\Feature;

use Scottlaurent\FSRS;
use Tests\TestCase;

class BasicUsageTest extends TestCase
{
    public function test_basic_card_creation_and_first_review()
    {
        $manager = new FSRS\Manager();
        $initialDate = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        
        // Create a new card
        $card = new FSRS\Card($initialDate);
        
        // Initial state should be New
        $this->assertEquals(0, $card->state);
        $this->assertEquals(0, $card->reps);
        $this->assertNull($card->retrievability);
        
        // First review with "Good" rating
        $scheduler = $manager->generateRepetitionSchedule($card);
        $reviewedCard = $scheduler[3]->card; // Rating 3 = Good
        
        // After first review, card should be in Learning state
        $this->assertEquals(1, $reviewedCard->state);
        $this->assertEquals(1, $reviewedCard->reps);
        $this->assertEquals(0, $reviewedCard->scheduledDays);
    }
    
    public function test_simple_progression_good_ratings()
    {
        $manager = new FSRS\Manager();
        $initialDate = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        $card = new FSRS\Card($initialDate);
        
        // Review 1: Good
        $scheduler = $manager->generateRepetitionSchedule($card);
        $card = $scheduler[3]->card;
        
        $this->assertEquals(1, $card->reps);
        $this->assertEquals(0, $card->scheduledDays);
        
        // Review 2: Good 
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $card = $scheduler[3]->card;
        
        $this->assertEquals(2, $card->reps);
        $this->assertGreaterThan(0, $card->scheduledDays);
        
        // Review 3: Good
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $card = $scheduler[3]->card;
        
        $this->assertEquals(3, $card->reps);
        $this->assertEquals(2, $card->state); // Review state
        $this->assertNotNull($card->retrievability);
        $this->assertGreaterThan(0, $card->scheduledDays);
    }
    
    public function test_easy_rating_scenario()
    {
        $manager = new FSRS\Manager();
        $initialDate = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        $card = new FSRS\Card($initialDate);
        
        // First review: Easy (should have longer interval)
        $scheduler = $manager->generateRepetitionSchedule($card);
        $easyCard = $scheduler[4]->card; // Rating 4 = Easy
        $goodCard = $scheduler[3]->card; // Rating 3 = Good for comparison
        
        // Easy should give longer interval than Good
        $this->assertGreaterThan($goodCard->scheduledDays, $easyCard->scheduledDays);
    }
    
    public function test_again_rating_scenario()
    {
        $manager = new FSRS\Manager();
        $initialDate = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        $card = new FSRS\Card($initialDate);
        
        // First review: Again (forgot the card)
        $scheduler = $manager->generateRepetitionSchedule($card);
        $againCard = $scheduler[1]->card; // Rating 1 = Again
        
        // Should stay in learning with minimal interval
        $this->assertEquals(1, $againCard->state); // Learning state
        $this->assertEquals(0, $againCard->scheduledDays);
    }
}