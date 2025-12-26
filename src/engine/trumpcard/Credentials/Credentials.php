<?php

namespace AwardWallet\Engine\trumpcard\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'TrumpCard@trumphotels.com',
            'TrumpCard@Contact-Client.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Activate Your New TRUMP CARD Membership Today#i",
            "#Thank you for registering for the Trump Card#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);

        $result["Login"] = $parser->getCleanTo();
        $result["Name"] = re("#(?:^|\n)\s*Dear\s+([^\n,:!.]+)#i", $text);

        return $result;
    }
}
