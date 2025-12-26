<?php

namespace AwardWallet\Engine\papajohns\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "specials@papajohns-specials.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Papa John's Online!",
        ];
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
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#Hello\s+(.*)\s*,#i', text($this->http->Response['body']));

        return $result;
    }
}
