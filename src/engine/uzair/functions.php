<?php

use AwardWallet\Engine\ProxyList;

// similar with TAAG (diff - template)
class TAccountCheckerUzair extends TAccountChecker
{
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private string $token;
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyDOP();
        $this->http->setHttp2(false);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['authorization'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            $this->http->setDefaultHeader("authorization", $this->State['authorization']);

            return true;
        }

        return false;
    }
    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;

        try {
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://ffp.uzairways.com/?lang=en");
            } catch (UnknownServerException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "cardNumber"]'), 7);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Login")]'), 0);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->savePageToLogs($selenium);
                 return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            sleep(2);
            $this->savePageToLogs($selenium);
            $button->click();

            $logout = $selenium->waitForElement(WebDriverBy::xpath("//button[@class='MuiButtonBase-root MuiIconButton-root MuiIconButton-colorInherit MuiIconButton-sizeMedium css-o648fm']"), 5);
            $this->savePageToLogs($selenium);

            if (!$logout) {
                $error = $selenium->waitForElement(WebDriverBy::xpath("//p[
                    contains(text(), 'Wrong card number or password')
                    or contains(text(), 'Member with status \"Dormant\" is not allowed to login')
                    or contains(text(), 'Fail ReCaptcha validation')
                    or contains(text(), 'Passwords do not match')
                ]"), 0);

                if ($error) {
                    $message = $error->getText();
                    $this->logger->error("[Error]: {$message}");

                    if (strstr($message, 'Fail ReCaptcha validation')) {
                        $this->DebugInfo = "Fail ReCaptcha validation";
                        return false;
                    }

                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                return false;
            }

            $jwt = $selenium->driver->executeScript("return localStorage.getItem('JWT');");
            $this->logger->debug("Token: $jwt");

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            return $jwt;
        } catch (UnknownServerException | NoSuchDriverException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            $retry = true;
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->logger->debug("[attempt]: {$this->attempt}");

            throw new CheckRetryNeededException(3, 5);
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            throw new CheckException('Wrong card number or password', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $result = $this->selenium();
        if (is_string($result)) {
            $this->token = $result;
            return true;
        } else {
            return $result;
        }
        $this->http->GetURL('https://ffp.uzairways.com/?lang=en');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $key = $this->http->FindPreg("/\"customerRecaptchaPublicKey\":\"([^\"]+)/");

        $captcha = $this->parseCaptcha($key);

        if ($captcha === false) {
            return false;
        }

        $data = [
            "password"   => $this->AccountFields['Pass'],
            "cardNumber" => $this->AccountFields['Login'],
            "token"      => $captcha,
        ];
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json",
            "Origin" => "https://ffp.uzairways.com",
            "Referer" => "https://ffp.uzairways.com/"
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://aloyal-ep.prod.apps.prod01.making.ventures/app/rest/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;



        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($this->token)) {
            //$this->captchaReporting($this->recognizer);
            $this->State['authorization'] = "Bearer {$this->token}";

            return $this->loginSuccessful();
        }

        $message =
            $response->errorCode
            ?? $response->message
            ?? null
        ;

        if ($message) {
            $this->logger->error("[errorCode]: {$message}");

            if ($message == 'Fail ReCaptcha validation') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->captchaReporting($this->recognizer);

            if ($message == 'forbiddenLoginWithExpiredStatus') {
                throw new CheckException('Member with status "Expired" is not allowed to login', ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'passwordsDoNotMatch') {
                throw new CheckException('Passwords do not match', ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'forbiddenLoginWithDormantStatus') {
                throw new CheckException('Member with status "Dormant" is not allowed to login', ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'badCardNumber'
                || $message == 'auth.errorOccurred'
            ) {
                throw new CheckException('Wrong card number or password', ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $profile = $response->data->ProfileNew ?? null;
        // Name
        $this->SetProperty('Name', beautifulName($profile->firstname . " " . $profile->lastname));
        // Membership
        $this->SetProperty('Membership', $profile->cardNumber ?? null);
        // Level
        $this->SetProperty("Status", $profile->levelId);
        // Used
        $this->SetProperty("UsedMiles", $profile->usedMiles);
        // For next level
        $this->SetProperty("ForNextLevel", $profile->milesForNextLevel);
        // Total received
        $this->SetProperty("TotalReceived", $profile->totalMilesReceived);
        // Balance - Available
        $this->SetBalance($profile->availableMiles);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//body[contains(text(), "404 - Not Found")]')) {
            $this->http->GetURL("https://www.uzairways.com/en/cabinet");

            if ($message = $this->http->FindSingleNode('//p[contains(text(), "For technical reasons, the service is temporarily unavailable. We apologize for the inconvenience.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"        => "*/*",
            "Content-Type"  => "application/json",
            "authorization" => $this->State['authorization'],
            "Origin"        => "https://ffp.uzairways.com",
            "Referer"       => "https://ffp.uzairways.com/",
        ];
        $data = [
            "variables" => [],
            "query"     => "{\n  ProfileNew {\n    cardNumber\n    lastname\n    firstname\n    memberTypeId\n    enrolDate\n    registrationSourceId\n    initialsId\n    title\n    genderId\n    contactPreferenceId\n    birthDay\n    newMember\n    lastFlightDate\n    levelId\n    statusId\n    totalBonusMilesReceived\n    totalMilesReceived\n    statusMilesCurrentYear\n    availableMiles\n    expiredMiles\n    milesForNextLevel\n    milesPercentForNextLevel\n    usedMiles\n    nextLevel\n    nationalityId\n    languageId\n    passports {\n      id\n      number\n      dateTo\n      __typename\n    }\n    emails {\n      id\n      email\n      __typename\n    }\n    phones {\n      id\n      countryCode\n      number\n      __typename\n    }\n    __typename\n  }\n}",
        ];
        // provider bug fix
//        $this->http->RetryCount = 0;
        $this->http->PostURL("https://aloyal-ep.prod.apps.prod01.making.ventures/app/graph", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->data->ProfileNew)) {
            $this->http->setDefaultHeader("authorization", $this->State['authorization']);

            return true;
        }

        return false;
    }

    private function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV2TaskProxyless",
            "websiteURL"   => 'https://ffp.uzairways.com/auth/login',
            "websiteKey"   => $key,
            //"isInvisible" => true
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => 'https://ffp.uzairways.com/auth/login',
            //"proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
//            "version"   => "v3",
//            "min_score" => 0.3,
//            "action"    => "login",
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
