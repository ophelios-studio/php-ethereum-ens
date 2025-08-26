<?php

use Ens\EnsProfile;
use PHPUnit\Framework\TestCase;

class EnsProfileTest extends TestCase
{
    public function testToArrayMapping(): void
    {
        $p = new EnsProfile(
            name: 'alice.eth',
            address: '0xabc',
            avatar: 'ipfs://avatar',
            twitter: 'alice',
            github: 'alicegh',
            url: 'https://example.com',
            email: 'a@b.c',
            description: 'desc',
            discord: 'alice#1234',
            telegram: 'alice',
            reddit: 'u/alice',
            linkedin: 'alice-linkedin',
            texts: ['key' => 'val']
        );
        $arr = $p->toArray();
        $this->assertSame('alice.eth', $arr['name']);
        $this->assertSame('0xabc', $arr['address']);
        $this->assertSame('ipfs://avatar', $arr['avatar']);
        $this->assertSame('alice', $arr['twitter']);
        $this->assertSame('alicegh', $arr['github']);
        $this->assertSame('https://example.com', $arr['url']);
        $this->assertSame('a@b.c', $arr['email']);
        $this->assertSame('desc', $arr['description']);
        $this->assertSame('alice#1234', $arr['discord']);
        $this->assertSame('alice', $arr['telegram']);
        $this->assertSame('u/alice', $arr['reddit']);
        $this->assertSame('alice-linkedin', $arr['linkedin']);
        $this->assertSame(['key' => 'val'], $arr['texts']);
    }
}
