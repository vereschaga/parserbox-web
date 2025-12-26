<?php

namespace AwardWallet\Engine\tripalertz\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'memberservice@tripalertz.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to TripAlertz.com',
            'Please Activate Your Account to Start Earning TripAlertz Trip Cash',
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Login"] = $result["Email"] = orval(
            $parser->getCleanTo(),
            re("#[a-zA-Z0-9_\-.]+@[a-zA-Z0-9\-]+\.[a-zA-Z0-9\-.]+#", $parser->getHeader('x-apparently-to'))
        );

        return $result;
    }
}
