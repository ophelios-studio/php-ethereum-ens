<?php namespace Ens;

final class ProfileHydrator
{
    /**
     * Default set of ENS text records to fetch when no list is provided at call-site. These include core EIP-634 keys
     * and popular community keys. When an array is provided, the first matching key is used.
     */
    public const array DEFAULT_RECORDS = [
        'avatar',
        'url',
        'email',
        'description',
        ['com.twitter', 'twitter'],
        ['com.github', 'github']
    ];

    private ?Resolver $resolver = null;

    public function __construct(
        private readonly Web3ClientInterface $client,
        private readonly EnsProfile $profile
    ) {}

    /**
     * Hydrate the given profile with the requested records for the provided ENS name.
     *
     * @param string $name Normalized ENS name
     * @param array $records Requested text record keys
     */
    public function hydrate(string $name, array $records = self::DEFAULT_RECORDS): void
    {
        $this->profile->name = $name;
        $this->initiateResolver($name);
        $this->hydrateAddress();
        $this->hydrateRecords($records);
    }

    private function hydrateRecords(array $records): void
    {
        foreach ($records as $entry) {
            if (is_string($entry)) {
                $this->hydrateRecord($entry);
            }
            if (is_array($entry)) {
                $this->hydrateArrayRecord($entry);
            }
        }
    }

    private function hydrateRecord(string $entry): void
    {
        $key = strtolower($entry);
        if ($key === 'avatar') {
            $this->hydrateAvatar();
            return;
        }
        $this->hydrateText($key);
    }

    private function hydrateArrayRecord(array $entry): void
    {
        $keys = array_values(array_filter(array_map(fn($k) => is_string($k) ? strtolower($k) : null, $entry)));
        if (count($keys) === 0) {
            return;
        }
        if ($this->resolver) {
            $val = $this->resolver->getFirstText($keys);
            if ($val !== null && $val !== '') {
                // Populate texts for each member key
                foreach ($keys as $k) {
                    $this->profile->texts[$k] = $val;
                }
                // Map to the first matching known property
                $map = $this->getPropertyMap();
                foreach ($keys as $k) {
                    if (isset($map[$k])) {
                        $prop = $map[$k];
                        $this->profile->$prop = $val;
                        break;
                    }
                }
            }
        }
    }

    private function hydrateAvatar(): void
    {
        if ($this->resolver) {
            $avatar = $this->resolver->getAvatar();
            if ($avatar !== null && $avatar !== '') {
                $this->profile->avatar = $avatar;
                $this->profile->texts['avatar'] = $avatar;
            }
        }
    }

    private function hydrateText(string $key): void
    {
        if ($this->resolver) {
            $val = $this->resolver->getText($key);
            if ($val !== null && $val !== '') {
                $this->profile->texts[$key] = $val;
                $map = $this->getPropertyMap();
                if (isset($map[$key])) {
                    $prop = $map[$key];
                    $this->profile->$prop = $val;
                }
            }
        }
    }

    private function initiateResolver(string $name): void
    {
        $this->resolver = new Resolver($name, $this->client);
    }

    private function hydrateAddress(): void
    {
        if ($this->resolver === null || !$this->resolver->exists()) {
            return;
        }
        $addr = $this->resolver->getAddress();
        if ($addr) {
            $this->profile->address = $addr;
        }
    }

    /**
     * Property map for known ENS text keys which are properties of EnsProfile.
     *
     * @return array<string,string>
     */
    private function getPropertyMap(): array
    {
        return [
            'avatar' => 'avatar',
            'url' => 'url',
            'email' => 'email',
            'description' => 'description',
            'com.twitter' => 'twitter',
            'twitter' => 'twitter',
            'com.github' => 'github',
            'github' => 'github',
            'com.discord' => 'discord',
            'com.reddit' => 'reddit',
            'org.telegram' => 'telegram',
            'com.linkedin' => 'linkedin',
        ];
    }
}
