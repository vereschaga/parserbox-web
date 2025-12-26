<?php

class TAccountCheckerTnk extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;
        $this->http->removeCookies();
        $this->http->getURL('https://pay.carbon-card.ru/personal/pub/Login');

        if (!$this->http->ParseForm('id4')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('login', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://pay.carbon-card.ru/personal/pub/Login';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(normalize-space(text()), 'Прямо сейчас мы работаем над тем, чтобы сделать наш сервис лучше, быстрее и удобнее.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//span[contains(text(), 'Выйти')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error')]//span[@class='feedbackPanelERROR']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // set Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//*[@id='id9']/div/span[contains(@class, 'user-name')]")));
        // set Cash Balance
        $this->SetProperty("CashBalance", CleanXMLValue(implode(' ', $this->http->FindNodes("//div[@id='id9']//div[@class='balance']/a/span/text()"))));
        // set Active Bonuses
        $this->SetProperty("ActiveBonuses", $this->http->FindSingleNode("//div[@id='idc']//span[@class='bonuspoints']"));
        // set Non-Active Bonuses
        $this->SetProperty("NonActiveBonuses", $this->http->FindSingleNode("//div[@id='idf']//span[@class='bonuspoints']"));
        // set balance
        $this->http->GetURL('https://pay.carbon-card.ru/personal/main?wicket:interface=:1:headPanel:bonusAmount:activeBonusAmount:showActiveRoubles::IBehaviorListener:0:&random=0.011073154393163587');
        $this->SetBalance($this->http->FindSingleNode("//span[@class = 'bonuspoints']"));
    }
}
