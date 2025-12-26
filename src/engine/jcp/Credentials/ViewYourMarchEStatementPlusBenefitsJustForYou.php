<?php

namespace AwardWallet\Engine\jcp\Credentials;

class ViewYourMarchEStatementPlusBenefitsJustForYou extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'jcprewards-email@jcprewards.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'View your March e-Statement + benefits just for you',
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
        $result['FirstName'] = re('#\s+Hi,\s+(.*?)!#i', $this->text());

        return $result;
    }
}
