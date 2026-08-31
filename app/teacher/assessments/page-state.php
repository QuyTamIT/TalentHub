<?php

declare(strict_types=1);

/**
 * @param array<string,mixed> $data
 */
function teacherGradingPageState(bool $dataLoaded, bool $unexpectedLoadError, array $data): string
{
    if ($unexpectedLoadError) {
        return 'load_error';
    }

    if (!$dataLoaded) {
        return 'request_error';
    }

    if (($data['activities'] ?? []) === []) {
        return 'empty_activities';
    }

    if (($data['selectedActivity'] ?? null) !== null && ($data['students'] ?? []) === []) {
        return 'empty_students';
    }

    if (($data['selectedActivity'] ?? null) !== null) {
        return 'ready';
    }

    return 'choose_activity';
}
