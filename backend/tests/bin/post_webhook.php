<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';

foreach (getenv() as $name => $value) {
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$app = require __DIR__.'/../../bootstrap/app.php';

$body = file_get_contents($argv[1]);
$request = Request::create('/api/webhooks/deals', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
    'HTTP_X_WEBHOOK_TOKEN' => (string) getenv('WEBHOOK_TOKEN'),
], $body);

$kernel = $app->make(Kernel::class);
try {
    $response = $kernel->handle($request);
    fwrite(STDOUT, $response->getStatusCode()."\n".$response->getContent());
    $kernel->terminate($request, $response);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString());
    exit(1);
}
