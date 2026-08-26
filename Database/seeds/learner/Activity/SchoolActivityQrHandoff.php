<?php

declare(strict_types=1);

namespace TalentHub\Learner\Seeds\Activity;

use Closure;
use RuntimeException;
use Throwable;

final class SchoolActivityQrHandoff
{
    private readonly string $workspaceRoot;
    private readonly string $outputDirectory;
    private readonly Closure $renderer;
    private readonly Closure $renamer;

    /** @var array<string,array{token:string,tokenHash:string,pending:string,final:string}> */
    private array $preparedSessions = [];
    private ?string $manifestPending = null;
    private ?string $manifestFinal = null;
    private bool $prepared = false;
    private bool $finalized = false;

    public function __construct(
        string $workspaceRoot,
        string $requestedOutputDirectory,
        string $pythonBinary,
        ?callable $renderer = null,
        ?callable $renamer = null,
    ) {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('QR handoff output is CLI-only.');
        }
        $resolvedRoot = realpath($workspaceRoot);
        if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
            throw new RuntimeException('QR handoff workspace root is invalid.');
        }
        $this->workspaceRoot = rtrim($resolvedRoot, DIRECTORY_SEPARATOR);
        $this->outputDirectory = self::resolveOutputDirectory($this->workspaceRoot, $requestedOutputDirectory);
        if (file_exists($this->outputDirectory) || is_link($this->outputDirectory)) {
            throw new RuntimeException('QR handoff output directory must not already exist.');
        }

        $rendererScript = __DIR__ . '/render-activity-qr.py';
        $this->renderer = $renderer === null
            ? static function (string $token, string $pendingPath) use ($pythonBinary, $rendererScript): void {
                self::renderWithBundledPython($pythonBinary, $rendererScript, $token, $pendingPath);
            }
            : Closure::fromCallable($renderer);
        $this->renamer = $renamer === null
            ? static fn (string $from, string $to): bool => rename($from, $to)
            : Closure::fromCallable($renamer);
    }

    public static function resolveOutputDirectory(string $workspaceRoot, string $requested): string
    {
        if (str_contains($requested, "\0")) {
            throw new RuntimeException('QR handoff output path contains an invalid byte.');
        }
        $normalized = str_replace('\\', '/', trim($requested));
        if (preg_match('#\A\.codex_tmp/activity-qr-fixtures/[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z#D', $normalized) !== 1) {
            throw new RuntimeException('QR handoff output must be one new directory directly under .codex_tmp/activity-qr-fixtures/.');
        }

        $root = realpath($workspaceRoot);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('QR handoff workspace root is invalid.');
        }
        $segments = explode('/', $normalized);
        $candidate = rtrim($root, DIRECTORY_SEPARATOR);
        foreach ($segments as $segment) {
            $candidate .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($candidate)) {
                throw new RuntimeException('QR handoff output path may not traverse a symlink.');
            }
            if (file_exists($candidate)) {
                $actual = realpath($candidate);
                if ($actual === false || !self::samePath($actual, $candidate)) {
                    throw new RuntimeException('QR handoff output path may not traverse a redirected directory.');
                }
            }
        }
        $base = rtrim($root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . '.codex_tmp'
            . DIRECTORY_SEPARATOR . 'activity-qr-fixtures'
            . DIRECTORY_SEPARATOR;
        if (!str_starts_with(self::pathKey($candidate), self::pathKey($base))) {
            throw new RuntimeException('QR handoff output path escapes the approved fixture root.');
        }
        return $candidate;
    }

    /**
     * @param list<array{schoolId:string,schoolName:string,activityId:string,activityTitle:string,sessionId:string,expiresAt:string}> $entries
     * @return array<string,array{token:string,tokenHash:string}>
     */
    public function prepare(array $entries, callable $tokenFactory): array
    {
        if ($this->prepared || $this->finalized) {
            throw new RuntimeException('QR handoff has already been prepared.');
        }
        if (count($entries) !== 3) {
            throw new RuntimeException('QR handoff requires exactly three fixture entries.');
        }

        try {
            $this->createOutputDirectory();
            $manifest = [];
            foreach ($entries as $entry) {
                $this->assertEntry($entry);
                $token = $tokenFactory();
                if (!is_string($token) || $token === '' || strlen($token) > 512 || preg_match('/\s/', $token) === 1) {
                    throw new RuntimeException('QR token factory returned an invalid opaque token.');
                }
                $filename = 'activity-qr-' . $entry['sessionId'] . '.png';
                $finalPath = $this->outputDirectory . DIRECTORY_SEPARATOR . $filename;
                $pendingPath = $finalPath . '.pending';
                if (file_exists($finalPath) || file_exists($pendingPath)) {
                    throw new RuntimeException('QR handoff refuses to overwrite an existing fixture file.');
                }
                ($this->renderer)($token, $pendingPath);
                $this->assertPng($pendingPath);
                @chmod($pendingPath, 0600);
                $this->preparedSessions[$entry['sessionId']] = [
                    'token' => $token,
                    'tokenHash' => hash('sha256', $token),
                    'pending' => $pendingPath,
                    'final' => $finalPath,
                ];
                $manifest[] = [
                    'schoolId' => $entry['schoolId'],
                    'schoolName' => $entry['schoolName'],
                    'activityId' => $entry['activityId'],
                    'activityTitle' => $entry['activityTitle'],
                    'sessionId' => $entry['sessionId'],
                    'expiresAt' => $entry['expiresAt'],
                    'file' => $filename,
                ];
            }
            $this->manifestFinal = $this->outputDirectory . DIRECTORY_SEPARATOR . 'manifest.json';
            $this->manifestPending = $this->manifestFinal . '.pending';
            $encoded = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($this->manifestPending, $encoded . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('QR handoff manifest could not be written.');
            }
            @chmod($this->manifestPending, 0600);
            $this->prepared = true;
        } catch (Throwable $exception) {
            $this->rollback();
            throw new RuntimeException('QR handoff preparation failed before database changes.', 0, $exception);
        }

        $result = [];
        foreach ($this->preparedSessions as $sessionId => $prepared) {
            $result[$sessionId] = ['token' => $prepared['token'], 'tokenHash' => $prepared['tokenHash']];
        }
        return $result;
    }

    public function finalize(): void
    {
        if (!$this->prepared || $this->manifestPending === null || $this->manifestFinal === null) {
            throw new RuntimeException('QR handoff cannot finalize before preparation.');
        }
        foreach ($this->preparedSessions as $prepared) {
            $this->renamePending($prepared['pending'], $prepared['final']);
        }
        $this->renamePending($this->manifestPending, $this->manifestFinal);
        $this->finalized = true;
        $this->wipeTokens();
    }

    public function rollback(): void
    {
        foreach ($this->preparedSessions as $prepared) {
            if (is_file($prepared['pending'])) {
                @unlink($prepared['pending']);
            }
        }
        if ($this->manifestPending !== null && is_file($this->manifestPending)) {
            @unlink($this->manifestPending);
        }
        $this->wipeTokens();
        if (is_dir($this->outputDirectory)) {
            $remaining = scandir($this->outputDirectory);
            if ($remaining === ['.', '..']) {
                @rmdir($this->outputDirectory);
            }
        }
        $this->preparedSessions = [];
        $this->manifestPending = null;
        $this->manifestFinal = null;
        $this->prepared = false;
    }

    public function outputDirectory(): string
    {
        return $this->outputDirectory;
    }

    private function createOutputDirectory(): void
    {
        $base = $this->workspaceRoot . DIRECTORY_SEPARATOR . '.codex_tmp';
        $fixtureRoot = $base . DIRECTORY_SEPARATOR . 'activity-qr-fixtures';
        foreach ([$base, $fixtureRoot, $this->outputDirectory] as $directory) {
            if (is_link($directory)) {
                throw new RuntimeException('QR handoff output path may not traverse a symlink.');
            }
            if (!is_dir($directory) && !mkdir($directory, 0700, false)) {
                throw new RuntimeException('QR handoff output directory could not be created.');
            }
            $actual = realpath($directory);
            if ($actual === false || !self::samePath($actual, $directory)) {
                throw new RuntimeException('QR handoff output directory resolved outside its approved path.');
            }
        }
    }

    /** @param array<string,mixed> $entry */
    private function assertEntry(array $entry): void
    {
        $expectedKeys = ['schoolId', 'schoolName', 'activityId', 'activityTitle', 'sessionId', 'expiresAt'];
        if (array_keys($entry) !== $expectedKeys) {
            throw new RuntimeException('QR handoff entry fields are incompatible.');
        }
        foreach ($entry as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new RuntimeException('QR handoff entry contains an empty field.');
            }
        }
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $entry['sessionId']) !== 1) {
            throw new RuntimeException('QR handoff session ID is invalid.');
        }
    }

    private function assertPng(string $path): void
    {
        $size = is_file($path) ? filesize($path) : false;
        $header = is_file($path) ? file_get_contents($path, false, null, 0, 8) : false;
        if ($size === false || $size < 100 || $size > 350 * 1024 || $header !== "\x89PNG\r\n\x1a\n") {
            throw new RuntimeException('QR renderer did not produce a valid, reasonably sized PNG.');
        }
    }

    private function renamePending(string $pending, string $final): void
    {
        if (!is_file($pending) || file_exists($final) || !($this->renamer)($pending, $final)) {
            throw new RuntimeException('Database committed, but QR handoff finalization failed; pending files were retained for recovery and tokens were not rotated.');
        }
    }

    private function wipeTokens(): void
    {
        foreach ($this->preparedSessions as &$prepared) {
            if ($prepared['token'] !== '') {
                if (function_exists('sodium_memzero')) {
                    sodium_memzero($prepared['token']);
                } else {
                    $prepared['token'] = '';
                }
            }
        }
        unset($prepared);
    }

    private static function renderWithBundledPython(string $pythonBinary, string $script, string $token, string $output): void
    {
        if (!is_file($pythonBinary) || !is_file($script) || !function_exists('proc_open')) {
            throw new RuntimeException('Bundled QR renderer runtime is unavailable.');
        }
        $pipes = [];
        $process = proc_open(
            [$pythonBinary, $script, $output],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname($script),
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Bundled QR renderer could not start.');
        }
        fwrite($pipes[0], $token);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0 || $stdout !== '') {
            if (is_file($output)) {
                @unlink($output);
            }
            throw new RuntimeException('Bundled QR renderer failed without exposing token material.');
        }
    }

    private static function samePath(string $left, string $right): bool
    {
        return self::pathKey($left) === self::pathKey($right);
    }

    private static function pathKey(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rtrim($path, '/\\'));
        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }
}
