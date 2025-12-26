<?php

namespace AwardWallet\Engine\ulta\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "U-Mail@ulta.com",
            "u-mail@ulta.com",
            "service@ecom.ulta.com",
            "EcomEmail@ulta.com",
            "ecomemail@ulta.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [" "];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
