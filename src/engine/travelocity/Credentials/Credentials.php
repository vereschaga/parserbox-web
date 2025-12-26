<?php

namespace AwardWallet\Engine\travelocity\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'memberservices@travelocity.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Travelocity",
        ];
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
        $result['Login'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result['FirstName'] = beautifulName(re('#\s*(.*)\s*,\s+We\'re\s+so\s+glad#i', $text));

        return $result;
    }
}
