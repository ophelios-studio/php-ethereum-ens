<?php namespace Ens;

final readonly class ContractReader
{
    public const string DEFAULT_REGISTRY_ADDRESS = '0x00000000000C2E074eC69A0dFb2997BA6C7d2e1e'; // ENS Registry mainnet address

    public function __construct(
        private Web3ClientInterface $client
    ) {}

    /**
     * Retrieves the resolver address associated with a specific node from the ENS Registry.
     *
     * @param string $node The node for which to fetch the resolver address.
     * @return string|null The resolver address if found, or null if not found.
     */
    public function getResolver(string $node): ?string
    {
        $result = $this->client->call([
            'to' => self::DEFAULT_REGISTRY_ADDRESS,
            'data' => '0x0178b8bf' . substr($node, 2)
        ]);
        if ($result && $result !== '0x0000000000000000000000000000000000000000000000000000000000000000') {
            return '0x' . substr($result, 26);
        }
        return null;
    }

    /**
     * Retrieves a text record value for the given resolver address, node, and key using ABI-correct encoding.
     */
    public function getText(string $resolverAddress, string $node, string $key): ?string
    {
        $selector = '59d1d43c';
        $encodedNode = substr($node, 2);
        $stringOffset = str_pad('40', 64, '0', STR_PAD_LEFT);
        $stringLength = str_pad(dechex(strlen($key)), 64, '0', STR_PAD_LEFT);
        $stringData = str_pad(bin2hex($key), (int)ceil(strlen(bin2hex($key)) / 64) * 64, '0', STR_PAD_RIGHT);
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

    /**
     * Retrieves the Ethereum address record for a node from a resolver as a lowercase hexadecimal string prefixed
     * with "0x", or null if the address cannot be found, is invalid, or consists only of zeros.
     */
    public function getAddr(string $resolverAddress, string $node): ?string
    {
        $selector = '3b3b57de'; // addr(bytes32)
        $encodedNode = substr($node, 2);
        $data = '0x' . $selector . $encodedNode;
        $result = $this->client->call([
            'to' => $resolverAddress,
            'data' => $data
        ]);
        if (!$result || $result === '0x') {
            return null;
        }
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

    /**
     * Decodes a hexadecimal string (ABI decode string response (offset,length,data...)) into its ASCII representation.
     *
     * @param string $hexString The hexadecimal string to decode, starting with "0x".
     * @return string|null Returns the decoded string if successful, or null if decoding fails or the input is invalid.
     */
    public function decodeString(string $hexString): ?string
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
}
