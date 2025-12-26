<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerPeetnik extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public const XPATH_NAME_AND_EMAIL = '//text()[contains(.,"firstname") and contains(.,"lastname") and contains(.,"email")]';

    private const WAIT_TIMEOUT = 10;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $customerToken;
    private $shopToken;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'peetnikCard')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->setProxyDOP(Settings::DATACENTERS_NORTH_AMERICA);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.peets.com/account", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();

            $selenium->setProxyGoProxies();

            $selenium->useChromePuppeteer();
            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['linux']];
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.peets.com/account#/rewards");

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="Email"]'), self::WAIT_TIMEOUT);
            $password = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="Password"]'), 0);
            $submit = $selenium->waitForElement(WebDriverBy::xpath('//input[@type="submit" and @value="Sign In"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$password || !$submit) {
                if ($this->http->FindSingleNode('//h1[contains(text(), "Please verify you are a human")]')) {
                    $retry = true;
                }

                return $this->checkErrors();
            }

            $login->sendKeys($this->AccountFields['Login']);
            $password->sendKeys($this->AccountFields['Pass']);
            $submit->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //span[contains(@id, "-error")]
                | //div[@id="acHeader"]//a[contains(@href, "logout")]
                | //div[@class="text-danger"]
                | //*[@id="header"]//a[@href="https://account.peets.com/login"]
                | //*[@id="header"]//a[@href="/account"]
            '), self::WAIT_TIMEOUT);
            $this->savePageToLogs($selenium);
        } finally {
            if ($retry) {
                $selenium->markProxyAsInvalid();
            }

            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(2, 0);
            }
        }

        return true;
    }

    public function Login()
    {
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Something went wrong.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "text-danger")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Unrecognized username and password combination')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindPreg("/^Unable to access Peet\'s identity service, please contact Customer Service.$/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $firstName = $this->http->FindSingleNode(self::XPATH_NAME_AND_EMAIL, null, true, '/"firstname":\s?"(.+?)"/') ?? '';
        $lastName = $this->http->FindSingleNode(self::XPATH_NAME_AND_EMAIL, null, true, '/"lastname":\s?"(.+?)"/') ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
        // Number
        /*
           $this->SetProperty('Number', $this->http->FindSingleNode("(//text()[contains(.,\"authenticate_customer\")])[1]", null, true, "/\"customer_id\":\"(.+?)\"/"));
        */

        $headers = [
            "Authorization" => "Bearer " . $this->customerToken,
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://apim.peets.com/shopify/v1/storedvalue/loyalty', $headers, 20);
        $response = $this->http->JsonLog();
        $earned = $response->earned ?? null;
        $goal = $response->goal ?? null;
        // Rewards Points
        $this->SetBalance($earned);

        // AccountID: 5830031, account with empty rewards section
        // AccountID: 6456053, account with empty rewards section
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
            && $this->http->FindPreg("/^(?:\{\"earned\":null,\"goal\":null\}|\{\})$/")
        ) {
            $this->SetBalanceNA();
        }

        // IsLoggedIn issue
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->Response['code'] == 401
        ) {
            throw new CheckRetryNeededException(2, 0);
        }

        if (isset($goal)) {
            // Until your next reward
            $this->SetProperty('PointsUntilNextReward', $goal - $earned);
        }
        $headers = [
            "Content-Type"               => "application/x-www-form-urlencoded; charset=UTF-8",
            "Referer"                    => "https://www.peets.com/account",
            "x-okta-user-agent-extended" => "okta-signin-widget-3.1.3",
            "Authorization"              => "Bearer " . $this->customerToken,
            "x-peets-source"             => $this->shopToken,
        ];
        $this->http->GetURL('https://apim.peets.com/shopify/v1/storedvalue', $headers, 20);
        $cardsResponse = $this->http->JsonLog();
        $this->logger->info('My Cards', ['Header' => 3]);
        $cards = $cardsResponse ?? [];

        foreach ($cards as $card) {
            $number = $card->number ?? null;
            $balance = $card->balance ?? null;

            if (!isset($number, $balance)) {
                continue;
            }
            $number = substr($number, -4, 4);

            if (!$number) {
                $this->sendNotification("wrong data card");

                continue;
            }
            $this->AddSubAccount([
                "Code"        => "peetnikCard" . $number,
                "DisplayName" => "Peet's card #" . $number,
                "Balance"     => $balance,
            ]);
        }
    }

    public function decodingJSEscapeSequences($string)
    {
        $out = preg_replace_callback(
            "(\\\\x([0-9a-f]{2}))i",
            function ($a) {
                return chr(hexdec($a[1]));
            },
            $string
        );

        return $out;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        /*
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://account.peets.com/multipassredirect?checkout=False", [], 20);
        $this->http->RetryCount = 2;
        */
        $customerToken = $this->http->FindPreg("/SDG\.Data\.customerToken = '(.+?)';/");
        $shopToken = $this->http->FindPreg("/SDG\.Data\.shopToken = '(.+?)';/");
        $email = $this->http->FindSingleNode(self::XPATH_NAME_AND_EMAIL, null, true, '/"email":\s?"(.+?)"/');

        if (
            $email
            && strtolower($email) === strtolower($this->AccountFields['Login'])
            && !empty($customerToken)
            && !empty($shopToken)
        ) {
            $this->customerToken = $customerToken;
            $this->shopToken = $shopToken;

            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        $errorCode = $response->errorCode ?? null;
        $errorSummary = $response->errorSummary ?? null;

        if ($errorCode || $errorSummary) {
            $this->logger->error("errorCode:" . $errorCode . "| errorSummary: " . $errorSummary);

            if (
                $errorSummary == "Authentication failed"
                && $errorCode == "E0000004"
            ) {
                throw new CheckException("Sign in failed!", ACCOUNT_INVALID_PASSWORD);
            }
        }

        return false;
    }
}
