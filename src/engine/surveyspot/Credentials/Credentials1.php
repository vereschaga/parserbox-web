<?php

namespace AwardWallet\Engine\surveyspot\Credentials;

class Credentials1 extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials1.eml',
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
            'Welcome to SurveySpot',
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
        $result['FirstName'] = re('#Welcome\s+(.+)#i', $this->text());

        return $result;
    }
}
