<?php namespace Ens;

use Web3\Web3;
use Web3\Providers\HttpProvider;

class Web3Client implements EnsClientInterface
{
    private readonly Web3 $web3;

    public function __construct(Configuration $config)
    {
        $this->web3 = new Web3(new HttpProvider($config->rpcUrl, $config->timeoutMs));
    }

    public function call(array $tx): ?string
    {
        $attempts = 0;
        while ($attempts < 3) {
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
            // Retry backoff with jitter 50–250ms
            $jitterUs = random_int(100_000, 250_000);
            usleep($jitterUs);
        }
        // Return null if all attempts failed or the node returned empty/0x
        return null;
    }
}
