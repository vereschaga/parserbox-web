<?php

namespace AwardWallet\Engine\redrobin\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'redrobin@redrobin.fbmta.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Don\'t Miss Out On A Free Shake!',
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
        return [
            'Login' => $parser->getCleanTo(),
        ];
    }
}
