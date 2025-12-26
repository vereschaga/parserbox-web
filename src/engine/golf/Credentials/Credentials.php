<?php

namespace AwardWallet\Engine\golf\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return ["golfgalaxy@email.golfgalaxy.com"];
    }

    public function getCredentialsSubject()
    {
        return [" "];
    }

    public function getParsedFields()
    {
        return [
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Login"] = $result["Email"] = $parser->getCleanTo();

        return $result;
    }
}
