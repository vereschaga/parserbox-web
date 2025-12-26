<?php

namespace AwardWallet\Engine\irazoo\Credentials;

class Pw extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Password.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'admin@iRazoo-inc.com',
            'admin@irazoo-inc.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Do not reply, Your Username and Password',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Password',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Password'] = re('#\bpassword\s+is\s+(.+?)\s+for\s+the\s+username\s+(.+?)\.#i', $this->text());
        $result['Login'] = re(2);

        return $result;
    }
}
