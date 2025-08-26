<?php

use Ens\ContractReader;
use Ens\Web3ClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ContractReaderTest extends TestCase
{
    /** @var Web3ClientInterface&MockObject */
    private $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Web3ClientInterface::class);
    }

    public function testGetResolverParsesAddress(): void
    {
        $reader = new ContractReader($this->client);
        $node = '0x' . str_repeat('ab', 32);
        $expectedAddr = '1234567890abcdef1234567890abcdef12345678';
        $resultHex = '0x' . str_repeat('0', 24) . $expectedAddr;

        $this->client->method('call')
            ->willReturnCallback(function (array $tx) use ($node, $resultHex) {
                $this->assertSame(Ens\ContractReader::DEFAULT_REGISTRY_ADDRESS, $tx['to']);
                $this->assertStringStartsWith('0x0178b8bf', $tx['data']);
                $this->assertSame(substr($node, 2), substr($tx['data'], 10));
                return $resultHex;
            });

        $addr = $reader->getResolver($node);
        $this->assertSame('0x' . $expectedAddr, $addr);
    }

    public function testGetResolverReturnsNullOnZero(): void
    {
        $reader = new ContractReader($this->client);
        $node = '0x' . str_repeat('00', 32);
        $this->client->method('call')->willReturn('0x' . str_repeat('0', 64));
        $this->assertNull($reader->getResolver($node));
    }

    public function testGetAddrParsesAddress(): void
    {
        $reader = new ContractReader($this->client);
        $node = '0x' . str_repeat('cd', 32);
        $expectedAddr = 'abcdefabcdefabcdefabcdefabcdefabcdefabcd';
        $resultHex = '0x' . str_repeat('0', 24) . $expectedAddr;

        $this->client->method('call')
            ->willReturnCallback(function (array $tx) use ($node, $resultHex) {
                $this->assertStringStartsWith('0x3b3b57de', $tx['data']);
                $this->assertSame(substr($node, 2), substr($tx['data'], 10));
                return $resultHex;
            });

        $addr = $reader->getAddr('0xres', $node);
        $this->assertSame('0x' . $expectedAddr, $addr);
    }

    public function testGetAddrNullOnEmptyOrZero(): void
    {
        $reader = new ContractReader($this->client);
        $node = '0x' . str_repeat('00', 32);
        $this->client->method('call')->willReturnOnConsecutiveCalls(null, '0x', '0x' . str_repeat('0', 62));
        $this->assertNull($reader->getAddr('0xres', $node));
        $this->assertNull($reader->getAddr('0xres', $node));
        $this->assertNull($reader->getAddr('0xres', $node));
    }

    public function testGetTextDecodesString(): void
    {
        $reader = new ContractReader($this->client);
        $resolver = '0xabc';
        $node = '0x' . str_repeat('11', 32);
        $key = 'url';
        $encoded = $this->encodeAbiString('https://example.com');

        $this->client->method('call')
            ->willReturnCallback(function (array $tx) use ($resolver, $encoded) {
                $this->assertSame($resolver, $tx['to']);
                $this->assertStringStartsWith('0x59d1d43c', $tx['data']);
                return $encoded;
            });

        $val = $reader->getText($resolver, $node, $key);
        $this->assertSame('https://example.com', $val);
    }

    public function testDecodeStringHandlesInvalid(): void
    {
        $reader = new ContractReader($this->client);
        $this->assertNull($reader->decodeString('0x'));
        $this->assertNull($reader->decodeString('0x' . '00'));
    }

    private function encodeAbiString(string $s): string
    {
        $len = strlen($s);
        $selectorless =
            str_pad('20', 64, '0', STR_PAD_LEFT) .
            str_pad(dechex($len), 64, '0', STR_PAD_LEFT) .
            str_pad(bin2hex($s), (int)ceil(strlen(bin2hex($s))/64)*64, '0', STR_PAD_RIGHT);
        return '0x' . $selectorless;
    }
}
