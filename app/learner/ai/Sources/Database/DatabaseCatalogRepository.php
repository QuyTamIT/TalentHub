<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use JsonException;
use PDO;
use TalentHub\Learner\Ai\Queue\TransactionalAiOutboxPublisher;

/** Canonical catalog mutation boundary with tenant-safe transactional refresh events. */
final class DatabaseCatalogRepository
{
    private const ITEM_TYPES = ['community', 'contest', 'group', 'project', 'skill_resource', 'workshop'];
    private const PUBLISH_STATES = ['archived', 'draft', 'published'];

    public function __construct(private readonly PDO $pdo) {}

    /** @param array<string,mixed> $item @param list<string> $affectedStudentIds */
    public function create(array $item, array $affectedStudentIds = []): void
    {
        $row = $this->validateCreate($item);
        $started = !$this->pdo->inTransaction();
        if ($started) $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO learner_ai_catalog_items '
                . '(catalog_id,item_type,category,title,summary,publish_status,deadline_at,eligibility_json,capacity,enrolled_count,url,action_json,school_id,tenant_id,updated_at) '
                . 'VALUES (:catalog_id,:item_type,:category,:title,:summary,:publish_status,:deadline_at,:eligibility_json,:capacity,:enrolled_count,:url,:action_json,:school_id,:tenant_id,:updated_at)'
            );
            $statement->execute($row);
            $audience = $this->audience((string) $row['catalog_id'], $affectedStudentIds);
            TransactionalAiOutboxPublisher::publish($this->pdo, 'learner_ai_catalog_item', (string) $row['catalog_id'], TransactionalAiOutboxPublisher::version(), $audience['student_ids'], 'catalog.created', ['catalog_id' => $row['catalog_id'], 'state' => $row['publish_status']], $audience['tenant_id']);
            if ($started) $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param list<string> $affectedStudentIds */
    public function archive(string $catalogId, array $affectedStudentIds = []): void { $this->mutate($catalogId, 'archived', $affectedStudentIds); }

    /** @param list<string> $affectedStudentIds */
    public function publish(string $catalogId, array $affectedStudentIds = []): void { $this->mutate($catalogId, 'published', $affectedStudentIds); }

    /** @param array{title?:string,summary?:string,category?:string,deadline_at?:string,eligibility_json?:string,capacity?:int,enrolled_count?:int,url?:string,action_json?:string} $changes @param list<string> $affectedStudentIds */
    public function update(string $catalogId, array $changes, array $affectedStudentIds = []): void
    {
        $catalogId = trim($catalogId);
        $allowed = ['title', 'summary', 'category', 'deadline_at', 'eligibility_json', 'capacity', 'enrolled_count', 'url', 'action_json'];
        $changes = array_intersect_key($changes, array_fill_keys($allowed, true));
        if ($catalogId === '' || $changes === []) throw new \InvalidArgumentException('Catalog update requires an id and allowlisted changes.');
        $this->validateChanges($changes);
        $started = !$this->pdo->inTransaction();
        if ($started) $this->pdo->beginTransaction();
        try {
            $sets = [];
            $params = ['catalogId' => $catalogId, 'updatedAt' => gmdate('Y-m-d H:i:s')];
            foreach ($changes as $field => $value) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            $sets[] = 'updated_at = :updatedAt';
            $statement = $this->pdo->prepare('UPDATE learner_ai_catalog_items SET ' . implode(', ', $sets) . ' WHERE catalog_id = :catalogId');
            $statement->execute($params);
            if ($statement->rowCount() !== 1) throw new \RuntimeException('Catalog item not found.');
            $audience = $this->audience($catalogId, $affectedStudentIds);
            TransactionalAiOutboxPublisher::publish($this->pdo, 'learner_ai_catalog_item', $catalogId, TransactionalAiOutboxPublisher::version(), $audience['student_ids'], 'catalog.updated', ['catalog_id' => $catalogId, 'fields' => array_keys($changes)], $audience['tenant_id']);
            if ($started) $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param list<string> $affectedStudentIds */
    private function mutate(string $catalogId, string $state, array $affectedStudentIds): void
    {
        $catalogId = trim($catalogId);
        if ($catalogId === '') throw new \InvalidArgumentException('Catalog id is required.');
        $started = !$this->pdo->inTransaction();
        if ($started) $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('UPDATE learner_ai_catalog_items SET publish_status = :state, updated_at = :updatedAt WHERE catalog_id = :catalogId');
            $statement->execute(['state' => $state, 'updatedAt' => gmdate('Y-m-d H:i:s'), 'catalogId' => $catalogId]);
            if ($statement->rowCount() !== 1) throw new \RuntimeException('Catalog item not found.');
            $audience = $this->audience($catalogId, $affectedStudentIds);
            TransactionalAiOutboxPublisher::publish($this->pdo, 'learner_ai_catalog_item', $catalogId, TransactionalAiOutboxPublisher::version(), $audience['student_ids'], 'catalog.' . $state, ['catalog_id' => $catalogId, 'state' => $state], $audience['tenant_id']);
            if ($started) $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param list<string> $requestedStudentIds @return array{student_ids:list<string>,tenant_id:?string} */
    private function audience(string $catalogId, array $requestedStudentIds): array
    {
        $catalog = $this->pdo->prepare('SELECT school_id, tenant_id FROM learner_ai_catalog_items WHERE catalog_id=:id');
        $catalog->execute(['id' => $catalogId]);
        $row = $catalog->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new \RuntimeException('Catalog item not found.');
        $schoolId = trim((string) ($row['school_id'] ?? ''));
        $tenantId = trim((string) ($row['tenant_id'] ?? ''));
        $studentColumns = $this->columns('student_profiles');
        $classColumns = $this->columns('classes');
        $sql = 'SELECT sp.id FROM student_profiles sp';
        $where = [];
        $params = [];
        if ($schoolId !== '') {
            if (!in_array('classId', $studentColumns, true) || !in_array('schoolId', $classColumns, true)) throw new \RuntimeException('School-scoped catalog audience cannot be resolved.');
            $sql .= ' INNER JOIN classes c ON c.id=sp.classId';
            $where[] = 'c.schoolId=:school';
            $params['school'] = $schoolId;
        }
        if ($tenantId !== '' && in_array('tenantId', $studentColumns, true)) {
            $where[] = 'sp.tenantId=:tenant';
            $params['tenant'] = $tenantId;
        } elseif ($tenantId !== '') {
            if ($schoolId === '' || !hash_equals($schoolId, $tenantId)) {
                throw new \RuntimeException('Tenant-scoped catalog audience cannot be resolved.');
            }
        }
        $requested = array_values(array_unique(array_map('trim', array_filter($requestedStudentIds, static fn (mixed $id): bool => is_string($id) && trim($id) !== ''))));
        if ($requested !== []) {
            $placeholders = [];
            foreach ($requested as $index => $studentId) {
                $key = 'student' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $studentId;
            }
            $where[] = 'sp.id IN (' . implode(',', $placeholders) . ')';
        }
        $query = $this->pdo->prepare($sql . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where)) . ' ORDER BY sp.id');
        $query->execute($params);
        $studentIds = array_values(array_filter($query->fetchAll(PDO::FETCH_COLUMN), 'is_string'));
        if ($studentIds === []) throw new \RuntimeException('Catalog audience cannot be resolved to learners.');
        return ['student_ids' => $studentIds, 'tenant_id' => $tenantId === '' ? null : $tenantId];
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    private function validateCreate(array $item): array
    {
        $required = ['catalog_id', 'item_type', 'category', 'title', 'summary', 'publish_status', 'eligibility_json', 'capacity', 'enrolled_count', 'url', 'action_json'];
        foreach ($required as $field) if (!array_key_exists($field, $item)) throw new \InvalidArgumentException("Catalog {$field} is required.");
        $catalogId = trim((string) $item['catalog_id']);
        $itemType = strtolower(trim((string) $item['item_type']));
        $publishStatus = strtolower(trim((string) $item['publish_status']));
        if ($catalogId === '' || !in_array($itemType, self::ITEM_TYPES, true) || !in_array($publishStatus, self::PUBLISH_STATES, true)) throw new \InvalidArgumentException('Catalog identity, type, or publish state is invalid.');
        $row = [
            'catalog_id' => $catalogId,
            'item_type' => $itemType,
            'category' => trim((string) $item['category']),
            'title' => trim((string) $item['title']),
            'summary' => trim((string) $item['summary']),
            'publish_status' => $publishStatus,
            'deadline_at' => isset($item['deadline_at']) && trim((string) $item['deadline_at']) !== '' ? trim((string) $item['deadline_at']) : null,
            'eligibility_json' => (string) $item['eligibility_json'],
            'capacity' => $item['capacity'],
            'enrolled_count' => $item['enrolled_count'],
            'url' => trim((string) $item['url']),
            'action_json' => (string) $item['action_json'],
            'school_id' => isset($item['school_id']) && trim((string) $item['school_id']) !== '' ? trim((string) $item['school_id']) : null,
            'tenant_id' => isset($item['tenant_id']) && trim((string) $item['tenant_id']) !== '' ? trim((string) $item['tenant_id']) : (isset($item['school_id']) && trim((string) $item['school_id']) !== '' ? trim((string) $item['school_id']) : null),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        $this->validateChanges($row);
        if ($row['category'] === '' || $row['title'] === '' || $row['summary'] === '' || $row['url'] === '') throw new \InvalidArgumentException('Catalog user-facing fields are required.');
        return $row;
    }

    /** @param array<string,mixed> $changes */
    private function validateChanges(array $changes): void
    {
        foreach (['eligibility_json', 'action_json'] as $field) {
            if (!array_key_exists($field, $changes)) continue;
            try {
                $decoded = json_decode((string) $changes[$field], true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new \InvalidArgumentException("Catalog {$field} must be valid JSON.", 0, $exception);
            }
            if (!is_array($decoded)) throw new \InvalidArgumentException("Catalog {$field} must contain a JSON object.");
        }
        if (isset($changes['capacity']) && (!is_int($changes['capacity']) || $changes['capacity'] < 1)) throw new \InvalidArgumentException('Catalog capacity must be a positive integer.');
        if (isset($changes['enrolled_count']) && (!is_int($changes['enrolled_count']) || $changes['enrolled_count'] < 0)) throw new \InvalidArgumentException('Catalog enrolled count must be a non-negative integer.');
        if (isset($changes['capacity'], $changes['enrolled_count']) && $changes['enrolled_count'] > $changes['capacity']) throw new \InvalidArgumentException('Catalog enrolled count cannot exceed capacity.');
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $rows = $this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
            return array_values(array_filter(array_column($rows, 'name'), 'is_string'));
        }
        $statement = $this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table ORDER BY ordinal_position');
        $statement->execute(['table' => $table]);
        return array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), 'is_string'));
    }
}
