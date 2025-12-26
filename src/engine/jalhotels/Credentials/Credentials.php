<?php

namespace AwardWallet\Engine\jalhotels\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'myjalhotels@jalhotels.co.jp',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Welcome to My JAL\s*Hotels Program#",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            // 'Login2',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Password'] = clear("#\s#", re("#Password:\s+(.*?)\n#i", $text));

        $result['Login'] = clear("#\s#", trim(re("#Enter your My JAL\s*Hotels \#([\d\s]+)#i", $text)));
        // $result['Login2'] = $parser->getCleanTo();

        return $result;
    }
}
