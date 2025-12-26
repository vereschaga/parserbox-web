<?php

namespace AwardWallet\Engine\kellogg\Credentials;

class Offer1 extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
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
            'Kroger Exclusive: SAVE $5! Details Inside.',
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
        $result['FirstName'] = re('#\s+Why\s+Hello\s+There,\s+(.*?)!#i', $this->text());

        return $result;
    }
}
