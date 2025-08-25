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
    private ProfileHydrator $hydrator;

    public function __construct(
        private readonly Web3ClientInterface $client,
        private readonly Configuration       $config
    ) {
        $this->reader = new ContractReader($client, $config);
        $this->ensClient = new EnsClient($client, $this->reader);
        $this->hydrator = new ProfileHydrator($this->ensClient);
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
    public function resolve(string $addressOrName, ?array $records = null): EnsProfile
    {
        $profile = new EnsProfile();
        $recordsToFetch = $records ?? self::DEFAULT_RECORDS;
        try {
            if (str_contains($addressOrName, '.')) {
                // It's a name like example.eth
                $normalizedName = Utilities::normalize($addressOrName);
                $profile->name = $normalizedName;
                $this->hydrator->hydrate($normalizedName, $profile, $recordsToFetch);
            } else {
                // It's an address (names could start with 0x, but addresses never contain dots)
                $normalized = strtolower($addressOrName);
                $normalized = str_starts_with($normalized, '0x') ? $normalized : ('0x' . $normalized);
                $profile->address = $normalized;
                $name = $this->ensClient->fetchName($addressOrName);
                if ($name) {
                    $normalizedName = Utilities::normalize($name);
                    $profile->name = $normalizedName;
                    $this->hydrator->hydrate($normalizedName, $profile, $recordsToFetch);
                }
            }
            // Post-safeguards for resilience against transient misses
            if ($profile->name) {
                if ($profile->avatar === null) {
                    $av = $this->fetchAvatar($profile->name);
                    if ($av !== null && $av !== '') {
                        $profile->avatar = $av;
                        $profile->texts['avatar'] = $av;
                    }
                }
                if ($profile->address === null) {
                    $addr = $this->resolveAddressForName($profile->name);
                    if ($addr) { $profile->address = $addr; }
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

    /**
     * Return the resolver address for a given ENS name.
     *
     * @param string $ensName ENS name (e.g. example.eth)
     * @return string|null 0x-prefixed resolver address or null if none configured.
     */
    public function fetchResolverAddressForName(string $ensName): ?string
    {
        return $this->ensClient->fetchResolverAddressForName($ensName);
    }

    /**
     * Resolve the ETH address for a given ENS name via its resolver.
     * Returns a lowercase 0x address when available.
     *
     * @param string $ensName ENS name (e.g. example.eth)
     * @return string|null Lowercase 0x address or null.
     */
    public function resolveAddressForName(string $ensName): ?string
    {
        return $this->ensClient->resolveAddressForName($ensName);
    }

    /**
     * Convenience reverse lookup wrapper (no primary name verification).
     *
     * @param string $address Ethereum address.
     * @return string|null Normalized ENS name or null.
     */
    public function reverseLookupAddress(string $address): ?string
    {
        return $this->fetchName($address);
    }

    /**
     * Fetch a single text record value for the exact node of the given ENS name.
     * This does not apply parent fallback; for avatar fallback use fetchAvatar().
     *
     * @param string $ensName ENS name to query.
     * @param string $key Text record key (e.g. avatar, com.twitter, url).
     * @return string|null Non-empty string value or null.
     */
    public function fetchText(string $ensName, string $key): ?string
    {
        return $this->ensClient->fetchText($ensName, $key);
    }

    /**
     * Fetch avatar text record applying a single-level parent fallback.
     * If the name has no avatar set on its resolver, tries the parent domain.
     *
     * @param string $ensName ENS name to query.
     * @return string|null Avatar URI or null.
     */
    public function fetchAvatar(string $ensName): ?string
    {
        return $this->ensClient->fetchAvatar($ensName);
    }
}
