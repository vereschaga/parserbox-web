<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSearsshop extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->keepCookies(false);


        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::none();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(false);


        //$this->usePacFile(false);
        $this->http->saveScreenshots = true;
        $this->setProxyGoProxies();
    }

    public function IsLoggedIn()
    {
        /*
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.shopyourway.com/secured/settings/account", [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog($this->http->FindPreg("/(\{\"isUser[^\)]+)\);/ims"));

        if (isset($response->personalZoneLoyaltyDataModel->redeemablePoints)) {
            return true;
        }
        */

        return false;
    }

    public function LoadLoginForm()
    {
        // Enter a valid email address
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Enter a valid email address', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL('https://www.sears.com');
        sleep(5);
        $btnToMenu = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "profile-menu")]'), 10);
        sleep(2);
        $login = $this->waitForElement(WebDriverBy::id('username'), 0);

        if (!$login) {
            if (!$btnToMenu) {
                $this->saveResponse();

                if ($this->http->FindSingleNode('//h2[contains(text(), "Checking if the site connection is secure")]')) {
                    throw new CheckRetryNeededException(2);
                }

                return false;
            }

            $this->driver->executeScript('setInterval(()=>{let closePopupBtn = document.getElementById("ltkpopup-close-button"); if (closePopupBtn) closePopupBtn.click();}, 500)');
            $this->driver->executeScript('try { document.getElementById("onetrust-accept-btn-handler").click(); } catch (e) {}');
            sleep(1);
            $this->saveResponse();
//        $btnToMenu->click();
            $this->driver->executeScript('try { document.querySelector(\'div[class *= "profile-menu"]\').click(); } catch (e) {}');
            $btnToForm = $this->waitForElement(WebDriverBy::xpath('//div[starts-with(@class, "loggedDivOpened")]//a[starts-with(@class, "profile-link-title") and contains(text(), "Dashboard")]'),
                2);

            if (!$btnToForm) {
                $this->saveResponse();

                if ($this->waitForElement(WebDriverBy::xpath('//div[contains(@class, " profile-menu ")]/span[starts-with(text(), "Hi ")]'),
                    2)) {
                    return true;
                }

                return false;
            }

            sleep(3);
            try {
                $this->driver->executeScript('try { document.querySelector(\'.profile-link-title, .profile-login-container .btn\').click(); } catch (e) {}');
            } catch (NoSuchDriverException $e) {
                $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 5);
            }
        }

        try {
//        $btnToForm->click();


            $login = $this->waitForElement(WebDriverBy::id('username'), 30);
            $this->saveResponse();

            if (empty($login)) {
                $this->saveResponse();
                $this->driver->executeScript("let login = document.querySelector('input[id = \"username\"]'); if (login) login.style.zIndex = '100003';");
                $this->driver->executeScript("let pass = document.querySelector('input[id = \"password\"]'); if (pass) pass.style.zIndex = '100003';");
                $login = $this->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 1);
            }

            try {
                if (!$login && $btnToForm->isDisplayed()) {
                    $btnToForm->click();
                    $login = $this->waitForElement(WebDriverBy::id('username'), 9);
                }
            } catch (StaleElementReferenceException | \Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                $this->saveResponse();

                return false;
            }
            $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 0);

            if (!isset($login, $pwd)) {
                $this->saveResponse();

                if ($this->waitForElement(WebDriverBy::xpath('//div[contains(@class, " profile-menu ")]/span[starts-with(text(), "Hi ")]'), 0)) {
                    return true;
                }

                return false;
            }

            $this->driver->executeScript('let remMe = document.getElementById("rememberMe"); if (remMe) remMe.checked = true;');

            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $pwd->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 5);
        }

        if (!$this->checkValidationErrors()) {
            return false;
        }

        // captcha on login form
//        $this->cloudFlareWorkaround($this);
//        $this->clickCloudFlareCheckbox($this, 7);// ff 102
        // captcha on login form
        $this->clickCaptchaCheckboxByMouseV2($this, '//div[@id = "cf-turnstile"]', 35, 27);
        sleep(10);
        $this->saveResponse();
        $btn = $this->waitForElement(WebDriverBy::id('kc-login'), 0);
        if (!$btn) {
            return false;
        }

        $btn->click();

        if (!$this->checkValidationErrors()) {
            return false;
        }

        $res = $this->waitForElement(WebDriverBy::xpath($xpath = '//div[contains(@class, " profile-menu ")]/span[starts-with(text(), "Hi ")] 
        | //span[starts-with(@id, "error-element-")] | //div[@class="alert alert-error"]/span[@class="kc-feedback-text"]'), 15);
        //$this->saveResponse();

        if (
            ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(),"Verify you are human by completing the action below.")]'), 5)
                && !strstr($res->getText(), 'Hi '))
            && $this->clickCaptchaCheckboxByMouseV2($this)
        ) {
            $this->waitForElement(WebDriverBy::xpath($xpath), 20);
            $this->saveResponse();
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# CAS Error occurred
        if ($message = $this->http->FindNodes("//h1[contains(text(), 'CAS Error occurred')]")) {
            throw new CheckException(implode('', $message), ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, an error has occurred
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sorry, an error has occurred.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The server is down for maintenance, try again later
        if ($message = $this->http->FindPreg("/(The server is down for maintenance, try again later)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("http://www.shopyourway.com/");
        //# ShopYourWay is currently unavailable
        if ($message = $this->http->FindNodes("//div[@class = 'DowntimeImage']/text()")) {
            throw new CheckException(implode('', $message), ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Check back soon, we\'re hurrying")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/error occurred while processing your request/ims')) {
            throw new CheckException("An error occurred while processing your request.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//img[contains(@src, "site_down")]')) {
            throw new CheckException("You're here, but we're doing some planned maintenance and are temporarily unavailable", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/planned\s*maintenance\s*on\s*our\s*system/i')) {
            throw new CheckException('Shop Your Way Rewards Members — Because of planned maintenance on our system, shopyourwayrewards.com will be temporarily unavailable. We apologize for any inconvenience.', ACCOUNT_PROVIDER_ERROR);
        }
        // Sign-in is temporarily unavailable
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Sign-in is temporarily unavailable")]', null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Temporarily Unavailable
        if ($message = $this->http->FindNodes('//h1[contains(text(),"Service Temporarily Unavailable")]/following::p')) {
            throw new CheckException("Service Temporarily Unavailable", ACCOUNT_PROVIDER_ERROR);
        }
        // Sears.com is temporarily closed for site enhancements
        if ($message = $this->http->FindNodes('//h2[contains(text(),"Sears.com is temporarily closed for site enhancements.")]')) {
            throw new CheckException("Sears.com is temporarily closed for site enhancements.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Sorry, but something went wrong.
        if ($this->http->FindPreg("/<body><h1>HTTP Status 500 - <\/h1></ims")) {
            throw new CheckException("Sorry, but something went wrong.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Internal Server Error
        if ($this->http->FindPreg("/<TITLE>\s*Internal\s*Server\s*Error\s*<\/TITLE>/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->driver->executeScript('setInterval(()=>{let closePopupBtn = document.getElementById("ltkpopup-close-button"); if (closePopupBtn) closePopupBtn.click();}, 500)');

        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(@class, " profile-menu ")]/span[starts-with(text(), "Hi ")]'), 2)) {
            return true;
        }

        $error = $this->http->FindSingleNode('//div[@class="alert alert-error"]/span[@class="kc-feedback-text"]')
            ?? $this->http->FindSingleNode('(//span[starts-with(@id, "error-element-")])[1]');

        if ($error) {
            $this->logger->error("[Error]: {$error}");

            if (stripos($error, 'Invalid username or password') !== false
                || stripos($error, 'Please double-check your email address. It should look like this') !== false
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            // it works
            if (
                $error == 'Invalid Captcha'
                && ($btn = $this->waitForElement(WebDriverBy::id('kc-login'), 0))
                && ($pwd = $this->waitForElement(WebDriverBy::id('password'), 0))
            ) {
                $pwd->sendKeys($this->AccountFields['Pass']);
                $this->saveResponse();
                $btn->click();

                $success = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, " profile-menu ")]/span[starts-with(text(), "Hi ")]'), 10);
                $this->saveResponse();

                if ($success) {
                    return true;
                }
            }

            $this->DebugInfo = $error;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse(): void
    {
        $balance = $this->waitForElement(WebDriverBy::xpath('//span[starts-with(@class, "points-total")]'), 10);
        $this->saveResponse();

        try {
            $currentUrl = $this->http->currentUrl();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 5);
        }
        $this->logger->debug("[Current URL]: {$currentUrl}");

        if (!$balance && $currentUrl !== 'https://www.sears.com/universalprofile/dashboard') {
            $this->http->GetURL("https://www.sears.com/universalprofile/dashboard");
            if (
                $this->waitForElement(WebDriverBy::xpath('//p[contains(text(),"Verify you are human by completing the action below.")]'), 5)
                && $this->clickCaptchaCheckboxByMouseV2($this)) {
                sleep(5);
            }
            $balance = $this->waitForElement(WebDriverBy::xpath('//span[starts-with(@class, "points-total")]'), 10);
            $this->saveResponse();
        }

        // You have $0.00 in points
        // (0 points)
        $this->SetProperty('PointsWorth', $this->http->FindSingleNode('//span[starts-with(@class, "points-value")]', null, true, '/([$\d.]+)/'));
        $this->SetBalance($this->http->FindSingleNode('//span[starts-with(@class, "points-total")]', null, true, '/(\d+) points/'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode('//li[contains(text(), "Join Our Free Loyalty Program")]')) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // AcountID: 5023629, 4057420, 4057420 etc
            $dollars = $this->http->FindSingleNode('//span[starts-with(@class, "points-currency")]/span[@class = "dollars"]', null, true, '/([$\d.]+)/');
            $cents = $this->http->FindSingleNode('//span[starts-with(@class, "points-currency")]/sup[@class = "cents"]', null, true, '/\s*(\d+)/');

            if (isset($dollars) && (isset($cents) || $dollars === '$0')) {
                $this->SetBalanceNA();
                $this->SetProperty('PointsWorth', $dollars . ($cents ? '.' . $cents : ''));
            // AccountID: 2529774, 2201823, 636601
            } elseif ($this->http->FindSingleNode('//li[contains(text(), "Looks like you don\'t have any")]')) {
                $this->SetBalanceNA();
            }
        }

        // Shop Your Way Member Status
        $this->SetProperty('Status', $this->http->FindSingleNode('//span[@class="vip-member syw-status"]'));

        $toProfile = $this->waitForElement(WebDriverBy::xpath('//a[@href="/universalprofile/managemyaccount"]'), 0);

        if (!$toProfile) {
            $this->saveResponse();

            return;
        }
        $this->driver->executeScript('document.querySelector(`a[href="/universalprofile/managemyaccount"]`).click();');
        $this->increaseTimeLimit();
        $name = $this->waitForElement(WebDriverBy::xpath('//label[@class="labelHead" and text() = "Name"]/following-sibling::h4[1]'), 2);
        $this->saveResponse();
        // Name
        try {
            $this->SetProperty('Name', beautifulName($name ? $name->getText() : null));
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        try {
            $this->driver->executeScript('let links = Array.from(document.querySelectorAll(".mat-tab-label-content")).filter(link => link.textContent.includes("Points & Rewards")); if (links.length > 0) links[0].click();'); // click to switch to "Points & Rewards" tab
            $number = $this->waitForElement(WebDriverBy::xpath('//p[starts-with(text(), "Member #:")]/span'), 2);
            $this->saveResponse();
            // Member #
            $this->SetProperty('Number', $number ? $number->getText() : null);
            $this->increaseTimeLimit();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
    }

    private function searsAuth()
    {
        $this->logger->notice(__METHOD__);
//        $this->http->SetProxy($this->proxyReCaptchaIt7());
        $this->setProxyBrightData();
        $this->http->GetURL('https://www.sears.com/');

        if ($message = $this->http->FindSingleNode('//p[contains(., "re performing some maintenance at the moment.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(2, 0);
        }

        $this->http->GetURL('https://www.sears.com/realms/rlmprd/protocol/openid-connect/auth?client_id=igssears&redirect_uri=https%3A%2F%2Fwww.sears.com%2F%3FstoreId%3D10153%26catalogId%3D12605%26langId%3D-1&state=3a230659-1ed6-4143-b0ee-110bf7de01b1&response_mode=query&response_type=code&scope=openid&nonce=ddf7b954-e77a-483c-a0a0-89f6badc7538&code_challenge=c6kaw2RyAMZHB2SN16NJSMrcageL3VqkCcFvZQzUQls&code_challenge_method=S256');

        if (!$this->http->ParseForm("kc-form-login")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("rememberMe", "on");
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $headers = [
            "Accept"           => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
            "Accept-Language"  => "en-US,en;q=0.5",
            "Content-Type"     => "application/x-www-form-urlencoded",
        ];

        $abck = [
            // 0
            "0283DE7CDD3B2B5838DB4C587D1AE670~0~YAAQP2l8aPmXUFKEAQAANdD5YAgP2UVM0os21Eh6NFuRnVQEvfisy8FK1hh7nFcRPJoUFylP6RgyqVT42LmDHW1sVwri91EfLQ8jPuQP+4WguJKV2TjJpFlzNQMVYkjKBon608/3EEF45gixCksxAw5yUu4+wG9F+WwsaaV431BbNNm9QOpGxfKbpugxMrrZYLig8+tz97tu8i+0pjUj5Pww4/LiafGLjaJ3v27NI+Nk6Xsxw7KfnGGYSFzkBGXCzazauBjoX66ZECfvqX0JvmaDVRQMDErzYUCi4qfDj9RSgDs3fPmgVTrt/ipB47HTOQl1VJ6wBHyKonzy5ML6bKjJKQLNgAOWkwCNEEcjAiL6rTy+Z0hWNfF2BZtY0n2r2zaMHABVglj9ueMkgT386RCAzp/3C+aW~-1~-1~-1",
            // 1
            "7247681F7FCC5590086FB9939ABCA0B9~0~YAAQVdcwFxFIZlqEAQAAyXotYQiPPUA0ig9+/vtQ2LgqPNieyQZjU9gu24rvDT6bKGwR7V31F8j0CjS02erlxx2hODHDsWM+B132wXEIW2nwmBh7W5mt+PWDxKOVw2CowesuHSzGrdZU72NpO2xc0f/NGS3eQnyW39SrFJZkFVP1twlDoIpZeDEx+5zeHLoRfwu2w5C3oKoRIrUKCvcJd/Bw3CefgQCywyrv3LFbXKC4FWBfuOiSnDUyrzL6Ly/lkI6bMn551Y1XunGhzmOMNVhgznBTA2aBv+0giL2S7FN85ZMGX++HONaEfmzGyYEmjwT192equnHJq4ixCcd3r6GjbBV/hq1P9cNlKy/Bat3qNA1tt/z5WJfijfekvkaJ7fDYvAR3DgQ/piBEwjqbg2qAP2A/2ps=~-1~-1~-1",
            // 2
            "506A3DF26C47C523A000FB1543D56E99~0~YAAQP2l8aL7cXXyEAQAAGFtCswg1zZ7PK6qRvL+5KL7sJjO0f2QuN2ZgjL+GROdajT9uFqmP3Av1RLYFifmTIAhAJDkRF1f7zQBmo9WKXfv3ZtbX3mXhomk9HAbnkYmpdqDyzIOhr3hHHXKS7xEVofoBJzVjaihVmYZ0+Icy2YojXoo3gEZWP0S1KTinwy2c/jGE80vKMT45aO6tMiMdWRz2CvIgV7XmjZrTPXzjFGuPj6LsF6qQd2EoqUBziic79jn98imVbcqiupRM7+52xJGxsqbnovkwpG5Xt38zI9HJfupufUhpwMKPKeuYlMudl6OKv2RoKnN0ShsCKm/rFOONnxf/ycTRnuZ6zTpeVZhmCquEk8cVUgGfhWTL5cWs4alhCSAY8GPnTYHA4xgleAIxFO0mpgWm~-1~||-1||~-1",
            // 3
            "C38DF35C2FCF79C2295B3F45AE1F347D~0~YAAQrfzDFz1QGaaEAQAAnkVJswiXFczfOOZFcS3/Xk34LQx4ost71VI25xWcll9Oe4xxc46BUdQBIb2LYE4Jh9riuqZ9anx/JGLt35xVS0xDBu2BMGezxjotjcRW6v0D+mx8MvkHcGVMksRSIW9lRAhs9W+Fp6+YMsqNHXxif3HI1CBaL1SRyaTtxEsv4LoVgkTYIAirVvWCRK1+S3TrtY4oRSJO0TgGgVs/ti8ku65PLMoVKldO4+8U1dxH9AWf/uT0vUT+4+unbbvOUy+9I4XEbBKZt+kXWGxT1al1bWItP0VORkWeSW2TTE4J8+NRKct9a/3tM+3vbqpiE64P4iH/qQByk1JOrx3vXx/Derh+enqf3vfRaZP8359jYNo0I5XA9qcD+VpQxDKyKJ5qtBWmYYZfLfQ=~-1~||-1||~-1",
            // 4
            "E74CCF256F835ED573C2F07B19170A5D~0~YAAQNzovF/xk7KGEAQAAdmRDswjFhholp3T/x69QP0ZFAWgsQqFQ9uo6nCE6PoIz1fj0yfT34ACucXUaqem8sBfELiCB/wLs7S1/F3Y8ExK6FtX787btrumj1gDY8z4ePnQCc+VzFhlmj2QR8UG4xKLMBC4fUg8mjAKQMZlEkDGntp2jdPUClYpqSXARfpMF0B0oXOd2gA0/vLtoLHa3R41smXgXs4BNk7lXGf9KU+IPUWh4kwG79Z5j5G22I7Lq9nVD2ODsU87FGo6j1JkhAN19ofWTTOiEiXZK0s+e+X/Oe7jF60XRQumaE7e0MUYEs5JuPxgEGp6D0oZytD/b+rjIkO2pHtEWeT7gDush5hyGklnPgM8CSnHpQlYd7cDtICqUy9k3W2GWsV/d2r82K9EgHO8G/EUL~-1~-1~-1",
            // 5
            "9CB6175517C36D47A75443AACF83E43C~0~YAAQrfzDF+RPGaaEAQAA7+tIswhKlXQ1XYl63qbFxewoZqHa3gmHLOLxGylZIjIVrDOZKnG4M5dP0rgD6wAKRHDiyegGpJI5kcYjpsmrHIPcNI9Crd8Z9HxOnrKgPeCK+wS/SIXHENr2i8fBs/0hn7H3KQFnEhHHcFl/N7f+HRsbEYvqOQ8hVQBDiOutsAvsvz9xx85DgbgbZFdOBW6hN0iL0W822W3FMZ/MGBH5MnnyJbK+NAkb1+DPigva4QxcrQN/SjWV5W8tm2PBErM5hm1fPgHhCNwyfVfR4TAuYzXawsLgxellvZSd2V95kMHM/gDnRUg1Q0vVF/D4tljNMZKLfAmH1QTaDhC3GHWA4cxfAU7ty+6B0AGHydwNQzn8NNHvRDeznWTxQ3asQ9ZbwLUK/0nCgks=~-1~||-1||~-1",
        ];
        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";
        $this->http->setCookie("_abck", $abck[$key], ".sears.com"); // todo: sensor_data workaround

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }

        $code = $this->http->FindPreg("/&code=([^&]+)/", false, $this->http->currentUrl());

        if (!$code) {
            $loginerrordesc = $this->http->FindSingleNode("//div[contains(@class, 'alert-error')]");
            $this->logger->error("[Error]: {$loginerrordesc}");

            if ($loginerrordesc) {
                $this->DebugInfo = $loginerrordesc;
            }

            // Incorrect password, please try again or reset your password
            if ($loginerrordesc == 'Invalid username or password.') {
                throw new CheckException('Incorrect password, please try again or reset your password', ACCOUNT_INVALID_PASSWORD); // message from https://www.shopyourway.com/
            }

            if ($loginerrordesc == 'For security reasons, your account has been locked. Please <a href=\'javascript:;\' class=\'forgotPassword\'>Reset your password</a>') {
                throw new CheckException('For security reasons, your account has been locked. Please reset your password.', ACCOUNT_LOCKOUT); // message from https://www.shopyourway.com/
            }

            if ($this->http->Response['code'] == 403) {
                $this->DebugInfo .= " | need to upd sensor_data";
            }

            return false;
        }

        $data = [
            "code"          => $code,
            "grant_type"    => "authorization_code",
            "client_id"     => "igssears",
            "redirect_uri"  => "https://www.sears.com/?storeId=10153&catalogId=12605&langId=-1",
            "code_verifier" => "W4XhKprmo3On6dn0VKjG6Eesp5CVH39hHsqsO51w73Rm0GQ7DYNbQTPkIpeDW0WFeXeKYQyaWdjuKzHmumdgaM0yJJeRS7xE",
        ];
        $headers = [
            "Accept"          => "*/*",
            "Referer"         => "https://www.sears.com/",
            "Content-type"    => "application/x-www-form-urlencoded",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.sears.com/realms/rlmprd/protocol/openid-connect/token", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->access_token)) {
            return false;
        }

        $this->http->setDefaultHeader("Authorization", "bearer {$response->access_token}");
        $this->http->setDefaultHeader("Accept", "application/json");

        $this->logger->info("Parse", ['Header' => 2]);

        $this->http->RetryCount = 0;

        $cust_info = base64_decode(explode('.', $response->access_token)[1]);
        $cust_info = $this->http->JsonLog($cust_info);

        // Points to Expire
        $this->SetProperty("PointsToExpire", $cust_info->custinfo->expiringPoints ?? null);
        // Expiration Date
        if (isset($cust_info->custinfo->expiringPointsDate)) {
            $exp = $cust_info->custinfo->expiringPointsDate;

            if (strtotime($exp)) {
                $this->SetExpirationDate(strtotime($exp));
            }
        }

        // Balance - Available Points
        $this->SetBalance($cust_info->custinfo->points ?? null);
        // Points worth
        if (isset($cust_info->custinfo->pointsDollar)) {
            $this->SetProperty("PointsWorth", "$" . $cust_info->custinfo->pointsDollar);
        }
        // Qualified spending on this year
        if (isset($cust_info->custinfo->spendingYear)) {
            $this->SetProperty("QualifiedSpending", "$" . $cust_info->custinfo->spendingYear);
        }
        // Spendings away from next level
        if (isset($cust_info->custinfo->nextLevel)) {
            $this->SetProperty("ToNextLevel", "$" . $cust_info->custinfo->nextLevel);
        }

        $this->http->GetURL("https://www.sears.com/api/profile/ws/personalinfo/fetch?upid=3");
        $response = $this->http->JsonLog();
        // Name
        $firstName = $response->firstName ?? null;
        $lastName = $response->lastName ?? null;
        $this->SetProperty("Name", beautifulName("{$firstName} {$lastName}"));

        $loyaltyId = $response->loyaltyId ?? null;

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && ($loyaltyId === "null" || $loyaltyId === "")
        ) {
            $this->SetWarning(self::NOT_MEMBER_MSG);

            return false;
        }

        // Membership #
        $this->SetProperty("Number", $loyaltyId);

        $this->http->PostURL("https://www.sears.com/api/profile/ws/memberlookup?upid=3", "{\"emailId\":\"{$this->AccountFields['Login']}\"}", [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json;charset=utf-8",
        ]);
        $response = $this->http->JsonLog();
        // Status
        $this->SetProperty("Status", $response->members[0]->memberEarnType ?? null);
//        // Status
//        if (isset($response->personalZoneLoyaltyDataModel->vipStatus->nextVipLevelString)) {
//            $status = $response->personalZoneLoyaltyDataModel->vipStatus->nextVipLevelString;
//
//            if (strstr($status, 'Silver')) {
//                $this->SetProperty("Status", "Member");
//            } elseif (strstr($status, 'Gold')) {
//                $this->SetProperty("Status", "VIP Silver");
//            } elseif (strstr($status, 'Platinum')) {
//                $this->SetProperty("Status", 'VIP Gold');
//            } elseif ($status === '') {
//                $this->SetProperty("Status", 'VIP Platinum');
//                unset($this->Properties['ToNextLevel']);
//            }
//        }// if (isset($response->personalZoneLoyaltyDataModel->vipStatus->nextVipLevelString))
        // Member Since
        if (isset($response->members[0]->memberSinceDate)) {
            $this->SetProperty("MemberSince", date("m/d/Y", strtotime($response->members[0]->memberSinceDate)));
        }

        // AccountID: 1628646, 2523545
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Status'])
            && isset($response->members[0]->totalRedeemablePoints)
        ) {
            // Balance - Available Points
            $this->SetBalance($response->members[0]->totalRedeemablePoints ?? null);
            // Points worth
            $this->SetProperty("PointsWorth", "$" . number_format($response->members[0]->totalRedeemablePoints / 1000, 2));

            return false;
        }
        // AccountID: 3347949
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
            && !empty($this->Properties['Number'])
            && $this->http->Response['code'] == 404
        ) {
            $this->SetWarning(self::NOT_MEMBER_MSG);

            return false;
        }

        $this->http->GetURL("https://www.sears.com/api/profile/ws/dashboardOrders/surprisePoints?upid=3");
        $response = $this->http->JsonLog();
        // Surprise Points
        $this->SetProperty("SurprisePoints", $response->totalSurprisePoints ?? null);

        if (!empty($response->totalSurprisePoints)) {
            $this->sendNotification("totalSurprisePoints not 0 - refs #20551 // RR");
        }

        // Surprise Points Worth
        $this->SetProperty("SurprisePointsWorth", $response->totalSurprisePointsWorth ?? null); //todo: wrong var

        return false;
    }

    private function checkValidationErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($errorEl = $this->waitForElement(WebDriverBy::xpath('//div[(@id = "unamemessage" or @id = "pwdmessage") and string-length(normalize-space()) > 1]'), 0)) {
            $error = $errorEl->getText();
            $this->logger->error($error);
            if (stripos($error, "doesn't meet our requirements") !== false) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            if (stripos($error, "doesn't meet our requirements") !== false) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            $this->DebugInfo = $error;

            return false;
        }

        return true;
    }
}
