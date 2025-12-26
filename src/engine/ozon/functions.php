<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerOzon extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->http->setRandomUserAgent();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.ozon.ru/my/login/");

        $mailLogin = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Войти по почте")]'), 10);

        if ($mailLogin) {
            $mailLogin->click();
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Почта') or contains(text(), 'Заполните почту')]/following-sibling::input"), 5);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Пароль')]/following-sibling::input"), 5);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput) {
            $this->logger->error("something went wrong");

            return false;
        }
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        sleep(1);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Войти')]"), 0);

        if (!$button) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }
        $button->click();
        /*
        $authorization = $this->http->FindPreg("/authorization\":\{\"token\":\"([^\"]+)/");
        if (!$authorization)
            return false;
        $this->http->SetInputValue("iwPreActions", 'callLoginController');
        $this->http->SetInputValue("logusername", $this->AccountFields['Login']);
        $this->http->SetInputValue("logpassword", $this->AccountFields['Pass']);

        $data = [
            "app_version" => "browser-ozonshop",
            "client_id"   => "web",
            "grant_type"  => "password",
            "password"    => $this->AccountFields['Pass'],
            "userName"    => $this->AccountFields['Login'],
        ];
        $headers = [
            "Accept"         => "application/json",
            "Authorization"  => "Bearer {$authorization}",
            "Content-Type"   => "application/x-www-form-urlencoded",
            "Origin"         => "https://www.ozon.ru",
            "X-OZON-ABGROUP" => "29",
            "x-o3-app-name"  => "ozon_new",
        ];
        $this->http->PostURL("https://api.ozon.ru/oauth/v1/auth/token", $data, $headers);
        */

        return true;
    }

    public function Login()
    {
        /*
        $response = $this->http->JsonLog();
        if (!isset($response->access_token))
            return false;
        $headers = [
            "Accept"       => "application/json, text/plain, *
        /*",
            "Content-Type" => "application/json;charset=utf-8",
        ];
        $data = [
            "token" => [
                "access_token"  => $response->access_token,
                "refresh_token" => $response->refresh_token,
                "expires_in"    => $response->expires_in,
            ],
        ];
        $this->http->PostURL("https://www.ozon.ru/json/client.asmx/loginbytoken", json_encode($data), $headers);
        $response = $this->http->JsonLog();
        $headers = [
            "Accept"         => "application/json",
            "Authorization"  => "Bearer {$response->access_token}",
            "Origin"         => "https://www.ozon.ru",
            "X-OZON-ABGROUP" => "29",
            "x-o3-app-name"  => "ozon_new",
        ];
        $this->http->GetURL("https://api.ozon.ru/user/v5/", $headers);
        $response = $this->http->JsonLog();
        return true;
        */
        $result = $this->waitFor(function () {
            return
                /*
                $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Личный кабинет")]'), 0)
                ||
                $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Личный кабинет")]'), 0, false)
                ||
                */
                $this->waitForElement(WebDriverBy::xpath('//div[@data-test-id = "header-my-ozon-icon" and not(contains(., "OZON"))]'), 0);
        }, 10);

        if ($result) {
            $this->http->GetURL("https://www.ozon.ru/");
            $this->waitForElement(WebDriverBy::xpath('//div[@class="eOzonStatus_ProgressLine"]/div[@class="eOzonStatus_ProgressSlider"]'), 5);
            $result = true;
        }
        // save page to logs
        $this->driver->executeScript('window.stop();');
        $this->saveResponse();

        if ($result) {
            // success login
            return true;
        }

        // failed to login
        $errorMsg = $this->http->FindSingleNode('//span[@id="PageFooter_ctl01_ErrorLabel" or @id = "ctl13_ErrorLabel" or @id = "phCenter_ctl01_ErrorLabel"][1]');

        if (!$errorMsg) {
            $errorMsg = $this->http->FindSingleNode('//span[contains(text(), "Неверная почта или пароль")]');
        }

        if (!$errorMsg && ($error = $this->waitForElement(WebDriverBy::xpath('//span[@id="PageFooter_ctl01_ErrorLabel" or @id = "ctl13_ErrorLabel" or @id = "phCenter_ctl01_ErrorLabel"][1]'), 0))) {
            $errorMsg = $error->getText();
        }

        if (!$errorMsg && ($error = $this->waitForElement(WebDriverBy::xpath("(//div[@id='__layout']//span[@class='placeholder error'])[1]"), 0))) {
            $errorMsg = $error->getText();
        }

        if ($errorMsg) {
            $this->logger->debug("[Error]: {$errorMsg}");
            // wrong card num
            if (strpos($errorMsg, 'Проверьте правильность ввода логина и пароля') !== false) {
                throw new CheckException($errorMsg, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strpos($errorMsg, 'Неверная почта или пароль') !== false
                || strpos($errorMsg, 'Некорректный формат почты') !== false
            ) {
                throw new CheckException($errorMsg, ACCOUNT_INVALID_PASSWORD);
            }
            // РџСЂРѕРІРµСЂСЊС‚Рµ РїСЂР°РІРёР»СЊРЅРѕСЃС‚СЊ РІРІРѕРґР° Р»РѕРіРёРЅР° Рё РїР°СЂРѕР»СЏ.
            if (strpos($errorMsg, 'РџСЂРѕРІРµСЂСЊС‚Рµ РїСЂР°РІРёР»СЊРЅРѕСЃС‚СЊ РІРІРѕРґР° Р»РѕРіРёРЅР° Рё РїР°СЂРѕР»СЏ.') !== false) {
                throw new CheckException('Проверьте правильность ввода логина и пароля.', ACCOUNT_INVALID_PASSWORD);
            }
        }

        return false;
    }

    public function Parse()
    {
        // Spendings Towards Next Level
        $this->SetProperty('SpendingsTowardsNextLevel', $this->http->FindSingleNode('//div[@class="eOzonStatus_ProgressLine"]/div[@class="eOzonStatus_ProgressSlider"]'));
        // Status
        $status = $this->http->FindSingleNode('//span[@id="ctl34_StatusLabel"]');

        if (empty($status)) {
            $status = 'Member';
        }
        $this->SetProperty('Status', $status);
        // Profile URL
        $this->http->GetURL('https://www.ozon.ru/context/mypersonal/');
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//input[contains(@id, "_FirstName")]/@value') . ' ' . $this->http->FindSingleNode('//input[contains(@id, "_LastName")]/@value')));

        // Balance URL
        $this->http->GetURL('https://www.ozon.ru/context/mypoints/');
        // Balance (ru: Сумма)
        $balance = $this->waitForElement(WebDriverBy::xpath('//div[@class = "eOzonStatus_NumberPoints"]'), 5);
        $this->saveResponse();

        if ($balance) {
            $this->SetBalance($balance->getText());
        } else {
            $noBalance = $this->waitForElement(WebDriverBy::xpath("//*[@class = 'eOzonStatus_PointsBlock']"), 5);

            if ($noBalance && $this->http->FindPreg("/У вас пока нет баллов/", false, $noBalance->getText())) {
                $this->SetBalanceNA();
            }
        }
        // Last Activity
        if ($lastActivity = $this->waitForElement(WebDriverBy::xpath('(//table[@class = \'eMyPoint_DataTable\']//tr/td[@class = \'eMyPoint_ColDate\' and not(contains(text(), \'дата\'))])[1]'), 5)) {
            $this->SetProperty("LastActivity", $lastActivity->getText());
        }

        //# Expiration Date  // refs #5891
        $exp = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'eOzonStatus_InfoBlock mRed') and not(contains(@class, 'hidden'))]"), 5);

        if ($exp && $this->http->FindPreg("/истекает срок действия/", false, $exp->getText())) {
            $this->sendNotification("ozon - exp date was found");
        }
//        if (strtotime($exp)) {
        // Баллы действуют год с момента начисления.
//            $this->SetExpirationDate(strtotime("+12 month", strtotime($exp)));
//        }
        // Account url
        $this->http->GetURL('https://www.ozon.ru/context/myaccount/');
        // Pending (ru: Остаток средств)
        $pending = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'currentFunds']/span"), 5);
        $this->saveResponse();

        if ($pending) {
            $this->SetProperty('Pending', str_replace("руб.", " руб.", $pending->getText()));
        }
        // Locked (ru: Заблокировано)
        if ($locked = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'blockedFunds']/span"), 0)) {
            $this->SetProperty('Locked', str_replace("руб.", " руб.", $locked->getText()));
        }
        // Available (ru: Доступные средства)
        if ($available = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'accessibleFunds']/span"), 0)) {
            $this->SetProperty('Available', str_replace("руб.", " руб.", $available->getText()));
        }
    }
}
