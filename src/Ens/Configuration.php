<?php namespace Ens;

class Configuration
{
    public const DEFAULT_TIMEOUT_MS = 10000;
    public const DEFAULT_REGISTERY_ADDRESS = '0x00000000000C2E074eC69A0dFb2997BA6C7d2e1e'; // ENS Registry mainnet address

    public function __construct(
        public readonly string $rpcUrl,
        public readonly int $timeoutMs = self::DEFAULT_TIMEOUT_MS,
        public readonly string $registryAddress = self::DEFAULT_REGISTERY_ADDRESS
    ) {}
}
