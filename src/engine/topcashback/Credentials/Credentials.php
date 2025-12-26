<?php

namespace AwardWallet\Engine\topcashback\Credentials;

class Credentials extends \TAccountChecker
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "support@topcashback.com"',
            'FROM "newsletter@email.topcashback.co.uk"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
