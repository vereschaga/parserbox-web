<?php

namespace AwardWallet\Engine\westmarine\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'wmcustomerservice@westmarine.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Reset Instructions#i",
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
