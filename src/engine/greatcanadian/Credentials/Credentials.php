<?php

namespace AwardWallet\Engine\greatcanadian\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return ["membercare@GreatCanadianRebates.ca"];
    }

    public function getCredentialsSubject()
    {
        return ["Welcome to GreatCanadianRebates.ca"];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Login"] = $result["Email"] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result["FirstName"] = re("#Greetings\s+([^,]+),#", $text);

        return $result;
    }
}
