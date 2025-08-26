<?php

use Ens\Configuration;
use Ens\Web3Client;
use Ens\Web3ClientInterface;
use PHPUnit\Framework\TestCase;

class Web3ClientTest extends TestCase
{
    public function testImplementsInterfaceAndConstructs(): void
    {
        $client = new Web3Client(new Configuration('http://localhost:8545'));
        $this->assertInstanceOf(Web3ClientInterface::class, $client);
        $this->assertTrue(method_exists($client, 'call'));
    }
}
