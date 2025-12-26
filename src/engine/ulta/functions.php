<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerUlta extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.ulta.com/ulta/myaccount/template.jsp?page=profile";

    private $responseData = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->FilterHTML = false;

        if ($this->attempt == 0) {
            $r = rand(1, 2);

            if ($this->attempt == 3) {
                $this->setProxyBrightData(null, 'static', 'au');
                $this->http->setRandomUserAgent(10);
            } elseif ($r == 1) {
                $this->http->SetProxy($this->proxyAustralia());
            } elseif ($r == 2) {
                $this->http->SetProxy($this->proxyReCaptcha());
            }

            $this->setProxyBrightData(null, 'static', 'au');

            $userAgentKey = "User-Agent";

            if (!isset($this->State[$userAgentKey]) || $this->attempt > 2) {
                $this->http->setRandomUserAgent(10);
                $agent = $this->http->getDefaultHeader("User-Agent");

                if (!empty($agent)) {
                    $this->State[$userAgentKey] = $agent;
                }
            } else {
                $this->http->setUserAgent($this->State[$userAgentKey]);
            }
        }
        $this->http->setDefaultHeader("Origin", "https://www.ulta.com");
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
//        $this->http->RetryCount = 0;
//        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
//        $this->http->RetryCount = 2;
//
//        if ($this->loginSuccessful()) {
//            return true;
//        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return $this->selenium();

        $this->http->GetURL("https://www.ulta.com/?forceLogin=true&source=account");
        $this->http->GetURL("https://www.ulta.com/services/v1/session/token");
        $response = $this->http->JsonLog();

        if (!isset($response->sessionConfirmationNumber)) {
            if (
                in_array($this->http->Response['code'], [403])
                || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            ) {
                $this->callRetry();
            }

            return $this->checkErrors();
        }

        $this->http->RetryCount = 0;
        $headers = [
            'Accept'             => '*/*',
            'API-Access-Control' => 'dyn_session_conf=' . $response->sessionConfirmationNumber,
            'Cache-Control'      => 'no-cache,no-store,must-revalidate,max-age=-1,private',
            'Content-Type'       => 'application/json',
            'X-Requested-With'   => 'XMLHttpRequest',
            'Referer'            => 'https://www.ulta.com/?source=account',
            'Expires'            => '-1',
            'x-dtpc'             => 'ignore',
        ];
        $data = [
            'login'      => $this->AccountFields['Login'],
            'password'   => $this->AccountFields['Pass'],
            'autoLogin'  => true,
            'sourcePage' => 'default',
        ];
        $this->http->PostURL('https://www.ulta.com/services/v5/user/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (
            isset($response->data->success) && $response->data->success
            || $this->http->FindSingleNode("//p[contains(text(), 'Email Address:')]")
            || $this->http->FindNodes("//div[contains(@class, 'Avatar')]")
        ) {
            sleep(2);

            return true;
        }

        $error = $this->http->FindSingleNode("//div[contains(@class, 'ResponseMessages__message--error')] | //span[@data-error-code=\"wrong-email-credentials\" and normalize-space(.) != '']");

        if ($error) {
            $this->logger->error("[Error]: {$error}");

            if (
                strstr($error, 'The email address/username or password you entered is invalid. Please try again.')
                || strstr($error, 'Your password has expired.')
                || $error == 'The email address or password you entered is incorrect. Please try again.'
                || $error == 'Please sign in using email address, or call 866-983-8582 for help.'
                || $error == 'Please sign in using your email address'
                || strstr($error, 'The email address or password you entered is incorrect.')
                || strstr($error, 'We couldn\'t log you in. Please check your email and password and try again')
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, 'We are currently unable to process your request. Please try again. If you continue to receive this message, call ')
            ) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $error;

            return false;
        }

        if ($message = $this->http->FindPreg('#"message":"(The email address/username or password you entered is invalid. Please try again.)"#')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('#"message":"(Your password has expired\.)#')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($this->http->Response['code'])) {
            if (
            in_array($this->http->Response['code'], [429, 409])
            && $this->http->FindPreg('#<html><head><title>Error<\/title><\/head><body>Conflict<\/body><\/html>#')
        ) {
                throw new CheckRetryNeededException(2, 15);
            }

            if (
            in_array($this->http->Response['code'], [408])
            && $this->http->FindPreg('#^Request Timeout$#')
        ) {
                $this->callRetry();
            }
        }

        // no auth, no errors
        if (in_array($this->AccountFields['Login'], [
            'phych1@hotmail.com',
            'eestonge3@gmail.com',
            'amendenj@gmail.com',
            'ceconrado5@gmail.com',
            'meijioro@gmail.com',
            'wanchailim@msn.com',
            'ellenrichards11@gmail.com',
            'ashleycook38@gmail.com',
        ])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        /*
        if (!$this->http->FindPreg('#/ulta/myaccount/template.jsp#', false, $this->http->currentUrl())) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        */

        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode("//p[contains(text(), 'Name:')]/span")
            ?? $this->http->FindSingleNode("//h5[contains(text(), 'Hi, ')]", null, true, "/,\s*([^<]+)/")
        ));

        $data = $this->http->JsonLog($this->responseData);

        // Name
        if (!empty($data->firstName) && !empty($data->lastName)) {
            $this->SetProperty("Name", beautifulName($data->firstName . " " . $data->lastName));
        }

        // Balance - Points Balance
        $this->SetBalance($data->pointsBalance ?? null);
        // Member ID
        $this->SetProperty("Number", $data->loyaltyId ?? null);
        // My Status
        $status = $data->rewardsMemberType ?? null;
        $this->SetProperty("MyStatus", beautifulName($status));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindPreg('/joinedLabel":"Joined ([^\"]+)/') ?? $data->memberSince ?? null);
        // Redeemable Points
        $this->SetProperty("RedeemablePoints", $data->pointRedeem ?? null);

        $headers = [
            "Accept"                    => "*/*",
            "Accept-Language"           => "en-US,en;q=0.5",
            "Accept-Encoding"           => "gzip, deflate, br",
            "Content-Type"              => "application/json",
            "apollographql-client-name" => "ulta-graph",
            "x-ulta-dxl-query-id"       => "NonCachedPage",
            "x-ulta-graph-type"         => "query",
            "x-ulta-graph-sub-type"     => "noncachedpage",
            "x-ulta-graph-module-name"  => "SignInOverLayContainer",
            "x-ulta-client-country"     => "US",
            "x-ulta-client-locale"      => "en-US",
            "x-ulta-client-channel"     => "web",
            "x-forwarded-proto"         => "https",
        ];
        $data = http_build_query([
            'ultasite'      => 'en-us',
            'query'         => urldecode('query%20NonCachedPage(%24stagingHost%3A%20String%2C%20%24previewOptions%3A%20JSON%2C%20%24moduleParams%3A%20JSON%2C%20%24contentId%3A%20String)%20%7B%0A%20%20Page%3A%20NonCachedPage(%0A%20%20%20%20stagingHost%3A%20%24stagingHost%0A%20%20%20%20previewOptions%3A%20%24previewOptions%0A%20%20%20%20moduleParams%3A%20%24moduleParams%0A%20%20%20%20url%3A%20%7Bpath%3A%20%22https%3A%2F%2Fwww.ulta.com%2Faccount%2Frewards%22%7D%0A%20%20%20%20contentId%3A%20%24contentId%0A%20%20)%20%7B%0A%20%20%20%20content%0A%20%20%20%20customResponseAttributes%0A%20%20%20%20meta%0A%20%20%20%20__typename%0A%20%20%7D%0A%7D'),
            'operationName' => 'NonCachedPage',
            'variables'     => ('{"moduleParams":{"gti":"' . $data->gti . '","loginStatus":"hardLogin","retailerVisitorId":"' . $data->retailerVisitorId . '","breakpoint":"XL"}}'),
        ]);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.ulta.com/v1/client/dxl/graphql?" . $data, $headers);

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (in_array($status, ['PLATINUM', 'DIAMOND'])) {
            $this->SetExpirationDateNever();
            $this->SetProperty('AccountExpirationWarning', 'do not expire with elite status');
        } else {
            // Points Expiring
            $pointsExpiring = $this->http->FindPreg('/","pointsExpiringValue":"([^\"]+)/');
            $this->SetProperty("PointsExpiring", $pointsExpiring);

            if ($pointsExpiring > 0 && $pointsEarningDate = strtotime($this->http->FindPreg('/"loggedDate":"([^"]+)","rewardsPoints":"' . $pointsExpiring . 'pts"/') ?? '')) {
                $this->SetProperty('EarningDate', date('m/d/Y', $pointsEarningDate));

                foreach (['March 31', 'June 30', 'September 30', 'December 31'] as $quarterEndDay) {
                    $quarterEndDate = strtotime($quarterEndDay, $pointsEarningDate);

                    if ($pointsEarningDate < $quarterEndDate) {
                        $this->SetExpirationDate(strtotime('+1 year', $quarterEndDate));

                        break;
                    }
                }
            }
        }

        if (isset($response->data->Page->content->modules)) {
            foreach ($response->data->Page->content->modules as $modules1) {
                if ($modules1->type == 'StateWrapper') {
                    foreach ($modules1->modules as $modules2) {
                        if ($modules2->type == 'ColumnContainer') {
                            foreach ($modules2->modules as $modules3) {
                                if (isset($modules3->targetTier) && $modules3->type == 'PLATINUM') {
                                    // Value
                                    $this->SetProperty("Value", $modules3->dollarValueLabel);
                                    // Amount to go
                                    $this->SetProperty("SpendToNextLevel", $modules3->amountToGoValue);

                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name']) && (isset($this->Properties['Number'])
                    || $this->http->FindPreg("/(You do not have payment details in your profile\.)/ims")
                    || $this->http->FindPreg("/(You do not have reminders set on any products\.)/ims"))) {
                $this->SetBalanceNA();
            } elseif ($this->http->FindSingleNode("//h2[contains(text(), 'Welcome,')]", null, true, '/Welcome\,\s*([^<]+)/ims')
                || $this->http->FindPreg("/(You do not have address in your profile\.)/ims")
                || $this->http->FindPreg("/(You do not have payment details in your profile\.)/ims")
                || $this->http->FindPreg("/(You do not have reminders set on any products\.)/ims")) {
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[contains(text(), 'Welcome,')]", null, true, '/Welcome\,\s*([^<]+)/ims')));
                $this->SetBalanceNA();
            }
            // Join our FREE ULTAmate Rewards program
            if ($data && isset($data->rewardsMember) && $data->rewardsMember === false) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            !strstr($this->http->currentUrl(), "https://www.ulta.com/?forceLogin=true")
            && $this->http->FindSingleNode("//p[contains(text(), 'E-mail Address:')]/span")
        ) {
            return true;
        }

        return false;
    }

    private function callRetry()
    {
        $this->logger->notice(__METHOD__);

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
            return;
        }
        $this->markProxyAsInvalid();

        throw new CheckRetryNeededException(4, 1);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Website is currently unavailable
        if ($this->http->FindSingleNode("(//img[contains(@src, 'Ulta/down') or contains(@src, 'spawaitingroom/images/site-down')]/@src)[1]")) {
            throw new CheckException("Website is currently unavailable", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($this->http->FindSingleNode("//img[@src = '/vp/ulta/www/vpwaitingroom/images/site-down_02.jpg']/@src")) {
            throw new CheckException("Our site is taking is quick beauty rest. We'll get you there soon!", ACCOUNT_PROVIDER_ERROR);
        }
        //# An error has occurred while trying to access the page
        if ($message = $this->http->FindPreg("/(We\'re sorry\, an error has occurred while trying to access the page you requested\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // HTTP Status 404
        if ($this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 404')]")
            || (isset($this->http->Response['code']) && $this->http->Response['code'] == 503 && $this->http->FindPreg("/An error occurred while processing your request\./"))) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error
        if ($this->http->FindPreg('/^Internal Server Error/')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $badAccounts = [
            'josephdizon@msn.com',
            'flintrivergal@gmail.com',
            'missalissa',
            'jat112@hotmail.com',
            'meijioro@gmail.com',
            'erseeton@gmail.com',
            'kyleebethngo@outlook.com',
        ];

        if (
            $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry, we can\'t find a ULTA.com page that matches your request,")]')
            && in_array($this->AccountFields['Login'], $badAccounts)
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useGoogleChrome();
            /*
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->disableImages();
            */

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            try {
                $selenium->http->GetURL("https://www.ulta.com/");
//                $selenium->http->GetURL("https://www.ulta.com/auth/login?redirect=%2F&screen=login");
            } catch (TimeOutException $e) {
                $this->logger->debug("TimeoutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            } catch (UnexpectedAlertOpenException $e) {
                $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

                try {
                    $error = $this->driver->switchTo()->alert()->getText();
                    $this->logger->debug("alert -> {$error}");
                    $this->driver->switchTo()->alert()->accept();
                    $this->logger->debug("alert, accept");
                } catch (NoAlertOpenException $e) {
                    $this->logger->debug("no alert, skip");
                }
            }

            if ($cookieAccept = $selenium->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 3)) {
                $cookieAccept->click();
                sleep(1);
            }

            if (!$openFormBtn = $selenium->waitForElement(WebDriverBy::xpath('//button/span[normalize-space() = "Join / Sign in"]'), 3)) {
                $this->savePageToLogs($selenium);

                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return $this->checkErrors();
            }
            $openFormBtn->click(); // will not do anything wrong if form already opened

            if ($authSubmit = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "SignIn__authSubmit")]/button'), 5)) {
                $authSubmit->click();
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[contains(@id, "username")]'), 5);
            $this->savePageToLogs($selenium);

            if (
                !$login
                && ($openFormBtn = $selenium->waitForElement(WebDriverBy::xpath('//button/span[normalize-space() = "Join / Sign in"]'), 0))
            ) {
                $this->savePageToLogs($selenium);
                $openFormBtn->click(); // will not do anything wrong if form already opened

                if ($authSubmit = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "SignIn__authSubmit")]/button'), 5)) {
                    $authSubmit->click();
                }

                sleep(5);
                $selenium->driver->executeScript("let login = document.querySelector('input[id *= \"username\"]'); if (login) login.style.zIndex = '100003';");
                $login = $selenium->waitForElement(WebDriverBy::xpath('//input[contains(@id, "username")]'), 2);
            }

            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@id, 'password')]"), 0);
            $signIn = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'LoginForm__Submit' or @class = 'SignIn__submit']/button | //button[normalize-space(text()) = 'SIGN IN']"), 0);
            $selenium->driver->executeScript('let c = document.getElementById("GiftQuestionnaireModal"); if (c) c.style.display = "none";');

            /*
            if ($closeWindow = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Close sticky email sign-up form"]'), 0)) {
                $closeWindow->click();
                sleep(1);
                $this->savePageToLogs($selenium);
            }
            */

            if (!$login || !$pass || !$signIn) {
                $this->logger->error('something went wrong');
                $this->savePageToLogs($selenium);

                return $this->checkErrors();
            }

            $selenium->driver->executeScript('var remember = document.querySelector(\'.ToggleButton__checkbox, #ulp-stay-signed-in\'); if (remember) remember.checked = true;');
            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $signIn->click();

            $selenium->waitForElement(WebDriverBy::xpath("
                //p[contains(text(), 'E-mail Address:')]/span
                | //div[@class = 'Avatar']
                | //div[contains(@class, 'ResponseMessages__message--error')]
                | //span[@data-error-code=\"wrong-email-credentials\" and normalize-space(.) != '']
            "), 30);
            $this->savePageToLogs($selenium);

            $this->responseData = $selenium->driver->executeScript("return localStorage.getItem('DSOTF_LOGIN_HINT_KEY');");
            $this->logger->info("[Form responseData]: " . $this->responseData);

            if (!$this->http->FindSingleNode("//div[contains(@class, 'ResponseMessages__message--error')]
                | //span[@data-error-code=\"wrong-email-credentials\" and normalize-space(.) != '']")) {
            $this->http->GetURL('https://www.ulta.com/account/all');
            $selenium->waitForElement(WebDriverBy::xpath("
                //a/span/strong[contains(text(), 'Dashboard')]
            "), 10);
            $this->savePageToLogs($selenium);
            }
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo:

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 5);
            }
        }

        return true;
    }
}
