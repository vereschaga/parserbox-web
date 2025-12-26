<?php

namespace AwardWallet\Engine\onepoll\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "no-reply@onepoll.com",
            "research@e.onepoll.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Thank you for signing up to Onepoll",
            "love to have you back on our OnePoll panel",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $result['Login'] = $parser->getCleanTo();

        if ($name = re("#Hello\s+(\w+\s+\w+)#", $this->text())) {
            $result['Name'] = beautifulName($name);
        } elseif ($name = re("#Hello\s+(\w+)#", $this->text())) {
            $result['Name'] = beautifulName($name);
        }

        return $result;
    }
}
