<?php

namespace AwardWallet\Engine\hhonors\Credentials;

class Statement extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return ["hhonors@h1.hiltonhhonors.com"];
    }

    public function getCredentialsSubject()
    {
        return ["/./"];
    }

    public function getParsedFields()
    {
        return ["Login", "FirstName", "Email"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        $result["FirstName"] = beautifulName($this->http->FindSingleNode("//font[. = 'Hello:']/following-sibling::font[1]"));
        $result["Login"] = re("#Account\s*:\s*(\d+)#", text($this->http->Response['body']));

        return $result;
    }
}
