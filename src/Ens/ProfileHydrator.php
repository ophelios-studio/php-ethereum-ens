<?php namespace Ens;

/**
 * ProfileHydrator fills an EnsProfile with address and text records for a given ENS name,
 * using a provided EnsClient. It centralizes the record mapping and fallback behavior
 * and exposes a small, easy to follow public API.
 */
final class ProfileHydrator
{
    private ?string $resolver = null;
    private string $queryNode = '';
    private string $nodeUsed = '';

    public function __construct(
        private readonly EnsClient $ensClient,
        private readonly EnsProfile $profile
    ) {}

    /**
     * Hydrate the given profile with the requested records for the provided ENS name.
     *
     * @param string $name Normalized ENS name
     * @param array $records Requested text record keys
     */
    public function hydrate(string $name, array $records): void
    {
        $this->initiateNodes($name);
        if ($this->resolver === null) {
            return;
        }

        // Address first to match previous behavior
        $this->populateAddress();

        [$requested, $requestedOrig] = $this->collectRequested($records);

        // Avatar uses dedicated fallback behavior
        $this->applyAvatar($name, $requested, $requestedOrig);

        // Social pairs (avoid duplicate lookups)
        $this->fetchSocialPair('com.twitter', 'twitter', $requested, $requestedOrig);
        $this->fetchSocialPair('com.github', 'github', $requested, $requestedOrig);

        // Remaining requested records
        $this->fetchRemaining($requested);
    }

    /**
     * Initialize resolver and nodes used for queries and fallback.
     * Sets $this->resolver, $this->queryNode, and $this->nodeUsed.
     */
    private function initiateNodes(string $name): void
    {
        [$resolverAddress, $nodeUsed] = $this->ensClient->findResolverNode($name);
        if (!$resolverAddress) {
            $this->resolver = null;
            $this->queryNode = '';
            $this->nodeUsed = '';
            return;
        }
        $this->resolver = $resolverAddress;
        $this->queryNode = Utilities::namehash($name);
        $this->nodeUsed = $nodeUsed;
    }

    /**
     * Populate address from queryNode with fallback to nodeUsed.
     */
    private function populateAddress(): void
    {
        if ($this->resolver === null) { return; }
        $addr = $this->fetchAddrFrom($this->resolver, $this->queryNode);
        if (!$addr && $this->nodeUsed !== $this->queryNode) {
            $addr = $this->fetchAddrFrom($this->resolver, $this->nodeUsed);
        }
        if ($addr) {
            $this->profile->address = $addr;
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
    private function applyAvatar(string $name, array &$requested, array $requestedOrig): void
    {
        if (!isset($requested['avatar'])) {
            return;
        }
        $avatar = $this->ensClient->fetchAvatar($name);
        if ($avatar !== null) {
            $this->profile->avatar = $avatar;
            $this->profile->texts['avatar'] = $avatar;
        }
        unset($requested['avatar']);
    }

    /**
     * Handle paired social keys (e.g., com.twitter/twitter) with preference to the namespaced key.
     */
    private function fetchSocialPair(
        string $primaryKey,
        string $secondaryKey,
        array &$requested,
        array $requestedOrig
    ): void {
        if (!isset($requested[$primaryKey]) && !isset($requested[$secondaryKey])) {
            return;
        }
        if ($this->resolver === null) { unset($requested[$primaryKey], $requested[$secondaryKey]); return; }
        $keys = [$primaryKey, $secondaryKey];
        $val = $this->ensClient->fetchFirstTextByNodes($this->resolver, $this->queryNode, $this->nodeUsed, $keys);
        if ($val !== null && $val !== '') {
            // Reflect the found value under any originally requested keys
            foreach ($keys as $k) {
                if (isset($requestedOrig[$k])) {
                    $this->profile->texts[$k] = $val;
                }
            }
            // Map to a known property using the first key that has a mapping
            $map = $this->getPropertyMap();
            foreach ($keys as $k) {
                if (isset($map[$k])) {
                    $prop = $map[$k];
                    $this->profile->$prop = $val;
                    break;
                }
            }
        }
        unset($requested[$primaryKey], $requested[$secondaryKey]);
    }

    /**
     * Fetch all remaining keys using standard fallback order, updating profile texts and mapped props.
     */
    private function fetchRemaining(
        array $requested
    ): void {
        $map = $this->getPropertyMap();
        foreach (array_keys($requested) as $ensKey) {
            $value = $this->fetchTextWithFallback($ensKey);
            if ($value !== null && $value !== '') {
                $this->profile->texts[$ensKey] = $value;
                if (isset($map[$ensKey])) {
                    $prop = $map[$ensKey];
                    $this->profile->$prop = $value;
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
    private function fetchTextWithFallback(string $key): ?string
    {
        if ($this->resolver === null) { return null; }
        $v = $this->fetchTextFrom($this->resolver, $this->queryNode, $key);
        if ($v === null || $v === '') {
            $v = $this->fetchTextFrom($this->resolver, $this->nodeUsed, $key);
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
