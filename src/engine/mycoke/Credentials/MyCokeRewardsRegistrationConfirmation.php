<?php

namespace AwardWallet\Engine\mycoke\Credentials;

class MyCokeRewardsRegistrationConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'mycokerewards@mycokerewards.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'My Coke Rewards Registration Confirmation',
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
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Hi\s+(.*?),\s+WELCOME#i', $text);

        return $result;
    }
}
