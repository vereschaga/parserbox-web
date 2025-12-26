<?php

namespace AwardWallet\Engine\omnihotels\Credentials;

class WelcomeToTheManyBenefitsOfSelectGuest extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
        'credentials-4.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'selectguest@omnihotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to the many benefits of Select Guest',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = orval(
            // credentials-1.eml
            re('#my\s+account\s+details\s+(.*)\s+Select\s+Guest.?\s*\w+\s+Level#i', $text),
            // credentials-4.eml
            re('#\s*(.*)\s+Select\s+Guest\s+Member\s*\##i', $text)
        );
        $result['FirstName'] = re('#Dear\s+(.*?)\s*,#i', $text);

        return $result;
    }
}
