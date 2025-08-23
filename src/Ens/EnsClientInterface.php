<?php namespace Ens;

interface EnsClientInterface
{
    /**
     * Performs an eth_call with given parameters on latest block.
     * Returns the raw hex response string or null.
     */
    public function call(array $tx): ?string;
}
