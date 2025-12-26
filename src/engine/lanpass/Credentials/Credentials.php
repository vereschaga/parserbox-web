<?php

namespace AwardWallet\Engine\lanpass\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "mail.lan@lan.com",
            "news@news.lan.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to LANPASS",
            "See your LANPASS account statement",
            "LANPASS account statement",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();

        if (stripos($subject, 'Welcome to LANPASS') !== false) {
            // credentials-1.eml
            $result['Name'] = beautifulName(re('#Dear\s+(.*?)\s*,#i', $text));
            $result['Login'] = re('#Member\s+number\s+(\d+)#i', $text);
        } elseif (stripos($subject, 'See your LANPASS account statement') !== false) {
            // credentials-2.eml
            $result['Name'] = beautifulName(re('#Dear\s+(.*?)\s*:#i', $text));
            $result['Login'] = re('#Número\s+de\s+Socio\s*:\s+(\d+)#i', $text);
        } elseif (stripos($subject, 'LANPASS account statement.') !== false) {
            // credentials-3.eml
            $result['Name'] = beautifulName(re('#Dear\s+(.*?)\s*,\s+This\s+is#i', $text));
            $result['Login'] = re('#Membership\s+number\s*:\s+(\d+)#i', $text);
        }

        return $result;
    }
}
