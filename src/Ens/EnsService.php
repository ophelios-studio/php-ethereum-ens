<?php namespace Ens;

class EnsService
{
    private EnsResolver $resolver;

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
            registryAddress: Configuration::DEFAULT_REGISTERY_ADDRESS
        );
        $client = new Web3Client($config);
        $this->resolver = new EnsResolver($client, $config);
    }

    /**
     * Retrieves the ENS profile associated with the given address or name.
     *
     * @param string $addressOrName The ENS address or name to resolve.
     * @return EnsProfile|null Returns the resolved ENS profile, or null if not found.
     */
    public function getProfile(string $addressOrName): ?EnsProfile
    {
        return $this->resolver->resolve($addressOrName);
    }

    /**
     * Retrieves data based on the provided address or name and optional records.
     *
     * @param string $addressOrName The address or name to resolve.
     * @param array|null $records Optional. An array of record types to consider during resolution.
     * @return array The resolved profile data as an array.
     */
    public function getData(string $addressOrName, ?array $records = null): array
    {
        $profile = $this->resolver->resolve($addressOrName, $records);
        return $profile->toArray();
    }
}