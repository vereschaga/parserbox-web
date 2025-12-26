<?php

class TAccountCheckerTahitinui extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.airtahitinui.com/en");

        if (!$this->http->ParseForm("atn-user-login-club-tiare")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("club_tiare_number", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/The website encountered an unexpected error\. Please try again later\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode('//a[contains(text(), "Logout")]')) {
            return true;
        }

        $message =
            $this->http->FindSingleNode('//div[contains(@class, "messages--error")]//li[contains(@class, "messages__item") and position() = 1]')
            ?? $this->http->FindSingleNode('//div[contains(@class, "messages--error")]/div[@role = "alert"]/text()[last()]')
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == "Invalid username or password"
                || $message == "User ID must be a 9-digit number"
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == "Impossible de traiter la demande."
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Award miles available
        $this->SetBalance($this->http->FindSingleNode('//div[@class = "award-miles-number"]'));
        // Miles expiration date
        $exp = $this->http->FindSingleNode('//div[contains(@class, "expiration-date desktop")]/div');

        if ($exp) {
            $this->SetExpirationDate(strtotime($exp));
        }

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//button[@class="username"]')));
        // Card type
        $this->SetProperty('Level', $this->http->FindSingleNode('//div[contains(text(), "Your level")]/following-sibling::div[1]'));
        // Account number
        $this->SetProperty('CardNumber', $this->http->FindSingleNode('//div[@class = "container-user-number"]', null, true, "/\:\s*([^<]+)/"));
        // Current Tier Miles
        $this->SetProperty('TierMiles', $this->http->FindSingleNode("//div[@class = 'qualifying-miles-number']"));
        // your are only 26819 tier miles away from SILVER status.
        $this->SetProperty('TierMilesToNextLevel', $this->http->FindSingleNode("//div[contains(@class, 'miles-progress-info')]", null, true, "/Earn an additional (\d+) tier mile/"));
    }
}
