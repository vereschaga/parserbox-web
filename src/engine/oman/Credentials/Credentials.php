<?php

namespace AwardWallet\Engine\oman\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'sindbadffpd@omanair.com',
            'sindbadnewsletter@omanair.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Sindbad#i",
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
        $result = [];

        $result['Name'] = re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text);

        if (re("#(?:user|member)#", $result['Name'])) {
            $result['Name'] = null;
        }

        if (!$result['Name']) {
            $result['Name'] = re("#\n\s*([^\n]+)\s*\n\s*Sindbad No\s*:#ix", $text);
        }

        $result['Login'] = orval(
            re("#Membership number[:\s]+([^\s]+)#ix", $text),
            re("#Sindbad No[:\s]+([^\s]+)#ix", $text),
            re("#Sindbad number is[:\s]+([^\s]+)#ix", $text)
        );

        $result['Password'] = re("#Area Password[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
