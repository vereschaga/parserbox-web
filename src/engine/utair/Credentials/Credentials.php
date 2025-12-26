<?php

namespace AwardWallet\Engine\utair\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "status.info@utair.ru",
            "status@utair-status.ru",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Account Activation#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        $result['Name'] = re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text);
        $result['Email'] = $parser->getCleanTo();
        //$result['Login'] = re("#(?:^|\n)\s*Your Username is[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
