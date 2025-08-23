# PHP Ethereum ENS

A lightweight ENS Resolver for PHP.

## Installation

Require via Composer:

```
composer require ophelios/php-ethereum-ens
```

## Usage

```php
use Ens\EnsService;

$providerUrl = 'https://mainnet.infura.io/v3/<your-project-id>'; // or another mainnet JSON-RPC URL
$ens = new EnsService($providerUrl);
$profile = $ens->getProfile('vitalik.eth');

print_r($profile->toArray());
```

## Testing

This project uses PHPUnit. Unit tests mock the JSON-RPC client; live tests hit Ethereum mainnet.

- Copy .env.example to .env and set your provider URL (kept out of VCS/CI logs):

```
cp .env.example .env
# edit .env to set ENS_PROVIDER_URL
```

- Run tests:

```
composer install
composer test
```

By default, if ENS_PROVIDER_URL is not set, live tests are skipped. To run live tests in CI/CD, set a secret environment variable `ENS_PROVIDER_URL` (e.g., your Infura endpoint):

```
ENS_PROVIDER_URL=https://mainnet.infura.io/v3/<your-project-id> vendor/bin/phpunit --testsuite Integration
```

## Security Notes

- Live tests read ENS_PROVIDER_URL from the environment via Dotenv; the value is never committed.
- The library performs read-only eth_call operations and does not send transactions.
