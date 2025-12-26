<?php

namespace AwardWallet\Engine\sunshine\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "info@sunshinerewards.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Sunshine Rewards!",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
