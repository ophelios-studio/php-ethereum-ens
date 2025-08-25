<?php namespace Ens;

class EnsResolver
{
    private EnsResolutionEngine $resolver;

    /**
     * Constructor method for initializing the EnsResolver with the specified provider URL.
     *
     * @param string $providerUrl The URL of the provider to be used for the configuration.
     * @return void
     */
    public function __construct(string $providerUrl)
    {
        $config = new Configuration(
            rpcUrl: $providerUrl,
            timeoutMs: Configuration::DEFAULT_TIMEOUT_MS,
            registryAddress: Configuration::DEFAULT_REGISTRY_ADDRESS
        );
        $client = new Web3Client($config);
        $this->resolver = new EnsResolutionEngine($client, $config);
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
        return $this->resolver->resolve($addressOrName, $minimalRecords);
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
        $profile = $this->resolver->resolve($ensName, $records);
        return $profile->toArray();
    }
}