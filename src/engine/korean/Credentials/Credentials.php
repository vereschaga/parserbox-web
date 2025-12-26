<?php

namespace AwardWallet\Engine\korean\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsSubject()
    {
        return [
            "#SKYPASS#i",
            "#Korean Air#i",
        ];
    }

    public function getCredentialsImapFrom()
    {
        return [
            "koreanair@market.koreanair.co.kr",
            "no-reply@koreanair.com",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "Login",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        $result['Email'] = re("#\n\s*E\-mail\s+Address:\s*([^\n]+)#i", $text);

        $result['Name'] = orval(
            re('#Dear\s+(.*)\s+Please#i', $text),
            re('#Dear\s+(.*?)\s*,#i', $text),
            re("#\n\s*Name:\s*([^\n]+)#i", $text)
        );

        $result['Login'] = orval(
            re("#\n\s*Member\s+ID:\s*([^\n]+)#i", $text),
            re("#\n\s*SKYPASS\s*Number:\s*([^\n]+)#i", $text)
        );

        return $result;
    }
}
