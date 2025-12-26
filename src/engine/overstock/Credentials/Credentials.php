<?php

namespace AwardWallet\Engine\overstock\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'Sale@Sales.Overstock.com',
            'info@overstock.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            " ",
        ];
    }

    public function GetParsedFields()
    {
        return [
            "Email",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Email'] = $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
