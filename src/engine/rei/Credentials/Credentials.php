<?php

namespace AwardWallet\Engine\rei\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "customer-service@rei.com",
            "rei@notices.rei.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "REI Customer Service: Your Account is Now Set Up",
            "Your New REI Online Account",
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
        $result['Login'] = $parser->getCleanTo();

        if ($s = re('#(?:Greetings|Dear)\s+(.*?)\s*,#i', text($this->http->Response['body']))) {
            $result['FirstName'] = $s;
        }

        return $result;
    }
}
