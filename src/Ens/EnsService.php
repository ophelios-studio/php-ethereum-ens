<?php namespace Ens;

// Facade class for ENS operations
class EnsService
{
    private Web3Client $client;

    public function __construct(string|Web3ClientInterface $client)
    {
        $this->client = ($client instanceof Web3ClientInterface)
            ? $client
            : new Web3Client(new Configuration(rpcUrl: $client));
    }

    public function resolveEnsName(string $address): ?string
    {
        return new ReverseLookup($this->client)->resolveEnsName($address);
    }

    public function resolveProfile(string $ensName, array $records = ProfileHydrator::DEFAULT_RECORDS): EnsProfile
    {
        $profile = new EnsProfile();
        $hydrator = new ProfileHydrator($this->client, $profile);
        $hydrator->hydrate($ensName, $records);
        return $profile;
    }

    public function resolveAvatar(string $ensName, bool $parentFallback = true): ?string
    {
        $resolver = new Resolver($ensName, $this->client);
        return $resolver->getAvatar($parentFallback);
    }

    public function resolveRecord(string $ensName, string|array $record): ?string
    {
        $resolver = new Resolver($ensName, $this->client);
        return $resolver->getRecord($record);
    }

    public function resolveRecords(string $ensName, array $records): ?array
    {
        $resolver = new Resolver($ensName, $this->client);
        return $resolver->getRecords($records);
    }
}
