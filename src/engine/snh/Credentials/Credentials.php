<?php

namespace AwardWallet\Engine\snh\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'customerservice@greenpoints.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#S&H greenpoints#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
            "Number",
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);

        $result["Login"] = $parser->getCleanTo();
        $result["Name"] = re("#(?:^|\n)\s*Dear\s+([^\n,:]+)#i", $text);
        $result["Password"] = re("#Your password is[:\s]+([^\s]+)#i", $text);
        $result["Number"] = re("#Your S&H Member[\s\#]+is[\#:\s]+([^\s]+)#i", $text);

        return $result;
    }
}
