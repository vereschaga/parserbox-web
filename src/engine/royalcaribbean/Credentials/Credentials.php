<?php

namespace AwardWallet\Engine\royalcaribbean\Credentials;

class Credentials extends \TAccountCheckerExtended
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
            "Your My Cruises UserName and Password",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Password',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($parser->getBody());
        $result['Email'] = $parser->getCleanTo();
        $result['Login'] = re('#Username\s*:\s+(\S+)#i', $text);
        $result['FirstName'] = re('#Dear\s+(.*?)\s*,#i', $text);
        $result['Password'] = re('#Password\s*:\s+(\S+)#i', $text);

        return $result;
    }
}
