<?php

namespace AwardWallet\Engine\petcare\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'email@mail.petcarerx.com',
            'email@petmail.petcarerx.com',
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
            "Login",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getHeader('subject');
        $result['FirstName'] = re('#^(\w+)\s*,.+#i', $subject);

        return $result;
    }
}
