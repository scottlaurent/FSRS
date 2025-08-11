<?php

namespace Tests\Unit;

use Scottlaurent\FSRS;
use Tests\TestCase;
use DateTime;
use DateTimeZone;

class SchedulingCardsTest extends TestCase
{
    public function test_scheduling_cards_creates_four_card_clones()
    {
        $originalCard = new FSRS\Card(
            due: new DateTime('2024-01-15', new DateTimeZone('UTC')),
            stability: 5.0,
            difficulty: 6.0,
            reps: 3,
            lapses: 1,
            state: FSRS\State::REVIEW
        );
        
        $schedulingCards = new FSRS\SchedulingCards($originalCard);
        
        // Verify all four cards exist and are clones
        $this->assertInstanceOf(FSRS\Card::class, $schedulingCards->again);
        $this->assertInstanceOf(FSRS\Card::class, $schedulingCards->hard);
        $this->assertInstanceOf(FSRS\Card::class, $schedulingCards->good);
        $this->assertInstanceOf(FSRS\Card::class, $schedulingCards->easy);
        
        // Verify they are different instances
        $this->assertNotSame($originalCard, $schedulingCards->again);
        $this->assertNotSame($originalCard, $schedulingCards->hard);
        $this->assertNotSame($originalCard, $schedulingCards->good);
        $this->assertNotSame($originalCard, $schedulingCards->easy);
        
        // Verify they have the same initial values
        $this->assertEquals($originalCard->stability, $schedulingCards->again->stability);
        $this->assertEquals($originalCard->difficulty, $schedulingCards->hard->difficulty);
        $this->assertEquals($originalCard->reps, $schedulingCards->good->reps);
        $this->assertEquals($originalCard->lapses, $schedulingCards->easy->lapses);
    }
    
    public function test_schedule_method_sets_intervals_and_due_dates()
    {
        $card = new FSRS\Card(new DateTime('2024-01-15 10:00:00', new DateTimeZone('UTC')));
        $schedulingCards = new FSRS\SchedulingCards($card);
        $baseTime = new DateTime('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        
        $schedulingCards->schedule($baseTime, 2.0, 7.0, 14.0);
        
        // Check scheduled days
        $this->assertEquals(0, $schedulingCards->again->scheduledDays);
        $this->assertEquals(2.0, $schedulingCards->hard->scheduledDays);
        $this->assertEquals(7.0, $schedulingCards->good->scheduledDays);
        $this->assertEquals(14.0, $schedulingCards->easy->scheduledDays);
        
        // Due dates should be in the future
        $this->assertInstanceOf(DateTime::class, $schedulingCards->again->due);
        $this->assertInstanceOf(DateTime::class, $schedulingCards->hard->due);
        $this->assertInstanceOf(DateTime::class, $schedulingCards->good->due);
        $this->assertInstanceOf(DateTime::class, $schedulingCards->easy->due);
    }
    
    public function test_schedule_method_handles_zero_hard_interval()
    {
        $card = new FSRS\Card(new DateTime('2024-01-15 10:00:00', new DateTimeZone('UTC')));
        $schedulingCards = new FSRS\SchedulingCards($card);
        $baseTime = new DateTime('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        
        $schedulingCards->schedule($baseTime, 0, 7.0, 14.0);
        
        // Hard interval of 0 should result in a short time interval
        $this->assertEquals(0, $schedulingCards->hard->scheduledDays);
        $this->assertInstanceOf(DateTime::class, $schedulingCards->hard->due);
    }
    
    public function test_update_state_for_new_cards()
    {
        $card = new FSRS\Card(state: FSRS\State::NEW);
        $schedulingCards = new FSRS\SchedulingCards($card);
        
        $schedulingCards->updateState(FSRS\State::NEW);
        
        $this->assertEquals(FSRS\State::LEARNING, $schedulingCards->again->state);
        $this->assertEquals(FSRS\State::LEARNING, $schedulingCards->hard->state);
        $this->assertEquals(FSRS\State::LEARNING, $schedulingCards->good->state);
        $this->assertEquals(FSRS\State::REVIEW, $schedulingCards->easy->state);
    }
    
    public function test_update_state_for_learning_cards()
    {
        $card = new FSRS\Card(state: FSRS\State::LEARNING);
        $schedulingCards = new FSRS\SchedulingCards($card);
        
        $schedulingCards->updateState(FSRS\State::LEARNING);
        
        $this->assertEquals(FSRS\State::LEARNING, $schedulingCards->again->state);
        $this->assertEquals(FSRS\State::LEARNING, $schedulingCards->hard->state);
        $this->assertEquals(FSRS\State::REVIEW, $schedulingCards->good->state);
        $this->assertEquals(FSRS\State::REVIEW, $schedulingCards->easy->state);
    }
    
    public function test_update_state_for_relearning_cards()
    {
        $card = new FSRS\Card(state: FSRS\State::RELEARNING);
        $schedulingCards = new FSRS\SchedulingCards($card);
        
        $schedulingCards->updateState(FSRS\State::RELEARNING);
        
        $this->assertEquals(FSRS\State::RELEARNING, $schedulingCards->again->state);
        $this->assertEquals(FSRS\State::RELEARNING, $schedulingCards->hard->state);
        $this->assertEquals(FSRS\State::REVIEW, $schedulingCards->good->state);
        $this->assertEquals(FSRS\State::REVIEW, $schedulingCards->easy->state);
    }
    
    public function test_update_state_for_review_cards()
    {
        $card = new FSRS\Card(state: FSRS\State::REVIEW, lapses: 2);
        $schedulingCards = new FSRS\SchedulingCards($card);
        
        $schedulingCards->updateState(FSRS\State::REVIEW);
        
        $this->assertEquals(FSRS\State::RELEARNING, $schedulingCards->again->state);
        $this->assertEquals(FSRS\State::REVIEW, $schedulingCards->hard->state);
        $this->assertEquals(FSRS\State::REVIEW, $schedulingCards->good->state);
        $this->assertEquals(FSRS\State::REVIEW, $schedulingCards->easy->state);
        
        // Check that lapse count is incremented for again rating
        $this->assertEquals(3, $schedulingCards->again->lapses);
        $this->assertEquals(2, $schedulingCards->hard->lapses); // Others unchanged
    }
}