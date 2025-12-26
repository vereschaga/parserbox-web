<?php

namespace AwardWallet\Engine\mypoints\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "BonusMailReply@mypoints.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($firstName = re('#\s*(.*)\s*,\s+you\s+have\s+[\d\.,\s]+\s+points#i', text($this->http->Response['body']))) {
            $result['FirstName'] = $firstName;
        }

        return $result;
    }
}
