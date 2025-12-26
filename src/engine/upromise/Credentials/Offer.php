<?php

namespace AwardWallet\Engine\upromise\Credentials;

class Offer extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
        'credentials-3.eml',
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
            '10% cash back & hot rewards in Gifts & Flowers \'til Thursday',
            'Deferred Repayment from Sallie Mae',
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
        $result['FirstName'] = re('#\s+(?:Hi|Dear)\s+(.*?),#i', $text);

        return $result;
    }
}
