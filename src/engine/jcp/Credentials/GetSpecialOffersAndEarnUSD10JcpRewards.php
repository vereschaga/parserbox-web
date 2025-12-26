<?php

namespace AwardWallet\Engine\jcp\Credentials;

class GetSpecialOffersAndEarnUSD10JcpRewards extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'jcprewards-email@e-jcprewards.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Get special offers & earn $10 jcp rewards!',
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
        $result['FirstName'] = re('#HELLO,\s+(.*?)!#i', $this->text());

        return $result;
    }
}
