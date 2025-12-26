<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common\Flight;

class TAccountCheckerVelocity extends TAccountChecker
{
    use ProxyList;
    use PriceTools;
    use SeleniumCheckerHelper;
    private const CONFIGS = [
        'firefox-100' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_100,
        ],
        'chrome-100' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_100,
        ],
        'chromium-80' => [
            'agent'           => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROMIUM,
            'browser-version' => SeleniumFinderRequest::CHROMIUM_80,
        ],
        'chrome-84' => [
            'agent'           => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_84,
        ],
        'chrome-95' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_95,
        ],
        'puppeteer-103' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
            'browser-version' => SeleniumFinderRequest::CHROME_PUPPETEER_103,
        ],
        'firefox-84' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_84,
        ],
    ];

    protected $collectedHistory = false;

    private $airCodes;
    private $EquipCode;
    private $headers;
    private $history = null;
    private $currentItin = 0;
    private $name = null;
    private $config;
    private $referer = '';

    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerVelocitySelenium.php";

            return new TAccountCheckerVelocitySelenium();
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        //$this->http->SetProxy($this->proxyDOP()); //$this->http->SetProxy($this->proxyWhite()); // todo: by agreement with Peter.Quigley@velocityfrequentflyer.com

        // error: Network error 28 - Connection timed out after ... milliseconds
        if ($this->attempt == 0) {
            $this->setProxyBrightData();
        } else {
            $array = ['us', 'ca', 'br', 'cl', 'mx'];
            $country = $array[random_int(0, count($array) - 1)];
            $this->setProxyGoProxies(null, $country);
        }
        //$this->http->setRandomUserAgent();
        $this->setConfig();

        $this->http->setCookie('virginCookiesAccepted', 'accepted', 'virginaustralia.com');
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['headers'])) {
            return false;
        }

        $this->headers = $this->State['headers'];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.velocityfrequentflyer.com/loyalty/v2/experience/members/me", $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (
            isset($response->data->member->loyaltyMembershipID)
            || isset($response->data->membershipId)
        ) {
            return true;
        }

        return false;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            if (self::CONFIGS[$this->config]['browser-family'] === SeleniumFinderRequest::BROWSER_FIREFOX
                || self::CONFIGS[$this->config]['browser-family'] === SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT) {
                $request = FingerprintRequest::firefox();
            } else {
                $request = FingerprintRequest::chrome();
            }
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if (isset($fingerprint)
                /*&& self::CONFIGS[$this->config]['browser-version'] !== SeleniumFinderRequest::CHROME_94
                && self::CONFIGS[$this->config]['browser-version'] !== SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101*/
            ) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
                $this->http->setUserAgent($fingerprint->getUseragent());
                $selenium->seleniumOptions->userAgent = $fingerprint->getUseragent();
            } else {
                $selenium->seleniumOptions->addHideSeleniumExtension = false;

                $selenium->seleniumOptions->userAgent = null;
            }

            $selenium->seleniumRequest->request(
                self::CONFIGS[$this->config]['browser-family'],
                self::CONFIGS[$this->config]['browser-version']
            );

            //$selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL('https://www.velocityfrequentflyer.com/');
                sleep(random_int(1, 4));
                $login = $selenium->waitForElement(WebDriverBy::xpath('//a[@id="joinNowLink"]/following-sibling::button[@aria-label="Log in"]'), 15);

                if (!$login) {
                    if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                        $this->markProxyAsInvalid();
                        $retry = true;
                    }

                    return false;
                }
                $login->click();
                sleep(5);
//                $this->savePageToLogs($selenium);
//                $selenium->http->GetURL('https://auth.velocityfrequentflyer.com/as/authorization.oauth2?client_id=vff_web&redirect_uri=https%3A%2F%2Fwww.velocityfrequentflyer.com%2Fmy-velocity%2Factivity%3Fexplicit_login%3Dweb&response_type=code&scope=openid+vff_web_group_scopes&state=2cda103d695e4eca96e62289f88660f7&code_challenge=aENgrk8P8F81pTF7eySwdnHjkR9F7sLODgrdez28i6o&code_challenge_method=S256&response_mode=query&prompt=login&cctp=velocity%3Ahome%3AvffLoginCta');

            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            }
            $this->savePageToLogs($selenium);

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"] | //input[@id="username"]'), 15);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="password"] | //input[@id="password"]'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@name="login"]'), 0);

            if (!$login || !$pass || !$btn) {
                return false;
            }

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($login, $this->AccountFields['Login'], 30);
            $mover->sendKeys($pass, $this->AccountFields['Pass'], 30);
            $this->savePageToLogs($selenium);
            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(),"Welcome back,")] | //a[@title="Activity"] | //div[contains(@class, "alert-error")]/span[contains(@class, "kc-feedback-text")]'), 25);
            $loginSuccess = $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(),"Welcome back,")] | //a[@title="Activity"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginSuccess) {
                if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-error")]/span[contains(@class, "kc-feedback-text")]')) {
                    $this->logger->error("[Error]: {$message}");

                    if (
                        strstr($message, "Your Velocity number or password is incorrect.")
                        || strstr($message, "The Velocity Membership number you have entered is associated with a closed account.")
                        || strstr($message, "Your account has been closed because a duplicate account exists")
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    if (
                        strstr($message, "The Velocity Membership number you have entered is associated with an inactive, suspended or closed account.")
                        || strstr($message, "Your account has been locked.")
                    ) {
                        throw new CheckException($message, ACCOUNT_LOCKOUT);
                    }

                    if (strstr($message, "Error while committing the transaction")) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                // The details entered do not match
                if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The details entered do not match')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindSingleNode("//span[contains(normalize-space(text()), 'INACTIVE, SUSPENDED OR CLOSED ACCOUNT')]")) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                // Unfortunately this request could not be processed
                if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Unfortunately this request could not be processed')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Oops! There seems to be an issue with your network connection.
                if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Oops! There seems to be an issue with your network connection.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->http->FindSingleNode('//p[contains(text(), "Sorry, we\'re having issues with our system.")]')) {
                    $retry = true;
                }

                return false;
            }

            $this->savePageToLogs($selenium);

            if ($this->ParseIts) {
                $selenium->setProxyMount();
                //$selenium->http->GetURL(' https://accounts.velocityfrequentflyer.com/auth/realms/velocity/protocol/saml/clients/ibe?RelayState=https%3A%2F%2Fbook.virginaustralia.com%2Fdx%2FVADX%2F%23%2Fdashboard%3Fcctp%3Dvald_velocity%3Amy-velocity%26channel%3Dvff-mybookings-browser');
                // https://accounts.velocityfrequentflyer.com/auth/realms/velocity/protocol/saml/clients/ibe?RelayState=https%3A%2F%2Fbook.virginaustralia.com%2Fdx%2FVADX%2F%23%2Fdashboard%3Fchannel%3Dvff-mybookings-browser

                $closeButton = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class,"mv__src-components-Modal-Modal_closeButton")]'), 0);
                if ($closeButton) {
                    $closeButton->click();
                }
                $menu = $selenium->waitForElement(WebDriverBy::xpath('//button[@id="control-login-module-dropdown-id" or @id="pointsButton"]'), 0);
                if ($menu) {
                    $menu->click();
                    $this->savePageToLogs($selenium);
                    $trips = $selenium->waitForElement(WebDriverBy::xpath('(//a[contains(@href,"auth/realms/velocity/protocol/saml/clients/ibe")])[1]'), 2);
                    if ($trips) {
                        $trips->click();
                        sleep(10);
                        $this->savePageToLogs($selenium);
                    }
                }
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                        $cookie['expiry'] ?? null);
                }
            }

            // https://accounts.velocityfrequentflyer.com/auth/realms/velocity/protocol/openid-connect/login-status-iframe.html/init?client_id=vff-join-client&origin=https%3A%2F%2Fwww.velocityfrequentflyer.com
            $selenium->http->GetURL('https://accounts.velocityfrequentflyer.com/auth/realms/velocity/protocol/openid-connect/login-status-iframe.html');
            sleep(random_int(3, 5));

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();
            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } finally {
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $currentUrl;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // Redirect to login page
        /*$this->http->RetryCount = 1;
        $this->http->GetURL('https://accounts.velocityfrequentflyer.com/auth/realms/velocity/protocol/openid-connect/auth?client_id=vff-join-client&redirect_uri=https%3A%2F%2Fwww.velocityfrequentflyer.com%2Fmy-velocity&state=13230540-2a57-474a-8b98-e9839676d345&response_mode=fragment&response_type=code&scope=openid&nonce=248362df-5bc0-4eac-bd5b-9ae883b1857b');
        $this->http->RetryCount = 2;*/

        //$this->http->setCookie('bm_sz', '543F9467294AD5A49EF90BED72C4E95E~0~YAAQZWQwFyoEaeWQAQAAEmxF/gyQkbYpG+lUAF2aRODBf7fPjzZqOc8tJaRIi3pJLn9kK7f73hnbmqQo/dBvFLKYBGPYhy4P8vMuxrcVXQxukLqDGLPjNmuIUDi45RrO6j3Evt9Pgwyb9Y8hZ8yoSj2hF6FVhMMrWrKv4t8+XsCXv4hmUMZhIs75LsP1XJqAwzYzyjSYZqdGD2yE5mAWD8oPaXbd8Xc4mhER8Soxz7gbDIO4TuJaTFilBa+vFubZ6tps4oVjWG9FzaEnmF8XAVrsG7fTsAFeBUEACoMHFDuPoLG7LGZZ0hZ075+qu7HvA2OcX9JOSxBKy4dWIeJQgPfGWTKIuWdDa0pn6DR7Mw1ow8d0E0nMT0GlDj83rOumIl9tjpT5viMkUVGMdAcAgXoryrOMEeqKYugOaIo3STAxT/3D0dPEHnBNGgFZFXvP~-1~||0||~1722256480');
        $this->referer = $this->selenium();

        if (!$this->referer) {
            return false;
        }

        return true;

        // proxy issues
        if (
            strstr($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT')
            || strstr($this->http->Error, 'Network error 28 - Connection timed out after ')
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 7);
        }

        if (!$this->http->ParseForm("velocityForm")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('
                //h1[contains(text(), "Oh crab! Our website is down for maintenance")]
                | //h2[contains(text(), "We are currently experiencing a technical issue")]
                | //p[contains(text(), "Our website is currently undergoing scheduled maintenance")]
                | //p[contains(text(), "we will be undertaking scheduled system maintenance")]    
                | //p[contains(text(), "Velocity Frequent Flyer is currently under maintenance.")]/text()[1]
        ')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The server is temporarily unable to service your request due to maintenance downtime or capacity problems.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The server is temporarily unable to service your request due to maintenance downtime or capacity problems.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // A system error occurred
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'A system error occurred')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Failed to retrieve the member profile information
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Failed to retrieve the member profile information')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are sorry, but we are unable to complete your request at this time
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are sorry, but we are unable to complete your request at this time')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - DNS failure')]")
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(7);
        $this->http->GetURL('https://experience.velocityfrequentflyer.com/my-velocity');
        $this->http->setMaxRedirects(5);
        // provider bug fix
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->http->FindPreg("/Scheduled-maintenance/ims", false, $this->http->currentUrl())) {
            throw new CheckException('We are currently undertaking scheduled maintenance and some of our usual services are not available.', ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently undertaking scheduled system maintenance
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are currently undertaking scheduled system maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Due to scheduled maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to scheduled maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are sorry, but we are unable to complete your request at this time
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are sorry, but we are unable to complete your request at this time')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 502 Proxy Error
        if ($message = $this->http->FindSingleNode("//title[contains(text(), '502 Proxy Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // hard code (AccountID: 1814707, 1457527)
        if (in_array($this->http->Response['code'], [307, 302]) && in_array($this->AccountFields['Login'], ['1018120313', '4520005353', '2101439866', '2111729904'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->Response['code'] == 307 && $this->http->FindSingleNode("//text()[contains(., 'Please wait while you are redirected to')]")) {
            throw new CheckRetryNeededException(3);
        }

        return false;
    }

    public function loginSuccessfulOld()
    {
        $this->logger->notice(__METHOD__);
        $identity = $this->http->getCookieByName("KEYCLOAK_IDENTITY", "accounts.velocityfrequentflyer.com", "/auth/realms/velocity/", true);
        $this->logger->debug("KEYCLOAK_IDENTITY: {$identity}");

        if ($identity) {
            $this->http->RetryCount = 0;
            $this->http->setMaxRedirects(1);
            $this->http->GetURL("https://accounts.velocityfrequentflyer.com/auth/realms/velocity/protocol/openid-connect/auth?client_id=vff-join-client&redirect_uri=https%3A%2F%2Fexperience.velocityfrequentflyer.com%2Fmy-velocity&state=d96ab9ca-68fb-42e1-82c9-cdd84f3ec2fc&nonce=766cea5b-fed8-4c18-b969-5930e6dce217&response_mode=fragment&response_type=code&scope=openid&prompt=none");
            $this->http->RetryCount = 2;
            $this->http->setMaxRedirects(5);
            $token = $this->http->FindPreg("/&code=([^\&]+)/", false, $this->http->currentUrl());

            if (!isset($token)) {
                return false;
            }

            $headers = [
                "Accept"          => "*/*",
                "Accept-Encoding" => "gzip, deflate, br",
                "Content-type"    => "application/x-www-form-urlencoded",
                "Origin"          => "https://experience.velocityfrequentflyer.com",
                "Referer"         => "https://experience.velocityfrequentflyer.com/my-velocity",
            ];
            $data = [
                "code"         => $token,
                "grant_type"   => "authorization_code",
                "client_id"    => "vff-join-client",
                "redirect_uri" => "https://experience.velocityfrequentflyer.com/my-velocity",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://accounts.velocityfrequentflyer.com/auth/realms/velocity/protocol/openid-connect/token", $data, $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!isset($response->access_token)) {
                return false;
            }
            $this->headers = [
                "Accept"         => "application/json",
                "Content-Type"   => "application/json",
                "Referer"        => "https://experience.velocityfrequentflyer.com/my-velocity",
                "Origin"         => "https://experience.velocityfrequentflyer.com",
                "client-channel" => "WEB",
                "authorization"  => "Bearer {$response->access_token}",
                "x-apikey"       => "o6wq2C1IiPG2TC6hlOAaaJjXtAYzDkIC", // https://experience.velocityfrequentflyer.com/my-velocity/my-activity/summary
            ];
            $this->State['headers'] = $this->headers;

            return true;
        }

        return false;
    }

    public function loginSuccessful($accessToken)
    {
        $this->logger->notice(__METHOD__);

        /*$data = [
            "grant_type" => "refresh_token",
            "refresh_token" => "yZeC4PbjjO7TSWzlbbx83YAvEi3st0Y8E2HxE4j3zs",
            "scope" => "openid vff_web_group_scopes",
            "client_id" => "vff_web",
            "client_secret" => $clientSecret
        ];
        $headers = [
            "Accept"          => "* / *",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-type"    => "application/x-www-form-urlencoded",
            "Origin"          => "https://experience.velocityfrequentflyer.com",
            "Referer"         => "https://experience.velocityfrequentflyer.com/my-velocity",
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://auth.velocityfrequentflyer.com/as/token.oauth2", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();*/

        $this->headers = [
            "Accept"         => "application/json",
            //"Content-Type"   => "application/json",
            "Referer"        => "https://www.velocityfrequentflyer.com/",
            "Origin"         => "https://www.velocityfrequentflyer.com",
            "client-channel" => "WEB",
            "authorization"  => "Bearer {$accessToken}",
            "x-apikey"       => "o6wq2C1IiPG2TC6hlOAaaJjXtAYzDkIC", // https://experience.velocityfrequentflyer.com/my-velocity/my-activity/summary
        ];
        $this->State['headers'] = $this->headers;
        return true;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }
        $timeout = 100;

        /*if (!$this->http->PostForm([
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,* / *;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Origin'=>'https://accounts.velocityfrequentflyer.com',
            'Priority' =>'u=0, i',
            'Referer' => $this->referer,
            'Connection' => null,
        ], $timeout)) {
            return $this->checkErrors();
        }

        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(3);
        }

        if ($redirect = $this->http->FindPreg('/<meta\s*http-equiv=\"refresh\"\s*content=\"1;\s*url=(https:\/\/experience\.velocityfrequentflyer\.com\/my-velocity)\"/')) {
            $this->http->GetURL($redirect);
        }

        if ($this->loginSuccessful()) {
            return true;
        }*/

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-error")]/span[contains(@class, "kc-feedback-text")]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, "Your Velocity number or password is incorrect.")
                || strstr($message, "The Velocity Membership number you have entered is associated with a closed account.")
                || strstr($message, "Your account has been closed because a duplicate account exists")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, "The Velocity Membership number you have entered is associated with an inactive, suspended or closed account.")
                || strstr($message, "Your account has been locked.")
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, "Error while committing the transaction")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // The details entered do not match
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The details entered do not match')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Unfortunately this request could not be processed
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Unfortunately this request could not be processed')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The Velocity Membership number you have entered is associated with an inactive, suspended or close account.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != "https://api.velocityfrequentflyer.com/loyalty/v2/experience/members/me") {
            $this->http->RetryCount = 1;
            $this->http->GetURL("https://api.velocityfrequentflyer.com/loyalty/v2/experience/members/me", $this->headers);
            $this->http->RetryCount = 2;
        }
        $response = $this->http->JsonLog();
        // An error occured while handling the request.
        if (isset($response->status) && $response->status == 500) {
            /*
            throw new CheckRetryNeededException(2);
            */
            sleep(3);
            $this->http->RetryCount = 1;
            $this->http->GetURL("https://api.velocityfrequentflyer.com/loyalty/v2/experience/members/me", $this->headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
        }

        // broken accounts: 1875635, 1786803, 3798748
        if (empty($this->http->Response['body']) && $this->http->Response['code'] == 200) {
            $this->http->RetryCount = 1;
            $this->http->GetURL("https://api.velocityfrequentflyer.com/loyalty/v2/members/profile", $this->headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
        }

        if (
            !isset($response->data)
            && isset($response->detail)
            && $response->detail == 'The server encountered an internal error or misconfiguration and is unable to complete your request'
        ) {
            throw new CheckRetryNeededException(2);
        }

        if (
            !isset($response->data)
            && isset($response->detail, $response->status)
            && in_array($response->status, [500, 503])
            && in_array($response->detail, [
                'Unknown error detected when processing the request',
                "The server is currently unable to handle the request due to a temporary overload or scheduled maintenance",
            ])
        ) {
            throw new CheckException("Sorry we're having issues with our system. Please refresh the page or try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        // Name
        $this->name = [
            'lastName'  => $response->data->member->lastName ?? $response->data->individual->identity->firstName ?? null,
            'firstName' => $response->data->member->firstName ?? $response->data->individual->identity->lastName ?? null,
        ];
        $this->SetProperty('Name', beautifulName($this->name['firstName'] . " " . $this->name['lastName']));
        // Member since
        $this->SetProperty('MemberSince', date("d M Y", strtotime($response->data->account->joinDate ?? $response->data->enrolmentDate)));
        // Balance - Points Balance
        $this->SetBalance($response->data->account->currentPointsBalance ?? $response->data->pointBalance);
        // Membership number
        $this->SetProperty('Number', $response->data->member->loyaltyMembershipID ?? $response->data->membershipId);
        // Membership level
        $level = $response->tiers->mainTierInfo->tierLevel ?? null;

        switch ($level) {
            case 'R':
                $this->SetProperty('Level', "Red");

                break;

            case 'S':
                $this->SetProperty('Level', "Silver");

                break;

            case 'G':
                $this->SetProperty('Level', "Gold");

                break;

            case 'P':
                $this->SetProperty('Level', "Platinum");

                break;

            case 'V':
                $this->SetProperty('Level', "VIP");

                break;

            default:
                if ($level !== null) {
                    $this->sendNotification("velocity. New status was found {$level}");
                }
        }// switch ($level)
        // Status Credit Balance
        $statusCreditBalance = $response->data->account->statusCreditsBalance ?? $response->data->statusCredit ?? null;
        $this->SetProperty('StatusCreditBalance', $statusCreditBalance);

        if ($statusCreditBalance > 0) {
            $this->AddSubAccount([
                'Code'           => 'velocityStatusCredit',
                'DisplayName'    => 'Status Credit',
                'Balance'        => $statusCreditBalance,
                'ExpirationDate' => strtotime($response->data->account->currentPointsBalanceExpiryDate ?? null, false),
            ], true);
        }
        // Status Credits to upgrade
        $this->SetProperty('CreditsToUpgrade', $response->data->tiers->periodicTierInfo->upgradeTierInfo->creditsRequired ?? null);
        // Eligible sectors flown
        $this->SetProperty('EligibleSectorsFlown', $response->data->account->eligibleSectorsBalance ?? $response->data->eligibleSector);

//        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
//            if (
//                ArrayVal($response, 'description') == 'An error occured while handling the request.'
//                && ArrayVal($response, 'message') == 'Server Error'
//            ) {
//                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
//            }
//        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $this->logger->info('Lounge passes', ['Header' => 3]);
//        $this->http->GetURL("https://experience.velocityfrequentflyer.com/my-velocity/benefits");
        $this->http->GetURL("https://api.velocityfrequentflyer.com/loyalty/v2/experience/benefits/me", $this->headers);
        $benefits = $this->http->JsonLog()->data ?? null;
        // Virgin Australia Lounge member
        $this->SetProperty('LoungeMember', $benefits->loungeDetails->membershipId ?? null);
        // Virgin Australia Lounge passes remaining
        $loungePasses = $benefits->loungeDetails->voucherPasses ?? [];
        $loungePassesAvailable = 0;
        // Lounge passes
        $this->logger->debug("Total " . count($loungePasses) . " Lounge passes were found");
        $this->SetProperty("CombineSubAccounts", false);
        // https://experience.velocityfrequentflyer.com/etc.clientlibs/my-velocity/clientlibs/my-velocity-react-6_0_0.lc-142-lc.min.js
        $voucherTypes = [
            "AMEX"          => "Amex lounge voucher",
            "FARE"          => "Fare Class",
            "FOC_VOUCH"     => "FOC Voucher",
            "MEMBER"        => "Lounge Passport Holder",
            "PRE_PAID"      => "Single Entry Pass",
            "TIERED_G"      => "Gold tiered Velocity member",
            "TIER_CHANGE"   => "Silver tiered voucher",
            "HSBC"          => "HSBC Lounge Voucher",
            "VIRGIN_MONEY"  => "Virgin Money lounge voucher",
            "CASUAL"        => "Purchased via GMS",
            "LIFETIME"      => "Lifetime",
            "INFANT"        => "Infant",
            "CHILD"         => "Child",
            "GUEST"         => "Guest",
            "TIERED_V"      => "VIP tiered Velocity member",
            "TIERED_P"      => "Platinum tiered Velocity member",
            "CASUAL_PASS"   => "Purchased",
            "WESTPAC_VOUCH" => "Westpac Lounge Voucher",
            "NZ_GOLD"       => "Gold tiered Airpoints member",
            "NZ_GOLD_ELITE" => "Gold Elite tiered Airpoints member",
            "NZ_KORU"       => "Koru Club member",
            "NZ_SILVER"     => "Silver tiered Airpoints member",
            "VFFCAMP"       => "Velocity Campaign Lounge Pass",
            "VABF"          => "Virgin Australia Business Flyer Lounge Pass",
        ];

        foreach ($loungePasses as $loungePasse) {
            if (!isset($voucherTypes[$loungePasse->voucherType])) {
                $this->sendNotification("voucherType not found, need to check -> '{$loungePasse->voucherType}'");

                continue;
            }

            $this->AddSubAccount([
                'Code'           => 'velocityLoungePasses' . $loungePasse->voucherID,
                'DisplayName'    => "Lounge pass: Single Entry",
                'Balance'        => $loungePasse->numberAvailable,
                'Type'           => $voucherTypes[$loungePasse->voucherType],
                'ExpirationDate' => strtotime($loungePasse->endDate, false),
            ], true);

            $loungePassesAvailable += $loungePasse->numberAvailable;
        }// foreach ($loungePasses as $loungePasse)

        $this->SetProperty('LoungePasses', $loungePassesAvailable);

        $this->logger->info('Complimentary Fare Upgrades', ['Header' => 3]);
        $awardCredits = $benefits->awardCreditDetails->awardCredits ?? [];

        if ($awardCredits) {
            foreach ($awardCredits as $awardCredit) {
                if (!strstr($awardCredit->description, 'Confirmed Complimentary Upgrade')) {
                    $this->logger->debug("skip non Confirmed Complimentary Upgrade");

                    continue;
                }

                $subAcc = [
                    'Code'        => 'velocityComplimentaryFareUpgrades',
                    'DisplayName' => "Complimentary Fare Upgrades",
                    'Balance'     => $awardCredit->totalAvailable,
                ];

                foreach ($awardCredit->available as $item) {
                    $balance = $item->amount;
                    $upgradeExpiration = $item->expiresAt;

                    if (
                        $balance > 0
                        && (
                            (
                                !isset($subAcc['ExpirationDate'])
                                || strtotime($upgradeExpiration) < $subAcc['ExpirationDate']
                            )
                            && strtotime($upgradeExpiration) > time()
                        )
                    ) {
                        $subAcc['ExpiringBalance'] = $balance;
                        $subAcc['ExpirationDate'] = strtotime($upgradeExpiration);
                    }
                }// foreach ($awardCredit->available as $item)

                $this->AddSubAccount($subAcc, true);
            }// foreach ($awardCredits as $awardCredit)
        }// if ($awardCredits)

        // Expiration date  // refs #14171
        // https://redmine.awardwallet.com/issues/14171
        $this->getExpirationDate();
    }

    public function getHistoryData()
    {
        $this->logger->notice(__METHOD__);

        if (!empty($this->history)) {
            return $this->history;
        }

//        $this->http->GetURL('https://experience.velocityfrequentflyer.com/my-velocity/my-activity/summary');
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-3 year"));
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://api.velocityfrequentflyer.com/loyalty/v2/experience/activities/me?startDate={$startDate}&endDate={$endDate}&pageOffset=0&pageLimit=100&include=categories", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 1)->data->history ?? [];

        /*
        // provider bug fix
        if (
            in_array($this->http->Response['code'], [400])
            || (isset($response->fault->faultstring) && $response->fault->faultstring == 'Gateway Timeout')
        ) {
            sleep(7);
            $this->http->GetURL("https://api.velocityfrequentflyer.com/loyalty/v2/experience/activities/me?startDate={$startDate}&endDate={$endDate}&pageOffset=0&pageLimit=100&include=categories", $this->headers);
            $response = $this->http->JsonLog();
        }
        */

        $this->logger->debug("Total " . ((is_array($response) || ($response instanceof Countable)) ? count($response) : 0) . " history items were found");

        if (in_array($this->http->Response['code'], [401, 404, 500, 504])) {
            return [];
        }

        $this->history = $response;

        return $response;
    }

    public function getExpirationDate()
    {
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $response = $this->getHistoryData();

        if (empty($response)) {
            return;
        }

        foreach ($response as $transaction) {
            $points = $transaction->points->awardPoints ?? 0;

            if ($points == 0) {
                continue;
            }

            $this->logger->debug("Points: " . $points);
            $exDate = $transaction->activityDate;

            if (empty($exDate)) {
                continue;
            }

            $lastActivity = $exDate;

            if ($exDate = strtotime($lastActivity)) {
                // Last Activity
                $this->SetProperty('LastActivity', $lastActivity);
                // Expiration Date
                if ($exDate < 1464739200) {
                    $this->logger->notice("LastActivity before 01 Jun 2016: exp date = last activity + 36 month");
                    $this->SetExpirationDate(strtotime("+3 year", $exDate));
                }// if ($exDate < 1464739200)
                else {
                    $this->logger->notice("LastActivity after 01 Jun 2016: exp date = last activity + 24 month");
                    $this->SetExpirationDate(strtotime("+2 year", $exDate));
                }

                break;
            }// if ($exDate = strtotime($lastActivity))
        }
    }

    public function ParseItineraries()
    {
        /*
        $this->http->GetURL('https://accounts.velocityfrequentflyer.com/auth/realms/velocity/protocol/saml/clients/ibe?RelayState=https%3A%2F%2Fbook.virginaustralia.com%2Fdx%2FVADX%2F%23%2Fdashboard%3Fcctp%3Dvald_velocity%3Amy-velocity%3Aactivity%26channel%3Dvff-mybookings-browser');

        if (!$this->http->ParseForm('saml-post-binding')) {
            $this->logger->error('Something went wrong');

            return [];
        }
        $this->http->PostForm();
        */


        $headers = [
            'application-id' => 'SWS1:SBR-DigConShpBk:fd34efe9a9',
            'Accept'             => '*/*',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'x-locale'           => 'en-US',
            'Content-Type'       => 'application/json',
            'Authorization'      => 'Bearer Basic anNvbl91c2VyOmpzb25fcGFzc3dvcmQ=',
            'Referer'            => 'https://book.virginaustralia.com/dx/VADX/',
            'Origin'             => 'https://book.virginaustralia.com',
            'Adrum'              => 'isAjax:true',
            'X-Sabre-Storefront' => 'VADX',
            'Dc-Url'             => '',
            'Execution'          => 'e2s1',
            'Ssgtoken'           => 'undefined',
            'Ssotoken'           => 'undefined',
            'priority' => 'u=1, i'
        ];
        $data = '{"operationName":"getProfileUpcomingTrips","variables":{},"extensions":{},"query":"query getProfileUpcomingTrips {\n  getProfileUpcomingTrips {\n    originalResponse\n    __typename\n  }\n}"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://book.virginaustralia.com/api/graphql", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        //$this->sendNotification("response {$this->http->Response['code']} // MI");

        if ($this->http->FindPreg('/\"originalResponse\":\{"trips":\[\]\},/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if (!isset($response->data->getProfileUpcomingTrips)) {
            // INACTIVE, SUSPENDED OR CLOSED ACCOUNT
            // The Velocity Membership number you have entered is associated with an inactive, suspended or close account. Please check that you have entered the correct membership details or contact the Membership Contact Centre for assistance.
            if (
                isset($response->errors[0]->message)
                && $response->errors[0]->message == 'Request failed with status code 500'
            ) {
                $this->logger->error("[Error]: INACTIVE, SUSPENDED OR CLOSED ACCOUNT");

                return [];
            }
        }

        $trips = $response->data->getProfileUpcomingTrips->originalResponse->trips ?? [];
        $this->logger->debug("Found " . count($trips) . " routes.");

        foreach ($trips as $trip) {
            if (!$this->ParsePastIts && strtotime($trip->departureDate) < time()) {
                $this->logger->notice('Past parking, skip it');

                continue;
            }

            $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$trip->pnr}", ['Header' => 3]);
            $this->currentItin++;

            $data = '{"operationName":"getMYBTripDetails","variables":{"pnrQuery":{"pnr":"' . $trip->pnr . '","lastName":"' . $this->name['lastName'] . '"}},"extensions":{},"query":"query getMYBTripDetails($pnrQuery: JSONObject!) {\n  getMYBTripDetails(pnrQuery: $pnrQuery) {\n    originalResponse\n    __typename\n  }\n  getStorefrontConfig {\n    privacySettings {\n      isEnabled\n      maskFrequentFlyerNumber\n      maskPhoneNumber\n      maskEmailAddress\n      maskDateOfBirth\n      maskTravelDocument\n      __typename\n    }\n    __typename\n  }\n}\n"}';
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://book.virginaustralia.com/api/graphql", $data, $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!isset($response->data->getMYBTripDetails)) {
                // Unable to retrieve booking
                // We are unable to retrieve the booking. If the booking was created by a Travel Agent please contact the booking agency or contact our Guest Contact Centre on 13 67 89. If the booking was purchased directly with Virgin Australia please ensure the information you have entered is correct and try again.
                if (
                    isset($response->errors[0]->message)
                    && $response->errors[0]->message == 'Request failed with status code 500'
                ) {
                    $this->logger->error("[Error]: Unable to retrieve booking");

                    continue;
                }
            }

            $this->parseItinerary($response->data->getMYBTripDetails->originalResponse->pnr);

            break;
        }// foreach ($trips as $trip)

        return [];
    }

    public function convertToHoursMinutes($minutes)
    {
        if ($minutes < 1) {
            return null;
        }
        $hours = floor($minutes / 60);
        $minutes = ($minutes % 60);

        if ($hours > 0) {
            if ($minutes > 0) {
                return sprintf('%2d hr %02d min', $hours, $minutes);
            } else {
                return sprintf('%2d hr', $hours);
            }
        } else {
            return sprintf('%2d min', $minutes);
        }
    }

    public function findEquipCode($name)
    {
        $this->logger->notice("findEquipCode -> " . $name);
        $code = null;

        if (empty($name)) {
            return $code;
        }

        if (isset($this->EquipCode[$name])) {
            return $this->EquipCode[$name];
        }

        return $name;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Last Name",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.virginaustralia.com/au/en/beta/?screen=mytrips&&error=login_required#myTrips";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        //$this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->seleniumConfNo();

        /*if (!$this->http->FindSingleNode("//input[@id='pnr']")) {
            $this->sendNotification("velocity - failed to retrieve itinerary by conf #", 'all', true,
                "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}");

            return null;
        }*/

        $this->http->GetURL('https://book.virginaustralia.com/dx/VADX/');
        $authorization = $this->http->FindPreg("/sabre\['access_token'\] = '(.+?)';\s/");

        if (!$authorization) {
            $this->sendNotification("velocity - failed to retrieve itinerary by conf #");

            return null;
        }
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://dc.virginaustralia.com/v4.3/dc/pnr?pnr={$arFields['ConfNo']}&lastName={$arFields['LastName']}&jipcc=VADX", [
            'Authorization' => "Bearer {$authorization}",
        ]);
        $this->http->RetryCount = 2;
        $resp = $this->http->JsonLog(null, 0);

        if (isset($resp->errorCode) && $resp->errorCode) {
            return $resp->message;
        }
        $this->parseItinerary($arFields['ConfNo']);

        return null;
    }

    public function seleniumConfNo()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1024, 786],
                [1152, 864],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);
            $selenium->useChromium();
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL($this->ConfirmationNumberURL(null));
            $cookie = $selenium->waitForElement(WebDriverBy::id("cookieAcceptanceBtn"), 10);

            if ($cookie) {
                $cookie->click();
            }
            sleep(5);
            $this->savePageToLogs($selenium);

            // save page to logs
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $currentUrl;
    }

    public function CheckConfirmationNumberInternal2($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->http->Form = [
            'reservationCode' => $arFields['ConfNo'],
            'lastName'        => $arFields['LastName'],
            'inOverlay'       => 'false',
            'brSubmit'        => 'Find Flight',
            'componentTypes'  => 'bookingretrieval',
        ];
        $this->http->FormURL = "https://fly.virginaustralia.com/SSW2010/VAVA/myb.html?d=dummy";

        if (!$this->http->PostForm()) {
            $this->sendNotification("velocity - failed to retrieve itinerary by conf #", 'all', true,
                "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}");

            return null;
        }
        // bad json fix
        $this->http->Response['body'] = preg_replace('/&(.+?);/', '', $this->http->Response['body']);

        if ($error = $this->http->FindSingleNode('//h2[contains(text(), "Unable to retrieve Booking")]')) {
            return $error;
        }

        if ($error = $this->http->FindSingleNode('//h2[contains(text(), "Unfortunately we\'ve experienced an error while processing your request.")]')) {
            return $error;
        }

        if ($error = $this->http->FindSingleNode('//div[@data-wl-translate=\'flow.message.noItineraryInBooking.message\']')) {
            return $error;
        }

        $data = $this->http->FindPreg('/var templateData = (.+);[\n\r]+/');
        $data = json_decode($data, true);

        if ($data) {
            $this->logger->notice('found JSON of itinerary');
        } else {
            $this->logger->notice('itinerary JSON not found');
        }

        if (isset($data) && isset($data['rootElement']['children']) && is_array($data['rootElement']['children'])) {
            $flight = $this->itinerariesMaster->add()->flight();

            foreach ($data['rootElement']['children'] as $rootChild) {
                if (isset($rootChild['children']) && is_array($rootChild['children'])) {
                    foreach ($rootChild['children'] as $child) {
                        if (isset($child['children']) && is_array($child['children'])) {
                            foreach ($child['children'] as $node) {
                                if (isset($node['componentCode']) && $node['componentCode'] == 'flightsdetails' && isset($node['model']['itineraryParts']) && is_array($node['model']['itineraryParts'])) {
                                    $this->parseItineraryJsonV2($flight, $node['model']);
                                }
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Process date"    => "PostingDate",
            "Activity date"   => "Info.Date",
            "Description"     => "Description",
            "Status Credits"  => "Info.Int",
            "Bonus Points"    => "Bonus",
            "Velocity Points" => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();
        $response = $this->getHistoryData();
        $page = 0;
        $page++;
        $this->logger->debug("[Page: {$page}]");
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate, $response));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate, $response)
    {
        $result = [];

        if (empty($response)) {
            return $result;
        }

        foreach ($response as $transaction) {
            $dateStr = $transaction->points->postedDate ?? $transaction->activityDate; //AccountID: 2823188
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }

            $result[$startIndex]['Process date'] = $postDate;
            $result[$startIndex]['Activity date'] = strtotime($transaction->activityDate);
            $result[$startIndex]['Description'] = $transaction->description;
            $result[$startIndex]['Status Credits'] = $transaction->points->statusCredits ?? 0;

            $pointsType = 'Velocity Points';

            if ($this->http->FindPreg('/Bonus/i', false, $transaction->description)) {
                $pointsType = 'Bonus';
            }

            $result[$startIndex][$pointsType] = $transaction->points->awardPoints ?? 0;

            $startIndex++;
        }

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    private function setConfig()
    {
        $configs = self::CONFIGS;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            unset($configs['chrome-94-mac']);
        }

        $successfulConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('velocity_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('velocity_config_' . $key) !== 0;
        });

        if (count($successfulConfigs) > 0) {
            $this->config = $successfulConfigs[array_rand($successfulConfigs)];
            $this->logger->info("found " . count($successfulConfigs) . " successful configs");
        } elseif (count($neutralConfigs) > 0) {
            $this->config = $neutralConfigs[array_rand($neutralConfigs)];
            $this->logger->info("found " . count($neutralConfigs) . " neutral configs");
        } else {
            $this->config = array_rand($configs);
        }

        $this->logger->info("selected config $this->config");
    }

    private function markConfigAsBadOrSuccess($isBad = false): void
    {
        if ($isBad) {
            $this->logger->info("marking config {$this->config} as bad");
            Cache::getInstance()->set('velocity_config_' . $this->config, 0, 60 * 60);
        } else {
            $this->logger->info("marking config {$this->config} as successful");
            Cache::getInstance()->set('velocity_config_' . $this->config, 1, 60 * 60);
        }
    }

    private function parseItinerary($resp)
    {
        $this->logger->notice(__METHOD__);

        if (empty($resp->itinerary->itineraryParts->segments) && $this->http->FindPreg('/"segments":\[\],"stops":\d+,"totalDuration"/')) {
            $this->logger->error('Segments empty');

            return;
        }

        $f = $this->itinerariesMaster->add()->flight();

        // {ONHOLD:"ONHOLD",CANCELLED:"CANCELLED",ACTIVE:"ACTIVE",NEW_FLIGHT:"NEW_FLIGHT",SCHEDULE_CHANGE:"NEWFLIGHT",
        // CONFIRMED:"CONFIRMED",OTHER:"OTHER",REMOVED:"REMOVED",CHANGED:"CHANGED"}
        /*if ($status == 'Cancelled') {
            $f->general()->status('Pending');
            $f->general()->cancelled();
        }*/

        $f->general()->confirmation($resp->reloc, 'Booking reference', true);

        $accounts = [];

        foreach ($resp->passengers as $passenger) {
            $f->general()->traveller(beautifulName($passenger->passengerDetails->firstName . ' ' . $passenger->passengerDetails->lastName));

            if (isset($passenger->preferences->frequentFlyer[0])) {
                $accounts[] = $passenger->preferences->frequentFlyer[0]->number;
            }
        }
        $f->program()->accounts(array_unique($accounts), false);

        $eticketNumbers = [];

        foreach ($resp->travelPartsAdditionalDetails as $parts) {
            if (isset($parts->passengers)) {
                foreach ($parts->passengers as $passenger) {
                    if (isset($passenger->eticketNumber)) {
                        $eticketNumbers[] = $passenger->eticketNumber;
                    }

                    continue;
                }
            }
        }
        $f->issued()->tickets(array_unique($eticketNumbers), false);

        //$stops = 0;

        foreach ($resp->itinerary->itineraryParts as $key => $its) {
            //$stops = $its->stops;
            $segments = $its->segments ?? [];

            foreach ($segments as $seg) {
                if (!isset($seg->flight, $seg->origin, $seg->arrival, $seg->bookingClass)) {
                    continue;
                }
                $s = $f->addSegment();
                $s->airline()->name($seg->flight->airlineCode);
                $s->airline()->number($seg->flight->flightNumber);
                $s->departure()->code($seg->origin);
                $s->departure()->date2($seg->departure);

                $s->arrival()->code($seg->destination);
                $s->arrival()->date2($seg->arrival);

                if ($s->getArrDate() < $s->getDepDate()) {
                    $s->arrival()->date(strtotime('+1 day', $s->getArrDate()));
                }

                if ($s->getArrDate() < $s->getDepDate()) {
                    $s->arrival()->date(strtotime('+1 year', $s->getArrDate()));
                }
                $s->extra()->cabin($seg->cabinClass ?? null, false, true);

                if ($seg->bookingClass !== 'notAvailable') {
                    $s->extra()->bookingCode($seg->bookingClass);
                }
                $s->extra()->duration($this->convertToHoursMinutes($seg->duration), false, true);
                //$s->extra()->stops($stops);
                if (isset($resp->travelPartsAdditionalDetails[$key]->passengers)) {
                    foreach ($resp->travelPartsAdditionalDetails[$key]->passengers as $passenger) {
                        if (isset($passenger->seat->seatCode)) {
                            $s->extra()->seat($passenger->seat->seatCode);
                        }
                    }
                }

                if ($seg->layoverDuration > 0 && is_array($seg->flight->stopAirports)) {
                    $s->extra()->stops(count($seg->flight->stopAirports));
                }
            }
        }

        if (isset($resp->priceBreakdown)) {
            $priceBreakdown = $resp->priceBreakdown;
            //$this->logger->debug(var_export($priceBreakdown->price->alternatives[0], true));
            if (isset($priceBreakdown->price->alternatives[0][0]->amount)) {
                $f->price()->total($priceBreakdown->price->alternatives[0][0]->amount);
            }

            if (isset($priceBreakdown->price->alternatives[0][0]->currency)) {
                $f->price()->currency($priceBreakdown->price->alternatives[0][0]->currency);
            }

            foreach ($priceBreakdown->subElements as $elem) {
                if ($elem->label == 'taxesPrice') {
                    foreach ($elem->price->alternatives as $tax) {
                        $f->price()->tax($tax[0]->amount);
                    }
                }
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryJsonV2(Flight $flight, $root)
    {
        $this->logger->notice(__METHOD__);

        // Passengers, AccountNumbers
        $passengers = [];
        $accountNumbers = [];

        if (isset($root['passengers']) && is_array($root['passengers'])) {
            foreach ($root['passengers'] as $pass) {
                $passengers[] = beautifulName(ArrayVal($pass, 'firstName') . ' ' . ArrayVal($pass, 'lastName'));
                // AccountNumbers
                if (isset($pass['frequentFlyer']) && is_array($pass['frequentFlyer'])) {
                    foreach ($pass['frequentFlyer'] as $frequentFlyer) {
                        $accountNumbers[] = $frequentFlyer['number'];
                    }
                }
            }
        }
        $passengers = array_values(array_unique($passengers));
        $flight->setTravellers($passengers, true);
        $accountNumbers = array_values(array_unique($accountNumbers));
        $flight->setAccountNumbers($accountNumbers, false);

        foreach ($root['itineraryParts'] as $children) {
            if (isset($children['segments']) && is_array($children['segments'])) {
                foreach ($children['segments'] as $node) {
                    $seg = $flight->addSegment();
                    // FlightNumber
                    $seg->setFlightNumber(is_array(ArrayVal($node, 'flightNumber')) ? implode('', ArrayVal($node, 'flightNumber')) : ArrayVal($node, 'flightNumber'));
                    // AirlineName
                    $airline = is_array(ArrayVal($node, 'wetLessor')) ? implode('', ArrayVal($node, 'wetLessor')) : ArrayVal($node, 'wetLessor');

                    if (empty($airline)) {
                        $airline = is_array(ArrayVal($node, 'farePublishingAirline')) ? implode('', ArrayVal($node, 'farePublishingAirline')) : ArrayVal($node, 'farePublishingAirline');
                    }

                    if (empty($airline)) {
                        $airline = is_array(ArrayVal($node, 'airlineCodes')) ? implode('', ArrayVal($node, 'airlineCodes')) : ArrayVal($node, 'airlineCodes');
                    }
                    $seg->setAirlineName($airline);
                    // DepCode, DepName, DepDate
                    if (isset($node['flightTO'])) {
                        $depCode = ArrayVal($node['flightTO']['origin'], 'code');
                        $arrCode = ArrayVal($node['flightTO']['destination'], 'code');
                    } else {
                        $depCode = $node['departureCode'];
                        $arrCode = $node['arrivalCode'];
                    }

                    if ($depCode !== 'notAvailable') {
                        $seg->departure()
                            ->code($depCode);
//                            ->name(ArrayVal($node['flightTO']['origin'], 'code'));
                    } else {
                        $seg->departure()->noCode();
                    }
                    $depDateStr = ArrayVal($node, 'departure');
                    $this->logger->debug('depDate: ' . $depDateStr);
                    $seg->departure()->date(strtotime(str_replace("/", "-", $depDateStr), false));
                    $this->logger->debug("parsed date: " . $seg->getDepDate());
                    // ArrCode, ArrName, ArrDate
                    if ($arrCode !== 'notAvailable') {
                        $seg->arrival()
                            ->code($arrCode);
//                            ->name(ArrayVal($node['flightTO']['destination'], 'code'));
                    } else {
                        $seg->arrival()->noCode();
                    }
                    $arrDateStr = ArrayVal($node, 'arrival');
                    $this->logger->debug('arrDate: ' . $arrDateStr);
                    $arrDate = strtotime(str_replace("/", "-", $arrDateStr), false);
                    $this->logger->debug("parsed date: " . $arrDate);

                    if ($arrDate < strtotime('-6 months', $seg->getDepDate())) {
                        $seg->setNoArrDate(true);
                    } else {
                        if ($arrDate < strtotime('-6 days', $seg->getDepDate())
                            && $arrDate > strtotime('-10 days', $seg->getDepDate())
                            && $airline == 'VA' && $seg->getFlightNumber() == '91'// fs don't now VA91; duration with other airlines ~4:50+
                        ) {
                            $seg->setArrDate(strtotime(date('H:i', $arrDate), $seg->getDepDate()));
                        } else {
                            $seg->setArrDate($arrDate);
                        }
                    }
                    // Cabin
                    $seg->setCabin(is_array(ArrayVal($node, 'bookingClass'))
                        && isset($node['bookingClass']['bookingClass'])
                        ? $node['bookingClass']['bookingClass'] : ArrayVal($node, 'bookingClass'), false, true);
                    // TravelledMiles
                    $seg->setMiles(ArrayVal($node, 'flightMiles'), false, true);
                    // Stops
                    $seg->setStops(ArrayVal($node, 'numberOfStops') ?: null, false, true);
                    // Aircraft
                    $seg->setAircraft($this->findEquipCode(ArrayVal($node['equipment'], 'equipmentCode')), false, true);
                    // Duration
                    $durHours = intval(ArrayVal($node, 'durationInHours'));
                    $durMinutes = intval(ArrayVal($node, 'durationInMinutes'));

                    if ($durHours > 0 || $durMinutes > 0) {
                        $seg->setDuration($durHours . "h " . $durMinutes . "m");
                    }
                    // Seats

                    $seats = [];

                    if (isset($root['passengers']) && is_array($root['passengers'])) {
                        foreach ($root['passengers'] as $pass) {
                            if (isset($pass['sectorSeats']) && is_array($pass['sectorSeats'])) {
                                foreach ($pass['sectorSeats'] as $seatCode) {
                                    if (
                                    isset($seatCode['sectorKey']['flightNumber'])
                                    && $seatCode['sectorKey']['flightNumber'] == $seg->getFlightNumber()
                                ) {
                                        $seats[] = ArrayVal($seatCode, 'seatCode');
                                    }
                                }
                            }// foreach ($pass['sectorSeats'] as $seatCode)
                        }// foreach ($root['passengers'] as $pass)
                    }// if (isset($root['passengers']) && is_array($root['passengers']))
                    $seats = array_unique($seats);

                    if (count($seats) > 0) {
                        $seg->setSeats($seats);
                    }
                }
            }
        }
    }
}
