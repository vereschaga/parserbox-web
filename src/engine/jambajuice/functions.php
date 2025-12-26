<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerJambajuice extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    private const REWARDS_PAGE_URL = 'https://www.jamba.com/';

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.jamba.com/signin');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        /*
        $this->http->GetURL('https://www.jamba.com/api/auth/csrf');
        $response = $this->http->JsonLog();

        if (empty($response->csrfToken)) {
            return $this->checkErrors();
        }

        $keyCaptcha = $this->parseCaptcha();

        if ($keyCaptcha === false) {
            return false;
        }

        $data = [
            'username'    => $this->AccountFields['Login'],
            'password'    => $this->AccountFields['Pass'],
            "redirect"    => "false",
            "token"       => "{\"token\":\"{$keyCaptcha}\",\"action\":\"login\"}",
            "csrfToken"   => $response->csrfToken,
            "callbackUrl" => "https://www.jamba.com/signin",
            "json"        => true,
        ];

        $this->http->RetryCount = 0;
        $headers = [
            "Accept"       => "*
        /*",
            "Referer"      => "https://www.jamba.com/signin",
            "Content-Type" => "application/x-www-form-urlencoded",
            "Origin"       => "https://www.jamba.com",
        ];
        $this->http->PostURL('https://www.jamba.com/api/auth/callback/credentials', $data, $headers);
        $this->http->RetryCount = 2;
        */

        $this->seleniumAuth();

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (
            isset($response->url)
            || $this->http->currentUrl() == 'https://www.jamba.com/'
            || $this->http->FindSingleNode('//p[@data-testid="txt_username" and normalize-space(.) != ""]')
        ) {
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        if ($this->http->currentUrl() == 'https://www.jamba.com/signin?error=409') {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->currentUrl() === 'https://www.jamba.com/api/auth/error?error=401') {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("The email or password entered is incorrect.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[@class = "toast-content"]/div[not(contains(text(), "You’re in"))]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'The email or password entered is incorrect')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $headers = [
            "Accept"               => "application/json, text/plain, */*",
            "x-focus-brand"        => "jamba",
            "x-focus-app"          => "web",
            "x-focus-app-v"        => "v1",
            "X-Frame-Options"      => "DENY",
            "x-focus-app-deviceid" => "16812366",
            "Authorization"        => "Bearer {$response->user->access_token}",
            "Origin"               => "https://www.jamba.com",
        ];
        // Name
        $this->SetProperty('Name', beautifulName($response->user->first_name . ' ' . $response->user->last_name));

        $this->http->GetURL('https://apiprd.jamba.com/prod/v1/content/template/jamba-web-homepage/lastupdated', $headers);
        $this->http->JsonLog();

        // Rewards
        $this->http->GetURL('https://apiprd.jamba.com/prod/v2/rewards?event_filter=rewards', $headers);
        $responseRewards = $this->http->JsonLog();
        $rewards = $responseRewards->data;
        $this->logger->debug("Total {$responseRewards->count} rewards were found");

        foreach ($rewards as $reward) {
            $displayName = $reward->name;
            $this->AddSubAccount([
                "Code"           => "Reward" . md5($displayName),
                "DisplayName"    => $displayName,
                "Balance"        => $reward->points,
            ], true);
        }

        // Offers
        $this->http->GetURL('https://apiprd.jamba.com/prod/v2/rewards?event_filter=deals', $headers);
        $responseDeals = $this->http->JsonLog();
        $offers = $responseDeals->data;
        $this->logger->debug("Total {$responseDeals->count} offers were found");

        foreach ($offers as $offer) {
            $displayName = $offer->name;
            $exp = strtotime($offer->expires);
            $this->AddSubAccount([
                "Code"           => "RewardOffer" . md5($displayName) . $exp,
                "DisplayName"    => $displayName,
                "Balance"        => null,
                "ExpirationDate" => $exp,
            ], true);
        }

        $this->http->GetURL('https://apiprd.jamba.com/prod/v2/rewards/balance', $headers);
        $responseBalance = $this->http->JsonLog();
        // Status
        $this->SetProperty('Status', beautifulName($responseBalance->data->membership->level->name));
        // Balance - Available Points
        $this->SetBalance($responseBalance->data->points->available);
        // Points Until Next Reward
//        $this->SetProperty('PointsUntilNextReward', $this->http->FindSingleNode('(//div[contains(@class, "progress-radial-component")]//span[contains(@class, "remaining-points")])[1]', null, true, '/(\d+) more pts/'));
        // 200/1200 pts until next tier
//        $this->SetProperty('PointsUntilNextTier', beautifulName($this->http->FindSingleNode("//p[contains(@class,'progress-milestone')]", null, false, '#(\d+)/\d+ pts#')));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = "6LfPz_YpAAAAAPOAdmSuDoNPC7d0JgbepQ7_Vd5L";

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => "https://www.jamba.com/signin",// $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.7,
            "pageAction"   => "login",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => "https://www.jamba.com/signin",// $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "enterprise",
            "min_score" => 0.7,
            "invisible" => 1,
            "action"    => "login",
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.jamba.com/api/auth/session");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->user->access_token)) {
            sleep(3);

            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;

            $selenium->useFirefoxPlaywright();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.jamba.com/signin");
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'email']"), 3);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'sign-in-button']"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $this->logger->debug("click by btn");
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('//div[@class = "toast-content"]/div[not(contains(text(), "You’re in"))] | //p[@data-testid="txt_username" and normalize-space(.) != ""]'), 10);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);
            }
        } catch (Facebook\WebDriver\Exception\UnknownErrorException | WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }
}
