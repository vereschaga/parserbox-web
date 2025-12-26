<?php

// refs #1988, tropicana

class TAccountCheckerTropicana extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->getURL('http://juicyrewards.tropicana.com/Account/Login');
        $this->http->FormURL = "http://juicyrewards.tropicana.com/Account/Authenticate";
        $this->http->SetFormText('ReturnUrl=&Username=test%40test.ru&Password=123456', '&');
        $this->http->Form['Username'] = $this->AccountFields['Login'];
        $this->http->Form['Password'] = $this->AccountFields['Pass'];
        $this->http->Form['ReturnUrl'] = 'http://juicyrewards.tropicana.com/Account/GetStarted';

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        if ($this->http->FindSingleNode("//a[contains(text(),'Log Out')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@id='error-list']")) {
            throw new CheckException($this->http->FindSingleNode("//div[@id='error-list']/ul/li[1]"), ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->getURL('http://juicyrewards.tropicana.com/Account/GetStarted');
        $this->SetProperty("Name", $this->http->FindSingleNode('//div[@id="account-intro"]', null, true, '/Welcome (.*)! You/'));
        //# Balance
        $this->SetBalance(intval($this->http->FindSingleNode('//div[@id="account-intro"]/span[@class="points"]')));
        //# Level
        $level = $this->http->FindSingleNode("//div[contains(@class, 'header')]/@class", null, true, "/header-(.*)/ims");

        if (isset($level)) {
            $this->SetProperty("Level", ucwords($level));
        }
        //# Collect more points
        $this->SetProperty("NeededForNextLevel", $this->http->FindSingleNode('//span[@id="header-slider-intro"]', null, true, '/Collect (.*) more/'));
        //# Next Level
        $this->SetProperty("NextLevel", $this->http->FindSingleNode('//span[@id="header-slider-intro"]', null, true, '/reach (.*)/'));

        $this->http->getURL('http://juicyrewards.tropicana.com/Account/Statement');
        //# Name
        $name = CleanXMLValue($this->http->FindSingleNode("//h3[contains(text(), 'Welcome')]", null, true, "/Welcome\s*([^<]+)/ims"));

        if (isset($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }
}
