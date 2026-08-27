<?php

declare(strict_types=1);

if (!function_exists('learner_activity_category_label')) {
    function learner_activity_category_label(string $category): string
    {
        return [
            'career_technical' => 'Kỹ thuật',
            'career_business' => 'Kinh doanh',
            'career_arts' => 'Sáng tạo',
            'career_sports_academic' => 'Thể thao & học thuật',
        ][strtolower(trim($category))] ?? 'Khác';
    }
}
