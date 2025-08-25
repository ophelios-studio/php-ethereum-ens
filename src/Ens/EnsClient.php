<?php namespace Ens;

/**
 * EnsClient encapsulates ENS name-based resolution operations against the
 * Registry and Resolver contracts. It offers small, focused methods that can
 * be composed by higher-level services.
 */
final class EnsClient
{
    /**
     * Default Reverse Resolver (Mainnet) used as a fallback for reverse lookups when the Registry has no resolver set.
     * Source: ENS Default Reverse Resolver
     */
    private const string DEFAULT_REVERSE_RESOLVER = '0x084b1c3c81545d370f3634392de611caabff8148';

    public function __construct(
        private readonly Web3ClientInterface $client,
        private readonly ContractReader      $reader
    ) {}

    /**
     * Fetch the first non-empty text value among the provided keys for a given ENS name.
     * Tries keys in order using the exact node of the name (no parent fallback except what each key provides).
     *
     * @param string $ensName The ENS name to query.
     * @param array<string> $keys Candidate text keys in order of preference.
     * @return string|null The first non-empty value found, or null.
     */
    public function fetchFirstText(string $ensName, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!is_string($key) || $key === '') { continue; }
            $val = $this->fetchText($ensName, $key);
            if ($val !== null && $val !== '') {
                return $val;
            }
        }
        return null;
    }

    /**
     * Fetch the first non-empty text among provided keys by querying primary node first
     * then secondary node for each key.
     *
     * @param string $resolverAddress Resolver address owning the records.
     * @param string $primaryNode The node to try first.
     * @param string $secondaryNode The node to try as fallback.
     * @param array<string> $keys Candidate keys in preferred order.
     * @return string|null First found non-empty value or null.
     */
    public function fetchFirstTextByNodes(string $resolverAddress, string $primaryNode, string $secondaryNode, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!is_string($key) || $key === '') { continue; }
            $v = $this->reader->getText($resolverAddress, $primaryNode, $key);
            if ($v !== null && $v !== '') { return $v; }
            if ($secondaryNode !== $primaryNode) {
                $v = $this->reader->getText($resolverAddress, $secondaryNode, $key);
                if ($v !== null && $v !== '') { return $v; }
            }
        }
        return null;
    }

    /**
     * Return the resolver address configured for a given ENS name or null.
     */
    public function fetchResolverAddressForName(string $ensName): ?string
    {
        $node = Utilities::namehash(Utilities::normalize($ensName));
        return $this->reader->getResolver($node);
    }

    /**
     * Resolve the ETH address for a given ENS name via its resolver.
     * Returns a lowercase 0x address when available, otherwise null.
     */
    public function resolveAddressForName(string $ensName): ?string
    {
        $node = Utilities::namehash(Utilities::normalize($ensName));
        $resolver = $this->reader->getResolver($node);
        if (!$resolver) { return null; }
        return $this->reader->getAddr($resolver, $node);
    }

    /**
     * Fetch a single text record value for the exact node of the given ENS name.
     * This does not apply parent fallback; for avatar with fallback, use fetchAvatar().
     */
    public function fetchText(string $ensName, string $key): ?string
    {
        $node = Utilities::namehash(Utilities::normalize($ensName));
        $resolver = $this->reader->getResolver($node);
        if (!$resolver) { return null; }
        $v = $this->reader->getText($resolver, $node, $key);
        return ($v !== null && $v !== '') ? $v : null;
    }

    /**
     * Fetch avatar text record applying a single-level parent fallback.
     */
    public function fetchAvatar(string $ensName): ?string
    {
        $name = Utilities::normalize($ensName);
        $node = Utilities::namehash($name);
        $resolver = $this->reader->getResolver($node);
        if ($resolver) {
            $v = $this->reader->getText($resolver, $node, 'avatar');
            if ($v !== null && $v !== '') { return $v; }
        }
        // Parent fallback
        $dotPos = strpos($name, '.');
        if ($dotPos === false) { return null; }
        $parent = substr($name, $dotPos + 1);
        if (!$parent) { return null; }
        $pNode = Utilities::namehash($parent);
        $pResolver = $this->reader->getResolver($pNode);
        if (!$pResolver) { return null; }
        $pv = $this->reader->getText($pResolver, $pNode, 'avatar');
        return ($pv !== null && $pv !== '') ? $pv : null;
    }

    /**
     * Fetch a text record by resolver/node/key directly.
     */
    public function fetchTextByNode(string $resolverAddress, string $node, string $key): ?string
    {
        return $this->reader->getText($resolverAddress, $node, $key);
    }

    /**
     * Reverse resolve an Ethereum address to its primary ENS name.
     * Tries the resolver configured in the registry for <addr>.addr.reverse and falls back
     * to the default reverse resolver when necessary.
     */
    public function fetchName(string $address): ?string
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

    /**
     * Fetch an ETH address by resolver/node directly.
     */
    public function fetchAddrByNode(string $resolverAddress, string $node): ?string
    {
        return $this->reader->getAddr($resolverAddress, $node);
    }

    /**
     * Walk up the name hierarchy until a resolver is found. Returns [resolverAddress, nodeUsed].
     */
    public function findResolverNode(string $name): array
    {
        $current = Utilities::normalize($name);
        while (true) {
            $node = Utilities::namehash($current);
            $resolver = $this->reader->getResolver($node);
            if ($resolver) {
                return [$resolver, $node];
            }
            $dotPos = strpos($current, '.');
            if ($dotPos === false) {
                break;
            }
            $current = substr($current, $dotPos + 1);
            if (!$current) {
                break;
            }
            
        }
        return [null, ''];
    }
}
