<?php

namespace AwardWallet\Engine\viarail\Credentials;

class EarnPointsToTravelFree extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-3.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'via@ms.memberservices.viapreference.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'earn points to travel free',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Name'] = re('#^(?:Fw:\s+)?(.*?),\s+earn\s+points\s+to\s+travel\s+free#i', $subject);
        $result['Login'] = re('#Membership\s+no\.:?\s+(\w+)#i', $text);

        return $result;
    }
}
