<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\expedia\AuthException;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerOrbitz extends TAccountChecker
{
    use ProxyList;
    use PriceTools;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.orbitz.com/Flights';
    public $removeCookies = true;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $isSelenium = false;

    public static function FormatBalance($fields, $properties)
    {
        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip");
        $this->http->RetryCount = 0;

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->setProxyGoProxies();
        $this->useChromePuppeteer();
        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->isSelenium = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
//        $this->http->RetryCount = 2;
        $tpid = $this->http->FindPreg('/v\.\d+,(\d+)/', false, $this->http->getCookieByName('tpid', '.orbitz.com'));
        $tuid = $this->http->FindPreg('/\\\\"tuid\\\\":(\d+),/');

        if (isset($tpid, $tuid)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Enter a valid email.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Enter a valid email.', ACCOUNT_INVALID_PASSWORD);
        }
        /*
         $this->http->removeCookies();
         if ($this->getCookiesFromSelenium() === true) {
             return false;
         }
         $message = $this->http->FindSingleNode("//div[contains(@class, 'uitk-banner-description')]");
         if (
             $message
             && !strstr($message, 'Explore more in 2023. Soon you’ll be able to sign in and book with one')
             && !strstr($message, "2023'te daha fazla yer keşfedin. Yakında Expedia, Hotels.com")
         ) {
             $this->logger->error("[Error]: {$message}");

             if (
                 $message == "Email and password don't match. Please try again."
                 || $message == "E-posta ve şifre eşleşmiyor. Lütfen tekrar deneyin."
                 || $message == "Your account was paused in a routine security check. Your info is safe, but a password reset is required."
                 || $message == "Hesabınız, rutin bir güvenlik kontrolü sırasında duraklatıldı. Bilgileriniz güvende ancak şifrenizi sıfırlamanız gerekiyor."
                 || $message == "Enter a valid email."
             ) {
                 throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
             }

             if (
                 $message == "Sorry, something went wrong on our end. Please wait a moment and try again."
             ) {
                 throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
             }

             $this->DebugInfo = $message;

             return false;
         }*/
        if ($this->removeCookies) {
            $this->http->removeCookies();
        }

        try {
            $this->http->GetURL("https://www.orbitz.com/user/account");
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        }

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'loginFormEmailInput']"), 15);
        $password = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'loginFormPasswordInput']"), 1);
        $loginButton = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'loginFormSubmitButton']"), 0);
        $this->saveResponse();

        if (!$login || !$loginButton) {
            $this->logger->error('Failed to find login button');

            if (
                $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
                || $this->http->FindPreg("/Access to <span jscontent=\"hostName\"[^>]+>www\.hotels\.com<\/span> was denied/")
            ) {
                $this->markProxyAsInvalid();
                $retry = true;
            }

            if ($this->http->FindSingleNode("//h2[contains(text(), 'My Account Info')]")) {
                return true;
            }

            return false;
        }

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->sendKeys($login, $this->AccountFields['Login'], 5);

//        $login->sendKeys($this->AccountFields['Login']);
        $this->saveResponse();
//        $password->sendKeys($this->AccountFields['Pass']);
        $mover->sendKeys($password, $this->AccountFields['Pass'], 5);
        sleep(2);
        $this->logger->debug("click 'Submit'");
        $this->saveResponse();
        $loginButton->click();
        sleep(1);

        $this->waitFor(function () {
            return $this->waitForElement(WebDriverBy::id("tabs-accountdetails"), 0)
                || $this->waitForElement(WebDriverBy::xpath("//div[@aria-hidden='false']//iframe"), 0)
                || $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'uitk-banner')]//div[contains(@class, 'uitk-banner-description')] | //h3[contains(@class, 'uitk-error-summary-heading')] | //h2[contains(text(), 'My Account Info')]"), 0);
        }, 10);
        $captchaFrame = $this->waitForElement(WebDriverBy::xpath("//div[@aria-hidden='false']//iframe"), 0);
        $this->saveResponse();

        if ($captchaFrame) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        $message = $this->http->FindSingleNode("//div[contains(@class, 'uitk-banner')]//div[contains(@class, 'uitk-banner-description')] | //h3[contains(@class, 'uitk-error-summary-heading')]");

        if ($message) {
            $this->logger->error("[Error]: {$message}");
            // Your account was paused in a routine security check. Your info is safe, but a password reset is required. Make a change with the "Forgot password?" link below to sign in.
            if (
                strstr($message, 'Your account was paused in a routine security check.')
                || $message == 'Email and password don\'t match. Please try again.'
                || $message == 'In order to access your account, a password reset is required.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return true;

        if ($this->attempt > 0) {
            $this->isSelenium = true;
            $this->getCookiesFromSelenium();

            return true;
        }

        $this->http->GetURL("https://www.orbitz.com/user/account");

        $pointOfSaleId = $this->http->FindPreg("/\"pointOfSaleId.\":.\"([^\\\]+)/");
        $uiBrand = $this->http->FindPreg("/\"uiBrand.\":.\"([^\\\]+)/");
        $deviceId = $this->http->FindPreg("/\"deviceId.\":.\"([^\\\]+)/");
        $remoteAddress = $this->http->FindPreg("/\"remoteAddress.\":.\"([^\\\]+)/");
        $guid = $this->http->FindPreg("/\"guid.\":.\"([^\\\]+)/");
        $traceId = $this->http->FindPreg("/\"traceId.\":.\"([^\\\]+)/");
        $tpid = $this->http->FindPreg("/.\"tpid.\":(\d+)/");
        $site_id = $this->http->FindPreg("/_site_id.\":(\d+)/");
        $eapid = $this->http->FindPreg("/.\"eapid.\":(\d+)/");
        $csrf = $this->http->FindPreg("/\,.\"login.\":.\"([^\\\]+).\",.\"mf/");

        if (!$this->http->ParseForm("loginForm") || !$csrf || !$deviceId || !$uiBrand || !$pointOfSaleId) {
            if ($this->http->FindSingleNode('//p[contains(text(), "We can\'t tell if you\'re a human or a bot.")]')) {
                throw new CheckRetryNeededException();
            }

            return $this->checkErrors();
        }

        $captcha = $this->parseFunCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "username"      => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "rememberMe"    => true,
            "channelType"   => "WEB",
            "devices"       => [
                [
                    "payload" => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"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;0400E6tBivBut4aVebKatfMjILHZu/3MoWJGY+Z5F+4MduD9HkmXdvXP4I5pcArZnLJFYTVT79gIH27QoDOmUF4MMWIPArjDreTCT43R5j+o8w7o+AgUjMy73h4wl4H0OlpfuJBsZbc5KTVSlMlmR+8RbGKimL6AeQno7a6TVvUauRDvUSbJ+Ltz3R3+1lLYqHZE1G5+flVDGW7naOkWZafykXUHzghRrLAQb+O5jfppV28Xpq5Pz6pIcrde6K+rNGjlN+Bt9xn7tx6GKBCJQKoPoLFnHNokJC9l3XRUNp4Te6Or/lLi+M/vd/0MPJV737wSihxAuyi8xBtoVNw0bruiRetwcbVEi1FKS3pZqPmXn94IYOvgepxln9j0VfXg9dU4hmdnVfJ6snSAxmwfldFU+mnzCBDW8ORL5X0UtkoBG7/G++DjhL1/kNSbxci8IWwyDX/eJYrNc7ZZ9QWrvZYKbNSCUkaham02Dk9ftvxJxx6pnIJ6aallvWO7P/zAA1mnVagB1dsnJeLd0HwZFZsoI7u4fdGNP6EcxS9GTGQ/PXGtAxCWlpP4SYs//BPd61qf9RKjvZJevR0sf+xD+xeqTTINRCQGbbX7eF2tLN6fC/aIrukwLD3N1NWVZvSNTGL8/A4PFBhI2uE/6D0SsrNZUt8dhOLXjHxwJFDd9XbRk1LCDNxpypgPJSfOq1vG2COfJKCHprnU9VGodXyUKxUgVtZ++LaXDquIDX8icOWPZcz7cCunQS9MKzlVT8kOFdaEZPe3o6D3FNDk9Pb7SWx7XZhtq7VtqYnDY92hNSmr+k28EssPaX5qs07kiN2llaPwY42UpAUwVYh8YD1Y+HjfzY9qpLt2ZL85FbTOvYetVJwMNYbcBqIXgYhPJxX5Cmud4bkIibh3yGpLh7h/QYTtLWdtfGytvAT+blUrdgWkJJSqSpU9TxjnIH6G4joXDjSTzZwYPPZOeqxfZIt4GTWL6ifF4hCMlNAhdLDs+1JC1RG6b81eKp8VTU+fcmTCxHp/YflyjaDZg9uQ2sBcS9tbDnGL9SBAEfPH8XjUEE5s4XAst5SBru1yMS4PNRUn4VsbEsAzZmYNb7n+wnsrNIKp1GZkN9YGR2B9eYJzf/Q1rd2p46nQIYnnFMyjpRbxiOKgpM5nGG0WDnKmK/+/7GwahtQzEmabpCR+Rou1hHsqTefVsok/Eywl34996Pw3218B1y1cVA2MAlmkKmN7Dgov19Pi6e51YicPcEyZ4HLIOXWItz5N2qxx1ud4qmI2cLzhHunYRf3C6kF7U/mVkxCIdO7YwkJKLXY0EJZtiIYy2xWIXpI74FNzShsv5A2DFbDrAmJ1xVS6lKoh17i2nEZUgYW82cN/+YxYIXI0pQyGfvBM6k9UbWAjAkrEz3XC/+yCauFKlyQYsyFFi3sORY+A2CHeI/q7wQQOr1YRFWe4ZAykLGatakAJZZ7dtpYp3VlgEl3TPwSa8qEH87qCWmdbKgXPRTWNeToUEPNlKlQIH1ZGbJUBnFtzQU4er6GD5lw3E/eJjGjRpp6MtwCD+HTyplG5w+NdN1SLkmN41D9HcKbiNFf5+DbuXB07gvdmHVpZxWbqpK+q579R098qC6Xbi5ZQtNjlTmUa6feRJ6fts0kP4q/t6imMryjTr/MEe30k5CNOumikojBNRHU4UWeWdD2bXja2pSlRTGC8d084eYyKCTDLlwSlmssa/jErmAs1LqBQHL/vGRH9tcOH2rgR+GVYZGxiFmr7egW0rpJo086zCRaGF+vkVAqnUC+XxMV2YdIhpdEhdhgeYmnaB0N46ES9pWZAEYdrYncuMA/HQnZYj23THls2GNiGyjbCAynr/TtMt+Ljdl6Cr91qR2Tu48tBLb0JDkgeuZmtmCH82pZkJKnVTqAb2bYC+cYm2LQrDJFqnvqh6m8PtKm6MUbVe1qI8clLlxY941ElYnKdf6BzsQj442OvrseOzPb/1vzliRxpRWtXspHkZpz2BmSbPuuU/6C4PUkN3BkOuS8GItdc1aryPil/amaAwFxuVorvrJ9UArrPTz1mOSzXG+l/bIt4I2N0m4zphhrZGg3iH0fBr61fZFlJt4dAKqHY+/sonaCNjmZ0dr8ZSZzjvik9VA/MJJZ88fvdaVXw05uVfwlUTXO0tQN/ZTaiSFnI1mqM6ONzXfnUeb0m9dqbsq++o+91qmgyH2FSrTtFJ4urrDubkSBgLl0TauxVgprB4BWr9bfQ4WCNMhqGiszCE1S8jw==","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1405,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":26118,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"70201,www.orbitz.com,Orbitz,ULX","requestURL":"https://www.orbitz.com/login","userAgent":"' . $this->http->userAgent . '","placement":"LOGIN","placementPage":"70201","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                    "type"    => "TRUST_WIDGET",
                ],
            ],
            "atoShieldData" => [
                "atoTokens" => [
                    "fc-token"      => $captcha,
                    "rememberMe"    => "",
                    "email"         => $this->AccountFields['Login'],
                ],
                "placement" => "login",
            ],
            "csrfData"      => [
                "csrfToken" => $csrf,
                "placement" => "login",
            ],
        ];

        $headers = [
            "Accept"               => "application/json",
            "accept-encoding"      => "gzip, deflate, br",
            "brand"                => $uiBrand,
            "content-type"         => "application/json",
            "device-type"          => "DESKTOP",
            "device-user-agent-id" => $deviceId,
            "eapid"                => $eapid,
            "pointofsaleid"        => $pointOfSaleId,
            "siteid"               => $site_id,
            "tpid"                 => $tpid,
            "trace-id"             => $traceId,
            "x-mc1-guid"           => $guid,
            "x-remote-addr"        => $remoteAddress,
            "X-USER-AGENT"         => $this->http->userAgent,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.orbitz.com/eg-auth-svcs/authenticate/password", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Site temporarily unavailable.
        if ($message = $this->http->FindPreg("/^Site temporarily unavailable\.$/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindNodes("//div[contains(text(), 'making updates and improvements to the site')]/text()")) {
            throw new CheckException(implode(' ', $message), ACCOUNT_PROVIDER_ERROR);
        }
        // Our service is temporarily down and it appears we’ve been delayed for take off.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our service is temporarily down and it appears we’ve been delayed for take off.')]")) {
            throw new CheckException("We’re Sorry! Our service is temporarily down and it appears we’ve been delayed for take off.", ACCOUNT_PROVIDER_ERROR);
        }
        //# We experienced an error and were unable to complete your request
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We experienced an error and were unable to complete your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're currently working to improve the site.
        if ($message = $this->http->FindPreg("/<strong>(We're currently working to improve the site\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);
        //# The e-mail and password you have entered do not match
        if (($message = $this->http->FindSingleNode("//span[contains(text(), 'The e-mail and password you have entered do not match')]"))
            || ($message = $this->http->FindSingleNode("//*[contains(text(), 'The e-mail address that you have entered is not properly formatted')]"))
            || ($message = $this->http->FindSingleNode("//*[contains(text(), 'Please make another selection and try again')]"))) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, for security purposes we must limit the number of invalid authentication attempts.
        if ($message = $this->http->FindPreg("/Sorry, for security purposes we must limit the number of invalid authentication attempts\./ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[@id = 'wrong-credentials-error-div']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // You may have entered an unknown email address or an incorrect password.
        if ($message = $this->http->FindSingleNode("//h5[contains(text(), 'You may have entered an unknown email address or an incorrect password.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We apologize for our system failure. Please try again.
        if ($message = $this->http->FindSingleNode("//div[@id = 'system-error-div']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Your account has been closed.
         * If you feel this is an error, please contact Orbitz Customer Care.
         */
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Your account has been closed')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We're sorry, your account is in use
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, your account is in use")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /**
         * As per our routine security check, we have reset your account password.
         * Rest assured your account is safe with us – use the “Forgot password?” link to set a new password.
         */
        if ($message = $this->http->FindSingleNode("//h5[contains(text(), 'As per our routine security check, we have reset your account password.')]")
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            !$this->http->FindSingleNode('//div[@class = "sub-header"]//div[@class = "join-content"]//a[contains(text(), "Sign in")]')
            && !$this->http->FindSingleNode("//form[@id = 'login-form']//div[not(@aria-hidden = 'true')]/h5[contains(@class, 'alert-message')]")
            && (
            $this->http->FindNodes('//a[contains(@href, "logout")]')
            || $this->http->FindSingleNode('//li[@class="welcomeText" and contains(text(), "Welcome") and not(contains(text(), "to Orbitz"))]')
            || $this->http->FindSingleNode("//span[@class = 'userName' and contains(text(), 'Welcome')]")
            || $this->http->FindSingleNode("//div[@class = 'headerMemberWelcome' and contains(text(), 'Welcome')]")
            )
        ) {
            return true;
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status === true || $this->isSelenium) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
            $menu = $this->waitForElement(WebDriverBy::xpath('//button[@data-testid="header-menu-button"  and not(contains(@title, "Shop"))]'), 10);

            if ($menu) {
                $this->saveResponse();
//                $menu->click();
                $this->driver->executeScript('document.querySelector(\'button.uitk-button-tertiary-large-icon[data-testid="header-menu-button"]\').click()');

                $this->waitForElement(WebDriverBy::xpath('//div[@data-stid="member-wallet-details"]'), 10);
            }

            $this->saveResponse();

            return $this->loginSuccessful();
        }

        $failure = $response->failure ?? null;
        $requestId = $response->requestId ?? null;
        $message = $response->message ?? null;
        $csrfData = $response->csrfData ?? null;

        if ($status === false && $failure === null && $requestId === null && $csrfData != null) {
            //throw new CheckException("Email and password don't match. Try again.", ACCOUNT_INVALID_PASSWORD);
            throw new CheckRetryNeededException(3, 0);
        }

        if ($message === 'Email constraint not met') {
            throw new CheckException("Enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    /**
     * refs#23097.
     */
    public function Parse()
    {
        $tpid = $this->http->FindPreg('/,"tpid.?":(\d+),/');
        $tuid = $this->http->FindPreg('/,"tuid.?":(\d+),/');

        /*
        $headers = [
            "Accept"       => "*
        /*",
            "Referer"      => "https://www.orbitz.com/",
            "content-type" => "application/json",
            "client-info"  => "blossom-flex-ui,1360ad02b397bf8c095a899df8b4f76ca08b5320,us-east-1",
            "x-page-id"    => "Homepage,U,10",
        ];
        $this->http->PostURL("https://www.orbitz.com/graphql", '[{"operationName":"MemberWalletQuery","variables":{"context":{"siteId":70201,"locale":"en_US","eapid":0,"currency":"USD","device":{"type":"DESKTOP"},"identity":{"duaid":"d421d0bb-1d7e-40a5-b62d-7df50ecfb873","expUserId":null,"tuid":null,"authState":"AUTHENTICATED"},"privacyTrackingState":"CAN_TRACK","debugContext":{"abacusOverrides":[]}}},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"adc0521e69bbc1b11f8dedb043f9141de11efb1c4ed2bad578cd7f6292fa32f1"}}}]', $headers);
        $response = $this->http->JsonLog();
        */

        /*
        if (!isset($tpid, $tuid)) {
            return;
        }

        $this->http->GetURL("https://www.orbitz.com/gc/memberDetails/$tpid/$tuid/en_US");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        $notMember = $this->http->FindPreg('/"tier":"","tierString":"","programName":"","balanceInCurrencyString":"","accountBalance":"","accountInfoString":null/');
        // Status
        $this->SetProperty("Tier", $response->tier ?? "");
        // Balance - Orbucks
        $this->SetBalance($response->accountBalance ?? null);
        */

        if (!$this->http->FindSingleNode('//div[@data-stid="member-wallet-details"]')) {
            $this->waitForElement(WebDriverBy::xpath("//button[@data-testid=\"header-menu-button\" and @data-context=\"global_navigation\" and .//div[@data-testid=\"memberprofile-mediumview\"]]"), 0)->click();
            $this->waitForElement(WebDriverBy::xpath("//div[@data-stid=\"member-wallet-details\"]"), 7);
            $this->saveResponse();
        }

        // Status
        $this->SetProperty("Tier", $this->http->FindSingleNode('//span[@class="uitk-badge-text"]'));
        // Balance - Orbucks
        $this->SetBalance($this->http->FindSingleNode('//div[@data-stid="member-wallet-details"]'));

        // Name
        $this->http->GetURL("https://www.orbitz.com/user/myprofile/persinfo?pwaAuth=1&form=persinfo");
        // Name
        $name = beautifulName(Html::cleanXMLValue(
            $this->http->FindSingleNode('//input[@id = "firstName"]/@value') . ' ' .
            $this->http->FindSingleNode('//input[@id = "middleName"]/@value') . ' ' .
            $this->http->FindSingleNode('//input[@id = "lastName"]/@value')));
        $this->SetProperty("Name", beautifulName($name));

        /*
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // not a member
            if (isset($tpid, $tuid) && $notMember) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }
        */

        $this->http->GetURL("https://www.orbitz.com/account/myclub");

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // not a member
            if ($this->http->FindSingleNode('//header[contains(@class, "desktop-only")]//h1[contains(text(), "Now is a great time to join Orbitz Rewards!")]')) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }

        $expNodes = $this->http->XPath->query("//div[@id='orbucksHistory']//table//td[@data-table-category='expiring-date' and not(normalize-space(text())='---')]");
        $this->logger->debug("Found $expNodes->length nodes");

        foreach ($expNodes as $node) {
            $date = Html::cleanXMLValue($node->nodeValue);
            $this->logger->debug("Exp date: $date / " . strtotime($date));
            $date = strtotime($date);

            if (!isset($exp) || ($date > time() && $date < $exp)) {
                $exp = $date;
                $this->SetExpirationDate($exp);
                $this->SetProperty("ExpiringBalance",
                    $this->http->FindSingleNode("./preceding-sibling::td[@data-table-category='remaining-amount']",
                        $node));
            }
        }
    }

    /*
     * these itineraries like as orbitz, cheaptickets, ebookers, hotelclub, expedia, travelocity itineraries
     *
     * YOU NEED TO CHECK ALL PARSERS
     */

    public function ParseItineraries()
    {
        $this->http->GetURL('https://www.orbitz.com/trips');

        if ($this->http->FindSingleNode("//a[contains(text(),'Sign in or create free account')]")) {
            $this->logger->error('Not logged in for itineraries');

            if ($this->LoadLoginForm() && $this->Login()) {
                $this->http->GetURL('https://www.orbitz.com/trips');
            }
        }

        if ($this->http->FindSingleNode("//a[contains(text(),'Sign in or create free account')]")) {
            $this->sendNotification('check parse itineraries // MI');
        }
        $this->delay();
        $it = [];
        $expedia = $this->getExpedia();

        try {
            $it = $expedia->ParseItineraries('www.orbitz.com', $this->ParsePastIts);
        } catch (AuthException $e) {
            $this->logger->notice('Re-authorization');
            $this->removeCookies = false;

            if ($this->LoadLoginForm()) {
                if ($this->Login()) {
                    $this->http->GetURL('https://www.orbitz.com/trips');
                    $it = $expedia->ParseItineraries('www.orbitz.com', $this->ParsePastIts);
                }
            }
        }

        return $it;
    }

    public function GetConfirmationFields()
    {
        return [
            "Email" => [
                "Caption"  => "Email Address",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
            "ConfNo" => [
                "Caption"  => "Itinerary Number",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.orbitz.com/trips/booking-search?view=SEARCH_BY_ITINERARY_NUMBER_AND_EMAIL";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $this->sendNotification('check confirmation // MI');
        $duaid = $this->http->FindPreg('#\\\\"duaid\\\\":\\\\"([\w\-]+)\\\\",\\\\"#');

        if (!isset($duaid)) {
            return [];
        }

        $headers = [
            'Accept'               => '*/*',
            'Content-Type'         => 'application/json',
            'client-info'          => 'trips-pwa,4484d3137c7eb9c7e64b788b606f32fdb276b64b,us-west-2',
            'Origin'               => 'https://www.orbitz.com',
        ];
        $data = '[{"operationName":"TripSearchBookingQuery","variables":{"viewType":"SEARCH_RESULT","context":{"siteId":69,"locale":"en_US","eapid":0,"currency":"BRL","device":{"type":"DESKTOP"},"identity":{"duaid":"' . $duaid . '","expUserId":"-1","tuid":"-1","authState":"ANONYMOUS"},"privacyTrackingState":"CAN_NOT_TRACK","debugContext":{"abacusOverrides":[],"alterMode":"RELEASED"}},"searchInput":[{"key":"EMAIL_ADDRESS","value":"' . $arFields['Email'] . '"},{"key":"ITINERARY_NUMBER","value":"' . $arFields['ConfNo'] . '"}]},"query":"query TripSearchBookingQuery($context: ContextInput!, $searchInput: [GraphQLPairInput!], $viewType: TripsSearchBookingView!) {\n  trips(context: $context) {\n    searchBooking(searchInput: $searchInput, viewType: $viewType) {\n      ...TripsViewFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsViewFragment on TripsView {\n  __typename\n  ...TripsViewContentFragment\n  floatingActionButton {\n    ...TripsFloatingActionButtonFragment\n    __typename\n  }\n  ...TripsDynamicMapFragment\n  pageTitle\n  contentType\n  tripsSideEffects {\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n}\n\nfragment TripsViewContentFragment on TripsView {\n  __typename\n  header {\n    ...ViewHeaderFragment\n    __typename\n  }\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsSectionContainerFragment\n    ...TripsFormContainerFragment\n    ...TripsListFlexContainerFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsSlimCardFragment\n    ...TripsMapCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsPageBreakFragment\n    ...TripsContainerDividerFragment\n    ...TripsLodgingUpgradesPrimerFragment\n    ...TripItemContextualCardsPrimerFragment\n    __typename\n  }\n  notifications: customerNotifications {\n    ...TripsCustomerNotificationsFragment\n    __typename\n  }\n  toast {\n    ...TripsToastFragment\n    __typename\n  }\n  contentType\n}\n\nfragment ViewHeaderFragment on TripsViewHeader {\n  __typename\n  primary\n  secondaries\n  toolbar {\n    ...ToolbarFragment\n    __typename\n  }\n  signal {\n    type\n    reference\n    __typename\n  }\n}\n\nfragment TripsTertiaryButtonFragment on TripsTertiaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsLinkActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    __typename\n  }\n}\n\nfragment TripsLinkActionFragment on TripsLinkAction {\n  __typename\n  resource {\n    value\n    __typename\n  }\n  target\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  clickstreamAnalytics {\n    ...ClickstreamAnalyticsFragment\n    __typename\n  }\n}\n\nfragment ClickstreamAnalyticsFragment on ClickstreamAnalytics {\n  event {\n    clickstreamTraceId\n    eventCategory\n    eventName\n    eventType\n    eventVersion\n    __typename\n  }\n  payload {\n    ... on TripRecommendationModule {\n      title\n      responseId\n      recommendations {\n        id\n        position\n        priceDisplayed\n        currencyCode\n        name\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment MapDirectionsActionFragment on TripsMapDirectionsAction {\n  __typename\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  url\n}\n\nfragment TripsWriteToClipboardActionFragment on CopyToClipboardAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  value\n}\n\nfragment TripsVirtualAgentInitActionFragment on TripsVirtualAgentInitAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  applicationName\n  pageName\n  clientOverrides {\n    enableAutoOpenChatWidget\n    enableProactiveConversation\n    subscribedEvents\n    conversationProperties {\n      launchPoint\n      pageName\n      skipWelcome\n      __typename\n    }\n    intentMessage {\n      ... on VirtualAgentCancelIntentMessage {\n        action\n        intent\n        emailAddress\n        orderLineId\n        orderNumber\n        product\n        __typename\n      }\n      __typename\n    }\n    intentArguments {\n      id\n      value\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsOpenDialogActionFragment on TripsOpenDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  modalDialog {\n    ...TripsModalDialogFragment\n    __typename\n  }\n}\n\nfragment TripsModalDialogFragment on TripsModalDialog {\n  __typename\n  heading\n  buttonLayout\n  buttons {\n    ...TripsDialogPrimaryButtonFragment\n    ...TripsDialogSecondaryButtonFragment\n    ...TripsDialogTertiaryButtonFragment\n    __typename\n  }\n  content {\n    ...TripsEmbeddedContentListFragment\n    __typename\n  }\n}\n\nfragment TripsDialogPrimaryButtonFragment on TripsPrimaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    ...TripsDeleteTripActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    ...TripsLinkActionFragment\n    __typename\n  }\n}\n\nfragment TripsDialogTertiaryButtonFragment on TripsTertiaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    __typename\n  }\n}\n\nfragment TripsDialogSecondaryButtonFragment on TripsSecondaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    __typename\n  }\n}\n\nfragment TripsCloseDialogActionFragment on TripsCloseDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsDeleteTripActionFragment on TripsDeleteTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  overview {\n    tripViewId\n    filter\n    __typename\n  }\n}\n\nfragment TripsCancelCarActionFragment on TripsCancelCarAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  orderLineNumbers\n}\n\nfragment TripsCancelInsuranceActionFragment on TripsCancelInsuranceAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  orderLineNumber\n}\n\nfragment TripsCancelActivityActionFragment on TripsCancelActivityAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  activityOrderLineNumbers: orderLineNumbers\n  orderNumber\n}\n\nfragment TripsCancellationActionFragment on TripsCancellationAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  itemToCancel: item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  cancellationType\n  cancellationAttributes {\n    orderNumber\n    orderLineNumbers\n    refundAmount\n    penaltyAmount\n    __typename\n  }\n}\n\nfragment TripsUnsaveItemFromTripActionFragment on TripsUnsaveItemFromTripAction {\n  __typename\n  tripEntity\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  tripItem {\n    tripItemId\n    tripViewId\n    filter\n    __typename\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsEmitSignalFragment on TripsEmitSignal {\n  signal {\n    type\n    reference\n    __typename\n  }\n  values {\n    key\n    value {\n      ...TripsSignalFieldIdValueFragment\n      ...TripsSignalFieldIdExistingValuesFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSignalFieldIdExistingValuesFragment on TripsSignalFieldIdExistingValues {\n  ids\n  prefixes\n  __typename\n}\n\nfragment TripsSignalFieldIdValueFragment on TripsSignalFieldIdValue {\n  id\n  __typename\n}\n\nfragment TripsEmbeddedContentListFragment on TripsEmbeddedContentList {\n  __typename\n  primary\n  secondaries\n  listTheme: theme\n  items {\n    ...TripsEmbeddedContentLineItemFragment\n    __typename\n  }\n}\n\nfragment TripsEmbeddedContentLineItemFragment on TripsEmbeddedContentLineItem {\n  __typename\n  items {\n    __typename\n    ...TripsEmbeddedContentItemFragment\n  }\n}\n\nfragment TripsEmbeddedContentItemFragment on TripsEmbeddedContentItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    __typename\n  }\n}\n\nfragment TripsOpenFullScreenDialogActionFragment on TripsOpenFullScreenDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  dialog {\n    ...TripsFullScreenDialogFragment\n    __typename\n  }\n}\n\nfragment TripsFullScreenDialogFragment on TripsFullScreenDialog {\n  __typename\n  heading\n  closeButton {\n    ...TripsCloseDialogButtonFragment\n    __typename\n  }\n  content {\n    ...TripsEmbeddedContentCardFragment\n    __typename\n  }\n}\n\nfragment TripsCloseDialogButtonFragment on TripsCloseDialogButton {\n  __typename\n  primary\n  icon {\n    __typename\n    id\n    description\n    title\n  }\n  action {\n    __typename\n    analytics {\n      __typename\n      referrerId\n      linkName\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n    }\n  }\n}\n\nfragment TripsEmbeddedContentCardFragment on TripsEmbeddedContentCard {\n  __typename\n  primary\n  items {\n    ...TripsEmbeddedContentListFragment\n    __typename\n  }\n}\n\nfragment TripsOpenMenuActionFragment on TripsOpenMenuAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  floatingMenu {\n    ...TripsFloatingMenuFragment\n    __typename\n  }\n}\n\nfragment TripsFloatingMenuFragment on TripsFloatingMenu {\n  items {\n    ...TripsMenuTitleFragment\n    ...TripsMenuListItemFragment\n    ...TripsMenuListTitleFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMenuListItemFragment on TripsMenuListItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenChangeDatesDatePickerActionFragment\n    ...TripsOpenEmailDrawerActionFragment\n    ...TripsOpenEditTripDrawerActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsOpenSaveToTripDrawerActionFragment\n    ...TripsCustomerNotificationOpenInAppActionFragment\n    __typename\n  }\n}\n\nfragment TripsNavigateToViewActionFragment on TripsNavigateToViewAction {\n  __typename\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  tripItemId\n  tripViewId\n  viewFilter {\n    filter\n    __typename\n  }\n  viewType\n  viewUrl\n}\n\nfragment TripsOpenChangeDatesDatePickerActionFragment on TripsOpenChangeDatesDatePickerAction {\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  attributes {\n    ...TripsListDatePickerAttributesFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListDatePickerAttributesFragment on TripsDatePickerAttributes {\n  analytics {\n    closeAnalytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  buttonText\n  changeDatesAction {\n    analytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    item {\n      filter\n      tripItemId\n      tripViewId\n      __typename\n    }\n    tripEntity\n    __typename\n  }\n  maxDateRange\n  maxDateRangeMessage\n  calendarSelectionType\n  daysBookableInAdvance\n  itemDates {\n    end {\n      ...TripsListDateFragment\n      __typename\n    }\n    start {\n      ...TripsListDateFragment\n      __typename\n    }\n    __typename\n  }\n  productId\n  __typename\n}\n\nfragment TripsListDateFragment on Date {\n  day\n  month\n  year\n  __typename\n}\n\nfragment TripsOpenEmailDrawerActionFragment on TripsOpenEmailDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    __typename\n    filter\n    tripItemId\n    tripViewId\n  }\n}\n\nfragment TripsOpenEditTripDrawerActionFragment on TripsOpenEditTripDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n}\n\nfragment TripsOpenMoveTripItemDrawerActionFragment on TripsOpenMoveTripItemDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  item {\n    filter\n    tripEntity\n    tripItemId\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsOpenSaveToTripDrawerActionFragment on TripsOpenSaveToTripDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  input {\n    itemId\n    source\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsListSaveItemAttributesFragment on TripsSaveItemAttributes {\n  ...TripsListSaveStayAttributesFragment\n  ...TripsListSaveActivityAttributesFragment\n  ...TripsSaveFlightSearchAttributesFragment\n  __typename\n}\n\nfragment TripsListSaveActivityAttributesFragment on TripsSaveActivityAttributes {\n  regionId\n  dateRange {\n    start {\n      ...TripsListDateFragment\n      __typename\n    }\n    end {\n      ...TripsListDateFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListSaveStayAttributesFragment on TripsSaveStayAttributes {\n  checkInDate {\n    ...TripsListDateFragment\n    __typename\n  }\n  checkoutDate {\n    ...TripsListDateFragment\n    __typename\n  }\n  regionId\n  roomConfiguration {\n    numberOfAdults\n    childAges\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSaveFlightSearchAttributesFragment on TripsSaveFlightSearchAttributes {\n  searchCriteria {\n    primary {\n      journeyCriterias {\n        arrivalDate {\n          ...TripsListDateFragment\n          __typename\n        }\n        departureDate {\n          ...TripsListDateFragment\n          __typename\n        }\n        destination\n        destinationAirportLocationType\n        origin\n        originAirportLocationType\n        __typename\n      }\n      searchPreferences {\n        advancedFilters\n        airline\n        cabinClass\n        __typename\n      }\n      travelers {\n        age\n        type\n        __typename\n      }\n      tripType\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsCustomerNotificationOpenInAppActionFragment on TripsCustomerNotificationOpenInAppAction {\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  notificationAttributes {\n    notificationLocation\n    xPageID\n    optionalContext {\n      tripItemId\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMenuListTitleFragment on TripsMenuListTitle {\n  __typename\n  primary\n}\n\nfragment TripsMenuTitleFragment on TripsMenuTitle {\n  __typename\n  primary\n}\n\nfragment ClientSideImpressionAnalyticsFragment on ClientSideImpressionAnalytics {\n  uisPrimeAnalytics {\n    ...ClientSideAnalyticsFragment\n    __typename\n  }\n  clickstreamAnalytics {\n    ...ClickstreamAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment ClientSideAnalyticsFragment on ClientSideAnalytics {\n  eventType\n  linkName\n  referrerId\n  uisPrimeMessages {\n    messageContent\n    schemaName\n    __typename\n  }\n  __typename\n}\n\nfragment TripsOpenInviteDrawerActionFragment on TripsOpenInviteDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n}\n\nfragment TripsOpenCreateNewTripDrawerActionFragment on TripsOpenCreateNewTripDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsOpenCreateNewTripDrawerForItemActionFragment on TripsOpenCreateNewTripDrawerForItemAction {\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  createTripMetadata {\n    moveItem {\n      filter\n      tripEntity\n      tripItemId\n      tripViewId\n      __typename\n    }\n    saveItemInput {\n      itemId\n      pageLocation\n      attributes {\n        ...TripsListSaveItemAttributesFragment\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInviteActionFragment on TripsInviteAction {\n  __typename\n  inputIds\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsSaveNewTripActionFragment on TripsSaveNewTripAction {\n  __typename\n  inputIds\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsFormActionFragment on TripsFormAction {\n  __typename\n  validatedInputIds\n  type\n  formData {\n    ...TripsFormDataFragment\n    __typename\n  }\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsFormDataFragment on TripsFormData {\n  __typename\n  ...TripsCreateTripFromMovedItemFragment\n  ...TripsInviteFragment\n  ...TripsSendItineraryEmailFragment\n  ...TripsUpdateTripFragment\n  ...TripsCreateTripFromItemFragment\n}\n\nfragment TripsCreateTripFromMovedItemFragment on TripsCreateTripFromMovedItem {\n  __typename\n  item {\n    filter\n    tripEntity\n    tripItemId\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsInviteFragment on TripsInvite {\n  __typename\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsSendItineraryEmailFragment on TripsSendItineraryEmail {\n  __typename\n  item {\n    tripViewId\n    tripItemId\n    filter\n    __typename\n  }\n}\n\nfragment TripsUpdateTripFragment on TripsUpdateTrip {\n  __typename\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsCreateTripFromItemFragment on TripsCreateTripFromItem {\n  __typename\n  input {\n    itemId\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    pageLocation\n    __typename\n  }\n}\n\nfragment ToolbarFragment on TripsToolbar {\n  __typename\n  primary\n  secondaries\n  accessibility {\n    label\n    __typename\n  }\n  actions {\n    primary {\n      ...TripsTertiaryButtonFragment\n      __typename\n    }\n    secondaries {\n      ...TripsTertiaryButtonFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsCarouselContainerFragment on TripsCarouselContainer {\n  __typename\n  heading\n  subheading {\n    ...TripsCarouselSubHeaderFragment\n    __typename\n  }\n  elements {\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsSlimCardFragment\n    __typename\n  }\n  accessibility {\n    ... on TripsCarouselAccessibilityData {\n      nextButton\n      prevButton\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsContentCardFragment on TripsContentCard {\n  __typename\n  primary\n  secondaries\n  rows {\n    __typename\n    ...ContentColumnsFragment\n    ...TripsViewContentListFragment\n    ...EmblemsInlineContentFragment\n  }\n}\n\nfragment ContentColumnsFragment on TripsContentColumns {\n  __typename\n  primary\n  columns {\n    __typename\n    ...TripsViewContentListFragment\n  }\n}\n\nfragment TripsViewContentListFragment on TripsContentList {\n  __typename\n  primary\n  secondaries\n  listTheme: theme\n  items {\n    ...TripsViewContentLineItemFragment\n    __typename\n  }\n}\n\nfragment TripsViewContentLineItemFragment on TripsViewContentLineItem {\n  __typename\n  items {\n    __typename\n    ...TripsViewContentItemFragment\n  }\n}\n\nfragment TripsViewContentItemFragment on TripsViewContentItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    __typename\n  }\n}\n\nfragment EmblemsInlineContentFragment on TripsEmblemsInlineContent {\n  __typename\n  primary\n  secondaries\n  emblems {\n    ...TripsEmblemFragment\n    __typename\n  }\n}\n\nfragment TripsEmblemFragment on TripsEmblem {\n  ...BadgeFragment\n  ...EGDSMarkFragment\n  ...EGDSStandardBadgeFragment\n  ...EGDSLoyaltyBadgeFragment\n  ...EGDSProgramBadgeFragment\n  __typename\n}\n\nfragment BadgeFragment on TripsBadge {\n  accessibility\n  text\n  tripsBadgeTheme: theme\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  __typename\n}\n\nfragment UIGraphicFragment on UIGraphic {\n  ...EGDSIconFragment\n  ...EGDSMarkFragment\n  ...EGDSIllustrationFragment\n  __typename\n}\n\nfragment EGDSIconFragment on Icon {\n  description\n  id\n  size\n  theme\n  title\n  withBackground\n  __typename\n}\n\nfragment EGDSMarkFragment on Mark {\n  description\n  id\n  markSize: size\n  url {\n    ... on HttpURI {\n      __typename\n      relativePath\n      value\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSIllustrationFragment on Illustration {\n  id\n  description\n  link: url\n  __typename\n}\n\nfragment EGDSStandardBadgeFragment on EGDSStandardBadge {\n  accessibility\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  text\n  size\n  theme\n  __typename\n}\n\nfragment EGDSLoyaltyBadgeFragment on EGDSLoyaltyBadge {\n  accessibility\n  text\n  size\n  theme\n  __typename\n}\n\nfragment EGDSProgramBadgeFragment on EGDSProgramBadge {\n  accessibility\n  text\n  theme\n  __typename\n}\n\nfragment TripsFullBleedImageCardFragment on TripsFullBleedImageCard {\n  primary\n  secondaries\n  background {\n    url\n    description\n    __typename\n  }\n  badgeList {\n    ...EGDSBadgeFragment\n    __typename\n  }\n  icons {\n    id\n    description\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSBadgeFragment on EGDSBadge {\n  ...EGDSStandardBadgeFragment\n  ...EGDSLoyaltyBadgeFragment\n  ...EGDSProgramBadgeFragment\n  __typename\n}\n\nfragment TripsImageTopCardFragment on TripsImageTopCard {\n  primary\n  secondaries\n  background {\n    url\n    description\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...MapDirectionsActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSlimCardFragment on TripsSlimCard {\n  graphic {\n    ...EGDSIconFragment\n    ...EGDSMarkFragment\n    ...EGDSIllustrationFragment\n    __typename\n  }\n  primary\n  secondaries\n  subTexts {\n    ...TripsTextFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsTextFragment on TripsText {\n  ...EGDSGraphicTextFragment\n  ...EGDSPlainTextFragment\n  __typename\n}\n\nfragment EGDSGraphicTextFragment on EGDSGraphicText {\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  text\n  __typename\n}\n\nfragment EGDSPlainTextFragment on EGDSPlainText {\n  text\n  __typename\n}\n\nfragment NavigateToManageBookingActionFragment on TripsNavigateToManageBookingAction {\n  __typename\n  item {\n    __typename\n    tripItemId\n    tripViewId\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  url\n}\n\nfragment TripsCarouselSubHeaderFragment on TripsCarouselSubHeader {\n  ...EGDSGraphicTextFragment\n  ...EGDSPlainTextFragment\n  __typename\n}\n\nfragment TripsSectionContainerFragment on TripsSectionContainer {\n  ...TripsInternalSectionContainerFragment\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsMediaGalleryFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsFittedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsMapCardFragment\n    ...TripsImageSlimCardFragment\n    ...TripsSlimCardFragment\n    ...TripsMapCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsFlightMapCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsPricePresentationFragment\n    ...TripsSubSectionContainerFragment\n    ...TripsListFlexContainerFragment\n    ...TripsServiceRequestsButtonPrimerFragment\n    ...TripsSlimCardContainerFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFittedImageCardFragment on TripsFittedImageCard {\n  primary\n  secondaries\n  img: image {\n    url\n    description\n    aspectRatio\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  imageType\n  __typename\n}\n\nfragment TripsMapCardFragment on TripsMapCard {\n  primary\n  secondaries\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  image {\n    url\n    description\n    __typename\n  }\n  action {\n    ...MapActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment MapActionFragment on TripsMapAction {\n  __typename\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsImageSlimCardFragment on TripsImageSlimCard {\n  ...TripsInternalImageSlimCardFragment\n  signal {\n    type\n    reference\n    __typename\n  }\n  cardIcon {\n    ...TripsIconFragment\n    __typename\n  }\n  primaryAction {\n    ...TripsLinkActionFragment\n    ...TripsMoveItemToTripActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsSaveItemToTripActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    __typename\n  }\n  itemPricePrimer {\n    ... on TripsSavedItemPricePrimer {\n      ...TripsSavedItemPricePrimerFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInternalImageSlimCardFragment on TripsImageSlimCard {\n  primary\n  secondaries\n  badgeList {\n    ...EGDSBadgeFragment\n    __typename\n  }\n  thumbnail {\n    aspectRatio\n    description\n    url\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    hint\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsIconFragment on TripsIcon {\n  action {\n    ...TripsOpenMenuActionFragment\n    ...TripsSaveItemToTripActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    title\n    theme\n    __typename\n  }\n  label\n  __typename\n}\n\nfragment TripsSaveItemToTripActionFragment on TripsSaveItemToTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  saveItemInput {\n    itemId\n    source\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n  tripId\n}\n\nfragment TripsSavedItemPricePrimerFragment on TripsSavedItemPricePrimer {\n  tripItem {\n    ...TripItemFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripItemFragment on TripItem {\n  filter\n  tripItemId\n  tripViewId\n  __typename\n}\n\nfragment TripsMoveItemToTripActionFragment on TripsMoveItemToTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  data {\n    item {\n      filter\n      tripEntity\n      tripItemId\n      tripViewId\n      __typename\n    }\n    toTripId\n    toTripName\n    __typename\n  }\n}\n\nfragment TripsIllustrationCardFragment on TripsIllustrationCard {\n  primary\n  secondaries\n  illustration {\n    url\n    description\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsPrimaryButtonFragment on TripsPrimaryButton {\n  ...TripsInternalPrimaryButtonFragment\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsSendItineraryEmailActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsUpdateTripActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsDeleteTripActionFragment\n    ...TripsInviteAcceptActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsAcceptInviteAndNavigateToOverviewActionFragment\n    ...TripsCreateTripFromItemActionFragment\n    __typename\n  }\n  width\n  __typename\n}\n\nfragment TripsInternalPrimaryButtonFragment on TripsPrimaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n}\n\nfragment TripsSendItineraryEmailActionFragment on TripsSendItineraryEmailAction {\n  __typename\n  inputIds\n  item {\n    __typename\n    tripViewId\n    tripItemId\n    filter\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsUpdateTripActionFragment on TripsUpdateTripAction {\n  __typename\n  inputIds\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsInviteAcceptActionFragment on TripsInviteAcceptAction {\n  __typename\n  inviteId\n  analytics {\n    __typename\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsAcceptInviteAndNavigateToOverviewActionFragment on TripsAcceptInviteAndNavigateToOverviewAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    tripViewId\n    filter\n    inviteId\n    __typename\n  }\n  overviewUrl\n}\n\nfragment TripsCreateTripFromItemActionFragment on TripsCreateTripFromItemAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  saveItemInput {\n    itemId\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n  inputIds\n}\n\nfragment TripsSecondaryButtonFragment on TripsSecondaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    __typename\n  }\n}\n\nfragment TripsFlightMapCardFragment on TripsFlightPathMapCard {\n  primary\n  secondaries\n  image {\n    url\n    description\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsPricePresentationFragment on TripsPricePresentation {\n  __typename\n  pricePresentation {\n    __typename\n    ...PricePresentationFragment\n  }\n}\n\nfragment PricePresentationFragment on PricePresentation {\n  title {\n    primary\n    __typename\n  }\n  sections {\n    ...PricePresentationSectionFragment\n    __typename\n  }\n  footer {\n    header\n    messages {\n      ...PriceLineElementFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationSectionFragment on PricePresentationSection {\n  header {\n    name {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    enrichedValue {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    __typename\n  }\n  subSections {\n    ...PricePresentationSubSectionFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationSubSectionFragment on PricePresentationSubSection {\n  header {\n    name {\n      primaryMessage {\n        __typename\n        ... on PriceLineText {\n          primary\n          __typename\n        }\n        ... on PriceLineHeading {\n          primary\n          __typename\n        }\n      }\n      __typename\n    }\n    enrichedValue {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    __typename\n  }\n  items {\n    ...PricePresentationLineItemFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationLineItemFragment on PricePresentationLineItem {\n  enrichedValue {\n    ...PricePresentationLineItemEntryFragment\n    __typename\n  }\n  name {\n    ...PricePresentationLineItemEntryFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationLineItemEntryFragment on PricePresentationLineItemEntry {\n  primaryMessage {\n    ...PriceLineElementFragment\n    __typename\n  }\n  secondaryMessages {\n    ...PriceLineElementFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PriceLineElementFragment on PricePresentationLineItemMessage {\n  __typename\n  ...PriceLineTextFragment\n  ...PriceLineHeadingFragment\n  ...PriceLineBadgeFragment\n  ...InlinePriceLineTextFragment\n}\n\nfragment PriceLineTextFragment on PriceLineText {\n  __typename\n  theme\n  primary\n  weight\n  additionalInfo {\n    ...AdditionalInformationPopoverFragment\n    __typename\n  }\n  additionalInformation {\n    ...PricePresentationAdditionalInformationFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n}\n\nfragment PricePresentationAdditionalInformationFragment on PricePresentationAdditionalInformation {\n  ...PricePresentationAdditionalInformationDialogFragment\n  ...PricePresentationAdditionalInformationPopoverFragment\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFragment on PricePresentationAdditionalInformationDialog {\n  closeAnalytics {\n    linkName\n    referrerId\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  footer {\n    ...PricePresentationAdditionalInformationDialogFooterFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  openAnalytics {\n    linkName\n    referrerId\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFooterFragment on EGDSDialogFooter {\n  ... on EGDSInlineDialogFooter {\n    buttons {\n      ...PricePresentationAdditionalInformationDialogFooterButtonsFragment\n      __typename\n    }\n    __typename\n  }\n  ... on EGDSStackedDialogFooter {\n    buttons {\n      ...PricePresentationAdditionalInformationDialogFooterButtonsFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFooterButtonsFragment on EGDSButton {\n  accessibility\n  disabled\n  primary\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationPopoverFragment on PricePresentationAdditionalInformationPopover {\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverFragment on AdditionalInformationPopover {\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverSectionFragment on AdditionalInformationPopoverSection {\n  __typename\n  ... on AdditionalInformationPopoverTextSection {\n    ...AdditionalInformationPopoverTextSectionFragment\n    __typename\n  }\n  ... on AdditionalInformationPopoverListSection {\n    ...AdditionalInformationPopoverListSectionFragment\n    __typename\n  }\n  ... on AdditionalInformationPopoverGridSection {\n    ...AdditionalInformationPopoverGridSectionFragment\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverTextSectionFragment on AdditionalInformationPopoverTextSection {\n  __typename\n  text {\n    text\n    ...EGDSStandardLinkFragment\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverListSectionFragment on AdditionalInformationPopoverListSection {\n  __typename\n  content {\n    __typename\n    items {\n      text\n      __typename\n    }\n  }\n}\n\nfragment AdditionalInformationPopoverGridSectionFragment on AdditionalInformationPopoverGridSection {\n  __typename\n  subSections {\n    header {\n      name {\n        primaryMessage {\n          ...AdditionalInformationPopoverGridLineItemMessageFragment\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    items {\n      name {\n        ...AdditionalInformationPopoverGridLineItemEntryFragment\n        __typename\n      }\n      enrichedValue {\n        ...AdditionalInformationPopoverGridLineItemEntryFragment\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverGridLineItemEntryFragment on PricePresentationLineItemEntry {\n  primaryMessage {\n    ...AdditionalInformationPopoverGridLineItemMessageFragment\n    __typename\n  }\n  secondaryMessages {\n    ...AdditionalInformationPopoverGridLineItemMessageFragment\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverGridLineItemMessageFragment on PricePresentationLineItemMessage {\n  ... on PriceLineText {\n    __typename\n    primary\n  }\n  ... on PriceLineHeading {\n    __typename\n    tag\n    size\n    primary\n  }\n  __typename\n}\n\nfragment PriceLineHeadingFragment on PriceLineHeading {\n  __typename\n  primary\n  tag\n  size\n  additionalInfo {\n    ...AdditionalInformationPopoverFragment\n    __typename\n  }\n  additionalInformation {\n    ...PricePresentationAdditionalInformationFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n}\n\nfragment PriceLineBadgeFragment on PriceLineBadge {\n  __typename\n  badge {\n    accessibility\n    text\n    theme\n    __typename\n  }\n}\n\nfragment InlinePriceLineTextFragment on InlinePriceLineText {\n  __typename\n  inlineItems {\n    ...PriceLineTextFragment\n    __typename\n  }\n}\n\nfragment EGDSStandardLinkFragment on EGDSStandardLink {\n  action {\n    ...ActionFragment\n    __typename\n  }\n  standardLinkIcon: icon {\n    ...EGDSIconFragment\n    __typename\n  }\n  iconPosition\n  size\n  text\n  __typename\n}\n\nfragment ActionFragment on UILinkAction {\n  accessibility\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  resource {\n    value\n    __typename\n  }\n  target\n  __typename\n}\n\nfragment TripsSubSectionContainerFragment on TripsSectionContainer {\n  __typename\n  heading\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsFittedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsMapCardFragment\n    ...TripsSlimCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    __typename\n  }\n}\n\nfragment TripsInternalSectionContainerFragment on TripsSectionContainer {\n  __typename\n  heading\n  subheadings\n  tripsListSubTexts: subTexts\n  theme\n}\n\nfragment TripsSlimCardContainerFragment on TripsSlimCardContainer {\n  heading\n  subHeaders {\n    ...TripsTextFragment\n    __typename\n  }\n  slimCards {\n    ...TripsSlimCardFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListFlexContainerFragment on TripsFlexContainer {\n  ...TripsInternalFlexContainerFragment\n  elements {\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsAvatarGroupFragment\n    ...TripsEmbeddedContentCardFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInternalFlexContainerFragment on TripsFlexContainer {\n  __typename\n  alignItems\n  direction\n  justifyContent\n  wrap\n  elements {\n    ...TripsInternalFlexContainerItemFragment\n    __typename\n  }\n}\n\nfragment TripsInternalFlexContainerItemFragment on TripsFlexContainerItem {\n  grow\n  __typename\n}\n\nfragment TripsAvatarGroupFragment on TripsAvatarGroup {\n  avatars {\n    ...TripsAvatarFragment\n    __typename\n  }\n  avatarSize\n  showBorder\n  action {\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsAvatarFragment on TripsAvatar {\n  name\n  url\n  __typename\n}\n\nfragment TripsServiceRequestsButtonPrimerFragment on TripsServiceRequestsButtonPrimer {\n  buttonStyle\n  itineraryNumber\n  lineOfBusiness\n  orderLineId\n  __typename\n}\n\nfragment TripsMediaGalleryFragment on TripsMediaGallery {\n  __typename\n  accessibilityHeadingText\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  nextButtonText\n  previousButtonText\n  mediaGalleryId: egdsElementId\n  media {\n    ...TripsMediaFragment\n    __typename\n  }\n  mediaGalleryDialogToolbar {\n    ...TripsMediaGalleryDialogFragment\n    __typename\n  }\n}\n\nfragment TripsMediaGalleryDialogFragment on TripsToolbar {\n  primary\n  secondaries\n  actions {\n    primary {\n      icon {\n        description\n        id\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMediaFragment on TripsMedia {\n  media {\n    ... on Image {\n      url\n      description\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFormContainerFragment on TripsFormContainer {\n  __typename\n  formTheme\n  elements {\n    ...TripsPrimaryButtonFragment\n    ...TripsContentCardFragment\n    ...TripsValidatedInputFragment\n    __typename\n  }\n}\n\nfragment TripsValidatedInputFragment on TripsValidatedInput {\n  egdsElementId\n  instructions\n  label\n  placeholder\n  required\n  value\n  inputType\n  leftIcon {\n    __typename\n    leftIconId: id\n    title\n    description\n  }\n  rightIcon {\n    __typename\n    rightIconId: id\n    title\n    description\n  }\n  validations {\n    ...EGDSMaxLengthInputValidationFragment\n    ...EGDSMinLengthInputValidationFragment\n    ...EGDSRegexInputValidationFragment\n    ...EGDSRequiredInputValidationFragment\n    ...EGDSTravelersInputValidationFragment\n    ...MultiEmailValidationFragment\n    ...SingleEmailValidationFragment\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSMaxLengthInputValidationFragment on EGDSMaxLengthInputValidation {\n  __typename\n  errorMessage\n  maxLength\n}\n\nfragment EGDSMinLengthInputValidationFragment on EGDSMinLengthInputValidation {\n  __typename\n  errorMessage\n  minLength\n}\n\nfragment EGDSRegexInputValidationFragment on EGDSRegexInputValidation {\n  __typename\n  errorMessage\n  pattern\n}\n\nfragment EGDSRequiredInputValidationFragment on EGDSRequiredInputValidation {\n  __typename\n  errorMessage\n}\n\nfragment EGDSTravelersInputValidationFragment on EGDSTravelersInputValidation {\n  __typename\n  errorMessage\n}\n\nfragment MultiEmailValidationFragment on MultiEmailValidation {\n  __typename\n  errorMessage\n}\n\nfragment SingleEmailValidationFragment on SingleEmailValidation {\n  __typename\n  errorMessage\n}\n\nfragment TripsPageBreakFragment on TripsPageBreak {\n  __typename\n  _empty\n}\n\nfragment TripsContainerDividerFragment on TripsContainerDivider {\n  divider\n  __typename\n}\n\nfragment TripsLodgingUpgradesPrimerFragment on TripsLodgingUpgradesPrimer {\n  itineraryNumber\n  __typename\n}\n\nfragment TripItemContextualCardsPrimerFragment on TripItemContextualCardsPrimer {\n  tripViewId\n  tripItemId\n  placeHolder {\n    url\n    description\n    __typename\n  }\n  __typename\n}\n\nfragment TripsCustomerNotificationsFragment on TripsCustomerNotificationQueryParameters {\n  funnelLocation\n  notificationLocation\n  optionalContext {\n    itineraryNumber\n    journeyCriterias {\n      dateRange {\n        start {\n          day\n          month\n          year\n          __typename\n        }\n        end {\n          day\n          month\n          year\n          __typename\n        }\n        __typename\n      }\n      destination {\n        airportTLA\n        propertyId\n        regionId\n        __typename\n      }\n      origin {\n        airportTLA\n        propertyId\n        regionId\n        __typename\n      }\n      tripScheduleChangeStatus\n      __typename\n    }\n    tripId\n    tripItemId\n    __typename\n  }\n  xPageID\n  __typename\n}\n\nfragment TripsToastFragment on TripsToast {\n  ...TripsInfoToastFragment\n  ...TripsInlineActionToastFragment\n  ...TripsStackedActionToastFragment\n  __typename\n}\n\nfragment TripsInfoToastFragment on TripsInfoToast {\n  primary\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInlineActionToastFragment on TripsInlineActionToast {\n  primary\n  button {\n    ...TripsToastButtonFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsToastButtonFragment on TripsButton {\n  __typename\n  primary\n  action {\n    ...TripsNavigateToViewActionFragment\n    ...TripsDismissActionFragment\n    __typename\n  }\n}\n\nfragment TripsDismissActionFragment on TripsDismissAction {\n  __typename\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsStackedActionToastFragment on TripsStackedActionToast {\n  primary\n  button {\n    ...TripsToastButtonFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFloatingActionButtonFragment on TripsFloatingActionButton {\n  __typename\n  action {\n    ...TripsVirtualAgentInitActionFragment\n    __typename\n  }\n}\n\nfragment TripsDynamicMapFragment on TripsView {\n  egTripsMap {\n    ...DynamicMapFragment\n    __typename\n  }\n  egTripsCards {\n    ...TripsDynamicMapCardContentFragment\n    __typename\n  }\n  __typename\n}\n\nfragment DynamicMapFragment on EGDSBasicMap {\n  label\n  initialViewport\n  center {\n    latitude\n    longitude\n    __typename\n  }\n  zoom\n  bounds {\n    northeast {\n      latitude\n      longitude\n      __typename\n    }\n    southwest {\n      latitude\n      longitude\n      __typename\n    }\n    __typename\n  }\n  computedBoundsOptions {\n    coordinates {\n      latitude\n      longitude\n      __typename\n    }\n    gaiaId\n    lowerQuantile\n    upperQuantile\n    marginMultiplier\n    minMargin\n    minimumPins\n    interpolationRatio\n    __typename\n  }\n  config {\n    ... on EGDSDynamicMapConfig {\n      accessToken\n      egdsMapProvider\n      externalConfigEndpoint {\n        value\n        __typename\n      }\n      mapId\n      __typename\n    }\n    __typename\n  }\n  markers {\n    ... on EGDSMapFeature {\n      id\n      description\n      markerPosition {\n        latitude\n        longitude\n        __typename\n      }\n      type\n      markerStatus\n      qualifiers\n      text\n      clientSideAnalytics {\n        linkName\n        referrerId\n        __typename\n      }\n      onSelectAccessibilityMessage\n      onEnterAccessibilityMessage\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsDynamicMapCardContentFragment on EGDSImageCard {\n  id\n  description\n  image {\n    aspectRatio\n    description\n    thumbnailClickAnalytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    url\n    __typename\n  }\n  title\n  __typename\n}\n"}]';

        // Check auth
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.orbitz.com/graphql", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        foreach ($response[0]->data->trips->searchBooking->elements as $elements) {
            foreach ($elements->elements as $element) {
                if (isset($element->action->viewType) && $element->action->viewType == 'OVERVIEW') {
                    $viewUrl = $element->action->viewUrl;

                    break 2;
                } elseif (isset($element->heading)) {
                    $this->logger->debug($element->heading);
                }
            }
        }

        if (!isset($viewUrl)) {
            if (!is_numeric($arFields['ConfNo'])) {
                return 'Please enter a valid itinerary number';
            }

            return null;
        }
        $this->http->GetURL($viewUrl);
        $providerHost = "https://www.orbitz.com";
        $this->delay();
        $its = $this->http->FindNodes('//div[@role="main"]//a[contains(@href, "/trips/egti-") and contains(.,"View booking")]/@href');
        $expedia = $this->getExpedia();

        foreach ($its as $it) {
            $this->http->GetURL($it);

            if ($expedia->ParseItineraryDetectType($providerHost, $arFields) === false) {
                //$this->delay();
                $this->http->GetURL($it);
                $this->increaseTimeLimit();
                $expedia->ParseItineraryDetectType($providerHost, $arFields);
            }
        }

        $this->logger->debug("Parsed data: " . var_export($this->itinerariesMaster->toArray(), true));

        return null;
    }

    protected function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindPreg("/recaptchaSiteKey = \"([^\"]+)/");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }

    /** @return TAccountCheckerExpedia */
    protected function getExpedia()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->expedia)) {
            $this->expedia = new TAccountCheckerExpedia();
            $this->expedia->http = $this->http;
            $this->expedia->itinerariesMaster = $this->itinerariesMaster;
            $this->expedia->http->LogHeaders = $this->http->LogHeaders;

            $this->expedia->globalLogger = $this->globalLogger;
            $this->expedia->logger = $this->logger;
            $this->expedia->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }
        $this->expedia->AccountFields = $this->AccountFields;

        return $this->expedia;
    }

    private function parseQuestion($headers, $csrfEmail, $csrfOTP): bool
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg('/\{"cmsToken":"NotGenerated","identifier":null,"csrfData"/')
            || $this->http->FindPreg('/"cmsToken":"NotNeeded","identifier":null,"csrfData":.+?"placement":"login"\},"failure":null,"scenario":"SIGNIN"/')
            || $this->http->FindPreg('/"identifier":\{"flow":"BETA_CONSENT","variant":"BETA_CONSENT_2"\}/')
        ) {
            $data = [
                'username'      => $this->AccountFields['Login'],
                'email'         => $this->AccountFields['Login'],
                'resend'        => false,
                'channelType'   => 'WEB',
                'scenario'      => 'SIGNIN',
                'atoShieldData' => [
                    'atoTokens' => new stdClass(),
                    'placement' => 'loginemail',
                    'csrfToken' => $csrfEmail,
                    'devices'   => [
                        [
                            'payload' => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"0400rFK0xCscs7eVebKatfMjIL0DlP25BqM+S9xeYVb8G8IQ0V5eJDnpvNFu1N6XayHZ5fGW5ZlOMYcpP+U/ow6B55RvFCMSbl5xsqD6MTN95fQNl17v7KIbJt7z2M2U4qB34yvRhXnDkIvOI6x43qvV/C/xvCwL50uNa0jdtCKsSKCMd+lYGc5Lt0/teK6wzGX440JwBIixsUHmx2vNwg2Qepnlac9gMrtHSCcEPP13kFSu6O+mu6rD44frKTZ440c9hqhbA4T/1Lh3m7ebYcyPRqcdvOaikN2qKULPxBs5mS8mLgsfSImYQydp/My5xsOpfvLxvyQ/zn9rRjLkmcdik84jrHjeq9X8L/G8LAvnS41rSN20IqxIoIx36VgZzku3T+14rrDMZfjjQnAEiLGxQcCq9DH/qIFm7l7z/4PvaMJxKa5ScHurDbx4kBPDhN1CIKwR+cb8pJlDyrXEs0yPFpIFqT+kaBgd5v+L5TKZh1JIkDv9euBifL4sP/rDpQqRf7qhSLHn4X668UW/DpoFFJAV+ZZLc8a70ZuZJFR0zYFfzl5qiHYuOrROpPzOzPf13ZV8YJVNIUPZCB2vuiEt/RZZ7hquPMFUrd58uV3EFRWaDcRyWlsPIYgvdKFIFOfTOALO/86gorFme/hJzHwuC2sHjQubcKhD+MTw3KUeezT3Hg+2Oq3BTYDB5mrABueSbZWp/5NFsh1iTDOguRPLm+1sRoFJwLN+kuN7pHlNT+U9GC17jlnhc+rwxyG0LqgC6Yf0/pTJUUwDVymmPVngsgydKgnOIDKXblUrdgWkJJT58QAB3NffQB3+Dd6SKNck5jxeFvm7v6P4K/PpO4bsaKV46r0SrTSJLuz+HM3F1yn9LhP5/51Z3C8MWvX7mP09LD4mV3TQqnyTnmPj+ncLdRKtWJRlWp63P8qFSAZ42orAdp/1/wwkJ7XbUlKdexkPRh35hSsrB0AKSs4KVYKB4qkb952p5qwSRw6NL2GAwt+X8yQ9pt4DpHSw7PtSQtURum/NXiqfFU1FefNUJkIj/kQwVr+xZSPZ3BOJ2d5nlZ52+ncQ4qJLHu4cY6Mt9WpRZpQRyqKemuJqTUnBSvUYsNwTidneZ5Wedvp3EOKiSx7uHGOjLfVqUWaUEcqinpriak1JwUr1GLDcE4nZ3meVnnb6dxDiokseTnOtpbEdaovEpkWTlnMi5g4mmYKxVbRrv0w6dFMy2EuOjA2+wLjlHAw1htwGoheBiE8nFfkKa53huQiJuHfIakuHuH9BhO0tZ218bK28BP5uVSt2BaQklKpKlT1PGOcgfobiOhcONJPNnBg89k56rF9ki3gZNYvqJ8XiEIyU0CF0sOz7UkLVEbpvzV4qnxVNT59yZMLEen9ZEBlF3CwJErGAqHKF92sv0O/E9ABp/9da+8GxQj+MNvyY8psLEkPL0PTwnfvCcYkQ6yIhr3QJEs5uK/niql9HJP61tHnSuc1CZw0scim3OeZFb4hcqt0yyzR7++NeG1r9cUc3lnMihGKAq2q0AOWxfkTz8aGQSxxOeUevoHxdjU59qpox3l1XkuyS99GcE2Ns1TwRpHpCSz78XZLVcp6e2n1AV9TjYwbz3wafI/WYHEsTCs2TKRSUB860/YtAXmzCSuwj+Acg2f2vFnup4QVZOEUGoVSakIq0bl8O++WORSiRlB4zzByYV5BlioOZde35LQ9wqMOHFD9mO1u0CUWvlxo53Jon/8IagwKYJeDhJQJthhlo0Dnn6RsAk1w41Ytz+jhoGvOy/cJaz3Upn4g9mZpHcBN21gaQy1sVBwxanS0FSbWlqzvNglTyVsX8cQTx8A39jYLu1XCHMlMlcepPk+2jS6qDfw2ku6OWNJfIoVhByCYxc22l+o0tCDnp9AlFhJKyMgZypm8czElJjtdFmVQI1ynhkIgWVqwLuGICv0N0VSgA3aSxDrC2Fj1gMxpNqa+tFdNBTrrE7eVz75WJSdYogTGekWE/1vPmh5kD1HOEJCBIIpiOhVPBsZRZ8SDBiYwWbjaXLvgIYSLTxG4sojveBLVAY5CttZhmo2xLdgZ+lD6aW6RMCRJZ+F8jVelKJUkBQo/UPv5x003OckU+zy5RNgqag+8FwYbaw3RkcEKalSp0adrBv9eOvsyvTBVPcHli7fPqCzj0nEo3ZjoPD1AfxKR/T6D7pLYBr1iG8/boT2Wl9J3REUCAAqfQFq+MsMdzyk02gDTXisny5oICTaCdaJ7Urfqa75l2MDM0+bvF+DkbGquKCu9M15MH2pytc3FhJ749epSkOINbAWYgf914BhbIwHzzK+fh9iZrke8BIDpDtF0jyT+s2Q2VWMjUiDeywT+wYJztM+y19kf6z2yujmp243GvtsbrgkRJ1WdIKoJK+zyXuS0usXJnXX2hJj1Fbxib32Uyd5Veuz5ggz26Fbwq67aYSp6eFvnj7c2QHdOpA+3iPJOwLeF4txyCGMoXbD+tOGEEZXTBxvvehI+2MFJX/g+3kFf91J67WrVwsgPYUnpfdYeqeGGlCbC7Wiv3gWKhvMRwyOa22rf1stqbaZ+Ngmsbn9cm51fOgvLvBoY0HZS3L4RA6xWCQselygYFmIwy9ud0sE5ae3Cm15362ubh5w+Nn0RPTzA/kPjsiVuwYN+kc6UreJ62NQhMCoLOUbQOUATcawACKVix","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1916,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":7551,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"9001,www.vrbo.com,Vrbo,ULX","requestURL":"https://www.vrbo.com/login?enable_login=true&redirectTo=%2Ftraveler%2Fth%2Fbookings%3Fvgdc%3DHAUS%26preferlocale%3Dtrue","userAgent":"Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/112.0","placement":"LOGIN","placementPage":"9001","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                            'type'    => 'TRUST_WIDGET',
                        ],
                    ],
                ],
            ];

            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.vrbo.com/identity/email/otp/send', json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            // todo: shoild be added 2fa support in the future
            // see refs #22167
            if (isset($response->identifier->variant) && $response->identifier->flow == 'OTP') {
                $this->State['headers'] = $headers;
                $this->State['csrf'] = $csrfOTP;
                $this->AskQuestion("Enter the secure code we sent to your email. Check junk mail if it’s not in your inbox.", null, "2fa");

                return true;
            }
        }

        return false;
    }

    private function delay()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->verify()) {
            $delay = rand(1, 10);
            $this->logger->debug("Delay -> {$delay}");
            sleep($delay);

            return true;
        }

        return false;
    }

    private function verify()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] != 429 || !$this->http->ParseForm("verifyButton")) {
            return false;
        }
        $currentUrl = $this->http->currentUrl();
        // captcha
        $key = $this->http->FindSingleNode("//form[@id = 'verifyButton']//div[@class = 'g-recaptcha']/@data-sitekey");
        $captcha = $this->parseCaptcha($key);

        if ($captcha === false) {
            return false;
        }
        $this->http->GetURL("https://www.orbitz.com/botOrNot/verify?g-recaptcha-response={$captcha}&destination={$this->http->Form['destination']}");

        if ($this->http->Response['code'] == 302) {
            $this->http->GetURL($currentUrl);
        }

        return true;
    }

    private function parseFunCaptcha($retry = true, $subDomain = 'expedia-api.arkoselabs.com')
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode('//iframe[contains(@src, "-api.arkoselabs.com")]/@src', null, true, "/pkey=([^&]+)/")
            ?? $this->http->FindPreg('/pkey=([^&\\\]+)/')
        ;

        if (!$key) {
            return false;
        }

        if ($this->attempt == 2) {
            $postData = array_merge(
                [
                    "type"                     => "FunCaptchaTaskProxyless",
                    "websiteURL"               => $this->http->currentUrl(),
                    "funcaptchaApiJSSubdomain" => $subDomain,
                    "websitePublicKey"         => $key,
                ],
                []
//            $this->getCaptchaProxy()
            );
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($recognizer, $postData, $retry);
        }

        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $result = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.orbitz.com/user/account");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'loginFormEmailInput']"), 5);
            $password = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'loginFormPasswordInput']"), 5);
            $loginButton = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'loginFormSubmitButton']"), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$loginButton) {
                $this->logger->error('Failed to find login button');

                if (
                    $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
                    || $this->http->FindPreg("/Access to <span jscontent=\"hostName\"[^>]+>www\.hotels\.com<\/span> was denied/")
                ) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $this->savePageToLogs($selenium);
            $password->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            sleep(2);
            $loginButton->click();
            sleep(1);

            /*
            $selenium->driver->executeScript("document.querySelector('#enterPasswordFormSubmitButton').click();");
            sleep(2);
            $selenium->driver->executeScript("
                try {
                    document.querySelector('#enterPasswordFormSubmitButton').click();
                } catch (e) {}
            ");
            */

            /*$selenium->waitForElement(WebDriverBy::xpath("
                //*[contains(text(), 'Get early access to a more rewarding experience')]
                | //h2[contains(text(), 'My Account Info')]
                | //div[@aria-hidden='false']//iframe
            "), 15);

            if ($selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Get early access to a more rewarding experience')]"), 0)) {
                $notNow = $selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Not now')]"), 0);
                if ($notNow) {
                    $notNow->click();
                    sleep(10);
                }
            }

            $this->savePageToLogs($selenium);*/

            $selenium->waitFor(function () use ($selenium) {
                return $selenium->waitForElement(WebDriverBy::id("tabs-accountdetails"), 0)
                    || $selenium->waitForElement(WebDriverBy::xpath("//div[@aria-hidden='false']//iframe"), 0)
                    || $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'uitk-banner')]//div[contains(@class, 'uitk-banner-description')] | //h3[contains(@class, 'uitk-error-summary-heading')]"), 0);
            }, 10);
            $this->savePageToLogs($selenium);
            $captchaFrame = $selenium->waitForElement(WebDriverBy::xpath("//div[@aria-hidden='false']//iframe"), 0);

            if ($captchaFrame) {
                $this->markProxyAsInvalid();
                $retry = true;

                return false;
            }

            $accountdetails = $selenium->waitForElement(WebDriverBy::id("tabs-accountdetails"), 0);

            if ($accountdetails) {
                $result = true;
            }

            $message = $this->http->FindSingleNode("//div[contains(@class, 'uitk-banner')]//div[contains(@class, 'uitk-banner-description')] | //h3[contains(@class, 'uitk-error-summary-heading')]");

            if ($message) {
                // Your account was paused in a routine security check. Your info is safe, but a password reset is required. Make a change with the "Forgot password?" link below to sign in.
                if (
                    strstr($message, 'Your account was paused in a routine security check.')
                    || $message == 'Email and password don\'t match. Please try again.'
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (
            NoSuchDriverException
            | Facebook\WebDriver\Exception\InvalidSessionIdException
            | Facebook\WebDriver\Exception\UnrecognizedExceptionException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $result;
    }
}
