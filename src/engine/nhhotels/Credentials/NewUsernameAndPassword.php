<?php

namespace AwardWallet\Engine\nhhotels\Credentials;

class NewUsernameAndPassword extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'nh@nh-hotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'New username and password',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            // 'Password',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        // $result['Password'] = re('#The\s+new\s+password\s+for\s+the\s+user\s+\S+\s+is\s+(\S+)#i', $text);
        return $result;
    }
}
