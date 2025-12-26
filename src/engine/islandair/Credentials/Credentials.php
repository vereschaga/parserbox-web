<?php

namespace AwardWallet\Engine\islandair\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'internet@islandair.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Island Air Online#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);

        $result["Login"] = clear("#\s#", re("#Your member\s*number is[:\s]+([^\n,]+)#i", $text));

        return $result;
    }
}
