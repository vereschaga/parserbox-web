<?php

namespace AwardWallet\Engine\iberia\Credentials;

class LetterFromThePresidentOfIberia extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-5.eml',
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
            'Letter from the President of Iberia',
        ];
    }

    public function getParsedFields()
    {
        return [
            'LastName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['LastName'] = re('#Dear\s+M[rs]+\s+(.*?),#i', $text);

        return $result;
    }
}
