<?php

namespace AwardWallet\Engine\preflight\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'PreFlightParking@intpark.com',
            'do-not-reply@intpark.com',
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
