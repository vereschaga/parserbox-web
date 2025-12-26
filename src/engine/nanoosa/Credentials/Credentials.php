<?php

namespace AwardWallet\Engine\nanoosa\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "customerservice@nanoosa.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to cashback site!",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Login',
            'FirstName',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re("#Dear\s+(\w+)#i", $text);
        $result['Password'] = re("#Password\s*:\s*(\S+)#i", $text);

        return $result;
    }
}
