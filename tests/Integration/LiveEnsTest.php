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
        $profile = $service->getProfile('vitalik.eth');
        print_r($profile);
        $this->assertNotNull($profile, 'Profile should not be null');
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
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $profile = $service->getProfile('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');
        print_r($profile);
        $this->assertNotNull($profile, 'Profile should not be null');
        $this->assertSame('vitalik.eth', $profile->name);
        $this->assertSame(strtolower('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045'), $profile->address);
        $this->assertSame('https://euc.li/vitalik.eth', $profile->avatar);
        $this->assertSame('https://vitalik.ca', $profile->url);
        $this->assertSame('vbuterin', $profile->github);
        $this->assertSame('mi pinxe lo crino tcati', $profile->description);
        $this->assertSame('VitalikButerin', $profile->twitter);
    }

    public function testResolvePrimarySubdomainByName()
    {
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $profile = $service->getProfile('ophelios.booe.eth');
        print_r($profile);
        $this->assertNotNull($profile, 'Profile should not be null');
        $this->assertSame('ophelios.booe.eth', $profile->name);
        $this->assertSame(strtolower('0xe4A2C90a656D0DEc8357953d97C5AcbA7C3f32d4'), $profile->address);
        $this->assertSame('https://euc.li/ophelios.booe.eth', $profile->avatar);
        $this->assertSame('https://www.ophelios.com', $profile->url);
        $this->assertSame('ophelios-studio', $profile->github);
        $this->assertSame('ophelios_studio', $profile->twitter);
    }

    public function testResolvePrimarySubdomainByAddress()
    {
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $profile = $service->getProfile('0xe4A2C90a656D0DEc8357953d97C5AcbA7C3f32d4');
        print_r($profile);
        $this->assertNotNull($profile, 'Profile should not be null');
        $this->assertSame('ophelios.booe.eth', $profile->name);
        $this->assertSame(strtolower('0xe4A2C90a656D0DEc8357953d97C5AcbA7C3f32d4'), $profile->address);
        $this->assertSame('https://euc.li/ophelios.booe.eth', $profile->avatar);
        $this->assertSame('https://www.ophelios.com', $profile->url);
        $this->assertSame('ophelios-studio', $profile->github);
        $this->assertSame('ophelios_studio', $profile->twitter);
    }

//    public function testResolveNull()
//    {
//        $provider = $this->getProviderUrl();
//        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
//        $service = new EnsService($provider);
//        $profile = $service->getProfile('lksdfnldskjfsdjfjsdfklsdlfkjsdlfjsdjfsdljfsdjfsd.booe.eth');
//        $this->assertNull($profile);
//    }

    public function testResolvePrimarySubdomainWithParentAvatarByName()
    {
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $profile = $service->getProfile('dadajuice.booe.eth');
        print_r($profile);
        $this->assertNotNull($profile, 'Profile should not be null');
        $this->assertSame('dadajuice.booe.eth', $profile->name);
        $this->assertSame(strtolower('0x846a07aa7577440174Fe89B82130D836389b1b81'), $profile->address);
        $this->assertSame('https://avatars.namespace.ninja/boe.png', $profile->avatar);;
    }

    public function testResolvePrimarySubdomainWithParentAvatarByAddress()
    {
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $profile = $service->getProfile('0x846a07aa7577440174Fe89B82130D836389b1b81');
        print_r($profile);
        $this->assertNotNull($profile, 'Profile should not be null');
        $this->assertSame('dadajuice.booe.eth', $profile->name);
        $this->assertSame(strtolower('0x846a07aa7577440174Fe89B82130D836389b1b81'), $profile->address);
        $this->assertSame('https://avatars.namespace.ninja/boe.png', $profile->avatar);
    }

    public function testResolveUnassignedSubdomain()
    {
        $provider = $this->getProviderUrl();
        $this->assertNotEmpty($provider, 'ENS_PROVIDER_URL must be set');
        $service = new EnsService($provider);
        $profile = $service->getProfile('trump.booe.eth');
        print_r($profile);
        $this->assertSame('trump.booe.eth', $profile->name);
        $this->assertSame(null, $profile->address);
    }

    private function getProviderUrl(): ?string
    {
        $candidates = [];
        $g = getenv('ENS_PROVIDER_URL');
        if ($g !== false) { $candidates[] = $g; }
        if (isset($_ENV['ENS_PROVIDER_URL'])) { $candidates[] = $_ENV['ENS_PROVIDER_URL']; }
        if (isset($_SERVER['ENS_PROVIDER_URL'])) { $candidates[] = $_SERVER['ENS_PROVIDER_URL']; }
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
