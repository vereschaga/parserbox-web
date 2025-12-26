<?php

namespace AwardWallet\Engine\royalcaribbean\Credentials;

class Start extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'royalwebsupport@rccl.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Start exploring with your My Cruises account",
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = $parser->getBody();
        $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Dear\s+(.*?)\s*,#i', $text);

        return $result;
    }
}
