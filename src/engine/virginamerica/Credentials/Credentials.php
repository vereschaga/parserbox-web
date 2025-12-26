<?php

namespace AwardWallet\Engine\virginamerica\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'virginamerica@elevate.virginamerica.com',
            'elevate@virginamerica.com',
            'info@fly.virginamerica.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#with fares from \D{1}\d+ one way#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = trim($this->http->FindSingleNode("//*[contains(text(),'points')]/ancestor::table[1]//tr[3]"), '# ');
        $result['Name'] = beautifulName($this->http->FindSingleNode("//*[contains(text(),'points')]/ancestor::table[1]//tr[1]"));

        return $result;
    }
}
