<?php
require __DIR__ . '/../vendor/autoload.php';

// Load .env if present for integration tests
$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->safeLoad();
    } catch (Throwable $e) {
        // ignore
    }
}
