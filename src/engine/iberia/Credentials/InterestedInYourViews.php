<?php

namespace AwardWallet\Engine\iberia\Credentials;

class InterestedInYourViews extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'iberiaplus@iberiaplus.iberia.es',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Interested in your views',
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
        $text = text($this->http->Response['body']);
        $result = [];

        if (preg_match('#\s*(.*)\s+Card\s+Number\s+(\d+)#i', $text, $m)) {
            $result['Name'] = $m[1];
            $result['Login'] = $m[2];
        }

        return $result;
    }
}
