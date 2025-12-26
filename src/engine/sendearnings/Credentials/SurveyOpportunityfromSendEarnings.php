<?php

namespace AwardWallet\Engine\sendearnings\Credentials;

class SurveyOpportunityfromSendEarnings extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials5.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'support@sendearnings.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Survey Opportunity from SendEarnings',
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['FirstName'] = re('#\s+Hello,\s+(.*?)\s+#i', $text);
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
