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
        // Strip surrounding single or double quotes.
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        // Skip when an env var is already set (real environment trumps .env).
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
