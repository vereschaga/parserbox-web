<?php

namespace AwardWallet\Engine\pepboys\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'pepboys@email.pepboys.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Pep Boys Online Account Verification",
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
        $result['Email'] = $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re("#Dear\s+([^,]+),#", $text);

        return $result;
    }
}
