<?php

namespace AwardWallet\Engine\milleniumnclc\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'enquiry@mcgloballoyaltyclub.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#M&C Loyalty#",
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Login',
            'Number',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['FirstName'] = re("#(?:^|\n)\s*Dear\s+M[rsi]+\.?\s+([^\n,]+)#i", $text);
        $result['Number'] = re("#(?:^|\n)\s*Member ID[:\s]+([^\s]+)#ix", $text);
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
