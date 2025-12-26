<?php

namespace AwardWallet\Engine\upromise\Credentials;

class YourUpromiseAccountStatement extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-4.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'accountsummary@your.upromise.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'your Upromise Account Statement',
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
        $result['FirstName'] = re('#Account\s+Statement\s+for\s+(.*?)\s+Summary\s+Period#i', $text);

        return $result;
    }
}
