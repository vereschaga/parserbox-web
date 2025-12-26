<?php

namespace AwardWallet\Engine\pampers\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'pampers@mail.pampers.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Special Topic',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($firstName = re('#Hello\s+(.*?),#i', text($this->http->Response['body']))) {
            $result['FirstName'] = $firstName;
        }

        return $result;
    }
}
