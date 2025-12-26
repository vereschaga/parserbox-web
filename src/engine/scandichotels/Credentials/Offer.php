<?php

namespace AwardWallet\Engine\scandichotels\Credentials;

class Offer extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'no-reply@scandichotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Stay 3 nights pay for 2',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['FirstName'] = re('#Hi\s+(.*?)!\s+You\s+have#i', $text);
        $result['Name'] = re('#Name:\s+(.*)#i', $text);
        $result['Login'] = re('#Membership\s+number:\s+(.*)#i', $text);

        return $result;
    }
}
