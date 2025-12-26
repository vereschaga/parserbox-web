<?php

namespace AwardWallet\Engine\japanair\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "@jal.com",
            "@news.jal.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "JAL Mileage Bank",
        ];
    }

    public function getParsedFields()
    {
        return ["Email"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
