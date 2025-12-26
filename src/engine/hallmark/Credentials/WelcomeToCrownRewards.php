<?php

namespace AwardWallet\Engine\hallmark\Credentials;

class WelcomeToCrownRewards extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'mbrinfo@hallmarkonline.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'welcome to Crown Rewards',
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
        $result['FirstName'] = re('#\s*(.*),\s+thanks\s+for\s+signing\s+up#i', $text);

        return $result;
    }
}
