<?php

namespace AwardWallet\Engine\macdonaldhotels\Credentials;

class Credentials extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return ["noreply@em.mail.macdonaldhotels.co.uk"];
    }

    public function getCredentialsSubject()
    {
        return ["#Welcome to your.*Club Statement#"];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result["Login"] = $result["Email"] = $parser->getCleanTo();
        $result["Name"] = re("#(\w+\s+\w+)\s+Club Member#msi", $text);

        return $result;
    }
}
