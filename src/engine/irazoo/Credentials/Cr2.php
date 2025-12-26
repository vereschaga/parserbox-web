<?php

namespace AwardWallet\Engine\irazoo\Credentials;

class Cr2 extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials2.eml',
        'Credentials3.eml',
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
            'New iRazoo Treasure Code',
            'iRazoo Bonus Points',
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
        $result['Login'] = re('#\s*Name\s*:\s*(.+)#i', $this->text());

        return $result;
    }
}
