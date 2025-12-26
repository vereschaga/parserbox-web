<?php

namespace AwardWallet\ExtensionWorker;

class LoginWithConfNoResult
{

    public ?string $errorMessage;

    private function __construct(?string $errorMessage)
    {
        $this->errorMessage = $errorMessage;
    }

    public static function success() : self
    {
        return new self(null);
    }

    public static function error(string $error) : self
    {
        return new self($error);
    }

    public function isSuccess() : bool
    {
        return $this->errorMessage === null;
    }

}