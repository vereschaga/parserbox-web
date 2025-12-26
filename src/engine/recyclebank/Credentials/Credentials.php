<?php

namespace AwardWallet\Engine\recyclebank\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'info@email.recyclebank.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "A Gift from our CEO",
            "Welcome to Recyclebank",
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
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = beautifulName(re('#(?:Hi,|Dear)\s+([^\s\.\,]+)#i', $text));

        return $result;
    }
}
