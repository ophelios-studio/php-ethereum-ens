<?php namespace Ens;

interface EnsResolverInterface
{
    /**
     * Resolve by address or name.
     * Optionally provide a list of ENS text record keys to fetch. If null, uses EnsResolver::DEFAULT_RECORDS.
     */
    public function fetchProfile(string $addressOrName, ?array $records = null): EnsProfile;
}
