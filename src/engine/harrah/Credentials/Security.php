<?php

namespace AwardWallet\Engine\harrah\Credentials;

class Security extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'emails@em.harrahs-marketing.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your Total Rewards Account's Security Profile",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Login'] = re('#Your\s+user\s+name\s+is\s+([^\.]+)#i', $text);
        $result['Name'] = beautifulName(str_replace(',', '', re('#Dear\s+(.*?,\s+.*)?,#i', $text)));

        return $result;
    }
}
