<?php

namespace AwardWallet\Engine\iberia\Credentials;

class IberiaPlusProgrammeTermsAndConditionsChanges extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-4.eml',
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
            'Iberia Plus Programme Terms and Conditions changes',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
            'LastName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        if (preg_match('#\s*(.*)\s+IB\s+(\d+)#i', $text, $m)) {
            $result['Name'] = $m[1];
            $result['Login'] = $m[2];
        }
        $result['LastName'] = re('#Dear\s+M[rs]+\s+(.*?),#i', $text);

        return $result;
    }
}
