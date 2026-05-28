<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

function aditya_early_log_path(): string
{
    return __DIR__.'/../adityaclasses/storage/logs/early-error.log';
}

function aditya_write_early_log(Throwable $error, string $stage): void
{
    $message = sprintf(
        "[%s] %s\nStage: %s\nURL: %s %s\nError: %s\nFile: %s:%s\nTrace:\n%s\n\n",
        date('Y-m-d H:i:s'),
        PHP_SAPI,
        $stage,
        $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        $_SERVER['REQUEST_URI'] ?? '-',
        $error->getMessage(),
        $error->getFile(),
        $error->getLine(),
        $error->getTraceAsString()
    );

    $logPath = aditya_early_log_path();
    $logDir = dirname($logPath);

    if (! is_dir($logDir) || ! is_writable($logDir)) {
        error_log($message);
        return;
    }

    error_log($message, 3, $logPath);
}

register_shutdown_function(function (): void {
    $error = error_get_last();

    if (! $error || ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    aditya_write_early_log(
        new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']),
        'php-shutdown'
    );
});

try {
    if (file_exists($maintenance = __DIR__.'/../adityaclasses/storage/framework/maintenance.php')) {
        require $maintenance;
    }

    require __DIR__.'/../adityaclasses/vendor/autoload.php';

    $app = require_once __DIR__.'/../adityaclasses/bootstrap/app.php';

    $kernel = $app->make(Kernel::class);

    $response = $kernel->handle(
        $request = Request::capture()
    )->send();

    $kernel->terminate($request, $response);
} catch (Throwable $error) {
    aditya_write_early_log($error, 'front-controller');

    throw $error;
}
