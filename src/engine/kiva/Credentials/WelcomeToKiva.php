<?php

namespace AwardWallet\Engine\kiva\Credentials;

class WelcomeToKiva extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'contactus@kiva.org',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'welcome to Kiva',
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Name',
            'Login',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re('#^(?:Fw:\s*)?(.*?),\s+welcome\s+to\s+Kiva#i', $parser->getSubject());
        $result['Name'] = re('#Welcome\s+(.*?),#i', $this->text());

        return $result;
    }
}
