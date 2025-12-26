<?php

namespace AwardWallet\Engine\qdoba\Credentials;

class Credentials extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return [
            "@marketing.qdoba.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Happy Birthday",
            "A very important message from your local Qdoba",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result['FirstName'] = re("#\s*([^\s,]+),\s+(?:We want to help you|We're sorry to announce)#ms", $text);

        return $result;
    }
}
