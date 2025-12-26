<?php

namespace AwardWallet\Engine\surveysavvy\Credentials;

class Study181132ParticipateInANew2USDSurvey extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'invite@surveysavvy.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Study 181132 - Participate in a new $2 survey',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = re('#Dear\s+(.*?),#i', $this->text());

        return $result;
    }
}
