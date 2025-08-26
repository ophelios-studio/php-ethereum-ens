<?php namespace Ens;

final class ReverseLookup
{
    /**
     * Default Reverse Resolver (Mainnet) used as a fallback for reverse lookups when the Registry has no resolver set.
     */
    public const string DEFAULT_REVERSE_RESOLVER = '0x084b1c3c81545d370f3634392de611caabff8148';

    private ContractReader $reader;

    public function __construct(
        private readonly Web3ClientInterface $client
    ) {
        $this->reader = new ContractReader($client);
    }

    /**
     * Reverse resolve an Ethereum address to its primary ENS name. Tries the resolver configured in the registry
     * for <addr>.addr.reverse and falls back to the default reverse resolver when necessary.
     *
     * @param string $address The address for which the name resolution is to be performed.
     * @return string|null Returns the resolved name as a string if found; otherwise, returns null.
     */
    public function resolveEnsName(string $address): ?string
    {
        $address = strtolower(trim($address));
        $cleanAddress = str_starts_with($address, '0x') ? substr($address, 2) : $address;
        $reverseName = $cleanAddress . '.addr.reverse';
        $nameHash = Utilities::namehash($reverseName);
        $resolverAddress = $this->reader->getResolver($nameHash);
        $data = '0x691f3431' . substr($nameHash, 2); // name(bytes32)

        $tryResolvers = [];
        if ($resolverAddress) {
            $tryResolvers[] = strtolower($resolverAddress);
        }
        $defaultLower = strtolower(self::DEFAULT_REVERSE_RESOLVER);
        if (!$resolverAddress || strtolower($resolverAddress) !== $defaultLower) {
            $tryResolvers[] = $defaultLower;
        }
        $tryResolvers = array_values(array_unique($tryResolvers));

        for ($pass = 0; $pass < 2; $pass++) {
            foreach ($tryResolvers as $resolver) {
                if (!$resolver) { continue; }
                $result = $this->client->call([
                    'to' => $resolver,
                    'data' => $data,
                    'from' => '0x0000000000000000000000000000000000000000'
                ]);
                if (!$result) { continue; }
                $decoded = $this->reader->decodeString($result);
                if ($decoded !== null && $decoded !== '') {
                    return Utilities::normalize($decoded);
                }
            }
        }
        return null;
    }
}
