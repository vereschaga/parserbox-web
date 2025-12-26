<?php

namespace AwardWallet\Engine\superdrug\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "Support@superdrug.com",
            "Offers@mail.superdrug.com",
            "support@superdrug.com",
            "offers@mail.superdrug.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your Superdrug registration",
            "New year, new you",
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
        $text = text($this->http->Response['body']);
        $result = [];
        $result['Email'] = $result['Login'] = $parser->getCleanTo();

        if ($name = orval(
            re("#Hi\s+(\w+)#", $text),
            re("#still\s+on\s+for\s+(\w+)#", $text)
        )) {
            $result['FirstName'] = beautifulName($name);
        }

        return $result;
    }
}
