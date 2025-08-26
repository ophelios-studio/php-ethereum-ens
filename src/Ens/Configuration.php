<?php namespace Ens;

readonly class Configuration
{
    public const int DEFAULT_TIMEOUT_MS = 10000;
    public const string DEFAULT_REGISTRY_ADDRESS = '0x00000000000C2E074eC69A0dFb2997BA6C7d2e1e'; // ENS Registry mainnet address
    public const int MAX_RETRIES = 3;

    public function __construct(
        public string $rpcUrl,
        public int $timeoutMs = self::DEFAULT_TIMEOUT_MS,
        public int $maxRetries = self::MAX_RETRIES,
        public string $registryAddress = self::DEFAULT_REGISTRY_ADDRESS
    ) {}
}
