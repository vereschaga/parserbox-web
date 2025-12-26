<?php

namespace AwardWallet\Engine\jcp\Credentials;

class RewardingYouBetYourShoppingBagItIs extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-3.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'jcpenneyrewards-email@e-jcprewards.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Rewarding? You bet your shopping bag it is!',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#\s+HELLO,\s+(.*?)!#i', $this->text());

        return $result;
    }
}
