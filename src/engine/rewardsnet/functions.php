<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerRewardsnet extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public const SQ_XPATH = "//input[contains(@id, 'QuestionKey')]/preceding-sibling::legend[contains(text(), '?')]";

    public $repeat = false;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $newSite = [
        'https://truebluedining.com/', // JetBlue
        'https://skymilesdining.com/', // Delta
        'https://www.skymilesdining.com/', // Delta
        'https://www.aadvantagedining.com/', // AA
        'https://mpdining.rewardsnetwork.com/', 'https://dining.mileageplus.com/', // Mileage Plus
        'https://www.rapidrewardsdining.com/', // Southwest
        'https://www.freespiritdining.com/', // Spirit
        'https://mileageplan.rewardsnetwork.com/', // Alaska Airlines Mileage Plan
        'https://www.hhonorsdining.com/', 'https://www.hiltonhonorsdining.com/', // Honors
        'https://priorityclub.rewardsnetwork.com/', 'https://ihgrewardsclubdining.rewardsnetwork.com/', 'https://ihgrewardsdineandearn.rewardsnetwork.com/', // IHG Rewards Club
        'https://www.idine.com/', 'https://neighborhoodnoshrewards.com/', // Neighborhood Nosh (old iDine)
    ];

    public static function FormatBalance($fields, $properties)
    {
        $options = self::getNameOptions();

        if (!empty($fields["Login2"]) && in_array($options[$fields["Login2"]], ['iDine', "Orbitz Rewards"])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->disableOriginHeader();
        // MileagePlus
        if (
            in_array($this->AccountFields['Login2'], ['https://mpdining.rewardsnetwork.com/', 'https://dining.mileageplus.com/'])
        ) {
            $this->http->setDefaultHeader("User-Agent", HttpBrowser::PROXY_USER_AGENT);
            /*
             * United Airlines forces AwardWallet to stop accessing united.com
             */
            /*if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
                $this->http->SetProxy($this->proxyDOP());
            }
            else
                $this->http->setDefaultHeader("User-Agent", HttpBrowser::PROXY_USER_AGENT);
            */
            $this->http->SetProxy($this->proxyReCaptchaIt7());
            $this->http->setDefaultHeader("Connection", "keep-alive");
        } else {
            $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
        }
    }

    public function IsLoggedIn()
    {
        // HTTPS
        $this->AccountFields['Login2'] = $this->rewriteDomain($this->AccountFields['Login2']);

        // refs #18462
        if (in_array($this->AccountFields['Login2'], ['https://eataroundtown.marriott.com/'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        // JetBlue, Delta, AA, Southwest
        if (in_array($this->AccountFields['Login2'], $this->newSite)) {
//            $this->http->GetURL($this->AccountFields['Login2'] . "account/recent_activity", [], 20);
            $this->http->GetURL("{$this->AccountFields['Login2']}api/Member", [], 20);
        }
        // Other
        else {
            $this->http->GetURL($this->AccountFields['Login2'] . "myaccount/rewards.htm", [], 20);
        }
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            // JetBlue, Delta, AA, Southwest
            (
                in_array($this->AccountFields['Login2'], $this->newSite)
                && !stristr($this->http->currentUrl(), 'flashMessage=sessionTimeout')
                && !stristr($this->http->currentUrl(), '/FixCredentials?token=')
                && ($this->http->FindPreg("/,\"memberType\":/"))
                && !in_array($this->http->Response['code'], [400, 403, 500])
            )
            // Other
            || (
               !stristr($this->http->currentUrl(), 'com/login.htm')
               && $this->http->FindNodes("//a[contains(@href, 'logout')]/@href")
           )
        ) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        // This dining program has ended
        if (strstr($this->AccountFields['Login2'], 'www.rewardzonedining.com')) {
            throw new CheckException("This dining program has ended.", ACCOUNT_PROVIDER_ERROR);
        }
        // It seems that US Airways Dividend Miles dining program no longer exists.
        if (strstr($this->AccountFields['Login2'], 'usairways.rewardsnetwork.com')) {
            throw new CheckException('It seems that US Airways Dividend Miles dining program no longer exists.', ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        //		if (!key_exists($this->AccountFields['Login2'], TAccountCheckerRewardsnet::GetLogin2Options())) {
        //			$this->AccountFields['Login2'] = current(array_flip(TAccountCheckerRewardsnet::GetLogin2Options()));
        //		}
        //# HTTPS
        $this->AccountFields['Login2'] = $this->rewriteDomain($this->AccountFields['Login2']);

        // MileagePlus
        if (
            in_array($this->AccountFields['Login2'], ['https://mpdining.rewardsnetwork.com/', 'https://dining.mileageplus.com/'])
        ) {
            return call_user_func([$this, "LoadLoginFormOfMileagePlus"]);
        }
        // AA
        elseif (
            (in_array($this->AccountFields['Login2'], ['https://www.aadvantagedining.com/']))
        ) {
            return call_user_func([$this, "LoadLoginFormOfAA"]);
        }
        // JetBlue, Delta, Southwest, Southwest
        elseif (
            (in_array($this->AccountFields['Login2'], $this->newSite))
        ) {
            return call_user_func([$this, "LoadLoginFormOfJetBlue"]);
        }
        // Marriott
        elseif (
            (in_array($this->AccountFields['Login2'], ["https://eataroundtown.marriott.com/"]))
        ) {
            return call_user_func([$this, "LoadLoginFormOfMarriott"]);
        }

        $this->http->GetURL($this->AccountFields['Login2'] . 'login.htm');

        // fixed stupid redirects
        if ($this->AccountFields['Login2'] != 'https://www.hiltonhonorsdining.com/') {
            $this->http->setMaxRedirects(0);
        }

        if (!$this->http->ParseForm("loginform")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('loginId', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember', 'on');
        $this->http->SetInputValue('x', '34');
        $this->http->SetInputValue('y', '2');
        $captcha = $this->parseReCaptcha();

        if ($captcha) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        return true;
    }

    public function LoadLoginFormOfMileagePlus()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 1;

        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $this->http->setMaxRedirects(10);
//        $this->http->GetURL("https://dining.mileageplus.com/login.htm");
        $this->http->GetURL("https://dining.mileageplus.com/Login");
//        $this->http->GetURL("https://www.united.com/web/en-US/apps/sso/Login.aspx?return_to=crew&redirect=sec&D=iZ4Wbsqo3KnQ", [], 30);
        $this->http->RetryCount = 2;

        if (
            // Network error 28
            strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            || strstr($this->http->Error, 'Network error 28 - Connection timed out after')
            || $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "Error 403 Forbidden")]')
            || $this->http->FindSingleNode('//h2[contains(text(), "Why have I been blocked?")]')
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(4, 10);
        }

        $this->http->setMaxRedirects(7);

//        if (!$this->http->FindSingleNode('//div[@id = "root"]/@id')) {
        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        $partnerQueryValues = $this->http->FindPreg("/\&(D=[^&]+)/", false, $this->http->currentUrl());

        if (!$partnerQueryValues) {
            return $this->checkErrors();
        }
        $this->State['partnerQueryValues'] = $partnerQueryValues;

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        $cookiesFromSelenium = true;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            /*
            $selenium->useFirefox();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
            */
            $selenium->useChromePuppeteer();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            /*
            $selenium->useChromePuppeteer();
            */
            /*
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            */

            $selenium->seleniumOptions->recordRequests = true;

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

//            $selenium->http->GetURL($this->http->currentUrl());
            $selenium->http->GetURL("https://dining.mileageplus.com/Login");
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "MPNumber" or @id = "MPIDEmailField"]'), 0);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "atm-c-btn--primary") and contains(., "Sign in") or contains(., "Continue")]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput && !$button) {
                $this->logger->notice("something went wrong");

                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);

            if (!$passwordInput) {
                $this->logger->notice("password not found");
                $button->click();

                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 5);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "atm-c-btn--primary") and contains(., "Sign in")]'), 0);
            }

            if (!$passwordInput) {
                $this->savePageToLogs($selenium);
                $this->logger->error("password not found");

                if ($this->http->FindSingleNode('//p[contains(text(), "Sorry, something went wrong. Please try again.")]')) {
                    $this->DebugInfo = "selenium has been blocked";
                }

                return false;
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);

            if ($cookiesFromSelenium) {
                $button->click();
                $button->click();

                $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Account Center")] | //span[contains(text(), "Account Overview")] | //div[@id = "error-message"] | //div[contains(@class, "alert__description")]'), 15);
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));

                if (preg_match('#(?:/api/token/anonymous|api/auth/anonymous-token)#', $xhr->request->getUri())) {
                    $this->logger->debug(var_export($xhr->response->getBody(), true), ['pre' => true]);
//                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $this->responseData = json_encode($xhr->response->getBody());
                }

                if (preg_match('#xapi/auth/signin#', $xhr->request->getUri())) {
                    $this->logger->notice(var_export($xhr->response->getBody(), true), ['pre' => true]);
//                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $this->http->SetBody(json_encode($xhr->response->getBody()));
                }
            }

            if ($cookiesFromSelenium) {
                $this->DebugInfo = 1000;
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    if ($cookie['name'] === 'bm_sz' || $cookie['name'] === '_abck') {
                        $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                        $this->http->setCookie($cookie['name'], $cookie['value'], '.rewardsnetwork.com', '/', $cookie['expiry'] ?? null);
                    }
                }
            }

            $seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current selenium URL]: {$seleniumURL}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(4, 10);
            }
        }
        /*
        $headers = [
            "Accept" => "application/json",
        ];

        $this->http->GetURL("https://www.united.com/api/token/anonymous", $headers);
        */

        $this->State['partnerQueryValues'] = $partnerQueryValues;

        if ($cookiesFromSelenium == false) {
            $this->sendSensorDataUnited();
        }

        if (empty($this->responseData)) {
            return false;
        }

        $response = $this->http->JsonLog($this->responseData);

        if (!isset($response->data->token->hash)) {
            return $this->checkErrors();
        }

        $this->State['authToken'] = "bearer {$response->data->token->hash}";
        $data = [
            "userName"  => $this->AccountFields['Login'],
            "password"  => $this->AccountFields['Pass'],
            "toPersist" => true,
        ];
        $headers = [
            "Accept"              => "application/json",
            "Content-Type"        => "application/json",
            "X-Authorization-api" => $this->State['authToken'],
        ];
        $this->http->setMaxRedirects(10);

        if ($this->attempt == 0) {
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.united.com/xapi/auth/signin", json_encode($data), $headers);
            $this->http->RetryCount = 2;

            if (strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false) {
                $this->DebugInfo = "sensor_data failed, key: {$this->DebugInfo}";

                return false;

                throw new CheckRetryNeededException(2, 0);
            }
        }

        return true;
    }

    public function sendSensorDataUnited()
    {
        return false;
        $this->logger->notice(__METHOD__);
        /*
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><link rel#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        */

        // cookies from firefox
        $abck = [
            // 0
            "0C6FC5187E31A44B9E16EDC779805666~0~YAAQzmncF3mLrNWUAQAAW4KV9g1dHOpRl5ylrZ2IE3zd81L4igJ1R8KB3Sim1XLRwHqIiZZOYdW55FkdkjR0GHPSb2ps2oDNs66fS/L7VW0xltWuuKUtavs6ynWYulzAOV6+zCC32z0MlLjs0YzwwtwBSz505FZA4wKzPeEa2mNQ8vIJAYq9YJuYv2znIQPf4gJSCx9mYWSndPO5DfL4GGHj1yKMiDPlOAcsqQz6q8Uo5JWlzb0dHDIGmUrFa6w9QOUx2+7mhGav0wPWxbvVCQNaOK0vVhsnvuiKT5up4YA8Ikcqg4O1Niiyws1bR0wP1Cx49ykZwVTnkyADGQOzKGeKwgLVHGbY89jx3Ed8JJN1GXjXFWRW8ua9+dpkeqIMYPBuYH0lQ5d0eGztiXFbahbE0dCoDJ+g/OtZ7exX0dqahtMlnpq4oruE9kaSEtQkE5ykMf5VWuPq8DaK2TGBU+GtcdhMxb98+CMYnKD8vTDpYbjp7uVqdUyGDQ==~-1~-1~1739306965",
            // 1
            "0C6FC5187E31A44B9E16EDC779805666~0~YAAQzmncFwCHsNWUAQAAmoOb9g2a/9loysJEXtD6X0w2v5u3mFpWd4Y5EShI1S6JHRftahv5Cer6XSftrEnDixQMaQV0qdR/C9EpE3majwR3JdLT84vrEjbn597ac0iWIE0kLSE5M6zlMaHtgAFjNODrQGMaFE28ZJuwfNPs6FseLlBlFVJK+rf32UXcnmZZjIUOHHPfmAo68sNZAL1g/qgwm1Iel3EorsKSdwZ1UYjoc1ZF1hxPwpETdEztDr9LOO//YVE+zX7HimwVF4YEgCWSMPg5BVUkAGTfNp567fGnRDn1mVyv13w/3i5WMK2yhb86Oc6yMijWx98iSzzboQGKNKI1DR421crfzvEUPTnc3TNar4varfz8HRSA0xnpFkeoMJknIAnWHiFy5JLaIG4oVqrpGZrv94BRi9wDlh82eWKbXamuUBJDQf1Y+qgtTiiZ+1l4gdzrHGExVXJi/JwScMHzVZTqEbOnMvcY/1i6Bvcy4RlkMyvADg==~-1~-1~1739306965",
            // 2
            "AC851F06430B236D938734E4926FFF65~0~YAAQxWncF7HJEuKUAQAAB4Sg9g09N+YMapn7J04NlgFcqSDi8SJhNqV39KFa6l5sAgw9G6bZalP+CUvjLgaq3G3OnNBUKY/13th8w/os1EqDl4BEIMcGqJmra+nGuc2ufCI6eHYARI29m+JiQtM7zZFq/89BsWsKzaHzKNf11KX86VfC0TuPCzacne0TKaZhRUqfZkLtWUdcM5ysjy+09IdBSN4MDjlHqzcAAk/owuVhVnBcj2x1C/vCZUIUPbDUcXJpVOmyM2AfRAXQBd/AZ0iTb7b6HU0LFAnOHzGntkTJvTiqnF4C3pILwfPPy2wcD9TbGCbX0Lwo3M6vu2mS97SYq8i/cp8rD2DuFymLpSf3KnZBx8hfI18IKZDQCr9u96ESS0f08Rx9VIDn8rMy5Wx1H2pzS041bKkTivyTY4n5LOjcwvqJ8dLIZlaytdLxMf2T5HIrTKEEzriF~-1~-1~1739308102",
            // 3
            "AC851F06430B236D938734E4926FFF65~0~YAAQzmncF3NLs9WUAQAA4P6g9g391AOadeGekWWEVtAA3qGDNbb/y2hcCLvxJq1OFQjNvPmOFULB9TTtgllDorpATEigtYQPgx5jk4wcsfEADourqVYV6QPa1LpF6BLwbV329PmvaCRCCiZIeNIEZSxolG09j9/HVMzrFaHQD1/zO4tEPZhi+YomYq2motj5eoF4o87p9Mprzwxp6zaYe/TLRyUoM63Dw8bNoBoN7gFUFPOCq/52LINRUnu4t6KbejSC4bQ2UB4JvAxOkTkM8YIqqzKz3Wo/DfxI9P4vRfusCC8kmqSG8fCpG6V7ilMD3xt1TE3Q77yJ2ydyFt1CDs+yzqvAKI0IfHBvI4UTG5PeYqrbSrJ3yUlaECQA+XL0hVA9Li03BpzDv9jm2dv+PWYGKQLcXqKsnhvPyX8hF5kgFhuC0169Y/s19niCK5dgXNphzDJtEuQvUAj2~-1~-1~1739308102",
            // 4
            "AC851F06430B236D938734E4926FFF65~0~YAAQzmncFxGPs9WUAQAADWSh9g0qo71oz6wThhPVS14Ui8zpCqlDlkb2+jym3XBVkJSXYdBN2cbhG/Ky94EagdpDapvJFt+3/OzTESyKfpLrNQy4dj7Vw52Z1wPWn/NS/FTHp0XA8E21cLoXhcVITbGmbto4PwVxZRRccyON0+hLrBNrvC8+dIfLgcVddR7fZuRF5ngegnFNipLtj+6+WPi2c/n5VMhLyf6BoQsq/Xb9tXVQYEuCVucLoYeJ78g1M+FuZAV+a3OYRYRQZUfNwF6vve/ORD0D6zQDSpxqSxmJlkAPshlC+H7Lp7oA+UQ2Hpn7Q4Kv+2xAkJWy+mZm7fS2CngczIvLRlyC1upBj0oilsJ5cuCz6Ycsbk+IpYRuZxmcLrnRXmZ6vOZZLK/ZY7TzktjwHpT1/+JELX5bGRwLHzNKXQbzTf+N+14ySE0TQeCAl1mvb7saczwW~-1~-1~1739308102",
            // 5
            "0C6FC5187E31A44B9E16EDC779805666~0~YAAQxWncFyxUD+KUAQAAku2V9g0Qi1PgNmqK6sc2A8ruT+/VgQbKAhClWkYyGsE5Q3Y14/gMi9WNkSBN26u7duikDT8XfrT413maHPDLxqwLRgTCoPMboNCmWOLBLOKNhRY3EyZgPuo5xa79/0s95YwiGaXtQjbsEIFAa1wjNxrpI8KZjjOmaIkL2aHLAcXbu6G5uZLcKRIoeTe1T6HFJYKVnT5Wu+N8b6kWumMibgJmCdJcxm6Dn+VzEPZ2bOmALyaaQdYI1H2pmBFVVzOr3rWFiKED+tj8g7uRBxxe5xHO6Sur+8I7OblYJ5cw6krvOuG1XAktE+0TaavcyUwIQrTUbmsZoB/sTKGhnmDTdl1I1msqD46Q5LKQpi5AMBHxuUjAfv87VjM0eTZfFo+LgTx/QKSymGplUQVPqjhrIFUDkdX2CMd4GcqSxwzCh8Nixp2ktT0lvftQ0OOQXMO1YEJ1eKIw3VRL7PaXg3QagJhachp38UNpKCmDqw==~-1~-1~1739306965",
        ];

        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";

        $this->http->setCookie('_abck', $abck[$key], ".united.com");

        return false;

        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            '2;3551302;3556661;9,0,0,1,1,0;$lVt:/1U{e?,mpTD3^VUIHt@4vr&>:L8O07`,+~Bw`85# kh?9T+*$4alrx!E>Q_kYK#`no7-_<9[5|}^W9SStH{sap}THbd|y|AJo/O+sY1+3#!f1kM[u](,#k`N$UOo}JB}O.lsH]mtT~+H#HwU:vZW=m 5bFp5.<ncy jmf[nih^ %q^)|iK8DRl7NJ)NJC2xx)Z csq9.HOl|e=:YxiM^l4e!Bw?pl+OCV }p3,kSnQH@?1~ClejpnKtV#IqKSn^Z%DF(7@]#.Pnr,{oIW;5<(Prb0g*n,OB>f??:0mp&Sw68UzEn@;Fhwf5p>|^9ittMJysh*?v}n T]kv~C%&RXdM#tS1^Rn@9<u[`[z#31Xt[ekhm}F{f1P>ifOQpVvDYgA#5&W#P&Qqb-56L+lw{S.raA(=je{.s{^?`Z5JY`:]Av7TP8x;SC[H)6vyQ@r8JbBF,MwI2zf]j3eq$4-D`:plVXMBX^A @akgz9[6&Qw h7p5Z;zCpO1%N`DXOgE4XrfRu;.A1o^H@rDg0/!=b|=+}0>CC/ x3oPPz(#MG! ]YB(7fT].O:-c-ED}Sa;:gR.3&$i>P34sgJLf=1847/o~/EFdf}j[&LF5CdM9613{#lgvE_f8fe;OQA:ef0w(H$;:t>2|/%#s+km10w)TdE4FT,SRcssuU<<Z3qpZdNgh0_PxMaDwV8f!0PjJluo}Qm^pL(2(8&;E_TQ(yHD)h%(QU1O~AT)vN7UydC=8Y[x~=,>u_$a+xR3Us[~Lg1=_D0SkVKQhrj5Jxeg, m-xy/Bt+u4s8nr&qy/dd[OHK5QW~4`*x@lk`vcp,iL5O#.^^jv>E0OX~5RvANbNX#XNFgSl1rP7W;OzwtRz(j,G|KdrygvpiDq$$TgI%%>+fTq37D{MJEkxWsSK%3d*-YASM$7~[P9&U@sDBV+ss{Ic>Y00;Pt >nWnf6rVC+qvN2g;iV<{+fo$g2#E-8v CqVv.s2|sxRGA{[5FpJ+=M!R6[K{Z4^n#q[s&qGJg jl)n@6hvtRWPG9txGeC|FQ>rS3&y*_k!:%NcugPHLWb[Nw4!ftv^CWa_+9K}*&og?nIjgw?GJ<bC[wRLx1AZ+M)F.~j{h`4w.2f_d!:dsZ@j*xheL SLP;T/H1I3x-#T}MRlQoAyP8S%e$V]DH-5d!pkBXHo(sNUwn0%L?*SB>sB[B*oE~ciR_s8>__9_4{K)wSxm9y1[BR[RJf8;&it}~s{yb:qbq@1D#p_*Ju.f<0w:;gF./WL_[)R1{SqX=Iu%A(QSpC{F{:oRa!`^b*/a>1i@3nTE>=l7u2%53mDR5F!H)}hR^k*Ah;LCV1a KXT2<?%dGt0*qz(Bbpnpuql,_j :G9ha*PB(F.H||rU?r&i&;KK!*dM(s cK,@BEti ZkE6:L.~~W?3BV;Xgy.FfSa<Wkp:Y3bZ4,K7F).3Z;E;T<Gd:.=F^S8u]f&[%qD7{d=0h{%7~JkQzC41t6{FWXwU1;Hn@6dZIB!u8pz,vZhp8V1cT2LpCM{Czj]~X9()yvVNnKe~usmluuIwfF Yp^.s^wO#a9*.-v[O1ey[ $@htRUSMW#E5AUHD za74 X)*{y]:Hzt4!>uXRyhD:xI^3J 2QZVUq`C0(ekHW=I#zB4YY4lln?w=k^`&P@:7<GueQQ8{6ai~Z{8H=z$wfA+V*|*L.zfpl$4FGV=fvIDO&d0nt`lcP6)S8&:jJ:-Coqu2DOCAjOf|9a-FK3BKG:rL$0L-]aJ(Q`&#u,>7xG[qf{m0mels{t_0~0J3Widw?`{DZoi!`DPfK %y#K@>KnF~_L5tTei%Kl]in6JO5z7RN$7u2ArXm|K&z=waXhr+E-?z6`t(s_yB7o#naFZe[-4wa,pF%CYGNvH1I(xl>*E#qRf_+7`z3;E^4P}ow1Ki:)O4D}BXeG&P6*=Ke)Sg83RHHlI/kB?*(d.~M%9Zb]<_ZdwuZr=[!<VOWoUh`2<&O8q^X 1`fnT|pu5B$B}Bo)JENzV^kZ_Czqd7pS|QY_=#,DPjOmLxNb236v`loD{u6$uzNASdNN.~1MYoT1i3o>bf YMRmG/cBK.4uY:kY?*n&:v#e5,s4S68bL:KYEweKeBQ6 /KTqqUmyNuqKF`H/Y  xv%VzbcmQKNF_|XwB1#?aIvV:.qr6aS~M7QtkbL5|DV;H:-CV^W~8~WC/)kl*n>_fS/SD!}z47CAL4*?A{m=/Q!ueo`W+@H^YbC(b?ZVpl,$@T~O7*fT)798E%;n}l5[u{2%Rv5F>uJqf&%.o0S waNax*dqVG RhlL#* ?ajtdGWNQ6p,/Vzf+3&:??>,dGNmE!.F=M{4 e08AxSb%b1o2C;OQlcc0j1O4}psJR!Fz-QPbQ@NeO2:,8 2itlo2Q_~iX,q9O0N-:Yg}ij!h6t{q8Grl~(O{V/RA.9Q|I ',
            // 1
            '2;3228985;4403253;10,0,0,1,2,0;X7~T&MkSkCI(9C7qZfK[64QQ5f1}%mRIt_AS][0g? Ae&+P3~dV3VhWsGnemNk-X.Kt?l*1tfn2ko/*&4||&IQ $Ys|>uda-`:,XkLv]5pCy@iBa.^]5jx`i<){%nDN=@BRm3qF+jSzk+2XXgEuczRv.8%Oe/%8k~-.&@%So:).F^yqv6[d%:YUNzk,Q?cx[.`Q*v;mrV5!h:q4=r+]i;Q2ZY@|?w6T7y2>{=Sz{8Z3(iFWmMDQ`(R3*S*:%Ue$mSc(g 9w1jmy@yfx;)q);y!)TN%lFo}=cWQX8%$[kR&YV]|0SO9^oO-oZM+r<65`w=i.u@`?;T`]S-#DYj_x9yZZ2*pc;&9mSn$Wk~|%0;F;F*7U|Mt0Tg*};ZLi1w`X]]8GP>UPIf!v5p{22 )vnXEH(Bv!fA)QTa.gaNng)1VYndJ,kW%J!q7UIpVap%YYBZS]a`{|BJ|)28+jAYp;sw]qUEHv- -y {rM}]#LL[,>[ba@-<M#rcApZv#$,u`><mMMFN7~AnE/qh dNnxjX<KOa)n;%4ta&43wp3DnNYBDr(:,l:H]$6`DLUTbA>#4=f!<Pa<:4N1Ay0F?(SnzVh0z)ps8]C$wbrr1z}S,]:f:kz@;vY{(os*Fd<7zq,$&Gvu)2JSL0*V}!o4H>gh0u7Dx+f}IUtxeKhss;[KQV#zP(Nv5.We5~I^xQ|F$ RDu1jFp7gUw~I>yU/RZdF}{%Ag<eCc>` /*`T[],WJ}(ZU1!Td98,i`q:a=bJuQu730A 8*[5P$u nGkk@QdN;SXN^?2EuZ-]g}aGbsbA&qrbf;Dkuw;M`}1f+,KIw_vgq:#hQW46mEWRON8 f!N.&Ub<`t@8=Nxh&60,Jt_<@ ;64>8]d&A@9_s_?zkXf;}!r./+T)}LlSdb~o$Y]!5I:k7yVS4>{iP?ZZda0os^s}42ILPD>^TIP<<xfqGN!!` ?qq!Q!!m+~HTk{(x&U/*Kg92qot~jqQW$Ybl?%t~-?P|94aG/uT`9d/BH?IJ(gstuioX(vjeCgV,8J<TjY*c%-k-yF8zGZi?C;uPfxkD@D{fM%i*O%sn0PoU[(5yxn6ej/QSN=*1]xh)@lKZ>qFXnHgB$fS>v<UrG)B0VetUvD,BGOEPa6ti/yrcl+qY!:A?IP)Z(p8qTMy._}dTVX=_YVD~c=p(9M$7e.7jv zr+3$UEz_CRSA&$wx%/yvM!>@ RxeLO:FG}HYcViwg;KL%4*QI9+M(#FoOA0y6*ep6.Xq;2jtziHn^TO6)(j[e|hajtk-X3vlb{/c< jG}dUntLPI_?VEf5X}(q:MWehIaDB~7!D8yPb80 wQ~,E+y33T*iNrCw~6@7 `+zbY4tm3[;9D%r|]FyI/3:5ZcImV#(R_V-*TiM(Z1FapD{A*({P$wr-vewH4`rgcYyaaG5J%wm4IlE;tr!n9p%OW@d+!Oa#ip&<vf~e:sPg?(XL>QvzQ2gY7)_4~jio1WBNJCFj(62i&y`.KI9ST<305dD^pNCo7|v!4&*Q25Go8Rz+>wqy`H(S.J~[0r&)]m26KLLgdjd}Uf9t+N;^x^4r*:oRdZt%JbN<b!Z7#3N,UcS[_qj%xoW~9^C~3tNg3J)8EUcS4KiNM>5r9PYwezU*OqLBM^|8v+}.Kz[HBdkmCSXZp5:aPhF4VZfR1)P<H`Gg|?Ta>oWuTlBh{mn5:Kt-HorV{ U%g4g9plB**X*v_z%FTFEhB~mi9ab[(:^;wJ2fAXyl.F :cv}YsGN2/OA@%IsX~hs;0n4%ZrNLQ!M[Gx/AwM2LU?fgT)}.^}pGyqU]TkExrP63[[{{kExY^7 liUYc3gN*vUS<#Fr%7yKnmA^!a6xwn=RWin-UT$#Gt!L8}H&D2?Y>]422~$8X{y$^N;%2sMoKF&-tvH$KTN)BV-YL^f>FW,nRB1QfISi=CKf_a:}<`HAWdUyd;<v]0l-O?9Lk`B<0>3>,3mT$J?>b#Y3(]XI1iP[+yBU|*:HaQQ2`XG-`1v-EvL/:WqMS-b<b(q;3W6iNl5ti%pJ8`ju:bA03e~g SG,12>s0E/BJOh}(A$.];C=vC4}ww}h~`7*N^w@(zs.0Oc$.N:D~MeoLypekZq`<fYVlt1rBME{yF}s!?:H f~3|@A?DAZT^A/dM,5jGQN*]Ih5R-1XbPq(Yerj|w;;R<B <^vgbe._rI`G[v(6,t|Z6$5|@9`s+2*`KTO8#>Ho6p*l.Vk.Tj(M?!5(Po):T|x`pl.,r:R',
        ];

        $secondSensorData = [
            '2;3551302;3556661;5,13,0,0,1,0;Ol%|q41au`B1EyJA!5^$LOxtfK|Sg5QCMf0X&^XGyc<5}zvCE?]-Z)daXiL+FD(dk/2X5r^7[qCCd)|>dUSNYgC{yalv;Jbc{4!Kd,J}Q3)L-;Z-vw>t!Q}L}Edp6Iw~/66tF^)O*Vgt}+yMNNka+c}Z#o@V|$hz5&8ufo-sSO3SUsm)t]rk3C!o}}AZ)eo)gcoYMyTmEvez).3F[d[ATKSTei9T!(Zzkj+y|/Pvpqf1Nu+FKG*}Ll_IPV.eLyUj?VvgY$^G2n18TrHnQoEs8W;7IwP3i:D;p0L>#Jt5)0t=1Wjmx<GEwI:~L^Y*?ngg2HT]_>vxn+;m# ~U5k&Mq`j}MlQtoN2hJlD;<]53bt#bC`~2e/CAy#Hf:Y:jfG:kbwH`m<aqkSW/UQpUSgDC|g3)]dlaN-.k_fARRm%9<XYo< 8Lh4CTCc|3[fO-8qt[+eAF2C(P[ywM$pQ;S <V:(FeElF^dMQ=_oV`*p7Ke4d))K{knF2@=QJ:K(Z!VFXwmO+#Ee/y;tG;uiF=pJrWZ%@=Ug&-,;F7/aQmY68[(!NG{&X?{`.mbe)0|q0$`OT$]C>_>)v:]e?H+7nkFIi11D_nqU}/GFjXKDe+`hb;gM7014#|E/WEg|J#~Qb0wzG=~nuLz/2tD:m@SgL[<MN?D&J-Nt-2X0]PUypUSGendi]dUjcx`U<o*<rZ#e)kPJ&2pxy7~+zP#&<.1u@fVZ!y5H)I]b<YKK~gTZnS?N|j=G4La(%.)6|i!@,x,4ZsR3Nll0_H4SnVNPRmn0JeigQ>c)x#-A_/hON BTcHx8a[1FjM`-<dk)a*5&IDQpYI@v|jF#(GNuqE1z]t#hOy(8z&Mo)1j*g8tO8I+o5B7nt}v5L{0k|Jew|oDR[_?lDwd>0]Hi3;Ao*.,fxT._|zW)$3Yw0+}8%`X2,cBi>Z[5Gs{:Y.^=Z;Y}z6(]kC5rR76GuRMmpNXg`o7H*@;z?zB~b,Hd3MUW]_GbUjaNITE|b%U(=b@$q4e?JcwVmmU02^cDb43-Gm.9T PYz>`7*v;j$Z0.HWHR _~v#f[C0}YdI(Iw;+ab2$ctcka}lz6P@e&Z6tglV]a`Y#@|ZM9-YIx|&GBg;_I9>0NE`N@&|YlzrW,hBG40yrE7h2&vBv*]UQw<cnk]&%Zpc6:@.4*5e [BdIH`5aXNes315AS`7mRw~Y)-kablflX:}VZ]C6!zd>oxA9RYW5i3BpB:(I>E6)}wfR`wK21d21`!v&>&4tno|gO_YhZvh#6{]vKkP9NZu#XlE_/%07e!z#JPDw|4=VT`@yI]U4SPqtJpZ/VSb[>A6R,kk|dKZ&*b;2{MicB}U=%I(:z,<i$QA`{e4/5ZJf?t=).(Hw)KIh,}T%.G_%(x++wF6ZuX|EI[YS!FLFk}cFpI`V}0TSA:)K=jtWqmF +D:c<iqm>W7cY%I!m{b_-_BC2[q;f@b9BdleC|3+c%qS8pA8#-_j3s_a[R7r1y:o+[^pI);Nw;5}fO{zv=tp)}Ufp8K5|Sy$pFKf>~U[&6J#(owVN/RoViV)wLFE%o@uP}+CVRfa^HAhyaH: 4lGY.(CeBzY9-T=S5=qqz,DXY0F~z(9;P-{78+R]Ip4miw5gr0!Fo`T<GyC.s1~L2|F;h6$C_VHk6qfA CA66`^v6-3TKXT_<Bj(jg={oIBzZSC<n2bs,$U{7CE07KxcE=vIN$}H3EOajc)B^W`1@nLG_zDqKgnGAEBS`gjkcs|htAu_MUV;?$S`il|l<LZj=2c&[sIvFH<Ezjop_7!%E%`kl{9Xt=UinyY;Kj]~&Q#XwqY;B&lw*uZle-Pw*a+AG~,yUYIU |6?Q`zjp(~-RAIc]/c+EW2lt$j^:I2Blv2E^e*+8{`&[M,E](N(|_) xA;,E!rRa`,<Wn6CN+4Y)ks+6m6w4#J*8NSf$Nh$ITny-Fw3_QBb8M?_AE]HrqEz8y`ZnY_uz +fw?g8=Ze-rrK>ubxob&_We;I=z(f(n#^slXnwi&)t2=`d#CvjG9pxZ{@t=#}$PnGdJtSU4<5qW!zILTtkWkE1Z6B0t`$p=UO,$8ymbezUT`q;xkKJ./RBxk3@/n.;_|l9*Rr<z48+iKYEuiK[TS;]Y$9WdNp{T~bM>iD,]s xg_*a]<53KMXI#6r=J)I2?ob>)ip<kTGC3^|hAL;N@^;K3w>[_^|,#`L+{a^3u/TTr-Qv{cZr-D9E3$KyxiAxP)RadtM5H<Zga7li?Qirqg$9}xG(,.N(jUQE@;;wp1q$yL7#$|#POvp+v#`$1@mF3s{o~mvYH8@el=F?2X~3ThUWj5jifR~YD24rNVA>sgam{g`MQ|=A]|uwm!(<}|:kb<lYO|d|<w9lXAA6?*2aj]?VQ3lB+#cC>aB(FByC)EqG>E69NT7%)@Pe 0k|Gsq)?-Ai%1g 7|l=c#qwlrdf$DhVYEo$Zn:-o+:VM.UD$oX~=QYHE@5#UJdobFuE(I8ljc4(]lqY4>cYTxNK+J^#>s< uQuHUU-|S=4a;E|SMLf h6cClAXWYnHhYOL>od_sj1i1Xpu-@95{U|UHU<& I$N Hb6A{o)eFuBnP@**]ps;7+z3zs{(E8*-V(`-9S0(a0Ibv>tx;ntdhR34SPu)(]g?UP[24vLzH3;V[0kn}Y$j(VF K/*^ks;@n_y/fs3!x5QbrCwe#$A__F.ca0](izT#I3]%@23K/M@y~W09%OS[C/U$-71A/6/>wi)I@;fsR2+ljGcy_T;$|H%x_nXin+)4ci2dQS K|LD%B0&hEx_CICCv`l~LN[eiFR!fRTv-}[`mxjkFxAdGEsHA8H>mj7FN46mAW[,Z_@<.D%4toSz,dhHZ?T1!^Z^Rk}|5EW]cO8{{1[^H7N(9$Y6>fY8]>G%<7EZdz5D}NuN{e8w^m<!B]?K[S%1f@t0',
            // 1
            '2;3228985;4403253;6,13,0,1,1,0;Q6|}?XoG+=S)-|1 ;YB&S9WYq)@`O  F*hH^9oC>e=x&GTA_f,tWuw<En7LPX^CH.v3;C&4@]|O+UmCRr?S05Nt?^t)J]N80|ismge#a)uE$?^t]8]QMh <a/Z&/R8ckFJL-4tJ%[+YCX8k4fCL[pFHgFc+c+&9mEEXx@y{!hP? a8;K*^n`9[i3f!//PJSgv$1UP8tmOTbP L}8ic`mF<tFwt 5t}#;q2>!J9vni^:(%IGkM7Ec5W;dO#j ro+f8:W@j09wRdDA$dr7WJ76n7}vL#p;x)F1~N,C&4cCXhx2l|sUzBZsS*o<IWr[>,}!?O?#6GKjgD(4x,Jjfhy=tqZ!$h2yTpnWJ$T>z;+;4[<E2q.TZ~5T)bIrZLF0s/[i[7^Q>6|ha!}7srNw~ycocF<,>}wcX&QVb3dadmn{5QXseP(fl$N~qTLfWUS*&_D:M!X}dzxXO $.75r</G<85XocsluaX2t}.qTaP}$Qd$QdKe9 =H/ie^mZpz$(us>Wdo/DI`9Lp:_t]$_Ri aVYKWW?%<.-pd!4Ixu.D+G`?Dz0?&l?UB~)JLAtnG>E#w>bS8X_8J/NKMg(B=5XvVPZ`z/Vl55F$9JZiY~&Y&y4p>k9B;)Y+(l%)L`:S{q5+#Kk4n1=#HQiTxK/9/:YT1 ?=0,e(A_m+lKhn7@]Hbm0hH#K}v O01tUuFi83;k4y4x%JPo;2N[W-$8h5/MdwqF*E0f=iBS##3lR`]0SK#([P:&R|0W/RthCbFjIpPm@V`;{YJ!XB}4gm:WlJY^h>@]+Z?dIub*]s|dNeszAujrY`OGksp8~[)=j.|kNfc*cq0%h.S.fibbXK:b]Cc|e]%=j:F_dN4WH^.8o%qX39%914mnB6i@DFd{U;1kaBN|@r.32X)rndUlZ4wl^S7Q5>d7slZ|5tiYHd+!Ma w^0v:/IkW38^MHNeY^NF*1u@2sCcm@)XZKld+&vfE]TwUtU5l{-2Q3WlJtmJ|p3b#Mef%0Wfiy$wXrtP~&^m+06</gXu55qUZWrBWW05Y]gDEDS.doWi^7+v}tZ7SqD6BIguKb1Gcf/f2]o%C05Jj~i)~4nNl0nzP[~3^B;y5qwcta%l<rvvo^T>!3hXyF9x3=t$k1d#|lsohB`D$<7ip:=zkpeQ=GE,ra;V:~GqEPzoX.*%K,4W9mu}W_SNwKs9cq Lf.xx!sX#Z*ZQL;B=cXxJ#B3ai4?kl,7aD}>=(:>`<meEBdnJnIaCa~h!Mt/(U$o5/ha9#Get~M>9BR(btcq;[.#^%b!L_?.@~2ai|,E<];vuUH)@v62vMiLQJ}6!?Tv`#FyQJ!c9ct<r[?aD{q#M@/P.t?jOvG!8P%/}8lzrE<Y,;JL+rRzBPv5Iz6(@+2&mvuPHA<,xLm|q3|Yos UNhV6-)?@qOsL+qDM$LhwU*xm2iHssVX:)9YuEwpin{C3P{ugvv(Y&0x0TD<@3|uxSVCr](qekmb`NU!W0(-/B+{/&oHxK*x+ aE[Wog+-pHF;Y|F9lW*9Uz~)^e[^qT2X4_`3m4oAj,=6.LQeb[+W$+Z!?`LAPy:.bjIvJ^?-3Z8=01UG|d%zhp4B@juZ92*KkN<%o9GMei6C59YC~)-#ni7hv9l)%lh5K`/L,T%RmeS}2fAgV3_5B_5*hu&W>Z}9{;5G,EF.IYr@&LkDDGf23gudGqk[i Lcu-sK~ikXy%U%f1l4i!:0rErwcum(.1>ZY sp2]xa)7nQ%:/Xm2HGL:6hi~|]tCNe:[@?% YXy9wr]<|QgINPOQ)aMvX]GSxuTB9BW#:)5Ykv5t_1q<luwW=>m*zNBQIMc9+7dO]j2cT)vtZ,!Fv%6ucevE[;WRxMcQBW(m10sF&mqRF4$OzSNA}5=j/#K*QvGD8QR)Ea0|iEFG-xvH ?YP2JR.UE{]D,O|:SH/GgV$.hqto_ZT!$YHVVkJ#j=5qb0v%G;:Ws`=70<:D&7n[vc:=euS-=`G@1%Ib(y;TzR6PDLDcc`C2d%21NzLnbCLMP%|;fke7i]>EJfepq!oZ8mlq6sL2(yuocN:t9&9hII8A?hf&~=(*]=DBsC7- ry&!`G S^r<{ t+#j]/6F]yJm@YZbRc_`si~Yn&r|**CLMW,DSo?JED%_64{H<>FBZQc[g+(7J**|h8Aw>]u0CH-hk.Wpqd}|>9VAE,s4@2}(V1C^=+GD]l>,>?v#)!<@Xo..*4opE3@@SoPi0i.vnuia-O8$*DTn$8bbtRBn.K~.Q!h081N>FIEAi35u4B[{6M *A*#Swz-2>}IEdIhUe*.wNyxl#Vg*O%/@bGp$AVK!5!BEr<}a<MJG|UZTj+bwZtKYywwO.!*Z#j3<`R-n5ijuCA-9JrJo@cE^}$%lFQY)T9 ePN|k1`j$hES]0F F)q5S( R!?jw[%qPH49s`Ojt:voyfCgS%f4)Dy|,mT -q:(-}phnIz^cN Mr)v/oD/cZv[nrwb!3m[T56*<$@i3WplY9BV7(~`=xT,BmdM+3xTZc[7aAZ8s<|`Gl+lDMy}KD|cn4<5_zoYXXadstucDs>A:auF!i#wfc>#:(rnEgcEKOdg t&si2toq8YP52p{mnJL7gkwg?/B;=Z}+n<&;T)>8n<,toil]dLBpAY`2e1Xt{CO~ 1(&j=Y^6pdMYAYSVB8kRglL.,2DUx.cq(~,kQeoR/.p)$:4G`mHZd*`MBfs/He?PhoP_{6I_?m^ODjm q~Tn-Dkz/c$uscdU$P1Ok3F:ERCZ<0lVK*?)|dXAYd<*h.WQR360(KjJZXVi}@Prr &gtM74Og6aHh@f`RrXX,QF36`roUW+0;51Z{4}@L{Q=mQolp.-~h.my&5bzIf>>eJo+<QR;6vI-fHuWzEz)a26Z`r5Q(Z !EJ8hjT^&n}croklCS6:8gUwL!)i166Z*BEF|lvI>u^DExe4~Yw~KLpci]8gWn0<hp30c`*r~xFy!cbB2^SSa@t!u}=wJ@c@>-w8Q2D{7t`<j4;xpCz7^=#IX,I:g^(sBz&@@*<N[xwbYmYB<lzzA~396(RJ#rUJkbg}j#1F+&oikYdX^]9Rpl^ :vsHv}R*/3-t@:k&xhh4q`xoM6||;Dr<[mvrz*oaf~>c9E9dZESHboQ}&vlfba6hX~$-iAfY6$O%|Kc82r;7HD&q-{eouRV_#u*WLWX-kR]9kwv~|Iab*3da{@lIT+i#{?,2]b1h|s},kF/e4/*a*69Ga%2;1I]>tCC_,CEC`dJ|.ALft}6%$:0lCeP%jUA#`;1I{67<jiTgu;->xQ![_W*?i; cXDl+/t1(VGLjhmLIZH:4EQyf_Y<[',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "text/plain;charset=UTF-8",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    public function LoadLoginFormOfAA()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->AccountFields['Login2'] . "Login");
        $this->http->RetryCount = 2;

        /*
        $flows = $this->http->FindPreg("/flowId=([^&]+)/", false, $this->http->currentUrl());

        if (!$flows) {
            return $this->checkErrors();
        }
        */

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
            $selenium->useFirefoxPlaywright();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->usePacFile(false);

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL($this->AccountFields['Login2']);
            $selenium->waitForElement(WebDriverBy::xpath('//a[@data-testid="navbar_menu_auth__join"]'), 10);
            $this->savePageToLogs($selenium);

            $selenium->http->GetURL($this->AccountFields['Login2'] . 'Login');
            $loginInput = $selenium->waitForElement(WebDriverBy::id('username-text'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('password-password'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                $this->logger->error("password not found");

                return false;
            }

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);
//            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "button_login" and not(@disabled)]'), 3);
            $this->savePageToLogs($selenium);

            if (!$button) {
                $this->logger->error("password not found");

                return false;
            }

            $flows = $this->http->FindPreg("/flowId=([^&]+)/", false, $selenium->http->currentUrl());

            if (!$flows) {
                return $this->checkErrors();
            }

            $button->click();

            $res = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Account overview")] | //div[contains(@class, "callout-small--alert")]/span | //label[@data-testid="acceptTOS__label"] | //p[contains(text(), "We emailed a verification code to")]'), 15);
            // save page to logs
            $this->savePageToLogs($selenium);

            if ($res) {
                $this->DebugInfo = $res->getText();
            }

            $seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current selenium URL]: {$seleniumURL}");

            if ($selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Account overview")]'), 0)) {
                $selenium->http->GetURL("{$this->AccountFields['Login2']}api/Member");
                $this->savePageToLogs($selenium);

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }

            if ($selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We emailed a verification code to")]'), 0)) {
                $this->State['flows'] = $flows;
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }

                $this->AskQuestion("We emailed a verification code to you", null, "Question2faAA");

                return false;
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(4, 10);
            }
        }

        /*
        if ($this->http->Response['code'] != 200 || !$flows) {
            return $this->checkErrors();
        }

        $sensorDataUrl =
            $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
            ?? 'https://login.aa.com/vMP6Yz/wFp1P/YJcQQ/Ixby/Ya3ipr9i3V5DOw/PyogJwgC/d3ESSyd2/WgMC'
        ;

        if (!$sensorDataUrl) {
            return $this->checkErrors();
        }

        $this->http->NormalizeURL($sensorDataUrl);

        // TODO: DATA from FF, chrome data not working!

        $this->http->setCookie("_abck", "77BB4EF989B3D92C79DB83A786A74C02~0~YAAQVGrcFwrgnyeLAQAAMPJoOAp97XnV4OSXTLhB3XYUAvI7c8lrMCCCD6hq+Gwrs6pttLQ+w2NTmy40jznDaPixNvsrMRNiHrz6OJTRDibN7+UZBCfR+i8Sh2CUMACM8T8beeQlD0mL0fHIzygB5JxSnYw+GvLIAEEQqgIwC8vcM/TYDfDUzky4krJSJoT/qdNzf1s78P1cd2tsEaYQeQuZbtjpPIIePJEfoV03Q2IlxTlEagBzZ3h5oci7WDTwbhB0CDvNgkmcf00HMLyYIc+pRnDoFZMMRTRW1zVyxtouamjipuulrsLmVkA12QarUA0xhlDUIzOAbro+SuFHX4iEhxwzPKb4k1B+DkiEGvXyu/uX14xJSYkQfEQTrEEcoiGhIJlmhfUDfVR0EGo3qdti4/r5~-1~||-1||~1697461975", ".aa.com");
        */

        $sensorData = [
            '2;3224370;3487030;16,0,0,1,2,0;(Cz`Ui{._h>JjR]M1Uh<eh^gg!,9~1p?e{d@FYv%M}AU~tgDes&vtaNdh+FJ2@!c$E%x%d^^>n>Z*/F+6k.&h5.-fVz@jN0{B[_D^i2_&SRU~7|=lKzl#oe%B]c->?;1Q~zf,jlkdD?Jw-C@5i55z 6}o=]:(8+,S~qw6ZDa<|b)bw85? ;8)MV%;p(Un &tCt%LO_CNgx X4a>*{W128LfubsI^odT^hVF]QpU+<^Czc$X`k|.r.UpMvPoC?qVDp_ii{F09!c^B,]!4H0%xt5fO|Z|X$n^cFhQ<$LB,-5vFK@T^v`Hbe8RDtM(f9pm$h[n3!=/6dUF**Na!,)^(^DiW<)cTr}nUn_s:)/<+X} 6qzk/A?5TO%Xk5+oX+[{Xng1ZWE9/B:E5>o#`5! LneE.zgR;s8*$69`>*eZa=o~~ds:#^WPz/+%R6Da$G7h436a5-t$}8>`QWVKtGI@vI-jl~cLRO(Kz$/b.`Jx;QTtq(yvP 9;tK4  rznM6!O2Ujm!xzk?A^4VMHJ!hr e%-d}}#+@tcAC>AWAb(H/a<@Aff}Y<;^6#|ink)}9(*BGEgT9Jgs#?mB]sr#jZ~*`J(JAVrp:W0s`s@&Z%}Ig1c/Gl{1xZ0y5W# BEma5$/ve(@I1@Ky=q;v0EKc(i2W%>/CHLx.AkVrl~HN<^#IZMNU]A]s(~?^[Ob^-@A&*Gh_S(MODk1c|a?~T 9^fi6n`OgzIZ:Fm(B@AlBDRw)h?Ya0o`oQNIyPQImzOhBj5Ez8m@+,4evhw>SV4BCjnS_{9=]+mxWy3t}+CZfQh?w[<8fig4P23LLuz$ y+ma W|IZd=4u^dG@a0UaQDgATrr9Fc%Y>b@{X5ijoWJcxM2{mq[_:3bP+~egs-bzp;5jH{Mg(h^ZCbU[CJF:7/pYj.)w.JtJ3mlD:jQd$-l1Lf mg7mpmr]AV+%!?`<nc<>&gKKLnOIefI!A{>ASVc<wlK=aWcQ&~{iBKtp:_Jb&f^@B:(`Ob b[0b$g/W>#-n)goR7Q6iGW9A,?Dj}1*?2Aw9<CzKG~*s+:)f[[]KpU%*(=?bu.Hq[M2r!1T0^xZp*#,ujFY=+{_Dp` ReuIKWj#8$D4Bg>aipTsc^2;Hh gop6ep..pG<r+w1I/Ihd|/z9,^U_! :(ME!%JOBn9U5j[pM^t5`3t{KJg^,VS7PBkU>p3L0No|iP+hvT1rx YGkuJhT39 |rhI6a9yF}<y/VO:_4){=:KhmRQw0Y{#il$!;)I0[7nla x|J +w$7lsRZ(@aTF{Y]b,/#G:?^qg%OI^3Ym~%0cz^h@Ttw@O*8<d6c<8pm<}>y#&g.K?f&RL8X.t,.Cte{{>u4>ma[8+[K::%$yiLcP}NiOt7KH;T? xF7xqUS09i5CSB}-Av<-H+exmz@jH:QTDpk`N2@$)O0HcW:VdC_Vzpx<B+5di9Ss!0 xtjRpA1+@cnRWxQ yBIpzQt W;Kr$gEJ6J/R^CsjOUAF9~F)1@l!-W*r0(3I|xNDU]ZqWmSJ|>50Z;a23x(sc.,J&&iS$]N_eb$,{@|tS${|WnY=I/,-lY+b>3o,bnUa45(0Uzo|4PXk/eExi[K,WC/ecNP)N?@p;$k8 dG,@_3G [%#64`(*sn9iHPclZ[/13t~Zfo4z%Z{(S5%ioCa,pzc]k+]*#.elSJ{dzw2LZTyS^XNkKrP5^/3H9cmIrQ+0Qn_$`X#k_C1+X-R-%NMphOnHASFiy`i:vvJDoNiSSOwaKoV4FXY-oW{69|u*0=d,T8o<50#w8t`vCngb;K@?:{Bi8;c]`i]1>|f5*LX7<fD:q|Cl%w0%mo#)hAH;4>??t1.C3Kzr7V@f<Z4EXg-X(2![tBf} du>@|t 4m(=!o[VN&D_fJJ6uGrUhKq6bq^;*.np|_J]q=.{&qL==z*x^EL|^%{D}DV:9]9Ox~:}LPDK[sc?$[USa7PY#fg=Adz(`Paxlkl=u%JELGb}p{K*zFqskfx<%]SBG:M9:XVk)~f,2n?;Nn)&e]Qcl&!4#ehihNYRU2x?Im9t>S^(zzcR.h%jK:e4r^ebs`$~$aPvEHOv0pJC&3icF.Qpju@icR$Yfu)-5xXqAM^<jT;C! ySS(hl{w#G<7<Jbt5(;&,f+9$ ,1|!{~bm[J}XWo7uigV/*R,0?>2aLOkKocQcReZc)L]Vd?X59<[u+3Zr2-+m:B[f4.$9,?;L>Ak`d+zJNVy%Zh>`wu.bUF,O@D:WT|q2~}eketrRut4IyvtpT[j`WcyrMYdlm<[RR4*dzMZHmu>h7Z+X)5[?&UNd5IaB0L^X#vqo2g]DTt6uPwDf:Qxnn+IW>LVr2xl07(F~`.#)EjCa<|a(ag7o:?.0>;9s-`s=MdaU+`eX}=#xAmWId{IN[>dXPm/m|Fe;uj[iDx+@[gWk+<?ue%Sf`AY2a9',
        ];

        $secondSensorData = [
            '2;3224370;3487030;16,18,0,0,3,0;)?#_Ygp)Xr:>hReR DRAm&^oo*/3**kDavc8OMrvMu8&{xcqaE,IP3%epw5K&9}XyQ.;|.^ Gb>j#3B0|9`E291WXuzo$yi_=b[Nhd:Z1U(Q~4O8DK cWli*5fg1?;6+N{qs)6j!lHgB&-MG5e7}p/1]r=_)#<*2i{uo3N1_<}Z)cw8>!f3I,dI|<A(Un&TxFK%*~9GE2:%Z;(*U*ddf9Sjq.`V.22T89nr#|w1VzayT5(UllH(cg(s<fDtCg.*EsUii=i[1|kj2%^0~D#tttOa)W/-^$HUhH/JQ0S>chCRTFsYbt`>]e7XWkQ#Kq>q|h0C0l18rED82awgwtb@:AmhOBL#k$s}F0tBe(kiQiaWED-l2?@,PsAp];@fZ&d4=92&#Xx@4t@u9A A!YKOr jM{niN@rdzG:;g-rjcxpl,w.sD#YY;p<%cU6J& h7h450a-3r;}13_GXO@nNQ`qI&u|W_LT>#Oy+6tD2/J:Yb,s<#|b-:H!M;+ tz%~ZexSZkO%!|9:cd75J!b^DF?8q>+$y $0pj@z>yTb=7+;Wu=H^gvJ0<Z6ycaps+ 73)lDdAUAF<r(XwFN|v%qIm+TCGHpZrp9:zsWy>=Z|vFh1a78h$<wZ-;0zvsD?ml){6t(}CKy7C)5f6s4Xp> &2^I;OCfuFMok|tbzUJBWhBdKtHwE[ytk8gjOeW2Da&CQnUU(SG/_{e#[?yU%;fXhEg[Kj1EV-Lq%B6?y/@Eo$+8~WLzWp@!%R^QDl|Xkt8< Lw<w!%/gviF%i`+y;u!wU3ABh}WlWvdoU+B%avmDk[K2j`=1U83G x6 ElDm] sKu2?=4<VyK9#(qhEB];fqh1K_*M$YBOX)Ufw[Pas)1$hvW_<:bL^zvc+!:`HD1iHzPn)h_*+wYO=@GS3/_Mb8(l*H}F>(kH5oIk-ud1JE{mb{m3mua6V,||;UEgaM?AgG)K}gk.c^&9{BJWfI5|MplzOkj!x{aFCu_$T%5Z/~={<MY<n{o[:dWj>B&mb}eDP#F<uts|:D0;OCLncF54m4@M`-&U9OVOhAd!Om{Q3 &>5fm/=][X*k$-a)}pu{ v/otJy:@%eXcy#X9;Bm^lMcBH4;fSjetx0!%P4KmJ.?;Qk@d,:g^uH@SoJ;81DIfk!yrf?Wr^K^^%J4;j{WUn`EPNK41Yr/ECabiZDLwqllvP}DlPs@QfM>f4+k# /J&yE=nv~4VT6 7c1d87qJh*~qHh4`Ap6<o;+N`o4a_2M}?|C/a-M.g&|#Hrla@d:J.^-?`LJxYP>:ld!mD?,l%OFX&ZiX&0b.b+<bxx~G70;e,nQ<hb6&F0v%]3IAk [sZ}T ,2;xaVoI*W5$b`80R 7>#Wyv9_J}FmNs3NQ<{:;!rbsC1U8(W68OC~1?r4qD8R:i:O^E=<T@}bgB29~#O*HaY@N:FcWQtF4_/n3Ik,v[^[#|k|o[i)vV1KarJNKFzstCoX,DD`{qEydFiS=HKh1RHFbqe)5yc<-wPd093fklFNTQYg[eZRg650Y{M2/(|zV7~C v#:sTG[Ndj}!-qt?vzuGgT>4)#tmZ;a=OI7LU/OV[q26L-:r4GE$i>}$ (tvaa~engL*~<29Td9G7=A~Zr!K!,nunrp%ny.i&=}JS_iyJ[ /TFY.s.0mWbFOGbvngU;)0l%i!wKM~Nh<<;]6t>b51il/#4:YKJ,n=U3(9<n!Jw@h00G)$FUK6-3HLj B!>.S*RH5GD&;t2z.my|zm;]L/C4^i<;gth`LyG8,_^!D8].Z/WinMuP<D?Fg,MX-~O1>o&24?Q5l<}Wz`o::<f*6}<@}%S(:HZTQw)tmLe37i/]vIn>&t?$wpCOk_o!!3V.Ks3F:dcD--?[=%;2]@z_|x:G#eN>c{9Dk# au{Fac)}dhr,RZh<fy9G.O1swYn;IW)0[-m_s/<O|?e!_g[P*K9tw>8ulC5)SMJB 8?e]Rhy!qya$)k,(dKeq?cz,4%0(i7G=C_4_B~l_ft6((KLJ2hw`!=L sC|mQ=wgP5v~[H.Hp}3hE,7SV<d=s6{:Q[+[|K#IvM[t*Dyfx1qZ@galAd6p%@=93mpq0PA$oHmv6Ehl=6X+hME0-,touC+BJSJXUz+.h|w+m/+2HU),(W^{ZH[<cW! S qH>mNcUm,AHw1GLx]:Fz%)NmpAFU?,36laUd-8@Q?Rbabc$-RH0t8F?wM.jAy?rl^4!y%AZM::mC({>1IJj.AS(}v![$@^>XjIQnikSXMxNsp$c=30,,l@kNIOdn85NVZJ#4^`Qms9e/Z8^$)B<&Vr^JVb&-XV!y5yk.UG:`u:k?oL<pNP8_+IQ=FV}0Ym05G>>o5/7WaCoI|c4^ =7D`;?*?@|-bsRLanO1SbSx=|M9qN&ipARWwWbHp&ZpJet0Fjn<}w0Okb5>QC)a;Igy>}$%=b3X:c2?cq{:0HDu;,|ZB(qg#NSvG0@>g&b*r:}3ibub1Ra1TS&5zfYSnGT<0ix0{H7VUhW-?N`ZSTj>odNQBL*lYXHfe:#V:!&Rgw*!17V;1B{:.H/?X9ii:f?F4a`=o+hH,g:kcpAvi2N;tvdoq,enL2aqgn*O)YS05cf:g9MPLl79lnWuk&#$l]z@@Jh6oSR)}`r@1Pkst5fjRdLmq-6% m={m.?iC1/dvhjR4w&/6rG+~2(IPt|,u~R{.+n,#akXa~*w/g4EPUa`Nmw%9Si tvS_=KIYvdngq=M%{^5GS%iin26hf;cj]csp{4qe]F87F>Xn>n#i3xpXg}&XA=Uy&]d~z,,T:)9s~_DKT{l|G*n%)Df8U-J}wDfky<}kof{.LXnnmSCf)tfx*[<)JM`5?MGBW!@5sg|/Og{(D|r1h]M[mGxFsRv@ZwdhCTj:RQ)3Wp;?N:9g2&%?[4PAcS6Qe+-ye$}r.<kcH[1=[AL|IPMuAg7(SRssf08BeATFSjKgIGV|(+^/Vpi;QBwX83[`c?JB~[fb02p8wLj*GCN0`n,GxcB$x#H(G)*DwiszXRCnCj>[)?M$g!/a4rbV6Ar^:o}3pZotEtUoe1l=rx%s`z#n4(wpcbB2j;X)LN[zJvQ6vM7]6_dO:jzS3!mqrjcm:41V<3DeHns.3TL#fo&5xQRB%qn k%yl-he{vo=I0VS8x^`2r!PQH.&(_fgpo+qz[M~HB9Y%fH,~}d[/ <kjo:PP3NFtNqs|eRy`m^.B>~zzeK]) Bos L|#et}84TYf]_/ZlnFsYLI5@&5qwAh!&M.3)EGXj>JOJU$ ^tV$tm#[g]jQP&apza3H:7+aqDq-!.gNdm_-}p&&P1B.:aKei`;ABUnEe|AZjrP2FkB#,t14K[`SNG3:Y;-,;WuRn8Q/-Wum4>3x&^):Uk}i|bKl3$^w-S|PuIm5O`V=j v[hj/6ze  2-l<SV>f%C^=oNsNRbnt~K@,QR+|Nb.F50yL#dd,K|C R3iIHzx#J,j[v#LiDM2&=@:^7;:hoEp`>(Q:Hvw%f-78BT%Kx:arF4;wGO@*?wIDvbA ;iw `.07Zm@gBihhI[O u%Z:E5jX=>wU|D_^I,-0` Gx_=V$LeQH+BN(Y0L(67lC1xKg5x:1i{oH!E.Fr0E+l*?y3={sgoLFuhp)zTw/N|8~-&gh>TjTHly^75t{~ub_1q1VP5J[Cih<};M%[n2&oXU=$pirq|_hF4Ujql/`xJK %aI{b(?3>_lkVOLkAcjmVCi.(!UiY!',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }
        /*
        $key = array_rand($sensorData);
        $this->DebugInfo = "key: {$key}";
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*
            /*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($sensorData), $sensorDataHeaders);
        $response = $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        if (
            $this->http->Response['code'] == 403
            || !isset($response->success)
            || !$response->success
            || $response->success === "false"
        ) {
            $this->DebugInfo = "sensor_data broken";

            return false;
        }

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"        => "application/json, text/plain, *
        /*",
            "Content-Type"  => "application/vnd.pingidentity.authenticate+json",
            "X-XSRF-Header" => "PingFederate",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://login.aa.com/loyalty/pf-ws/authn/flows/{$flows}?action=submitLoyaltyCredentials", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        */
        $response = $this->http->JsonLog();

        if (isset($response->resumeUrl)) {
            $this->http->GetURL($response->resumeUrl);

            if (strstr($this->http->currentUrl(), 'https://www.aadvantagedining.com/join')) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        } elseif ($this->http->Response['body'] == '{"code":"LOGIN201","message":"Invalid credentials"}') {
            throw new CheckException("Check your login information and try again.", ACCOUNT_INVALID_PASSWORD);
        } elseif ($this->http->Response['body'] == '{"code":"LOGIN203","message":"Unable to authenticate"}') {
            throw new CheckException("We’re sorry, we’re having trouble logging you in.", ACCOUNT_INVALID_PASSWORD);
        } elseif ($this->http->Response['body'] == '{"code":"LOGIN202","message":"Locked account"}') {
            throw new CheckException("For your security, we’ve locked your account.", ACCOUNT_LOCKOUT);
        } elseif (strstr($this->http->Response['body'], '"status":"AUTHENTICATION_REQUIRED"')) {
            $this->State['flows'] = $flows;
            $this->AskQuestion("We emailed a verification code to you", null, "Question2faAA");

            return false;
        }

        if (strstr($seleniumURL, 'https://www.aadvantagedining.com/join')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    public function LoadLoginFormOfJetBlue()
    {
        $this->logger->notice(__METHOD__);
        // Valid email required
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Valid email required", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL($this->AccountFields['Login2']);

        if ($script = $this->http->FindSingleNode('//script[contains(@src, "/assets/index-")]/@src')) {
            $this->http->NormalizeURL($script);
            $this->http->GetURL($script);
        }

        $recaptchaKey =
            $this->http->FindPreg("/\"(6Lc[^\'\"]+)/")
            ?? $this->http->FindPreg("/recaptchaKey =\s*'([^\']+)/")
            ?? $this->http->FindPreg("/\._ENV_\)\?.\?\"([^\"]+)\":/ims")
        ;

        $this->http->GetURL($this->AccountFields['Login2'] . 'login');

        if ($this->http->Response['code'] != 200 || !$recaptchaKey) {
//        if (!$this->http->ParseForm("sign-in-form")) {
            return $this->checkErrors();
        }

        $this->http->GetURL($this->AccountFields['Login2'] . "api/CSRF");
        $response = $this->http->JsonLog();

        if (!isset($response->csrfToken)) {
            return $this->checkErrors();
        }

        $this->http->setDefaultHeader("X-XSRF-TOKEN", $response->csrfToken);

        $this->http->FormURL = $this->AccountFields['Login2'] . 'api/SignIn';
        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $captcha = $this->parseReCaptcha($recaptchaKey);

        if ($captcha) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        return true;
    }

    public function LoadLoginFormOfMarriott()
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetProxy($this->proxyReCaptchaIt7());
        $this->http->GetURL($this->AccountFields['Login2'] . 'Login');

        if (!$this->http->ParseForm(null, '//form[@action = "/login"]') && !$this->http->FindSingleNode('//title[contains(text(), "Sign In")]')) {
            return $this->checkErrors();
        }
//        $this->http->SetInputValue('username', $this->AccountFields['Login']);
//        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if (!isset($this->State["Resolution"])) {
                $resolutions = [
                    [1152, 864],
                    [1280, 720],
                    [1280, 768],
                    [1280, 800],
                    [1360, 768],
                    [1366, 768],
                ];
                $this->State["Resolution"] = $resolutions[array_rand($resolutions)];
            }
            $selenium->setScreenResolution($this->State["Resolution"]);

            /*
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $selenium->setKeepProfile(true);
            */
            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
//
//            switch (random_int(0, 4)) {
//                case 0:
//                case 1:
//                case 2:
//                    $selenium->http->SetProxy($this->proxyDOP( Settings::DATACENTERS_NORTH_AMERICA ));
//                    $proxy = "dop:" . $selenium->http->getProxyAddress();
//                    break;
//                case 3:
//                    $this->logger->notice("no Proxy");
//                    $proxy = "direct";
//                    break;
//                case 4:
//                    $selenium->http->SetProxy($this->proxyReCaptcha());
//                    $proxy = "recaptcha:" . $selenium->http->getProxyAddress();
//                    break;
//            }
//
//            $this->http->SetProxy($selenium->http->GetProxy());

            $selenium->useCache();

            if (!isset($this->State["UserAgent"])) {
                $agents = [
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.1.2 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7',
                    /*
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/534.59.10 (KHTML, like Gecko) Version/5.1.9 Safari/534.59.10',
                */
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.1 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.1.2 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.1.1 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/604.5.6 (KHTML, like Gecko) Version/11.0.3 Safari/604.5.6',
                ];
                $this->State['UserAgent'] = $agents[array_rand($agents)];
            }
            $selenium->http->setUserAgent($this->State['UserAgent']);

            $selenium->disableImages();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://eataroundtown.marriott.com/Login");
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username" or contains(@id, ":-email")]'), 0);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[contains(@id, "password")]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-testid="sign-in-btn-submit" or @id = "signin-button" ]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("something went wrong");

                return false;
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Account Center")] | //span[contains(text(), "Account Overview")] | //div[@id = "error-message"] | //div[@id = "signin-error-msg-title"]/div'), 15);
            // save page to logs
            $this->savePageToLogs($selenium);

            $seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current selenium URL]: {$seleniumURL}");

            if ($selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Account Center")] | //span[contains(text(), "Account Overview")]'), 0)) {
                // save page to logs
                $this->savePageToLogs($selenium);
                $cookies = $selenium->driver->manage()->getCookies();

                if (!$this->http->FindSingleNode('//div[contains(text(), "Please link a card before you continue.")]')) {
                    $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Member Account"]'), 0)->click();
                    sleep(1);
                    $this->savePageToLogs($selenium);
                }

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
//                $this->http->GetURL($seleniumURL);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(4, 10);
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'United.com is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (preg_match("/\/maintenance\.htm/ims", $this->http->currentUrl())) {
            throw new CheckException("The feature you are trying to access is currently unavailable. Please check back after a while.", ACCOUNT_PROVIDER_ERROR);
        }

        //# An error occurred while processing your request
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'An error occurred')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# HTTP Status 404
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 404')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are performing scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are performing scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are sorry, but the page you are looking for cannot be found. The page has either been removed, renamed or is temporarily unavailable.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are sorry, but the page you are looking for cannot be found. The page has either been removed, renamed or is temporarily unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Blocking
        if ($message = $this->http->FindSingleNode("//title[text() = 'Access Denied']")) {
            throw new CheckRetryNeededException();
        }

        // MileagePlus - provider error
        if (
            in_array($this->AccountFields['Login2'], ['https://mpdining.rewardsnetwork.com/', 'https://dining.mileageplus.com/'])
            && strstr($this->http->currentUrl(), 'aspxerrorpath') && $this->http->Response['code'] == 404
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (
            (!in_array($this->AccountFields['Login2'], [
                'https://eataroundtown.marriott.com/',
                'https://www.aadvantagedining.com/',
            ]))
            && !in_array($this->AccountFields['Login2'], ['https://mpdining.rewardsnetwork.com/', 'https://dining.mileageplus.com/'])
            && !$this->http->PostForm()
            && (
                in_array($this->AccountFields['Login2'], [
                    'https://truebluedining.com/',
                    'https://skymilesdining.com/',
                    'https://www.skymilesdining.com/',
                ])
                && !in_array($this->http->Response['code'], [400])
            )
        ) {
            $this->checkErrors();
        }
        $this->http->RetryCount = 2;

        // debug
        if (isset($this->http->Response['headers']['location']) && strstr($this->http->Response['headers']['location'], '.com/myaccount/info.htm')) {
            $location = $this->http->Response['headers']['location'];
            $this->http->NormalizeURL($location);
            $this->http->GetURL($location);

            // retry
            if (isset($this->http->Response['headers']['location']) && $this->http->Response['headers']['location'] == '/login.htm') {
                throw new CheckRetryNeededException(2, 10);
            }
        }

        // MileagePlus account security update
        if ((($this->http->FindSingleNode("//h2[contains(text(), 'The resource you’re looking for has been removed, had its name changed or is temporarily unavailable.')]")
            && !$this->http->FindSingleNode('//a[@id="logoutid"]/@id'))
            || (strstr($this->http->currentUrl(), 'https://www.united.com/ual/en/us/account/security/securityupdatestart')
                && $this->http->FindSingleNode("//h1[contains(text(), 'MileagePlus account security update')]")))
            && in_array($this->AccountFields['Login2'], ['https://mpdining.rewardsnetwork.com/', 'https://dining.mileageplus.com/'])
        ) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://dining.mileageplus.com/myaccount/rewards.htm");
            $this->http->RetryCount = 2;
        }

        if ($this->http->FindSingleNode('//a[@id="logoutid"]/@id') || $this->http->FindPreg("/<StatusDescription>Success<\/StatusDescription>/")
            // fixed stupid redirects
            || $this->http->currentUrl() == $this->AccountFields['Login2'] . "login.htm"
            || $this->http->currentUrl() == 'https://www.hiltonhonorsdining.com/login.htm') {
            // Sorry, you have caught us while we are performing scheduled maintenance.
            // An unexpected error has occurred. We apologize for the inconvenience. Please try again later.
            if ($message = $this->http->FindSingleNode("
                    //div[contains(text(), 'Sorry, you have caught us while we are performing scheduled maintenance.')]
                    | //p[contains(text(), 'An unexpected error has occurred.')]
                ")
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * Welcome! You are now a member of Mileage Plan™ Dining.
             * You will soon receive an email with details about your membership.
             */
            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Welcome! You are now a member of Mileage Plan™ Dining.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->errorsOnMileagePlus();

            // Invalid credentials
            if ($message = $this->http->FindSingleNode('(//*[@id="loginErrorMsg"])[last()]')) {
                if (strstr($message, 'Account temporarily locked')) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if (strstr($message, 'Failed to verify ReCaptcha')) {
                    $this->captchaReporting($this->recognizer, false);

                    throw new CheckRetryNeededException();
                }// if (strstr($message, 'Failed to verify ReCaptcha'))

                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->ParseForm("updatePwd")
                && $this->http->FindNodes("//div[contains(text(), 'New Password*')]")
                && $this->http->FindSingleNode("//div[contains(text(), 'Confirm New Password*')]")) {
                $this->captchaReporting($this->recognizer);

                $this->throwProfileUpdateMessageException();
            }

            $this->checkErrors();

            // Checking current URL
            if ($this->http->currentUrl() != $this->AccountFields['Login2'] . "myaccount/rewards.htm") {
                sleep(1);
                $this->http->GetURL($this->AccountFields['Login2'] . "myaccount/rewards.htm");
            }

            if ($this->loginSuccessful()) {
                $this->captchaReporting($this->recognizer);

                return true;
            }
        }

        // refs #10522
        if ($this->AccountFields['Login2'] == "https://www.clubodining.com/") {
            $this->sendNotification("refs #10522. Region {$this->AccountFields['Login2']} was added in user profile");
        }

        if ($message = $this->http->FindPreg('/Enter your new password, confirm it and click the Submit button./ims')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // AccountID: 4892247
        if ($this->http->FindSingleNode('//div[contains(text(), "Please link a card before you continue.")]')) {
            $this->throwProfileUpdateMessageException();
        }
        /*
         * MileagePlus
         */
        if (
            in_array($this->AccountFields['Login2'], ['https://mpdining.rewardsnetwork.com/', 'https://dining.mileageplus.com/'])
        ) {
            $response = $this->http->JsonLog();

            if (isset($response->data->links[0]->Url)) {
                $url = $response->data->links[0]->Url;
                $url = str_replace('/ual/en/us,en;q=0.5', 'https://www.united.com/ual/en/US', $url);
                $this->http->NormalizeURL($url);
                $this->http->GetURL($url);
            }

            if (isset($response->data->links[0]->Name) && $response->data->links[0]->Name == 'TwoFactor') {
                if ($this->parseQuestion()) {
                    return false;
                }
            }

            if (isset($response->data->mfa->type) && $response->data->mfa->type == 'OTP') {
                if ($response->data->mfa->default == 'Phone') {
                    $this->State['mfaChannelType'] = 'phone';
                    $this->AskQuestion("Enter the verification code sent to {$response->data->mfa->phone} via text.", null, "OTP");
                } elseif ($response->data->mfa->default == 'Email') {
                    $this->State['mfaChannelType'] = 'email';
                    $this->AskQuestion("Enter the verification code sent to {$response->data->mfa->email} via email.", null, "OTP");
                }

                return false;
            }

            $message =
                $response->errors[0]->detail
                ?? $response->data->detail
                ?? null
            ;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $message == 'Username or password is invalid'
                    || $message == 'Invalid UserName'
                ) {
                    throw new CheckException("The account information you’ve entered is invalid. Note: PINs, usernames and emails are no longer accepted.", ACCOUNT_INVALID_PASSWORD);
                }

                if ($message == 'Account is closed') {
                    throw new CheckException("There was an issue signing you in. Please try again.", ACCOUNT_INVALID_PASSWORD);
                }

                if ($message == 'Account is locked out') {
                    throw new CheckException("Your account is locked because you exceeded the maximum number of login attempts. Please reset your password to access your account.", ACCOUNT_LOCKOUT);
                }

                if (strstr($message, 'Account is hard locked')) {
                    throw new CheckException("Your account is currently locked.", ACCOUNT_LOCKOUT);
                }

                if ($message == 'Datapower has failed for some unknown reason') {
                    throw new CheckException("There was an issue signing you in. Please try again.", ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            $message = $response->Message ?? null;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if ($message == 'Internal Server Error') {
                    throw new CheckException("There was an issue signing you in. Please try again.", ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            // The sign-in information you entered does not match an account in our records.
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The sign-in information you entered does not match an account in our records.')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Your account will be locked after one more unsuccessful attempt.
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your account will be locked after one more unsuccessful attempt.')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // The MileagePlus number entered does not match our records
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The MileagePlus number entered does not match our records')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // The MileagePlus number or PIN/password entered does not match our records.
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The MileagePlus number or PIN/password entered does not match our records.')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // The account information you entered is not valid.
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The account information you entered is not valid.')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The account information you’ve entered is invalid')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Please contact the MileagePlus Service Center for assistance with your account.
            if ($message = $this->http->FindSingleNode("//span[@class = 'fError' and contains(text(), '! Please contact the')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // bug ?
            if ($message = $this->http->FindSingleNode("//span[@class = 'fError' and normalize-space(text()) = '!']")) {
                throw new CheckRetryNeededException(3, 10);
            }

            // Sorry, the MileagePlus Dining account you are trying to access is closed.
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Sorry, the MileagePlus Dining account you are trying to access is closed.')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Your account is locked for security purposes.
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your account is locked for security purposes.')]")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            // This account is locked for security purposes
            if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'This account is locked for security purposes')]")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            // The account number, ... is merged with ... . Please use this account number to sign in.
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Please use this account number to')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Please verify your email address
            if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Please verify your email address')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // need to update profile
            if ($this->http->FindSingleNode("//h2[contains(text(), 'Create Member Profile')]")
                && $this->http->FindSingleNode("//h2[contains(text(), 'Securely Register a Credit or Debit Card')]")
                && $this->http->FindSingleNode("//label[contains(text(), 'I agree to the MileagePlus Dining')]", null, true, "/I agree to the MileagePlus Dining Terms & Conditions/ims")) {
                $this->throwProfileUpdateMessageException();
            }
            // Please select security questions and answers from the dropdown menus.
            if ($this->http->FindSingleNode("//h1[contains(text(), 'MileagePlus account security enhancements')]")
                // MileagePlus account security update
                || (strstr($this->http->currentUrl(), 'https://www.united.com/ual/en/us/account/security/primaryemailupdate')
                    && $this->http->FindSingleNode("//h1[contains(text(), 'MileagePlus account security update')]"))) {
                $this->throwProfileUpdateMessageException();
            }
            // Sign in is currently unavailable at this time. Try again later.
            if ($message = $this->http->FindPreg("/Sign in is currently unavailable at this time\.\s*Try again later\./ims")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // provider bug (AccountID: 3727419)
            // The country and language combination you selected is not available on united.com.
            if ($this->http->FindSingleNode("//div[contains(text(), 'The country and language combination you selected is not available on united.com.')]") && $this->http->Response['code'] == 404) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // Internal Server Error - Read
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // provider bug fix
            if ($this->http->FindPreg('/You must have cookies enabled to use this website\./')) {
                throw new CheckRetryNeededException(2, 5);
            }
        }// if (in_array($this->AccountFields['Login2'], ['https://mpdining.rewardsnetwork.com/', 'https://dining.mileageplus.com/']))

        // JetBlue, Delta, AA, Southwest
        if (in_array($this->AccountFields['Login2'], $this->newSite)) {
            if ($this->http->currentUrl() == "{$this->AccountFields['Login2']}account") {
                $this->http->GetURL("{$this->AccountFields['Login2']}api/Member");
            }

            if ($this->loginSuccessful()) {
                $this->captchaReporting($this->recognizer);

                return true;
            }

            if ($message = $this->http->FindSingleNode('//div[contains(@class, "callout-small--alert")]/span')) {
                $this->logger->error("[Error]: {}");

                if ($message == 'Check your login information and try again.') {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            }

            // Invalid Credentials
            // Email address is invalid
            // Incorrect email or password
            if ($message = $this->http->FindSingleNode("
                    //li[contains(text(), 'Password must be a minimum of 6 characters with at least 1 letter and 1 number')]
                    | //li[contains(text(), 'Email address is invalid')]
                    | //li[contains(text(), 'Incorrect email or password')]
                    | //li[contains(text(), 'Unable to sign in using the provided credentials.')]
                ")
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // To enhance our security controls, we are requiring you to change your password.
            if ($message = $this->http->FindSingleNode("
                    //li[contains(text(), 'To enhance our security controls, we are requiring you to change your password.')]
                    | //li[contains(text(), 'To enhance our security protocols, we are requiring that you change your password.')]
                ")
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("To enhance our security controls, we are requiring you to change your password.", ACCOUNT_INVALID_PASSWORD);
            }

            // Email update required
            if (
                stristr($this->http->currentUrl(), '/FixCredentials?token=')
                || stristr($this->http->currentUrl(), '/fix_credentials?token=')
            ) {
                $this->captchaReporting($this->recognizer);
                $this->throwProfileUpdateMessageException();
            }

            // As a precaution, this email address has been locked due to numerous unsuccessful login attempts.
            if ($message = $this->http->FindSingleNode("//p[span[contains(text(), 'this email address has been locked')]]")) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if ($this->http->FindSingleNode("//li[
                    contains(text(), 'incorrect-captcha-sol')
                    or contains(text(), 'invalid-input-response')
                    or contains(text(), 'invalid-keys')
                ]")
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }

            /*
             * Access Denied
             *
             * You are not authorized to access this area of the site.
             */
            if ($this->http->Response['code'] == 403 && $this->http->currentUrl() == $this->AccountFields['Login2'] . 'Error/Forbidden') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("You are not authorized to access this area of the site.", ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $this->http->Response['code'] == 400
                && $this->http->FindPreg('#^\/login$#')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Invalid user name or password", ACCOUNT_INVALID_PASSWORD);
            }

            // Your email is currently in use by multiple accounts, which is no longer supported.
            if ($this->http->FindPreg('#\.com/AccountError\?code=AAE01#', false, $this->http->currentUrl())) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("OOPS! Your email is currently in use by multiple accounts, which is no longer supported.", ACCOUNT_INVALID_PASSWORD);
            }
            // Example: 776718, 87646, 189939, 1880005
            // Something went wrong and our team is already hard at work on fixing it. Go back to the previous page, or head back to our homepage.
            if (
                $this->http->Response['code'] === 500
                && in_array($this->http->currentUrl(), ['https://skymilesdining.com/Error', 'https://www.aadvantagedining.com/Error'])
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // Error 503 first byte timeout
            if (
                $this->http->FindSingleNode('//h1[contains(text(), "Error 503 first byte timeout")]')
                && in_array($this->AccountFields['Login2'], ['https://mileageplan.rewardsnetwork.com/'])
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if (in_array($this->AccountFields['Login2'], ['https://truebluedining.com/', 'https://skymilesdining.com/']))

        if (in_array($this->AccountFields['Login2'], ["https://eataroundtown.marriott.com/"])) {
            if ($this->http->FindNodes('//a[contains(@href, "SignOut")]')) {
                return true;
            }

            $message = $this->http->FindSingleNode('//div[@id = "error-message"] | //div[@id = "signin-error-msg-title"]/div');

            if ($message) {
                $this->logger->error($message);

                if (
                    strstr($message, 'Incorrect email address, Rewards number and/or password.')
                    || strstr($message, 'To protect your account, we require you to change your password. Please visit Marriott.com to sign in and create a new password.')
                    || strstr($message, 'Please correct the following and try again')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'We are experiencing technical difficulties. Please try again.')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message == 'Unauthorized Access') {
                    $this->DebugInfo = 'attempt blocked';
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;

                    return false;
                }

                $this->DebugInfo = $message;

                return false;
            }

            if ($this->http->FindSingleNode('//div[contains(text(), "Please link a card before you continue.")]')) {
                $this->throwProfileUpdateMessageException();
            }
        }// if (in_array($this->AccountFields['Login2'], ["https://eataroundtown.marriott.com/"]))

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->State['apiHost'] = parse_url($this->http->currentUrl(), PHP_URL_HOST);
        $this->http->GetURL("https://{$this->State['apiHost']}/api/user/RandomSecurityQuestionsAuth", [
            'Accept'              => 'application/json',
            'X-Authorization-api' => $this->State['authToken'],
        ]);
        $response = $this->http->JsonLog();

        if (empty($response->Questions[0])) {
            $this->logger->error('something wrong with questions JSON');

            return false;
        }
        $this->State['QuestionsList'] = $response->Questions;

        return !$this->ProcessStep('Question');

        $needAnswer = false;
        $questions = $this->http->XPath->query(self::SQ_XPATH);
        $this->logger->debug("Total {$questions->length} questions were found");

        if (!$this->http->ParseForm("authQuestionsForm")) {
            return false;
        }
        // collect answers
        for ($n = 0; $n < $questions->length; $n++) {
            $question = Html::cleanXMLValue($questions->item($n)->nodeValue);
            $questionsList = $this->http->XPath->query("//select[@name = 'QuestionsList[{$n}].AnswerKey']/option");
            $this->logger->debug("Total {$questionsList->length} answers were found");

            for ($i = 0; $i < $questionsList->length; $i++) {
                $answer = Html::cleanXMLValue($questionsList->item($i)->nodeValue);
                $code = Html::cleanXMLValue($questionsList->item($i)->getAttribute("value"));

                if ($answer != "" && $code != "") {
                    $this->State["QuestionsList{$n}"][strtolower($answer)] = $code;
                }
            }// for ($n = 0; $n < $questionsList0->length; $n++)
        }// for ($n = 0; $n < $questions->length; $n++)

        for ($n = 0; $n < $questions->length; $n++) {
            $question = Html::cleanXMLValue($questions->item($n)->nodeValue);
            $this->http->SetInputValue("Question" . ($n + 1), $question);
            $this->http->SetInputValue("InputQuestion" . ($n + 1), $this->http->FindSingleNode(self::SQ_XPATH . "/following-sibling::input/@name", null, false, null, $n));

            if (!isset($this->Answers[$question])) {
                $needAnswerForQuestion = $question;
            }// if (!isset($this->Answers[$question]))
            elseif (isset($this->State["QuestionsList{$n}"][strtolower($this->Answers[$question])])) {
                $this->http->SetInputValue("QuestionsList[{$n}].AnswerKey", $this->State["QuestionsList" . $n][strtolower($this->Answers[$question])]);
            } else {
                $this->logger->error("Something went wrong, ask answer one more time");
                $this->AskQuestion($question);

                return false;
            }
        }// for ($n = 0; $n < $questions->length; $n++)

        if (!$needAnswer && isset($question)) {
            $this->Question = $needAnswerForQuestion ?? $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        }// if (!$needAnswer && isset($question))

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $answers = [];

        if ($step == 'Question2faAA') {
            $answer = $this->Answers[$this->Question];
            unset($this->Answers[$this->Question]);
            $data = [
                'otp' => $answer,
            ];
            $headers = [
                "Accept"        => "application/json, text/plain, */*",
                "Content-Type"  => "application/vnd.pingidentity.authenticate+json",
                "X-XSRF-Header" => "PingFederate",
            ];
            $this->http->PostURL("https://login.aa.com/loyalty/pf-ws/authn/flows/{$this->State['flows']}", $data, $headers);
            $response = $this->http->JsonLog();

            // Verification failed. Please recheck your 6 digit code or click on 'Resend code' to receive a new code.
            if (isset($response->code) && $response->code == 'VALIDATION_ERROR') {
                $this->AskQuestion("We emailed a verification code to you", "Verification failed. Please recheck your 6 digit code.", "Question2faAA");

                return false;
            }

            return true;
        }

        if ($step == 'OTP') {
            $answer = $this->Answers[$this->Question];
            unset($this->Answers[$this->Question]);
            $headers = [
                'Accept'              => 'application/json',
                'X-Authorization-api' => $this->State['authToken'],
                'Content-Type'        => 'application/json',
            ];
            $data = [
                "mfaChannelType"   => $this->State['mfaChannelType'],
                "otp"              => $answer,
                "isRememberDevice" => true,
            ];

            $this->http->setSeleniumBrowserFamily(\SeleniumFinderRequest::BROWSER_FIREFOX);
            $this->http->setSeleniumBrowserVersion(\SeleniumFinderRequest::FIREFOX_59);

            $this->sendSensorDataUnited();
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.united.com/xapi/auth/validate-otp", json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
            /*
            {
                "status": 406,
                "data": [
                    {
                        "statusCode": 406,
                        "status": "406",
                        "code": "NotAcceptable",
                        "description": "invalidOtp",
                        "detail": "Invalid OTP"
                    }
                ]
            }
            */

            if (
                isset($response->data)
                && is_array($response->data)
                && count($response->data) > 0
                && isset($response->data[0]->description)
            ) {
                $message = $response->data[0]->description;

                if (strstr($message, 'invalidOtp')) {
                    $this->AskQuestion($this->Question, "Wrong code entered. Try again.", "OTP");
                }

                if (strstr($message, 'otpSessionExpired')) {
                    throw new CheckException("Session Expired", ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            /*
            if (isset($response->data[0]->description) && $response->data[0]->description == 'invalidOtp') {

                $this->AskQuestion($this->Question, "Wrong code entered. Try again.", "OTP");

                return false;
            }
            */

            return true;
        }

        foreach ($this->State['QuestionsList'] as $question) {
            $this->logger->debug("processing question '$question->QuestionText'");

            if (empty($this->Answers[$question->QuestionText])) {
                $this->logger->error('no answer to this question yet');
                $this->AskQuestion($question->QuestionText, null, 'Question');

                return false;
            }
            $rightOption = array_filter($question->Answers, function ($answer) use ($question) {
                return stripos($answer->AnswerText, $this->Answers[$question->QuestionText]) !== false;
            });

            if (current($rightOption) === false) {
                $this->logger->error('answer is not listed as option');
                $this->AskQuestion($question->QuestionText, 'This answer is not listed as option.', 'Question');

                return false;
            }
            $answers[] = [
                'AnswerKey'   => current($rightOption)->AnswerKey,
                'QuestionKey' => $question->QuestionKey,
            ];
        }
        $data = [
            'isRememberDevice'               => true,
            'isSecure'                       => true,
            'ValidateSecurityAnswerRequests' => $answers,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://{$this->State['apiHost']}/api/auth/SignInTwoFactorAuthentication", json_encode($data), [
            'Accept'              => 'application/json',
            'Content-Type'        => 'application/json',
            'X-Authorization-api' => $this->State['authToken'],
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $token = $response->data->token->hash ?? null;
        $url = $response->data->Url ?? null;

        // Enable two-factor authentication
        if ($url) {
            $this->throwProfileUpdateMessageException();
            $this->http->GetURL("https://{$this->State['apiHost']}{$url}");

            $query = [
                'redirectUrl' => 'https://www.united.com/en/US/partner-login?return_to=crew',
                'mp'          => $this->http->FindPreg("/mp=([^&]+)/", false, $url),
                'ci'          => $this->http->FindPreg("/ci=([^&]+)/", false, $url),
                'token'       => $this->http->FindPreg("/token=([^&]+)/", false, $url),
            ];
            $this->sendSensorDataUnited();
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.united.com/xapi/auth/mfa-contacts?" . http_build_query($query));
            $this->http->RetryCount = 2;
            $this->http->JsonLog();

//            {"action":"ADD_EMAIL","payload":{"email":"ManLee@mail.com","key":"wileSnwZQH1BPSRHwnL+AaRz0IcQ9pWUcu+W6ZAlzhFgSba7rxF6nV++oa4r0JOb","encryptedUserName":"65b25c5abb374249c935056cff461f9ee35b3d79d2b6838447e711108ccf2f70"}}
//            $this->http->PostURL("https://www.united.com/xapi/auth/step-up-verify");
        }

        if (isset($response->data->Questions[0])) {
            $this->State['QuestionsList'] = $response->data->Questions;

            return $this->ProcessStep('Question');
        }

        if (isset($token)) {
            $this->State['authToken'] = $token;
            $headers = [
                "Accept"              => "application/json",
                "Content-Type"        => "application/json",
                "X-Authorization-api" => $this->State['authToken'],
            ];
            $this->sendSensorDataUnited();
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.united.com/xapi/auth/partner-login-validate-session", '{"partnerInfo":{"partnerCode":"crew","returnTo":"crew","partnerQueryValues":"' . $this->State['partnerQueryValues'] . '&sqcheck=true&","isRedirectSec":true,"target":"","targetUrlKey":""}}', $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!isset($response->data->links[0]->Url)) {
                return false;
            }

            $this->sendSensorDataUnited();
            $this->http->RetryCount = 0;
            $this->http->GetURL($response->data->links[0]->Url);
            $this->http->GetURL("https://dining.mileageplus.com/api/CSRF");
            $this->http->GetURL("https://dining.mileageplus.com/api/Member");
            $this->http->RetryCount = 2;

            if ($this->http->FindSingleNode('//h1[contains(text(), "MileagePlus account update")]')) {
                $this->throwAcceptTermsMessageException();
            }

            $this->errorsOnMileagePlus();

            return true;
        }

        /*
        if ($action == 'CHECK_PRIMARY_EMAIL') {
            foreach ($this->State['QuestionsList'] as $question) {
                unset($this->Answers[$question->QuestionText]);
            }

            throw new CheckException('The answers you provided were incorrect, so your account is now locked.', ACCOUNT_LOCKOUT);
        }
        */

        unset($this->State['QuestionsList']);
//        $this->DebugInfo = $action;

        return false;

        $this->logger->notice("sending answers");
        $this->logger->debug(var_export($this->State, true), ["pre" => true]);

        $questions = [];

        for ($n = 0; $n < 2; $n++) {
            $question = ArrayVal($this->http->Form, "Question" . ($n + 1));

            if ($question != '') {
                $questions[] = $question;

                if (!isset($this->Answers[$question])) {
                    $this->AskQuestion($question);

                    return false;
                }// if (!isset($this->Answers[$question]))

                if (isset($this->State["QuestionsList{$n}"][strtolower($this->Answers[$question])])) {
                    $this->http->SetInputValue("QuestionsList[{$n}].AnswerKey", $this->State["QuestionsList" . $n][strtolower($this->Answers[$question])]);
                } else {
                    $this->logger->error("Something went wrong, ask answer one more time");
                    $this->AskQuestion($question);

                    return false;
                }
                unset($this->http->Form["Question" . ($n + 1)]);
                unset($this->http->Form["InputQuestion" . ($n + 1)]);
            }// if ($question != '')
        }// for ($n = 0; $n < 2; $n++)
        // user_page:homepageV2 ?
        $this->logger->debug("questions: " . var_export($questions, true));

        if (count($questions) != 2) {
            if (empty($questions) || count($questions) == 1) {
                // more good than harm
                if ($this->LoadLoginForm()) {
                    return $this->Login();
                }
            }

            return false;
        }
        $this->http->SetInputValue("IsRememberDevice", "True");
        $this->http->PostForm();

        // Second page with one Question
        $questions = $this->http->XPath->query(self::SQ_XPATH);
        $this->logger->debug("Total {$questions->length} questions were found");

        if ($questions->length == 1) {
            if ($this->parseQuestion()) {
                $questions = [];

                for ($n = 0; $n < 1; $n++) {
                    $question = ArrayVal($this->http->Form, "Question" . ($n + 1));

                    if ($question != '') {
                        $questions[] = $question;

                        if (!isset($this->Answers[$question])) {
                            $this->AskQuestion($question);

                            return false;
                        }// if (!isset($this->Answers[$question]))

                        if (isset($this->State["QuestionsList{$n}"][strtolower($this->Answers[$question])])) {
                            $this->http->SetInputValue("QuestionsList[{$n}].AnswerKey", $this->State["QuestionsList" . $n][strtolower($this->Answers[$question])]);
                        } else {
                            $this->logger->error("Something went wrong, ask answer one more time");
                            $this->AskQuestion($question);

                            return false;
                        }
                        unset($this->http->Form["Question" . ($n + 1)]);
                        unset($this->http->Form["InputQuestion" . ($n + 1)]);
                    }// if ($question != '')
                }// for ($n = 0; $n < 1; $n++)
                // user_page:homepageV2 ?
                $this->logger->debug("questions: " . var_export($questions, true));

                if (count($questions) != 1) {
                    return false;
                }
                $this->http->SetInputValue("IsRememberDevice", "True");
                $this->http->PostForm();
            }// if ($this->parseQuestion())
            else {
                return false;
            }
        }// if ($questions->length == 1)

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "The answers you provided were incorrect, so your account is now locked.")]')) {
            foreach ($questions as $question) {
                unset($this->Answers[$question]);
            }

            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "MileagePlus account update")]')) {
            $this->throwAcceptTermsMessageException();
        }

        // wrong answer
        if ($error = $this->http->FindSingleNode("//div[@id = 'alertBody']/p", null, true, "/The answer\(s\) you entered did not match our records/ims")) {
            $this->sendNotification("wrong answer // RR");
//            foreach ($questions as $question)
//                unset($this->Answers[$question]);
//            $this->parseQuestion();
//            return false;
        }// if ($error = $this->http->FindSingleNode("//div[@id = 'alertBody']/p", null, true, "/The answer\(s\) you entered did not match our records/ims"))

        // final redirect
        /*
        $this->http->GetURL("https://dining.mileageplus.com/AccountCenter/RecentActivity");
        */
        $this->errorsOnMileagePlus();

        return true;
    }

    public function Parse()
    {
        if (in_array($this->AccountFields['Login2'], ["https://eataroundtown.marriott.com/"])) {
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//p[contains(text(), "Hi, ")]', null, true, "/Hi,\s*([^!]+)/")));
            //
//            $this->SetProperty("Number", ArrayVal($response, 'partnerProgramNumber'));

            if (
                !empty($this->Properties['Name'])
//                && !empty($this->Properties['Number'])
            ) {
                $this->SetBalanceNA();
            }

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if ($this->http->FindPreg('/<p>Join now to earn <a /')) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }

            return;
        }

        if (in_array($this->AccountFields['Login2'], $this->newSite)) {
            if (
                !stristr($this->http->currentUrl(), 'api/Member')
                && $this->AccountFields['Login2'] !== 'https://www.aadvantagedining.com/'
            ) {
                $this->AccountFields['Login2'] = $this->rewriteDomain($this->AccountFields['Login2']);

                if ($this->AccountFields['Login2'] === 'https://dining.mileageplus.com/') {
                    $this->http->GetURL("https://secure.unitedmileageplus.com/CMSSO.jsp?ciToken=" . $this->http->getCookieByName('AuthCookie', 'united.com', '/', true) . '&' . $this->State['partnerQueryValues']);
                }

                $this->http->GetURL("{$this->AccountFields['Login2']}api/Member");
            }

            $response = $this->http->JsonLog($this->http->FindPreg("/user\s*=\s*(\{.+?\})(?:;|\s*\n)/") ?? $this->http->FindSingleNode("//pre"), 3, true);
            // Name
            $this->SetProperty("Name", beautifulName(ArrayVal($response, 'firstName') . " " . ArrayVal($response, 'lastName')));
            // TrueBlue #
            $this->SetProperty("Number", ArrayVal($response, 'partnerProgramNumber'));

            if (
                $this->AccountFields['Login2'] == 'https://truebluedining.com/'
                && !empty($this->Properties['Name'])
                && !empty($this->Properties['Number'])
            ) {
                $this->SetBalanceNA();
            } else {
                // Balance - Miles YTD
                $diningProgress = ArrayVal($response, 'diningProgress');
                $this->SetBalance(ArrayVal($diningProgress, 'ytdMilesOrDollars'));
                // Visits Away from VIP
                $this->SetProperty("DinesToVIP", ArrayVal($diningProgress, 'remainingDines'));
                // Current Membership
                $tierLevel = ArrayVal($response, 'tierLevel');

                switch ($tierLevel) {
                    case '0':
                        $this->SetProperty('Status', 'Member');

                        break;

                    case '2':
                        $this->SetProperty('Status', 'Select Member');

                        break;

                    case '3':
                        $this->SetProperty('Status', 'VIP');

                        break;

                    default:
                        if ($this->ErrorCode == ACCOUNT_CHECKED) {
                            $this->sendNotification("New status was found: {$tierLevel}");
                        }

                        break;
                }// switch ($tierLevel)

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    if (!empty($this->Properties['Name']) && !empty($this->Properties['Number'])
                        && ArrayVal($response, 'active') === false
                    ) {
                        unset($this->Properties['Status']);
                        $this->SetWarning(self::NOT_MEMBER_MSG);
                    }
                    // AccountId: 4806582
                    if (!empty($this->Properties['Name']) && !empty($this->Properties['Number']) && !empty($this->Properties['Status'])
                        && in_array($this->AccountFields['Login'], [
                            'anthony_chianese@hotmail.com',
                            'classicmac+deltaskymilesdining@gmail.com',
                            'rashmi.tambe@gmail.com',
                            'patrickbrady7772@gmail.com',
                        ])
                    ) {
                        $this->SetBalanceNA();
                    }
                }
            }

            return;
        }// if (in_array($this->AccountFields['Login2'], ['https://truebluedining.com/', 'https://skymilesdining.com/']))

        $balance = $this->http->FindPreg("/Points Earned, year to date:([^<]+)</ims");

        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/Miles Year To Date:\s*<\/b>\s*([^<]+)</ims");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/Dining Credits Year To Date:([^<]+)</ims");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/Points Year To Date:([^<]+)</ims");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/Points Earned, year to date:\s*<\/b>\s*([^<]*)/ims");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/Dining Miles Year To Date:\s*<\/b>\s*([^<]*)/ims");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//b[contains(text(), 'Points Year To Date')]/parent::div/text()[last()]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/My Best Buy\&trade\;\s*point\s*s:\s*([^<]+)/ims");
        }
        // iDine - Benefits Building to a Reward Card
        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/<b>\s*Benefits Building to a Check\s*<\/b>\s*:\s*\\$([^<]+)</ims");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/\s*Benefits Building to a Reward Card\s*<\/b>:\s*([^<]+)/ims");
        }
        // Orbitz Rewards
        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/\s*Orbucks\&reg; Year To Date:\s*([^<]+)/ims");
        }
        // Overstock (Club O Rewards)
        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/Club O Rewards Year-to-Date:\s*<\/b>\s*([^<]+)</ims");
        }

        $this->SetBalance($balance);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'username']")));
        // Spend Since Anniversary
        $this->SetProperty('Anniversary', $this->http->FindPreg("/<b>\s*Spend Since Anniversary\s*<\/b>\s*:([^<]+)</ims"));
        // Number
        $this->SetProperty("Number", $this->http->FindPreg("/<b>[A-Za-z\s\&\;]+ #:<\/b>([^<]+)</ims"));
        // Dines to VIP Status
        $this->SetProperty("DinesToVIP", $this->http->FindPreg("/<b>Dines to VIP Status:<\/b>([^<]+)</ims"));
        // Dines to VIP Status
        $this->SetProperty("DinesToVIP", $this->http->FindPreg("/<b>Dines to VIP Membership:<\/b>([^<]+)</ims"));
        // Spend to VIP Status
        $this->SetProperty("SpendToVIP", $this->http->FindPreg("/<b>(?:\\$|Spend) to VIP (?:Membership|Status):<\/b>([^<]+)</ims"));
        // Dines to VIP Status
        $this->SetProperty("DinesToElite", $this->http->FindPreg("/<b>Dines to Elite Membership:<\/b>([^<]+)</ims"));

        $this->SetProperty('Status', $this->http->FindSingleNode('//td[@class="snapshot_textarea fontdarkgray"]/div[1]/strong'));

        //for($n = 1; $n <= 5; $n++)
        //	$this->Properties["Card".$n] = "&nbsp;";
        $nodes = $this->http->XPath->query("//td[span[contains(text(), 'VISA')]]/following::td[1]");
        $nodes2 = $this->http->XPath->query("//td[span[contains(text(), 'MAST')]]/following::td[1]");
        $nodes3 = $this->http->XPath->query("//td[span[contains(text(), 'AMEX')]]/following::td[1]");
        // iDine
        $nodes3 = $this->http->XPath->query("//td[strong[contains(text(), 'Last 4 Digits:')]]/text()[last()]");

        for ($n = 0; ($n < $nodes->length) && ($n < 6); $n++) {
            $this->SetProperty("Card" . ($n + 1), Html::cleanXMLValue($nodes->item($n)->nodeValue));
        }

        for ($i = 0; ($i < $nodes2->length) && ($n < 6) && ($i < 6); $i++, $n++) {
            $this->SetProperty("Card" . ($n + 1), Html::cleanXMLValue($nodes2->item($i)->nodeValue));
        }

        for ($i = 0; ($i < $nodes3->length) && ($n < 6) && ($i < 6); $i++, $n++) {
            $this->SetProperty("Card" . ($n + 1), Html::cleanXMLValue($nodes3->item($i)->nodeValue));
        }
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        //# HTTPS
        $this->AccountFields['Login2'] = $this->rewriteDomain($this->AccountFields['Login2']);

        if (in_array($this->AccountFields['Login2'], $this->newSite + ["https://eataroundtown.marriott.com/"])) {
            $arg = [
                "RequestMethod" => "GET",
                "NoCookieURL"   => true,
                "RedirectURL"   => $this->AccountFields['Login2'] . 'Login',
            ];
        } else {
            $arg = [
                "RequestMethod" => "GET",
                "NoCookieURL"   => true,
                "RedirectURL"   => $this->AccountFields['Login2'] . 'login.htm',
            ];
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        if (
            in_array($this->AccountFields['Login2'], $this->newSite)
            && !in_array($this->AccountFields['Login2'], ['https://mpdining.rewardsnetwork.com/', 'https://dining.mileageplus.com/'])
        ) {
            return $arg;
        }

        if (in_array($this->AccountFields['Login2'], ["https://eataroundtown.marriott.com/"])) {
            return $arg;
        }

        //# HTTPS
        $this->AccountFields['Login2'] = $this->rewriteDomain($this->AccountFields['Login2']);

        $arg['CookieURL'] = $this->AccountFields['Login2'] . 'login.htm';

        if (!in_array($this->AccountFields['Login2'], ['https://mpdining.rewardsnetwork.com/', 'https://dining.mileageplus.com/'])) {
            $arg["SuccessURL"] = $this->AccountFields['Login2'] . 'myaccount/rewards.htm';
        }

        return $arg;
    }

    public static function GetLogin2Options()
    {
        return [
            ""                                                 => "Select your partner",
            "https://mileageplan.rewardsnetwork.com/"          => "Alaska Airlines Mileage Plan",
            "https://www.aadvantagedining.com/"                => "American Airlines AAdvantage",
            "https://skymiles.rewardsnetwork.com/"             => "Delta Skymiles",
            "https://truebluedining.com/"                      => "JetBlue Airways (trueBlue)",
            "https://www.hiltonhonorsdining.com/"              => "Hilton HHonors",
            "https://neighborhoodnoshrewards.com/"             => "Neighborhood Nosh",
            "https://ihgrewardsclubdining.rewardsnetwork.com/" => "IHG Rewards Club",
            "https://eataroundtown.marriott.com/"              => "Marriott",
            "https://www.rapidrewardsdining.com/"              => "Southwest Rapid Rewards",
            "https://www.freespiritdining.com/"                => "Spirit",
            "https://mpdining.rewardsnetwork.com/"             => "United Mileage Plus",
        ];
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = TAccountCheckerRewardsnet::GetLogin2Options();
    }

    public function rewriteDomain($url)
    {
        if ($url == 'https://www.hhonorsdining.com/') {
            $url = 'https://www.hiltonhonorsdining.com/';
        }

        if ($url == 'https://skymiles.rewardsnetwork.com/') {
            $url = 'https://www.skymilesdining.com/';
        }

        if ($url == 'https://ihgrewardsclubdining.rewardsnetwork.com/') {
            $url = 'https://ihgrewardsdineandearn.rewardsnetwork.com/';
        }

        if ($url == 'https://mpdining.rewardsnetwork.com/') {
            $url = 'https://dining.mileageplus.com/';
        }

        if ($url == 'https://aa.rewardsnetwork.com/') {
            $url = 'https://www.aadvantagedining.com/';
        }

        if ($url == 'https://www.clubodining.com/') {
            $url = 'https://www.idine.com/';
        }

        if ($url == 'https://www.idine.com/') {// iDine is now Neighborhood Nosh
            $url = 'https://neighborhoodnoshrewards.com/';
        }

        $url = preg_replace('/http:/ims', 'https:', $url);

        return $url;
    }

    public static function getNameOptions()
    {
        return [
            "https://mileageplan.rewardsnetwork.com/"           => "Alaska",
            "https://aa.rewardsnetwork.com/"                    => "American", // old
            "https://www.aadvantagedining.com/"                 => "American",
            "https://skymiles.rewardsnetwork.com/"              => "Delta", // old
            "https://skymilesdining.com/"                       => "Delta", // old
            "https://www.skymilesdining.com/"                   => "Delta",
            "https://truebluedining.com/"                       => "JetBlue",
            "https://www.hhonorsdining.com/"                    => "Hilton", // old
            "https://www.hiltonhonorsdining.com/"               => "Hilton",
            "https://www.idine.com/"                            => "Neighborhood Nosh", // old
            "https://neighborhoodnoshrewards.com/"              => "Neighborhood Nosh", // iDine is now Neighborhood Nosh
            "https://priorityclub.rewardsnetwork.com/"          => "IHG Rewards Club", // old
            "https://ihgrewardsclubdining.rewardsnetwork.com/"  => "IHG Rewards Club",
            "https://ihgrewardsdineandearn.rewardsnetwork.com/" => "IHG Rewards Club",
            "https://eataroundtown.marriott.com/"               => "Marriott",
            "https://www.orbitzrewardsdining.com/"              => "Orbitz Rewards", // old
            "https://www.clubodining.com/"                      => "Overstock (Club O Rewards)", // old
            "https://www.rapidrewardsdining.com/"               => "Southwest",
            "https://www.freespiritdining.com/"                 => "Spirit",
            "https://mpdining.rewardsnetwork.com/"              => "United", // old
            "https://dining.mileageplus.com/"                   => "United",
            "https://usairways.rewardsnetwork.com/"             => "US Airways", // old
            "https://www.rewardzonedining.com/"                 => "Reward Zone", // old
        ];
    }

    public static function DisplayName($fields)
    {
        $options = self::getNameOptions();

        return $fields["DisplayName"] . " - " . $options[$fields["Login2"]];
    }

    public static function ProviderName($fields)
    {
        $options = self::getNameOptions();

        return "R.N. " . $options[$fields["Login2"]];
    }

    private function parseReCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);
        $key = $key ?? $this->http->FindSingleNode("//form[@id = 'loginform' or @id = 'sign-in-form']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->AccountFields['Login2'] . 'Login', //$this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function errorsOnMileagePlus()
    {
        $this->logger->notice(__METHOD__);
        // Create Member Profile
        if (strstr($this->http->currentUrl(), 'https://dining.mileageplus.com/new-member.htm')
            && $this->http->FindSingleNode("//h2[contains(text(), 'Create Member Profile')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if (stristr($this->http->currentUrl(), 'https://dining.mileageplus.com/Join')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, the MileagePlus Dining account you are trying to access is closed.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Sorry, the MileagePlus Dining account you are trying to access is closed.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("
                //div[contains(text(), 'Sorry, you have caught us while we are performing scheduled maintenance.')]
                | //p[contains(text(), 'An unexpected error has occurred.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred while processing your request.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'An error occurred while processing your request.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Temporarily down for maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Temporarily down for maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        /*
         * Hang in there.
         *
         * Please contact MileagePlus Dining at (800) 555-5116 for help and reference code: UN01
         *
         * AccountID: 5694659
         */
        if ($this->http->currentUrl() == 'https://dining.mileageplus.com/error?code=UN01') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Network error 28
        if (strstr($this->http->Error, 'Network error 28 - Operation timed out after')) {
            throw new CheckRetryNeededException(3);
        }
    }
}
