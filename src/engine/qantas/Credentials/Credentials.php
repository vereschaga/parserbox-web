<?php

namespace AwardWallet\Engine\qantas\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "frequent_flyer@qantas.com.au",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Qantas Frequent Flyer",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $lastName = re('#(?:Dear)\s+M[rs]+\s+(.*?)\s*,#i', $text);
        $result['Name'] = $lastName;

        $Login = re("#Qantas\s+Frequent\s+Flyer\s+number\s*:\s*(\d+)#i", $text);

        if ($Login) {
            $result['Login'] = $Login;
        }

        return $result;
    }
}
