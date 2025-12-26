<?php

namespace AwardWallet\Engine\kroger\Credentials;

class Credentials extends \TAccountChecker
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "no-reply@kroger.com"',
            'FROM "kroger@krogermail.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        return ['Login' => $parser->getCleanTo()];
    }
}
