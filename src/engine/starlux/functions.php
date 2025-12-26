<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerStarlux extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.starlux-airlines.com/en-US/COSMILE/MemberDashboard';

    private $recognizer;
    private $headers = [
        'Accept'       => 'application/json, text/plain, */*',
        'Content-Type' => 'application/json',
        'Origin' => 'https://www.starlux-airlines.com',
        'jx-lang' => 'en-US'
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setHttp2(true);
        */

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
//        $this->setProxyGoProxies();
        $this->setKeepProfile(true);
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }
        $this->headers['Authorization'] = $this->State['Authorization'];

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.starlux-airlines.com/en-US/member/login?prev=%2Fen-JP&next=%2Fen-US%2Fcosmile%2Fmy-cosmile%2Faccount-overview');

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@data-qa = 'qa-txt-id']"), 10);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@data-qa = 'qa-txt-password']"), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@data-qa = "qa-btn-login"]'), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            $this->logger->error("something went wrong");

            return false;
        }

        if ($acceptCookie = $this->waitForElement(WebDriverBy::xpath('//button[@data-qa = "qa-btn-accept"]'), 0)) {
            $acceptCookie->click();
            $this->saveResponse();
        }

        $this->logger->debug("set login");
        $login->sendKeys($this->AccountFields['Login']);
        $this->logger->debug("set pass");
        $pass->click();
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $this->logger->debug("click btn");
        $btn->click();

        return true;

        if (!$this->http->FindSingleNode('//title[contains(text()," Login ")]')) {
            return $this->checkErrors();
        }

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $data = [
            'username' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
            'recaptcha' => $captcha
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://ecapi.starlux-airlines.com/cosmile/v2/login', json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('(//script[contains(@src, "/enterprise.js?render=")]/@id)[1]') ??
            "6LcK62YcAAAAAHIdmM0xVWtpGqKY_zGFrhck6KBa";

        if (!$key) {
            return false;
        }
//        $this->recognizer = $this->getCaptchaRecognizer();
//        $this->recognizer->RecognizeTimeout = 120;
//        $parameters = [
//            "pageurl"   => $this->http->currentUrl(),
////            "proxy"     => $this->http->GetProxy(),
//            "version"   => "enterprise",
//            "action"    => "CosmileLogin",
//            "min_score" => 0.3,
//            "invisible" => 1,
//            "domain"    => "www.recaptcha.net",
//        ];
//
//        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.3,
            "pageAction"   => "CosmileLogin",
            "isEnterprise" => true,
            "apiDomain"    => "www.recaptcha.net",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//p[@data-qa = "qa-msg-errorAlert"] | //p[contains(text(), "To protect your privacy, please use the OTP sent to your email to verify.")]'), 15);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//p[@data-qa = "qa-msg-errorAlert"]')) {
            $this->logger->error("[Error]: {$message}");

//            if (strstr($message, 'Invalid account or password, please try again.')) {
//                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
//            }

            if (strstr($message, 'Google reCAPTCHA authentication failed')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($sendOTP = $this->waitForElement(WebDriverBy::xpath('//button[@data-qa="qa-btn-confirm"]'), 0)) {
            $sendOTP->click();

            return $this->parseQuestion();
        }

        return $this->checkErrors();

        // login successful
        $response = $this->http->JsonLog(null, 4);

        if (isset($response->data->accessToken)) {
            $this->State['Authorization'] = 'Bearer ' . $response->data->accessToken;
            $this->headers['Authorization'] = $this->State['Authorization'];

            return $this->loginSuccessful();
        }
        // Invalid account or password, please try again.
        $message = $response->message->details[0] ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Invalid account or password, please try again.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Too many attempts, this account is locked, please use forgot password to unlock.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($message, 'System under maintenance.')
                || strstr($message, 'The system is currently busy.')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $q = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Verification code has been sent to E-mail")]'), 10);
        $codeInput = $this->waitForElement(WebDriverBy::xpath('//input[@data-qa = "qa-txt-verificationCode"]'), 0);
        $confirmBtn = $this->waitForElement(WebDriverBy::xpath('//button[@data-qa = "qa-btn-confirm"]'), 0);
        $this->saveResponse();

        if (!$q || !$codeInput || !$confirmBtn) {
            return false;
        }

        $question = "Verification code has been sent to E-mail, and will expire in 5 minutes,";

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");
            return false;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $codeInput->sendKeys($answer);
        $confirmBtn->click();

        $this->waitForElement(WebDriverBy::xpath('//button[@data-qa = "124"]'), 10);// TODO
        $this->saveResponse();

        return $this->loginSuccessful();
    }

    public function ProcessStep($step)
    {
        return $this->parseQuestion();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);

        if (!isset($response->data->id)) {
            return;
        }
        $response = $response->data;
        // Current Valid Award Mileage
        $this->SetBalance($response->memberCard->awardMiles);
        // Name
        $this->SetProperty('Name', beautifulName("{$response->name} {$response->surname}"));
        // COSMILE Member ID
        $this->SetProperty('AccountNumber', $response->id);
        // Status
        switch ($response->memberCard->tier) {
            case 'SITC':
                $status = 'Traveler';

                break;

            case 'TITC':
                $status = 'Adventurer';

                break;

            case 'AITC':
                $status = 'Explorer';

                break;

            case 'RITC':
                $status = 'Insighter';

                break;

            default:
                $status = '';
                $this->sendNotification('refs #18641 - check status');

                break;
        }
        $this->SetProperty('Status', $status);
        // Tier Miles
        $this->SetProperty('TierMiles', $response->memberCard->qualifyingMiles);
        // Sectors
        $this->SetProperty('Sectors', $response->memberCard->qualifyingSectors);

        if ($this->Balance <= 0) {
            return;
        }
        /*
       $this->logger->info("Expiration date", ['Header' => 3]);

       if ($response->memberCard->expiredSixMonths > 0) {
           $this->sendNotification('refs #18641 - check Exp date // MI');
       }
       */
        /*
        $this->http->GetURL('https://ecapi.starlux-airlines.com/loyalty/v1/mile/info', $this->headers);
        $response = $this->http->JsonLog(null, 5);
        $expireSix = $response->result->data[0]->expireSix ?? null;
        $this->logger->debug("expireSix: {$expireSix}");

        if ($expireSix > 0) {
            $this->sendNotification('refs #18641 - check Exp date');
        }
        */
    }

    private function loginSuccessful()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://ecapi.starlux-airlines.com/cosmile/v2/retrieve', $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 5);

        if (!isset($response->data->id)) {
            return false;
        }

        return true;
    }
}
