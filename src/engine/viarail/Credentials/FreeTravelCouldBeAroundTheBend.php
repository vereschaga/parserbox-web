<?php

namespace AwardWallet\Engine\viarail\Credentials;

class FreeTravelCouldBeAroundTheBend extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
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
            'free travel could be around the bend',
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
        $result['Name'] = re('#\s*(.*),\s+Welcome\s+to\s+the\s+Préférence\s+level#i', $text);
        $result['Login'] = re('#Membership\s+no\.:?\s+(\w+)#i', $text);

        return $result;
    }
}
