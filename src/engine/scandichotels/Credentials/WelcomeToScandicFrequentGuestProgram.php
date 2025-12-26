<?php

namespace AwardWallet\Engine\scandichotels\Credentials;

class WelcomeToScandicFrequentGuestProgram extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'noreply@scandichotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to Scandic frequent guest program!',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Password',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = re('#Membership\s+number\s+(\d+)#i', $text);
        $result['Password'] = re('#PIN\s+code\s+(\S+)#i', $text);
        $result['Name'] = re('#following\s+address:\s*(.*)\s+\S+@#i', $text);

        return $result;
    }
}
