<?php namespace Ens;

/**
 * ProfileHydrator fills an EnsProfile with address and text records for a given ENS name,
 * using a provided EnsClient. It centralizes the record mapping and fallback behavior
 * and exposes a small, easy to follow public API.
 */
final class ProfileHydrator
{
    public function __construct(private readonly EnsClient $ensClient) {}

    /**
     * Hydrate the given profile with the requested records for the provided ENS name.
     *
     * @param string $name Normalized ENS name
     * @param EnsProfile $profile Profile to mutate
     * @param array $records Requested text record keys
     */
    public function hydrate(string $name, EnsProfile $profile, array $records): void
    {
        [$resolver, $queryNode, $nodeUsed] = $this->resolveNodes($name);
        if ($resolver === null) {
            return;
        }

        // Address first to match previous behavior
        $this->populateAddress($resolver, $queryNode, $nodeUsed, $profile);

        [$requested, $requestedOrig] = $this->collectRequested($records);

        // Avatar uses dedicated fallback behavior
        $this->applyAvatar($name, $requested, $requestedOrig, $profile);

        // Social pairs (avoid duplicate lookups)
        $this->fetchSocialPair('com.twitter', 'twitter', $resolver, $queryNode, $nodeUsed, $requested, $requestedOrig, $profile);
        $this->fetchSocialPair('com.github', 'github', $resolver, $queryNode, $nodeUsed, $requested, $requestedOrig, $profile);

        // Remaining requested records
        $this->fetchRemaining($resolver, $queryNode, $nodeUsed, $requested, $profile);
    }

    /**
     * Find the resolver and compute nodes for querying and fallback.
     *
     * @return array{0:?string,1:string,2:string} [resolverAddress|null, queryNode, nodeUsed]
     */
    private function resolveNodes(string $name): array
    {
        [$resolverAddress, $nodeUsed] = $this->ensClient->findResolverNode($name);
        if (!$resolverAddress) {
            return [null, '', ''];
        }
        $queryNode = Utilities::namehash($name);
        return [$resolverAddress, $queryNode, $nodeUsed];
    }

    /**
     * Populate address from queryNode with fallback to nodeUsed.
     */
    private function populateAddress(string $resolver, string $queryNode, string $nodeUsed, EnsProfile $profile): void
    {
        $addr = $this->fetchAddrFrom($resolver, $queryNode);
        if (!$addr && $nodeUsed !== $queryNode) {
            $addr = $this->fetchAddrFrom($resolver, $nodeUsed);
        }
        if ($addr) {
            $profile->address = $addr;
        }
    }

    /**
     * Normalize and de-duplicate requested keys.
     *
     * @param array $records
     * @return array{0:array<string,bool>,1:array<string,bool>}
     */
    private function collectRequested(array $records): array
    {
        $requested = [];
        foreach ($records as $k) {
            if (is_string($k) && $k !== '') {
                $requested[strtolower($k)] = true;
            }
        }
        $requestedOrig = $requested;
        return [$requested, $requestedOrig];
    }

    /**
     * Fetch avatar via dedicated RecordFetcher (with parent fallback).
     * Unsets the 'avatar' request after applying.
     */
    private function applyAvatar(string $name, array &$requested, array $requestedOrig, EnsProfile $profile): void
    {
        if (!isset($requested['avatar'])) {
            return;
        }
        $avatar = $this->ensClient->fetchAvatar($name);
        if ($avatar !== null) {
            $profile->avatar = $avatar;
            $profile->texts['avatar'] = $avatar;
        }
        unset($requested['avatar']);
    }

    /**
     * Handle paired social keys (e.g., com.twitter/twitter) with preference to the namespaced key.
     */
    private function fetchSocialPair(
        string $primaryKey,
        string $secondaryKey,
        string $resolver,
        string $queryNode,
        string $nodeUsed,
        array &$requested,
        array $requestedOrig,
        EnsProfile $profile
    ): void {
        if (!isset($requested[$primaryKey]) && !isset($requested[$secondaryKey])) {
            return;
        }
        $val = $this->fetchTextWithFallback($resolver, $queryNode, $nodeUsed, $primaryKey);
        if ($val === null && isset($requested[$secondaryKey])) {
            $val = $this->fetchTextWithFallback($resolver, $queryNode, $nodeUsed, $secondaryKey);
        }
        if ($val !== null) {
            if (isset($requestedOrig[$primaryKey])) { $profile->texts[$primaryKey] = $val; }
            if (isset($requestedOrig[$secondaryKey])) { $profile->texts[$secondaryKey] = $val; }
            $prop = $this->getPropertyMap()[$primaryKey] ?? $this->getPropertyMap()[$secondaryKey] ?? null;
            if ($prop) { $profile->$prop = $val; }
        }
        unset($requested[$primaryKey], $requested[$secondaryKey]);
    }

    /**
     * Fetch all remaining keys using standard fallback order, updating profile texts and mapped props.
     */
    private function fetchRemaining(
        string $resolver,
        string $queryNode,
        string $nodeUsed,
        array $requested,
        EnsProfile $profile
    ): void {
        $map = $this->getPropertyMap();
        foreach (array_keys($requested) as $ensKey) {
            $value = $this->fetchTextWithFallback($resolver, $queryNode, $nodeUsed, $ensKey);
            if ($value !== null && $value !== '') {
                $profile->texts[$ensKey] = $value;
                if (isset($map[$ensKey])) {
                    $prop = $map[$ensKey];
                    $profile->$prop = $value;
                }
            }
        }
    }

    /**
     * Property map for known ENS text keys.
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

    /**
     * Fetch a text value trying queryNode first then nodeUsed.
     */
    private function fetchTextWithFallback(string $resolver, string $queryNode, string $nodeUsed, string $key): ?string
    {
        $v = $this->fetchTextFrom($resolver, $queryNode, $key);
        if ($v === null || $v === '') {
            $v = $this->fetchTextFrom($resolver, $nodeUsed, $key);
        }
        return ($v !== null && $v !== '') ? $v : null;
    }

    private function fetchAddrFrom(string $resolverAddress, string $node): ?string
    {
        return $this->ensClient->fetchAddrByNode($resolverAddress, $node);
    }

    private function fetchTextFrom(string $resolverAddress, string $node, string $key): ?string
    {
        return $this->ensClient->fetchTextByNode($resolverAddress, $node, $key);
    }
}
