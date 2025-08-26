<?php

use Ens\EnsService;
use PHPUnit\Framework\TestCase;

class LiveEnsTest extends TestCase
{
    public function testResolveByName()
    {
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $profile = $service->resolveProfile('vitalik.eth');
        $this->assertSame('vitalik.eth', $profile->name);
        $this->assertSame(strtolower('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045'), $profile->address);
        $this->assertSame('https://euc.li/vitalik.eth', $profile->avatar);
        $this->assertSame('https://vitalik.ca', $profile->url);
        $this->assertSame('vbuterin', $profile->github);
        $this->assertSame('VitalikButerin', $profile->twitter);
        $this->assertSame('mi pinxe lo crino tcati', $profile->description);
    }

    public function testResolveByAddress()
    {
        sleep(2);
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $name = $service->resolveEnsName('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');
        $this->assertSame('vitalik.eth', $name);
    }

    public function testResolvePrimarySubdomainByName()
    {
        sleep(2);
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $profile = $service->resolveProfile('ophelios.booe.eth');
        $this->assertSame('ophelios.booe.eth', $profile->name);
        $this->assertSame(strtolower('0xe4A2C90a656D0DEc8357953d97C5AcbA7C3f32d4'), $profile->address);
        $this->assertSame('https://euc.li/ophelios.booe.eth', $profile->avatar);
        $this->assertSame('https://www.ophelios.com', $profile->url);
        $this->assertSame('ophelios-studio', $profile->github);
        $this->assertSame('ophelios_studio', $profile->twitter);
    }

    public function testResolvePrimarySubdomainByAddress()
    {
        sleep(2);
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $name = $service->resolveEnsName('0xe4A2C90a656D0DEc8357953d97C5AcbA7C3f32d4');
        $this->assertSame('ophelios.booe.eth', $name);
    }

    public function testResolvePrimarySubdomainWithParentAvatarByName()
    {
        sleep(2);
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $profile = $service->resolveProfile('dadajuice.booe.eth');
        $this->assertSame('dadajuice.booe.eth', $profile->name);
        $this->assertSame(strtolower('0x846a07aa7577440174Fe89B82130D836389b1b81'), $profile->address);
        $this->assertSame('https://avatars.namespace.ninja/boe.png', $profile->avatar);;
    }

    public function testResolveUnassignedSubdomain()
    {
        sleep(2);
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $profile = $service->resolveProfile('trump.booe.eth');
        $this->assertSame('trump.booe.eth', $profile->name);
        $this->assertSame(null, $profile->address);
    }

    private function getProviderUrl(): ?string
    {
        $candidates = [];
        $g = getenv('ENS_PROVIDER_URL');
        if ($g !== false) {
            $candidates[] = $g;
        }
        if (isset($_ENV['ENS_PROVIDER_URL'])) {
            $candidates[] = $_ENV['ENS_PROVIDER_URL'];
        }
        if (isset($_SERVER['ENS_PROVIDER_URL'])) {
            $candidates[] = $_SERVER['ENS_PROVIDER_URL'];
        }
        foreach ($candidates as $val) {
            if (is_string($val)) {
                $val = trim($val);
                if ($val !== '') {
                    return $val;
                }
            }
        }
        return null;
    }
}
