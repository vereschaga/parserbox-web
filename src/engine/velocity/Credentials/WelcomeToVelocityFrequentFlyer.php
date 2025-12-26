<?php

namespace AwardWallet\Engine\velocity\Credentials;

class WelcomeToVelocityFrequentFlyer extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'velocity@velocityrewards.com.au',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to Velocity Frequent Flyer',
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
        $result['LastName'] = re('#Dear\s+M[rs]+\s+(.*)\s*,#i', $text);
        $result['Login'] = re('#membership\s+number:\s+(\d+)#i', $text);

        return $result;
    }
}
