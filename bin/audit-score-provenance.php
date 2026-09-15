<?php

declare(strict_types=1);

/**
 * TalentHub - Score Provenance & Legacy Data Audit Tool (P6)
 *
 * Reconciles legacy scores, verifies rubric/evidence provenance,
 * checks talentScore = skill-mean-1.0 consistency, and strips ungrounded AI scores.
 *
 * Usage:
 *   php bin/audit-score-provenance.php [--dry-run] [--apply] [--export-csv=<file>] [--help]
 */

require_once __DIR__ . '/bootstrap.php';

final class ScoreProvenanceAuditor
{
    private PDO $pdo;
    private bool $apply;
    private ?string $csvExportPath;
    /** @var list<array{type: string, table: string, record_id: string, student_masked: string, details: string, action: string}> */
    private array $findings = [];

    public function __construct(PDO $pdo, bool $apply = false, ?string $csvExportPath = null)
    {
        $this->pdo = $pdo;
        $this->apply = $apply;
        $this->csvExportPath = $csvExportPath;
    }

    public static function maskIdentifier(string $id): string
    {
        if (strlen($id) <= 6) {
            return 'id_' . substr(hash('sha256', $id), 0, 8);
        }
        return substr($id, 0, 3) . '***' . substr($id, -3);
    }

    private function hasTable(string $table): bool
    {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = ?");
                $stmt->execute([$table]);
                return $stmt->fetchColumn() !== false;
            }
            $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->query("PRAGMA table_info({$table})");
                if ($stmt) {
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                        if (strcasecmp((string) ($col['name'] ?? ''), $column) === 0) {
                            return true;
                        }
                    }
                }
                return false;
            }
            $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
            $stmt->execute([$table, $column]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $summary = [
            'mode' => $this->apply ? 'APPLY' : 'DRY-RUN',
            'total_skills' => 0,
            'scored_skills' => 0,
            'evidence_only_skills' => 0,
            'missing_source_skills' => 0,
            'unlinked_scores_found' => 0,
            'unlinked_scores_fixed' => 0,
            'revoked_evidence_found' => 0,
            'revoked_evidence_fixed' => 0,
            'talent_score_mismatches_found' => 0,
            'talent_score_mismatches_fixed' => 0,
            'deprecated_ai_consumers_found' => 0,
            'duplicate_skills_found' => 0,
        ];

        if (!$this->hasTable('student_skills')) {
            return $summary;
        }

        $hasScoreState = $this->hasColumn('student_skills', 'scoreState');
        $hasSourceEvalId = $this->hasColumn('student_skills', 'sourceEvaluationId');
        $hasSourceEvidId = $this->hasColumn('student_skills', 'sourceEvidenceId');

        // 1. Audit counts by state
        $totalStmt = $this->pdo->query('SELECT COUNT(*) FROM student_skills');
        $summary['total_skills'] = (int) ($totalStmt ? $totalStmt->fetchColumn() : 0);

        if ($hasScoreState) {
            $stateStmt = $this->pdo->query('SELECT scoreState, COUNT(*) as cnt FROM student_skills GROUP BY scoreState');
            if ($stateStmt) {
                foreach ($stateStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $st = (string) ($row['scoreState'] ?? '');
                    if ($st === 'scored') $summary['scored_skills'] = (int) $row['cnt'];
                    elseif ($st === 'evidence_only') $summary['evidence_only_skills'] = (int) $row['cnt'];
                    elseif ($st === 'missing_source') $summary['missing_source_skills'] = (int) $row['cnt'];
                }
            }
        }

        // 2. Audit unlinked scores (scored state but missing provenance links)
        if ($hasScoreState && $hasSourceEvalId && $hasSourceEvidId) {
            $unlinkedQuery = "
                SELECT ss.id, ss.studentId, ss.skillId, ss.levelScore, ss.scoreState, ss.sourceType
                FROM student_skills ss
                WHERE (ss.scoreState = 'scored' OR (ss.scoreState IS NULL AND ss.levelScore IS NOT NULL))
                  AND ss.sourceEvaluationId IS NULL
                  AND ss.sourceEvidenceId IS NULL
            ";
            $unlinkedStmt = $this->pdo->query($unlinkedQuery);
            $unlinkedRows = $unlinkedStmt ? $unlinkedStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $summary['unlinked_scores_found'] = count($unlinkedRows);

            foreach ($unlinkedRows as $row) {
                $id = (string) $row['id'];
                $maskedStudent = self::maskIdentifier((string) $row['studentId']);
                $this->findings[] = [
                    'type' => 'UNLINKED_SCORE',
                    'table' => 'student_skills',
                    'record_id' => $id,
                    'student_masked' => $maskedStudent,
                    'details' => "Score {$row['levelScore']} without sourceEvaluationId or sourceEvidenceId",
                    'action' => $this->apply ? 'RECONCILED_TO_MISSING_SOURCE' : 'NEEDS_RECONCILIATION',
                ];
            }

            if ($this->apply && count($unlinkedRows) > 0) {
                $this->pdo->beginTransaction();
                try {
                    $fixStmt = $this->pdo->prepare("
                        UPDATE student_skills
                        SET levelScore = NULL,
                            scoreState = 'missing_source',
                            verificationStatus = 'pending',
                            verifiedAt = NULL
                        WHERE id = ?
                    ");
                    foreach ($unlinkedRows as $row) {
                        $fixStmt->execute([$row['id']]);
                        $summary['unlinked_scores_fixed']++;
                    }
                    $this->pdo->commit();
                } catch (\Throwable $e) {
                    $this->pdo->rollBack();
                    throw $e;
                }
            }
        }

        // 3. Audit Revoked Evidence
        $evidenceTable = $this->hasTable('learner_skill_evidence') ? 'learner_skill_evidence' : ($this->hasTable('learner_evidences') ? 'learner_evidences' : null);
        if ($hasScoreState && $hasSourceEvidId && $evidenceTable !== null) {
            $hasRevokedAt = $this->hasColumn($evidenceTable, 'revokedAt');
            $hasStatus = $this->hasColumn($evidenceTable, 'status');
            $revokedCond = [];
            if ($hasRevokedAt) $revokedCond[] = 'lse.revokedAt IS NOT NULL';
            if ($hasStatus) $revokedCond[] = "lse.status = 'revoked'";

            if ($revokedCond !== []) {
                $revCondSql = implode(' OR ', $revokedCond);
                $revSql = "
                    SELECT ss.id, ss.studentId, ss.skillId, ss.levelScore, ss.scoreState
                    FROM student_skills ss
                    JOIN {$evidenceTable} lse ON lse.id = ss.sourceEvidenceId
                    WHERE ({$revCondSql})
                      AND (ss.scoreState != 'missing_source' OR ss.levelScore IS NOT NULL)
                ";
                $revStmt = $this->pdo->query($revSql);
                $revRows = $revStmt ? $revStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $summary['revoked_evidence_found'] = count($revRows);

                foreach ($revRows as $row) {
                    $this->findings[] = [
                        'type' => 'REVOKED_EVIDENCE_SCORE',
                        'table' => 'student_skills',
                        'record_id' => (string) $row['id'],
                        'student_masked' => self::maskIdentifier((string) $row['studentId']),
                        'details' => "Skill points to revoked evidence but retains score {$row['levelScore']}",
                        'action' => $this->apply ? 'RECONCILED_TO_MISSING_SOURCE' : 'NEEDS_RECONCILIATION',
                    ];
                }

                if ($this->apply && count($revRows) > 0) {
                    $this->pdo->beginTransaction();
                    try {
                        $fixRevStmt = $this->pdo->prepare("
                            UPDATE student_skills
                            SET levelScore = NULL,
                                scoreState = 'missing_source',
                                verificationStatus = 'pending',
                                verifiedAt = NULL
                            WHERE id = ?
                        ");
                        foreach ($revRows as $row) {
                            $fixRevStmt->execute([$row['id']]);
                            $summary['revoked_evidence_fixed']++;
                        }
                        $this->pdo->commit();
                    } catch (\Throwable $e) {
                        $this->pdo->rollBack();
                        throw $e;
                    }
                }
            }
        }

        // 4. Audit TalentScore integrity (sp.talentScore vs AVG(ss.levelScore) where scoreState = 'scored')
        if ($this->hasTable('student_profiles')) {
            $hasTalentScoreCol = $this->hasColumn('student_profiles', 'talentScore');
            if ($hasTalentScoreCol) {
                $scoredCond = $hasScoreState ? "ss.scoreState = 'scored'" : "ss.levelScore IS NOT NULL AND ss.levelScore > 0";
                $checkQuery = "
                    SELECT sp.id AS studentId, sp.talentScore,
                           (SELECT ROUND(AVG(ss.levelScore), 0)
                            FROM student_skills ss
                            WHERE ss.studentId = sp.id AND {$scoredCond}) AS expectedScore
                    FROM student_profiles sp
                ";
                $checkStmt = $this->pdo->query($checkQuery);
                $profiles = $checkStmt ? $checkStmt->fetchAll(PDO::FETCH_ASSOC) : [];

                $mismatchedProfiles = [];
                foreach ($profiles as $p) {
                    $current = $p['talentScore'] !== null ? (float) $p['talentScore'] : null;
                    $expected = $p['expectedScore'] !== null ? (float) $p['expectedScore'] : null;

                    $diff = false;
                    if ($current === null && $expected !== null) $diff = true;
                    elseif ($current !== null && $expected === null) $diff = true;
                    elseif ($current !== null && $expected !== null && abs($current - $expected) >= 1.0) $diff = true;

                    if ($diff) {
                        $mismatchedProfiles[] = [
                            'studentId' => $p['studentId'],
                            'current' => $current,
                            'expected' => $expected,
                        ];
                        $this->findings[] = [
                            'type' => 'TALENT_SCORE_MISMATCH',
                            'table' => 'student_profiles',
                            'record_id' => (string) $p['studentId'],
                            'student_masked' => self::maskIdentifier((string) $p['studentId']),
                            'details' => "Current talentScore: " . ($current !== null ? (string)$current : 'NULL') . ", Expected: " . ($expected !== null ? (string)$expected : 'NULL'),
                            'action' => $this->apply ? 'UPDATED_TO_EXPECTED' : 'NEEDS_UPDATE',
                        ];
                    }
                }
                $summary['talent_score_mismatches_found'] = count($mismatchedProfiles);

                if ($this->apply && count($mismatchedProfiles) > 0) {
                    $this->pdo->beginTransaction();
                    try {
                        $upStmt = $this->pdo->prepare('UPDATE student_profiles SET talentScore = ? WHERE id = ?');
                        foreach ($mismatchedProfiles as $mp) {
                            $upStmt->execute([$mp['expected'], $mp['studentId']]);
                            $summary['talent_score_mismatches_fixed']++;
                        }
                        $this->pdo->commit();
                    } catch (\Throwable $e) {
                        $this->pdo->rollBack();
                        throw $e;
                    }
                }
            }
        }

        // 5. Audit Deprecated AI consumers
        if ($this->hasTable('learner_roadmaps')) {
            try {
                $rmStmt = $this->pdo->query("SELECT COUNT(*) FROM learner_roadmaps WHERE goals_payload LIKE '%talent_map%' OR raw_response LIKE '%talent_map%'");
                $summary['deprecated_ai_consumers_found'] += (int) ($rmStmt ? $rmStmt->fetchColumn() : 0);
            } catch (\Throwable) {}
        }
        if ($this->hasTable('learner_ai_capability_profiles')) {
            try {
                $cpStmt = $this->pdo->query("SELECT COUNT(*) FROM learner_ai_capability_profiles WHERE capabilities_payload LIKE '%talent_map%'");
                $summary['deprecated_ai_consumers_found'] += (int) ($cpStmt ? $cpStmt->fetchColumn() : 0);
            } catch (\Throwable) {}
        }

        // 6. Audit duplicate skills for same student
        try {
            $dupStmt = $this->pdo->query("
                SELECT studentId, skillId, COUNT(*) as cnt
                FROM student_skills
                GROUP BY studentId, skillId
                HAVING cnt > 1
            ");
            $dups = $dupStmt ? $dupStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $summary['duplicate_skills_found'] = count($dups);
            foreach ($dups as $dup) {
                $this->findings[] = [
                    'type' => 'DUPLICATE_SKILL',
                    'table' => 'student_skills',
                    'record_id' => "skill_{$dup['skillId']}",
                    'student_masked' => self::maskIdentifier((string) $dup['studentId']),
                    'details' => "Found {$dup['cnt']} duplicate entries for skill {$dup['skillId']}",
                    'action' => 'MANUAL_REVIEW_REQUIRED',
                ];
            }
        } catch (\Throwable) {}

        // 7. Export CSV if requested
        if ($this->csvExportPath !== null && $this->csvExportPath !== '') {
            $this->exportCsv($this->csvExportPath);
        }

        return $summary;
    }

    public function exportCsv(string $filePath): void
    {
        $fp = fopen($filePath, 'wb');
        if ($fp === false) {
            throw new RuntimeException("Cannot open CSV export file: {$filePath}");
        }
        fputcsv($fp, ['issue_type', 'table', 'record_id', 'student_masked_id', 'details', 'action']);
        foreach ($this->findings as $row) {
            fputcsv($fp, [
                $row['type'],
                $row['table'],
                $row['record_id'],
                $row['student_masked'],
                $row['details'],
                $row['action'],
            ]);
        }
        fclose($fp);
    }

    /**
     * @return list<array{type: string, table: string, record_id: string, student_masked: string, details: string, action: string}>
     */
    public function getFindings(): array
    {
        return $this->findings;
    }
}

// CLI entrypoint
if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        $options = array_slice($argv, 1);
        $apply = false;
        $exportCsv = null;

        foreach ($options as $opt) {
            if ($opt === '--help' || $opt === '-h') {
                fwrite(STDOUT, "TalentHub Score Provenance Audit Tool\n");
                fwrite(STDOUT, "Usage: php bin/audit-score-provenance.php [--dry-run] [--apply] [--export-csv=<path>]\n");
                fwrite(STDOUT, "  --dry-run           Perform audit without writing changes (default)\n");
                fwrite(STDOUT, "  --apply             Apply repairs and reconcile data within transactions\n");
                fwrite(STDOUT, "  --export-csv=<path> Export audit findings to CSV file\n");
                exit(0);
            }
            if ($opt === '--apply') {
                $apply = true;
            } elseif ($opt === '--dry-run') {
                $apply = false;
            } elseif (str_starts_with($opt, '--export-csv=')) {
                $exportCsv = substr($opt, strlen('--export-csv='));
            } else {
                throw new RuntimeException("Unknown option: {$opt}. Use --help for usage.");
            }
        }

        $config = require dirname(__DIR__) . '/config/database.php';
        $pdo = (new \TalentHub\Database\Connection($config))->connect();

        $auditor = new ScoreProvenanceAuditor($pdo, $apply, $exportCsv);
        $summary = $auditor->run();

        fwrite(STDOUT, "========================================================\n");
        fwrite(STDOUT, " TALENTHUB SCORE PROVENANCE & LEGACY AUDIT (P6)\n");
        fwrite(STDOUT, " Mode: " . $summary['mode'] . "\n");
        fwrite(STDOUT, "========================================================\n");
        fwrite(STDOUT, sprintf(" Total skills in system:           %d\n", $summary['total_skills']));
        fwrite(STDOUT, sprintf(" - Scored skills:                  %d\n", $summary['scored_skills']));
        fwrite(STDOUT, sprintf(" - Evidence-only skills:           %d\n", $summary['evidence_only_skills']));
        fwrite(STDOUT, sprintf(" - Missing-source skills:          %d\n", $summary['missing_source_skills']));
        fwrite(STDOUT, "--------------------------------------------------------\n");
        fwrite(STDOUT, sprintf(" Unlinked scores found:            %d\n", $summary['unlinked_scores_found']));
        if ($apply) fwrite(STDOUT, sprintf(" Unlinked scores reconciled:       %d\n", $summary['unlinked_scores_fixed']));
        fwrite(STDOUT, sprintf(" Revoked evidence skills found:    %d\n", $summary['revoked_evidence_found']));
        if ($apply) fwrite(STDOUT, sprintf(" Revoked evidence skills fixed:    %d\n", $summary['revoked_evidence_fixed']));
        fwrite(STDOUT, sprintf(" TalentScore mismatches found:     %d\n", $summary['talent_score_mismatches_found']));
        if ($apply) fwrite(STDOUT, sprintf(" TalentScore mismatches fixed:     %d\n", $summary['talent_score_mismatches_fixed']));
        fwrite(STDOUT, sprintf(" Duplicate skills found:           %d\n", $summary['duplicate_skills_found']));
        fwrite(STDOUT, sprintf(" Deprecated AI consumers found:    %d\n", $summary['deprecated_ai_consumers_found']));
        fwrite(STDOUT, "========================================================\n");

        if ($exportCsv !== null) {
            fwrite(STDOUT, "[OK] Findings exported to CSV: {$exportCsv}\n");
        }

        if (!$apply && ($summary['unlinked_scores_found'] > 0 || $summary['talent_score_mismatches_found'] > 0)) {
            fwrite(STDOUT, "[INFO] Run with --apply to reconcile discrepancies.\n");
        } else {
            fwrite(STDOUT, "[OK] Audit completed successfully.\n");
        }

        exit(0);
    } catch (\Throwable $e) {
        fwrite(STDERR, "[FAIL] Audit failed: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
