<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerLukoil extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $sessionId = null;
    private $version = 'v4';
    //private $cardId;

    private $headers = [
        "Accept"       => "application/json, text/plain, */*",
        "Content-Type" => "application/json;charset=utf-8",
        "X-Api-Token"  => "mcHySTn5vmPvMLWrYMfG3xgC9rV2moJ6",
    ];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerLukoilSelenium.php";

        return new TAccountCheckerLukoilSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyBrightData(null, 'static', 'ru');
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['sessionId'])) {
            return false;
        }

        $this->sessionId = $this->State['sessionId'];

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->selenium();
        $this->http->GetURL("https://customer.licard.ru/login");

        if ($this->http->Response['code'] != 200) {
            return false;
        }
//        if (!$this->http->ParseForm("form"))
        //			return false;
        //		$this->http->SetInputValue('login', $this->AccountFields['Login']);
        //		$this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $this->http->PostURL("https://customer.licard.ru/api/{$this->version}/user/login", '{"platform":"web","osVersion":"MacIntel","appVersion":"1"}', $this->headers);
        $response = $this->http->JsonLog();
        $sessionId = $response->result->sessionId ?? null;

        if (!isset($sessionId)) {
            return false;
        }
        $this->sessionId = $sessionId;

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "cardNumber" => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
            "sessionId"  => $this->sessionId,
            "token"      => $captcha,
        ];
        $this->http->PostURL("https://customer.licard.ru/api/{$this->version}/card/connect", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $result = $response->result ?? null;

        if ($result && $this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        $message = $response->errorMessage ?? $this->http->JsonLog();

        if ($message) {
            $this->logger->error($message);

            if (
                strstr($message, 'Номер карты или пароль введены неверно. 55 - Карта неактивна')
                || strstr($message, 'Номер карты или пароль введены неверно. 13 - Пароль неверен')
                || strstr($message, 'Недействительный номер карты.')
                || strstr($message, 'Номер карты или пароль введены неверно. Пароль неверен')
//                || strstr($message, 'Пользователь не найден.') // what is that? -> false positive on AccountID: 4112482
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Номер карты или пароль введены неверно.", ACCOUNT_INVALID_PASSWORD);
            }
            // Внутренняя ошибка сервера.
            if (
                strstr($message, 'Внутренняя ошибка сервера.')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($message, 'Некорректное значение recaptcha.')
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $cards = $response->result->cards ?? [];

        if (empty($cards) || count($cards) > 1) {//todo: need to check account with multiple cards
            return;
        }

        $data = [
            "cardId"    => $cards[0]->id,
            "sessionId" => $this->sessionId,
        ];
        $this->http->PostURL("https://customer.licard.ru/api/{$this->version}/card/info", json_encode($data), $this->headers, 20);
        $response = $this->http->JsonLog(null, 0, true);
        $result = ArrayVal($response, 'result');
        // ФИО
        $this->SetProperty("Name", beautifulName(Html::cleanXMLValue(ArrayVal($result, 'firstName') . " " . ArrayVal($result, 'middleName') . " " . ArrayVal($result, 'lastName'))));

        $this->http->PostURL("https://customer.licard.ru/api/{$this->version}/card/details/info", json_encode($data), $this->headers, 20);
        $response = $this->http->JsonLog();
        $result = $response->result ?? null;

        if (!$result) {
            $message = $response->errorMessage ?? null;

            if ($message == 'Уровень Вашей карты и расчетный период появятся после совершения первой покупки на АЗС с использованием карты лояльности') {
                $this->SetWarning($message);
            }

            return;
        }

        // Баланс Вашей карты
        $this->SetBalance($result->balance);
        // Карта:
        $this->SetProperty("CardNumber", $result->cardNumber);
        $this->SetProperty("CardStatus", $result->currentCardLevel->description);

        // Expiration Date refs#18401#note-3
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6LfYf7kUAAAAAI7HaoG1xNAoiVmBocZ6_ajEzgbv';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => "https://customer.licard.ru/login",
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $data = [
            "sessionId" => $this->sessionId,
        ];
        $this->http->PostURL("https://customer.licard.ru/api/{$this->version}/card/list", json_encode($data), $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->result->cards[0]->id)) {
            if (count($response->result->cards) > 1) {
                $this->sendNotification('cards > 1 //MI');
            }
            //$this->cardId = $response->result->cards[0]->id;
            return true;
        }

        return false;
    }

    /*private function selenium() {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->useChromium();
            //$selenium->useCache();
            //$selenium->disableImages();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://customer.licard.ru/login");

            //$selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Войдите в личный кабинет")]'), 5);
            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'cardNumber']"), 5);
            $login->click();
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 1);
            $pass->click();
            sleep(1);
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $cookies = $selenium->driver->manage()->getCookies();
            foreach ($cookies as $cookie)
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], isset($cookie['expiry']) ? $cookie['expiry'] : null);
            $result = true;
        }
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");
                throw new CheckRetryNeededException(2, 5);
            }
        }

        return $result;
    }*/
}
