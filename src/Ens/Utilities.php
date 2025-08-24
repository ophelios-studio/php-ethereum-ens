<?php namespace Ens;

use kornrunner\Keccak;

final class Utilities
{
    /**
     * Normalizes a domain name by trimming, converting to lowercase, and optionally
     * transforming it to ASCII format using IDN (Internationalized Domain Names) encoding.
     *
     * @param string $name The domain name to normalize.
     * @return string The normalized domain name in lowercase and optionally ASCII format.
     */
    public static function normalize(string $name): string
    {
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

    /**
     * Computes EIP-137 namehash for a given domain name. If the name is empty, returns the zero hash (0x followed
     * by 64 zeros).
     *
     * @param string $name The domain name to hash, represented as a dot-separated string.
     * @return string The computed name hash as a hexadecimal string prefixed with "0x".
     */
    public static function namehash(string $name): string
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
