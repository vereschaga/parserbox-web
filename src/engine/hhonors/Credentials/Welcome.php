<?php

namespace AwardWallet\Engine\hhonors\Credentials;

class Welcome extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return ["hhonors@h2.hiltonhhonors.com"];
    }

    public function getCredentialsSubject()
    {
        return ["Welcome"];
    }

    public function getParsedFields()
    {
        return ["Login", "Email"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        if ($login = re("#YOUR\s+HHONORS\s+ACCOUNT\s+NUMBER\s+IS\s*:\s+(\d+)#msi", text($this->http->Response['body']))) {
            $result["Login"] = $login;
        }

        return $result;
    }
}
