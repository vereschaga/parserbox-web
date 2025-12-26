<?php

namespace AwardWallet\Engine\velocity\Credentials;

class YourVelocityFrequentFlyerStatement extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'velocity@email.virginaustralia.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Your Velocity Frequent Flyer Statement',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'LastName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['LastName'] = re('#\s+M[rs]+\s+(.*)\s+Account\s+details#i', $text);
        $result['Login'] = re('#membership\s+number:\s+(\d+)#i', $text);

        return $result;
    }
}
