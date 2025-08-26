<?php

use Ens\Resolver;
use Ens\Utilities;
use Ens\Web3ClientInterface;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{
    private function makeClientForSimpleResolver(string $name, string $resolverAddr, ?string $ethAddr = null, array $texts = []): Web3ClientInterface
    {
        $client = $this->createMock(Web3ClientInterface::class);
        $queryNode = Utilities::namehash(Utilities::normalize($name));
        $resolverWord = '0x' . str_repeat('0', 24) . substr($resolverAddr, 2);

        $client->method('call')->willReturnCallback(function (array $tx) use ($queryNode, $resolverAddr, $resolverWord, $ethAddr, $texts) {
            $data = $tx['data'];
            // Registry getResolver
            if (str_starts_with($data, '0x0178b8bf')) {
                $node = '0x' . substr($data, 10);
                if ($node === $queryNode) {
                    return $resolverWord;
                }
                return '0x' . str_repeat('0', 64);
            }
            // Addr
            if (str_starts_with($data, '0x3b3b57de')) {
                return $ethAddr ? '0x' . str_repeat('0', 24) . substr(strtolower($ethAddr), 2) : null;
            }
            // Text
            if (str_starts_with($data, '0x59d1d43c')) {
                // Return the first text in the map regardless of key for simplicity
                if (!$texts) { return null; }
                $v = reset($texts);
                return self::encodeAbiString($v);
            }
            return null;
        });

        return $client;
    }

    public function testInitializeAndGetters(): void
    {
        $client = $this->makeClientForSimpleResolver('alice.eth', '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $resolver = new Resolver('Alice.ETH', $client);
        $this->assertTrue($resolver->exists());
        $this->assertSame('alice.eth', $resolver->getName());
        $this->assertSame('0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $resolver->getResolverAddress());
        $this->assertSame(Utilities::namehash('alice.eth'), $resolver->getQueryNode());
    }

    public function testGetAddressWithParentResolver(): void
    {
        $parent = 'alice.eth';
        $child = 'bob.' . $parent;
        $parentNode = Utilities::namehash($parent);
        $childNode = Utilities::namehash($child);
        $resolverAddr = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $ethAddr = '0x1234567890abcdef1234567890abcdef12345678';

        $client = $this->createMock(Web3ClientInterface::class);
        $client->method('call')->willReturnCallback(function (array $tx) use ($childNode, $parentNode, $resolverAddr, $ethAddr) {
            $data = $tx['data'] ?? '';
            // getResolver
            if (str_starts_with($data, '0x0178b8bf')) {
                $node = '0x' . substr($data, 10);
                if ($node === $childNode) { return '0x' . str_repeat('0', 64); }
                if ($node === $parentNode) { return '0x' . str_repeat('0', 24) . substr($resolverAddr, 2); }
            }
            // getAddr
            if (str_starts_with($data, '0x3b3b57de')) {
                $node = '0x' . substr($data, 10);
                if ($tx['to'] === $resolverAddr && $node === $parentNode) {
                    return '0x' . str_repeat('0', 24) . substr($ethAddr, 2);
                }
                return null;
            }
            return null;
        });

        $resolver = new Resolver($child, $client);
        $this->assertTrue($resolver->exists());
        $this->assertSame($ethAddr, $resolver->getAddress());
    }

    public function testGetTextWithFallback(): void
    {
        $parent = 'alice.eth';
        $child = 'carol.' . $parent;
        $parentNode = Utilities::namehash($parent);
        $childNode = Utilities::namehash($child);
        $resolverAddr = '0xcccccccccccccccccccccccccccccccccccccccc';

        $client = $this->createMock(Web3ClientInterface::class);
        $client->method('call')->willReturnCallback(function (array $tx) use ($childNode, $parentNode, $resolverAddr) {
            $data = $tx['data'] ?? '';
            if (str_starts_with($data, '0x0178b8bf')) {
                $node = '0x' . substr($data, 10);
                if ($node === $childNode) { return '0x' . str_repeat('0', 64); }
                if ($node === $parentNode) { return '0x' . str_repeat('0', 24) . substr($resolverAddr, 2); }
            }
            if (str_starts_with($data, '0x59d1d43c')) {
                // return parent value only
                $node = '0x' . substr($data, 10, 64);
                if ($node === $parentNode) {
                    return self::encodeAbiString('https://parent.example');
                }
                return null;
            }
            return null;
        });

        $resolver = new Resolver($child, $client);
        $this->assertSame('https://parent.example', $resolver->getText('url'));
    }

    public function testGetAvatarParentFallback(): void
    {
        $parent = 'd.eth';
        $child = 'e.' . $parent;
        $parentNode = Utilities::namehash($parent);
        $childNode = Utilities::namehash($child);
        $resolverAddr = '0xdddddddddddddddddddddddddddddddddddddddd';

        $client = $this->createMock(Web3ClientInterface::class);
        $client->method('call')->willReturnCallback(function (array $tx) use ($childNode, $parentNode, $resolverAddr) {
            $data = $tx['data'] ?? '';
            if (str_starts_with($data, '0x0178b8bf')) {
                $node = '0x' . substr($data, 10);
                if ($node === $childNode) { return '0x' . str_repeat('0', 64); }
                if ($node === $parentNode) { return '0x' . str_repeat('0', 24) . substr($resolverAddr, 2); }
            }
            if (str_starts_with($data, '0x59d1d43c')) {
                $node = '0x' . substr($data, 10, 64);
                // only parent has avatar
                if ($node === $parentNode) { return self::encodeAbiString('ipfs://parent-avatar'); }
                return null;
            }
            return null;
        });

        $resolver = new Resolver($child, $client);
        $this->assertSame('ipfs://parent-avatar', $resolver->getAvatar(true));
    }

    public function testGetRecordsNullWhenNoResolver(): void
    {
        $client = $this->createMock(Web3ClientInterface::class);
        $client->method('call')->willReturn('0x' . str_repeat('0', 64));
        $resolver = new Resolver('noresolver.eth', $client);
        $this->assertNull($resolver->getRecords(['url', 'email']));
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
