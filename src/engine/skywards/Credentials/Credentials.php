<?php

namespace AwardWallet\Engine\skywards\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'skywards@e.emirates.travel',
            'skywards.cms@emirates.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Emirates Skywards newsletter",
            "Welcome to Skywards",
            "#Emirates Skywards#i",
            "#Welcome to Skywards#i",
            "#new offers and rewards#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        $result['Login'] = orval(
            re('#membership\s+number\s+is\s+EK\s+(\d+)#i', $text),
            re('#membership\s+Number\s+(\d[\d\s]+\d)#i', $text),
            re("#\n\s*Membership\s*no\s*:\s*([\d ]+)#", $text),
            $parser->getCleanTo()
        );
        $result['Login'] = preg_replace('#\s#i', '', $result['Login']);

        $result['Name'] = orval(
            re("#\n\s*Name\s+M[rs]+\s+([^\n]+)#i", $text),
            re("#Dear\s+M[rs]+\s+(.*?)\s*,#i", $text),
            re("#Dear\s+M[rs]+\s+(.*?)\s*,#i", $text)
        );

        return $result;
    }
}
