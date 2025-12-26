<?php

namespace AwardWallet\Engine\iberia\Credentials;

class NowEvenMoreAvios extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-3.eml',
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
            'Now, even more Avios!',
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

        if (preg_match('#\s*(.*)\s+IB\s+(\d+)#i', $text, $m)) {
            $result['Name'] = $m[1];
            $result['Login'] = $m[2];
        }

        return $result;
    }
}
