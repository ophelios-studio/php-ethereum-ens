<?php

use Ens\EnsProfile;
use Ens\ProfileHydrator;
use Ens\Utilities;
use Ens\Web3ClientInterface;
use PHPUnit\Framework\TestCase;

class ProfileHydratorTest extends TestCase
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

    public function testHydrateFillsProfileFields(): void
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
                // Every getText returns different content based on a stable sequence
                static $i = 0; $i++;
                return match ($i) {
                    1 => self::encodeAbiString('https://avatar.example/alice.png'), // avatar
                    2 => self::encodeAbiString('https://alice.example'), // url
                    3 => self::encodeAbiString('alice@example.com'), // email
                    4 => self::encodeAbiString('just alice'), // description
                    5 => self::encodeAbiString('alice_twitter'), // com.twitter/twitter first match
                    6 => self::encodeAbiString('alice_github'), // com.github/github first match
                    default => null,
                };
            }
            return null;
        });

        $profile = new EnsProfile();
        $hydrator = new ProfileHydrator($client, $profile);
        $hydrator->hydrate($name);

        $this->assertSame('alice.eth', $profile->name);
        $this->assertSame($ethAddr, $profile->address);
        $this->assertSame('https://avatar.example/alice.png', $profile->avatar);
        $this->assertSame('https://alice.example', $profile->url);
        $this->assertSame('alice@example.com', $profile->email);
        $this->assertSame('just alice', $profile->description);
        $this->assertSame('alice_twitter', $profile->twitter);
        $this->assertSame('alice_github', $profile->github);
        $this->assertSame('https://avatar.example/alice.png', $profile->texts['avatar'] ?? null);
        $this->assertSame('https://alice.example', $profile->texts['url'] ?? null);
    }

    public function testHydrateArrayRecordMapsFirstAndAllKeys(): void
    {
        $name = 'bob.eth';
        $node = Utilities::namehash($name);
        $resolverAddr = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

        $client = $this->createMock(Web3ClientInterface::class);
        $client->method('call')->willReturnCallback(function (array $tx) use ($node, $resolverAddr) {
            $data = $tx['data'] ?? '';
            if (str_starts_with($data, '0x0178b8bf')) {
                $nodeArg = '0x' . substr($data, 10);
                if ($nodeArg === $node) { return '0x' . str_repeat('0', 24) . substr($resolverAddr, 2); }
            }
            if (str_starts_with($data, '0x59d1d43c')) {
                // only return one value for a set of keys
                return self::encodeAbiString('handle123');
            }
            return null;
        });

        $profile = new EnsProfile();
        $hydrator = new ProfileHydrator($client, $profile);
        $hydrator->hydrate($name, [ ['com.twitter','twitter','com.github','github'] ]);

        $this->assertSame('handle123', $profile->twitter);
        $this->assertNull($profile->github);
        $this->assertSame('handle123', $profile->texts['com.twitter']);
        $this->assertSame('handle123', $profile->texts['twitter']);
        $this->assertSame('handle123', $profile->texts['com.github']);
        $this->assertSame('handle123', $profile->texts['github']);
    }
}
