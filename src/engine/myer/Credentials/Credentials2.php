<?php

namespace AwardWallet\Engine\myer\Credentials;

class Credentials2 extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return ["myerone.com.au"];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Your \d+ days of MYER one savings starts#",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result["Email"] = $parser->getCleanTo();
        $result["FirstName"] = re("#(?:Hi|Dear)\s+(\w+)#i", $text);

        return $result;
    }
}
