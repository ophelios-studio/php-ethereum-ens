<?php namespace Ens;

use Web3\Web3;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;

class Web3Client implements EnsClientInterface
{
    private Web3 $web3;

    public function __construct(private readonly Configuration $config)
    {
        $this->web3 = new Web3(new HttpProvider(new HttpRequestManager($config->rpcUrl, $config->timeoutMs)));
    }

    public function call(array $tx): ?string
    {
        $result = null;
        $this->web3->eth->call($tx, 'latest', function ($err, $response) use (&$result) {
            if ($err === null && !empty($response) && $response !== '0x') {
                $result = $response;
            }
        });
        return $result;
    }
}
