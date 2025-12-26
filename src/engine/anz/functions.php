<?php

use AwardWallet\Engine\anz\QuestionAnalyzer;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAnz extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizerV3;

    private $clientID = "dd0431f3-e1e7-4185-ac1c-13a1a10bc2cb";

    private const XPATH_QUESTION = '//div[div[contains(text(), "A One-Time Password (OTP) has been sent to")]]';
    private const XPATH_BALANCE = '//div[contains(@class, "top-menu-container")]//strong[contains(text(), "Reward Point")]';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->useChromePuppeteer();
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
    }

    public function IsLoggedIn()
    {
        return false;
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid Email.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid Email.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
//        $this->http->GetURL("https://auth.anzrewards.com/login?client_id={$this->clientID}&connection=password&state=e792cb26-0422-47a6-8ad8-08f9d90a4c7f&scope=openid,address,email,phone,profile,custom&redirect_uri=https://www.anzrewards.com&response_type=id_token,token");
        $this->http->GetURL("https://auth.anzrewards.com/login");

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "uid"]'), 5);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
        $button =
            $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Log in") and not(contains(@class, "is-loading"))]'), 10)
            ?? $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Log in")]'), 0)
        ;
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Please verify you are a human")]')) {
                $this->DebugInfo = $message;
            }

            if ($this->http->FindSingleNode(self::XPATH_BALANCE)) {
                return true;
            }

            return $this->checkErrors();
        }

        $loginInput->clear();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $this->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/callback/g.exec(url)) {
                        localStorage.setItem("response", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
        ');
        sleep(1);

        $button->click();

        /*
        $csrf = $this->http->FindSingleNode('//meta[@name = "csrf-token"]/@value');

        if (!$csrf) {
            return $this->checkErrors();
        }

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $captcha_v3 = $this->parseReCaptchaV3();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "credentials"       => [
                "uid"        => $this->AccountFields['Login'],
                "password"   => $this->AccountFields['Pass'],
                "rememberMe" => true,
            ],
            "_csrf_token"       => $csrf,
            "_captcha_v2_token" => $captcha,
            "_captcha_v3_token" => $captcha_v3,
            "client_id"         => $this->clientID,
        ];

        $headers = [
            "Accept"          => "application/json, text/plain, *
        /*",
            "Content-Type"    => "application/json",
            "Alt-Used"        => "auth.anzrewards.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://auth.anzrewards.com/auth/email/callback", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        */

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_QUESTION. " | ". self::XPATH_BALANCE), 10);
        $this->saveResponse();
        $responseData = $this->driver->executeScript("return localStorage.getItem('response');");
        $this->logger->info("[Response]: " . $responseData);

        if ($question = $this->http->FindSingleNode(self::XPATH_QUESTION)) {
            if (!QuestionAnalyzer::isOtcQuestion($question)) {
                $this->sendNotification("need to check QuestionAnalyzer");
            }

            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        if (!empty($responseData)) {
            $this->http->SetBody($responseData);
        }

        $response = $this->http->JsonLog();

        if (isset($response->redirect_uri)) {
            $redirect_uri = $response->redirect_uri;
            $this->http->NormalizeURL($redirect_uri);
            $this->http->GetURL($redirect_uri);

            $state = $this->http->FindPreg("/state=([^&]+)/", false, $response->redirect_uri);

            if ($state) {
                $this->captchaReporting($this->recognizer);
                $this->captchaReporting($this->recognizerV3);
                $this->http->GetURL("https://auth.anzrewards.com/authorize?response_type=web_message&state={$state}&client_id={$this->clientID}&hermes_version=2.0.2");

                if ($access_token = $this->http->FindPreg("/access_token\":\"([^\"]+)/")) {
                    $this->State['headers'] = [
                        "X-Access-Token" => $access_token,
                        "X-Force-Locale" => "en-AU",
                        "X-RD-Local-Preferences", "points_account_id=" . $this->http->FindPreg("/points_account_id\":\"([^\"]+)/"),
                    ];

                    return $this->loginSuccessful();
                }
            }
        }

        $message = $response->errors[0]->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (in_array($message, [
                "Invalid credentials.",
                "Please reset your password to continue",
                "Expired password. A reset password instruction was sent to your email",
            ])
            ) {
                $this->captchaReporting($this->recognizer);
                $this->captchaReporting($this->recognizerV3);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Account closed for user') {
                $this->captchaReporting($this->recognizer);
                $this->captchaReporting($this->recognizerV3);

                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->waitForElement(WebDriverBy::xpath('//input[@autocomplete = "one-time-code"]'), 3);
        $answerInputs = $this->driver->findElements(WebDriverBy::xpath('//input[@autocomplete = "one-time-code"]'));
        $this->saveResponse();

        if (empty($answerInputs)) {
            $this->logger->error("something went wrong");

            return false;
        }

        $this->logger->debug("entering answer...");

        foreach ($answerInputs as $i => $element) {
            $this->logger->debug("#{$i}: {$answer[$i]}");
            $answerInputs[$i]->clear();
            $answerInputs[$i]->sendKeys($answer[$i]);
            $this->saveResponse();
        }

        $this->logger->debug("Submit question");
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_BALANCE. ' | //span[contains(@class, "has-text-danger")]'), 10);
        $this->saveResponse();

        if ($error = $this->http->FindPreg('/(Incorrect OTP\.\s*Please try again\.|Code is invalid or has expired)/')) {
            $this->logger->error("[Error]: {$error}");
            $this->holdSession();
            $this->AskQuestion($this->Question, $error, "Question");

            return false;
        }

        return true;
    }

    public function Parse()
    {
        // Balance - ANZ Rewards Black: ... Reward Points
        $this->SetBalance($this->http->FindSingleNode(self::XPATH_BALANCE, null, true, "/(.+) Reward Point/"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(text(), 'Hi ')]", null, true, "/Hi (.+),/")));

        $this->http->GetURL("https://www.anzrewards.com/points_activity");
        $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "expiring-points")]'), 10);
        $this->saveResponse();
        // Reward Points Expiring 31/12/..
        $expiringBalance = $this->http->FindSingleNode('//p[contains(@class, "expiring-points")]/b', null, true, "/(.+) Reward Point/");
        $this->SetProperty("ExpiringBalance", $expiringBalance);
        $exp = $this->http->FindSingleNode('//p[contains(@class, "expiring-points")]', null, true, "/expiring on (.+)/");
        $this->logger->debug("[Exp date]: {$exp}");

        if ($expiringBalance == 0) {
            $this->ClearExpirationDate();
        }
        elseif (isset($exp) && strtotime($exp)) {
            $exp = strtotime($exp);
            // Expiration Date
            $this->SetExpirationDate($exp);
        }

        $this->http->GetURL('https://auth.anzrewards.com/current_user');
        // Name
        $response = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)]"));
        $this->SetProperty("Name", beautifulName($response->first_name . " " . $response->last_name));

        return;

        $this->http->GetURL("https://anz-nn.kaligo.com/points_accounts?active=false", $this->State['headers']);
        $response = $this->http->JsonLog();
//        $this->http->GetURL("https://anz-nn.kaligo.com/points_summary", $this->State['headers']);
//        $response = $this->http->JsonLog();

        // IsLoggedIn issue
        if (!$response && strstr($this->http->Response['body'], 'Unauthorized')) {
            throw new CheckRetryNeededException(2, 0);
        }

        // Balance - ANZ Rewards Black: ... Reward Points
        $this->SetBalance(floor($response->data[0]->attributes->pointsBalance));

        $tranches = $response->data[0]->attributes->tranches ?? [];

        foreach ($tranches as $tranch) {
            $date = $tranch->expiryDate;

            if (!isset($exp) || strtotime($date) < $exp) {
                $exp = strtotime($date);
                // Reward Points Expiring 31/12/..
                $this->SetProperty("ExpiringBalance", floor($tranch->balance));
                // Expiration Date
                $this->SetExpirationDate($exp);
            }
        }// foreach ($tranches as $tranch)
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = "6LfTa2QaAAAAABMBZPJ2but6p-s3B9BFdpni9D3I";

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => "1",
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function parseReCaptchaV3()
    {
        $this->logger->notice(__METHOD__);
        $key = "6Lerp0AcAAAAAD_HGryPRVMwJXD3LvMoi81xtPtS";

        if (!$key) {
            return false;
        }

        $this->recognizerV3 = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizerV3->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => "1",
            "version"   => "v3",
            "action"    => "login",
            "min_score" => 0.3,
        ];

        return $this->recognizeByRuCaptcha($this->recognizerV3, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://auth.anzrewards.com/current_user');
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (!empty($email) && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
