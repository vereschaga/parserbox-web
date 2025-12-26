<?php

namespace AwardWallet\Engine\srilankan\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'flysmilesupdates@srilankan.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to FlySmiles",
            "FlySmiLes News and Updates",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);
        $result["Login"] = re("#Your\s+FlySmiLes\s+Membership\s+Number\D+(\d+)#", $text);
        $result["Name"] = beautifulName(re("#Dear\s+M[rsi]+\s+(\w+\s+\w+)#", $text));

        return $result;
    }
}
