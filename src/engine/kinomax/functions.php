<?php

class TAccountCheckerKinomax extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->LogHeaders = true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://kinomax.ru/";
        $arg["SuccessURL"] = "https://kinomax.ru/lk/index";

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://kinomax.ru");

        if (!$this->http->ParseForm("login-form")) {
            return $this->checkErrors();
        }
        $this->http->FormURL = "https://kinomax.ru/lk/login";
        $this->http->SetInputValue("login", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//p[contains(text(), 'CDbConnection failed to open the DB connection.')]")
            || $this->http->FindSingleNode("//title[contains(text(), '502 Bad Gateway')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '503 Service Temporarily Unavailable')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $headersJS = [
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "X-Requested-With" => "XMLHttpRequest",
        ];

        if (!$this->http->PostForm($headersJS)) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog(null, 3, true);

        if (isset($response['result']) && $response['result'] == 'ok') {
            return true;
        }

        if (isset($response['result']) && $response['result'] == 'error') {
            throw new CheckException($response['message'], ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://kinomax.ru/lk/index");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@id='nameInput']/@value") . ' ' . $this->http->FindSingleNode("//input[@id='lastnameInput']/@value")));

        $cardError = $this->http->FindSingleNode("//div[contains(text(),'По техническим причинам использование Мультикарты') or contains(text(), 'Обновляем данные о карте...')]");

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://kinomax.ru/lk/cardinfo");
        $this->http->RetryCount = 2;

        // Мультикарта
        $this->SetProperty("Number", $this->http->FindSingleNode("//div[contains(text(),'Мультикарта')]/following-sibling::div[1]"));
        // Бонус с покупок
        $this->SetProperty("Bonus", $this->http->FindSingleNode("//div[contains(text(),'Бонус с покупок')]/following-sibling::div[1]"));
        // Balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(),'Активный баланс карты')]/following-sibling::div[1]"));
        // Bonuses To The Next Level of LP
        $this->SetProperty("BonusesToTheNextLevel", $this->http->FindSingleNode("//text()[contains(normalize-space(.),'До уровня') and contains(normalize-space(.),'нужно накопить')]", null, true, "#ещё\s*(.+)\s+бонус#"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // По техническим причинам использование Мультикарты (других бонусных карт, включая VIP) временно доступно только в кинотеатрах.
            // Обновляем данные о карте...
            if ($this->http->Response['code'] == 500 && isset($cardError)) {
                throw new CheckException($cardError, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->XPath->query("//div[contains(text(),'Не удалось проверить')]")->length > 0) {
                throw new CheckException($this->http->FindSingleNode("(//div[contains(text(),'Не удалось проверить')])[1]"), ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * Бонусная карта
             * Активируйте свою Мультикарту или VIP-карту
             */
            if ($this->http->FindSingleNode("//div[contains(text(), 'Активируйте свою Мультикарту или VIP-карту')]")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }
}
