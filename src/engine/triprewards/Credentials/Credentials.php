<?php

namespace AwardWallet\Engine\triprewards\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'wyndhamrewards@e-mails.wyndhamrewards.com',
            'WyndhamRewards@emails.wyndhamrewards.com',
            'wyndhamRewards@emails.wyndhamrewards.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Want to earn Double Points on weekday stays?",
            "Rates As Low As $25/Night, Start the New Year Right!",
            "Alexi, Earn up to 30,000 Wyndham Rewards points!",
        ];
    }

    public function getParsedFields()
    {
        return [
            // 'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        if ($this->http->FindSingleNode("//*[contains(text(),'Member')][contains(text(),'Info')]")) {
            $result['Name'] = beautifulName(trim($this->http->FindSingleNode("//*[contains(text(),'Member')][contains(text(),'Info')]", null, true, "#^(.*?)'s#")));
        // $result['Login'] = trim($this->http->FindSingleNode("//*[contains(text(),'Member')][contains(text(),'Info')]/../following-sibling::*[1]", null, true, "/#:(.+)$/"));
        } elseif ($this->http->FindSingleNode("//*[contains(text(),'Membership')]")) {
            $result['Name'] = beautifulName(trim($this->http->FindSingleNode("//*[contains(text(),'Membership')]/ancestor::table[1]//tr[1]")));
        // $result['Login'] = trim($this->http->FindSingleNode("//*[contains(text(),'Membership')]/following-sibling::*[1]"));
        } elseif ($this->http->FindSingleNode("//*[contains(text(),'Member #')]")) {
            $result['Name'] = beautifulName(trim($this->http->FindSingleNode("//*[contains(text(),'Member #')]/..", null, true, "#^(.*?)Member#")));
            // $result['Login'] = trim($this->http->FindSingleNode("//*[contains(text(),'Member #')]/..", null, true, "/Member # (.+)$/"));
        }

        return $result;
    }
}
