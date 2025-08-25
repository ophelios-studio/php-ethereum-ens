<?php namespace Ens;

class EnsResolutionEngine implements EnsResolverInterface
{
    /**
     * Default set of ENS text records to fetch when no list is provided at call-site.
     * These include core EIP-634 keys and popular community keys.
     */
    public const array DEFAULT_RECORDS = [
        'avatar',
        'url',
        'email',
        'description',
        'com.twitter', 'twitter',
        'com.github', 'github'
    ];

    private ContractReader $reader;
    private EnsClient $ensClient;

    public function __construct(
        private readonly Web3ClientInterface $client,
        private readonly Configuration       $config
    ) {
        $this->reader = new ContractReader($client, $config);
        $this->ensClient = new EnsClient($client, $this->reader);
    }

    /**
     * Resolve an ENS profile from either an address or a name.
     * - If an ENS name is provided, fetch requested records for that name.
     * - If an address is provided, perform reverse lookup to discover a primary name, then fetch records.
     * Any provided $records list limits which text records are queried; if null, DEFAULT_RECORDS is used.
     *
     * @param string $addressOrName Ethereum address (0x...) or ENS name (e.g. example.eth).
     * @param array<string>|null $records ENS text record keys to fetch (e.g. ['avatar','com.twitter']).
     * @return EnsProfile A profile with name/address and requested properties populated when available.
     */
    public function fetchProfile(string $addressOrName, ?array $records = null): EnsProfile
    {
        $profile = new EnsProfile();
        $recordsToFetch = $records ?? self::DEFAULT_RECORDS;
        try {
            if (str_contains($addressOrName, '.')) {
                // It's a name like example.eth
                $normalizedName = Utilities::normalize($addressOrName);
                $profile->name = $normalizedName;
                new ProfileHydrator($this->ensClient, $profile)->hydrate($normalizedName, $recordsToFetch);
            } else {
                // It's an address (names could start with 0x, but addresses never contain dots)
                $normalized = strtolower($addressOrName);
                $normalized = str_starts_with($normalized, '0x') ? $normalized : ('0x' . $normalized);
                $profile->address = $normalized;
                $name = $this->ensClient->fetchName($addressOrName);
                if ($name) {
                    $normalizedName = Utilities::normalize($name);
                    $profile->name = $normalizedName;
                    new ProfileHydrator($this->ensClient, $profile)->hydrate($normalizedName, $recordsToFetch);
                }
            }
        } catch (\Throwable $e) {
            error_log("ENS Resolution error: " . $e->getMessage());
        }
        return $profile;
    }

    /**
     * Reverse resolve an Ethereum address to its primary ENS name.
     *
     * @param string $address Ethereum address (with or without 0x prefix).
     * @return string|null Normalized ENS name if available; otherwise null.
     */
    public function fetchName(string $address): ?string
    {
        return $this->ensClient->fetchName($address);
    }


}
