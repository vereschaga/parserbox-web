<?php

namespace AwardWallet\Engine\vueling\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'vueling@news.vueling.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Launch your Punto account',
            'Earn double points with our new destinations',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result['FirstName'] = beautifulName(re("#Hi\s+(\w+)#", $text));

        return $result;
    }
}
