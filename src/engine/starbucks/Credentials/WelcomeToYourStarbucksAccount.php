<?php

namespace AwardWallet\Engine\starbucks\Credentials;

class WelcomeToYourStarbucksAccount extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'orders@starbucks.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to your Starbucks account',
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
        $result['FirstName'] = re('#Hi\s+(.*?),#i', $text);

        return $result;
    }
}
