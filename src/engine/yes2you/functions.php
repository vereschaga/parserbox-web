<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerYes2you extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.kohls.com/myaccount/dashboard.jsp';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = false;
        $this->http->setHttp2(true);
//        $this->http->SetProxy($this->proxyReCaptcha());
        $this->setProxyGoProxies();
    }

    public function IsLoggedIn()
    {
        return false;
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 10);
        $this->http->RetryCount = 2;

        if (
            $this->http->FindPreg('/s\.eVar73="(.+?)";/')
            && !strstr($this->http->currentUrl(), 'kohls_login')
            && !strstr($this->http->currentUrl(), '/signin.jsp?')
            && $this->http->Response['code'] != 404
        ) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter valid email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter valid email', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        /*if (!$this->http->GetURL('https://www.kohls.com', ['Referer' => 'https://www.kohls.com/?DPSLogout=true'])) {
            if ($this->http->Response['code'] == 0) {
                throw new CheckRetryNeededException(2, 5);
            }

            return $this->checkErrors();
        }
        $this->http->GetURL('https://www.kohls.com/myaccount/signin.jsp');
        $sessionId = $this->http->FindPreg("/\"sessionID\":\"([^\"]+)/");

        if (!$sessionId) {
            return $this->checkErrors();
        }*/

        $currentUrl = $this->http->currentUrl();
        $result = $this->selenium(true);

        if ($result || $this->http->FindSingleNode('//div[@data-testid="guidedSignInAlert-message"]')) {
            return true;
        }

        throw new CheckRetryNeededException(2, 0);
        // TODO: outdated auth, not working anymore

        $headers = [
            'Accept'       => 'application/json, text/javascript, */*; q=0.01',
            'Referer'      => 'https://www.kohls.com/myaccount/signin.jsp',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ];
        $key = $this->http->FindPreg("/\"reCaptchaSecretKey\"\s*:\s*\"([\w\-]+)\"/");
        $data = [
            'email'                => $this->AccountFields['Login'],
            'pw'                   => $this->AccountFields['Pass'],
            'keepMeSignedIn'       => 'true',
            'g-recaptcha-response' => '',
            'nds-pmd'              => empty($nds) ? '{"jvqtrgQngn":{"oq":"1512:411:1512:864:1512:864","wfi":"flap-1","oc":"2501pp0s72219oop","fe":"1512k982+30","qvqgm":"-360","jxe":888186,"syi":"snyfr","si":"si,btt,zc4,jroz","sn":"sn,zcrt,btt,jni","us":"9por14892o0qopno","cy":"ZnpVagry","sg":"{\"zgc\":0,\"gf\":snyfr,\"gr\":snyfr}","sp":"{\"gp\":gehr,\"ap\":gehr}","sf":"gehr","jt":"89s991q1o3714o8","sz":"o76o7n4o82n7101p","vce":"apvc,0,65s43269,2,1;fg,0,vachg-cnary1011,0;ss,0,vachg-cnary1011;zp,5,271,101,vachg-cnary1011;xx,1,0,vachg-cnary1011;ss,0,vachg-cnary1011;xq,16,0,5;xq,4r,1;zz,79,272,101,vachg-cnary1011;xh,34,0;so,36r,vachg-cnary1011;zzf,4p,0,n,p4+12r,49r3+17qr,q72,q84,-28p4r,23nq2,-129n;zzf,418,418,n,ABC;zzf,416,416,n,ABC;zzf,3rp,3rp,n,ABC;zz,q2,n7,18r,cnary1010;zzf,319,3ro,n,41+1s8,793s+1os7,1628,1650,-430q0,4pssn,-126;zzf,3rr,3rr,n,977+25q,31r1+10rs,63r,64r,-2098o,1n8s8,0;zzf,3rr,3rr,n,ABC;zzf,3rs,3rs,n,ABC;zzf,3rr,3rr,n,ABC;zzf,437,437,n,ABC;zz,164,105,17q,cnary1010;fg,3qo,vachg-cnary1011,25;fg,17s,;zzf,2369,2n27,32,3qo+293,20q6+os4,171,r83,-6n9o,np20,22;gf,0,52rq;zz,r4r,251,187,;zp,p1r,26p,138,vachg-cnary1014;zz,82o,26q,138,vachg-cnary1014;fg,43o,vachg-cnary1014,25;zzf,6o,273q,32,21+0,1s01+13o1,1s6,13n0,-o6o8,o7n2,8;fg,226,;zz,nr9,280,148,;zp,114o,27o,12s,vachg-cnary1019;gf,0,9884;"},"jg":"1.j-756138.1.2.cjq1BrgcNC35jUjiDk2J9N,,.CEqVTqeYXJU5mvrLGPxVuhbIp3Nw5M2U22YUrZFu4qoij1F8Gk0NcdW3P_KNeKRolUjYlYbWZEfOp9Z-bg0NIJ5Hgo9yxgb6f2xxQ6r9CDi4INdELuICdcJqJPtWth95md_0v9CCulrLDcNtDPsphCIFmoztlkGbeVCI5uHliecjcf6joGEsDRDrBcB6TurnnuJYhwoTiL4I1HTqmKv4zAtntCP_brODBGQxuBQ7eH5cXw48-xhGa5tt-32OE-Ke"}' : $nds,
        ];
        $this->http->RetryCount = 0;
//        $this->http->PostURL('https://www.kohls.com/myaccount/json/signin/account/lookupjson.jsp', $data, $headers);
        $this->http->PostURL('https://www.kohls.com/myaccount/json/signin/account/signinjson.jsp', $data, $headers);

        $response = $this->http->JsonLog(null, 0);

        /*if (
            (isset($response->{'sec-cp-challenge'}) && $response->{'sec-cp-challenge'} == 'true')
            || $this->http->Response['code'] == 403
        ) {
            $this->selenium($currentUrl, true);
//            $this->http->PostURL('https://www.kohls.com/myaccount/json/signin/account/lookupjson.jsp', $data, $headers);
            $this->http->PostURL('https://www.kohls.com/myaccount/json/signin/account/signinjson.jsp', $data, $headers);
            $response = $this->http->JsonLog();
        }*/

        if (isset($response->message) && $response->message == "Show user a captcha." && $key) {
            $this->http->JsonLog();
            $recaptcha = $this->parseReCaptcha($key);

            if (!$recaptcha) {
                return false;
            }
            $data['remedyChallengeType'] = 'captcha';
            $data['g-recaptcha-response'] = $recaptcha;
//            $this->http->PostURL('https://www.kohls.com/myaccount/json/signin/account/lookupjson.jsp', $data, $headers);
            $this->http->PostURL('https://www.kohls.com/myaccount/json/signin/account/signinjson.jsp', $data, $headers);
        }

        /*
        $data = [
            'email'                => $this->AccountFields['Login'],
            'pw'                   => $this->AccountFields['Pass'],
            'keepMeSignedIn'       => 'true',
            'g-recaptcha-response' => '',
            'nds-pmd'              => empty($nds) ? '{"jvqtrgQngn":{"oq":"1536:482:1536:879:1536:880","wfi":"flap-148694","oc":"q400qo6n8n86q525","fe":"1536k960 30","qvqgm":"-360","jxe":418794,"syi":"snyfr","si":"si,btt,zc4,jroz","sn":"sn,zcrt,btt,jni","us":"1r084ns5s7307pq6","cy":"ZnpVagry","sg":"{\"zgc\":0,\"gf\":snyfr,\"gr\":snyfr}","sp":"{\"gp\":gehr,\"ap\":gehr}","sf":"gehr","jt":"n546s8qns06r2p76","sz":"5349q3345n5nq64q","vce":"apvc,0,5s4pp244,2,1;fg,0,frnepu,0,xvbfx_ybtvaRznvy,0,xvbfx_ybtvaCnffjbeq,0;"},"jg":"1.j-756138.1.2.ScfhkjfJ3ofeBjftrvkYpj,,.994YNaSC8tfkFrItK_dvD4o374HSZBaIktOnrP6iVgiHLm2cHBcZaz5CY9MLN2rp9Lee0pEoS8Sc56DKjbRKJ6Nw7PC0RAtLzKMINPyxNPJPcaXuiamf_4V9kYVZm9jE_3nF8_XvhYqmVoSdL5QUsDrfZ1pOZLVIwPyFiU0X-KXXe8q4avBVYdjMZFod9KLHM4OSZXnPK-XcAWxKnJVE3KwhTPZ7h_KjGgM1ti9wagfwMGqkE34LAXN_JHkGU-dJ"}' : $nds,
        ];
        $this->http->PostURL('https://www.kohls.com/myaccount/json/signin/account/signinjson.jsp', $data, $headers);
        */

        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->statusCode) && $response->statusCode == 200) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($this->http->FindSingleNode('//a[contains(text(), "Sign Out")] | //div[contains(@class, "greeting-container") and not(contains(., \'Sign-In\'))]')) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        $error =
            $response->errorObj->Error
            ?? $response->message
            ?? $this->http->FindSingleNode('//div[@id = "error"]')
            ?? $this->http->FindSingleNode('//div[@id = "alert"]//div[contains(@class, "text")]')
            ?? $this->http->FindSingleNode('//div[@data-testid="guidedSignInAlert-message"]')
            ?? null
        ;

        if ($error) {
            $this->logger->error("[Error]: {$error}");

            if (stripos($error, "Invalid Captcha.") !== false) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 5, $error);
            }

            $this->captchaReporting($this->recognizer);

            // The email address and/or password you entered has an error
            if (
                stripos($error, 'The email address and/or password you entered has an error') !== false
                || stripos($error, 'The password you entered does not match your existing password.') !== false
                || $error == 'Unable to log you in. Please ensure your password is correct.'
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if ($error == 'This user account is locked, please reset your password.') {
                throw new CheckException($error, ACCOUNT_LOCKOUT);
            }

            // To sign in to your account, please reset your password...
            if (stripos($error, 'To sign in to your account, please reset your password') !== false) {
                throw new CheckException('To sign in to your account, please reset your password.', ACCOUNT_INVALID_PASSWORD);
            }
            // Please verify your email & password and try again...
            if (stripos($error, 'Please verify your email & password and try again') !== false) {
                throw new CheckException('Please verify your email & password and try again.', ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $error == 'Currently unable to sign in due to technical issues. Wait a moment and try again.'
                || $error == 'You can\'t sign in right now. Feel Free to try again later or try on the Kohl\'s App (iOS, Android).'
                || $error == 'Currently unable to sign in due to technical issues. Please wait a moment and try again.'
            ) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);

                throw new CheckRetryNeededException(2, 5, $error);
            }

            $this->DebugInfo = $error;

            return false;
        }// if ($error)

        // AccountID: 4865862
        if (
            isset($this->http->Response['code'])
            && $this->http->Response['code'] == 403
            && strstr($this->AccountFields['Pass'], '^')
            && strstr($this->AccountFields['Pass'], '[')
        ) {
            throw new CheckException('Please verify your email & password and try again.', ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($response->branding_url_content) && strstr($response->branding_url_content, "/_sec/cp_challenge/crypto_message-3-")) {
            $this->DebugInfo = 'cp_challenge';

            throw new CheckRetryNeededException(2, 0);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            $this->DebugInfo = 'Access Denied';

            throw new CheckRetryNeededException(2, 0);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Rewards ID
        $number = $this->http->FindPreg('/o\.eVar73="(.+?)";/');

        if ($number == 'no loyalty id') {
            $number = '';
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }

        $this->SetProperty('AccountNumber', $number);

        $this->http->GetURL('https://www.kohls.com/myaccount/json/myinfo/customer_info_details_json.jsp', ['Accept' => '*/*', 'Content-Type' => 'application/json', 'x-requested-with' => 'XMLHttpRequest']);
        $response = $this->http->JsonLog(null, 3, false, "loyaltyId");
        // Full Name
        if (isset($response->payload->profile->customerName->firstName, $response->payload->profile->customerName->lastName)) {
            $this->SetProperty('Name', beautifulName($response->payload->profile->customerName->firstName . ' ' . $response->payload->profile->customerName->lastName));
        }
        // Member since
        $this->SetProperty('MemberSince', date("Y", strtotime($response->payload->profile->createdTimestamp)));

        $this->http->PostURL('https://www.kohls.com/myaccount/json/rewrads/getRewardsTrackerJson.jsp', []);
        $getRewardsTrackerJson = $this->http->JsonLog();

        if (isset($getRewardsTrackerJson->existingEarnTrackerBal, $getRewardsTrackerJson->earnTrackerThreshold)) {
            // Balance - Kohl's Rewards Balance
            $this->SetBalance($getRewardsTrackerJson->existingEarnTrackerBal);
        }// if (isset($getRewardsTrackerJson->existingEarnTrackerBal, $getRewardsTrackerJson->earnTrackerThreshold))

        // AccountID: 5681618, 5773169, 5220628, 7060360
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && isset($getRewardsTrackerJson->name)
            && $getRewardsTrackerJson->name == 'TypeError'
            && $response->payload->profile->loyaltyId === null
        ) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR ...

        $this->http->PostURL('https://www.kohls.com/checkout/v2/json/persistent_bar_components_json_v1.jsp', []);
        $persistent = $this->http->JsonLog();
        // PointsToNextReward - Spend $... to earn your next $5 in Kohl's Rewards.
        $this->SetProperty('PointsToNextReward', '$' . $persistent->purchaseEarnings->kohlsCashEarnings->everyDayKc->spendAwayEverydayNonKcc ?? '');

        $this->http->GetURL('https://www.kohls.com/wallet/json/wallet_json.jsp');
        $data = $this->http->JsonLog(null, 3, false, 'profileLinkedLoyaltyId');

        if (isset($data->payload->kohlsCashBalance)) {
            $this->AddSubAccount([
                "Code"        => 'yes2youCash',
                "DisplayName" => "Kohl’s Cash",
                "Balance"     => $data->payload->kohlsCashBalance,
            ]);

            if ($data->payload->kohlsCashBalance > 0) {
                $this->sendNotification("Kohl’s Cash > 0, refs #20007 // RR");
            }
        }

        // Offers
        $this->http->PostURL('https://www.kohls.com/myaccount/json/dashboard/walletOcpPanelJson.jsp', []);
        $data = $this->http->JsonLog();
        $offers = $data->response->offers ?? [];

        foreach ($offers as $offer) {
            if ($offer->status != 'ACTIVE') {
                continue;
            }

            $this->AddSubAccount([
                "Code"           => 'yes2youCoupons' . $offer->eventName . $offer->barcode,
                "DisplayName"    => $offer->description,
                "Balance"        => null,
                "PromoCode"      => $offer->eventName,
                'BarCode'        => $offer->barcode,
                "BarCodeType"    => BAR_CODE_CODE_128,
                "ExpirationDate" => strtotime('-1 day', $offer->endDate / 1000),
            ]);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // AccountID: 4138286
            if (isset($data->errors[0]->message) && $data->errors[0]->message == 'Service Unavailable') {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function parseReCaptcha($key = null, $action = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode('//form[@action = "?"]//div[@class = "g-recaptcha"]/@data-sitekey');
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        /*
        $postData = array_merge(
            [
                "type"       => "NoCaptchaTask",
                "websiteURL" => $this->http->currentUrl(),
                "websiteKey" => $key,
            ],
            $this->getCaptchaProxy()
        );
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;
        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => "https://www.kohls.com/myaccount/kohls_login.jsp", //$this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        if ($action) {
            $parameters += [
                "version"   => "v3",
                "action"    => $action,
                "min_score" => 0.3,
            ];
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseReCaptchaV2($selenium, $url)
    {
        $this->logger->notice(__METHOD__);
        $key = '6LfIdBYpAAAAACN-AsV8Ek7dH_P8_lBlXUsPiv-C';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $this->logger->debug($selenium->http->currentUrl());
        $parameters = [
            "pageurl"   => $url,
            "proxy"     => $selenium->http->GetProxy(),
            "proxytype" => "HTTP",
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function selenium($auth = false)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;

            /* not working with frames */
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
//            if ($this->attempt == 0) {
//                $selenium->useChromePuppeteer(SeleniumFinderRequest::CHROME_PUPPETEER_103);
//                $selenium->seleniumOptions->addHideSeleniumExtension = false;
//                $selenium->seleniumOptions->userAgent = null;
//            } else {
//                $selenium->useGoogleChrome();
//            }

            $selenium->usePacFile(false);

            $resolutions = [
                //                [1152, 864],
                [1280, 720],
                //                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);
//            $selenium->useCache();
//            $selenium->usePacFile(false);
//            $selenium->keepCookies(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL('https://www.kohls.com/');
            sleep(random_int(1, 4));
            $this->savePageToLogs($selenium);
            $this->slideCaptcha($selenium);

            $account = $selenium->waitForElement(WebDriverBy::xpath("//a[@title='Account']"), 7);
            $this->savePageToLogs($selenium);

            if (!$account) {

                return false;
            }
            $account->click();

            $loginInputXpath = "//input[@id = 'signin-email' or @type = 'email' or @placeholder='Email']";
            $loginBtnXpath = "//button[@data-testid = 'continue-button' or @title = 'Continue']";

            $passInputXpath = "//input[@name = 'signInPW' or @name = 'enterpw']";
            $passBtnXpath = "//button[@type='submit' and contains(text(),'Sign In')] | //button[@title = 'Sign In']";

            $login = $selenium->waitForElement(WebDriverBy::xpath($loginInputXpath), 10);
            $this->savePageToLogs($selenium);

            if (!$login && ($account = $selenium->waitForElement(WebDriverBy::xpath("//a[@title='Account']"), 0))) {
                $account->click();
                $login = $selenium->waitForElement(WebDriverBy::xpath($loginInputXpath), 20);
            }

            $btn = $selenium->waitForElement(WebDriverBy::xpath($loginBtnXpath), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$login || !$btn) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->logger->debug("set login");
            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);

            $closePopupBtn = $selenium->waitForElement(WebDriverBy::xpath('//a[@rel="modal:close"]'), 3);
            $this->savePageToLogs($selenium);

            if ($closePopupBtn) {
                $closePopupBtn->click();
            }

            sleep(random_int(1, 3));
            $this->savePageToLogs($selenium);
            $result = urldecode($this->http->FindSingleNode('//input[@name = "nds-pmd"]/@value'));
            $btn->click();
            $this->overlayWorkaround($selenium, $loginBtnXpath);

            $pass = $selenium->waitForElement(WebDriverBy::xpath($passInputXpath), 7);
            $this->savePageToLogs($selenium);

            if (!$pass) {
                $this->logger->notice("pass not found");
                $login = $selenium->waitForElement(WebDriverBy::xpath($loginInputXpath), 7);
                $btn = $selenium->waitForElement(WebDriverBy::xpath($loginBtnXpath), 0);

                if (!$login || !$btn) {
                    $this->logger->error("something went wrong");
                    $createPW = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'createPW']"), 7);

                    if ($createPW) {
                        throw new CheckException("Email don't match. Please try again.", ACCOUNT_INVALID_PASSWORD);
                    }

                    if ($message = $this->http->FindSingleNode('//div[@data-testid="guidedSignInAlert-message"]')) {
                        $this->logger->error("[Error]: {$message}");

                        if ($message == 'This user account is locked, please reset your password.') {
                            throw new CheckException($message, ACCOUNT_LOCKOUT);
                        }

                        $this->DebugInfo = $message;
                    }

                    return false;
                }

                $this->logger->debug("set login");
                $login->clear();
                $login->sendKeys($this->AccountFields['Login']);

                $this->overlayWorkaround($selenium, $loginBtnXpath);

                $btn->click();
                sleep(5);
                $pass = $selenium->waitForElement(WebDriverBy::xpath($passInputXpath), 10);
                $this->savePageToLogs($selenium);
            }

            if (!$pass) {
                $this->logger->notice("pass not found, second attempt");
                $login = $selenium->waitForElement(WebDriverBy::xpath($loginInputXpath), 7);
                $btn = $selenium->waitForElement(WebDriverBy::xpath($loginBtnXpath), 0);

                if (!$login || !$btn) {
                    $this->savePageToLogs($selenium);
                    $this->logger->error("something went wrong");

                    return false;
                }

                $this->logger->debug("set login");
                $login->clear();
                $login->sendKeys($this->AccountFields['Login']);
                $btn->click();
                sleep(5);
                $pass = $selenium->waitForElement(WebDriverBy::xpath($passInputXpath), 10);
                $this->savePageToLogs($selenium);
            }

            if ($auth === true) {
                $btn = $selenium->waitForElement(WebDriverBy::xpath($passBtnXpath), 0);

                if (!$pass || !$btn) {
                    $this->logger->notice("pass not found, third attempt");

                    if (
                        !$this->http->FindSingleNode($loginInputXpath . "/@name")
                        && $this->http->FindSingleNode('//h1[@class = "heading-05" and contains(text(), "Join Kohl\'s Rewards")]')
                    ) {
                        throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    return false;
                }
                $keepMeSignedIn = $selenium->waitForElement(WebDriverBy::xpath('//label[@for = "checkbox-keepMeSignedIn"]'), 0);

                if ($keepMeSignedIn) {
                    $keepMeSignedIn->click();
                }
                $this->logger->debug("set pass");
                $pass->click();
                $pass->sendKeys($this->AccountFields['Pass']);
                $this->savePageToLogs($selenium);
                $this->logger->debug("click btn");
                $btn->click();
                // //div[@id = 'error'] | //div[contains(@class, 'err')] | //div[@id = 'alert']//div[@class = 'text']
                $selenium->waitForElement(WebDriverBy::xpath("//span[@class = 'first-name' and not(contains(text(), 'Account'))] | //div[contains(@class, \"greeting-container\") and not(contains(., 'Sign-In'))]"), 10);
                $result = true;
                $this->savePageToLogs($selenium);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->savePageToLogs($selenium);
        } catch (NoSuchDriverException | TimeOutException | Facebook\WebDriver\Exception\TimeoutException | Facebook\WebDriver\Exception\UnknownErrorException | WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } catch (WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            if (str_contains($e->getMessage(), 'JSON decoding of remote response failed')) {
                $retry = true;
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(2, 0);
            }
        }

        return $result;
    }

    private function overlayWorkaround($selenium, $loginBtnXpath)
    {
        $this->logger->notice(__METHOD__);

        if ($selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'sec-overlay']"), 7)) {
            $this->savePageToLogs($selenium);
            // "I'm not a robot"
            if ($iframe = $selenium->waitForElement(WebDriverBy::xpath("//iframe[@id = 'sec-cpt-if']"), 0)) {
                $selenium->driver->switchTo()->frame($iframe);

                if ($captcha = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'robot-checkbox']"), 5)) {
                    $this->savePageToLogs($selenium);
                    $selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'progress-button']"), 2);
                    $this->savePageToLogs($selenium);
                    /*
//                      $captcha->click();
//                      $selenium->driver->executeScript('document.querySelector(\'#robot-checkbox\').click()');
                        $this->savePageToLogs($selenium);
//                      $selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'progress-button']"), 2)->click();
                        $this->savePageToLogs($selenium);
                        $this->logger->debug("delay -> 15 sec");
                    */
                }// if ($captcha = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'robot-checkbox']"), 5))
                $selenium->driver->switchTo()->defaultContent();
                $this->logger->debug("click by checkbox");
                $selenium->driver->executeScript('document.querySelector(\'#sec-cpt-if\').contentWindow.document.querySelector(\'#robot-checkbox\').click()');
                sleep(2);
                $this->savePageToLogs($selenium);
                $this->logger->debug("click by 'Proceed' btn");
                $selenium->driver->executeScript('document.querySelector(\'#sec-cpt-if\').contentWindow.document.querySelector(\'#progress-button\').click()');
                sleep(2);
                $this->savePageToLogs($selenium);
            }// if ($iframe = $selenium->waitForElement(WebDriverBy::xpath("//iframe[@id = 'sec-cpt-if']"), 0))

            $selenium->waitFor(function () use ($selenium) {
                return !$selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
            }, 80);
            $this->savePageToLogs($selenium);

            $btn = $selenium->waitForElement(WebDriverBy::xpath($loginBtnXpath), 3);
            $this->savePageToLogs($selenium);
            $btn->click();

            /*$captcha = $this->parseReCaptchaV2($selenium, 'https://www.kohls.com/myaccount/signin.jsp');

            if ($captcha) {
                $selenium->driver->executeScript('
                var msg = {"captcha_response": "' . $captcha . '"};
                window.parent.postMessage(JSON.stringify(msg), "*");');
                sleep(5);
            }*/
        }
    }

    private function slideCaptcha($selenium = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$selenium) {
            $selenium = $this;
        }
        $mover = new MouseMover($selenium->driver);
        $mover->logger = $this->logger;
        $mover->duration = 30;
        $mover->steps = 10;
        $mover->enableCursor();
        $counter = 0;

        if (!$slider = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "signin-email" or @type = "email" or @placeholder="Email"]'), 0)) {
            return;
        }

        do {
            if ($counter++ > 2) {
                /*
                $this->sendNotification('refs #23019 slider captcha not solved // BS');
                */

                break;
            }
            $this->savePageToLogs($selenium);
            $mover->moveToElement($slider);
            $mouse = $selenium->driver->getMouse()->mouseDown();
            usleep(500000);
            $mouse->mouseMove($slider->getCoordinates(), 200, 0);
            usleep(500000);
            $mouse->mouseUp();
            $this->savePageToLogs($selenium);
            sleep(2);
        } while ($slider = $selenium->waitForElement(WebDriverBy::cssSelector('div.cpt-drop-btn'), 0));
    }
}
