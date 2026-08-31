<?php
declare(strict_types=1);

namespace TalentHub\Modules\School\Repository;

use PDO;

final class DatabaseSchoolAiInsightRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function save(string $schoolId, array $payload, string $modelVersion): void
    {
        $state = (string) ($payload['state'] ?? '');
        $origin = (string) ($payload['analysis_origin'] ?? '');
        if ($state !== 'ready_model' || $origin !== 'model') {
            return;
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $aggregate = $payload['aggregate'] ?? [];
        if (is_array($aggregate)) {
            unset($aggregate['generated_at']);
        }
        $hash = hash('sha256', json_encode($aggregate, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $id = self::uuid();
        $now = gmdate('Y-m-d H:i:s');
        $sqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $sql = $sqlite
            ? 'INSERT OR REPLACE INTO school_ai_insights (id, school_id, aggregate_hash, payload_json, model_version, generated_at) VALUES (?, ?, ?, ?, ?, ?)'
            : 'INSERT INTO school_ai_insights (id, school_id, aggregate_hash, payload_json, model_version, generated_at) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), model_version = VALUES(model_version), generated_at = VALUES(generated_at)';

        $this->pdo->prepare($sql)->execute([$id, $schoolId, $hash, $json, $modelVersion, $now]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function latest(string $schoolId): ?array
    {
        $s = $this->pdo->prepare('SELECT payload_json, model_version, generated_at FROM school_ai_insights WHERE school_id = ? ORDER BY generated_at DESC, id DESC LIMIT 1');
        $s->execute([$schoolId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)) {
            return null;
        }
        try {
            $payload = json_decode((string) $r['payload_json'], true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                return null;
            }
            $origin = (string) ($payload['analysis_origin'] ?? '');
            $state = (string) ($payload['state'] ?? '');
            if ($origin !== 'model' || !in_array($state, ['ready_model', 'stale_model'], true)) {
                return null;
            }
            return array_replace($payload, [
                'generated_at' => $r['generated_at'],
                'model_version' => $payload['model_version'] ?? $r['model_version'],
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function uuid(): string
    {
        $h = bin2hex(random_bytes(16));
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3) . '-8' . substr($h, 17, 3) . '-' . substr($h, 20, 12);
    }
}
