<?php

namespace AwardWallet\Engine\pureprofile\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "messages@mails.pureprofile.com",
            "email@messages.pureprofile.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "your next pureprofile survey awaits!",
            "Triple your Pureprofile earnings",
            "Amazon voucher",
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
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($name = re("#Hi\s*,?\s+(\w+)#", $this->text())) {
            $result['FirstName'] = beautifulName($name);
        }

        return $result;
    }
}
