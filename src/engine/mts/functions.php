<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMts extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [
        "Accept"          => "application/json",
        "Accept-Encoding" => "gzip, deflate, br",
        "app-version"     => "5.1",
        "os"              => "desktop-web",
        "Origin"          => "https://cashback.mts.ru",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyBrightData(null, "dc_ips_ru", "ru");
        // $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://cashback.mts.ru", [], 20);
        $this->http->RetryCount = 2;
        $this->getAppVersion();

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function getAppVersion()
    {
        $this->logger->notice(__METHOD__);
        $this->headers['app-version'] = $this->http->FindPreg("/\"API_APP_VERSION\":\"([^\"]+)/") ?? $this->headers['app-version'];
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return $this->selenium();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        if ($errors = $this->http->FindSingleNode('
                //div[@class="login-form__control-error"][normalize-space()!=""][1]
                | //p[contains(@class, "errorText")]/@data-error
            ')
        ) {
            $this->logger->error($errors);

            if ($errors === "Неверный формат. Введите номер правильно"
                || $errors === "Неверный пароль. Повторите попытку или получите новый пароль."
                // Неверный пароль. Осталось 2 попытки
                || strstr($errors, "Неверный пароль.")
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(strip_tags($errors), ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        $response = $this->http->JsonLog(null, 0);
        $message = $response->error->detail ?? null;

        if ($message) {
            $this->logger->error($message);
            // todo: ???
            return false;
        }

        $status = $response->Status->Description ?? null;

        if ($status === "NOT_MEMBER") {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Вы не являетесь участником этой программы лояльности", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//div[h1[contains(normalize-space(text()), "Введите код из")]]/following-sibling::p[contains(text(), "Мы отправили ")]');

        if (!$question || !$this->http->ParseForm(null, '//form[//input[@name = "otp"]]')) {
            return false;
        }

        $this->Question = "Введите код из SMS. " . $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->sendNotification("code was entered // RR");

        $this->http->SetInputValue("otp", $this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);
        $this->http->FormURL = 'https://login.mts.ru/amserver/UI/Login?';

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($error = $this->http->FindSingleNode('//div[contains(@class,"codeCheck__errorText") and contains(normalize-space(), "Неверный код.")]')) {
            $this->AskQuestion($this->Question, $error, 'Question');

            return false;
        }

        return $this->loginSuccessful();
    }

    public function Parse()
    {
        $this->http->GetURL("https://freecom-app.mts.ru/api/v2/profile", $this->headers);
        $profile = $this->http->JsonLog();
        // Name
        $this->SetProperty("Name", beautifulName($profile->data->name ?? null));
        // Phone
        $this->SetProperty("Phone", $profile->data->msisdn ?? null);

        $this->http->GetURL("https://freecom-app.mts.ru/api/v2/balance", $this->headers);
        $balance = $this->http->JsonLog();
        // Доступный кэшбэк
        $this->SetBalance($balance->data->availableValue ?? null);
        // Ожидает начисления
        $pending = $balance->data->pendingValue ?? null;

        if (isset($pending)) {
            $this->AddSubAccount([
                "Code"        => "mtsPending",
                "DisplayName" => "Pending",
                "Balance"     => $pending,
            ]);
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            // AccountID: 1248057
            && $profile->data->msisdn == '79161662363'
            && $profile->data->balance == 0
            && isset($balance->Details->Code) && $balance->Details->Code == -1
        ) {
            $this->SetBalance(0);
        }

        $this->logger->info("Certificates", ['Header' => 3]);
        $this->http->GetURL("https://freecom-app.mts.ru/api/v3/certificates", $this->headers);
        $data = $this->http->JsonLog();
        $certificates = $data->data->claimed ?? [];

        foreach ($certificates as $certificate) {
            $this->AddSubAccount([
                "Code"           => "mtsCertificate" . $certificate->cashBackGiftCode,
                "DisplayName"    => "Certificate #" . $certificate->cashBackGiftCode,
                "Balance"        => $certificate->cashBackAmount,
                "ExpirationDate" => strtotime($certificate->cashBackGiftDateTo),
            ]);
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $imageData = $this->http->FindSingleNode('
            //img[@id = "kaptchaImage"]/@src
            | //img[@id = "captchaImage"]/@src
            | //img[@alt = "Red dot"]/@src
        ', null, true, "/png;base64\,\s*([^<]+)/ims");
        $this->logger->debug("png;base64: {$imageData}");

        if (!empty($imageData)) {
            $this->logger->debug("decode image data and save image in file");
            // decode image data and save image in file
            $imageData = base64_decode($imageData);
            $image = imagecreatefromstring($imageData);
            $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true) . ".png";
            imagejpeg($image, $file);
        }

        if (!isset($file)) {
            return false;
        }
        $this->logger->debug("file: " . $file);
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://freecom-app.mts.ru/profile", $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $phone = $response->Msisdn ?? null;
        $status = $response->Status->Description ?? null;

        // if NOT_MEMBER /profile = Internal server error
        if (
            substr($phone, -10) === substr($this->AccountFields['Login'], -10)
            && $status !== "NOT_MEMBER"
        ) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            /*
            $selenium->keepCookies(false);
            $selenium->http->removeCookies();
            */
            $selenium->http->GetURL("https://cashback.mts.ru/");

            $signIn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(normalize-space(),"Войти")]'), 10);
            $this->saveToLogs($selenium);
            $this->getAppVersion();

            if (!$signIn) {
                return $this->checkErrors();
            }
            $signIn->click();

            $this->authorization($selenium);

            if ($this->checkForCaptcha($selenium)) {
                $this->passingCaptcha($selenium);
            }
            // if wrong captcha
            if ($selenium->waitForElement(WebDriverBy::xpath('//div[@class="error" and normalize-space() = "Вы ввели неверный код."]'), 2)) {
                $this->captchaReporting($this->recognizer, false);
                $this->passingCaptcha($selenium);
            }

            if ($this->checkForCaptcha($selenium)) {
                $this->passingCaptcha($selenium);
            }

            // logged in
            $profile_menu = $selenium->waitForElement(WebDriverBy::id('profile_menu'), 5);

            if (!$profile_menu) {
                $this->authorization($selenium);

                $byPass = $selenium->waitForElement(WebDriverBy::xpath('//label[normalize-space() = "По паролю"]'), 0);
                $contBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "submit"]'), 0);

                if ($byPass && $contBtn) {
                    $this->saveToLogs($selenium);
                    $byPass->click();
                    $contBtn->click();
                }

                if ($this->checkForCaptcha($selenium)) {
                    $this->saveToLogs($selenium);
                    $this->passingCaptcha($selenium);
                    $this->authorization($selenium);

                    sleep(5);
                    $selenium->waitForElement(WebDriverBy::id('profile_menu'), 0);
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->saveToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            if ($selenium->waitForElement(WebDriverBy::xpath('//div[h1[contains(normalize-space(text()), "Введите код из")]]/following-sibling::p[contains(text(), "Мы отправили ")]'), 0)) {
                $this->captchaReporting($this->recognizer);

                if ($this->parseQuestion()) {
                    return false;
                }
            }
        } catch (TimeOutException | SessionNotCreatedException | NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "Exception";
            // retries
            $retry = true;
        }// catch (TimeOutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 1);
            }
        }

        return true;
    }

    private function authorization($selenium)
    {
        $this->logger->notice(__METHOD__);
        $login = $selenium->waitForElement(WebDriverBy::id('login'), 15);
        $next = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(., 'Далее')]"), 0);

        if (!$login || !$next) {
            return false;
        }
        $login->sendKeys(substr($this->AccountFields['Login'], -10));
        $this->saveToLogs($selenium);
        $next->click();

        $pass = $selenium->waitForElement(WebDriverBy::id('password'), 3);
        $button = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(., 'Далее')]"), 0);

        if (!$pass || !$button) {
            return false;
        }
        $pass->click();
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveToLogs($selenium);
        $button->click();

        return true;
    }

    private function checkForCaptcha($selenium)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        return $selenium->waitForElement(WebDriverBy::xpath(
            '//text()[normalize-space() = "This question is for testing whether you are a human visitor and to prevent automated spam submission."] 
            | //div[@class = "captcha_description" and normalize-space() = "Мы заметили подозрительную активность с вашего аккаунта. Для входа в приложение, пожалуйста, подтвердите, что вы не робот."]
            | //p[normalize-space() = "Подтвердите, что вы не робот"]
            | //b[normalize-space() = "What code is in the image?"]
            | //h1[normalize-space() = "Введите код с картинки"]
            | //label[normalize-space() = "По паролю"]
            | //body[contains(., "What code is in the image?")]
        '), 5) ?? $selenium->waitForElement(WebDriverBy::xpath('//body[contains(., "What code is in the image?")]'), 0);
    }

    private function passingCaptcha($selenium)
    {
        $this->logger->notice(__METHOD__);
        $inputCode = $selenium->waitForElement(WebDriverBy::xpath('
            //input[@id = "ans" and @name="answer"]
            | //input[@id = "password" and @name = "IDToken2"]
            | //input[@id = "captchaInput"]
            | //input
        '), 0);
        $buttonCode = $selenium->waitForElement(WebDriverBy::xpath('
            //input[@id = "jar" and type="button"]
            | //button[@type = "submit" and contains(normalize-space(), "Далее")]
            | //button[@id="submit" and contains(normalize-space(), "Отправить")]
            | //button[@id="submit" and contains(normalize-space(), "Подтвердить")]
            | //button[@id="jar" and contains(normalize-space(), "submit")]
            | //body//button[text() = "submit"]
        '), 0);

        if (!$inputCode || !$buttonCode) {
            return false;
        }

        $this->saveToLogs($selenium);
        // parse captcha
        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $inputCode->sendKeys($captcha);
        $this->saveToLogs($selenium);
        $buttonCode->click();

        return true;
    }

    private function saveToLogs($selenium)
    {
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
