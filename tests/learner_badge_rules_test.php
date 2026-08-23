<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Domain\LevelProgression;
use TalentHub\Learner\Data\Service\BadgeRuleEngine;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$engine = new BadgeRuleEngine();

// 1. Valid rule evaluations
$criteria1 = ['fact' => 'confirmed_experience_hours', 'operator' => 'gte', 'value' => 10];
$res1 = $engine->evaluate($criteria1, ['confirmed_experience_hours' => 15.5]);
$assert($res1['eligible'] === true, '15.5 >= 10 is eligible');
$assert($res1['current'] === 15.5, 'current is 15.5');
$assert($res1['target'] === 10.0 || $res1['target'] === 10, 'target is 10');
$assert($res1['progressPercent'] === 100, 'progress capped at 100%');

$res2 = $engine->evaluate($criteria1, ['confirmed_experience_hours' => 5.0]);
$assert($res2['eligible'] === false, '5.0 >= 10 is not eligible');
$assert($res2['current'] === 5.0, 'current is 5.0');
$assert($res2['progressPercent'] === 50, 'progress is 50%');

$res3 = $engine->evaluate($criteria1, ['confirmed_experience_hours' => 10]);
$assert($res3['eligible'] === true, '10 >= 10 is eligible at exact boundary');
$assert($res3['progressPercent'] === 100, 'progress is 100% at boundary');

// 2. Reject unknown facts
$thrown = false;
try {
    $engine->evaluate(['fact' => 'unknown_fact', 'operator' => 'gte', 'value' => 5], []);
} catch (InvalidArgumentException) {
    $thrown = true;
}
$assert($thrown, 'Unknown fact must throw InvalidArgumentException');

// 3. Reject unknown operators
$thrown = false;
try {
    $engine->evaluate(['fact' => 'confirmed_experience_hours', 'operator' => 'lte', 'value' => 5], []);
} catch (InvalidArgumentException) {
    $thrown = true;
}
$assert($thrown, 'Unknown operator must throw InvalidArgumentException');

// 4. Reject invalid criteria schema (extra/missing keys)
$thrown = false;
try {
    $engine->evaluate(['fact' => 'confirmed_experience_hours', 'operator' => 'gte'], []);
} catch (InvalidArgumentException) {
    $thrown = true;
}
$assert($thrown, 'Missing value key must throw InvalidArgumentException');

$thrown = false;
try {
    $engine->evaluate(['fact' => 'confirmed_experience_hours', 'operator' => 'gte', 'value' => 5, 'extra' => 'hack'], []);
} catch (InvalidArgumentException) {
    $thrown = true;
}
$assert($thrown, 'Extra keys must throw InvalidArgumentException');

// 5. Reject negative or non-finite values
$thrown = false;
try {
    $engine->evaluate(['fact' => 'confirmed_experience_hours', 'operator' => 'gte', 'value' => -5], []);
} catch (InvalidArgumentException) {
    $thrown = true;
}
$assert($thrown, 'Negative value must throw InvalidArgumentException');

$thrown = false;
try {
    $engine->evaluate(['fact' => 'confirmed_experience_hours', 'operator' => 'gte', 'value' => INF], []);
} catch (InvalidArgumentException) {
    $thrown = true;
}
$assert($thrown, 'INF value must throw InvalidArgumentException');

// 6. LevelProgression tests
$assert(LevelProgression::CONFIG_VERSION === 'experience-hours-v1', 'Config version matches specification');

// 0 hours -> Explorer
$lvl0 = LevelProgression::fromHours(0.0);
$assert($lvl0['name'] === 'Explorer', '0 hours is Explorer');
$assert($lvl0['number'] === 1, 'Explorer is level 1');
$assert($lvl0['currentHours'] === 0.0, 'current hours is 0.0');
$assert($lvl0['targetHours'] === 10.0, 'target hours is 10.0');
$assert($lvl0['nextLevel'] === 'Innovator', 'next level is Innovator');
$assert($lvl0['remainingHours'] === 10.0, 'remaining hours is 10.0');
$assert($lvl0['progressPercent'] === 0, 'progress is 0%');

// 5 hours -> Explorer
$lvl5 = LevelProgression::fromHours(5.0);
$assert($lvl5['name'] === 'Explorer', '5 hours is Explorer');
$assert($lvl5['progressPercent'] === 50, '5/10 is 50%');

// 10 hours -> Innovator boundary
$lvl10 = LevelProgression::fromHours(10.0);
$assert($lvl10['name'] === 'Innovator', '10 hours is Innovator');
$assert($lvl10['number'] === 2, 'Innovator is level 2');
$assert($lvl10['targetHours'] === 100.0, 'target hours is 100.0');
$assert($lvl10['nextLevel'] === 'Expert', 'next level is Expert');
$assert($lvl10['remainingHours'] === 90.0, 'remaining hours is 90.0');
$assert($lvl10['progressPercent'] === 0, 'progress is 0% into Innovator');

// 55 hours -> Innovator
$lvl55 = LevelProgression::fromHours(55.0);
$assert($lvl55['name'] === 'Innovator', '55 hours is Innovator');
$assert($lvl55['progressPercent'] === 50, '(55-10)/(100-10) is 50%');

// 100 hours -> Expert boundary
$lvl100 = LevelProgression::fromHours(100.0);
$assert($lvl100['name'] === 'Expert', '100 hours is Expert');
$assert($lvl100['number'] === 3, 'Expert is level 3');
$assert($lvl100['targetHours'] === 200.0, 'target hours is 200.0');
$assert($lvl100['nextLevel'] === 'Master', 'next level is Master');
$assert($lvl100['remainingHours'] === 100.0, 'remaining hours is 100.0');
$assert($lvl100['progressPercent'] === 0, 'progress is 0% into Expert');

// 200 hours -> Master boundary
$lvl200 = LevelProgression::fromHours(200.0);
$assert($lvl200['name'] === 'Master', '200 hours is Master');
$assert($lvl200['number'] === 4, 'Master is level 4');
$assert($lvl200['nextLevel'] === null, 'next level is null at Master');
$assert($lvl200['remainingHours'] === 0.0, 'remaining hours is 0.0');
$assert($lvl200['progressPercent'] === 100, 'progress is 100% at Master');

// 350 hours -> Master capped
$lvl350 = LevelProgression::fromHours(350.0);
$assert($lvl350['name'] === 'Master', '350 hours is Master');
$assert($lvl350['nextLevel'] === null, 'next level is null');
$assert($lvl350['progressPercent'] === 100, 'progress is 100%');

echo "learner_badge_rules_test: OK\n";
