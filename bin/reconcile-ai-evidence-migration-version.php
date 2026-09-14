<?php
declare(strict_types=1);

/**
 * Repair only the pre-merge AI evidence migration version collision.
 * Default invocation is a dry-run; --apply moves its verified ledger entry.
 * No DDL is executed. Run the normal migration command after reconciliation
 * to apply the separate organization verification migration at the old version.
 * Including this file exposes the operation without connecting to a database.
 */
function reconcileAiEvidenceMigrationVersion(PDO $pdo, string $migrationFile, bool $apply = false): string
{
    $oldVersion = '20260914000100';
    $newVersion = '20260915000200';
    $name = 'widen_learner_ai_evidence_source_id';
    $adminName = 'allow_suspended_organization_verification';
    if (basename($migrationFile) !== $newVersion . '_' . $name . '.php' || !is_readable($migrationFile)) {
        throw new RuntimeException('Canonical evidence migration file is missing.');
    }
    $contents = file_get_contents($migrationFile);
    if (!is_string($contents)) {
        throw new RuntimeException('Cannot read canonical evidence migration file.');
    }
    $canonical = hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents));
    $raw = hash('sha256', $contents);
    $matches = static fn (string $checksum): bool => hash_equals($canonical, $checksum) || hash_equals($raw, $checksum);
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if (!in_array($driver, ['mysql', 'sqlite'], true) || $pdo->inTransaction()) {
        throw new RuntimeException('Reconciliation requires MySQL or SQLite and its own transaction.');
    }
    $locked = false;
    try {
        if ($driver === 'mysql') {
            // Use the same advisory lock as MigrationRunner so migration execution cannot race this repair.
            $lock = $pdo->prepare('SELECT GET_LOCK(?, 30)');
            $lock->execute(['talenthub:schema_migrations']);
            if ((int) $lock->fetchColumn() !== 1) {
                throw new RuntimeException('Unable to acquire migration lock.');
            }
            $locked = true;
            $engine = $pdo->query("SELECT engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'")->fetchColumn();
            if (strcasecmp((string) $engine, 'InnoDB') !== 0) {
                throw new RuntimeException('Migration ledger must use InnoDB for transactional reconciliation.');
            }
        }
        $pdo->beginTransaction();
        $query = $pdo->prepare('SELECT version, name, checksum FROM schema_migrations WHERE version IN (?, ?) ORDER BY version' . ($driver === 'mysql' ? ' FOR UPDATE' : ''));
        $query->execute([$oldVersion, $newVersion]);
        $records = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $records[(string) $row['version']] = $row;
        }
        $old = $records[$oldVersion] ?? null;
        $target = $records[$newVersion] ?? null;
        if ($old !== null && !in_array($old['name'], [$name, $adminName], true)) {
            throw new RuntimeException('Unexpected migration at the old version; refusing reconciliation.');
        }
        if ($target !== null) {
            if ($target['name'] !== $name || ($old !== null && $old['name'] === $name)) {
                throw new RuntimeException('Target version is occupied; refusing reconciliation.');
            }
            if (!$matches((string) $target['checksum'])) {
                throw new RuntimeException('Target evidence migration checksum mismatch.');
            }
        }
        if ($old === null || $old['name'] === $adminName) {
            if ($target === null) {
                $pdo->rollBack();
                return 'no collision: no evidence ledger entry needs moving';
            }
        } elseif (!$matches((string) $old['checksum'])) {
            throw new RuntimeException('Old evidence migration checksum mismatch.');
        }

        // A matching ledger alone is insufficient: the widening must already exist.
        foreach (['learner_recommendation_snapshot_evidence', 'learner_recommendation_evidence'] as $table) {
            $adequate = false;
            if ($driver === 'mysql') {
                $column = $pdo->prepare("SELECT data_type, character_maximum_length FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'sourceId'");
                $column->execute([$table]);
                $metadata = $column->fetch(PDO::FETCH_ASSOC);
                if (is_array($metadata)) {
                    $metadata = array_change_key_case($metadata, CASE_LOWER);
                }
                $adequate = $metadata !== false
                    && in_array(strtolower((string) $metadata['data_type']), ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'], true)
                    && (int) $metadata['character_maximum_length'] >= 128;
            } else {
                foreach ($pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) as $column) {
                    if ($column['name'] !== 'sourceId') {
                        continue;
                    }
                    $type = strtoupper(trim((string) $column['type']));
                    $adequate = $type === 'TEXT' || (preg_match('/\A(?:VAR)?CHAR\((\d+)\)\z/', $type, $length) === 1 && (int) $length[1] >= 128);
                }
            }
            if (!$adequate) {
                throw new RuntimeException('Evidence sourceId capacity is not verified for ' . $table . '.');
            }
        }
        if ($target !== null) {
            $pdo->rollBack();
            return 'already reconciled: evidence migration has its unique version';
        }
        if (!$apply) {
            $pdo->rollBack();
            return "dry-run: would move verified evidence migration {$oldVersion} -> {$newVersion}; no changes";
        }
        $update = $pdo->prepare('UPDATE schema_migrations SET version = ?, checksum = ? WHERE version = ? AND name = ? AND checksum = ?');
        $update->execute([$newVersion, $canonical, $oldVersion, $name, $old['checksum']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Evidence ledger changed during reconciliation; refusing update.');
        }
        $pdo->commit();
        return "moved verified evidence migration {$oldVersion} -> {$newVersion}; preserved batch, executionMs and appliedAt";
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($locked) {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute(['talenthub:schema_migrations']);
        }
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    require __DIR__ . '/bootstrap.php';
    try {
        $options = array_slice($argv, 1);
        if ($options === ['--help']) {
            fwrite(STDOUT, "Usage: php bin/reconcile-ai-evidence-migration-version.php [--dry-run|--apply]\nDefault: dry-run. Run normal migrations separately after applying this ledger repair.\n");
            exit(0);
        }
        if ($options !== [] && $options !== ['--dry-run'] && $options !== ['--apply']) {
            throw new RuntimeException('Usage: php bin/reconcile-ai-evidence-migration-version.php [--dry-run|--apply]');
        }
        $apply = $options === ['--apply'];
        if ($apply && \TalentHub\Config\Environment::appEnvironment() === 'production'
            && !\TalentHub\Config\Environment::boolean('ALLOW_PRODUCTION_MIGRATIONS')) {
            throw new RuntimeException('Production reconciliation requires ALLOW_PRODUCTION_MIGRATIONS=true.');
        }
        $config = require dirname(__DIR__) . '/config/database.php';
        $pdo = (new \TalentHub\Database\Connection($config))->connect();
        $result = reconcileAiEvidenceMigrationVersion($pdo, dirname(__DIR__) . '/Database/migrations/20260915000200_widen_learner_ai_evidence_source_id.php', $apply);
        fwrite(STDOUT, '[OK] ' . $result . PHP_EOL);
    } catch (\TalentHub\Database\Exception\DatabaseConnectionException | PDOException $e) {
        fwrite(STDERR, '[FAIL] Database operation failed; reconciliation was not confirmed.' . PHP_EOL);
        exit(1);
    } catch (RuntimeException $e) {
        fwrite(STDERR, '[FAIL] ' . $e->getMessage() . PHP_EOL);
        exit(2);
    } catch (Throwable $e) {
        fwrite(STDERR, '[FAIL] Reconciliation failed; no result confirmed.' . PHP_EOL);
        exit(2);
    }
}
