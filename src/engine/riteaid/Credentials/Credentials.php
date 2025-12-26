<?php

namespace AwardWallet\Engine\riteaid\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "inquiry@riteaid.com",
            "riteaid@email.riteaid.com",
            "riteaid@email2.riteaid.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to My Rite Aid",
            "Welcome to wellness+ online!",
        ];
    }

    public function getParsedFields()
    {
        return ["Name"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#\s*(.*),\s+(You are receiving this message because|Welcome to)#ms', text($this->http->Response['body']));

        return $result;
    }
}
