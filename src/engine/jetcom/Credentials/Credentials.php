<?php

namespace AwardWallet\Engine\jetcom\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "Jet2Reservations@Jet2.Com",
            "jet2reservations@jet2.com",
            "myjet2@jet2mail.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Confirmation of registration with Jet2.com loyalty scheme",
            "Your May myJet2 statement and exclusive member offers",
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

        $result['FirstName'] = beautifulName(nice(re("#Hi\s+(\w+)#ms", $text)));

        if ($name = re("#([A-Z]+\s+[A-Z]+)\s+\d+#ms", $text)) {
            $result['Name'] = beautifulName($name);
        }

        return $result;
    }
}
