<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerGazneft extends TAccountChecker
{
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.gpnbonus.ru/profile-online/main/", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (!$this->http->FindPreg("/^\+7\(\d{3}\)/", false, $this->AccountFields['Login'])) {
            throw new CheckException("Неверный номер телефона. Пожалуйста, введите номер телефона в формате +7(XXX)XXX-XXXX", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.gpnbonus.ru/login/");

        if (!$this->http->ParseForm("login_form")) {
            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $this->http->SetInputValue("phone", $this->AccountFields['Login']);
        $this->http->SetInputValue("phone_form", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
//        $this->http->unsetInputValue('MESSAGE');

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.gpnbonus.ru/login/";

        return $arg;
    }

    public function Login()
    {
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        if (!$this->http->PostForm()) {
            return false;
        }
        $response = $this->http->JsonLog();

        if ($response->message == 'Сообщение с кодом подтверждения успешно отправлено.') {
            $form['secret'] = $response->secret;
            $this->State['Form'] = $form;
            $this->State['FormURL'] = $formURL;
            $this->parseQuestion();

            return false;
        }

        // invalid credentials
        if (isset($response->message, $response->action) && $response->action != 'login_ok') {
            // retries
            if (strstr($response->message, 'Неверно введены цифры и буквы с картинки')) {
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();

                throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
            } else {
                throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);
            }
        }
        // redirect
        if (isset($response->action_param)) {
            $redirect = $response->action_param;
            $this->logger->notice("Redirect to -> {$redirect}");
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }
        // Смена пароля
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Смена пароля')]")) {
            throw new CheckException("GazpromNeft (Going our way) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->currentUrl() == 'https://www.gpnbonus.ru/?server=false__2') {
            $this->recognizer->reportIncorrectlySolvedCAPTCHA();

            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = "Пожалуйста, введите 4 цифры из СМС";
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->State['Form']["code"] = $this->Answers[$this->Question];
        $this->http->RetryCount = 0;
        $this->http->PostURL($this->State['FormURL'], $this->State['Form']);

        unset($this->Answers[$this->Question]);
        $response = $this->http->JsonLog();

        if (isset($response->action) && $response->action == 'login_ok') {
            $this->http->GetURL("https://www.gpnbonus.ru/profile-online/main/#phone");
        }

        return true;
    }

    public function Parse()
    {
        // Status
        $this->SetProperty("Status", ucfirst(basename($this->http->FindSingleNode("(//div[contains(text(), 'ТЕКУЩИЙ СТАТУС КАРТЫ')]/following::img[1]/@src)[1]"), '.png')));
        // Spend to retain status
        $this->SetProperty("RetainStatus", implode(' / ', $this->http->FindNodes("//p[contains(text(), 'Для подтверждения статуса') and @style]/following-sibling::div[1]")));
        // Spend to next level
        $this->SetProperty("NextLevel", $this->http->FindSingleNode("//p[contains(text(), 'Для повышения статуса')]/following::div[1]"));
        // Spent this month
        if ($thisMonth = $this->http->FindSingleNode("//div[contains(@class, 'DinProMedium')]")) {
            $thisMonth = str_replace('зона', 'зона ', $thisMonth);
            $thisMonth = str_replace('руб. ', 'руб. / ', $thisMonth);
            $thisMonth = str_replace('руб.', ' руб.', $thisMonth);
            $this->SetProperty("ThisMonth", Html::cleanXMLValue($thisMonth));
        }
        // set Balance
        $this->SetBalance(str_replace(".", ",", $this->http->FindSingleNode(".//*[@id='PersonalDatTable']//div[contains(@class, 'fs40')]")));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->GetURL("https://www.gpnbonus.ru/profile-online/statistics/");
            // Balance - Бонусов доступно на балансе
            $this->SetBalance(str_replace(".", ",", $this->http->FindSingleNode("//h3[contains(text(), 'Бонусов доступно на балансе')]", null, true, "/Бонусов доступно на балансе\s*([^<]+)/")));
        }

        // Expiration date  // refs #6674
        $this->http->PostURL("https://www.gpnbonus.ru/profile-online/statistics/handler.php", ["month" => "0", "year" => "0"]);
        $response = $this->http->JsonLog(null, false);

        foreach ($response as $row) {
            if (isset($row->dt) && (!isset($exp) || strtotime($row->dt) > $exp)) {
                $exp = strtotime($row->dt);
                $this->SetProperty("LastActivity", $row->dt);
                $this->SetExpirationDate(strtotime("+3 year", $exp));
            }
        }// if (isset($row->dt) && (!isset($exp) || strtotime($row->dt) > $exp))
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("(//form[@id='login_form']//button[@class='g-recaptcha']/@data-sitekey)[1]");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//form[@id='logout']/@id")) {
            return true;
        }

        return false;
    }
}
