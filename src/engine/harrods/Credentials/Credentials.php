<?php

namespace AwardWallet\Engine\harrods\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "news@email.harrods.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Harrods#",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        $result['Name'] = re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text);
        $result['Login'] = $parser->getCleanTo();
        $result['Login2'] = re("#(?:^|\n)\s*Account number[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
