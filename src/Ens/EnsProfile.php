<?php namespace Ens;

class EnsProfile
{
    public function __construct(
        public ?string $name = null,
        public ?string $address = null,
        public ?string $avatar = null,
        public ?string $twitter = null,
        public ?string $github = null,
        public ?string $url = null,
        public ?string $email = null,
        public ?string $description = null,
        public ?string $discord = null,
        public ?string $telegram = null,
        public ?string $reddit = null,
        public ?string $linkedin = null,
        // Generic bucket for any fetched text records (ensKey => value)
        public array $texts = []
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address,
            'avatar' => $this->avatar,
            'twitter' => $this->twitter,
            'github' => $this->github,
            'url' => $this->url,
            'email' => $this->email,
            'description' => $this->description,
            'discord' => $this->discord,
            'telegram' => $this->telegram,
            'reddit' => $this->reddit,
            'linkedin' => $this->linkedin,
            'texts' => $this->texts,
        ];
    }
}
