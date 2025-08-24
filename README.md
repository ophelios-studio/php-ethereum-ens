# PHP Ethereum ENS

A lightweight, dependency‑minimal ENS (Ethereum Name Service) resolver for PHP.

- Resolve by ENS name to address and profile records.
- Reverse resolve an address to its primary ENS name.
- Fetch common ENS text records (avatar, url, twitter, github, …).
- Works with any Ethereum JSON-RPC provider (Infura, Alchemy, self‑hosted, etc.).
- Read‑only: uses eth_call only, no transactions (no gas cost).

## Installation

Require via Composer:

```
composer require ophelios/php-ethereum-ens
```

## Quick start

```php
use Ens\EnsResolver;

$providerUrl = 'https://mainnet.infura.io/v3/<your-project-id>'; // or any mainnet JSON‑RPC URL
$ens = new EnsResolver($providerUrl);

// Resolve by name
$profile = $ens->getProfile('vitalik.eth');
print_r($profile->toArray());

// Resolve by address (reverse lookup + forward records)
$profile = $ens->getProfile('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');
print_r($profile->toArray());
```

### What you get back
`EnsProfile` exposes convenient properties and a generic `texts` map for any fetched key:
- name, address
- avatar, url, email, description, display, notice, keywords
- twitter, github, discord, reddit, telegram, keybase, matrix, linkedin
- texts: [ensKey => value]

### Supported records by default
The resolver fetches a curated set of common ENS text records when you don’t pass a list:
`avatar, display, url, email, description, notice, keywords, com.twitter, twitter, com.github, github, com.discord, com.reddit, org.telegram, io.keybase, org.matrix, com.linkedin`

You can also pass a custom list to `getProfile($name, $records)` if you use the lower‑level resolver directly.

## Testing

This project uses PHPUnit. Unit tests mock the JSON‑RPC client; live tests query Ethereum mainnet.

- Copy .env.example to .env and set your provider URL (kept out of VCS/CI logs):

```
cp .env.example .env
# edit .env to set ENS_PROVIDER_URL
```

- Run tests locally:

```
composer install
composer test
```

By default, if `ENS_PROVIDER_URL` is not set, live tests are skipped. To run live tests:

```
ENS_PROVIDER_URL=https://mainnet.infura.io/v3/<your-project-id> \
  vendor/bin/phpunit --testsuite Integration
```

### CI status & coverage
- CI runs on pushes/PRs to `dev`. Unit tests always run. Integration tests also run in CI when `ENS_PROVIDER_URL` is provided as a GitHub Actions secret.
- To see coverage locally, enable a coverage driver (Xdebug or PCOV) and run:

```
vendor/bin/phpunit --coverage-text
```

## Usage notes & security
- The resolver is read‑only and never sends transactions.
- Provider URL is read from the environment via Dotenv in tests; keep secrets out of source control.

## Contributing
- Issues and PRs are welcome.
- For feature work, please add/update unit tests (and integration tests if they touch live resolution behavior).

## Funding / Donations
If this library helps you, consider supporting:
- GitHub Sponsors: ophelios-studio
- ETH: ophelios.booe.eth

## License
MIT License © Ophelios Studio
