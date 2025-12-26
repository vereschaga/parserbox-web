<?php

namespace AwardWallet\Engine\globaltestmarket\Credentials;

class ANewSurveyFromGlobalTestMarket extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'frontdesk@globaltestmarket.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'A new survey from GlobalTestMarket',
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
        $result['Email'] = $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Dear\s+(.*?),#i', $this->text());

        return $result;
    }
}
