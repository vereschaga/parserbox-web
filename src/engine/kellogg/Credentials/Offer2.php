<?php

namespace AwardWallet\Engine\kellogg\Credentials;

class Offer2 extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'no-reply@em.kelloggs.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Kroger Shoppers: 1 FREE Month of HULUPLUS & Instant Win! Details Inside.',
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
        $result['FirstName'] = re('#\s+Hello,\s+(.*?)\s+[\d,.]+\s+points\s+as\s+of#i', $this->text());

        return $result;
    }
}
