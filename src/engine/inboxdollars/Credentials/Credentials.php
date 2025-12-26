<?php

namespace AwardWallet\Engine\inboxdollars\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'paidemail@inboxdollars.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Get Free Shipping & Free",
            "PaidEmail from InboxDollars",
            "Your InboxDollars Account Information",
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Login',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Email'] = $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Hello\s+(\w+),#i', $text);

        return $result;
    }
}
