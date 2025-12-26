<?php

namespace AwardWallet\Engine\trident\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "tridentprivilege@tridenthotels.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Trident Privilege Points Summary",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        $result['Email'] = $parser->getCleanTo();

        $result['Name'] = orval(
            re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text),
            nice(re("#\n\s*Name\s*:\s*(.*?)\s+Membership#is", $text))
        );

        $result['Login'] = orval(
            //$this->http->FindSingleNode("//text()[normalize-space(.) = 'Membership Number']/ancestor::tr[1]/following::tr[1]/td[1]", null, true, "#^\d+$#"),
            re("#\n\s*Closing Balance\s*\n\s*(\d+)\n#ix", $text),
            re("#\n\s*Membership Number\s*:\s*([^\s]+)#ix", $text),
            re("#\n\s*Login ID\s*:\s*([^\s]+)#ix", $text)
        );

        return $result;
    }
}
