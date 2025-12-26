<?php

namespace AwardWallet\Engine\thon\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'thonmember@e.thonhotels.no',
            'thonmember@posten.no',
            'thonmember@bringcrm.no',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Thon Hotels#i",
            "#Thon Member#i",
            "#Remember to join#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);

        $result["Name"] = orval(
            nice(re("#<[^>]+>Dear(?:&nbsp;|\s)+([^<,]+)#is", $html)),
            re([
                "#(?:^|\n)\s*(?:Dear)\s+([^\n,]+)#",
                "#\n\s*([^\n]+)\s*\n\s*Member\s*number:\s*#i",
            ], $text)
        );

        $result["Login"] = re([
            "#\n\s*Member\s+Number\s*:\s*([A-Z\d-]+)#i",
            "#\n\s*Username\s*:\s*([A-Z\d-]+)#i",
            "#\n\s*Medlemsnummer\s*:\s*([A-Z\d-]+)#i",
        ], $text);

        $result['Password'] = re("#\n\s*Password\s*:\s*([^\s]+)#", $text);

        return $result;
    }
}
