<?php

namespace AwardWallet\Engine\hostelworld\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "help@hostelworld.com",
            "hostelworld@bmail.hostelworld.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "come meet the world",
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
        $result["Login"] = $result["Email"] = $parser->getCleanTo();

        if ($FirstName = re("#^(\w+), come meet the world#i", $parser->getSubject())) {
            $result['FirstName'] = $FirstName;
        } elseif ($FirstName = re("#Hi\s+(\w+),#", $text)) {
            $result['FirstName'] = $FirstName;
        }

        return $result;
    }
}
