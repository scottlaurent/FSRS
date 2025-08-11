# FSRS PHP

A PHP implementation of the Free Spaced Repetition Scheduler (FSRS) algorithm.

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://github.com/scottlaurent/fsrs)
[![License](https://img.shields.io/github/license/scottlaurent/fsrs)](https://github.com/scottlaurent/fsrs/blob/main/LICENSE)
[![Tests](https://github.com/scottlaurent/fsrs/actions/workflows/tests.yml/badge.svg)](https://github.com/scottlaurent/fsrs/actions/workflows/tests.yml)

FSRS is a modern spaced repetition algorithm that adapts to your memory patterns, making learning more efficient than traditional methods like Anki's default algorithm.\n\n## How FSRS Works\n\nFSRS is based on the **Three Components of Memory** model and implements several key principles:\n\n### Core Memory Components\n- **Stability (S)**: Storage strength of memory - how long it can be retained\n- **Retrievability (R)**: Retrieval strength of memory - how easily it can be recalled  \n- **Difficulty (D)**: Inherent complexity of the material (1-10 scale)\n\n### Key Principles\n1. **Exponential Forgetting**: Memory follows the curve R(t) = 2^(-t/S)\n2. **Difficulty Impact**: More complex material results in lower stability increases\n3. **Stability Decay**: Higher current stability leads to smaller future stability gains\n4. **Retrievability Effect**: Lower retrievability at review time enables higher stability increases\n\n### Memory States\nCards progress through four distinct states:\n- **New (0)**: Never studied before\n- **Learning (1)**: Short-term learning with frequent reviews\n- **Review (2)**: Long-term review with spaced intervals\n- **Relearning (3)**: Forgotten cards returned to short-term learning"

## Table of Contents

- [Installation](#installation)
- [Quickstart](#quickstart)
- [Usage](#usage)
- [Configuration](#configuration)
- [API Reference](#api-reference)
- [Development](#development)
- [Testing](#testing)
- [Contributing](#contributing)
- [Inspiration](#inspiration)
- [License](#license)

## Installation

Install via Composer:

```bash
composer require scottlaurent/fsrs
```

## Quickstart

```php
<?php

use Scottlaurent\FSRS\Manager;
use Scottlaurent\FSRS\Card;

// Create a scheduler with default settings
$fsrs = new Manager();

// Create a new card
$card = new Card(new DateTime('2024-01-01', new DateTimeZone('UTC')));

// Generate scheduling options for different ratings
$schedule = $fsrs->generateRepetitionSchedule($card);

// Choose a rating and get the updated card
// 1 = Again, 2 = Hard, 3 = Good, 4 = Easy
$updatedCard = $schedule[3]->card; // User rated "Good"

echo "Next review: " . $updatedCard->due->format('Y-m-d H:i:s');
echo "Interval: " . $updatedCard->scheduledDays . " days";
```

## Usage

### Basic Card Review

```php
$fsrs = new Manager();
$card = new Card(new DateTime('now', new DateTimeZone('UTC')));

// First review
$schedule = $fsrs->generateRepetitionSchedule($card);
$card = $schedule[3]->card; // Good rating

// Subsequent reviews
$reviewDate = new DateTime('2024-01-05', new DateTimeZone('UTC'));
$schedule = $fsrs->generateRepetitionSchedule($card, $reviewDate);
$card = $schedule[2]->card; // Hard rating
```

### Advanced Usage

```php
// Check card retrievability
$retrievability = $fsrs->getCardRetrievability($card);
echo "Recall probability: " . ($retrievability * 100) . "%";

// Review card with timing data
$result = $fsrs->reviewCard($card, 3, new DateTime(), 2500);
$updatedCard = $result['card'];
$reviewLog = $result['log'];

// Serialize card data
$cardData = $card->toArray();
$cardJson = $card->toJson();

// Restore from serialized data
$restoredCard = Card::fromArray($cardData);
$cardFromJson = Card::fromJson($cardJson);

// Custom learning steps
$customFsrs = new Manager(
    learningSteps: [1, 5, 15, 30],
    relearningSteps: [5, 15],
    enableFuzzing: false
);
```

### Card States

Cards progress through different states:

- **0 (New)**: Card has never been studied
- **1 (Learning)**: Card is being learned for the first time
- **2 (Review)**: Card has graduated and is in long-term review
- **3 (Relearning)**: Card was forgotten and needs to be relearned

### Rating System

- **1 (Again)**: You forgot the card completely
- **2 (Hard)**: You remembered with significant difficulty  
- **3 (Good)**: You remembered after some hesitation
- **4 (Easy)**: You remembered easily and quickly

## Configuration

### Custom Parameters

```php
$fsrs = new Manager(
    defaultRequestRetention: 0.85,    // Target retention rate (85%)
    weights: [                        // Custom model weights
        0.4872, 1.4003, 3.7145, 13.8206, 5.1618, 1.2298, 0.8975, 0.031,
        1.6474, 0.1367, 1.0461, 2.1072, 0.0793, 0.3246, 1.587, 0.2272, 2.8755
    ],
    defaultMaximumInterval: 36500,    // Maximum interval in days (100 years)
    learningSteps: [1, 10],           // Learning phase intervals (minutes)
    relearningSteps: [10],            // Relearning phase intervals (minutes)
    enableFuzzing: true               // Add randomness to intervals
);
```

### Parameters Explained

- **defaultRequestRetention**: The target retention rate (0.0-1.0). Higher values create shorter intervals but better retention.
- **weights**: Array of 17 model weights that control the algorithm's behavior. Use the defaults unless you have training data.
- **defaultMaximumInterval**: Maximum number of days between reviews.
- **learningSteps**: Array of intervals (in minutes) for the learning phase.
- **relearningSteps**: Array of intervals (in minutes) for the relearning phase.
- **enableFuzzing**: Add randomness to intervals to prevent review clustering.

## API Reference

### Manager Class

#### `__construct(...)`

Create a new FSRS manager with optional custom parameters.

```php
new Manager(
    float $defaultRequestRetention = 0.90,
    array $weights = [...],
    int $defaultMaximumInterval = 36500,
    array $learningSteps = [1, 10],
    array $relearningSteps = [10],
    bool $enableFuzzing = true
)
```

#### `generateRepetitionSchedule(Card $card, ?DateTime $repeatDate = null): array`

Generate scheduling options for a card review.

**Parameters:**
- `$card`: The card being reviewed
- `$repeatDate`: The date of the review (defaults to now)

**Returns:** Array with keys 1-4 representing rating options, each containing a `card` property with the updated card state.

#### `getCardRetrievability(Card $card, ?DateTime $now = null): float`

Calculate the probability of successfully recalling a card at a given time.

**Parameters:**
- `$card`: The card to calculate retrievability for
- `$now`: The time to calculate retrievability at (defaults to now)

**Returns:** Float between 0.0 and 1.0 representing recall probability.

```php
$retrievability = $fsrs->getCardRetrievability($card);
echo "Recall probability: " . ($retrievability * 100) . "%";
```

#### `reviewCard(Card $card, int $rating, ?DateTime $reviewDate = null, ?int $reviewDurationMs = null): array`

Convenience method to review a card and get both the updated card and review log.

**Parameters:**
- `$card`: The card being reviewed
- `$rating`: Rating (1-4)
- `$reviewDate`: When the review occurred (defaults to now)
- `$reviewDurationMs`: How long the review took in milliseconds

**Returns:** Array with 'card' and 'log' keys.

```php
$result = $fsrs->reviewCard($card, 3, null, 2500);
$updatedCard = $result['card'];
$reviewLog = $result['log'];
```

#### Serialization Methods

```php
// Export configuration
$config = $fsrs->toArray();

// Restore from configuration
$fsrs = Manager::fromArray($config);
```

### Card Class

#### `__construct(...)`

Create a new card with optional parameters.

```php
new Card(
    ?DateTime $due = null,
    float $stability = 0,
    float $difficulty = 0,
    int $reps = 0,
    int $lapses = 0,
    int $state = 0,
    ?DateTime $lastReview = null,
    int $step = 0,
    ?string $cardId = null
)
```

**Properties:**
- `$due`: Next review date
- `$state`: Current state (0-3)
- `$reps`: Number of repetitions
- `$lapses`: Number of times forgotten
- `$stability`: Memory stability
- `$difficulty`: Card difficulty
- `$elapsedDays`: Days since last review
- `$scheduledDays`: Days until next review
- `$retrievability`: Probability of recall
- `$step`: Current step in learning/relearning
- `$cardId`: Optional unique identifier

#### Serialization Methods

```php
// Export card data
$cardData = $card->toArray();
$jsonString = $card->toJson();

// Restore from data
$card = Card::fromArray($cardData);
$card = Card::fromJson($jsonString);
```

### ReviewLog Class

The `ReviewLog` class tracks review sessions with enhanced metadata.

```php
new ReviewLog(
    int $rating,
    int $scheduledDays,
    int $elapsedDays,
    DateTime $review,
    int $state,
    ?string $cardId = null,
    ?DateTime $reviewDateTime = null,
    ?int $reviewDurationMs = null
)
```

**Properties:**
- `$rating`: The rating given (1-4)
- `$scheduledDays`: Scheduled interval
- `$elapsedDays`: Actual elapsed days
- `$review`: Review timestamp
- `$state`: Card state during review
- `$cardId`: Optional card identifier
- `$reviewDateTime`: Optional detailed review timestamp
- `$reviewDurationMs`: Optional review duration in milliseconds

#### Serialization Methods

```php
// Export review log
$logData = $reviewLog->toArray();
$jsonString = $reviewLog->toJson();

// Restore from data
$reviewLog = ReviewLog::fromArray($logData);
$reviewLog = ReviewLog::fromJson($jsonString);
```

## Development

This package uses Docker for development and testing.

### Setup

```bash
# Build and start containers
make up

# Install dependencies
make install
```

### Available Commands

- `make up` - Start containers in background
- `make down` - Stop containers
- `make ssh` - Get shell access to container
- `make install` - Install composer dependencies
- `make test` - Run all tests
- `make test-coverage` - Generate HTML coverage report
- `make help` - Show all available commands

## Testing

Run the test suite:

```bash
make test
```

The project includes comprehensive tests:

- **Feature Tests**: Real-world scenarios showing how the algorithm behaves
- **Unit Tests**: Individual class testing

Test coverage is maintained at >90% with detailed behavioral scenarios.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for your changes
4. Ensure all tests pass with `make test`
5. Submit a pull request

## Inspiration

This PHP implementation is inspired by and compatible with:

- [open-spaced-repetition/py-fsrs](https://github.com/open-spaced-repetition/py-fsrs) - Original Python implementation
- [open-spaced-repetition/fsrs4anki](https://github.com/open-spaced-repetition/fsrs4anki) - Anki add-on
- [FSRS Algorithm](https://github.com/open-spaced-repetition/fsrs-algorithm) - Algorithm specification

The FSRS algorithm was developed by [Jarrett Ye](https://github.com/L-M-Sherlock) and represents a significant improvement over traditional SM-2 based algorithms.

## License

MIT License - see the [LICENSE](LICENSE) file for details.

## Related Projects

- [FSRS-rs](https://github.com/open-spaced-repetition/fsrs-rs) - Rust implementation
- [ts-fsrs](https://github.com/open-spaced-repetition/ts-fsrs) - TypeScript implementation  
- [go-fsrs](https://github.com/open-spaced-repetition/go-fsrs) - Go implementation

---

**Learn more about spaced repetition:** [spaced-repetition.com](https://www.spaced-repetition.com/)