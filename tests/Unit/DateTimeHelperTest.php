<?php

namespace Tests\Unit;

use Scottlaurent\FSRS\DateTimeHelper;
use Tests\TestCase;
use DateTime;
use DateTimeZone;

class DateTimeHelperTest extends TestCase
{
    public function test_ensure_utc_now_creates_utc_datetime_when_null()
    {
        $result = DateTimeHelper::ensureUtcNow(null);
        
        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('UTC', $result->getTimezone()->getName());
    }
    
    public function test_ensure_utc_now_returns_utc_datetime_when_already_utc()
    {
        $utcDateTime = new DateTime('2024-01-15 10:30:00', new DateTimeZone('UTC'));
        
        $result = DateTimeHelper::ensureUtcNow($utcDateTime);
        
        $this->assertEquals($utcDateTime, $result);
        $this->assertEquals('UTC', $result->getTimezone()->getName());
    }
    
    public function test_ensure_utc_now_throws_exception_for_non_utc_timezone()
    {
        $nonUtcDateTime = new DateTime('2024-01-15 10:30:00', new DateTimeZone('America/New_York'));
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('datetime must be timezone-aware and set to UTC');
        
        DateTimeHelper::ensureUtcNow($nonUtcDateTime);
    }
    
    public function test_add_minutes_creates_new_datetime_instance()
    {
        $originalDate = new DateTime('2024-01-15 10:30:00', new DateTimeZone('UTC'));
        $expectedDate = new DateTime('2024-01-15 10:45:00', new DateTimeZone('UTC'));
        
        $result = DateTimeHelper::addMinutes($originalDate, 15);
        
        $this->assertEquals($expectedDate->format('c'), $result->format('c'));
        $this->assertNotSame($originalDate, $result); // Ensure it's a clone
        $this->assertEquals('2024-01-15T10:30:00+00:00', $originalDate->format('c')); // Original unchanged
    }
    
    public function test_add_minutes_handles_negative_values()
    {
        $originalDate = new DateTime('2024-01-15 10:30:00', new DateTimeZone('UTC'));
        $expectedDate = new DateTime('2024-01-15 10:15:00', new DateTimeZone('UTC'));
        
        $result = DateTimeHelper::addMinutes($originalDate, -15);
        
        $this->assertEquals($expectedDate->format('c'), $result->format('c'));
    }
    
    public function test_add_days_creates_new_datetime_instance()
    {
        $originalDate = new DateTime('2024-01-15 10:30:00', new DateTimeZone('UTC'));
        $expectedDate = new DateTime('2024-01-18 10:30:00', new DateTimeZone('UTC'));
        
        $result = DateTimeHelper::addDays($originalDate, 3);
        
        $this->assertEquals($expectedDate->format('c'), $result->format('c'));
        $this->assertNotSame($originalDate, $result); // Ensure it's a clone
        $this->assertEquals('2024-01-15T10:30:00+00:00', $originalDate->format('c')); // Original unchanged
    }
    
    public function test_add_days_handles_negative_values()
    {
        $originalDate = new DateTime('2024-01-15 10:30:00', new DateTimeZone('UTC'));
        $expectedDate = new DateTime('2024-01-12 10:30:00', new DateTimeZone('UTC'));
        
        $result = DateTimeHelper::addDays($originalDate, -3);
        
        $this->assertEquals($expectedDate->format('c'), $result->format('c'));
    }
}