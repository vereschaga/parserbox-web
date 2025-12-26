<?php

namespace AwardWallet\Engine\saveup\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'noreply@saveup.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "SaveUp",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($parser->getBody());
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = beautifulName(re("#Hi,\s+(\w+)#i", $text));

        return $result;
    }
}
