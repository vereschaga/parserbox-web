<?php

namespace AwardWallet\Engine\irazoo\Credentials;

class Cr1 extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials1.eml',
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
            'Welcome to iRazoo',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = re('#Welcome\s+,?\s*(.+)#i', $this->text());

        return $result;
    }
}
