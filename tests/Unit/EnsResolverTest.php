<?php

use Ens\Configuration;
use Ens\EnsClientInterface;
use Ens\EnsResolutionEngine;
use Ens\EnsProfile;
use kornrunner\Keccak;
use PHPUnit\Framework\TestCase;

class EnsResolverTest extends TestCase
{
    private function namehash(string $name): string
    {
        $node = str_repeat('0', 64);
        if ($name) {
            $labels = array_reverse(explode('.', strtolower(rtrim($name, '.'))));
            foreach ($labels as $label) {
                $node = Keccak::hash(hex2bin($node) . hex2bin(Keccak::hash($label, 256)), 256);
            }
        }
        return '0x' . $node;
    }

    public function abiEncodeString(string $s): string
    {
        $offset = str_pad(dechex(32), 64, '0', STR_PAD_LEFT);
        $length = str_pad(dechex(strlen($s)), 64, '0', STR_PAD_LEFT);
        $data = bin2hex($s);
        $paddedData = str_pad($data, (int)ceil(strlen($data) / 64) * 64, '0', STR_PAD_RIGHT);
        return '0x' . $offset . $length . $paddedData;
    }

    public function testResolveByNameFetchesAddressAndText()
    {
        $config = new Configuration(rpcUrl: 'http://localhost');
        $registry = $config->registryAddress;
        $name = 'example.eth';
        $node = $this->namehash($name);
        $resolverAddress = '0x1111111111111111111111111111111111111111';
        $accountAddress = '0x2222222222222222222222222222222222222222';

        $client = new class($registry, $node, $resolverAddress, $accountAddress, $this) implements EnsClientInterface {
            public function __construct(private $registry, private $node, private $resolver, private $addr, private $test) {}
            public function call(array $tx): ?string
            {
                $to = strtolower($tx['to'] ?? '');
                $data = strtolower($tx['data'] ?? '');
                // registry.resolver(bytes32)
                if ($to === strtolower($this->registry) && strpos($data, '0x0178b8bf') === 0 && substr($data, -64) === substr(strtolower($this->node), 2)) {
                    // Return 32-byte word with resolver in the last 20 bytes
                    return '0x' . str_pad('', 24, '0') . substr(strtolower($this->resolver), 2);
                }
                // resolver.addr(bytes32)
                if ($to === strtolower($this->resolver) && strpos($data, '0x3b3b57de') === 0 && substr($data, -64) === substr(strtolower($this->node), 2)) {
                    return '0x' . str_pad('', 24, '0') . substr(strtolower($this->addr), 2);
                }
                // resolver.text(bytes32,string) selector 0x59d1d43c
                if ($to === strtolower($this->resolver) && strpos($data, '0x59d1d43c') === 0) {
                    // Return twitter = "ens_user"
                    return $this->test->abiEncodeString('ens_user');
                }
                return null;
            }
        };

        $resolver = new EnsResolutionEngine($client, $config);
        $profile = $resolver->resolve($name, ['twitter']);

        $this->assertInstanceOf(EnsProfile::class, $profile);
        $this->assertSame('example.eth', $profile->name);
        $this->assertSame(strtolower($accountAddress), $profile->address);
        $this->assertSame('ens_user', $profile->twitter);
        $this->assertArrayHasKey('twitter', $profile->texts);
    }

    public function testResolveByAddressWithReverseLookup()
    {
        $config = new Configuration(rpcUrl: 'http://localhost');
        $registry = $config->registryAddress;
        $addr = '0x1234567890abcdef1234567890abcdef12345678';
        $reverseNode = $this->namehash(strtolower(substr($addr, 2)) . '.addr.reverse');
        $reverseResolver = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $name = 'example.eth';
        $nameNode = $this->namehash($name);
        $resolverForName = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $resolvedAccount = '0x2222222222222222222222222222222222222222';

        $client = new class($registry, $reverseNode, $reverseResolver, $name, $nameNode, $resolverForName, $resolvedAccount, $this) implements EnsClientInterface {
            public function __construct(private $registry, private $reverseNode, private $reverseResolver, private $name, private $nameNode, private $nameResolver, private $resolvedAccount, private $test) {}
            public function call(array $tx): ?string
            {
                $to = strtolower($tx['to'] ?? '');
                $data = strtolower($tx['data'] ?? '');
                // registry.resolver(bytes32) for reverse node -> return reverse resolver
                if ($to === strtolower($this->registry) && strpos($data, '0x0178b8bf') === 0 && substr($data, -64) === substr(strtolower($this->reverseNode), 2)) {
                    return '0x' . str_pad('', 24, '0') . substr(strtolower($this->reverseResolver), 2);
                }
                // reverseResolver.name(bytes32)
                if ($to === strtolower($this->reverseResolver) && strpos($data, '0x691f3431') === 0) {
                    return $this->test->abiEncodeString($this->name);
                }
                // registry.resolver(bytes32) for name node -> return name resolver
                if ($to === strtolower($this->registry) && strpos($data, '0x0178b8bf') === 0 && substr($data, -64) === substr(strtolower($this->nameNode), 2)) {
                    return '0x' . str_pad('', 24, '0') . substr(strtolower($this->nameResolver), 2);
                }
                // nameResolver.addr(bytes32)
                if ($to === strtolower($this->nameResolver) && strpos($data, '0x3b3b57de') === 0) {
                    return '0x' . str_pad('', 24, '0') . substr(strtolower($this->resolvedAccount), 2);
                }
                return null;
            }
        };

        $resolver = new EnsResolutionEngine($client, $config);
        $profile = $resolver->resolve($addr, []);

        $this->assertSame('example.eth', $profile->name);
        $this->assertSame(strtolower($resolvedAccount), $profile->address);
    }
}
