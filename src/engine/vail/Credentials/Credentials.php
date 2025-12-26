<?php

namespace AwardWallet\Engine\vail\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "store@vailresorts.com",
            "comments@vailresorts.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Still shopping for a season pass?",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Login"] = $result["Email"] = $parser->getCleanTo();

        return $result;
    }
}
