<?php

namespace AwardWallet\Engine\hoopla\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "support@hoopladoopla.com",
            "Hoopla_Doopla_Inc@mail.vresp.com",
            "Hoopla_Doopla@mail.vresp.com",
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
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
