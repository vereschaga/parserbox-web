<?php

namespace AwardWallet\Engine\jordanian\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'royalplus@rj.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Welcome to Royal Jordanian Royal Plus#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Name'] = re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text);
        $result['Login'] = re("#(?:^|\n)\s*Your membership number is[:\s]+([^\s]+)#ix", $text);
        $result['Password'] = re("#Pin Code is (\d+)#ix", $text);

        return $result;
    }
}
