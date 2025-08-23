<?php

use Ens\EnsService;
use PHPUnit\Framework\TestCase;

class LiveEnsTest extends TestCase
{
    public function testResolveOpheliosBooeEth()
    {
        $provider = $this->getProviderUrl();
        if (!$provider) {
            $this->markTestSkipped('ENS_PROVIDER_URL is not set; skipping live test.');
        }
        $service = new EnsService($provider);
        $profile = $service->getProfile('ophelios.booe.eth');
        $this->assertNotNull($profile, 'Profile should not be null');
        $this->assertSame('ophelios.booe.eth', $profile->name);
        $this->assertMatchesRegularExpression('/^0x[0-9a-fA-F]{40}$/', $profile->address);
        // Optional informational checks if present
        if (!empty($profile->url)) {
            $this->assertIsString($profile->url);
        }
        if (!empty($profile->avatar)) {
            $this->assertIsString($profile->avatar);
        }
        if (!empty($profile->github)) {
            $this->assertIsString($profile->github);
        }
        if (!empty($profile->twitter)) {
            $this->assertIsString($profile->twitter);
        }
    }

    public function testResolveByAddressHasSameValues()
    {
        $provider = $this->getProviderUrl();
        if (!$provider) {
            $this->markTestSkipped('ENS_PROVIDER_URL is not set; skipping live test.');
        }
        $service = new EnsService($provider);
        $addr = '0xe4A2C90a656D0DEc8357953d97C5AcbA7C3f32d4';
        $profile = $service->getProfile($addr);
        $this->assertNotNull($profile);
        $this->assertMatchesRegularExpression('/^0x[0-9a-fA-F]{40}$/', $profile->address);
        $this->assertSame(strtolower($addr), strtolower($profile->address));
        if (!empty($profile->name)) {
            // If a reverse name is set, resolving it should produce the same address
            $again = $service->getProfile($profile->name);
            $this->assertNotNull($again);
            $this->assertSame(strtolower($profile->address), strtolower($again->address));
        }
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
