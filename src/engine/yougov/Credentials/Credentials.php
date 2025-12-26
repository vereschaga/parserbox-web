<?php

namespace AwardWallet\Engine\yougov\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'yougov@yougov.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#YouGov survey#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);

        $result["Name"] = trim(re("#(?:^|\n)\s*Dear\s+([^,.\n]+)#i", $text));
        $result["Login"] = $parser->getCleanTo();

        return $result;
    }
}
