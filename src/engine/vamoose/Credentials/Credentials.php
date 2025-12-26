<?php

namespace AwardWallet\Engine\vamoose\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'reservations@vamoosebus.com',
            'support@vamoosebus.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Welcome to Vamoose Bus#i",
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

        $result["Name"] = trim(re("#(?:^|\n)\s*(?:Dear|Hello)\s+([^,.\n]+)#i", $text));
        $result["Login"] = re("#Your (?:login|user\s*name) is[:\s]+([^\n,]+)#i", $text);

        return $result;
    }
}
