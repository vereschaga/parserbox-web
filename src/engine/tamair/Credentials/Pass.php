<?php

namespace AwardWallet\Engine\tamair\Credentials;

class Pass extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'res_inbc@tam.com.br',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your data to access the TAM web site!",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Password'] = re('#New electronic signature\s*:\s*(\d+)#i', $text);

        return $result;
    }
}
