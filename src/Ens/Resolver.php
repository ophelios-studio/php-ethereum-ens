<?php namespace Ens;

class Resolver
{
    private ?string $resolverAddress = null;
    private string $normalizedName;
    private string $queryNode;
    private string $nodeUsed = '';
    private ContractReader $reader;

    public function __construct(string $ensName, Web3ClientInterface $client)
    {
        $this->reader = new ContractReader($client);
        $this->normalizedName = Utilities::normalize($ensName);
        $this->queryNode = Utilities::namehash($this->normalizedName);
        $this->initializeResolverAddress();
    }

    /**
     * Returns the normalized ENS name (UTS-46 compatible normalization).
     */
    public function getName(): string
    {
        return $this->normalizedName;
    }

    /**
     * Returns the resolver address currently in force for the name (or null if none).
     */
    public function getResolverAddress(): ?string
    {
        return $this->resolverAddress;
    }

    /**
     * Returns the namehash of the normalized name.
     */
    public function getQueryNode(): string
    {
        return $this->queryNode;
    }

    /**
     * Returns the node on which the active resolver was discovered (may differ from queryNode if inherited).
     */
    public function getNodeUsed(): string
    {
        return $this->nodeUsed;
    }

    /**
     * Returns true if a resolver is configured for the name or one of its parents.
     */
    public function exists(): bool
    {
        return $this->resolverAddress !== null;
    }

    /**
     * Resolve coin-type 60 (ETH) address using the cached resolver/node. Tries queryNode first, then nodeUsed
     * if different. Returns lowercase 0x address or null.
     */
    public function getAddress(): ?string
    {
        if ($this->resolverAddress === null) {
            return null;
        }
        $addr = $this->reader->getAddr($this->resolverAddress, $this->queryNode);
        if (!$addr && $this->nodeUsed !== $this->queryNode) {
            $addr = $this->reader->getAddr($this->resolverAddress, $this->nodeUsed);
        }
        return $addr ?: null;
    }

    public function getRecord(string|array $key): ?string
    {
        return is_array($key)
            ? $this->getFirstText($key)
            : (($key == 'avatar') ? $this->getAvatar() : $this->getText($key));
    }

    public function getRecords(array $keys): ?array
    {
        if ($this->resolverAddress === null) {
            return null;
        }
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->getRecord($key);
        }
        return $results;
    }

    public function getText(string $key): ?string
    {
        if ($this->resolverAddress === null) {
            return null;
        }
        $value = $this->reader->getText($this->resolverAddress, $this->queryNode, $key);
        if ($value === null || $value === '') {
            if ($this->nodeUsed !== $this->queryNode) {
                $value = $this->reader->getText($this->resolverAddress, $this->nodeUsed, $key);
            }
        }
        return ($value !== null && $value !== '') ? $value : null;
    }

    /**
     * Get the first matching text value of the given keys from the resolver.
     */
    public function getFirstText(array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!is_string($key) || $key === '') { continue; }
            $v = $this->getText($key);
            if ($v !== null && $v !== '') { return $v; }
        }
        return null;
    }

    /**
     * Special-case avatar with one-level parent fallback: try current resolver on queryNode; if not found,
     * ascend exactly one label and fetch avatar via the parent's resolver/node (if any).
     */
    public function getAvatar(bool $parentFallback = true): ?string
    {
        if ($this->resolverAddress === null) {
            return null;
        }

        // First try current name on its active resolver (even if inherited from the parent)
        $v = $this->reader->getText($this->resolverAddress, $this->queryNode, 'avatar');
        if ($v !== null && $v !== '') {
            return $v;
        }

        // Parent fallback: compute parent label and check its resolver/node independently
        if ($parentFallback) {
            $dotPos = strpos($this->normalizedName, '.');
            if ($dotPos === false) {
                return null;
            }
            $parent = substr($this->normalizedName, $dotPos + 1);
            if ($parent === '') {
                return null;
            }
            $pNode = Utilities::namehash($parent);
            $pResolver = $this->reader->getResolver($pNode);
            if (!$pResolver) {
                return null;
            }
            $pv = $this->reader->getText($pResolver, $pNode, 'avatar');
            return ($pv !== null && $pv !== '') ? $pv : null;
        }
        return null;
    }

    private function initializeResolverAddress(): void
    {
        $current = $this->normalizedName;
        while (true) {
            $node = Utilities::namehash($current);
            $resolver = $this->reader->getResolver($node);
            if ($resolver) {
                $this->resolverAddress = $resolver;
                $this->nodeUsed = $node;
                break;
            }
            $dot = strpos($current, '.');
            if ($dot === false) {
                break;
            }
            $current = substr($current, $dot + 1);
            if ($current === '') {
                break;
            }
        }
    }
}
