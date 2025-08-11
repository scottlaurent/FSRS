<?php

namespace Tests\Feature;

use Scottlaurent\FSRS;
use Tests\TestCase;

class LearningProgressionTest extends TestCase
{
    protected $debug = false;

    public static function dateStringFromNow(int $days = 0): string
    {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));
        $date->modify("+{$days} days");
        return $date->format('Y-m-d');
    }

    /**
     * Test a long-term learning progression with consistent Good ratings
     * This simulates a user who consistently remembers a card correctly
     */
    public function test_consistent_good_ratings_progression()
    {
        $ratingHistory = [3, 3, 3, 3, 3, 1, 3, 3, 3, 3, 3, 3];
        $expectedOutputs = [
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(),
                'due' => self::dateStringFromNow(0),
                'interval' => 0,
                'reps' => 1,
                'difficulty' => 5.1618,
                'state' => 1,
                'retrievability' => null,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(),
                'due' => self::dateStringFromNow(4),
                'interval' => 4.0,
                'reps' => 2,
                'difficulty' => 5.1618,
                'state' => 2,
                'retrievability' => null,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(4),
                'due' => self::dateStringFromNow(19),
                'interval' => 15.0,
                'reps' => 3,
                'difficulty' => 5.1618,
                'state' => 2,
                'retrievability' => 0.89349950,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(19),
                'due' => self::dateStringFromNow(68),
                'interval' => 49.0,
                'reps' => 4,
                'difficulty' => 5.1618,
                'state' => 2,
                'retrievability' => 0.89889404,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(68),
                'due' => self::dateStringFromNow(214),
                'interval' => 146.0,
                'reps' => 5,
                'difficulty' => 5.1618,
                'state' => 2,
                'retrievability' => 0.90079900,
            ],
            [
                'rating' => 1,
                'reviewDate' => self::dateStringFromNow(214),
                'due' => self::dateStringFromNow(214),
                'interval' => 0,
                'reps' => 6,
                'difficulty' => 6.9012,
                'state' => 3,
                'retrievability' => 0.89980674,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(214),
                'due' => self::dateStringFromNow(223),
                'interval' => 9.0,
                'reps' => 7,
                'difficulty' => 6.9012,
                'state' => 2,
                'retrievability' => 0.89980674,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(223),
                'due' => self::dateStringFromNow(247),
                'interval' => 24.0,
                'reps' => 8,
                'difficulty' => 6.8472,
                'state' => 2,
                'retrievability' => 0.89788061,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(247),
                'due' => self::dateStringFromNow(308),
                'interval' => 61.0,
                'reps' => 9,
                'difficulty' => 6.795,
                'state' => 2,
                'retrievability' => 0.90154817,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(308),
                'due' => self::dateStringFromNow(453),
                'interval' => 145.0,
                'reps' => 10,
                'difficulty' => 6.7444,
                'state' => 2,
                'retrievability' => 0.90053412,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(453),
                'due' => self::dateStringFromNow(777),
                'interval' => 324.0,
                'reps' => 11,
                'difficulty' => 6.6953,
                'state' => 2,
                'retrievability' => 0.90006704,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(777),
                'due' => self::dateStringFromNow(1464),
                'interval' => 687.0,
                'reps' => 12,
                'difficulty' => 6.6478,
                'state' => 2,
                'retrievability' => 0.90002481,
            ],
        ];

        $this->runSchedulingScenario($ratingHistory, $expectedOutputs);
    }

    /**
     * Test mixed ratings progression with Hard and Easy ratings
     */
    public function test_mixed_ratings_progression()
    {
        $ratingHistory = [2, 3, 4, 2, 3, 4];
        $expectedOutputs = [
            [
                'rating' => 2,
                'reviewDate' => self::dateStringFromNow(),
                'due' => self::dateStringFromNow(0),
                'interval' => 0,
                'reps' => 1,
                'difficulty' => 6.3916,
                'state' => 1,
                'retrievability' => null,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(),
                'due' => self::dateStringFromNow(1),
                'interval' => 1.0,
                'reps' => 2,
                'difficulty' => 6.3916,
                'state' => 2,
                'retrievability' => null,
            ],
            [
                'rating' => 4,
                'reviewDate' => self::dateStringFromNow(1),
                'due' => self::dateStringFromNow(10),
                'interval' => 9.0,
                'reps' => 3,
                'difficulty' => 5.4838,
                'state' => 2,
                'retrievability' => 0.92548463,
            ],
            [
                'rating' => 2,
                'reviewDate' => self::dateStringFromNow(10),
                'due' => self::dateStringFromNow(24),
                'interval' => 14.0,
                'reps' => 4,
                'difficulty' => 6.3435,
                'state' => 2,
                'retrievability' => 0.89866666,
            ],
            [
                'rating' => 3,
                'reviewDate' => self::dateStringFromNow(24),
                'due' => self::dateStringFromNow(64),
                'interval' => 40.0,
                'reps' => 5,
                'difficulty' => 6.3069,
                'state' => 2,
                'retrievability' => 0.89780416,
            ],
            [
                'rating' => 4,
                'reviewDate' => self::dateStringFromNow(64),
                'due' => self::dateStringFromNow(290),
                'interval' => 226.0,
                'reps' => 6,
                'difficulty' => 5.4017,
                'state' => 2,
                'retrievability' => 0.89935685,
            ],
        ];

        $this->runSchedulingScenario($ratingHistory, $expectedOutputs);
    }

    private function runSchedulingScenario(array $ratingHistory, array $expectedOutputs): void
    {
        $initialDateTime = new \DateTime(self::dateStringFromNow(), new \DateTimeZone('UTC'));
        $fsrsManager = new FSRS\Manager();

        foreach ($ratingHistory as $index => $rating) {

            if (!isset($card)) {
                $card = new FSRS\Card($initialDateTime);
                $scheduler = $fsrsManager->generateRepetitionSchedule($card);
                $reviewDate = clone $initialDateTime;
            } else {
                $scheduler = $fsrsManager->generateRepetitionSchedule($card, $card->due);
                $reviewDate = clone $card->due;
            }
            $card = $scheduler[$rating]->card;
            $dueDate = clone $card->due;

            if ($this->debug) {
                $this->dumpSchedulingDetails($rating, $reviewDate, $dueDate, $card);
            }

            $this->assertEquals($expectedOutputs[$index], [
                'rating' => $rating,
                'reviewDate' => $reviewDate->format('Y-m-d'),
                'due' => $dueDate->format('Y-m-d'),
                'interval' => $card->scheduledDays,
                'reps' => $card->reps,
                'difficulty' => round($card->difficulty, 4),
                'state' => $card->state,
                'retrievability' => $card->retrievability !== null ? round($card->retrievability, 8) : null,
            ]);
        }
    }

    private function dumpSchedulingDetails(int $rating, \DateTime $reviewDate, \DateTime $dueDate, FSRS\Card $card): void
    {
        dump([
            'rating' => $rating,
            'reviewDate' => $reviewDate->format('Y-m-d'),
            'due' => $dueDate->format('Y-m-d'),
            'interval' => $card->scheduledDays,
            'reps' => $card->reps,
            'difficulty' => $card->difficulty,
            'state' => $card->state,
            'retrievability' => $card->retrievability !== null ? round($card->retrievability, 8) : null,
        ]);
    }
}