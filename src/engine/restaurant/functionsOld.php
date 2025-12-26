<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerRestaurant extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PROFILE_PAGE = "https://www.restaurant.com/account/mycertificates";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $certificates = 0;
    private $attemptScript = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->setProxyBrightData(); // "The email address and/or password you entered is incorrect. Please try again." issue
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.restaurant.com/account/mycertificates';
        $arg['SuccessURL'] = 'https://www.restaurant.com/account/mycertificates';

        return $arg;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PROFILE_PAGE, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PROFILE_PAGE);
        $this->http->RetryCount = 2;
        $this->script();
        $this->script();
        $this->script();

        if (!$this->http->ParseForm("signInForm")) {
            return $this->checkErrors();
        }

        $this->http->FormURL = 'https://www.restaurant.com/Authenticate/SignIn';
        $this->http->SetInputValue('RedirectUrl', 'https://www.restaurant.com/Authenticate/signin?redirecturl=https:%2F%2Fwww.restaurant.com%2Faccount%2Fmycertificates');
        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $captcha = $this->parseReCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('ReCaptchaResponse', $captcha);
        }

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        $headers = [
            'Accept'          => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Encoding' => 'gzip, deflate, br',
            'x-requested-wit' => 'XMLHttpRequest',
        ];

        if (!$this->http->PostForm($headers, 180)) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if (isset($response->IsCallSuccess, $response->RedirectUrl) && $response->IsCallSuccess) {
            // redirect url
            $this->http->GetURL($response->RedirectUrl);
            // successful access
            if ($this->loginSuccessful()) {
                $this->captchaReporting($this->recognizer);

                return true;
            }
        } elseif (isset($response->IsCallSuccess, $response->Errors[0]) && $response->IsCallSuccess === false) {
            $message = $response->Errors[0];
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "The email address and/or password you entered is incorrect. Please try again.")) {
                $this->captchaReporting($this->recognizer);
                // refs №22622
                throw new CheckRetryNeededException(2, 0, $message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Oops, something went wrong. Please re-enter your information and try again.') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, "Verification failed. Please pass reCAPTCHA to verify identity.")) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->DebugInfo = $message;

            return false;
        } else {
            $this->logger->debug(var_export($response, true), ['pre' => true]);
        }
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        // AccountID: 1171779, 2546229, 1243083
        if ($this->http->currentUrl() == 'https://www.restaurant.com/Error/GeneralError?aspxerrorpath=/Authenticate/signin') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // site bug fix
        if ($this->http->currentUrl() == 'http://www.restaurant.com/Error/PageNotFound404') {
            $this->http->GetURL(self::REWARDS_PROFILE_PAGE);
        }

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(@class, 'desktopNav')]//div[contains(@class, \"headerMemberName\")]/span[contains(@class, 'first')]", null, true, "/Welcome,\s*([^!]+)/")));
        // Page ... of ...
        $this->logger->debug($this->http->FindSingleNode("//span[@class = 'currentPage']"));

        if ($noCertificates = $this->http->FindPreg("/(There are no Restaurant Certificate orders for this account\.|There are no Restaurant Certificates for this account\.)/ims")) {
            $this->logger->notice($noCertificates);
        }
        $subAccounts = $this->parseSubAccounts();
        $this->logger->notice("Certificates: " . $this->certificates);
        $page = 2;
        // Get all pages with certificates
        while ($link = $this->http->FindSingleNode('//a[@aria-label="Next"]/@href')) {
            $this->logger->debug("Page: " . $page);
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
            $subAccounts = array_merge($subAccounts, $this->parseSubAccounts());
            $this->logger->notice("Certificates: " . $this->certificates);
            $page++;
        }
        // Number of certificates
        $this->SetProperty("NumberOfCertificates", $this->certificates);

        // refs #6650
        if ((!empty($this->Properties['Name'])
            && $this->http->currentUrl() != 'http://www.restaurant.com/Error/PageNotFound404')
            || $noCertificates) {
            $this->SetBalanceNA();
        }

        // Get all pages with Gift Cards
        $this->http->GetURL("https://www.restaurant.com/account/mygiftcards");
        $subAccounts = array_merge($subAccounts, $this->parseGiftCard());
        $page = 2;
        // Get all pages with Gift Cards
        while ($link = $this->http->FindSingleNode('//a[@aria-label="Next"]/@href')) {
            $this->logger->debug("Page: " . $page);
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
            $subAccounts = array_merge($subAccounts, $this->parseGiftCard());
            $page++;
        }

        // set subAccounts
        if (!empty($subAccounts)) {
            // Set Sub Accounts
            $this->SetProperty("CombineSubAccounts", false);
            $this->logger->debug("Total subAccounts: " . count($subAccounts));
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                $this->SetBalanceNA();
            }
        } elseif ($this->http->FindSingleNode('//div[@class = "desktop"]/a[normalize-space(text()) = "Active"]')) {// AccountID: 4316978
            $this->SetBalanceNA();
        }
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//button[@id = "signIn"]//@data-sitekey');

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // provider error
        if (
            $this->http->FindPreg("/Server Error in \'\/\' Application\./")
            || $this->http->FindSingleNode('
                    //p[contains(text(), "The following error was encountered while trying to retrieve the URL:")]
                    | //h1[contains(text(), "504 Gateway Time-out")]
                    | //h1[contains(text(), "502 Bad Gateway")]
                    | //h2[contains(text(), "The requested URL could not be retrieved")]
                ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // provider bug (AccountID: 3090360, 3301618, 3511289, 3705304)
        if ($this->http->Response['code'] == 0 && in_array($this->AccountFields['Login'], ['mccuistion@aol.com', 'arwaller1@gmail.com', 'redsocksrule93@yahoo.com', 's.rohan86@gmail.com', 'Jlbrown2012@gmail.com', 'jordan.nadler@aol.com', 'sinhanurag@hotmail.com', 'ycetindil@hotmail.com', 'tayler3@gmail.com', 'd.schagrin@gmail.com', 'christinamcasto@gmail.com', 'chadmd23@gmail.com', 'eaatterberry@gmail.com'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // AccountID: 5346676
        if ($this->http->Response['code'] == 403
            /*
            in_array($this->AccountFields['Login'], [
                'ahlaughlin@gmail.com',
                'habeasnat@yahoo.com',
                'kathiranney@gmail.com',
                'atherfz@gmail.com',
                'stevendbach@gmail.com', // 5373364
                'ryan.braley+deals@gmail.com', // 4149922
                'goyanks444@gmail.com', // 2410513
                ])
            */
            && $this->http->FindSingleNode('//center[normalize-space() = "Microsoft-Azure-Application-Gateway/v2"]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'We are currently performing scheduled maintenance to improve your experience.') ]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 6221215
        if ($this->http->Response['code'] == 500 && $this->AccountFields['Login'] == 'markdavidhansen@gmail.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // maintenance
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.restaurant.com/");
        $this->http->RetryCount = 2;

        if (
            $message = $this->http->FindSingleNode('
                //h1[contains(text(), "We are currently performing scheduled maintenance to Restaurant.com")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $message = $this->http->FindPreg('/The requested service is temporarily unavailable. It is either overloaded or under maintenance. Please try later./')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'Logout')]")) {
            return true;
        }

        return false;
    }

    private function script()
    {
        $script = $this->http->FindPreg('#(function leastFactor\(n\) {.*)//-->#us');

        if ($script) {
            ++$this->attemptScript;

            if ($this->attemptScript == 1) {
                $this->setProxyBrightData();
            } else {
                $this->setProxyBrightData(true);
            }
            $script = str_replace('document.location.reload(true);', '', $script);
            $script = str_replace('document.cookie=', 'return ', $script);
            $script .= "\nsendResponseToPho(go())";
            $this->logger->debug($script, ['pre' => true]);
            $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
            $cookies = $jsExecutor->executeString($script);
            $this->logger->debug("Cookies: $cookies");
            $cookies = explode(';', $cookies);
            $cookie = explode('=', $cookies[0]);
            $this->http->setCookie($cookie[0], $cookie[1]);
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.restaurant.com/account/mycertificates");
            $this->http->RetryCount = 2;
        }
    }

    private function parseSubAccounts()
    {
        $this->logger->info('Certificates', ['Header' => 3]);
        $subAccounts = [];
        $certificates = $this->http->XPath->query("//div[contains(@class, 'certificate')]");
        $this->certificates += $certificates->length;

        for ($i = 0; $i < $certificates->length; $i++) {
            $certificate = $certificates->item($i);
            $code = $this->http->FindSingleNode(".//div[strong[contains(text(), 'Certificate #:')]]", $certificate, true, '/:\s*([^<]+)/ims');
            $displayName = Html::cleanXMLValue($this->http->FindSingleNode('.//div[contains(@class, "cert-restname")]/div/h4', $certificate));
            $location = Html::cleanXMLValue($this->http->FindSingleNode('.//div[@class = "cert-address"]/div[1]', $certificate));
            $balance = $this->http->FindSingleNode("./div/div[@id = 'value-box']/div[@id = 'cert-value']", $certificate);

            if (isset($balance, $displayName, $location)) {
                $subAccounts[] = [
                    'Code'              => 'restaurantCertificate' . $code,
                    'DisplayName'       => $displayName . " ($location)",
                    'Balance'           => $balance,
                    'CertificateNumber' => $code,
                    'PurchaseDate'      => $this->http->FindSingleNode(".//div[strong[contains(text(), 'Purchased:')]]", $certificate, true, "/:\s*([^<]+)/ims"),
                ];
            }
        }

        return $subAccounts;
    }

    private function parseGiftCard()
    {
        $this->logger->info('Gift Cards', ['Header' => 3]);
        $subAccounts = [];
        $cards = $this->http->XPath->query("//div[contains(@class, 'giftCard')]");
        $this->logger->debug("Total {$cards->length} gift cards were found");

        for ($i = 0; $i < $cards->length; $i++) {
            $card = $cards->item($i);
            $code = $this->http->FindSingleNode(".//div[@class = 'giftNum']", $card, true, '/:\s*([^<]+)/ims');
            $balance = $this->http->FindSingleNode(".//div[strong[contains(text(), 'Credit Remaining')]]", $card, true, "/:\s*([^<]+)/ims");

            if (isset($balance, $code)) {
                $subAccounts[] = [
                    'Code'        => 'restaurantGiftCard' . $code,
                    'DisplayName' => "Gift Card # {$code}",
                    'Balance'     => $balance,
                ];
            }
        }

        return $subAccounts;
    }
}
