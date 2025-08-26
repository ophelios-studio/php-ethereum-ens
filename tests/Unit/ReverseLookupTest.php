<?php

use Ens\ReverseLookup;
use Ens\Utilities;
use Ens\Web3ClientInterface;
use PHPUnit\Framework\TestCase;

class ReverseLookupTest extends TestCase
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

    public function testResolveEnsNameUsesRegistryThenDefault(): void
    {
        $client = $this->createMock(Web3ClientInterface::class);
        $addr = '0x1111111111111111111111111111111111111111';
        $rev = substr($addr, 2) . '.addr.reverse';
        $node = Utilities::namehash($rev);
        $resolver1 = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $default = strtolower(ReverseLookup::DEFAULT_REVERSE_RESOLVER);
        $encoded = $this->encodeAbiString('Alice.ETH');

        $client->method('call')->willReturnCallback(function (array $tx) use ($node, $resolver1, $encoded, $default) {
            $data = $tx['data'] ?? '';
            // getResolver from registry
            if (str_starts_with($data, '0x0178b8bf')) {
                $nodeArg = '0x' . substr($data, 10);
                if ($nodeArg === $node) { return '0x' . str_repeat('0', 24) . substr($resolver1, 2); }
            }
            // name(bytes32) on resolver
            if (str_starts_with($data, '0x691f3431')) {
                if (strtolower($tx['to']) === strtolower($resolver1)) {
                    return $encoded;
                }
                if (strtolower($tx['to']) === $default) {
                    return $encoded;
                }
            }
            return null;
        });

        $rl = new ReverseLookup($client);
        $this->assertSame('alice.eth', $rl->resolveEnsName($addr));
    }

    public function testResolveEnsNameReturnsNullIfNoResolversRespond(): void
    {
        $client = $this->createMock(Web3ClientInterface::class);
        $client->method('call')->willReturn(null);
        $rl = new ReverseLookup($client);
        $this->assertNull($rl->resolveEnsName('0x' . str_repeat('22', 20)));
    }
}
