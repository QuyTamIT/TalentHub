<?php

declare(strict_types=1);

$script = dirname(__DIR__) . '/bin/rehearse-learner-ai-roadmap-schema.php';
$source = (string) file_get_contents($script);
function roadmap_mysql_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

roadmap_mysql_assert(str_contains($source, "['local', 'test']"), 'rehearsal refuses non-local environments');
roadmap_mysql_assert(str_contains($source, "'talenthub_codex_roadmap_'"), 'disposable database prefix is fixed');
roadmap_mysql_assert(str_contains($source, 'DROP DATABASE {$quoted}'), 'disposable database is removed in finally');
roadmap_mysql_assert(substr_count($source, "migrateApproved(['005_create_ai_roadmap_store'])") === 2, 'exact migration is applied twice');
roadmap_mysql_assert(!str_contains($source, "['database'] = 'talenthub'"), 'primary talenthub database is never selected');

$approved = (string) ($_ENV['TALENTHUB_MYSQL_REHEARSAL_APPROVED'] ?? getenv('TALENTHUB_MYSQL_REHEARSAL_APPROVED') ?: '');
if ($approved !== 'DISPOSABLE_SCHEMA_ONLY') {
    echo "learner_ai_roadmap_mysql_rehearsal_test: OK (execution skipped)\n";
    exit(0);
}
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
$process = proc_open($command, [1=>['pipe','w'],2=>['pipe','w']], $pipes);
roadmap_mysql_assert(is_resource($process), 'rehearsal process starts');
$stdout=(string)stream_get_contents($pipes[1]); fclose($pipes[1]);
$stderr=(string)stream_get_contents($pipes[2]); fclose($pipes[2]);
$exit=proc_close($process);
$result=json_decode(trim($stdout),true);
roadmap_mysql_assert($exit===0 && is_array($result), 'disposable MySQL rehearsal exits successfully: ' . trim($stderr));
roadmap_mysql_assert(($result['success']??false)===true && ($result['cleaned_up']??false)===true, 'rehearsal succeeds and cleans up');
roadmap_mysql_assert(($result['database_prefix']??null)==='talenthub_codex_roadmap_', 'only disposable prefix was used');
roadmap_mysql_assert(($result['first_apply']??null)===['005_create_ai_roadmap_store'] && ($result['second_apply']??null)===[], 'migration is repeatable');
roadmap_mysql_assert(($result['new_table_count']??null)===4 && ($result['sentinel_unchanged']??false)===true, 'four tables are created and old data is unchanged');
roadmap_mysql_assert(($result['parent_contract_unchanged']??false)===true && ($result['legacy_read_compatible']??false)===true, 'additive schema preserves the legacy parent contract and read path');
echo "learner_ai_roadmap_mysql_rehearsal_test: OK\n";
