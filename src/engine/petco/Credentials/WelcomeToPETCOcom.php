<?php

namespace AwardWallet\Engine\petco\Credentials;

class WelcomeToPETCOcom extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'news@emailservice.petco.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to PETCO.com',
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
        $subject = $parser->getSubject();
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Welcome\s+to\s+PETCO\.com\s*,\s+(.*)!#i', $subject);

        return $result;
    }
}
