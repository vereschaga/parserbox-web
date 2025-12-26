<?php

namespace AwardWallet\Engine\gymboree\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'gymboree@gymboree.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Gymboree#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
