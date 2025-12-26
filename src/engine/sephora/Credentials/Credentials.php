<?php

namespace AwardWallet\Engine\sephora\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "Sephora@shop.sephora.com",
            "CustomerService@cs.sephora.com",
            "sephora@shop.sephora.com",
            "customerservice@cs.sephora.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Looking for sunscreen you actually want to wear?",
            "Welcome Sephora Beauty Insider",
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $firstName = orval(
            // credentials-1.eml
            re('#(\S+),\s+choose#i', $text),
            // credentials-2.eml
            re('#Congratulations,\s+(.*)\s*:#i', $text)
        );

        if ($firstName) {
            $result['FirstName'] = $firstName;
        }

        return $result;
    }
}
