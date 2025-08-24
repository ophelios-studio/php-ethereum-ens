<?php namespace Ens;

class EnsResolutionEngine implements EnsResolverInterface
{
    /**
     * Default Reverse Resolver (Mainnet) used as a fallback for reverse lookups when the Registry has no resolver set.
     * Source: ENS Default Reverse Resolver
     */
    private const string DEFAULT_REVERSE_RESOLVER = '0x084b1c3c81545d370f3634392de611caabff8148';

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

    public function __construct(
        private readonly EnsClientInterface $client,
        private readonly Configuration $config
    ) {
        $this->reader = new ContractReader($client, $config);
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
                $this->populateRecords($normalizedName, $profile, $recordsToFetch);
            } else {
                // It's an address (names could start with 0x, but addresses never contain dots)
                $normalized = strtolower($addressOrName);
                $normalized = str_starts_with($normalized, '0x') ? $normalized : ('0x' . $normalized);
                $profile->address = $normalized;
                $name = $this->fetchName($addressOrName);
                if ($name) {
                    $normalizedName = Utilities::normalize($name);
                    $profile->name = $normalizedName;
                    $this->populateRecords($normalizedName, $profile, $recordsToFetch);
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
     * Tries the resolver configured in the registry for <addr>.addr.reverse and falls back to the
     * default reverse resolver when necessary.
     *
     * @param string $address Ethereum address (with or without 0x prefix).
     * @return string|null Normalized ENS name if available; otherwise null.
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
        // ensure unique list
        $tryResolvers = array_values(array_unique($tryResolvers));

        for ($pass = 0; $pass < 2; $pass++) {
            foreach ($tryResolvers as $resolver) {
                if (!$resolver) { continue; }
                $result = $this->client->call([
                    'to' => $resolver,
                    'data' => $data,
                    'from' => '0x0000000000000000000000000000000000000000'
                ]);
                if (!$result) {
                    continue;
                }
                $decoded = $this->reader->decodeString($result);
                if ($decoded !== null && $decoded !== '') {
                    return Utilities::normalize($decoded);
                }
            }
        }
        return null;
    }

    // ============ Public low-level helpers ============

    /**
     * Return the resolver address for a given ENS name.
     *
     * @param string $ensName ENS name (e.g. example.eth)
     * @return string|null 0x-prefixed resolver address or null if none configured.
     */
    public function fetchResolverAddressForName(string $ensName): ?string
    {
        $node = Utilities::namehash(Utilities::normalize($ensName));
        return $this->reader->getResolver($node);
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
        $node = Utilities::namehash(Utilities::normalize($ensName));
        $resolver = $this->reader->getResolver($node);
        if (!$resolver) { return null; }
        return $this->reader->getAddr($resolver, $node);
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
        $node = Utilities::namehash(Utilities::normalize($ensName));
        $resolver = $this->reader->getResolver($node);
        if (!$resolver) { return null; }
        $v = $this->reader->getText($resolver, $node, $key);
        return ($v !== null && $v !== '') ? $v : null;
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

    // ======== Private helpers (kept at end) ========

    private function populateRecords(string $name, EnsProfile $profile, array $records): void
    {
        // Attempt to find a resolver for this exact name; if none, walk up the hierarchy
        [$resolverAddress, $nodeUsed] = $this->findResolverUpTree($name);
        if (!$resolverAddress) {
            return;
        }
        // Compute the query node for potential wildcard resolvers
        $queryNode = Utilities::namehash($name);

        // For addresses: query the full name's node; if empty, try the node that owns the resolver
        $addr = $this->reader->getAddr($resolverAddress, $queryNode);
        if (!$addr && $nodeUsed !== $queryNode) {
            $addr = $this->reader->getAddr($resolverAddress, $nodeUsed);
        }
        if ($addr) {
            $profile->address = $addr;
        }
        // Map specific ENS keys to well-known profile properties
        $map = [
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

        // Normalize and de-duplicate requested record keys
        $requested = [];
        foreach ($records as $k) {
            if (is_string($k) && $k !== '') {
                $requested[strtolower($k)] = true;
            }
        }
        // Keep a copy of original requests to populate texts under requested keys
        $requestedOrig = $requested;

        // Helper: fetch a text value from preferred node order (queryNode then nodeUsed)
        $fetchText = function (string $key) use ($resolverAddress, $queryNode, $nodeUsed): ?string {
            $v = $this->reader->getText($resolverAddress, $queryNode, $key);
            if ($v === null || $v === '') {
                $v = $this->reader->getText($resolverAddress, $nodeUsed, $key);
            }
            return ($v !== null && $v !== '') ? $v : null;
        };

        // Avatar via dedicated helper (with single-level parent fallback)
        if (isset($requested['avatar'])) {
            $avatar = $this->fetchAvatar($name);
            if ($avatar !== null) {
                $profile->avatar = $avatar;
                $profile->texts['avatar'] = $avatar;
            }
            unset($requested['avatar']);
        }

        // Avoid duplicate text lookups for twitter: prefer com.twitter, then twitter
        if (isset($requested['com.twitter']) || isset($requested['twitter'])) {
            $val = $fetchText('com.twitter');
            if ($val === null && isset($requested['twitter'])) {
                $val = $fetchText('twitter');
            }
            if ($val !== null) {
                if (isset($requestedOrig['com.twitter'])) { $profile->texts['com.twitter'] = $val; }
                if (isset($requestedOrig['twitter'])) { $profile->texts['twitter'] = $val; }
                $profile->twitter = $val;
            }
            unset($requested['com.twitter'], $requested['twitter']);
        }

        // Avoid duplicate text lookups for github: prefer com.github, then github
        if (isset($requested['com.github']) || isset($requested['github'])) {
            $val = $fetchText('com.github');
            if ($val === null && isset($requested['github'])) {
                $val = $fetchText('github');
            }
            if ($val !== null) {
                if (isset($requestedOrig['com.github'])) { $profile->texts['com.github'] = $val; }
                if (isset($requestedOrig['github'])) { $profile->texts['github'] = $val; }
                $profile->github = $val;
            }
            unset($requested['com.github'], $requested['github']);
        }

        // Fetch remaining requested records with standard fallback order
        foreach (array_keys($requested) as $ensKey) {
            $value = $fetchText($ensKey);
            if ($value !== null && $value !== '') {
                $profile->texts[$ensKey] = $value;
                if (isset($map[$ensKey])) {
                    $prop = $map[$ensKey];
                    $profile->$prop = $value;
                }
            }
        }
    }

    private function findResolverUpTree(string $name): array
    {
        // Walk up from the full name to parents until a resolver is found
        $current = Utilities::normalize($name);
        while (true) {
            $node = Utilities::namehash($current);
            $resolver = $this->reader->getResolver($node);
            if ($resolver) {
                return [$resolver, $node];
            }
            // Move to parent: drop the left-most label
            $dotPos = strpos($current, '.');
            if ($dotPos === false) {
                break;
            }
            $current = substr($current, $dotPos + 1);
            if ($current === '' || $current === false) {
                break;
            }
        }
        return [null, ''];
    }
}
