<?php

namespace AwardWallet\Engine\staples\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "staples@easy.staples.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Your \S[\d\.]+ in rewards will expire soon#",
            "NEW! Easier access to your benefits.",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
