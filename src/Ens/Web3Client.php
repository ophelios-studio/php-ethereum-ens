<?php namespace Ens;

use Web3\Web3;
use Web3\Providers\HttpProvider;

readonly class Web3Client implements EnsClientInterface
{
    private Web3 $web3;
    private Configuration $configuration;

    public function __construct(Configuration $config)
    {
        $this->configuration = $config;
        $this->initializeWeb3();
    }

    /**
     * Executes a transaction call through Web3 and retries if necessary based on the configuration. Returns null if
     * all attempts failed or the node returned empty/0x.
     *
     * @param array $tx The transaction details to be called.
     * @return string|null The result of the transaction call if successful, or null if all attempts fail.
     */
    public function call(array $tx): ?string
    {
        $attempts = 0;
        while ($attempts < $this->configuration->maxRetries) {
            $attempts++;
            $result = null;
            $this->web3->eth->call($tx, 'latest', function ($err, $response) use (&$result) {
                if ($err === null && is_string($response) && $response !== '' && $response !== '0x') {
                    $result = $response;
                }
            });
            if (is_string($result) && $result !== '' && $result !== '0x') {
                return $result;
            }
            usleep($this->getBackoffDelay($attempts));
        }
        return null;
    }

    /**
     * Initializes the Web3 instance with the provided HTTP provider configuration.
     * The provider is set using the RPC URL and timeout values defined in the configuration.
     *
     * @return void
     */
    private function initializeWeb3(): void
    {
        $provider = new HttpProvider($this->configuration->rpcUrl, $this->configuration->timeoutMs);
        $this->web3 = new Web3($provider);
    }

    /**
     * Calculates a backoff delay based on the attempt number, applying exponential backoff
     * with jitter to prevent thundering herd issues.
     *
     * @param int $attempt The number of the current retry attempt (starting from 1).
     * @return int The calculated backoff delay in microseconds, including jitter.
     */
    private function getBackoffDelay(int $attempt): int
    {
        $baseDelay = 100_000;
        $maxJitter = 50_000;
        $delay = $baseDelay * (2 ** ($attempt - 1));
        return $delay + random_int(0, $maxJitter);
    }
}
