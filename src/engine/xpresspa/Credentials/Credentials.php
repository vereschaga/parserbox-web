<?php

namespace AwardWallet\Engine\xpresspa\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "adames@xpresspa.com",
            "info@xpresspa.com",
            "customerservice@xpresspa.com",
            "noreply@xpresspa.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            " ",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
