<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use Throwable;

/**
 * Fail-safe sanitizer for `internship_posts.requirementsJson` (and prose
 * fields such as description/benefits) before the content may reach an
 * opportunity candidate.
 *
 * Guarantees:
 * 1. Input must be a JSON array of strings (or an already decoded list),
 *    exactly matching the production schema; anything else fails safe to an
 *    empty list and is reported through sourceStatus().
 * 2. Raw markup, <script>/<style>/<iframe>/<object>/<embed> payloads,
 *    entities and control characters never survive.
 * 3. Bounded output: at most 20 items of at most 500 characters (1000 for
 *    prose fields).
 * 4. Nothing is inferred: dropped content is never replaced with invented
 *    GPA, experience or skill claims.
 * 5. No personal data is consumed or emitted.
 */
final class JobRequirementsSanitizer
{
    public const MAX_ITEMS = 20;

    public const MAX_ITEM_LENGTH = 500;

    public const MAX_PROSE_LENGTH = 1000;

    public const MAX_SOURCE_LENGTH = 20000;

    /**
     * @return list<string> sanitized, structured recruitment requirements
     */
    public function sanitize(mixed $raw): array
    {
        [$items] = $this->process($raw, self::MAX_ITEM_LENGTH);
        return $items;
    }

    /**
     * @return string one of: ok (structured list processed), invalid
     *                 (malformed JSON or wrong shape), missing (no data)
     */
    public function sourceStatus(mixed $raw): string
    {
        if ($raw === null) {
            return 'missing';
        }
        if (is_string($raw) && trim($raw) === '') {
            return 'missing';
        }
        if (is_array($raw)) {
            return array_is_list($raw) ? 'ok' : 'invalid';
        }
        if (is_string($raw)) {
            try {
                $decoded = json_decode(mb_substr(trim($raw), 0, self::MAX_SOURCE_LENGTH, 'UTF-8'), true, 64);
            } catch (Throwable) {
                return 'invalid';
            }
            return is_array($decoded) && array_is_list($decoded) ? 'ok' : 'invalid';
        }
        return 'invalid';
    }

    /**
     * Sanitizes a single prose value (description, benefits) with the same
     * markup and control-character rules.
     */
    public function sanitizeText(mixed $raw, int $maxLength = self::MAX_PROSE_LENGTH): string
    {
        if (!is_string($raw)) {
            return '';
        }
        $maxLength = max(1, min($maxLength, self::MAX_PROSE_LENGTH));
        return self::clean(mb_substr($raw, 0, self::MAX_SOURCE_LENGTH, 'UTF-8'), $maxLength);
    }

    /** @return array{0:list<string>,1:string} */
    private function process(mixed $raw, int $maxLength): array
    {
        $status = $this->sourceStatus($raw);
        if ($status !== 'ok') {
            return [[], $status];
        }

        $entries = is_array($raw) ? $raw : $this->decodeList($raw);
        $items = [];
        foreach ($entries as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $clean = self::clean(mb_substr($entry, 0, self::MAX_SOURCE_LENGTH, 'UTF-8'), $maxLength);
            if ($clean === '') {
                continue;
            }
            $items[] = $clean;
            if (count($items) >= self::MAX_ITEMS) {
                break;
            }
        }

        return [$items, $status];
    }

    /** @return list<mixed>|null */
    private function decodeList(string $raw): ?array
    {
        try {
            $decoded = json_decode(trim($raw), true, 64);
        } catch (Throwable) {
            return null;
        }
        return is_array($decoded) && array_is_list($decoded) ? $decoded : null;
    }

    private static function clean(string $value, int $maxLength): string
    {
        // Remove dangerous blocks with their content; an unclosed block is
        // removed together with everything after it so script bodies can
        // never leak as prose.
        $value = (string) preg_replace(
            '/<\s*(script|style|iframe|object|embed)\b[^>]*>.*?(?:<\s*\/\s*\1\s*>|$)/is',
            ' ',
            $value,
        );
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        $value = (string) preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $value);
        $value = self::redactSensitiveValues($value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        return mb_strlen($value, 'UTF-8') > $maxLength ? mb_substr($value, 0, $maxLength, 'UTF-8') : $value;
    }

    private static function redactSensitiveValues(string $value): string
    {
        // Free-form recruiter content can contain personal contact details.
        // Redact explicit labelled fields first so their whole value is
        // removed, then catch standalone email and phone patterns.
        $value = (string) preg_replace(
            '/(?:địa\s*chỉ|address)\s*:\s*[^;|\r\n]+/iu',
            'Địa chỉ: [đã ẩn địa chỉ]',
            $value,
        );
        $value = (string) preg_replace(
            '/(?:họ\s*(?:và\s*)?tên|tên\s*(?:người\s*)?liên\s*hệ|contact\s*(?:name|person))\s*:\s*[^;|\r\n]+/iu',
            'Tên liên hệ: [đã ẩn tên liên hệ]',
            $value,
        );
        $value = (string) preg_replace(
            '/(?:số\s*điện\s*thoại|điện\s*thoại|sđt|phone|mobile|tel)\s*:\s*[^;|\r\n]+/iu',
            'Số điện thoại: [đã ẩn số điện thoại]',
            $value,
        );
        $value = (string) preg_replace(
            '/(?<![\p{L}\p{N}._%+\-])[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}(?![\p{L}\p{N}])/iu',
            '[đã ẩn email]',
            $value,
        );
        return (string) preg_replace(
            '/(?<![\p{L}\p{N}])(?:\+\d{1,3}|0)(?:[\s().\-]*\d){7,12}(?![\p{L}\p{N}])/u',
            '[đã ẩn số điện thoại]',
            $value,
        );
    }
}
