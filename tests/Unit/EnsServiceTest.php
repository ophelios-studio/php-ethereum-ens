<?php

use Ens\EnsService;
use Ens\EnsProfile;
use Ens\Utilities;
use Ens\Web3ClientInterface;
use PHPUnit\Framework\TestCase;

class EnsServiceTest extends TestCase
{
    private function encodeAbiString(string $s): string
    {
        $len = strlen($s);
        $selectorless =
            str_pad('20', 64, '0', STR_PAD_LEFT) .
            str_pad(dechex($len), 64, '0', STR_PAD_LEFT) .
            str_pad(bin2hex($s), (int)ceil(strlen(bin2hex($s))/64)*64, '0', STR_PAD_RIGHT);
        return '0x' . $selectorless;
    }

    public function testResolveProfileAndRecords(): void
    {
        $name = 'alice.eth';
        $node = Utilities::namehash($name);
        $resolverAddr = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $ethAddr = '0x1234567890abcdef1234567890abcdef12345678';

        $client = $this->createMock(Web3ClientInterface::class);
        $client->method('call')->willReturnCallback(function (array $tx) use ($node, $resolverAddr, $ethAddr) {
            $data = $tx['data'] ?? '';
            if (str_starts_with($data, '0x0178b8bf')) {
                $nodeArg = '0x' . substr($data, 10);
                if ($nodeArg === $node) { return '0x' . str_repeat('0', 24) . substr($resolverAddr, 2); }
            }
            if (str_starts_with($data, '0x3b3b57de')) {
                return '0x' . str_repeat('0', 24) . substr($ethAddr, 2);
            }
            if (str_starts_with($data, '0x59d1d43c')) {
                return self::encodeAbiString('val');
            }
            return null;
        });

        $service = new EnsService($client);
        $profile = $service->resolveProfile($name, ['url']);
        $this->assertInstanceOf(EnsProfile::class, $profile);
        $this->assertSame($ethAddr, $profile->address);
        $this->assertSame('val', $profile->url);

        $this->assertSame('val', $service->resolveRecord($name, 'url'));
        $this->assertSame(['url' => 'val'], $service->resolveRecords($name, ['url']));
    }

    public function testResolveEnsNameDelegatesReverse(): void
    {
        $client = $this->createMock(Web3ClientInterface::class);
        $addr = '0x' . str_repeat('11', 20);
        $rev = substr($addr, 2) . '.addr.reverse';
        $node = Utilities::namehash($rev);
        $resolverAddr = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $client->method('call')->willReturnCallback(function (array $tx) use ($node, $resolverAddr) {
            $data = $tx['data'] ?? '';
            if (str_starts_with($data, '0x0178b8bf')) {
                $nodeArg = '0x' . substr($data, 10);
                if ($nodeArg === $node) { return '0x' . str_repeat('0', 24) . substr($resolverAddr, 2); }
            }
            if (str_starts_with($data, '0x691f3431')) {
                return self::encodeAbiString('alice.eth');
            }
            return null;
        });

        $service = new EnsService($client);
        $this->assertSame('alice.eth', $service->resolveEnsName($addr));
    }
}
