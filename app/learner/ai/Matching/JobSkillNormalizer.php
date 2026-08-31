<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use Throwable;

/**
 * Deterministic normalizer that turns recruiter display names from
 * `internship_posts.skillsJson` into canonical skill codes.
 *
 * Responsibilities are intentionally centralized here (single alias table,
 * single normalization pipeline) so no other pipeline component ever maps
 * display names ad hoc:
 *
 * 1. Display names are trimmed, lowercased and folded to a safe ASCII key
 *    (Vietnamese diacritics included) before matching.
 * 2. Known aliases (NodeJS -> nodejs, ReactJS -> react, Machine Learning ->
 *    machine_learning, Data Analysis -> data_analysis, ...) resolve
 *    deterministically to a canonical code.
 * 3. A normalized name may also resolve directly when its snake_case slug
 *    equals a canonical registry code.
 * 4. Only codes passed in as the canonical registry (loaded from the
 *    `skills` table by the caller) can ever be returned. Nothing is
 *    invented: unresolvable display names are reported as unmapped labels.
 * 5. No personal data is consumed or emitted.
 */
final class JobSkillNormalizer
{
    /** Maximum stored length for a single display label (mapped or unmapped). */
    public const MAX_LABEL_LENGTH = 100;

    /**
     * Canonical display aliases. Keys are normalized ASCII display names
     * (lowercase, diacritics stripped, punctuation collapsed to single
     * spaces); values are canonical skill codes. An alias only applies when
     * the target code exists in the registry handed to the constructor.
     */
    private const ALIASES = [
        // English display variants used by enterprise recruiters.
        'machine learning' => 'machine_learning',
        'ml' => 'machine_learning',
        'data analytics' => 'data_analysis',
        'node js' => 'nodejs',
        'nodejs' => 'nodejs',
        'reactjs' => 'react',
        'react js' => 'react',
        'js' => 'javascript',
        'html and css' => 'html_css',
        'html5 css3' => 'html_css',
        'ui ux' => 'ui_ux_design',
        'rest api' => 'api_development',
        'automation testing' => 'software_testing',
        'automation qa' => 'software_testing',
        'qa testing' => 'software_testing',
        'excel' => 'spreadsheet',
        'data structures' => 'algorithms',

        // Vietnamese display names used by enterprise recruiters.
        'lap trinh python' => 'python',
        'phan tich du lieu' => 'data_analysis',
        'phan tich thong ke' => 'statistical_analysis',
        'giai quyet van de' => 'problem_solving',
        'lam viec nhom' => 'teamwork',
        'giao tiep' => 'communication',
        'nghien cuu' => 'research',
        'lanh dao' => 'leadership',
        'khoi nghiep' => 'entrepreneurship',
        'thiet ke sang tao' => 'creative_design',
        'xu ly ngon ngu tu nhien' => 'nlp',
        'phat trien api' => 'api_development',
        'thiet ke co so du lieu' => 'database_design',
        'kiem thu phan mem' => 'software_testing',
        'phan tich roi' => 'roi_analysis',
        'thiet ke ui ux' => 'ui_ux_design',
        'cau truc du lieu va giai thuat' => 'algorithms',
        'tu duy logic' => 'problem_solving',
        'ke chuyen storytelling' => 'storytelling',
    ];

    private const DIACRITIC_ASCII = [
        'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'đ' => 'd',
        'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
    ];

    /** @var array<string,true> */
    private readonly array $canonical;

    /** @param list<string> $canonicalCodes Active codes loaded from the skills registry. */
    public function __construct(array $canonicalCodes)
    {
        $canonical = [];
        foreach ($canonicalCodes as $code) {
            if (is_string($code) && preg_match('/^[a-z0-9]+(_[a-z0-9]+)*$/', $code) === 1) {
                $canonical[$code] = true;
            }
        }
        $this->canonical = $canonical;
    }

    /**
     * Normalizes a raw skills payload (decoded JSON array, JSON string or a
     * single display name) into mapped canonical skills plus unmapped labels.
     */
    public function normalize(mixed $raw): JobSkillNormalization
    {
        $mapped = [];
        $unmapped = [];
        $seenCodes = [];
        $seenKeys = [];

        foreach ($this->displayEntries($raw) as $label) {
            $key = self::key($label);
            if ($key === '' || isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;
            $storedLabel = mb_substr($label, 0, self::MAX_LABEL_LENGTH, 'UTF-8');

            $code = $this->resolve($key);
            if ($code === null) {
                $unmapped[] = $storedLabel;
                continue;
            }
            if (isset($seenCodes[$code])) {
                continue;
            }
            $seenCodes[$code] = true;
            $mapped[] = ['code' => $code, 'label' => $storedLabel];
        }

        return new JobSkillNormalization($mapped, $unmapped);
    }

    /** @return list<string> */
    private function displayEntries(mixed $raw): array
    {
        if (is_array($raw)) {
            $entries = [];
            foreach ($raw as $entry) {
                if (is_string($entry)) {
                    $entries[] = trim($entry);
                    continue;
                }
                if (is_array($entry)) {
                    foreach (['name', 'code', 'label'] as $field) {
                        if (isset($entry[$field]) && is_string($entry[$field])) {
                            $entries[] = trim($entry[$field]);
                            break;
                        }
                    }
                }
            }
            return $entries;
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }
            try {
                $decoded = json_decode($trimmed, true, 64);
            } catch (Throwable) {
                $decoded = null;
            }
            return is_array($decoded) ? $this->displayEntries($decoded) : [$trimmed];
        }

        return [];
    }

    /** @return ?string the canonical code, or null when the display name is unmapped */
    private function resolve(string $key): ?string
    {
        $aliased = self::ALIASES[$key] ?? null;
        if (is_string($aliased) && isset($this->canonical[$aliased])) {
            return $aliased;
        }

        $slug = str_replace(' ', '_', $key);
        return isset($this->canonical[$slug]) ? $slug : null;
    }

    private static function key(string $raw): string
    {
        $value = mb_strtolower(trim($raw), 'UTF-8');
        // Keep language symbols distinct before punctuation stripping so
        // "C#" and "C++" can never collapse onto a bare "c" code.
        $value = str_replace(['c#', 'c++', 'c/c++'], ['csharp', 'cpp', 'cpp'], $value);
        $value = strtr($value, self::DIACRITIC_ASCII);
        $value = (string) preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
