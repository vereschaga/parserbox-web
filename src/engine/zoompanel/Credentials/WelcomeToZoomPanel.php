<?php

namespace AwardWallet\Engine\zoompanel\Credentials;

class WelcomeToZoomPanel extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'survey@zoompanel.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to ZoomPanel!',
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
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Welcome\s+to\s+ZoomPanel,\s+(.*?)!#i', $this->text());

        return $result;
    }
}
