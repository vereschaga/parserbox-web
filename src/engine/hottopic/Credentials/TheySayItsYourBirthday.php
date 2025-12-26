<?php

namespace AwardWallet\Engine\hottopic\Credentials;

class TheySayItsYourBirthday extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'htplus1@e.hottopic.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'they say it\'s your birthday...',
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
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#(.*),\s+they say it\'s your birthday#i', $parser->getSubject());

        return $result;
    }
}
