<?php

namespace AwardWallet\Engine\surveyspree\Credentials;

class WelcomeToSurveySpreeByOpinionPlace extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'support@surveyhelpcenter.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to SurveySpree by Opinion Place',
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['FirstName'] = re('#\s+Hello\s+(.*?)\s+Thank\s+you\s+for\s+regist#i', $text);
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
