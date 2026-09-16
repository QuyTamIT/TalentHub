<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Repository;

use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

final class InternshipSpecialtyRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * List active specialties visible to an enterprise:
     *   - Global specialties (enterpriseId IS NULL, status = active)
     *   - Enterprise's own custom specialties (enterpriseId = :enterpriseId)
     * Order: global first (by displayOrder, name), then enterprise custom (same).
     *
     * @return list<array<string,mixed>>
     */
    public function listForEnterprise(string $enterpriseId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT id, enterpriseId, name, slug, category, description, displayOrder, status,
                   (enterpriseId IS NULL) AS isGlobal
            FROM internship_specialties
            WHERE status = 'active'
              AND (enterpriseId IS NULL OR enterpriseId = :enterpriseId)
            ORDER BY isGlobal DESC, displayOrder ASC, name ASC
        SQL);
        $statement->execute(['enterpriseId' => $enterpriseId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(string $specialtyId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM internship_specialties WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $specialtyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function findBySlugForEnterprise(string $enterpriseId, string $slug): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT * FROM internship_specialties
            WHERE slug = :slug
              AND (enterpriseId IS NULL OR enterpriseId = :enterpriseId)
            LIMIT 1
        SQL);
        $statement->execute(['slug' => $slug, 'enterpriseId' => $enterpriseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * Create a new specialty owned by an enterprise.
     * Validation rules enforced by caller (Service).
     *
     * @return array<string,mixed>
     */
    public function createForEnterprise(string $enterpriseId, string $userId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $category = trim((string) ($input['category'] ?? 'general'));
        $description = trim((string) ($input['description'] ?? ''));
        $baseSlug = trim((string) ($input['slug'] ?? ''));

        $slug = $baseSlug !== '' ? self::toAsciiSlug($baseSlug) : self::toAsciiSlug($name);
        if ($slug === '') {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Tên lĩnh vực không hợp lệ (cần tối thiểu 2 ký tự chữ/số).');
        }
        if (mb_strlen($slug) > 160) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Slug vượt quá 160 ký tự.');
        }

        // Uniqueness within (enterpriseId, slug) — UNIQUE KEY enforces this, but we give
        // a friendly message instead of an integrity exception.
        if ($this->findBySlugForEnterprise($enterpriseId, $slug) !== null) {
            throw new ApiException(409, 'SPECIALTY_ALREADY_EXISTS', 'Lĩnh vực này đã có trong danh mục của bạn.');
        }

        $nextOrder = $this->nextDisplayOrder($enterpriseId);

        $id = Uuid::v4();
        $now = $this->now();

        $insert = $this->pdo->prepare(<<<'SQL'
            INSERT INTO internship_specialties
                (id, enterpriseId, name, slug, category, description, status, displayOrder, createdBy, createdAt, updatedAt)
            VALUES
                (:id, :enterpriseId, :name, :slug, :category, :description, 'active', :displayOrder, :createdBy, :createdAt, :updatedAt)
        SQL);
        $insert->execute([
            ':id'           => $id,
            ':enterpriseId' => $enterpriseId,
            ':name'         => $name,
            ':slug'         => $slug,
            ':category'     => $category,
            ':description'  => $description !== '' ? $description : null,
            ':displayOrder' => $nextOrder,
            ':createdBy'    => $userId,
            ':createdAt'    => $now,
            ':updatedAt'    => $now,
        ]);

        $created = $this->findById($id);
        if (!is_array($created)) {
            throw new ApiException(500, 'INTERNAL_ERROR', 'Không thể tạo lĩnh vực mới.');
        }
        return $created;
    }

    private function nextDisplayOrder(string $enterpriseId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(MAX(displayOrder), 0) + 1 FROM internship_specialties WHERE enterpriseId = :enterpriseId'
        );
        $statement->execute(['enterpriseId' => $enterpriseId]);
        return (int) $statement->fetchColumn();
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    /**
     * Vietnamese diacritic → ASCII mapping.
     *
     * Mirrors the convention in
     * app/learner/ai/Sources/Database/DatabaseOpportunitySource::DIACRITIC_ASCII.
     * We cannot rely on iconv('UTF-8','ASCII//TRANSLIT') here: on glibc it maps
     * 'ế' → "'e" and 'ồ' → "o`", which collapses to a wrong slug
     * ('Thiết kế Đồ họa' => 'thit-k-d-ha' instead of 'thiet-ke-do-hoa').
     */
    private const DIACRITIC_ASCII = [
        'a' => 'a', 'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'đ' => 'd',
        'e' => 'e', 'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'i' => 'i', 'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'o' => 'o', 'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'u' => 'u', 'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'y' => 'y', 'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
    ];

    /**
     * Convert a string to an ASCII slug suitable for URLs.
     * Lowercase, strip Vietnamese/European diacritics, replace non-alphanumeric
     * with hyphens, collapse repeated hyphens, trim leading/trailing hyphens.
     */
    public static function toAsciiSlug(string $input): string
    {
        $lower = mb_strtolower(trim($input), 'UTF-8');

        // Vietnamese first (covers both cases after mb_strtolower of the
        // lowercase-only table below via the uppercase map).
        $lower = strtr($lower, self::DIACRITIC_ASCII);
        $lower = strtr($lower, array_change_key_case(self::DIACRITIC_ASCII, CASE_UPPER));

        // Remaining accents (other Latin scripts) — iconv is adequate once the
        // multi-codepoint Vietnamese sequences are already resolved.
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        $lower = $ascii === false ? $lower : strtolower($ascii);

        // Drop any transliteration artifacts (quotes/backticks/tildes/carets).
        $lower = (string) preg_replace('/[\'"`^~]+/', '', $lower);
        $hyphenated = (string) preg_replace('/[^a-z0-9]+/', '-', $lower);

        return trim($hyphenated, '-');
    }
}
