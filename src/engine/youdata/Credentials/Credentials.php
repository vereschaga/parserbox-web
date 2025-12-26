<?php

namespace AwardWallet\Engine\youdata\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return ["activation@youdata.com"];
    }

    public function getCredentialsSubject()
    {
        return [" "];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Email"] = $parser->getCleanTo();
        $result["Login"] = re("#Dear\s+(\S+)#", $this->text());

        return $result;
    }
}
