<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerTesco extends TAccountChecker
{
    use ProxyList;

    public $regionOptions = [
        ""        => "Select your region",
        "Ireland" => "Ireland",
        "UK"      => "United Kingdom",
    ];

    private $question = 'Please enter the digits from your active Clubcard linked to this account (Clubcard, Clubcard Credit Card, Privilege Card or Clubcard Plus)'; /*review*/
    private $message = "To update this Tesco Clubcard account you need to enter the whole number of your Tesco Clubcard. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information."; /*review*/

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        // Add field "Region"
        ArrayInsert($arFields, "Login", true, ["Login3" => [
            "Type"      => "string",
            "InputType" => "select",
            "Required"  => true,
            "Caption"   => "Region",
            "Options"   => $this->regionOptions,
        ]]);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
    }

    public static function isClientCheck(AwardWallet\MainBundle\Entity\Account $account, $isMobile)
    {
        if ($account->getLogin3() == 'Ireland') {
            return false;
        }

        return null;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (empty($this->AccountFields['Login3'])) {
            $this->AccountFields['Login3'] = 'UK';
        }
        $this->DebugInfo = $this->AccountFields['Login3'];

        if ($this->AccountFields['Login3'] == 'Ireland') {
            /*
            $this->http->SetProxy($this->proxyStaticIpDOP(), false);
            */

            return $this->LoadLoginFormIreland();
        }

        throw new CheckException(PROVIDER_CHECKING_VIA_EXTENSION_ONLY, ACCOUNT_PROVIDER_DISABLED);

        if (empty($this->Answers[$this->question]) && empty($this->AccountFields['Login2'])) {
            throw new CheckException($this->message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->FilterHTML = false;
        $this->http->setCookie("tesco_cookie_accepted", '1', 'secure.tesco.com');
        $this->http->GetURL("https://secure.tesco.com/register/?from=%2fclubcard%2fdeals%2fDefault.aspx");

        if (!$this->http->ParseForm("fSignin")) {
            // new form?
            if (!$this->http->ParseForm(null, "//form[contains(@action, '/account/en-GB/login?from')]")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('username', $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);

            return true;
        }

        $this->http->SetInputValue('loginID', $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("confirm-signin.x", '33');
        $this->http->SetInputValue("confirm-signin.y", '13');
        $this->http->SetInputValue("from", '/clubcard/deals/Default.aspx');

        return true;
    }

    public function LoadLoginFormIreland()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.tesco.ie/account/login/en-IE?from=https://secure.tesco.ie/clubcard");

        if ($loginURL = $this->http->FindPreg("/var loginURL = '([^\']+)/")) {
            $this->http->GetURL($loginURL);
        }

        if (!$this->http->ParseForm(null, "//form[//input[@name = 'email'] and not(@action)]", false)) {
            return false;
        }

        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function LoginIreland()
    {
        $this->logger->notice(__METHOD__);

        // sensor_data workaround
        $this->sendSensorData();

        if (!$this->http->PostForm()) {
            return false;
        }
        // You may have entered the wrong email address or password.
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'You may have entered the wrong email address or password.')
                or contains(text(), 'Unfortunately we do not recognise those details.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->Response['code'] == 403) {
            $this->DebugInfo = "403, sensor_data issue";

            return false;
        }

        // js redirect
        $this->http->GetURL("https://secure.tesco.ie/clubcard/myaccount/home.aspx");
        // Access is allowed
//        if ($this->http->FindSingleNode("//a[contains(text(), 'Log out') or contains(text(), 'Sign out')]")) {
        if ($this->http->getCookieByName('OAuth.AccessToken')) {
            return true;
        }

        return false;
    }

    public function ParseIreland()
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Encoding" => "gzip, deflate, br",
            "Referer"         => "https://secure.tesco.ie/clubcard/myaccount/home",
            "X-XSRF-TOKEN"    => $this->http->getCookieByName("XSRF-TOKEN"),
        ];
        $this->http->GetURL('https://secure.tesco.ie/clubcard/myaccount/app/accounts/api/name', $headers);
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/Hello\s+([^\"]+)/ims")));

        $this->http->GetURL('https://secure.tesco.ie/clubcard/myaccount/app/points/api/summary', $headers);
        $response = $this->http->JsonLog();
        // Balance - My current points total
        $this->SetBalance($response->points);

        $this->SetProperty("CombineSubAccounts", false);

        // Equivalent BA Avios
        $equivalent = $this->http->FindSingleNode("//h3[span[contains(text(), 'Equivalent')]]/following-sibling::div/div");
        $displayName = $this->http->FindSingleNode("//h3[span[contains(text(), 'Equivalent')]]", null, true, "/([^\:]+)/");

        if (isset($equivalent)) {
            $this->AddSubAccount([
                "Code"        => 'tescoIrelandEquivalent',
                "DisplayName" => Html::cleanXMLValue($displayName),
                "Balance"     => $equivalent,
            ]);
        }

        $this->logger->info("Vouchers", ['Header' => 3]);

        $this->http->GetURL("https://secure.tesco.ie/clubcard/myaccount/vouchers/app/vouchers/api/onlySummary", $headers);
        $response = $this->http->JsonLog();
        // Vouchers to spend now
        if (isset($response->value)) {
            $this->AddSubAccount([
                "Code"        => 'tescoVouchersIreland',
                "DisplayName" => "Value in Clubcard vouchers",
                "Balance"     => $response->value,
            ]);
        }// if (isset($voucher))

//        $this->http->GetURL("https://secure.tesco.ie/clubcard/myaccount/vouchers/app/vouchers/a/api/redeemed");
        $vouchers = $this->http->XPath->query("//div[contains(@id, 'div_UnusedVoucherSummary')]//tr[td[contains(@id, 'lblVoucherslist')]]");
        $this->logger->debug("Total {$vouchers->length} vouchers were found");

        foreach ($vouchers as $voucher) {
            $code = $this->http->FindSingleNode("td[2]", $voucher);
            $balance = $this->http->FindSingleNode("td[4]", $voucher, true, self::BALANCE_REGEXP_EXTENDED);
            $exp = strtotime($this->ModifyDateFormat($this->http->FindSingleNode("td[3]", $voucher)));

            if (isset($code, $balance, $exp)) {
                $this->AddSubAccount([
                    "Code"           => 'tescoVouchersIreland' . $code,
                    "DisplayName"    => 'Voucher # ' . $code,
                    "Balance"        => $balance,
                    "ExpirationDate" => $exp,
                ]);
            }
        }// foreach ($vouchers as $voucher)
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Unexpected error occurred
        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Unexpected error occurred')]")) {
            throw new CheckException("Website Temporarily Unavailable", ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException("Website Temporarily Unavailable", ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")
            // An error occurred while processing your request.
            || $this->http->FindPreg("/An error occurred while processing your request\./ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h2[@class="hello"][contains(text(),"error")]/..')) {
            throw new CheckException($this->replaceError($message), ACCOUNT_PROVIDER_ERROR);
        }
        //# We are sorry that this page is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are sorry that this page is temporarily unavailable')]")) {
            throw new CheckException($this->replaceError($message), ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'website is currently down')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We have recently upgraded our systems')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Lots of people are shopping with us at the moment and we need to ask you wait briefly.
         * Don't worry, we'll redirect you to the website as quickly as we can.
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Lots of people are shopping with us at the moment')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Sorry, we are currently experiencing problems with our system, please try again later.
        if ($message = $this->http->FindPreg("/(Sorry, we are currently experiencing problems with our system, please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // If you are seeing this page it is because your browser has failed some security checks
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'If you are seeing this page it is because your browser has failed some security checks')]")) {
            $this->DebugInfo = $message;
            $this->logger->error($message);
        }

        return false;
    }

    public function Login()
    {
        if ($this->AccountFields['Login3'] == 'Ireland') {
            return $this->LoginIreland();
        }

        usleep(rand(400000, 1300000));

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->currentUrl() == 'https://secure.tesco.com/register/redirect.aspx?from=http%3a%2f%2fwww.tesco.com%2fclubcard%2fdeals%2fDefault.aspx'
            || $this->http->currentUrl() == 'https://secure.tesco.com/register/redirect.aspx?isRC=0&from=http%3a%2f%2fwww.tesco.com%2fclubcard%2fdeals%2fDefault.aspx'
            // There is only link "Sign in" on the page - this is provider bug
            || $this->http->currentUrl() == 'http://www.tesco.com/') {
            $this->http->Log(">>> Redirect");
            $this->http->GetURL("https://secure.tesco.com/clubcard/myaccount/home.aspx");

            // prevent error 404
            if ($this->http->Response['code'] == 404) {
                throw new CheckRetryNeededException(3, 7);
            }
        }
        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'signout')]/@href")
            || $this->http->FindSingleNode("//a[contains(@onclick, 'Sign out')]/@onclick")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//form[@id = 'fLoginError']/p[1]", null, false)) {
            throw new CheckException($this->replaceError($message), ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//p[@id='ctl00_PageContainer_pNormalMessage']/text()[1]", null, false)) {
            throw new CheckException($this->replaceError($message), ACCOUNT_INVALID_PASSWORD);
        }
        //# Sorry the email and/or password you have entered has not been recognised, please check and try again.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry the email and/or password you have entered has not been recognised')]", null, false)) {
            throw new CheckException($this->replaceError($message), ACCOUNT_INVALID_PASSWORD);
        }
        // As part of our ongoing work to always protect your security we kindly ask you to create a new password
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'As part of our ongoing work to always protect your security we kindly ask you to create a new password')]")) {
            throw new CheckException($this->replaceError($message), ACCOUNT_INVALID_PASSWORD);
        }

        //# This account has previously been closed
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'This account has previously been closed')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // ... we couldn't find the page you requested.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "... we couldn\'t find the page you requested.")]')) {
            throw new CheckException("We're sorry... we couldn't find the page you requested.", ACCOUNT_PROVIDER_ERROR);
        }

        // new form

        // Unfortunately we do not recognise those details.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Unfortunately we do not recognise those details.')]")) {
            throw new CheckException($this->replaceError($message), ACCOUNT_INVALID_PASSWORD);
        }
        // Unfortunately we do not recognise those details. Please try again
        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Unfortunately we do not recognise those details. Please try again')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account is locked
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Your account is locked')]")) {
            throw new CheckException($this->replaceError($message), ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function replaceError($error)
    {
        $error = preg_replace('/My Clubcard account Sorry but an unexpected error has occured\. We are currently trying to resolve the issue\. Please try again\./ims',
                            'Sorry but an unexpected error has occured. We are currently trying to resolve the issue. Please try again.',
                            $error);

        return preg_replace('/Please try one of the options below\.$/ims', '', $error);
    }

    public function Parse()
    {
        if ($this->AccountFields['Login3'] == 'Ireland') {
            $this->ParseIreland();

            return;
        }// if ($this->AccountFields['Login2'] == 'Ireland')

        //# Provider error
        if ($message = $this->http->FindPreg('(We\'re not available right now.  We\'ll be back as soon as we can\.)')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

//        // this page is opening only in the UK  // refs #8140
//        $this->http->GetURL("http://www.tesco.com/clubcard/account/");
        //# Confirm your Clubcard details
        if ($this->http->FindSingleNode("(//span[contains(text(), 'Confirm your Clubcard details')])[1]")) {
            throw new CheckException("Tesco Clubcard website is asking you to confirm your Clubcard details, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }
        //# This account is not available to view online
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'This account is not available to view online')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->ParseForm(null, 1, true, '//form[contains(@action, "HomeSecurityLayer.aspx")]')
            && $this->http->FindSingleNode("//h2[contains(text(), 'Clubcard Security Verification')]")) {
            // it's old code
            if ((empty($this->Answers[$this->question]) || !is_numeric($this->Answers[$this->question])
                    || strlen($this->Answers[$this->question]) < 13)
                && empty($this->AccountFields['Login2'])) {
                throw new CheckException($this->message, ACCOUNT_PROVIDER_ERROR);
            }// if (empty($this->Answers[$this->question]))
            else {
                if (!empty($this->AccountFields['Login2'])) {
                    $answer = $this->AccountFields['Login2'];
                } elseif (isset($this->Answers[$this->question])) {
                    $answer = $this->Answers[$this->question];
                } else {
                    throw new CheckException($this->message, ACCOUNT_PROVIDER_ERROR);
                }
                $firstDigit = $this->http->FindSingleNode("//span[@id = 'ctl00_PageContainer_spnFirstDigit']");
                $secondDigit = $this->http->FindSingleNode("//span[@id = 'ctl00_PageContainer_spnSecondDigit']");
                $thirdDigit = $this->http->FindSingleNode("//span[@id = 'ctl00_PageContainer_spnThirdDigit']");
                $this->logger->debug("Digits: $firstDigit, $secondDigit, $thirdDigit");

                if (isset($answer[$firstDigit - 1], $answer[$secondDigit - 1], $answer[$thirdDigit - 1])) {
                    $this->http->SetInputValue('ctl00$PageContainer$txtSecurityAnswer1', $answer[$firstDigit - 1]);
                    $this->http->SetInputValue('ctl00$PageContainer$txtSecurityAnswer2', $answer[$secondDigit - 1]);
                    $this->http->SetInputValue('ctl00$PageContainer$txtSecurityAnswer3', $answer[$thirdDigit - 1]);
                    $this->http->SetInputValue('ctl00$PageContainer$btnSbmtNumbers', "Submit");
                    $this->http->PostForm();
                    // invalid card number
                    if ($error = $this->http->FindSingleNode("//span[contains(text(), 'The details you have entered do not')]")) {
                        throw new CheckException($this->message, ACCOUNT_PROVIDER_ERROR);
                    }
                    // Sorry, we are currently experiencing problems with our system, please try again later.
                    if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Sorry, we are currently experiencing problems with our system')]")) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }
                    // You have exceeded the maximum number of attempts to access your account.
                    if ($message = $this->http->FindSingleNode("//span[contains(text(), 'You have exceeded the maximum number of attempts to access your account.')]")) {
                        throw new CheckException($message, ACCOUNT_LOCKOUT);
                    }

                    // Name
                    $this->SetProperty("Name", $this->http->FindPreg("/>\s*(?:Hello)\s+([^<\.]+)[<\.]/ims"));
                    // Balance - My current points total
                    $this->SetBalance($this->http->FindSingleNode("//h3[contains(text(), 'My current points total')]/following-sibling::div/div"));

                    $subAccounts = [];
                    // Equivalent BA Avios
                    $equivalent = $this->http->FindSingleNode("//h3[span[contains(text(), 'Equivalent')]]/following-sibling::div/div");
                    $displayName = $this->http->FindSingleNode("//h3[span[contains(text(), 'Equivalent')]]", null, true, "/([^\:]+)/");

                    if (isset($equivalent, $displayName)) {
                        $subAccounts[] = [
                            "Code"        => 'tescoEquivalent',
                            "DisplayName" => CleanXMLValue($displayName),
                            "Balance"     => $equivalent,
                        ];
                    }

                    // Vouchers to spend now
                    $this->http->GetURL("https://secure.tesco.com/Clubcard/MyAccount/Vouchers/Home.aspx");
                    $voucher = $this->http->FindSingleNode("//h3[contains(text(), 'You still have to spend')]/following-sibling::div[1]/div/h4");

                    if (isset($voucher)) {
                        $subAccounts[] = [
                            "Code"        => 'tescoVouchers',
                            "DisplayName" => "Value in Clubcard vouchers",
                            "Balance"     => $voucher,
                        ];
                    }// if (isset($voucher))
                    // Clubcard Fuel Save
                    $this->http->GetURL("https://secure.tesco.com/Clubcard/MyAccount/FuelSave/Home.aspx");
                    $savings = $this->http->XPath->query("//div[@id = 'ctl00_ctl00_PageContainer_MyAccountContainer_divCcbody']/ul/li");
                    $this->http->Log("Total {$savings->length} savings was found");

                    // refs #11561 notifications
                    if ($savings->length > 0) {
                        $this->sendNotification("tesco - refs #11561. Need to add Fuel Save in the extension");
                    }

                    for ($i = 0; $i < $savings->length; $i++) {
                        $node = $savings->item($i);
                        $saving = $this->http->FindSingleNode(".//div[@class = 'content']//div[@class = 'content']", $node, true, '/([\d]+)/ims');
                        $expMonth = $this->http->FindSingleNode(".//div[@class = 'content']//h3", $node, true, '/by\s*end\s*of\s*([^<]+)/ims');
                        $months = [
                            'January'   => 1,
                            'February'  => 2,
                            'March'     => 3,
                            'April'     => 4,
                            'May'       => 5,
                            'June'      => 6,
                            'July'      => 7,
                            'August'    => 8,
                            'September' => 9,
                            'October'   => 10,
                            'November'  => 11,
                            'December'  => 12,
                        ];
                        $this->http->Log("Expiration Date at the end of {$expMonth}");
                        unset($exp);

                        if (isset($months[$expMonth])) {
                            $exp = mktime(0, 0, 0, $months[$expMonth] + 1, 0, date("Y"));

                            if ($exp < strtotime("-4 months")) {
                                $this->http->Log("Correct date (+1 year)");
                                $exp = strtotime("+1 year", $exp);
                            }// if ($exp < strtotime("-4 months"))
                        }// if (isset($months[$expMonth]))

                        if (!empty($saving) && isset($exp)) {
                            $subAccounts[] = [
                                "Code"           => 'tescoSaving' . $i,
                                "DisplayName"    => "Clubcard Fuel Save",
                                "Balance"        => $saving,
                                "ExpirationDate" => $exp,
                            ];
                        }
                    }// for ($i = 0; $i < $savings->length; $i++)

                    if (!empty($subAccounts)) {
                        $this->SetProperty("SubAccounts", $subAccounts);
                        $this->SetProperty("CombineSubAccounts", false);
                    }// if (isset($subAccounts))
                }// if (isset($answer[$firstDigit-1], $answer[$firstDigit-1], $answer[$firstDigit-1]))
                else {
                    throw new CheckException($this->message, ACCOUNT_PROVIDER_ERROR);
                }
            }
        }// if ($this->http->ParseForm(null,1, true, '//form[contains(@action, "HomeSecurityLayer.aspx")]')...

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Sorry, we are currently experiencing problems with our system
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Sorry, we are currently experiencing problems with our system')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'tescoVouchersIreland')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");
        }

        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'tescoVoucher')) {
            return '&pound;' . $fields['Balance'];
        }

        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'tescoSaving')) {
            return $fields['Balance'] . 'p / litre';
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //$arg["SuccessURL"] = 'https://secure.tesco.com/clubcard/myaccount/home.aspx';
        $arg["PreloadAsImages"] = true;

        return $arg;
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

        // data from FF
        $this->http->setCookie("_abck", "951822D84B2093B66F3AE0827C92E1E9~0~YAAQm2rcFxgsHyCVAQAAe2UFIg1NHNEcIxsZUvynmDZXiJUayQP6YJFTPVbkbqunoPRM8tFykJsGzGhzXihySp9d7i2fJTLB/cW2SLNuX+lSvtROvI6immC7LtqbdotyfcOnKumxGfCcBLcvZO2CUyr4VC9aixURUioZLpxTHSdjdGKUaAh+nsGkGIHwG/BOiTw6Wh9xbr+XyJPlhH8ZExuiIotPXlgF4uC0GTmrn5a4O3bBciRxMqgptemFrsKr+mWYbdpA2WGiTf/RNX7gQJs2h7RmpAl1EwptWeBpfmw+LpHIlM4iV8Otnz2KP1fpuxREATu93TtPjOOe9WnDN+eF8lPnaR3+quis1M+iNhnl4OtdtOQmPwgRwLzgW7DayJMVFDmzxdSoJ8ngzHYoptDLDHCCxOcgJRbaTdFPmefFb+Svr+EpNI/TQ7qTr3iAXdLMT8dUCd9rO7IyZKIqXaBH1LBqx17xBs5XC1hCwOz2K7ssQzAsTwspoAQZecLWxAPzWhzfg66YysBTXgJTvQPrN6odB+fBuvzZc49IqclzWC1T7ggxyswnhkQAxOFjpKeu5TzbCM7e4TNEFPWxYQKrGY2S3J2T207lKJYgFUpBbauesNhCg7WWBM7qQiCUeCWddm59bp4gL8O6pGurAJucYIX+ybiKyBRnL/2xefbp/iuCihfIpzA7qVupuKF+SpbZ8v7jGAhKlbp56oz1KC5fNbIeyGpdCPRXtXSyNgOq7fzyFEGyD7Oc4WkYv/jZGJTummkOfeKv1gFabtLaT4YRW3f2kLnQjWwrPJubyY0MNw==~-1~-1~-1"); // todo: sensor_data workaround

        $sensorData = [
            '3;0;1;2048;3359554;IcH+AU+8JMn0psWPN0tz00vk2f/ob+HvAul7lNu2tuw=;19,0,0,2,3,0;"n.xfs3/<Uj+G=om=c"e"m6i"0r6^v;lu5"?w:"/"ns"?"vpG"9"BQ"O"?#@"hw|W6abJNgyD+|P!pe^=obqE@$B4qu!]?${yoTFH,mEg=RGZqI:F{!oJX"mA-r"cmFGPz]K"srT"z]T"/~3"kgnGlX.sY9"ZqQ"5T"7 c"s5ys^reabN|";X-"5&NT!":wx"E"-%l-"5" ]-"76s;Tqp5;noGY0"Ju@"oN`R@g]"n"r~y"1@"G"GBi._yQk`wMr/}p-<OC3<ZiPTN>SQn6|_@J4f4FX99)K<:T:XAqb5eka2F?bQKha"O5Fm_~fdQ6Hme+"I<giKkJ3xH"-s%F";motv"p /i"_"_f!Pa@9Z.z""|Z~"p@ "|!}{-=C)"X-j"l"F7."<""$"?vJ")QwWC66I:YQF31L&&^"`PKG"7A4"^*x"R+niRuy"Klg1y5r",0^>&"%%O"UL6HQ"Q?#"Y"";"Y!4"jP4(Cx,wazp"n,&"3k ym|$q>A"!N|"dpIAR"B|.,"m "<=."Nh>"4?Ry6](X"92C"T""#"KFu"q"@ftfN"OM5"Vk?"o"^5"IN5"z[Rt"cj:Figo_#f6,-hzMxZ)q9owX|}Coqr@Ll%Zs"7R7"]3|"s""+")u="d!i#E"eeg+"G"/"]+h"2_a"l"}{r?#Y/$MWY.g9<f~S)a%L8>`,tGPipE1!@!LSnHv}&kR:2^@l;fJ(<""q"p60"e"} _W0)O/"7%c"l,S".yA~/k_"E:J"c"Q?elp"vL("4)t"e&<1["_CzB"<"r(6 LN78!>#oE4RbPJouJ d>1lUyis6! DU:n%"i">8F"%qX"(P`"K*k@mhI)3wWKx8$#e^,_rujUAt1"H"qP/":""V"Y$B"s6KK~"~4:F"pXQtCGGe8|WNA(*v"9Vv"O~<@r"|6"{z{rY"VZ`"$wPZ)E)"[Fj"~"0hZX_e^:ZYopq5zv5mr+AW>{;#(XQh:)}<^:wcL?v"?F7"L__"J""x|0"v|l"NadNv"u:Py")""C"nSx";""O"Hne"1*XEW"N."n"[DJ` g7ZQI}U$iXHWIOW!|i;HC0UBu83#+A<5<nM|f.t"/"v=U"IaL[8"Jm`"8"p"i"LZ7"6 rGaa8O?"j#";"H+$Utvk&6r7~L/^,Cbj`~MFAPV(?|5MN~p])af"!"c p"I{Si8>.g=%%3"j%Ul"$/16]13."hA"5^Nnbp"+4yT"~""z"WlY"UE6.T"Y!"e""`"nJh"&"":"{Uj"<"/t.U[`B-=?sP6"Q@,"P~_"D"["a<X"S@S"4M[";p?"M""E"pgD"9".?*m~1a&n<3bN">"L1*"%&z"PNRK"S".Soo6">v="NJx]X"SZF9M"Ho"A<;I73oP](o"g%0"[""KPw"m)k"c""M"y$"dWS@,Njm^"$zF7@"%"R="qqj"K/n6"lmrnmWC"<$="C(N`5"ith")IX] KN4ELcj,##}e"X]d"',
        ];

        $secondSensorData = [
            '3;0;1;0;3359554;IcH+AU+8JMn0psWPN0tz00vk2f/ob+HvAul7lNu2tuw=;7,21,0,0,4,65;l*H7"sw7"16MPT@2S7G>gv](k&q5_q3n2!J2|;C#>ht=C"52P"rh~"0""c"A*5"*"Oz=mJTz8bvPMHh[5ievG^Ee5.LFW=<a?4I`=wkY_U"-Uc"J~N",NZDt"+uX7"Kr<">5Nh"z"p/hwJ?Q,bdnDm"{ K"n]%")"kLVL"+"eY|"IditB-="X9#"1"b>.c@EO> )@n+m<tN-U#"2"=gf">6]iE"-gC"lwTJQ"s<T"xC~<~/Y"/s`qk;`4yN<2xOs+~B14MFh>Xf#Q j(P>e~ckG4j:rTfm^F45)>_=DY:/Ab:w;Z~"VXqjfF_K"KL2"j"3">kC"fO7"M|Jz8"}?zm","K_g)["tPg"`1T/A",-`m>":em"k5rnn"|!NA"m"3jjp@j`F" :{"`Ck"w}MVeC6"e0LY{"$_0";`?"L"FDFQbf9CEO5RHJP91Jxe[/;tG--qB92!~/wVJF&Xj(N)8&mTp9gniU"L"}Dm"#"Seutc"D+"sA{"^Y)"6Gv$d[z]Fe}[C="=8"=""<"Su/"4?IYtB6"}PRfZFg0#bmk0Bma*-B&n,`MH[k4U5aC]0GZNH"4"Tpu"s"C"VrZ"tF)"syT{Z)s;s*FK@t).u"#>e"}:L]NWgE1"?a9s"]9fGY!i#E"eeg+"G""Hiz"_@]"G""["h;*"f"PU_8ouj/Bt"o<`"6{4"zz~XbW|"F6r"Qw!%kP8Ic"Pp["03Qdc"m%4g"J}(^^7+W3/Cu"Zl6T"."9x<pm)nUl-Z+vdjFH-4Q6Ab?-J[ T=qX*t7`r="n" U9"E"#:"_Ez"F*!F"X%cJ9`Pk"Qvk"FR:W]2w""W"wP."hNpoY+)R2{hCB7PpGh8$kjm,hugqamg;Kf.@Q+)m,@A$a*tgn7^?/!"<(L"pPFI-{ m5 YU9(*v"9H+~")Q4S2"&6P"8"}!SRQcbJ1eJ,{|P")kb"Fwf"84PDddD6(^7"Ef"^"Q"1<;"h8m"^""Rbj"07a"Yv7"?Yc"J"*"p>*"olM"kuW,A PW"%Ol"Q"gcv/thkXnkxz,"qD#"*hT99/"+e}iRbM?F]6BftOYhiQm|!F#mgsONxcXTJq^YG3:e,Pi":"3Qu"g""F"Cm`"8"h#n+l*[s,!fCfeSTMt+Pk71O8.Pz}q1<i6zR-c(Nk_Z&WJPSV(?v1ON}p])cbd2>vK%[D}Ip9=&a@75JW1<s<ot51;VC@@x)j8&]S~b,Z^X5 $ 9@ Aw>}#RGO.jizXyV-}fY5s<f{$`>QR}1h.t8{)V^h;)6B~J/ac@A!u: 0Bz_rwKlHpWya+O[wY;RkDptJK)4id1v*I-uz8c-xA6bOgERrMNayT2/u$kqMJ#n{K&&7RvR?up7,S/E^ZB[*f>B:A5K/0bHi/)w/T7`M1!_`-g%D/=_&fVOAHR[NZ?4E{ms/P3gfE>|{[GO-q}En_CLFiggk}ca}RETn=(c`LR1%j^}SSY{LG3@Od^,!4}{P*5 W{oOQHR?;(n4bv$X/* $,OW(,I,8p8KU:aY29-E.=#!Xw3LuM^Wi{"O"Yuj"|V"ZOr"HRV")[h"0j)"?x%|/L_"DGjpmP@"9"x$mx(V7Y;9#,/1}`z"X"%9z" ""g"Pmg"cPre&+",3@"C"F"0C`"Ij7"[PcW9"fL"ph})>"^fnW","AtA]"G"$e2"?C0"6Va"uV"2E]"R"B<"w#h"1]yo"Cg;*z|"i?^n."*Ja")Kg"v?ylo-1|;"E4X(r"D6^3."&O#","x^uo]pwxpk;Gzb1"iTf"A.t"pg.;o5p{;LL"V[ACCl+@!jR2nw]08Q2lyrfkcZ7R._Tn(y4Gx~ tWI42vDg8s"c:T["1s<"/l;"@"/"qoo"~$"r`O9dPMgC"6:"h@xg<f9c"up"7"+NP1A#:uzG1Fk"8"P}T"F""J"(?d"s|@(bIBD"Cu"%""3k-"X7_"N?y[7q") .R"%2H"=2K"i""H}j"$/~"xYO/$W?Q{j"5EH"]Tqafki";Tz"X""&"H$z";""{"*;_"T4B|-2gTWqP{]GYJX8E${.YiB>Z"e"-O]"%""M"KaU"Q""8"vg"."/"olH"EOc"B""9Zq"19_"}"-"E%P"*C<"a"XS$h"-"+)5")GZm i"iQ-HRCls"{"u_u"="dh/mY_o+Ar"Ml1"7xa"j"(&{;7RPsj~Y8X}pNgg?R~I|3ml%sg5S9z0-7Os":"z]a"k""W"w@r"M]8DRPAi.}k|#4Hc"695"Os=aa+j6O$<Jj9Yf"GlD"',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $referer = $this->http->currentUrl();

        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $secondSensorData[$key]]), $sensorDataHeaders);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);

        return $key;
    }
}
