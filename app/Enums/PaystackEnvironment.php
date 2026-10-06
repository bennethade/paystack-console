<?php

namespace App\Enums;

enum PaystackEnvironment: string
{
    case Test = 'test';
    case Live = 'live';

    public function label(): string
    {
        return config("paystack.environments.{$this->value}.label", ucfirst($this->value));
    }

    public function isLive(): bool
    {
        return $this === self::Live;
    }

    public function secretPrefix(): string
    {
        return config("paystack.environments.{$this->value}.secret_prefix");
    }

    public function publicPrefix(): string
    {
        return config("paystack.environments.{$this->value}.public_prefix");
    }
}
