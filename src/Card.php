<?php

namespace Scottlaurent\FSRS;

use DateTime;
use DateTimeZone;

/**
 * Represents a single flashcard in the FSRS system
 * 
 * A Card object encapsulates all the necessary information for the FSRS algorithm to
 * calculate optimal review scheduling. Each card progresses through different states
 * and accumulates memory-related statistics over time.
 * 
 * Card States:
 * - 0 (NEW): Card has never been studied
 * - 1 (LEARNING): Card is being learned for the first time
 * - 2 (REVIEW): Card has graduated to long-term review
 * - 3 (RELEARNING): Card was forgotten and needs to be relearned
 * 
 * Key Properties:
 * - Stability: How long the card can be remembered (higher = longer intervals)
 * - Difficulty: Inherent complexity of the card content (0-10 scale)
 * - Retrievability: Current probability of successful recall (0.0-1.0)
 * 
 * @package Scottlaurent\FSRS
 */
class Card
{
    public $cardId;

    public $due;

    public $stability;

    public $difficulty;

    public $elapsedDays;

    public $scheduledDays;

    public $reps;

    public $lapses;

    public $state;

    public $step;

    public $lastReview;

    public $retrievability;

    /**
     * Create a new Card instance
     * 
     * @param DateTime|null $due When this card is next due for review. For new cards, this represents
     *                          when the card was created. Gets updated after each review.
     * @param float $stability Memory stability - how long the card can be remembered.
     *                        Higher values mean longer intervals between reviews.
     * @param float $difficulty Inherent difficulty of the card content (0-10 scale).
     *                         Affects how much stability increases with successful reviews.
     * @param int $elapsedDays Number of days since the last review (calculated during review)
     * @param int $scheduledDays Number of days until next review (calculated during scheduling)
     * @param int $reps Total number of reviews performed on this card
     * @param int $lapses Number of times this card was forgotten (rating = 1)
     * @param int $state Current card state: 0=New, 1=Learning, 2=Review, 3=Relearning
     * @param int $step Current step within learning/relearning phase (0-based index)
     * @param DateTime|null $lastReview When this card was last reviewed
     * @param string|null $cardId Optional unique identifier for tracking this specific card
     */
    public function __construct(
        ?DateTime $due = null,
        float $stability = 0,
        float $difficulty = 0,
        int $elapsedDays = 0,
        int $scheduledDays = 0,
        int $reps = 0,
        int $lapses = 0,
        int $state = State::NEW,
        int $step = 0,
        ?\DateTime $lastReview = null,
        ?string $cardId = null
    ) {
        $this->cardId = $cardId ?? $this->generateCardId();
        $this->due = $due ?? new DateTime('now', new DateTimeZone('UTC'));
        $this->stability = $stability;
        $this->difficulty = $difficulty;
        $this->elapsedDays = $elapsedDays;
        $this->scheduledDays = $scheduledDays;
        $this->reps = $reps;
        $this->lapses = $lapses;
        $this->state = $state;
        $this->step = $step;
        $this->lastReview = $lastReview;
    }

    /**
     * Generate a unique identifier for this card
     * 
     * Creates a unique ID using PHP's uniqid() function with high resolution timestamp.
     * This ID can be used to track cards across sessions or in databases.
     * 
     * @return string Unique card identifier with 'card_' prefix
     */
    private function generateCardId(): string
    {
        return uniqid('card_', true);
    }

    /**
     * Export card data to an associative array
     * 
     * Serializes all card properties to an array format suitable for JSON encoding,
     * database storage, or API responses. DateTime objects are converted to ISO 8601 strings.
     * 
     * @return array Associative array containing all card properties with DateTime objects
     *              converted to ISO 8601 formatted strings (or null)
     */
    public function toArray(): array
    {
        return [
            'cardId' => $this->cardId,
            'due' => $this->due ? $this->due->format('c') : null,
            'stability' => $this->stability,
            'difficulty' => $this->difficulty,
            'elapsedDays' => $this->elapsedDays,
            'scheduledDays' => $this->scheduledDays,
            'reps' => $this->reps,
            'lapses' => $this->lapses,
            'state' => $this->state,
            'step' => $this->step,
            'lastReview' => $this->lastReview ? $this->lastReview->format('c') : null,
            'retrievability' => $this->retrievability,
        ];
    }

    /**
     * Create a Card instance from an associative array
     * 
     * Reconstructs a Card object from previously serialized data. This is the inverse
     * operation of toArray(). DateTime strings are parsed back to DateTime objects.
     * Missing array keys will use default values.
     * 
     * @param array $data Associative array containing card data (typically from toArray())
     * 
     * @return self New Card instance with data restored from the array
     * 
     * @throws \Exception If date strings cannot be parsed
     */
    public static function fromArray(array $data): self
    {
        $card = new self();
        $card->cardId = $data['cardId'] ?? $card->generateCardId();
        $card->due = isset($data['due']) ? new DateTime($data['due']) : new DateTime('now', new DateTimeZone('UTC'));
        $card->stability = $data['stability'] ?? 0;
        $card->difficulty = $data['difficulty'] ?? 0;
        $card->elapsedDays = $data['elapsedDays'] ?? 0;
        $card->scheduledDays = $data['scheduledDays'] ?? 0;
        $card->reps = $data['reps'] ?? 0;
        $card->lapses = $data['lapses'] ?? 0;
        $card->state = $data['state'] ?? State::NEW;
        $card->step = $data['step'] ?? 0;
        $card->lastReview = isset($data['lastReview']) ? new DateTime($data['lastReview']) : null;
        $card->retrievability = $data['retrievability'] ?? null;
        return $card;
    }

    /**
     * Export card data to a JSON string
     * 
     * Convenience method that combines toArray() with JSON encoding.
     * Useful for API responses or storing card data as JSON.
     * 
     * @return string JSON representation of the card data
     * 
     * @throws \JsonException If JSON encoding fails
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Create a Card instance from a JSON string
     * 
     * Convenience method that combines JSON decoding with fromArray().
     * This is the inverse operation of toJson().
     * 
     * @param string $json JSON string containing card data
     * 
     * @return self New Card instance with data restored from JSON
     * 
     * @throws \JsonException If JSON cannot be decoded
     * @throws \Exception If date strings cannot be parsed
     */
    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true));
    }
}
