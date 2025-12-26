<?php

namespace AwardWallet\Engine\panera\Credentials;

class Logging extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'donotreply@panerabread.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Need help logging in?',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'FirstName',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($firstName = re('#Hello\s+(.+?)\s*,#i', $text)) {
            $result['FirstName'] = $firstName;
        }

        if ($login = re('#Your\s+username\s+is\s*:\s*(\S+)#i', $text)) {
            $result['Login'] = $login;
        }

        return $result;
    }
}
