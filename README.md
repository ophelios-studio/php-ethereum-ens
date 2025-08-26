# PHP Ethereum ENS

A lightweight PHP library to read ENS (Ethereum Name Service) records via standard JSON-RPC providers. It provides:

- Reverse lookup (address -> primary ENS name)
- Name resolution for records and avatar
- A simple profile hydrator to fetch common text records
- A small facade (EnsService) for convenience

The library does not write to the chain and works with any Ethereum-compatible RPC endpoint.

## Installation

Install with Composer:

```
composer require ophelios/php-ethereum-ens
```

## Quick start

```php
use Ens\EnsService;

$rpcUrl = getenv('ENS_PROVIDER_URL') ?: 'https://mainnet.infura.io/v3/<key>';
$ens = new EnsService($rpcUrl);

// Reverse resolve
$name = $ens->resolveEnsName('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');
// => 'vitalik.eth'

// Resolve a profile (common records by default)
$profile = $ens->resolveProfile('vitalik.eth');
// $profile is an instance of Ens\EnsProfile

// Resolve single/multiple records
$avatar = $ens->resolveAvatar('vitalik.eth');
$url = $ens->resolveRecord('vitalik.eth', 'url');
$records = $ens->resolveRecords('vitalik.eth', ['email', 'url']);
```

## API Overview

- Ens\\EnsService
  - __construct(string|Ens\\Web3ClientInterface $clientOrRpcUrl)
  - resolveEnsName(string $address): ?string
  - resolveProfile(string $ensName, array $records = Ens\\ProfileHydrator::DEFAULT_RECORDS): Ens\\EnsProfile
  - resolveAvatar(string $ensName, bool $parentFallback = true): ?string
  - resolveRecord(string $ensName, string|array $record): ?string
  - resolveRecords(string $ensName, array $records): ?array

- Ens\\Resolver
  - Resolve individual records for a name. Handles parent fallback for avatar and inherited resolvers.

- Ens\\ReverseLookup
  - Reverse resolve address -> name using the registry and the default reverse resolver as fallback.

- Ens\\ProfileHydrator
  - Populates an EnsProfile from Resolver data with a set of requested records.

- Ens\\Utilities
  - normalize(string $name): string
  - namehash(string $name): string

- Ens\\Web3ClientInterface / Ens\\Web3Client
  - Thin wrapper around web3p/web3.php for read-only eth_call with retries.

## Testing

The test suite contains both unit tests (with mocks) and an optional live integration test.

Run all tests (unit + integration):

```
vendor/bin/phpunit
```

Run unit tests only:

```
vendor/bin/phpunit --testsuite Unit
```

Integration tests require an Ethereum RPC URL with ENS access. Set an environment variable:

```
ENS_PROVIDER_URL=https://mainnet.infura.io/v3/<key>
```

Then run:

```
vendor/bin/phpunit --testsuite Integration
```

## Notes

- This package performs read-only on-chain calls via eth_call. No private keys are required.
- For internationalized domains, normalize() attempts to use idn_to_ascii when available.
- Mainnet registry and default reverse resolver addresses are embedded in the library.


