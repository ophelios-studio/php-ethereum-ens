<?php

use Ens\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $cfg = new Configuration('http://localhost:8545');
        $this->assertSame('http://localhost:8545', $cfg->rpcUrl);
        $this->assertSame(Configuration::DEFAULT_TIMEOUT_MS, $cfg->timeoutMs);
        $this->assertSame(Configuration::MAX_RETRIES, $cfg->maxRetries);
    }

    public function testCustomValues(): void
    {
        $cfg = new Configuration('https://rpc.example', 1234, 7);
        $this->assertSame('https://rpc.example', $cfg->rpcUrl);
        $this->assertSame(1234, $cfg->timeoutMs);
        $this->assertSame(7, $cfg->maxRetries);
    }
}
