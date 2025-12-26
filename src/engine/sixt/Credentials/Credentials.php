<?php

namespace AwardWallet\Engine\sixt\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'sixt.com@newsletter.sixt.info',
            'no-reply@sixt.de',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Sixt username forgotten?",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result['Login'] = re('#Your\s+user\s+ID\s*:\s+(\S+)#i', $text);
        $result['Name'] = re('#Dear\s+(?:Mr|Ms|Mrs)\.\s+(.*?),#i', $text);

        return $result;
    }
}
