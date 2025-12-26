<?php

namespace AwardWallet\Engine\omnihotels\Credentials;

class AccountSummaryAndSpecialOffers extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'enews@omnihotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Account Summary and Special Offers!',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#\s*(.*)\s+Select\s+Guest(?:\s+\w+)?\s+level#i', $text);

        return $result;
    }
}
