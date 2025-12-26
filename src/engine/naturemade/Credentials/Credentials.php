<?php

namespace AwardWallet\Engine\naturemade\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'NatureMadeinfo@naturemade.com',
            'naturemadeinfo@naturemade.com',
            'news@e.naturemade.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to NatureMade.com",
            "30 Day Notice: Don't Let Your Points Expire! Enter Codes Today",
        ];
    }

    public function GetParsedFields()
    {
        return [
            "FirstName",
            "Login",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = orval(
            re("#Welcome\s+(\S+)#ms", $text),
            re("#(\S+), you have \d+ points#ms", $text)
        );

        return $result;
    }
}
