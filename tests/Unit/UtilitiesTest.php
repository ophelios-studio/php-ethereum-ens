<?php

use Ens\Utilities;
use PHPUnit\Framework\TestCase;

class UtilitiesTest extends TestCase
{
    public function testNormalizeBasic(): void
    {
        $this->assertSame('vitalik.eth', Utilities::normalize('  Vitalik.ETH  '));
        $this->assertSame('vitalik.eth', Utilities::normalize('vitalik.eth.'));
        $this->assertSame('', Utilities::normalize('   '));
    }

    public function testNamehashEmptyAndEth(): void
    {
        $this->assertSame('0x' . str_repeat('0', 64), Utilities::namehash(''));
        // Known EIP-137 namehash of "eth":
        $this->assertSame('0x93cdeb708b7545dc668eb9280176169d1c33cfd8ed6f04690a0bcc88a93fc4ae', Utilities::namehash('eth'));
    }

    public function testNamehashNested(): void
    {
        $this->assertSame(
            '0x6033644d673b47b3bea04e79bbe06d78ce76b8be2fb8704f9c2a80fd139c81d3',
            Utilities::namehash('foo.bar.eth')
        );
    }
}
