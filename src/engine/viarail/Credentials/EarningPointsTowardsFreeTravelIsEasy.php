<?php

namespace AwardWallet\Engine\viarail\Credentials;

class EarningPointsTowardsFreeTravelIsEasy extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
        'credentials-4.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'viapreference@memberservices.viapreference.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'earning points towards free travel is easy',
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
        $result['Name'] = re('#\s*(?:M[rs]+\.?)?\s*(.*?),\s+earning\s+points\s+towards\s+free\s+travel\s+is\s+easy#i', $subject);
        $result['Login'] = re('#Membership\s+no\.:?\s+(\w+)#i', $text);

        return $result;
    }
}
