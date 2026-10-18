<?php

declare(strict_types=1);

// Run through the documented disposable PHP container; never print generated secrets.
$path = dirname(__DIR__).'/.env';
if (! is_file($path)) {
    fwrite(STDERR, "Run cp .env.example .env first.\n");
    exit(1);
}
$content = file_get_contents($path);
$generators = [
    'APP_KEY' => fn () => 'base64:'.base64_encode(random_bytes(32)),
    'DB_PASSWORD' => fn () => bin2hex(random_bytes(24)),
    'DB_ROOT_PASSWORD' => fn () => bin2hex(random_bytes(24)),
    'REDIS_PASSWORD' => fn () => bin2hex(random_bytes(24)),
];
foreach ($generators as $name => $generate) {
    $content = preg_replace_callback('/^'.preg_quote($name, '/').'=[\t ]*$/m',
        fn () => $name.'='.$generate(), $content);
}
if (file_put_contents($path, $content, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write .env');
}
chmod($path, 0600);
echo "Empty secret values initialized in .env; existing values retained.\n";
