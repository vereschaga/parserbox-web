<?php

namespace AwardWallet\Engine\myer\Credentials;

class Credentials extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return ["myerone.com.au"];
    }

    public function getCredentialsSubject()
    {
        return [
            "MYER one Registration Confirmation",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result["Email"] = $parser->getCleanTo();
        $result["Login"] = preg_replace("#\s+#", "", re("#Your\s+MYER\s+one\s+Member\s+number\s+is\s+([\d\s]+)#i", $text));
        $result["FirstName"] = re("#(?:Hi|Dear)\s+(\w+)#i", $text);

        return $result;
    }
}
