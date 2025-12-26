<?php

namespace AwardWallet\Engine\isay\Credentials;

class NewISaySurvey extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'questions@i-say.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'New i-Say Survey (1303463501005)',
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
        $result['FirstName'] = re('#Hi\s+(.*?),#i', $this->text());

        return $result;
    }
}
