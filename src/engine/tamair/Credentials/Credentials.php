<?php

namespace AwardWallet\Engine\tamair\Credentials;

class Credentials extends \TAccountCheckerExtended
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
            "Confirmation of your registration at TAM!",
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
        $result['Login'] = re('#your\s+Fidelidade\s+Card\s+number\s+(\d+)\s+and#i', $text);
        $result['FirstName'] = re('#(?:Congratulations|Dear|Hello|Olá)\s*,\s+(.*?)\s*,#i', $text);

        return $result;
    }
}
