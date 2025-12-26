<?php

namespace AwardWallet\Engine\hollandamerica\Credentials;

class WelcomeToYourHollandAmericaLineAccount extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'no-reply@hollandamerica.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to your Holland America Line account',
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
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#Welcome\s+(.*?),#i', $text);

        return $result;
    }
}
