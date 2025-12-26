<?php

namespace AwardWallet\Engine\legovip\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'account@LEGO.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Your LEGO ID username#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Login'] = re("#ignore this email message[\.\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
