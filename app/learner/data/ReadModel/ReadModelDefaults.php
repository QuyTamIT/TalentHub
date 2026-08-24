<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\ReadModel;

use TalentHub\Learner\Data\Support\LearnerViewAdapter;

final class ReadModelDefaults
{
    public static function apply(array $record, array $defaults, string $domain): array
    {
        $view = LearnerViewAdapter::record($record);
        $notes = is_array($view['data_notes'] ?? null) ? $view['data_notes'] : [];

        foreach ($defaults as $field => $default) {
            if (array_key_exists($field, $view) && $view[$field] !== null) {
                continue;
            }

            $view[$field] = $default;
            $notes[] = "{$domain}.{$field} uses a safe compatibility default because the schema has no value.";
        }

        $view['data_notes'] = array_values(array_unique($notes));
        return $view;
    }
}
