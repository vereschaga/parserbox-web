<?php

namespace AwardWallet\Engine\rentacar\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "enterpriseplus@enterprise.com",
            "EnterprisePlus@specials.enterprise.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Enterprise Plus#i",
            "#now activated#i",
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
        $result = [];

        if (preg_match('#\s*(.*)\s+Member\s*\#\s*:\s*(\w+)#', text($this->http->Response['body']), $m)) {
            $result['Name'] = $m[1];
            $result['Login'] = $m[2];
        }

        return $result;
    }
}
