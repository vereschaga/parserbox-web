<?php

namespace AwardWallet\Engine\macy\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "alerts@opsemail.macys.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($firstName = re('#Dear\s+(.*)\s*,#i', text($this->http->Response['body']))) {
            $result['FirstName'] = $firstName;
        }

        return $result;
    }
}
