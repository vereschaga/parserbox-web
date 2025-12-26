<?php

namespace AwardWallet\Engine\upromise\Credentials;

class WelcomeToUpromiseImportantTipsForYourAccount extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'member@your.upromise.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to Upromise!  Important tips for your account',
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['FirstName'] = re('#Welcome\s+to\s+Upromise\s*,\s+(.*?)\.#i', $text);

        return $result;
    }
}
