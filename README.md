# PHP Ethereum ENS 🚀

[![Maintainability](https://qlty.sh/badges/7487c070-b46a-4e39-97ae-4abd686f2320/maintainability.svg)](https://qlty.sh/gh/ophelios-studio/projects/php-ethereum-ens)
[![Code Coverage](https://qlty.sh/badges/7487c070-b46a-4e39-97ae-4abd686f2320/coverage.svg)](https://qlty.sh/gh/ophelios-studio/projects/php-ethereum-ens)

A lightweight PHP library for reading ENS (Ethereum Name Service) records using any standard JSON‑RPC provider. Read‑only by design, fast to adopt, and easy to extend.

## ✨ Features
- 🔄 Reverse lookup (address → primary ENS name)
- 🧩 Resolve text records and avatar for a name
- 👤 Simple profile hydrator for common records
- 🎛️ Tiny facade (EnsService) for convenience
- 🔒 Read‑only eth_call usage (no private keys needed)
- ⚡ Works with any Ethereum‑compatible RPC endpoint

## 💿 Installation
Install with Composer:

```
composer require ophelios/php-ethereum-ens
```

## 🌱 Quick start
```php
use Ens\EnsService;

$rpcUrl = getenv('ENS_PROVIDER_URL') ?: 'https://mainnet.infura.io/v3/<key>';
$ens = new EnsService($rpcUrl);

// Reverse resolve (address -> ENS name)
$name = $ens->resolveEnsName('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045'); // vitalik.eth

// Resolve a profile (common records by default)
$profile = $ens->resolveProfile('vitalik.eth');
```

### 🎯 Resolve individual records
```php
use Ens\EnsService;

$rpcUrl = getenv('ENS_PROVIDER_URL') ?: 'https://mainnet.infura.io/v3/<key>';
$ens = new EnsService($rpcUrl);

// Resolve single/multiple records (without profile)
$avatar  = $ens->resolveAvatar('vitalik.eth');
$url     = $ens->resolveRecord('vitalik.eth', 'url');
$records = $ens->resolveRecords('vitalik.eth', ['email', 'url']);
```

## 📚 API overview
- Ens\\EnsService
  - __construct(string|Ens\\Web3ClientInterface $clientOrRpcUrl)
  - resolveEnsName(string $address): ?string
  - resolveProfile(string $ensName, array $records = Ens\\ProfileHydrator::DEFAULT_RECORDS): Ens\\EnsProfile
  - resolveAvatar(string $ensName, bool $parentFallback = true): ?string
  - resolveRecord(string $ensName, string|array $record): ?string
  - resolveRecords(string $ensName, array $records): ?array

- Ens\\Resolver: resolve individual records for a name. Handles inherited resolvers and one‑level parent fallback for avatar.
- Ens\\ReverseLookup: reverse resolve address → name using registry, with default reverse resolver fallback.
- Ens\\ProfileHydrator: populate an EnsProfile with a set of requested records.
- Ens\\Utilities: normalize(string $name), namehash(string $name)
- Ens\\Web3ClientInterface / Ens\\Web3Client: thin wrapper around web3p/web3.php for eth_call with retries.

## ⚙️ Configuration (custom client)
You can pass a custom client instead of a URL if you need to control retries or timeouts:

```php
use Ens\Web3Client;
use Ens\Configuration;
use Ens\EnsService;

$client = new Web3Client(new Configuration(
    rpcUrl: 'https://mainnet.infura.io/v3/<key>',
    timeoutMs: 10000,
    maxRetries: 3,
));

$ens = new EnsService($client);
```

## 🧰 Default records
ProfileHydrator::DEFAULT_RECORDS includes:
- avatar, url, email, description
- social aliases: ["com.twitter", "twitter"], ["com.github", "github"]

When an array of keys is provided, the first matching key is mapped to the corresponding profile property; all keys in that group are still available in `$profile->texts`.

## 🧪 Testing
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

## 🤝 Contributing
We welcome contributions! If you find a bug or have an enhancement in mind:
- Open an issue to discuss it, or
- Send a pull request (PR) with a clear description and relevant tests.

To work on the project locally:
- Install dependencies: `composer install`
- Run unit tests: `vendor/bin/phpunit --testsuite Unit`
- Please do not modify integration tests in PRs unless specifically discussed.

## 📄 License
MIT License © 2025 Ophelios. See the LICENSE file for full text.

## 🗒️ Notes
- Read‑only on‑chain calls via eth_call. No private keys are required.
- For internationalized domains, `normalize()` attempts to use `idn_to_ascii` when available.
- Mainnet registry and default reverse resolver addresses are embedded in the library.
