<?php

class TAccountCheckerStadium extends TAccountChecker
{
    public const REWARDS_PAGE_URL = "https://www.stadium.fi/INTERSHOP/web/WFS/Stadium-FinlandB2C-Site/fi_FI/-/EUR/ViewUserAccount-Start";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm("LoginUserForm")) {
            return false;
        }

        $this->http->SetInputValue("ShopLoginForm_Login", $this->AccountFields['Login']);
        $this->http->SetInputValue("ShopLoginForm_Password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("signIn", "");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = self::REWARDS_PAGE_URL;

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Syötetty käyttäjätunnuksen ja salasanan yhdistelmä on valitettavasti virheellinen. Yritä uudelleen.
        if ($message = $this->http->FindSingleNode("//span[contains(@class, 'error-placeholder')]/span[@class = 'error']")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Syötetty käyttäjätunnuksen ja salasanan yhdistelmä on valitettavasti virheellinen')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }
        // Olemme päivittäneet jäsenehtomme ja tietosuojakäytäntömme.
        if ($this->http->FindNodes("//strong/span[text()='Uudet ehdot']")) {
            $this->throwAcceptTermsMessageException();
        }

        if ($this->AccountFields['Login'] == 'ossirikkila@gmail.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id='user-info-signature']")));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(@class, 'user-info__level-text')]")." ".$this->http->FindSingleNode("//span[contains(@class, 'user-info__level-number')]"));

        $this->http->GetURL("https://www.stadium.fi/INTERSHOP/web/WFS/Stadium-FinlandB2C-Site/fi_FI/-/EUR/CC_ViewBonus-ShowBonusInfo");
        // Balance - Pisteeni
        $this->SetBalance($this->http->FindSingleNode("(//dt[contains(text(), 'Pisteeni')]/following-sibling::dd)[1]", null, true, "/([^,]+)/"));
        // Käytössäsi oleva bonus
        $this->SetProperty("AvailableBonus", $this->http->FindSingleNode("(//dt[contains(text(), 'Käytössäsi oleva bonus')]/following-sibling::dd)[1]", null, true, "/([^,]+)/"));
        // Pistettä seuraavaan vuosibonukseen (Points to next annual bonus)
        $this->SetProperty("ToNextBonus", $this->http->FindSingleNode("(//dt[contains(text(), 'Pistettä seuraavaan vuosibonukseen')]/following-sibling::dd)[1]"));
    }

    // Pisteet ja Bonus
    public function getXPathString($name): string
    {
        return '(//dt[@class = "bonus-info__title" and normalize-space() = "' . $name . '"]/following-sibling::dd[@class = "bonus-info__amount"])[1]';
    }

    private function loginSuccessful()
    {
        if (
            $this->http->FindNodes("//a[contains(@href, 'LogoutUser')]/@href")
            && $this->http->FindSingleNode("//div[@id='user-info-signature']")
        ) {
            return true;
        }

        return false;
    }
}
