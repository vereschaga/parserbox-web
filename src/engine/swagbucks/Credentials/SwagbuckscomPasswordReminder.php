<?php

namespace AwardWallet\Engine\swagbucks\Credentials;

class SwagbuckscomPasswordReminder extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'info@swagbucks.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Swagbucks.com Password Reminder',
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
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Hi\s+(.*?),\s+I\s+see\s+you\s+forgot\s+your\s+password#i', $text);

        return $result;
    }
}
