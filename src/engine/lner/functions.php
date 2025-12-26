<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerLner extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.lner.co.uk/';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    // public function LoadLoginForm()
    // {
    //     $this->http->removeCookies();
    //     $this->http->GetURL('https://www.lner.co.uk/quick-registration/');
    //     $clientId = $this->http->FindPreg("/\"clientId\":\s*\"([^\"]+)/");
    //     // $apiKey = $this->http->FindPreg("/\"apiKey\":\s*\"([^\"]+)/");

    //     if (!$this->http->ParseForm(null, '//form[@id="login-complete-form"]') || !$clientId) {
    //         return $this->checkErrors();
    //     }

    //     $this->seleniumInit();
    //     $this->seleniumInstance->http->GetURL('https://www.lner.co.uk/quick-registration/');

    //     $this->seleniumInstance->driver->executeScript("setInterval(function() { $('#onetrust-accept-btn-handler').click(); }, 1000)");

    //     $username = $this->seleniumInstance->waitForElement(WebDriverBy::xpath('//input[@id="lner-authentication-email"]'), 5);
    //     $password = $this->seleniumInstance->waitForElement(WebDriverBy::xpath('//input[@id="lner-authentication-password"]'), 0);
    //     $button = $this->seleniumInstance->waitForElement(WebDriverBy::xpath('//button[@id="lner-authentication-sign-in-button"]'), 0);

    //     $this->saveResponse();

    //     if (!$username || !$password || !$button) {
    //         return $this->checkErrors();
    //     }

    //     $this->seleniumInstance->driver->executeScript(
    //         "
    //         function triggerInput(enteredName, enteredValue) {
    //             const input = document.getElementById(enteredName);
    //             const lastValue = input.value;
    //             input.value = enteredValue;
    //             const event = new Event(`input`, { bubbles: true });
    //             const tracker = input._valueTracker;
    //             if (tracker) {
    //               tracker.setValue(lastValue);
    //             }
    //             input.dispatchEvent(event);
    //           }
    //           triggerInput(`lner-authentication-email`, `".$this->AccountFields['Login']."`);
    //           triggerInput(`lner-authentication-password`, `".$this->AccountFields['Pass']."`);
    //           document.getElementById(`lner-authentication-sign-in-button`).click();
    //         "
    //     );

    //     sleep(5);
    //     $this->saveResponse();

    //     // $username->sendKeys($this->AccountFields['Login']);
    //     // sleep(1);
    //     // $password->sendKeys($this->AccountFields['Pass']);
    //     // sleep(1);
    //     // $button->click();

    //     return true;
    // }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.lner.co.uk/quick-registration/');
        $clientId = $this->http->FindPreg("/\"clientId\":\s*\"([^\"]+)/");
        $apiKey = $this->http->FindPreg("/\"apiKey\":\s*\"([^\"]+)/");

        if (!$this->http->ParseForm(null, '//form[@id="login-complete-form"]') || !$clientId) {
            return $this->checkErrors();
        }

        $captcha = $this->parseCaptcha();

        if (!$captcha) {
            return false;
        }

        $postData = [
            'emailAddress'          => $this->AccountFields['Login'],
            'password'              => $this->AccountFields['Pass'],
            'reCaptchaVerifyResult' => [
                // "executionTime" => rand(100, 1500),
                "token"         => $captcha,
            ],
        ];

        $postHeaders = [
            "Accept"         => "*/*",
            "Accept-Version" => 1,
            "Content-Type"   => "application/json",
            "Origin"         => "https://www.lner.co.uk",
            "Referer"        => "https://www.lner.co.uk/",
            "X-Api-Key"      => $apiKey,
            "X-Client-Id"    => $clientId,
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://auth.lner.co.uk/login", json_encode($postData), $postHeaders);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            // $this->captchaReporting($this->recognizer);

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        return false;

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Balance -
        $this->SetBalance($this->http->FindSingleNode('//li[contains(@id, "balance")]'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//li[contains(@id, "name")]')));
        // Number
        $this->SetProperty('Number', $this->http->FindSingleNode('//li[contains(@id, "number")]'));

        // Expiration Date
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $exp = $this->http->FindSingleNode("//p[contains(text(), 'Expiration Date')]", null, true, "/expiring on ([^<]+)/ims");
        $expiringBalance = $this->http->FindSingleNode("//p[contains(., 'CashPoints expiring on')]", null, true, "/([\d\.\,]+) CashPoints? expiring/ims");
        // Expiring Balance
        $this->SetProperty("ExpiringBalance", $expiringBalance);

        if ($expiringBalance > 0 && strtotime($exp)) {
            $this->SetExpirationDate(strtotime($exp));
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->GetURL('https://www.lner.co.uk/');
        $itineraries = $this->http->XPath->query("//its");
        $this->logger->debug("Total {} itineraries were found");

        foreach ($itineraries as $itinerary) {
            $this->http->GetURL($itinerary->nodeValue);
            $it = $this->parseItinerary();
            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($it, true), ['pre' => true]);
            $result[] = $it;
        }

        return $result;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Description" => "Description",
            "Bonus"       => "Bonus",
            "Points"      => "Miles",
        ];
    }

    public function useFirefoxCommon($selenium)
    {
        $versions = [
            // SeleniumFinderRequest::FIREFOX_84,
            SeleniumFinderRequest::FIREFOX_100,
        ];
        $selenium->useFirefox($versions[array_rand($versions)]);
        $request = FingerprintRequest::firefox();
        $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if ($fingerprint !== null) {
            $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $selenium->http->setUserAgent($fingerprint->getUseragent());
        }

        return $selenium;
    }

    public function useChromeCommon($selenium)
    {
        $versions = [
            // SeleniumFinderRequest::CHROME_84,
            // SeleniumFinderRequest::CHROME_94,
            SeleniumFinderRequest::CHROME_95,
            SeleniumFinderRequest::CHROME_99,
            SeleniumFinderRequest::CHROME_100,
        ];
        $selenium->useGoogleChrome($versions[array_rand($versions)]);
        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if ($fingerprint !== null) {
            $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $selenium->http->setUserAgent($fingerprint->getUseragent());
        }

        return $selenium;
    }

    public function getSeleniumEngine($selenium)
    {
        $this->logger->debug('Using random selenium engine');

        // Other engines may be added later
        $engines = [
            'firefoxCommon',
            'chromeCommon',
        ];
        $engine = $engines[array_rand($engines)];

        switch ($engine) {
            case 'firefoxCommon':
                return $this->useFirefoxCommon($selenium);

            case 'chromeCommon':
                return $this->useChromeCommon($selenium);
        }
    }

    protected function parseCaptcha()
    {
        // $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/recaptchaSiteKey\":\s*\"([^\"]+)/");

        if (!$key) {
            return false;
        }

        // $this->recognizer = $this->getCaptchaRecognizer();

        // // $parameters += [
        // //     "invisible" => 1,
        // //     "version"   => "enterprise",
        // //     "action"    => "login",
        // //     "min_score" => 0.3,
        // // ];

        // $this->recognizer->RecognizeTimeout = 120;
        // $parameters = [
        //     "pageurl" => $this->http->currentUrl(),
        //     "proxy" => $this->http->GetProxy(),
        //     "version" => "enterprise",
        //     //            "version"   => "v3",
        //     "action" => "SUBMIT_FORM", // TODO: ?
        //     "min_score" => 0.9,
        //     "invisible" => 1,
        // ];

        // return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }
        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.3,
            "pageAction"   => "SUBMIT_FORM",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//button[contains(@data-href, "/sign-out/")]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseItinerary()
    {
        $result = [];
        $bookNumber = '';
        $this->logger->info("Parse itinerary #{$bookNumber}", ['Header' => 3]);

        return $result;
    }

    private function seleniumInit()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        $this->logger->notice("Running Selenium...");
        $selenium->UseSelenium();

        $selenium = $this->getSeleniumEngine($selenium);
        $selenium->http->saveScreenshots = true;

        $selenium->http->start();
        $selenium->Start();
        $this->seleniumInstance = $selenium;
    }

    private function mouseInit()
    {
        $mover = new \MouseMover($this->seleniumInstance->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(300, 700);
        $mover->steps = rand(20, 40);
        $mover->enableCursor();

        return $mover;
    }
}
