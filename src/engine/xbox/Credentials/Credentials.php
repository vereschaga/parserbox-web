<?php

namespace AwardWallet\Engine\xbox\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'Xbox@engage.xbox.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            '#.*#',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Name'] = orval(
            re("#Hello\s+(\S+)#i", $this->text()),
            re("#Dear\s+([^\s:]+)#i", $this->text())
        );

        return $result;
    }
}
