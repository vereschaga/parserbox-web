<?php

namespace AwardWallet\Engine\surveyspot\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'surveyspot@surveyspot.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Exciting New Survey Awaits!',
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
        $result['FirstName'] = re('#Dear\s+(.+)\s*,#i', $this->text());

        return $result;
    }
}
