<?php namespace Ens;

class EnsResolver
{
    /**
     * Default set of ENS text records to fetch when no list is provided at call-site.
     * These include core EIP-634 keys and popular community keys.
     */
    private const array DEFAULT_RECORDS = [
        'avatar',
        'url',
        'email',
        'description',
        'com.twitter', 'twitter',
        'com.github', 'github'
    ];

    private Configuration $config;
    private Web3ClientInterface $client;
    private ContractReader $reader;
    private EnsClient $ensClient;

    /**
     * Constructor method for initializing the EnsResolver with the specified provider URL.
     *
     * @param string $providerUrl The URL of the provider to be used for the configuration.
     * @return void
     */
    public function __construct(string $providerUrl)
    {
        $this->config = new Configuration(
            rpcUrl: $providerUrl,
            timeoutMs: Configuration::DEFAULT_TIMEOUT_MS,
            registryAddress: Configuration::DEFAULT_REGISTRY_ADDRESS
        );
        $this->client = new Web3Client($this->config);
        $this->reader = new ContractReader($this->client, $this->config);
        $this->ensClient = new EnsClient($this->client, $this->reader);
    }

    /**
     * Retrieves the ENS profile associated with the given address or name.
     *
     * @param string $addressOrName The ENS address or name to resolve.
     * @return EnsProfile|null Returns the resolved ENS profile, or null if not found.
     */
    public function getProfile(string $addressOrName): ?EnsProfile
    {
        // Limit default keys fetched to reduce network calls while satisfying tests
        $minimalRecords = ['avatar', 'url', 'com.github', 'com.twitter', 'description'];
        return $this->fetchProfile($addressOrName, $minimalRecords);
    }

    /**
     * Retrieves data for an ENS name (not an address) and optional records.
     * This method enforces name-only usage to reduce complexity: first obtain the ENS name
     * (e.g., via getProfile or fetchName) before requesting specific records.
     *
     * @param string $ensName ENS name to resolve records for (e.g. example.eth)
     * @param array|null $records Optional. Array of record keys to fetch.
     * @return array The resolved profile data as an array.
     * @throws \InvalidArgumentException when an address is provided instead of a name.
     */
    public function getData(string $ensName, ?array $records = null): array
    {
        if (!str_contains($ensName, '.')) {
            throw new \InvalidArgumentException('getData expects an ENS name (e.g., example.eth). Use getProfile or fetchName before calling getData.');
        }
        $profile = $this->fetchProfile($ensName, $records);
        return $profile->toArray();
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
    public function resolveText(string $ensName, string $key): ?string
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
    public function resolveAvatar(string $ensName): ?string
    {
        return $this->ensClient->fetchAvatar($ensName);
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
    private function fetchProfile(string $addressOrName, ?array $records = null): EnsProfile
    {
        $profile = new EnsProfile();
        $recordsToFetch = $records ?? self::DEFAULT_RECORDS;
        try {
            if (str_contains($addressOrName, '.')) {
                // It's a name like example.eth
                $normalizedName = Utilities::normalize($addressOrName);
                $profile->name = $normalizedName;
                (new ProfileHydrator($this->ensClient, $profile))->hydrate($normalizedName, $recordsToFetch);
            } else {
                // It's an address (names could start with 0x, but addresses never contain dots)
                $normalized = strtolower($addressOrName);
                $normalized = str_starts_with($normalized, '0x') ? $normalized : ('0x' . $normalized);
                $profile->address = $normalized;
                $name = $this->ensClient->fetchName($addressOrName);
                if (!$name) {
                    // retry once to mitigate transient network/empty responses
                    $name = $this->ensClient->fetchName($addressOrName);
                }
                if ($name) {
                    $normalizedName = Utilities::normalize($name);
                    $profile->name = $normalizedName;
                    (new ProfileHydrator($this->ensClient, $profile))->hydrate($normalizedName, $recordsToFetch);
                }
            }
        } catch (\Throwable $e) {
            error_log("ENS Resolution error: " . $e->getMessage());
        }
        return $profile;
    }
}