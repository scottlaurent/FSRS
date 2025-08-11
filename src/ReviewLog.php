<?php

namespace Scottlaurent\FSRS;

/**
 * Review Log - Records metadata about each card review session
 * 
 * The ReviewLog class captures detailed information about a review session,
 * including the user's rating, timing information, and card state at the time
 * of review. This data is essential for analysis and algorithm improvement.
 * 
 * This class supports the FSRS principle of tracking detailed review history
 * to better understand memory patterns and optimize future scheduling.
 * 
 * Rating Scale:
 * - 1 (Again): User forgot the card completely
 * - 2 (Hard): User remembered with significant difficulty
 * - 3 (Good): User remembered after some hesitation
 * - 4 (Easy): User remembered easily and quickly
 * 
 * @package Scottlaurent\FSRS
 */
class ReviewLog
{
    /**
     * Create a new ReviewLog instance
     * 
     * @param int $rating The rating given by the user (1-4):
     *                   1 = Again (forgot completely)
     *                   2 = Hard (remembered with difficulty)
     *                   3 = Good (remembered with some hesitation)
     *                   4 = Easy (remembered easily)
     * @param int $scheduledDays Number of days the card was scheduled to wait before this review
     * @param int $elapsedDays Actual number of days that passed since the last review
     * @param \DateTime $review Timestamp when the review occurred
     * @param int $state The card's state at the time of review (0=New, 1=Learning, 2=Review, 3=Relearning)
     * @param string|null $cardId Optional unique identifier linking this log to a specific card
     * @param \DateTime|null $reviewDateTime Optional detailed timestamp (defaults to $review)
     * @param int|null $reviewDurationMs Optional duration in milliseconds showing how long the review took
     */
    public function __construct(
        public int $rating,
        public int $scheduledDays,
        public int $elapsedDays,
        public \DateTime $review,
        public int $state,
        public ?string $cardId = null,
        public ?\DateTime $reviewDateTime = null,
        public ?int $reviewDurationMs = null
    ) {
        $this->cardId = $cardId ?? uniqid('card_', true);
        $this->reviewDateTime = $reviewDateTime ?? $review;
        $this->reviewDurationMs = $reviewDurationMs;
    }

    /**
     * Export review log to an associative array
     * 
     * Serializes all review log properties to an array format suitable for JSON encoding,
     * database storage, or API responses. DateTime objects are converted to ISO 8601 strings.
     * 
     * @return array Associative array containing all review log properties with DateTime objects
     *              converted to ISO 8601 formatted strings
     */
    public function toArray(): array
    {
        return [
            'cardId' => $this->cardId,
            'rating' => $this->rating,
            'scheduledDays' => $this->scheduledDays,
            'elapsedDays' => $this->elapsedDays,
            'review' => $this->review->format('c'),
            'reviewDateTime' => $this->reviewDateTime->format('c'),
            'state' => $this->state,
            'reviewDurationMs' => $this->reviewDurationMs,
        ];
    }

    /**
     * Create a ReviewLog instance from an associative array
     * 
     * Reconstructs a ReviewLog object from previously serialized data. This is the inverse
     * operation of toArray(). DateTime strings are parsed back to DateTime objects.
     * 
     * @param array $data Associative array containing review log data (typically from toArray())
     * 
     * @return self New ReviewLog instance with data restored from the array
     * 
     * @throws \Exception If date strings cannot be parsed
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rating: $data['rating'],
            scheduledDays: $data['scheduledDays'],
            elapsedDays: $data['elapsedDays'],
            review: new \DateTime($data['review']),
            state: $data['state'],
            cardId: $data['cardId'] ?? null,
            reviewDateTime: isset($data['reviewDateTime']) ? new \DateTime($data['reviewDateTime']) : null,
            reviewDurationMs: $data['reviewDurationMs'] ?? null
        );
    }

    /**
     * Export review log to a JSON string
     * 
     * Convenience method that combines toArray() with JSON encoding.
     * Useful for API responses or storing review log data as JSON.
     * 
     * @return string JSON representation of the review log data
     * 
     * @throws \JsonException If JSON encoding fails
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Create a ReviewLog instance from a JSON string
     * 
     * Convenience method that combines JSON decoding with fromArray().
     * This is the inverse operation of toJson().
     * 
     * @param string $json JSON string containing review log data
     * 
     * @return self New ReviewLog instance with data restored from JSON
     * 
     * @throws \JsonException If JSON cannot be decoded
     * @throws \Exception If date strings cannot be parsed
     */
    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true));
    }
}
