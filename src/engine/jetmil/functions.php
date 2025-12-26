<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerJetmil extends TAccountChecker
{
    use ProxyList;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.atlasglb.com/en/atlasmiles');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $headers = [
            'Accept'       => '*/*',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ];
        $data = [
            'cardNo'   => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];
        $this->http->PostURL('https://www.atlasglb.com/milesLogin', $data, $headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.atlasmiles.com';

        return $arg;
    }

    public function Login()
    {
        if ($this->http->FindPreg('/^\w+\s+\w+(?:\s+\w+$|$)/')) {
            return true;
        }

        if ($this->http->FindPreg('/^failed$/')) {
            throw new CheckException('Check the information you entered and try again', ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.atlasglb.com/en/atlasmiles/my-Account');
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[@class='user']//span[@class='name']")));
        // Balance - Total Miles
        $this->SetBalance($this->http->FindSingleNode("//span[@id='totalMiles']"));
        // Card #
        $this->SetProperty('CardNumber', $this->http->FindSingleNode("(//input[@name='cardNo']/@value)[1]"));
        // Status
        if ($card = $this->http->FindSingleNode("//img[contains(@src,'/resources/images/card-')]/@src", null, false, '/card-(\w+)\.png/')) {
            $status = strtolower(pathinfo($card, PATHINFO_FILENAME));
            in_array($status, ['bronze', 'silver', 'gold', 'platinum'/*, 'business', 'junior'*/])
                ? $this->SetProperty('CardStatus', ucfirst($status))
                : $this->sendNotification('refs #10007 - New status: ' . $status);
        }

        if ($this->Balance <= 0) {
            return;
        }

        if ($expDate = $this->http->FindSingleNode("//span[contains(text(),'Miles will be expired on')]/following-sibling::span[1]",
            null, false, '/\d+ \w+ \d+/')
        ) {
            $this->SetExpirationDate(strtotime($expDate, false));
        }
    }
}
