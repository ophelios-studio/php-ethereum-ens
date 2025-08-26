<?php namespace Ens;

readonly class Configuration
{
    public const int DEFAULT_TIMEOUT_MS = 10000;
    public const int MAX_RETRIES = 3;

    public function __construct(
        public string $rpcUrl,
        public int $timeoutMs = self::DEFAULT_TIMEOUT_MS,
        public int $maxRetries = self::MAX_RETRIES
    ) {}
}
