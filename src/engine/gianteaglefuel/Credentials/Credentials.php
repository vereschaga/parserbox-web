<?php

namespace AwardWallet\Engine\gianteaglefuel\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'gianteagle@email.gianteagle.com',
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
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
