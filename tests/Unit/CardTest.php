<?php

namespace Tests\Unit;

use Scottlaurent\FSRS\Card;
use Tests\TestCase;

class CardTest extends TestCase
{
    public function test_card_creation_with_initial_values()
    {
        $date = new \DateTime('2024-01-01', new \DateTimeZone('UTC'));
        $card = new Card($date);
        
        $this->assertEquals($date, $card->due);
        $this->assertEquals(0, $card->state);
        $this->assertEquals(0, $card->reps);
        $this->assertEquals(0, $card->lapses);
        $this->assertEquals(0, $card->stability);
        $this->assertEquals(0, $card->difficulty);
        $this->assertEquals(0, $card->elapsedDays);
        $this->assertEquals(0, $card->scheduledDays);
        $this->assertNull($card->retrievability);
    }
    
    public function test_card_creation_with_different_timezones()
    {
        $utcDate = new \DateTime('2024-01-01 10:00:00', new \DateTimeZone('UTC'));
        $estDate = new \DateTime('2024-01-01 10:00:00', new \DateTimeZone('America/New_York'));
        
        $utcCard = new Card($utcDate);
        $estCard = new Card($estDate);
        
        // Both cards should be created successfully regardless of timezone
        $this->assertInstanceOf(Card::class, $utcCard);
        $this->assertInstanceOf(Card::class, $estCard);
        $this->assertEquals($utcDate, $utcCard->due);
        $this->assertEquals($estDate, $estCard->due);
    }
}