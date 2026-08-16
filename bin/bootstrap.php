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
        $trim = trim($line);
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
        if (array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER) || getenv($name) !== false) {
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

/**
 * Convert an absolute app path (e.g. "/app/school/students.php") into a
 * relative URL from the current executing script's directory.
 *
 * Browser resolves relative URLs from the directory *containing* the current
 * script. The helper computes the shortest relative path from that directory
 * to the target, keeping navigation correct regardless of where the app
 * is mounted under the web server's DocumentRoot.
 *
 * Examples (app at /FTalentHUB/TalentHub/):
 *   login.php + /app/school/index.php  ->  app/school/index.php
 *   students.php + /app/school/teachers.php  ->  teachers.php
 *   app/teacher/students/index.php + /login.php  ->  ../../../login.php
 */
if (!function_exists('app_href')) {
    function app_href(string $absolutePath): string {
        $appRootFs = str_replace('\\', '/', dirname(__DIR__));
        $docRootFs = str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appRootUrl = ($docRootFs !== '' && str_starts_with($appRootFs, $docRootFs))
            ? substr($appRootFs, strlen($docRootFs))
            : '/';
        $appRootUrl = '/' . ltrim($appRootUrl, '/');

        // Browser resolves from the directory containing the current script.
        $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
        $targetPath = str_replace('\\', '/', parse_url($absolutePath, PHP_URL_PATH) ?: $absolutePath);

        // Strip app root from both.
        $scriptInsideApp = str_starts_with($scriptDir, $appRootUrl)
            && (strlen($scriptDir) === strlen($appRootUrl) || $scriptDir[strlen($appRootUrl)] === '/');
        $targetInsideApp = str_starts_with($targetPath, $appRootUrl)
            && (strlen($targetPath) === strlen($appRootUrl) || $targetPath[strlen($appRootUrl)] === '/');

        $scriptRel = $scriptInsideApp
            ? substr($scriptDir, strlen($appRootUrl) + 1)
            : substr($scriptDir, 1);
        $targetRel = $targetInsideApp
            ? substr($targetPath, strlen($appRootUrl) + 1)
            : substr($targetPath, 1);

        if ($targetRel === '') { $targetRel = '.'; }
        if ($scriptRel === '') { return $targetRel; }

        $scriptParts = explode('/', $scriptRel);
        $targetParts = explode('/', $targetRel);

        $i = 0;
        $max = min(count($scriptParts), count($targetParts));
        while ($i < $max && $scriptParts[$i] === $targetParts[$i]) { $i++; }

        $up = count($scriptParts) - $i;
        $down = array_slice($targetParts, $i);

        $relative = str_repeat('../', $up) . implode('/', $down);
        return $relative === '' ? './' : $relative;
    }
}
