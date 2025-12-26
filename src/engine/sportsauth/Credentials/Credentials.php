<?php

namespace AwardWallet\Engine\sportsauth\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'SportsAuthority.com@em.sportsauthority.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            '#.*#i',
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
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
