<?php
/** Run without Composer/vendor: php scripts/preflight.php */
$failed = false;
function check(string $label, bool $pass, string $hint = ''): void {
    global $failed;
    echo ($pass ? '[OK]   ' : '[FAIL] ').$label.($pass || !$hint ? '' : ' — '.$hint).PHP_EOL;
    if (!$pass) $failed = true;
}
check('PHP 8.2+', version_compare(PHP_VERSION, '8.2.0', '>='), 'Select a recent PHP installation in WAMP/XAMPP and add it to PATH.');
echo 'CLI executable: '.PHP_BINARY.PHP_EOL;
foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'ctype', 'curl', 'dom', 'xml', 'xmlwriter', 'tokenizer', 'session'] as $extension) {
    check('PHP extension: '.$extension, extension_loaded($extension), 'Enable it in the php.ini used by the CLI (php --ini).');
}
$root = dirname(__DIR__);
check('Laravel configuration exists', file_exists($root.'/backend/.env'), 'Copy backend/.env.example to backend/.env and configure MySQL.');
check('Composer dependencies installed', file_exists($root.'/backend/vendor/autoload.php'), 'Run composer install from backend/.');
foreach (['backend/storage', 'backend/bootstrap/cache'] as $directory) check($directory.' writable', is_writable($root.'/'.$directory));
echo PHP_EOL.'No credentials or database contents have been printed. This command does not create or modify databases.'.PHP_EOL;
exit($failed ? 1 : 0);
