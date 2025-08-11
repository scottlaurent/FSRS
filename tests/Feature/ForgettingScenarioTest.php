<?php

namespace Tests\Feature;

use Scottlaurent\FSRS;
use Tests\TestCase;

class ForgettingScenarioTest extends TestCase
{
    /**
     * Test what happens when a user forgets a card they previously knew
     * This simulates the forgetting curve and relearning process
     */
    public function test_forgetting_after_successful_reviews()
    {
        $manager = new FSRS\Manager();
        $initialDate = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        $card = new FSRS\Card($initialDate);
        
        // Build up the card through successful reviews
        // Review 1: Good
        $scheduler = $manager->generateRepetitionSchedule($card);
        $card = $scheduler[3]->card;
        $this->assertEquals(1, $card->state); // Learning
        $this->assertEquals(1, $card->reps);
        
        // Review 2: Good (graduates to Review state)
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $card = $scheduler[3]->card;
        $this->assertEquals(2, $card->state); // Review
        $this->assertEquals(2, $card->reps);
        $this->assertGreaterThan(0, $card->scheduledDays);
        
        // Review 3: Good (successful review)
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $card = $scheduler[3]->card;
        $this->assertEquals(2, $card->state); // Still Review
        $this->assertEquals(3, $card->reps);
        $initialInterval = $card->scheduledDays;
        
        // Now forget the card: Again rating
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $forgottenCard = $scheduler[1]->card; // Rating 1 = Again
        
        // Card should enter Relearning state
        $this->assertEquals(3, $forgottenCard->state); // Relearning
        $this->assertEquals(4, $forgottenCard->reps); // Reps increased
        $this->assertGreaterThan(0, $forgottenCard->lapses); // Lapses tracked
        $this->assertEquals(0, $forgottenCard->scheduledDays); // Back to immediate review
        
        // The difficulty should have increased after forgetting
        $this->assertGreaterThan($card->difficulty, $forgottenCard->difficulty);
    }
    
    /**
     * Test recovery from relearning state
     */
    public function test_recovery_from_relearning()
    {
        $manager = new FSRS\Manager();
        $initialDate = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        $card = new FSRS\Card($initialDate);
        
        // Quick progression to Review state
        $scheduler = $manager->generateRepetitionSchedule($card);
        $card = $scheduler[3]->card; // Good
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $card = $scheduler[3]->card; // Good - now in Review state
        
        // Forget the card
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $card = $scheduler[1]->card; // Again - now in Relearning state
        $this->assertEquals(3, $card->state); // Relearning
        
        // Relearn successfully
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $card = $scheduler[3]->card; // Good
        
        // Should return to Review state after successful relearning
        $this->assertEquals(2, $card->state); // Review
        $this->assertGreaterThan(0, $card->scheduledDays);
        
        // The interval should be shorter than if we never forgot
        // (demonstrating the penalty for forgetting)
        $this->assertLessThan(30, $card->scheduledDays); // Conservative estimate
    }
    
    /**
     * Test multiple forgetting episodes
     */
    public function test_multiple_forgetting_episodes()
    {
        $manager = new FSRS\Manager();
        $initialDate = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        $card = new FSRS\Card($initialDate);
        
        // Build up the card
        $scheduler = $manager->generateRepetitionSchedule($card);
        $card = $scheduler[3]->card; // Good
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $card = $scheduler[3]->card; // Good
        
        $originalDifficulty = $card->difficulty;
        
        // First forgetting episode
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $card = $scheduler[1]->card; // Again
        $this->assertEquals(1, $card->lapses);
        $firstLapseDifficulty = $card->difficulty;
        
        // Recover
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $card = $scheduler[3]->card; // Good
        
        // Second forgetting episode
        $scheduler = $manager->generateRepetitionSchedule($card, $card->due);
        $card = $scheduler[1]->card; // Again
        $this->assertEquals(2, $card->lapses);
        $secondLapseDifficulty = $card->difficulty;
        
        // Difficulty should increase with each lapse
        $this->assertGreaterThan($originalDifficulty, $firstLapseDifficulty);
        $this->assertGreaterThan($firstLapseDifficulty, $secondLapseDifficulty);
    }
    
    /**
     * Test the impact of rating "Hard" vs "Again"
     */
    public function test_hard_vs_again_rating_differences()
    {
        $manager = new FSRS\Manager();
        $initialDate = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        
        // Scenario A: Rating "Hard"
        $cardA = new FSRS\Card($initialDate);
        $scheduler = $manager->generateRepetitionSchedule($cardA);
        $cardA = $scheduler[3]->card; // Good
        $scheduler = $manager->generateRepetitionSchedule($cardA, $cardA->due);
        $cardA = $scheduler[3]->card; // Good - in Review state
        
        $scheduler = $manager->generateRepetitionSchedule($cardA, $cardA->due);
        $hardCard = $scheduler[2]->card; // Rating 2 = Hard
        
        // Scenario B: Rating "Again"
        $cardB = new FSRS\Card($initialDate);
        $scheduler = $manager->generateRepetitionSchedule($cardB);
        $cardB = $scheduler[3]->card; // Good
        $scheduler = $manager->generateRepetitionSchedule($cardB, $cardB->due);
        $cardB = $scheduler[3]->card; // Good - in Review state
        
        $scheduler = $manager->generateRepetitionSchedule($cardB, $cardB->due);
        $againCard = $scheduler[1]->card; // Rating 1 = Again
        
        // "Hard" should keep the card in Review state with reduced interval
        $this->assertEquals(2, $hardCard->state); // Review
        $this->assertGreaterThan(0, $hardCard->scheduledDays);
        
        // "Again" should put the card in Relearning state
        $this->assertEquals(3, $againCard->state); // Relearning
        $this->assertEquals(0, $againCard->scheduledDays);
        $this->assertGreaterThan(0, $againCard->lapses);
        
        // Difficulty should increase more with "Again" than "Hard"
        $this->assertGreaterThan($hardCard->difficulty, $againCard->difficulty);
    }
}