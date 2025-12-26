<?php

namespace AwardWallet\Engine\macdonaldhotels\Credentials;

class Credentials2 extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return ["emarketing@emarketing.macdonaldhotels.com"];
    }

    public function getCredentialsSubject()
    {
        return ["Don't miss your double points"];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
            "LastName",
            "Number",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result["Login"] = $result["Email"] = $parser->getCleanTo();
        $result["LastName"] = re("#Member Name:\s+M[rsi]+\s+(\w+)#msi", $text);

        if ($Number = re("#Membership Number:\s+(\d+)#ms", $text)) {
            $result["Number"] = $Number;
        }

        return $result;
    }
}
