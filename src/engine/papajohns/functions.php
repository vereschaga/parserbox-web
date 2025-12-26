<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerPapajohns extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private bool $www2 = false;

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (empty($this->AccountFields['Login2']) || $this->AccountFields['Login2'] == 'USA') {
            $this->http->setHttp2(true);
            $this->KeepState = false;

            if ($this->attempt == 3) {
                $this->setProxyGoProxies();
            } elseif ($this->attempt == 2) {
                $this->http->SetProxy($this->proxyDOP());
            } else {
                $this->setProxyGoProxies();
            }

            $userAgentKey = "User-Agent";

            if (empty($this->State[$userAgentKey]) || $this->attempt > 0) {
                $this->http->setRandomUserAgent();
                $agent = $this->http->getDefaultHeader("User-Agent");

                if (!empty($agent)) {
                    $this->State[$userAgentKey] = $agent;
                }
            } else {
                $this->http->setUserAgent($this->State[$userAgentKey]);
            }
        }
    }

    public function IsLoggedIn()
    {
        switch ($this->AccountFields['Login2']) {
            case 'UK':
                // todo: need to realize, now works via TAccountCheckerPapajohnsSelenium
                return false;

                break;

            case 'USA':
            default:
                $this->http->RetryCount = 0;
                $this->http->GetURL('https://www.papajohns.com/order/account/edit-profile', [], 20);
                $this->http->RetryCount = 2;

                if ($this->loginSuccessful()) {
                    return true;
                }

                break;
        }

        return false;
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        $fields["Login2"]["Options"] = [
            ""    => "Select your region",
            "UK"  => "United Kingdom",
            "USA" => "United States",
        ];
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && (strstr($properties['SubAccountCode'], 'papajohnsUSARewardsBalance') || strstr($properties['SubAccountCode'], 'papajohnsUSAMyPapaDough'))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        } else {
            return parent::FormatBalance($fields, $properties);
        }
    }

    /*
    public static function GetAccountChecker($accountInfo)
    {
        if ($accountInfo['Login2'] == 'UK') {
            require_once __DIR__ . "/TAccountCheckerPapajohnsSelenium.php";

            return new TAccountCheckerPapajohnsSelenium();
        } else {
            return new TAccountCheckerPapajohns();
        }
    }
    */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        switch ($this->AccountFields['Login2']) {
            case "UK":
                $this->http->SetProxy($this->proxyReCaptchaIt7());
                $this->http->FilterHTML = false;
                $this->http->GetURL("https://www.papajohns.co.uk/");

                if ($this->http->currentUrl() === 'https://www2.papajohns.co.uk/') {
                    $this->www2 = true;
                    $this->seleniumFirstUrl = "https://www2.papajohns.co.uk/signin?return=papa-rewards";
                    return $this->selenium();
                }

                if (!$this->http->ParseForm("aspnetForm")) {
                    if ($this->http->FindSingleNode('//a[@class="accountBtn" and @href="#rmenu"]/@class')) {
                        $this->www2 = true;
                        $this->seleniumFirstUrl = "https://www.papajohns.co.uk/signin?return=papa-rewards";
                        return $this->selenium();
                    }
                    return $this->checkErrors();
                }

                $this->http->SetInputValue('ctl00$ScriptManager1', 'ctl00$_objHeader$upHeaderSummary|ctl00$_objHeader$lbLoginRegisterItem');
                $this->http->SetInputValue('__EVENTTARGET', 'ctl00$_objHeader$lbLoginRegisterItem');
                $this->http->SetInputValue('__ASYNCPOST', 'true');

                $form = $this->http->Form;
                $this->http->PostForm();
                sleep(2);

                $this->http->Form = $form;

//                if (!$this->http->ParseForm("aspnetForm"))
//                    return $this->checkErrors();
//                $this->http->SetFormText("&__ASYNCPOST=true&__EVENTARGUMENT=&__EVENTTARGET=ctl00%24_objHeader%24lbSignIn&__EVENTVALIDATION={$this->http->Form['__EVENTVALIDATION']}&__VIEWSTATE={$this->http->Form['__VIEWSTATE']}&__VIEWSTATEGENERATOR=CA0B0334&ctl00%24ScriptManager1=ctl00%24_objHeader%24upLogin%7Cctl00%24_objHeader%24lbSignIn&ctl00%24_objHeader%24chkEmailUpdates=on&ctl00%24_objHeader%24ddlRegTitle=%20&ctl00%24_objHeader%24txtEmail=&ctl00%24_objHeader%24txtPassword=&ctl00%24_objHeader%24txtRegConfirmPassword=&ctl00%24_objHeader%24txtRegContactNumber=&ctl00%24_objHeader%24txtRegEmail=&ctl00%24_objHeader%24txtRegFirstName=&ctl00%24_objHeader%24txtRegPassword=&ctl00%24_objHeader%24txtRegSurname=&ctl00%24cphBody%24txtPostcode=&ctl00_ScriptManager1_HiddenField=", "&");

                $this->http->setDefaultHeader("X-MicrosoftAjax", "Delta=true");
                $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
                $this->http->setDefaultHeader("Content-Type", "application/x-www-form-urlencoded; charset=utf-8");
                $this->http->setDefaultHeader("Accept", "*/*");

                $this->http->SetInputValue('__EVENTTARGET', 'ctl00$_objHeader$lbSignIn');
                $this->http->SetInputValue('ctl00$ScriptManager1', 'ctl00$_objHeader$upLogin|ctl00$_objHeader$lbSignIn');

                $state = $this->http->FindPreg("/__VIEWSTATE\|([^|]+)/");
                $EVENTVALIDATION = $this->http->FindPreg("/__EVENTVALIDATION\|([^|]+)/");

                if (!$state || !$EVENTVALIDATION) {
                    return false;
                }

                $this->http->SetInputValue('__VIEWSTATE', $state);
                $this->http->SetInputValue('__EVENTVALIDATION', $EVENTVALIDATION);
                $this->http->SetInputValue('ctl00$_objHeader$txtEmail1', $this->AccountFields['Login']);
                $this->http->SetInputValue('ctl00$_objHeader$txtPassword', $this->AccountFields['Pass']);

                $captcha = $this->parseReCaptcha($this->http->FindPreg("/sitekey'\s*:\s*'([^']+)/"));

                if ($captcha === false) {
                    if ($this->http->FindPreg("/updatePanel/")) {
                        return true;
                    }

                    return false;
                }

                $this->http->SetInputValue('g-recaptcha-response', $captcha);

                break;

            default:
                $this->http->GetURL("https://www.papajohns.com/order/papa-rewards");
//                throw new CheckException(PROVIDER_CHECKING_VIA_EXTENSION_ONLY, ACCOUNT_PROVIDER_DISABLED);

                $this->http->RetryCount = 0;

//                if (!$this->http->ParseForm("header-account-sign-in-form")) {
                if ($this->http->Response['code'] != 200) {
                    return $this->checkErrors();
                }

                $this->http->Form = [];
                $this->http->FormURL = 'https://www.papajohns.com/order/signin';
                $this->http->SetInputValue("user", $this->AccountFields['Login']);
                $this->http->SetInputValue("pass", $this->AccountFields['Pass']);
                $this->http->SetInputValue("remember_me", "true");

                break;
        }// switch ($this->AccountFields['Login2'])

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case "UK":
                break;

            default:
                //# Technical difficulties
                if ($message = $this->http->FindSingleNode("//title[contains(text(),'Sorry, we are down for technical difficulties')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                //# Scheduled Maintenance
                $this->CheckError($this->http->FindSingleNode("//title[contains(text(),'Sorry, we are down for scheduled maintenance.')]"), ACCOUNT_PROVIDER_ERROR);
                //# Internal server error
                if ($this->http->FindSingleNode("//h2[contains(text(), 'Internal server error')]")
                    || $this->http->FindPreg("/(An error occurred while processing your request)/ims")) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                // retries
                if ($this->http->Response['code'] == 401) {
                    throw new CheckRetryNeededException(2, 7, self::CAPTCHA_ERROR_MSG);
                }

                // maintenance
                if ($message = $this->http->FindSingleNode('//h1[contains(text(), "SCHEDULED MAINTENANCE")]/following-sibling::h3[contains(text(), "Papa John\'s Online Ordering can not take your orders at this time due to scheduled maintenance.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Seems everyone’s craving Papa John’s right now. Thanks for your patience. We’ll have you back to ordering soon so you can enjoy a hot, delicious pizza.
                if ($message = $this->http->FindSingleNode('//p[contains(text(), "Seems everyone’s craving Papa John’s right now. Thanks for your patience. We’ll have you back to ordering soon so you can enjoy a hot, delicious pizza.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindSingleNode('//p[contains(text(), "Papa John’s apologizes for the inconvenience. Our goal is to provide the best quality customer experience.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // Papa John's Online Ordering can not take your order at this time due to technical difficulties.
                if ($this->http->FindSingleNode("//h1[contains(text(), 'Technical Difficulties')]")
                    || $this->http->Response['code'] == 403) {
//                    throw new CheckRetryNeededException(4);
                }

                break;
        }// switch ($this->AccountFields['Login2'])

        return false;
    }

    public function Login()
    {
        if ($this->www2) {
            if ($message = $this->http->FindSingleNode('//p[contains(@class, "errorText")]')) {
                $this->logger->error("[Error]: {$message}");

                if ($message == 'The email address / password you entered were not found') {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            }

            return true;
        }

        sleep(1);
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        if ($this->AccountFields['Login2'] != 'UK') {
//            $this->sendSensorData();
            $this->selenium();

            if ($this->loginSuccessful()) {
                return true;
            }

            $this->http->Form = $form;
            $this->http->FormURL = $formURL;
        }

        if ($this->AccountFields['Login2'] != 'USA'
            && !$this->http->PostForm() && $this->http->Response['code'] != 401
        ) {
            if (in_array($this->AccountFields['Login'], [
                'walton1234567@yahoo.com',
                'keatzo@yahoo.com',
                'mark_andrew_gross@hotmail.com',
                'oughgh@gmail.com',
                'mandijojohn@me.com',
            ])) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        switch ($this->AccountFields['Login2']) {
            case "UK":
//                if ($this->http->FindSingleNode("//a[contains(@href, 'sign-out')]/@href"))
//                if ($this->http->FindPreg("/pageRedirect\|\|\%2f\|/")) {

                if ($message = $this->http->FindSingleNode('//div[@id = "ctl00__objHeader_pnlLoginError"]/p')) {
                    $this->logger->error("[Error]: {$message}");

                    if ($message == 'The email address / password you entered were not found') {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                if ($this->http->FindPreg("/updatePanel/")) {
                    $this->http->GetURL("https://www.papajohns.co.uk/");

                    return true;
                }

                if ($error = $this->http->FindPreg("/site-error\.aspx/")) {
                    $this->logger->error($error);
                }

                break;

            default:
                if ($this->loginSuccessful()) {
                    return true;
                }
                $this->checkLoginErrors();

                // Re-enter your password and verify that you are human.
                if ($this->http->FindPreg('/Re-enter your password and verify that you are human/')) {
                    $this->http->Form = $form;
                    $this->http->FormURL = $formURL;
                    $key = '6LfetwgTAAAAAOsoRJN6IRyNXt-xct4aBaFRtEXN';
                    $captcha = $this->parseReCaptcha($key);

                    if ($captcha === false) {
                        return false;
                    }
                    $this->http->SetInputValue("g-recaptcha-response", $captcha);
                    $this->http->SetInputValue("siteKey", $key);

                    if (!$this->http->PostForm()) {
                        return $this->checkErrors();
                    }

                    if ($this->loginSuccessful()) {
                        return true;
                    }
                    $this->checkLoginErrors();
                }

                if ($this->http->FindSingleNode('//p[contains(text(), "Account security enhancements require that you change your password.")]')) {
                    $this->throwProfileUpdateMessageException();
                }

                $this->http->GetURL("https://www.papajohns.com/order/sign-in");
                $this->http->RetryCount = 0;

                if ($this->http->Response['code'] != 200) {
                    return $this->checkErrors();
                }

                $this->http->Form = [];
                $this->http->FormURL = 'https://www.papajohns.com/order/signin';
                $this->http->SetInputValue("user", $this->AccountFields['Login']);
                $this->http->SetInputValue("pass", $this->AccountFields['Pass']);
                $this->http->SetInputValue("remember_me", "true");
                $this->http->PostForm();

                if ($this->loginSuccessful()) {
                    return true;
                }

                $this->checkLoginErrors();

                break;
        }// switch ($this->AccountFields['Login2'])

        return $this->checkErrors();
    }

    public function checkLoginErrors()
    {
        $this->logger->notice(__METHOD__);
        // Invalid credentials
        if ($message = $this->http->FindPreg('#"invalidCredentialsMessage": "(Sorry, the e-mail/password combination didn\'t match what we have on file. Please try again.)"#')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Create a New Password
        if ($this->http->FindPreg('#^/order/password\-reset\?key#')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode('//span[@id = "header-recaptcha_error_msg" and normalize-space() != ""] | //label[@id and contains(@class, "error") and normalize-space() != ""] | //p[@class="error-description"]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Sorry, the e-mail/password combination didn\'t match what we have on file. Please try again.'
                || $message == 'Please enter a valid email address. example@yourdomain.com'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Looks like there’s an issue. Please check your connection')) {
                throw new CheckRetryNeededException(3);
            }

            $this->DebugInfo = $message;
        }
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
            case "UK":
                if ($this->www2) {
                    // You've earned X Reward points
                    $this->SetBalance($this->http->FindSingleNode('//span[@id = "userPointsD" and normalize-space(.) != ""] | //div[contains(@class, "popupPointsCard") and not(contains(., "}"))]//p[contains(@class, "pointsNr")]/text()[1]'));
                    // Welcome back, Name
                    $this->SetProperty("Name", beautifulName(
                        $this->http->FindSingleNode('//h4[@id = "userName"]', null, false, "/Welcome back, (.+)/")
                        ?? $this->http->FindSingleNode('//span[contains(text(), "Good ")]/b')
                    ));

                    $lastActivity = $this->http->FindSingleNode('//div[@class = "pointsHistory"]/table/tbody/tr[1]/td[1]');
                    $this->SetProperty('LastActivity', $lastActivity);

                    $exp =
                        // Valid until XX/XX/XXXX
                        $this->http->FindSingleNode('//span[@id = "userPointsExpiryValue"]', null, false, '/(\d\d\/\d\d\/\d{4})/')
                        // Your Reward Points & Papa Dough expire on 22nd May 2025
                        ?? $this->http->FindSingleNode('//p[@class = "expiryText" and not(contains(., "}"))]', null, false, '/expire on (.+)/')
                    ;

                    if (isset($exp) && strtotime($exp)) {
                        $this->SetProperty("AccountExpirationWarning", "{$this->AccountFields['DisplayName']} on their website state that the balance on this award program is due to expire on {$exp}");
                        $this->SetExpirationDate(strtotime($exp));
                    }
                    return;
                }

                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'ctl00__objHeader_pnlLoggedInUserTitle']/span/span", null, true, "/Hi\s*([^\!<]+)/ims")));
                // Balance - table "Your Reward History" -> first row -> field "Balance"
                $this->http->GetURL("https://www.papajohns.co.uk/my-papa-rewards.aspx");

                if (
                    !$this->SetBalance($this->http->FindSingleNode("//span[@id = 'ctl00_cphBody_rptPoints_ctl00_lblPointsTotal']", null, true, "/([\d\.\,]+)/ims"))
                    // AccountID: 5416718
                    && count($this->http->FindNodes("//table[contains(@class, 'nutritionalTable')]//tr")) == 2
                ) {
                    $this->SetBalanceNA();
                }

                // Expiration Date
                $this->http->GetURL("https://www.papajohns.co.uk/my-previous-orders.aspx");
                $nodes = $this->http->XPath->query("//div[@id='ctl00_cphBody_divPreviousOrders']//table//tr[not(tr) and count(td) > 1]");
                $maxDate = 0;

                foreach ($nodes as $node) {
                    $lastActivity = $this->http->FindSingleNode("td[@class='orderDate']", $node);
                    $this->logger->debug("Last Activity: {$lastActivity}");
                    $expDate = strtotime($lastActivity, false);

                    if ($expDate && $expDate > $maxDate) {
                        $maxDate = $expDate;
                        $this->SetExpirationDate(strtotime('+6 month', $maxDate));
                        $this->SetProperty("LastActivity", $lastActivity);
                        $this->SetProperty("AccountExpirationWarning", "{$this->AccountFields['DisplayName']} state the following on their website: <a target=\"_blank\" href=\"https://www.papajohns.co.uk/terms-and-conditions/papa-rewards.aspx\">Points will expire 6 months after the customers last order date</a>.
 <br><br>We determined that last time you had account activity with Papa John's Pizza on {$lastActivity}, so the expiration date was calculated by adding 6 months to this date.");
                    }
                }

                break;

            default:
                // Balance - 0/75 Points
                $this->SetBalance($this->http->FindSingleNode('(//p[contains(text(), "Points")]/following-sibling::strong)[1]', null, false, "/([\d\.\,]+)\/[\d\.\,]+/i"));

                if ($myPapaDough = $this->http->FindSingleNode('(//p[contains(text(), "My Papa Dough")]/following-sibling::strong)[1]')) {
                    $this->AddSubAccount([
                        "Code"        => "papajohnsUSAMyPapaDough",
                        "DisplayName" => "My Papa Dough",
                        "Balance"     => $myPapaDough,
                    ]);
                }

                $this->http->GetURL('https://www.papajohns.com/order/account/edit-profile');

                $customerToken = $this->http->FindPreg("/customerToken\s*=\s*'([^\']+)/");
                $customerId = $this->http->FindPreg("/customerId\s*=\s*'([^\']+)/");

                if ($customerToken && $customerId) {
                    $headers = [
                        "Accept"           => "application/json, text/plain, */*",
                        "pj-authorization" => $customerToken,
                        "Content-Type"     => "application/json",
                    ];

                    $this->http->GetURL("https://www.papajohns.com/api/v2/customers/{$customerId}/simple", $headers);
                    $response = $this->http->JsonLog();
                    // Name
                    $this->SetProperty("Name", beautifulName(($response->data->firstname ?? null)." ".($response->data->lastname ?? null)));
                } else {
                    // Name
                    $this->SetProperty("Name", beautifulName("{$this->http->FindSingleNode("//input[@id='firstname']/@value")} {$this->http->FindSingleNode("//input[@id='lastname']/@value")}"));
                }

                $this->http->GetURL('https://www.papajohns.com/order/account/my-papa-rewards');
                // 0/75 Points
                $this->SetBalance($this->http->FindSingleNode('//div[@id = "popup-user"]//p[contains(text(), "Points")]/following-sibling::strong', null, false, "/([\d\.\,]+)\/[\d\.\,]+/i"));

                $customerToken = $this->http->FindPreg("/customerToken\s*=\s*'([^\']+)/");
                $customerId = $this->http->FindPreg("/customerId\s*=\s*'([^\']+)/");

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    // AccountId: 3577859
                    $this->SetWarning($this->http->FindSingleNode("//p[contains(text(),'We are having trouble getting your rewards information. Try again later.')]"));

                    if ($this->http->FindSingleNode("//p[contains(text(), 'By accepting the terms and conditions, you')]")) {
                        $this->throwAcceptTermsMessageException();
                    }
                }

                // My Papa Dough
                $this->SetProperty('CombineSubAccounts', false);
                if ($myPapaDough = $this->http->FindSingleNode('//div[@id = "popup-user"]//p[contains(text(), "My Papa Dough")]/following-sibling::strong')) {
                    $this->AddSubAccount([
                        "Code"        => "papajohnsUSAMyPapaDough",
                        "DisplayName" => "My Papa Dough",
                        "Balance"     => $myPapaDough,
                    ]);
                }

                // 27 more to get $10.00 of Papa Dough
                $this->http->GetURL("https://www.papajohns.com/api/1/services/rewards-points-content.json");
                $response = $this->http->JsonLog();

                if (isset($response->data->pointsGoal)) {
                    $this->SetProperty('PointsNextReward', $response->data->pointsGoal - $this->Balance);
                }

                // Expiration Date
                $this->logger->info('Expiration Date', ['Header' => 3]);

                if (!$customerId || !$customerToken) {
                    $this->logger->error("customerId / customerToken not found");

                    return;
                }

                $headers = [
                    "pj-client-app"    => "rwd-ng",
                    "pj-authorization" => $customerToken,
                ];
                $this->http->GetURL("https://www.papajohns.com/api/v4/loyalty/history/activity/{$customerId}", $headers);
                $response = $this->http->JsonLog();
                $lastActivity = $response->data->events[0]->date ?? null;
                $this->logger->debug("Last Activity: {$lastActivity}");

                if ($lastActivity) {
                    $data = floor($lastActivity / 1000);
                    $this->SetExpirationDate(strtotime('+12 month', $data));
                    $this->SetProperty("LastActivity", date("m/d/Y", $data));
                }

                break;
        }// switch ($this->AccountFields['Login2'])
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        switch ($this->AccountFields['Login2']) {
            case "UK":
                $arg['CookieURL'] = 'http://www.papajohns.co.uk/';

                break;

            default:
                $arg['CookieURL'] = 'https://www.papajohns.com/';

                break;
        }

        return $arg;
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//input[@id='recapthca-site-key']/@value");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            //            "pageurl" => 'https://www.papajohns.com/order/papa-rewards',
            "pageurl" => 'https://www.papajohns.co.uk/',
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("
                //a[contains(@href, 'signout')]/@href
                | //a[@id = 'signoutbutton']
                | //a[@id = 'signoutbutton-header-nav-utility-mobile']
                | //a[contains(text(), \"Hi, \")]
            ")
        ) {
            return true;
        }

        return false;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            return $this->checkErrors();
        }
        $this->http->NormalizeURL($sensorDataUrl);

        $this->http->setCookie('_abck', '68A2E6CADF304A6AFE3B8A79CCDAB940~0~YAAQFeHdF1rTnFiNAQAAZE7hrAtsSwfmL4ZcBvWqddEzku4oXZAOi5mYIggCLq97tsGx2XlS48Aj3ubet1k10rU8mmR3uuxPCFi30fSpf6QhEYozwSdNA9KyxCsSapx9ZfO2iUlZlXZwZu0tCjWQhw4ObEFKn5GLvAQt+GYtZjM/c3RfiRWxM742nRGHQNGW/l+3NYtvhkKfCTWsEoPeNfWnpML7O+a6eyymPKBfdd3J5g7UOXXS1OXSIUVpdw4IikK829LtHlXODN4tTljzS2LuLNKzkOeJfOiaiy+bbECzr4+7TTjPx4wO3JhgvkDoa50JJHIhiVsbUGDhxUqGDKQYqXyO0LKJntxl6GXFwxOS29khweOlsuByOF4xeyEjsCWZKRW4YjSCl5LwJpZYuFl1g25W5QOj9AhZSD7RyofWuwSICLdU5CELfkga2gZkDr1qxBDDgfWMIVBE~-1~-1~-1', '.papajohns.com');

        return false;

        $formUrl = $this->http->FormURL;
        $form = $this->http->Form;

        $sensorData = [
            // 0
            '2;4539973;3687238;18,0,0,0,2,0;KL,d,G}/3-|>w(}9Vo ILY>v$DJ={.jH8fBY-Gozz&;[|BF_oi<zIG&W*PUNoaqe.*54G}L|2=4q`j[7[4vkM+mP M]Ci$c)]sHiLfBk)0=nAl#DlUH{?r*1a;GX?/s]ka^)~>t<t{0cMl#Q2Zzex8*o>#:^85pzPsrRAsD C[tSKisU7l6r8bFQ_{{ou|CDp> wj4r>yxA@+IgO5 j~msx|K5Kg_]z9i67o[IA_ETute2GO$#k!j7+#aWl;7,I.i/,b[wqqp;55)H]IfgSmt#~)QX<]WU+B6ajb[20z`~NL_-YO8F?ff~)+ji>nzoLhDFN29.$jU/]bcE|wUreVM7d|wh9p.3yO/w}hWbvYV!PntE&!2OIn$?1^hQJ%.D{=A65/;=O7)iR^m?(83Sj/El B=@!a}2pJS&{ZA_|!nA2>UK-44GuOV8YrjQ?(H4pORLaY 0<?Czq|JCv*8RxDlJg&!AE~!Fc@;Ikf%)Vb]?8b,s`!zOvQ#^lLaseJvK;~z7`[<~#hR`4k%P<Th@m*?aQPa+$tFnj^zs4Z``mH moAa|x#K%F+kOnYi=#`Edc`>&^`<`OIuvoG%$0AB<KHI)cRq/8BqH+btRJxEtOPylpUoZ;?)![Dxp?/Sy$lCQQX}>pup@v1eZiI(9V.F4gYD2rf:mo:$HYk#J(T3fNn1@Y4uBNxC>otYXC1MC`hV>VR$T9jRw]0hm kbw(iH>x}xtcVse,871:K{M8 wN>[4IePOT*lE4%,!8 j]HUvejs4(*&dJj6kUu3D%CHeEY m*/00paXFceUJba<t_ooO}Bit]M8cs8jHf0gR?a)n`-q^ .=ynEV=(-(:0%ifD*6Zo=i,3R3+d?Lk}ZhMR;AP?3CR(2OE(Qq|$ZU2XM9.0%W|v$nUk$U.3gXkY(a/rszA#FKaY/y(ua Fqo$ ~,Nj[/A1f,ydh9AmumL{o_{Rh]5@p4sm<GFK@I)3Ue[8KuI.Rwi6:@ damx~zWBrFSha$&|k<f<ZPN{4]$E4@,Ka]S]VjrC3cr;ZE$eD=X}1#p~hE,Q[/G)LUrUh sd[0qUBn0+3`WDsnt#J7x0C8L6H9|e^:*Itq(oo?a,YPW&NV^OF%:]AUV58PKA65Y8(sxiU^6lDyU8& eh9Z~t1gS|oa[y0UITr/WU%Bob*DntSAO*;d/tkD{*^f]C8%v>I9b%>+BJt,7t4^kAj<@jIR,rHgCYG[>l/Wj_%AGf1,BFBmH1*ikTPlfr ^P^V1k;yd2r2Zu4vo_by+@!3vzaj8o(R:#;.SPveL+/K#upFgSYI3)zl8~FY-,T][Jk%`8zh4X--+6[_QyAzXhdaWVWkE/Z$y[0S/NWJ9$]p1/h|z1ZO$9qkI+OOR;Q%~-42il+x dM?^fzMlU*`NfMn~;0-(1J!74Y&]yb$?)|lf0ruuB}qx,%6B`f5xsCy!1c1~rP&r;[8;v>JfFqqj((Kn(/*`HQ]p%Z|pjzf=.(yy=|0NyjjE(<Xc:Pvdq>2LR@ytkwk4qd>ngLb!eBKNpoRyJ[x#6~az(zjTVqp%5J:E/u3L>};2ixZnHb(5FAhif2)YRae#~ .=xpOd_>N^C9p=q:hx7%:1dR^XQirMzzu$w3.cuN*DsG2?1G{9WO9swQ#lAigteY#H.;k.0&FnFiHuZ/[wMEBvR4$Pj&K%7Yj(!,W!aa$yP5(D.fD]..7cDB9XmCX;n9Bfd^5v&&KxdwPo((|u0yXJ-=?YqMqi-Wl?4F+YhyLpd?3sjQ*V2BQ0txv;>Swb&e8gbg]6RISf38,t-jNwYY$aIYiKyBsc(s6a{0t`~.oM:~/]8a,%b%B:t(-zR&tb,oCj!M8!BS;!#^k_tLL3q&WV7QMN~mDK0kCt,bPD[-iKMFIBXMD(o?hS6t2nHEyqVG!h1M:*a5$oZ1#S$p+~d<`cZ#H2fdJJ,*gCu&^CE50bI)~?^dcVHvEUZ}L<voh h^A5Oy-/bG6 ~:^a;^)`]9Z<MxI%sDCUQSA?b8ycdigZq4|Af.}|@[Ux0i?.*obv,8$uN)7CND>Ih>boAQvstQ*b9&OKFiq55B.3fGdsiEFMu+T!JRw$5wV{9qNUJc@a^K54xe$9~qgKaIEA/[|2K*w.O8q*cnL?uo/=!e-OBKcV(Fz>(@zx;oEKEQ9YOi}KTss6xj{UVDetEwQza1]a%tyCk_N$%AUv.8]{Y_%ZPRx#et=O^JpBleyOolXk%94F4y0D?HyFLQ}4q4#pP{[wJLF0SEY!~ecu~ YqxyT/E=_)eIE|ZCyQY@7>7IYl{S3Z)gr>?5 qk/k mG2]9!mF2DK3xvlN|Lu].d+S]fKrfYnS2riujQBiUL:a%Rc7T1kQ{g(`r$kU##bPElym^FKvKE:wiK2p6rNv<`Z-s1D?.J%l`I|>+@ip^p6`L4}*fJ*L$NxLi*#HmUy}.wVx3~xVNc35}A^Asi=D1sL-RfCzpSHGP2JNS>NH.@#&zU>P/7qLLVQSycD_^^P=PH`@Z<^xLM&q+c<3+rJ=yvwx{LPFuTF8U8:}g6OC<jEQZ#q_9>!.<4ri;[}6^:;)?Gj FP[8R>k|t.Jjoiu6zF9~K14P+lhe%2auBTnd g7Nb9jv_mm|$0+%aBQa^ycLFoDo6PkewTRK;dx:<ULi{>y!gBaj:_>e1Q|MfTl>xeYx.XTo^w3ECnbzaCE].o{/?:bXN/VVm_B$J4m}z?S{$EAXN0d.voCAu={@wL(.IF;s09(8:aeSa{%>}gN#wh/K*R*bKxEf3+x]nH|o?I~ao7H_LR.bgT)? W@sT,o8cP^>bl[{:q%[)Nd!)+d6DMu(wN(=s|*6HMcCBLMh$o~^(fq46G1K3+!j[F1m`D/g<FV2lq/DcESy-;s>LYE!:C}KF){lKc9<-?d>1>R)?D0fL`t>XcY<OpDxr[_>L)$PxHm',
        ];

        $secondSensorData = [
            // 0
            '2;4539973;3687238;4,26,0,0,1,0;|#L`(~RPm3w>~&a 8_d&TE_ne$-v!CF?[ur+nxu#456`A,!J^wjQ?0Sg+@RN^Df1K>t4o}k!790zS^^/S9viE8nR~M5=u%$5Qd@bHfGg-:9x`w|Ds-I:0p-7d](?Ch3ik+k/~:Uv9T4Sk6-wOO8ZwnJxBIyfI+7O|8rX<xKyHc{YFrw`7!meekPQsTn:#o6<tB*_toZr[nuCv(lB/&] ssjpO4Bqid}-v;1r[=3_AOzsn8N<r{lws+|%XRlnr].#U&#cgJ6p$[}upj!tEOAnUTa^E=lA=W](sdK:/krtA^(/Ra2E1K@_[!)*qh=j}|OhWRK<?|WM%_<[gA!x`ry0OZd|gfCs03kC3vtppjrdQ!QppQ&&}?Fl%EO]e^=v%Ar>G60e$t]72jIcv]1,7Ml3Gvz:=>-`(6uTI?P<fe)ugJ:I`G(,>f!JV@Vsj<>=CfN&QE[^t&^mvQLg,{T4 }U/C#THkw/DpX#2@I]d/KK>IL0Z$hVppgQ&R<oM[scNw?9tJs<7lxE^wfZ%PBCXk@pG|^q]m.|pFsufqlAY~?`l rrDkdeyP%P1ZMrVh2((e1baq(_-n&YgtnoP,,,AiOudH.mDY(5>v>HCr|JyPh?HucyW1:.c)uMFzg@0J{0>qCPhx=}3o5{-^`tR!=@|E4hgE:zf9kz wMYb0S&L@kHq1CM8!*C}C5s~U`I9aHSr^AUR)S5jR$[,ho/g[{4l?>(+|mbV1m,88*8Q{T>n^!X}aiNPz7P- O~3faocG5XtX`l0{9%hbG+U:Y!k`6G@xllyq~h$fMr.3*LhD%}WFB1k@d-4.PtJ}/~LYrl#_I|G>Ju=yS^D:& YEm01,q4-x0#>CY[tLOtUO_5FB#,pmWiM9UC;G$IIERGq|1FSAC#RXZ[C(+[.RDJ2pK&6@m`YU_m9Lpr++-hKM`8nfUc[kkvda4eQ[TXZRy*UT/;`bY!isR6fj`sgxvCbmBrG1dF5$p `zFVv}9y`&8ze^Upy/2DCF,}2WF(7;j,`oQn<?O=#Yuc>JEZb9<cZ[IC_sm`y(QqItXyBQjbMr>2$|)NX#7PUnWIn2-*R_7L;dFk;[zqi-XXOcZ+@syBWc`P(TRCn-e(fJ+[6?zxp6nwYy4axQb>CZHV2_]T$C(T6zf=rr<fn7~|w5H~<s2r.JH:(e`d)]>w[@7kYvHX[:w+}N]Eh=W9Yt.!lFRb1^O-&X`8*O)8y]M!y@g;uF i-SFe<4IQ4UM2+sgOHuYf#UHco9_E l4k<y!/vjsCvT@&.vObk8o(L;/1K5NBem3-Uwc|p/^t}`/xZBLjbE`Mi6IF[`f&eRg,$2!a,,SA eAa9`v3;L4ZR&t7T6k`Iq#xI/Nhhc.WJ.<(AAPVKG@W-%!Rph5+#xgW5y?uvlI}`FaMt!?7@10V%.0Y xhn[Y@G%pEsb)T0wjz%EQg|5esG**5l| mY*zp#rVJba] I_~~E]+=:mZ`ME#_=as-TRh#WUQlXa+!rT~DU3W$/~DSzHV.S^A)T(vTQlVqyi;thmx:qiyN53@`_,Q>DE:3OGlt,[3InaKfcr`{+yO!:oi_Bjuv)PhXqbee&4:nBm._Y,@+-Eq~<l,u;EnhUeV,uf4BlffKh%)PmB7^f}B5zALSL@Gv4Ot]L~62YX2:&^3l>g_I0y]dagz/S3A!3<tBeo0WXiE(Z-Q*8qK0W8q`J]A`XL|vw}^ zdW8?Hmb(8<=J53GndD/r4ois$paR1s_(7?O[FvsJ,AP?#%xzU?`$c2.V8mu&az {A.nH*(4j/!6CV:6vM3.ku,XIlj+$ qneL%l9_IF1L{a2QI8(,S2.hd(tZg%iL}caznEHe W]Q*H[bvGFBcVwea!O>lW:PfI9$`zF@CjLfH;Y9.ram2}}|l8y-5t[cJHFgrW<#isG?=d8)epi{x$**xn4`db+L.^[NO&.lOr;76o@2}| EIY]hbNvKP`&H7n{uoP`D8Vu-3mD./=C[a!&C-zd/CNvF5|)l#vt^Y&grmR4& ;Igq/EfZJ<i}6g?*}~!3$:%vQ,AT:plo,B[tHX|ntL5L_Kt|j&x0<E69fSh}oE31H.YtJPx&7xYz9lT]KmZz&I><wf*7)g+*^sEU3S#w=.z/OAz*#CKl}$680a(GF3PZ&F%E!Ey%;%#Jp[5]TqyBSs{^~B:1Vynyzq#!f(2=]z&LfbUvrnRycW8ye73fX$v^fa{{$TZw:?k?qkVp (Bs+%0LFNzFERv2l@-hI(g%KAJ4_cXu%Z}J|MYzx{_+>Bj/eNA!e7}G{|4h7KIn*Z-Ss>C?DH-rsta!oH2|oT7];KR8q$/UpMvY0d(OXsA2GW:SM;4oeU=nTfsSR[i8L;uX~a,kf{!8W]=GyFxdkKLxKs9|it3q9kTHp[~/J9pc)I+bXJH=Y;<t_t25}8D0dI-sUJxOd,$t5XK~.j-~/y{ZQj/2)IYEtsZC)sR:Pz%v<^Hr}aPISQZE8^!y OkE0(qsF]0u?_`b+PH<SG[i&z)ne8hnc;eV3?r$}yL@oEIF}RI8OLnu-:KG=tKHV#^K6=w8/)qa<bk(akh^oFr)x|4(X=xI1jF6x#Lc h/{y3A&VjB6U73nAMnq>X:P_Ck@]qAV#]V`]9JfY88FjzJc4Esn}_HjycA:DRMig&uxjO_~m_go4K)N)1_^|aR}9vSok{*&z{aucPd%*o+)Syb!N}KYoUIp39n~&;^$(DAfP6c!!v?AuB~C#Hu,RG8k4~{9<beDU~(5*(Y|wm;R+J6kCqEf+&yW+*o7Cb#Xt7DVY[4[`_/G)SpxZ/B8dU`798cSjv&.XUa+/.anFwsb|MBt?x.%x},py(Ur(d#j^0pn<Q48m*Gq_F)nlC/[.HX)xz(@oHpN)_LF~-?[lJTQLUWimEe61H@t6?%YD#[>iOMw`e*AUiH~IXaIU2-+7PlQ>5hMi XzN4hBSg_J`vc@A-+{%wkA&Rq]Qd!A1!eA!nH @K&B6/E}H@{CYuGZA5_RJl.lwZKpPoIUP?1af{mRbzsbwp@g$un<9h2kRlT&kgw%|srqMidXagvf-/9&kM{!#NtcAJ(6#tXGeOMY XUCTQp5ip&|',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }
        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $secondSensorData[$key]]), $sensorDataHeaders);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        $this->http->FormURL = $formUrl;
        $this->http->Form = $form;

        return $key;
    }

    private function selenium(): bool
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

//            $selenium->setProxyMount();
            $selenium->useChromePuppeteer();
//            $selenium->useGoogleChrome();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            if ($this->www2) {
                $selenium->http->GetURL($this->seleniumFirstUrl);
                $selenium->driver->manage()->window()->maximize();
                $login = $selenium->waitForElement(WebDriverBy::id('txtEmail'), 5);
                $pwd = $selenium->waitForElement(WebDriverBy::id('txtPass'), 0);
                $btn = $selenium->waitForElement(WebDriverBy::id('btnLogin'), 0);

                $acceptCookieBtn = $selenium->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 5);

                if (isset($acceptCookieBtn)) {
                    $acceptCookieBtn->click();
                    sleep(5);
                }

                $this->savePageToLogs($selenium);
                if (!isset($login, $pwd, $btn)) {
                    return false;
                }

                $this->logger->debug('insert credentials');

                try {
                    $login->click();
                } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                    sleep(5);
                    $login = $selenium->waitForElement(WebDriverBy::id('txtEmail'), 5);
                }

                $this->logger->debug("set login");
                $login->sendKeys($this->AccountFields['Login']);
                sleep(1);
                $pwd->click();
                $this->logger->debug("set password");
                $pwd->sendKeys($this->AccountFields['Pass']);
                sleep(1);
                $this->savePageToLogs($selenium);
                $this->logger->debug("click by login btn");
                $btn->click();

                $securityChallenge = $selenium->waitForElement(WebDriverBy::id('sec-overlay'), 10);
                if (isset($securityChallenge)) {
                    $this->logger->debug('security challenge started');

                    $selenium->http->FilterHTML = false;
                    /*
                    $success = $selenium->waitFor(function () use ($selenium) {
                        $this->savePageToLogs($selenium);
                        return is_null($selenium->waitForElement(WebDriverBy::id('sec-overlay'), 0));
                    }, 40);
                    */

                    $success = $selenium->waitFor(function () use ($selenium) {
                        $this->savePageToLogs($selenium);
                        return !$selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                    }, 120);

                    $this->savePageToLogs($selenium);

                    $success ? $this->logger->debug('security challenge completed')
                        : $this->logger->error($this->DebugInfo = "security challenge reached timeout");

                    $btn = $selenium->waitForElement(WebDriverBy::id('btnLogin'), 0);
                    $btn->click();
                }

                $selenium->waitForElement(WebDriverBy::xpath('//div[@class = "pointsHistory"]/table/tbody/tr[1]/td[1]'), 10);
                $this->savePageToLogs($selenium);
            }
            else {
//                $selenium->http->GetURL("https://www.papajohns.com/order/papa-rewards");
                $selenium->driver->manage()->window()->maximize();
                $selenium->http->GetURL("https://www.papajohns.com/order/sign-in");
                $loginPopup = $selenium->waitForElement(WebDriverBy::xpath('//a[@aria-controls="popup-login" or @popovertitle="Log In"]'), 7);
                $this->savePageToLogs($selenium);

                if (!$loginPopup) {
                    return false;
                }

                $loginPopup->click();

                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "user" or @id = "email-address"]'), 7);
                $passInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "pass" or @id = "singin-password"]'), 0);
                $this->savePageToLogs($selenium);

                if (!$loginInput || !$passInput) {
                    return $this->checkErrors();
                }

                $mover = new MouseMover($selenium->driver);
                $this->logger->debug("set login");
                $mover->moveToElement($loginInput);
                $mover->click();
                $mover->sendKeys($loginInput, $this->AccountFields['Login']);

                $this->logger->debug("set pass");
                $mover->setCoords(400, 200);
                $mover->moveToElement($passInput);
                $mover->click();
                $mover->sendKeys($passInput, $this->AccountFields['Pass']);

//                $loginInput->sendKeys($this->AccountFields['Login']);
//                $passInput->sendKeys($this->AccountFields['Pass']);
                $btn = $selenium->waitForElement(WebDriverBy::xpath('//input[@value = "Log In"] | //button[contains(text(), "Sign In") and not(@disabled)]'), 2);

                $mover->setCoords(400, 200);
                $mover->moveToElement($btn);
                $this->savePageToLogs($selenium);
                $mover->click();

                $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Hi, ")] | //span[@id = "header-recaptcha_error_msg" and normalize-space() != ""] | //label[@id and contains(@class, "error") and normalize-space() != ""] | //h1[contains(text(), "Create a New Password")] | //p[@class="error-description"]'), 10);
                $this->savePageToLogs($selenium);

                // Create a New Password
                if ($this->http->FindSingleNode('//h1[contains(text(), "Create a New Password")]')) {
                    $this->throwProfileUpdateMessageException();
                }

                if ($this->http->FindSingleNode('//a[contains(text(), "Hi, ")]')) {
                    $selenium->http->GetURL('https://www.papajohns.com/order/account/my-papa-rewards');
                    $selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Points")]/following-sibling::strong'), 10, false);
                    if ($user = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@class, "header-link") and contains(@popoverclass, "user")]'), 0)) {
                        $user->click();
                    }
                }
            }
            $cookies = $selenium->driver->manage()->getCookies();
            $this->savePageToLogs($selenium);

            foreach ($cookies as $cookie) {
//                if (!in_array($cookie['name'], [
////                    'bm_sz',
//                    '_abck',
//                ])) {
//                    continue;
//                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (NoSuchDriverException | WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
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
