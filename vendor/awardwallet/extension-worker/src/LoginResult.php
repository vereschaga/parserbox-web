<?php

namespace AwardWallet\ExtensionWorker;

class LoginResult
{

    public bool $success;
    public ?string $error;
    public ?string $question = null;
    public ?int $errorCode;

    public function __construct(bool $success, ?string $error = null, ?string $question = null, ?int $errorCode = null)
    {
        $this->success = $success;
        $this->error = $error;
        $this->question = $question;
        $this->errorCode = $errorCode;
    }

    public static function invalidPassword(string $error) : self
    {
        return new self(false, $error, null, ACCOUNT_INVALID_PASSWORD);
    }

    public static function providerError(string $error) : self
    {
        return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
    }

    public static function question(string $question, string $error = null) : self
    {
        return new self(false, $error, $question, ACCOUNT_QUESTION);
    }

    public static function captchaNotSolved() : self
    {
        return new self(false, 'Please try to update your account again and make sure that you manually solve the CAPTCHA on the provider’s tab', null, ACCOUNT_PROVIDER_ERROR);
    }

    public static function identifyComputer() : self
    {
        return new self(false, 'It seems that %DISPLAY_NAME% needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your %SHORT_NAME% authentication options) to get this computer authorized.', null, ACCOUNT_PROVIDER_ERROR);
    }

    public static function success() : self
    {
        return new self(true);
    }

    public static function lockout(string $error) : self
    {
        return new self(false, $error, null, ACCOUNT_LOCKOUT);
    }

}
