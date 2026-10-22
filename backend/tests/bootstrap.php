<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

// PHPUnit's <env force="true"> updates putenv, while Laravel reads $_ENV first.
// Docker already populated $_ENV, so without this copy the test process keeps
// the application token and the demo database.
foreach (getenv() as $name => $value) {
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

if (($_ENV['DB_URL'] ?? null) === '') {
    putenv('DB_URL');
    unset($_ENV['DB_URL'], $_SERVER['DB_URL']);
}

Illuminate\Support\Env::enablePutenv();
