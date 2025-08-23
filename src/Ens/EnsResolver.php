<?php namespace Ens;

use kornrunner\Keccak;

class EnsResolver implements EnsResolverInterface
{
    /**
     * Default set of ENS text records to fetch when no list is provided at call-site.
     * These include core EIP-634 keys and popular community keys.
     */
    public const DEFAULT_RECORDS = [
        'avatar',
        'display',
        'url',
        'email',
        'description',
        'notice',
        'keywords',
        // Support both legacy and current keys for popular platforms
        'com.twitter', 'twitter',
        'com.github', 'github',
        'com.discord',
        'com.reddit',
        'org.telegram',
        'io.keybase',
        'org.matrix',
        'com.linkedin'
    ];

    public function __construct(
        private readonly EnsClientInterface $client,
        private readonly Configuration $config
    ) {}

    public function resolve(string $addressOrName, ?array $records = null): EnsProfile
    {
        $profile = new EnsProfile();
        $recordsToFetch = $records ?? self::DEFAULT_RECORDS;
        try {
            if (str_contains($addressOrName, '.')) {
                // It's a name like example.eth
                $normalizedName = $this->normalizeName($addressOrName);
                $profile->name = $normalizedName;
                $this->populateRecords($normalizedName, $profile, $recordsToFetch);
            } else {
                // It's an address (names could start with 0x, but addresses never contain dots)
                $normalized = strtolower($addressOrName);
                $normalized = str_starts_with($normalized, '0x') ? $normalized : ('0x' . $normalized);
                $profile->address = $normalized;
                $name = $this->getNameFromAddress($addressOrName);
                if ($name) {
                    $normalizedName = $this->normalizeName($name);
                    $profile->name = $normalizedName;
                    $this->populateRecords($normalizedName, $profile, $recordsToFetch);
                }
            }
        } catch (\Throwable $e) {
            error_log("ENS Resolution error: " . $e->getMessage());
        }
        return $profile;
    }

    private function getNameFromAddress(string $address): ?string
    {
        $address = strtolower(trim($address));
        $cleanAddress = str_starts_with($address, '0x') ? substr($address, 2) : $address;
        $reverseName = $cleanAddress . '.addr.reverse';
        $nameHash = $this->namehash($reverseName);
        $resolverAddress = $this->getResolver($nameHash);
        if (!$resolverAddress) {
            return null;
        }
        $data = '0x691f3431' . substr($nameHash, 2); // name(bytes32)
        $result = $this->client->call([
            'to' => $resolverAddress,
            'data' => $data,
            'from' => '0x0000000000000000000000000000000000000000'
        ]);
        if (!$result) {
            return null;
        }
        $decoded = $this->decodeString($result);
        if ($decoded === null) {
            return null;
        }
        return $this->normalizeName($decoded);
    }

    private function populateRecords(string $name, EnsProfile $profile, array $records): void
    {
        // Attempt to find a resolver for this exact name; if none, walk up the hierarchy
        [$resolverAddress, $nodeUsed] = $this->findResolverUpTree($name);
        if (!$resolverAddress) {
            return;
        }
        // Try to resolve the ETH address for this name (using the node that has a resolver)
        $addr = $this->getAddr($resolverAddress, $nodeUsed);
        if ($addr) {
            $profile->address = $addr;
        }
        // Map specific ENS keys to well-known profile properties
        $map = [
            'avatar' => 'avatar',
            'url' => 'url',
            'email' => 'email',
            'description' => 'description',
            'display' => 'display',
            'notice' => 'notice',
            'keywords' => 'keywords',
            'com.twitter' => 'twitter',
            'twitter' => 'twitter',
            'com.github' => 'github',
            'github' => 'github',
            'com.discord' => 'discord',
            'com.reddit' => 'reddit',
            'org.telegram' => 'telegram',
            'io.keybase' => 'keybase',
            'org.matrix' => 'matrix',
            'com.linkedin' => 'linkedin',
        ];
        foreach ($records as $ensKey) {
            $value = $this->getText($resolverAddress, $nodeUsed, $ensKey);
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
        $current = $this->normalizeName($name);
        while (true) {
            $node = $this->namehash($current);
            $resolver = $this->getResolver($node);
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

    private function getResolver(string $node): ?string
    {
        $result = $this->client->call([
            'to' => $this->config->registryAddress,
            'data' => '0x0178b8bf' . substr($node, 2)
        ]);
        if ($result && $result !== '0x0000000000000000000000000000000000000000000000000000000000000000') {
            return '0x' . substr($result, 26);
        }
        return null;
    }

    private function getText(string $resolverAddress, string $node, string $key): ?string
    {
        $selector = '59d1d43c';
        $encodedNode = substr($node, 2);
        $stringOffset = str_pad('40', 64, '0', STR_PAD_LEFT);
        $stringLength = str_pad(dechex(strlen($key)), 64, '0', STR_PAD_LEFT);
        $stringData = str_pad(bin2hex($key), ceil(strlen(bin2hex($key)) / 64) * 64, '0', STR_PAD_RIGHT);
        $data = '0x' . $selector . $encodedNode . $stringOffset . $stringLength . $stringData;
        $result = $this->client->call([
            'to' => $resolverAddress,
            'data' => $data
        ]);
        if (!$result) {
            return null;
        }
        return $this->decodeString($result);
    }

    private function getAddr(string $resolverAddress, string $node): ?string
    {
        // resolver.addr(bytes32) selector: 0x3b3b57de
        $selector = '3b3b57de';
        $encodedNode = substr($node, 2);
        $data = '0x' . $selector . $encodedNode;
        $result = $this->client->call([
            'to' => $resolverAddress,
            'data' => $data
        ]);
        if (!$result || $result === '0x' ) {
            return null;
        }
        // Result is a 32-byte word; extract the last 20 bytes as the address
        $hex = substr($result, 2);
        if (strlen($hex) < 64) {
            return null;
        }
        $addrHex = substr($hex, -40);
        if ($addrHex === str_repeat('0', 40)) {
            return null;
        }
        return '0x' . strtolower($addrHex);
    }

    private function decodeString(string $hexString): ?string
    {
        try {
            $hex = substr($hexString, 2);
            $offset = hexdec(substr($hex, 0, 64));
            $length = hexdec(substr($hex, $offset * 2, 64));
            if ($length === 0) {
                return null;
            }
            $stringHex = substr($hex, ($offset * 2) + 64, $length * 2);
            return hex2bin($stringHex);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeName(string $name): string
    {
        // Trim whitespace and trailing dot, lowercase labels; attempt IDNA ASCII conversion when available
        $n = trim($name);
        $n = rtrim($n, '.');
        $n = strtolower($n);
        if (function_exists('idn_to_ascii')) {
            try {
                $ascii = idn_to_ascii($n, IDNA_DEFAULT);
                if ($ascii !== false && is_string($ascii)) {
                    return strtolower($ascii);
                }
            } catch (\Throwable $e) {
                // ignore and fall back to lowercased input
            }
        }
        return $n;
    }

    private function namehash(string $name): string
    {
        $node = str_repeat('0', 64);
        if ($name) {
            $labels = array_reverse(explode('.', $name));
            foreach ($labels as $label) {
                $node = Keccak::hash(hex2bin($node) . hex2bin(Keccak::hash($label, 256)), 256);
            }
        }
        return '0x' . $node;
    }
}
