<?php
declare(strict_types=1);

/**
 * Load .env file (if present) into $_ENV / $_SERVER / getenv().
 * Existing process-level env vars are NOT overwritten so that
 * sysadmin shell overrides (e.g. production secrets) win.
 *
 * Format: VALUE / KEY=VALUE / KEY="VALUE" / export KEY=VALUE / # comments.
 */
(static function (): void {
    $envPath = dirname(__DIR__) . '/.env';
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $trim = ltrim(trim($line), "\xEF\xBB\xBF");
        if ($trim === '' || str_starts_with($trim, '#')) {
            continue;
        }
        if (str_starts_with($trim, 'export ')) {
            $trim = trim(substr($trim, 7));
        }
        $eq = strpos($trim, '=');
        if ($eq === false) {
            continue;
        }
        $name = trim(substr($trim, 0, $eq));
        if ($name === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $name)) {
            continue;
        }
        $value = trim(substr($trim, $eq + 1));
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        // A web server may export an empty placeholder (for example APP_ENV="").
        // Treat that as unset so the project .env can still provide the value;
        // non-empty process-level overrides continue to win.
        $existing = array_key_exists($name, $_ENV) ? $_ENV[$name]
            : (array_key_exists($name, $_SERVER) ? $_SERVER[$name] : getenv($name));
        if ($existing !== false && $existing !== null && (string) $existing !== '') {
            continue;
        }
        $_ENV[$name]    = $value;
        $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
})();

spl_autoload_register(static function(string $class): void {
    $prefix='TalentHub\\'; if(!str_starts_with($class,$prefix)){return;}
    $relative=str_replace('\\','/',substr($class,strlen($prefix)));
    $roots=['Tests\\'=>dirname(__DIR__).'/tests/'];
    if(str_starts_with(substr($class,strlen($prefix)),'Tests\\')){$relative=str_replace('\\','/',substr($class,strlen('TalentHub\\Tests\\')));$path=dirname(__DIR__).'/tests/'.$relative.'.php';}
    else{$path=dirname(__DIR__).'/src/'.$relative.'.php';}
    if(is_file($path)){require $path;}
});

if (PHP_SAPI !== 'cli') {
    \TalentHub\Http\UnhandledExceptionHandler::register();
}

/**
 * Convert an app-relative path (e.g. "/app/enterprise/index.php" or "login.php") into
 * a robust, base-prefixed URL path (e.g. "/TalentHub/app/enterprise/index.php" when mounted
 * under a subdirectory, or "/app/enterprise/index.php" when mounted at web root).
 *
 * Preserves query parameters, works consistently from any nested route,
 * and avoids fragile relative "../" traversing.
 */
if (!function_exists('app_href')) {
    function app_href(string $absolutePath): string {
        $appRootFs  = str_replace('\\', '/', dirname(__DIR__));
        $docRootFs  = str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

        $basePrefix = '';
        if ($docRootFs !== '' && stripos($appRootFs, $docRootFs) === 0) {
            $sub = substr($appRootFs, strlen($docRootFs));
            $trimmed = trim(str_replace('\\', '/', $sub), '/');
            $basePrefix = $trimmed !== '' ? ('/' . $trimmed) : '';
        } elseif ($scriptName !== '' && stripos($scriptName, '/TalentHub') !== false) {
            $basePrefix = '/TalentHub';
        } elseif (isset($_SERVER['REQUEST_URI']) && stripos((string)$_SERVER['REQUEST_URI'], '/TalentHub') !== false) {
            $basePrefix = '/TalentHub';
        }

        $path = '/' . ltrim($absolutePath, '/');
        if ($basePrefix !== '' && (str_starts_with($path, $basePrefix . '/') || $path === $basePrefix)) {
            return $path;
        }

        return $basePrefix . $path;
    }
}

if (!function_exists('resolve_logo_url')) {
    function resolve_logo_url(?string $url): ?string {
        if ($url === null || trim($url) === '') {
            return null;
        }
        $url = trim($url);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, 'data:')) {
            return $url;
        }
        return app_href($url);
    }
}
