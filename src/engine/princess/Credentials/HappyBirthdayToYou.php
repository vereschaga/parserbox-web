<?php

namespace AwardWallet\Engine\princess\Credentials;

class HappyBirthdayToYou extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'princesscruises@email.princess.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Happy Birthday to you',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Happy\s+Birthday\s+to\s+you\s*,\s+(.*?)!#i', $parser->getSubject());

        return $result;
    }
}
