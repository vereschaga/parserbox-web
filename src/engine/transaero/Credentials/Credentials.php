<?php

namespace AwardWallet\Engine\transaero\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'news_privilege@transaero.ru',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Transaero Privilege#",
            "Transaero News",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Name'] = implode(' ', $this->http->FindNodes("//*[contains(text(), 'Registration data')]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>1][position()<3]/td[1]"));
        $result['Login'] = $parser->getCleanTo();
        $result['Password'] = $this->http->FindSingleNode("//*[contains(text(), 'PIN number')]/ancestor-or-self::td[1]/following-sibling::td[1]", null, true, "#^[^\s]+$#");

        if (!$result['Name']) {
            $result['Name'] = $this->http->FindSingleNode("//*[contains(text(), 'BALANCE OF ACCOUNT')]/ancestor::div[1]/following::div[1]/p[1]");
        }

        return $result;
    }
}
