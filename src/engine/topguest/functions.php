<?php

class TAccountCheckerTopguest extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://doubletree.switchfly.com/my-points");

        if (!$this->http->ParseForm("login")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        //# Server didn't response error
        if ($message = $this->http->FindPreg('/The\s*server\s*didn\'t\s*respond\s*in\s*time./ims')) {
            throw new CheckException('The server didn\'t respond in time. Please try again later.', ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# "Internal Server Error - Read"
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Provider error
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry, but something went wrong")]/parent::*')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Provider error
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re sorry, but something went wrong")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We're taking a short break.
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re taking a short break.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is allowed
        if ($this->http->FindSingleNode('//a[contains(text(),"Sign out")]')) {
            return true;
        }
        //# Incorrect email or password
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Incorrect email or password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Your account has been suspended
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your account has been suspended')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# E-mail
        $this->SetProperty("Name", $this->http->FindSingleNode('//a[@id="account"]'));

        $subAccounts = [];
        $nodes = $this->http->XPath->query('//div[@id="points"]/div');
        $this->http->Log("Total nodes found: " . $nodes->length);

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $program = $nodes->item($i);
                $Points = $this->http->FindSingleNode("div[@class='program-points']/div[@class='progress-container']/h3/span", $program);
                $Name = $this->http->FindSingleNode("div[@class='program-points']/div[@class='progress-container']/h3/text()[2]", $program);

                if (isset($Points) && isset($Name)) {
                    $Code = str_ireplace(" ", "", strtolower($Name));
                    $subAccounts[] = [
                        "Code"          => $Code,
                        "DisplayName"   => $Name,
                        "AccountNumber" => CleanXMLValue(preg_replace("/.+\#/ims", '', $Name)),
                        "Balance"       => intval(preg_replace('/,/', '', $Points)),
                    ];
                }// if (isset($Points) && isset($Name))
            }// for ($i = 0; $i < $nodes->length; $i++)
        }// if ($nodes->length > 0)
        elseif ($this->http->FindSingleNode("//h1[contains(text(), 'My points')]") && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        if (count($subAccounts) > 0) {
            $this->SetBalanceNA();
            $this->SetProperty("SubAccounts", $subAccounts);
        }
    }
}
