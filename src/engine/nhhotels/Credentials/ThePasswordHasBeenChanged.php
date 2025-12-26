<?php

namespace AwardWallet\Engine\nhhotels\Credentials;

class ThePasswordHasBeenChanged extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
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
            'The password for the NH&YOURSPACE / NH&YOURAGENCYSPACE account has been changed',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Password',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if (preg_match('#The\s+new\s+password\s+for\s+the\s+user\s+(.*?)\s+is\s+(\S+)#i', $text, $m)) {
            $result['FirstName'] = $m[1];
            $result['Password'] = $m[2];
        }

        return $result;
    }
}
