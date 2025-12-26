<?php

namespace AwardWallet\Engine\starbucks\Credentials;

class StarbucksWiFiRegistration extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'register@starbucks.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Starbucks WiFi Registration',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login!',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login!'] = re('#For\s+future\s+reference,\s+your\s+username\s+is\s+(\S+?)\.#i', $text);

        return $result;
    }
}
