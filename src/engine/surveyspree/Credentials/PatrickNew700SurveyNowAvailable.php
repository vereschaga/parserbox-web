<?php

namespace AwardWallet\Engine\surveyspree\Credentials;

class PatrickNew700SurveyNowAvailable extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials2.eml',
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
            'Survey Now Available',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'FirstName',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['FirstName'] = reni('^ (.+?) -', $subject);
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
