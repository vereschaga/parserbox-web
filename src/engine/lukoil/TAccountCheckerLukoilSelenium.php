<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerLukoilSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    // private $browser;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome();
//        $this->disableImages();
        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
        $this->setProxyBrightData(null, "dc_ips_ru", "ru");
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['sessionId'])) {
            return false;
        }
        $this->logger->debug("localStorage.setItem('sessionId', '{$this->State['sessionId']}');");
        $this->driver->executeScript("localStorage.setItem('sessionId', '{$this->State['sessionId']}');");

//        $this->http->GetURL('https://customer.licard.ru/main');
        $this->http->GetURL('https://customer.licard.ru/login');

        if ($this->loginSuccessful()) {
            if ($this->waitForElement(WebDriverBy::xpath("//input[@name='cardNumber']"), 2)) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (stripos($this->http->currentUrl(), 'https://customer.licard.ru/login') === false) {
            $this->http->GetURL('https://customer.licard.ru/login');
        }
        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name='cardNumber']"), 7);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name='password']"), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[@type='submit']"), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$button) {
            $this->logger->error("something went wrong");

            if ($this->loginSuccessful()) {
                return true;
            }

            if ($this->http->FindSingleNode('//h1[contains(text(), "Произошла ошибка")]')) {
                $this->callRetries();
            }

            return $this->checkErrors();
        }
        $login->click();
        $login->sendKeys(substr($this->AccountFields['Login'], 8));
        $pass->click();
        $password = $this->AccountFields['Pass'];
        $pass->sendKeys($password);
        $this->saveResponse();
        $button->click();

        return true;
    }

    public function callRetries()
    {
        $this->logger->notice(__METHOD__);

        throw new CheckRetryNeededException(3, 1);
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        // maintenance
//        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Thank you for visiting bloomingdales.com. We are in the process of upgrading our site')]"))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        // Access Denied
        /*if ($this->http->ParseForm("memberSignInForm") || $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            throw new CheckRetryNeededException(3, 7);
        }*/

        return false;
    }

    public function Login()
    {
        sleep(3);

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $this->waitForElement(WebDriverBy::xpath("//p[@class='modal__text']"), 0);

        if ($message) {
            $message = $message->getText();
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Уровень Вашей карты и расчетный период появятся после совершения первой покупки на АЗС с использованием карты лояльности')) {
                $this->SetWarning($message);

                return false;
            }

            if ($message == 'Проверьте Ваше интернет соединение.') {
                $this->callRetries();
            }

            if ($message == 'Внутренняя ошибка сервера.') {
                $this->callRetries();
            }
            // Номер карты или пароль введены неверно
            if ($message == 'Номер карты или пароль введены неверно') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        // That email address & password combination isn’t in our records. Forgot your password?
//        if ($message = $this->waitForElement(WebDriverBy::xpath("//p[@class='modal__text' and contains(text(),'Внутренняя ошибка сервера.')]"), 0))
//            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);

        return $this->checkErrors();
    }

    public function Parse()
    {
        $sessionId = $this->driver->executeScript("return localStorage.getItem('sessionId');");

        if ($sessionId) {
            $this->logger->info("get sessionId: " . $sessionId);
            $this->State['sessionId'] = $sessionId;
        }

        $name = $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Добрый день,')]"), 7);

        if (!$name) {
            $this->saveResponse();
            $this->http->GetURL("https://customer.licard.ru/main");
            $name = $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Добрый день,')]"), 7);
            $this->saveResponse();

            return;
        }
        // Добрый день, Сергей!
        $this->SetProperty("Name", beautifulName($this->http->FindPreg('/Добрый день, (.{3,30})!/u', false, $name->getText())));
        // Баланс Вашей карты 20 579 баллов
        $this->SetBalance($this->http->FindPreg('/Баланс Вашей карты ([\d.,\s]+) балл/iu', false, $name->getText()));
        // Карта:
        $card = $this->waitForElement(WebDriverBy::xpath("//p[starts-with(text(), 'Карта: ')]"), 0);
        $this->SetProperty("CardNumber", $this->http->FindPreg('/Карта: (.{23})$/u', false, $card->getText()));
        // Status
        $status = $this->waitForElement(WebDriverBy::xpath("//p[starts-with(text(), 'Карта: ')]/preceding-sibling::div/p[@class='level-card__title']"));
        $this->SetProperty("CardStatus", $status->getText());

        // Expiration Date refs#18401#note-3
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $login = $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Добрый день,')]"), 5);
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $this->saveResponse();

        if ($login) {
            return true;
        }

        return false;
    }
}
