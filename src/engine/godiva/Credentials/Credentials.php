<?php

namespace AwardWallet\Engine\godiva\Credentials;

class Credentials extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return ["godiva@email.godiva.com"];
    }

    public function getCredentialsSubject()
    {
        return [" "];
    }

    public function getParsedFields()
    {
        return [
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result["Login"] = $result["Email"] = $parser->getCleanTo();

        return $result;
    }
}
