<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerKlbbluebiz extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    protected const COUNTRIES_CACHE_KEY = 'klbbluebiz_countries';
    private const LOGIN_URL = 'https://account.bluebiz.com/shell/en/login';

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $newSite = true;

    private $headers = [
        'AFKL-Travel-Country' => 'FR',
        'country'             => 'FR',
        'AFKL-TRAVEL-Host'    => 'bluebiz',
        'Accept'              => 'application/json, text/plain, *',
        'Content-Type'        => 'application/json',
        'Referer'             => 'https://login.bluebiz.com/login/account',
        "Accept-Encoding"     => "gzip, deflate, br",
    ];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerKlbbluebizSelenium.php";

        return new TAccountCheckerKlbbluebizSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->attempt == 1) {
            $this->setProxyGoProxies();
        } elseif ($this->attempt == 2) {
            $this->http->SetProxy($this->proxyDOP());
        }

        if (isset($this->State['2fa_hold_session'], $this->Step) && $this->Step == 'Question') {
            unset($this->State['2fa_hold_session']);
            $this->UseSelenium();
            $this->useGoogleChrome();
            $this->useCache();
            $this->http->saveScreenshots = true;

            return;
        }

        $this->http->setRandomUserAgent();
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $result = Cache::getInstance()->get(self::COUNTRIES_CACHE_KEY);

        if (($result !== false) && (count($result) > 1)) {
            $arFields["Login2"]["Options"] = $result;
        } else {
            $arFields["Login2"]["Options"] = [
                "" => "Select a region",
            ];
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->GetURL("https://www.bluebiz.com/en/enrol-now/");

            /*
            $counties = $browser->XPath->query('//select[@id = "Country"]/option[@value]');
            */

            $counties = $browser->XPath->query('//label[contains(text(), "Country/region")]/../div/div/select/option[@value]');

            if (!$counties || $counties->length == 0) {
                $this->sendNotification("refs #24826 - Regions aren't found // IZ");
            }

            if ($counties->length > 0) {
                for ($n = 0; $n < $counties->length; $n++) {
                    $country = Html::cleanXMLValue($counties->item($n)->nodeValue);
                    $value = $counties->item($n)->getAttribute('value');

                    if ($country != "") {
                        $arFields['Login2']['Options'][$value] = $country;
                    }
                }
            }

            if (count($arFields['Login2']['Options']) > 1) {
                Cache::getInstance()->set(self::COUNTRIES_CACHE_KEY, $arFields['Login2']['Options'], 3600);
            } else {
                $this->sendNotification("Regions aren't found", 'all', true, $browser->Response['body']);
            }
        }
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] != 'nl') {
            return false;
        }

        $this->newSite = true;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginFormNew()
    {
        $this->newSite = true;
//        $this->http->GetURL("https://account.bluebiz.com/shell/en/login");
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::LOGIN_URL);
        $this->http->RetryCount = 2;
//        $this->http->GetURL("https://login.bluebiz.com/login/account");
//        $this->http->setDefaultHeader($this->http->FindSingleNode("//meta[@id = 'csrf_header']/@content"), $this->http->FindSingleNode("//meta[@id = 'csrf']/@content"));
//        if (!$this->http->FindSingleNode("//meta[@id = 'disableCaptcha' and @content = 'false']/@content")) {
//            return $this->checkErrors();
//        }

        $this->selenium();

        if (isset($this->State['2fa_hold_session'])) {
            return false;
        }

        return true;

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $data = [
            "operationName" => "login",
            "variables"     => [
                "loginParams" => [
                    "loginId"           => $this->AccountFields['Login'],
                    "password"          => $this->AccountFields['Pass'],
                    "persistent"        => false,
                    "recaptchaResponse" => $captcha,
                    //"recaptchaResponse" => "03AGdBq25JBq-rYCraH6JOdSxjg71B2WYUDo-9PO6VVbXl1PG3jdwmfanIFKu9X2WRHRHjX0120YZYq46YwRPX8hmk_z4Ux2mXZ-9qI1D2sUW60epjRmsjF7wD2mnJDtdIyls0APDmFBS263r3oRhr6Bg11kmt_tqm5Cm968U2rKh2uRNosciODAOilQJyYgM8h-tOp9C3Lrn_0FBnSlA-xqm1UOfRpWo3vLFKuM_eb_rZ8cDHANuiSCQcBcC56I1YyOCe24iThTbvrxgnNuAWxSQyj30GM0StogX4r0Y2avdP0rn2Y1aUrq2zZbnLJNC-uqQKJOi7i_8ZCEBfZxTBedHxMYktZaNSrPubGwKOv9JZGt2BzN7HRG9gy5FVh_M_TU-PKLnZoMzjuCIFCdV1WTx7yKZvTCzgsHw-ofy2CySb1Zwh3GcG5dctHPHLsdr7ySbhIYtOUFRP",
                    "type"              => is_numeric($this->AccountFields['Login']) ? "FLYINGBLUE" : "EMAIL",
                ],
            ],
            "query"         => "query login(\$loginParams: LoginParams) {\n  login(input: \$loginParams) {\n    code\n    redirectUri\n    errors {\n      code\n      description\n      __typename\n    }\n    __typename\n  }\n}\n",
        ];
        /*
        $type = is_numeric($this->AccountFields['Login']) ? "FLYINGBLUE" : "EMAIL";
        $data = '{"operationName":"login","variables":{"loginParams":{"loginId":"'.$this->AccountFields['Login'].'","password":"'.$this->AccountFields['Pass'].'","persistent":false,"recaptchaResponse":"'.$captcha.'","type":"'.$type.'"}},"query":"query login($loginParams: LoginParams) {\n  login(input: $loginParams) {\n    code\n    redirectUri\n    errors {\n      code\n      description\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        */
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://login.bluebiz.com/login/gql/gql-login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $login2 = $this->AccountFields['Login2'] . '_en';

        $form['id'] = $this->AccountFields['Login'];
        $form['password'] = $this->AccountFields['Pass'];
        $form['remember'] = 'on';
        $form['logonType'] = 'BB';
        $form['successUrl'] = "/travel/{$login2}/business/jsme/account_statements/index.htm";
        $form['actionUrl'] = "/travel/{$login2}/business/jsme/login/failed.htm";
        $form['firstLogonUrl'] = "/travel/{$login2}/business/jsme/account_statements/index.htm";

        return [
            "URL"           => 'https://www.klm.com/travel/weblogon/' . $login2,
            "RequestMethod" => "POST",
            "PostValues"    => $form,
            "CookieURL"     => "https://www.klm.com/travel/{$login2}/business/jsme/account_statements/index.htm",
        ];
    }

    public function LoadLoginForm()
    {
        $login2 = $this->AccountFields['Login2'] . '_en';
        $this->http->removeCookies();
        // new login url -> https://login.bluebiz.com/login/account

        // Netherlands
        if ("https://www.klm.com/travel/{$login2}/business/jsme/account_statements/index.htm" == 'https://www.klm.com/travel/nl_en/business/jsme/account_statements/index.htm') {
            return $this->LoadLoginFormNew();
        }

        $this->http->GetURL("https://www.klm.com/travel/{$login2}/business/jsme/account_statements/index.htm");

        /*
        if (
            in_array($this->http->currentUrl(), [
                'https://www.bluebiz.com/en/service-centre/bluebiz-is-improving-its-services/',
                'https://www.bluebiz.com/en/service-centre/log-in-to-your-bluebiz-account/',
                'https://www.bluebiz.com/en/service-centre/access-to-your-account/',
            ])
        ) {
        */
        return $this->LoadLoginFormNew();
        /*
        }
        */

        // BlueBiz not available for US/France/Malta users now
        if (in_array($login2, ['us_en', 'fr_en', 'mt_en']) && $this->http->Response['code'] == 404) {
            throw new CheckException("KLM BlueBiz (Corporate) currently not available in your region.", ACCOUNT_PROVIDER_ERROR); /*review*/
        }

        // KLM BlueBiz (Corporate) is not available for all countries
        if (!strstr($this->http->currentUrl(), 'business/jsme/profile/index')
            && strstr($this->http->currentUrl(), '404.html')) {
            throw new CheckException("It seems like, you have selected the wrong country or KLM BlueBiz (Corporate) is not available for your region.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/
        $this->http->FormURL = 'https://www.klm.com/travel/weblogon/' . $login2;
        $this->http->Form['id'] = $this->AccountFields['Login'];
        $this->http->Form['password'] = $this->AccountFields['Pass'];
        $this->http->Form['remember'] = 'on';
        $this->http->Form['logonType'] = 'BB';
        $this->http->Form['successUrl'] = "/travel/{$login2}/business/jsme/account_statements/index.htm";
        $this->http->Form['actionUrl'] = "/travel/{$login2}/business/jsme/login/failed.htm";
        $this->http->Form['firstLogonUrl'] = "/travel/{$login2}/business/jsme/account_statements/index.htm";
        unset($this->http->Form['query']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/(The system received an unexpected authentication challenge from a junction Web\s*server)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable - Zero size object")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 3, false, 'description');

        if ($response || $this->http->FindNodes('//div[contains(@class, "bwc-form-errors")]/span | //div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)]')) {
            if (isset($response->data->login->redirectUri)) {
                $this->captchaReporting($this->recognizer);
                $this->http->GetURL($response->data->login->redirectUri);

                $this->http->GetURL("https://account.bluebiz.com/ctms/auth/asfc/callback?code={$response->data->login->code}");

                return $this->loginSuccessful();
            }

            $message =
                $response->data->login->errors[0]->description
                ?? $this->http->FindSingleNode('//div[contains(@class, "bwc-form-errors")]/span')
                ?? $this->http->FindSingleNode('(//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)])[last()]')
                ?? null
            ;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (
                    strstr($message, 'Incorrect username and/or password. Please check and try again.')
                    || strstr($message, 'These login details appear to be incorrect. Please verify the information and try again')
                    || strstr($message, 'Sorry, we couldn’t find the e-mail address or Flying Blue number you entered. Please try again or create a Flying Blue account.')
                    || strstr($message, 'Sorry, we couldn\'t find the e-mail address or Flying Blue number entered. Please try again or create a Flying Blue account.')
                    || strstr($message, 'More than 1 passenger is registered with this e-mail address. Please log in with your Flying Blue number instead, so we can uniquely identify you.')
                    || strstr($message, 'The password you entered is not valid. Please try again.')
                    || $message == 'Please enter a valid password.'
                    || $message == 'Please enter a valid e-mail address.'
                    || $message == "Sorry, we can't recognise your password due to a technical error. Please click on \"Forgot password?\" to request a new one."
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message == 'Unfortunately, your account is blocked. Please click "Forgot password?" to reset your password.'
                    || strstr($message, 'Unfortunately, your account is blocked.')
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if (
                    $message == 'Sorry, an unexpected technical error occurred. Please try again or contact our customer support.'
                    || strstr($message, 'Sorry, we cannot log you in right now. Contact us via the KLM Customer Contact Centre, or 24/7 via social media. Please mention the type of error "account is missing"')
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                if (
                    strstr($message, 'Authentication failed: recaptchaResponse')
                    || strstr($message, 'Access denied: Ineligible captcha score')
                    || $message == 'Invalid Captcha'
                ) {
                    $this->captchaReporting($this->recognizer, false);

                    throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
                }

                if (
                    strstr($message, 'Due to a technical error, it is not possible to log you in right now.')
                    || strstr($message, 'Due to a technical error, we cannot log you in right now.')
                ) {
                    $this->markProxyAsInvalid();

                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                    $this->DebugInfo = "block, technical error";

                    throw new CheckRetryNeededException(2, 0);
                }

                $this->DebugInfo = $message;

                return false;
            }

            return $this->checkErrors();
        }

        if (
            $this->http->getCookieByName("KLMCOM.SSOCOOKIE")
            || $this->http->FindSingleNode('//span[contains(@class, "bwc-logo-header__user-name")]')
        ) {
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        if ($this->http->FindSingleNode('//div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Update your password")]')) {
            $this->throwProfileUpdateMessageException();
        }

        $login2 = $this->AccountFields['Login2'] . '_en';

        if (!$this->http->PostForm() && $this->http->currentUrl() != "https://www.klm.com/travel/{$login2}/business/jsme/overview/account_statements/index.htm") {
            return $this->checkErrors();
        }

        // provider bug fix
        if ($this->http->currentUrl() == 'https://www.bluebiz.com/en/service-centre/' || $this->http->Response['code'] == 404) {
            $this->http->GetURL("https://www.klm.com/travel/{$login2}/business/jsme/login/error.htm?successUrl=/travel/{$login2}/business/jsme/overview/account_statements/index.htm");
        }

        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Your account is locked')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Authentication failed')]/following::p[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // You have used an invalid BlueBiz number / password. Please use your BlueBiz Number (format XX12345) to login.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Authentication failed')]/parent::div | //h3[contains(text(), 'Authentication failed')]/parent::div", null, true, "/(You have used an invalid BlueBiz number \/ password\.[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
        // Update your log-in details
        if ($this->http->FindSingleNode('//h2[contains(normalize-space(),"Update your log-in details")]/following-sibling::p[contains(normalize-space(),"You are no longer able to access your account with your current log-in details. To access your bluebiz account, please update your log-in details.")]')){
            throw new CheckException("You are no longer able to access your account with your current log-in details. To access your bluebiz account, please update your log-in details.", ACCOUNT_INVALID_PASSWORD);
        }
        */

        if ($this->http->FindSingleNode('//a[@id = "btn-bluebiz-logout" and not(./ancestor::*[contains(@style, "display: none")])]/@id')
            || $this->http->FindPreg('/(bb_company\s*=\s*\{[^\}]*)/ims')) {
            return true;
        }

        if ($message = $this->http->FindPreg('/(document\.location\.replace\(\"\/travel\/generic\/index.html)/ims')) {
            throw new CheckException("Invalid combination of Flying Blue number and PIN code", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//h1[@class='warning-text']")) {
            throw new CheckException("Invalid combination of Flying Blue number and PIN code", ACCOUNT_INVALID_PASSWORD);
        }

        // AccountID: 1420627
        if ($this->http->FindSingleNode('//h1[contains(text(), "Internal Server Error - Read")]') && $this->AccountFields['Login'] == 'CN02998') {
            throw new CheckException("Invalid combination of Flying Blue number and PIN code", ACCOUNT_INVALID_PASSWORD);
        }

        if ($iframe = $this->http->FindSingleNode("//iframe[@name = 'appFrame']/@src")) {
            $this->http->GetURL($iframe);

            if (($this->http->FindSingleNode("//h1[contains(text(), 'Your access')]")
                || $this->http->FindSingleNode("//h1[contains(text(), 'Ihr Zugang')]"))
                && ($this->http->FindSingleNode("//span[contains(text(), 'Required information')]")
                    || $this->http->FindSingleNode("//span[contains(text(), 'Pflichtangaben')]"))
                && ($this->http->FindSingleNode("//span[contains(text(), 'confirm')]")
                    || $this->http->FindSingleNode("//span[contains(text(), 'bestätigen')]"))) {
                throw new CheckException("KLM BlueBiz (Corporate) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            } /*checked*/
        }

        // provider bug fix
        if (
            $this->http->currentUrl() == 'https://www.bluebiz.com/en/service-centre/'
            || $this->http->GetURL("https://www.klm.com/travel/{$login2}/business/jsme/login/error.htm?successUrl=/travel/{$login2}/business/jsme/overview/account_statements/index.htm")
        ) {
            throw new CheckRetryNeededException();
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $code = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        if (!$otpInput = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5)) {
            $this->saveResponse();

            return false;
        }

        $this->logger->debug("entering code...");
        $elements = $this->driver->findElements(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'));

        foreach ($elements as $key => $element) {
            $this->logger->debug("#{$key}: {$code[$key]}");
            $element->click();
            $element->sendKeys($code[$key]);
            $this->saveResponse();
        }// foreach ($elements as $key => $element)

        if ($captchaField = $this->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 0)) {
            $captcha = $this->parseCaptchaImg();

            if ($captcha === false) {
                $this->saveResponse();

                return false;
            }

            $captchaField->sendKeys($captcha);
        }
        $this->saveResponse();

        if (!$button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 1)) {
            return false;
        }
        $button->click();

        $this->waitForElement(WebDriverBy::xpath('
            //span[contains(@class, "bwc-logo-header__user-name")]
            | //div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)]
            | //div[contains(@class, "bwc-form-errors")]/span
        '), 15);

        if ($this->waitForElement(WebDriverBy::xpath('//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)] | //div[contains(@class, "bwc-form-errors")]/span'), 0)) {
            $this->saveResponse();
            $error = $this->http->FindSingleNode('//div[contains(@class, "bwc-form-errors")]/span')
                ?? $this->http->FindSingleNode('(//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)])[last()]');
            $this->logger->error("[Error]: $error");

            if (
                strstr($error, 'This is not the right PIN code. Please try again.')
                || strstr($error, 'You have entered an incorrect PIN code. Please try again.')
                || strstr($error, 'Your one-time PIN code has expired')
            ) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $error, "Question");

                return false;
            }

            if (strstr($error, 'Sorry, an unexpected technical error occurred')) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $error;

            return false;
        }

        $this->switchToCurl();

        return true;
    }

    public function Parse()
    {
        if ($this->newSite) {
            $this->parseBluebiz();

            return;
        }

        if ($m = $this->http->FindPreg('/(bb_company\s*=\s*\{[^\}]*)/ims')) {
            $this->logger->debug(var_export($m, true), ['pre' => true]);
            // BlueBiz balance
            $this->SetBalance($this->http->FindPreg('/Balance\"\s*\:\s*\"([^\"]*)/ims', false, $m));
            // Company name
            $this->SetProperty("Companyname", $this->http->FindPreg('/\"Name\"\s*\:\s*\"([^\"]*)/ims', false, $m));
            // Program administrator
            $this->SetProperty("Programadministrator", beautifulName($this->http->FindPreg('/AdminName\"\s*\:\s*\"([^\"]*)/ims', false, $m)));
        }
        // BlueBiz transactions -> Account Statement
        $login2 = $this->AccountFields['Login2'] . '_en';

        if ($this->http->currentUrl() != "https://www.klm.com/travel/{$login2}/business/jsme/account_statements/index.htm") {
            $this->http->GetURL("https://www.klm.com/travel/{$login2}/business/jsme/account_statements/index.htm");
        }
        $this->logger->notice("Loading iframe...");

        if ($iframe = $this->http->FindSingleNode("//iframe[@name = 'appFrame']/@src")) {
            $this->http->GetURL($iframe);
        }

        // fix for Netherlands and some other regions
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Balance')]/following-sibling::span[1]", null, true,
                "/(.+)\s+blue credit/"));
            // Company name
            $this->SetProperty("Companyname", $this->http->FindSingleNode("//span[contains(text(), 'Company Name')]/following-sibling::span[1]"));
            // Program administrator
            $this->SetProperty("Programadministrator", beautifulName($this->http->FindSingleNode("//span[contains(text(), 'Program administrator')]/following-sibling::span[1]")));
        }

        // BlueBiz number
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[@id = 'jsmeBlueBizNumber']/span[2]"));
        // Date of last transaction
        $this->SetProperty("LastActivity", $this->http->FindSingleNode("//p[contains(text(), 'Date of last transaction')]", null, true, "/\:\s*([^<]+)/"));
        // Expiring Balance
        $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode("//p[contains(., 'After this date, these blue credits will expire.')]/b"));
        // Expiration date  // refs #11580
        $exp = $this->http->FindSingleNode("//p[contains(., 'After this date, these blue credits will expire.')]", null, true, "/You must use [\d\,\.\s]+ blue credits? before the deadline of ([^\.]+)/ims");

        if (strtotime($exp)) {
            $this->SetExpirationDate(strtotime($exp));
        }
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $data = [
            "operationName" => "getCustomer",
            "variables"     => [],
            "query"         => "query getCustomer {\n  fetchCustomer {\n    givenNames\n    familyName\n    communicationMedium {\n      postalAddresses {\n        usageType {\n          name\n          __typename\n        }\n        country {\n          code\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://account.bluebiz.com/ctms/gql-ctms-ubc", json_encode($data), $this->headers);
        $response = $this->http->JsonLog();

        return !empty($response->data->fetchCustomer->givenNames);
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/recaptchaSiteKey\":\"([^\"]+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

//        $postData = [
//            "type"       => "RecaptchaV3TaskProxyless",
//            "websiteURL" => $this->http->currentUrl(),
//            "websiteKey" => $key,
//            "minScore"   => 0.9,
//            "pageAction" => "login",
//        ];
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $this->recognizer->RecognizeTimeout = 120;
//        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => "customer_login",
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseBluebiz()
    {
        $this->logger->notice(__METHOD__);
        $this->loginSuccessful();
        $response = $this->http->JsonLog();
        // Program administrator
        $this->SetProperty("Programadministrator", beautifulName($response->data->fetchCustomer->givenNames . " " . $response->data->fetchCustomer->familyName));

        $data = [
            "operationName" => "corporateEnvironments",
            "variables"     => [],
            "query"         => "query corporateEnvironments {\n  fetchCorporateEnvironments {\n    corporateEnvironments {\n      id\n      uccrId\n      corporateName\n      label\n      statusCode\n      tcAccepted\n      publishedCorporateContractId\n      highestRole {\n        code\n        profileLabel\n        profileDescription\n        __typename\n      }\n      permissions {\n        permissionCode\n        granted\n        __typename\n      }\n      __typename\n    }\n    isAccessGranted\n    linkToAirfranceBooking\n    linkToKlmBooking\n    __typename\n  }\n}\n",
        ];
        $this->http->PostURL("https://account.bluebiz.com/ctms/gql-ctms-ubc", json_encode($data), $this->headers);
        $response = $this->http->JsonLog();

        if (!isset($response->data->fetchCorporateEnvironments->corporateEnvironments[0])) {
            if (
                (
                    // AccountID: 4867584
                    $response->data->fetchCorporateEnvironments->corporateEnvironments === []
                    // AccountID: 1626651
                    || $response->data->fetchCorporateEnvironments->corporateEnvironments === null
                )
                && $response->data->fetchCorporateEnvironments->isAccessGranted === false
            ) {
                // Sorry... This page is for travel managers only. Please go to the KLM or Air France website instead.
                throw new CheckException("It seems that you don’t have a KLM BlueBiz account. Please go to the KLM website instead.", ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }
        // Company name
        $this->SetProperty("Companyname", $response->data->fetchCorporateEnvironments->corporateEnvironments[0]->corporateName);

        $ceId = $response->data->fetchCorporateEnvironments->corporateEnvironments[0]->id ?? null;

        if (!$ceId) {
            return;
        }
        $ceId = intval($ceId);
        $data = [
            "operationName" => "accountdata",
            "variables"     => [
                "ceId" => $ceId,
            ],
            "query"         => "query accountdata(\$ceId: Int!) {\n  fetchBBAccountData(ceId: \$ceId) {\n    blueBizRetroclaim\n    blueBizStatements {\n      currency\n      blueCreditToCurrencyRate\n      balance\n      endingDate\n      __typename\n    }\n    __typename\n  }\n}\n",
        ];
        $this->http->PostURL("https://account.bluebiz.com/ctms/gql-ctms-bluebiz", json_encode($data), $this->headers);
        $response = $this->http->JsonLog();
        // Balance - blue credits
        $this->SetBalance($response->data->fetchBBAccountData->blueBizStatements[0]->balance ?? null);

        $data = [
            "operationName" => "bbstatementtransactions",
            "variables"     => [
                "ceId" => $ceId,
                "page" => 1,
                "size" => 10,
            ],
            "query"         => "query bbstatementtransactions(\$ceId: Int!, \$page: Int!, \$size: Int!) {\n  fetchBBbStatementTransactions(ceId: \$ceId, page: \$page, size: \$size) {\n    transactions {\n      transactionType\n      date\n      category\n      documentNumber\n      bcAmount\n      routing\n      burnDetails {\n        departureAirport\n        arrivalAirport\n        bookingClass\n        __typename\n      }\n      __typename\n    }\n    paginationData {\n      transactionsTotalNumber\n      currentPageNumber\n      elementsPerPage\n      __typename\n    }\n    accountInformation {\n      totalSpentAmount\n      totalSpentFromDate\n      expiringPoints {\n        amount\n        expiringDate\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n",
        ];
        $this->http->PostURL("https://account.bluebiz.com/ctms/gql-ctms-bluebiz", json_encode($data), $this->headers);
        $response = $this->http->JsonLog(null, 3, false, "expiringPoints");
        $expiringPoints = $response->data->fetchBBbStatementTransactions[0]->accountInformation->expiringPoints->amount ?? null;

        if ($expiringPoints && $expiringPoints > 0) {
            // Expiration date
            $expDate = $response->data->fetchBBbStatementTransactions[0]->accountInformation->expiringPoints->expiringDate ?? null;

            if ($expDate != '2099-01-01T00:00:00Z') {
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $expiringPoints);

                if (strtotime($expDate)) {
                    $this->SetExpirationDate(strtotime($expDate));
                }
            }// if ($expDate != '2099-01-01T00:00:00Z')
        }
        // Date of last transaction
        if (isset($response->data->fetchBBbStatementTransactions[0]->transactions[0]->date)) {
            $this->SetProperty("LastActivity", date("F d, Y", strtotime($response->data->fetchBBbStatementTransactions[0]->transactions[0]->date)));
        }

        $data = [
            "operationName" => "contracts",
            "variables"     => [
                "ceId" => $ceId,
            ],
            "query"         => "query contracts(\$ceId: Int!) {\n  fetchContracts(ceId: \$ceId) {\n    corporateName\n    oin\n    contractType\n    market\n    validityStartingDate\n    validityEndingDate\n    __typename\n  }\n}\n",
        ];
        $this->http->PostURL("https://account.bluebiz.com/ctms/gql-ctms-contract", json_encode($data), $this->headers);
        $response = $this->http->JsonLog(null, 4);
        // Company name
        $this->SetProperty("Companyname", $response->data->fetchContracts[0]->corporateName);
        // BlueBiz number
        $this->SetProperty("Number", $response->data->fetchContracts[0]->oin);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;

        try {
            $selenium->UseSelenium();

            $selenium->useGoogleChrome();
            $selenium->useCache();
            /*
            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            */
            $selenium->setProxyGoProxies();

            $selenium->http->saveScreenshots = true;

//            if ($this->attempt > 0) {
            $wrappedProxy = $this->services->get(WrappedProxyClient::class);
            $proxy = $wrappedProxy->createPort($selenium->http->getProxyParams());
            $selenium->seleniumOptions->antiCaptchaProxyParams = $proxy;
            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
//            }

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->http->start();
            $selenium->Start();

            $this->newSite = true;

            $login2 = $this->AccountFields['Login2'] . '_en';
            // new login url -> https://login.bluebiz.com/login/account
            $selenium->http->GetURL("https://www.klm.com/travel/{$login2}/business/jsme/account_statements/index.htm");

            try {
                $selenium->http->GetURL(self::LOGIN_URL);
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "login-page__button")]'), 15);
            $this->savePageToLogs($selenium);
            $selenium->driver->executeScript('try { document.querySelector(\'.login-page__button\').click() } catch (e) {}');

            $loginWithPass = $selenium->waitForElement(WebDriverBy::xpath('//a[@aria-label="Log in with your password instead?"]'), 10);
            $this->savePageToLogs($selenium);

            if (!$loginWithPass) {
                return false;
            }

            $this->acceptCookies($selenium);
            $loginWithPass->click();

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="loginId"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                    $retry = true;
                }

                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    $retry = true;
                }

                return false;
            }

            $this->acceptCookies($selenium);

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->duration = rand(100000, 120000);
            $mover->steps = rand(50, 70);

            $this->logger->debug("set login");
            $this->savePageToLogs($selenium);
//            $loginInput->sendKeys($this->AccountFields['Login']);
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
            $mover->click();
            $this->logger->debug("set pass");
//            $passwordInput->click();
//            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);
            $mover->click();

            // remember me
            $this->logger->debug("click by 'remember me'");
            $selenium->driver->executeScript('
                var rememberme = document.querySelector(\'[id = "mat-slide-toggle-1-input"]\');
                if (rememberme)
                    rememberme.click();
            ');

            $this->logger->debug("click by btn");
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
            $this->savePageToLogs($selenium);

            if (!$button) {
                return false;
            }

            $loginInput->click();
            $button->click();

            $captchaField = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 5);

            if ($captchaField || $selenium->waitForElement(WebDriverBy::xpath("//div[@formcontrolname='recaptchaResponse']"), 0, false)) {
                $captcha = $selenium->parseCaptchaImg();

                if ($captcha !== false) {
                    $captchaField->sendKeys($captcha);
                } else {
                    $selenium->waitFor(function () use ($selenium) {
                        $this->logger->warning("Solving is in process...");
                        sleep(3);
                        $this->savePageToLogs($selenium);

                        return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
                    }, 180);

                    if ($this->attempt == 0 && $this->http->FindSingleNode('//div[@formcontrolname="recaptchaResponse"]//iframe/@title')) {
                        $retry = true;
                    }
                }

                $this->savePageToLogs($selenium);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
                $this->savePageToLogs($selenium);

                if (!$button) {
                    if ($captcha === '') {
                        $this->captchaReporting($selenium->recognizer, false);

                        throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
                    }
                }

                if ($button) {
                    $this->logger->debug("click by btn");

                    try {
                        $button->click();
                    } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                        $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    }
                }
            }

            $selenium->waitForElement(WebDriverBy::xpath('
                //span[contains(@class, "bwc-logo-header__user-name") or contains(text(), "Invalid Captcha")]
                | //div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)]
                | //div[contains(@class, "bwc-form-errors")]/span
                | //div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Get your one-time PIN code")]
                | //div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Update your password")]
            '), 15);
            $this->savePageToLogs($selenium);

            if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Get your one-time PIN code")]'), 0)) {
                $this->logger->notice('started 2fa');

                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }

                if ($captchaField = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 0)) {
                    $captcha = $selenium->parseCaptchaImg();

                    if ($captcha === false) {
                        return false;
                    }

                    $captchaField->sendKeys($captcha);
                }

                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
                $this->savePageToLogs($selenium);

                if (!$button) {
                    if (isset($captcha) && $captcha === '') {
                        $this->captchaReporting($selenium->recognizer, false);

                        throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
                    }

                    return false;
                }

                $button->click();
                $result = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);

                try {
                    if (!$result && $button->isDisplayed()) {
                        $selenium->waitFor(function () use ($selenium) {
                            $this->logger->warning("Solving is in process...");
                            sleep(3);
                            $this->savePageToLogs($selenium);

                            return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
                        }, 180);

                        $result = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);
                    }

                    if (!$result && $button->isDisplayed()) {
                        $this->saveResponse();
                        $this->logger->info('repeat button click');
                        $button->click();
                        $result = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);
                    }

                    $this->savePageToLogs($selenium);
                } catch (
                    StaleElementReferenceException
                    | \Facebook\WebDriver\Exception\StaleElementReferenceException $e
                ) {
                    $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
                }

                $this->savePageToLogs($selenium);

                if (!$result) {
                    $this->DebugInfo = 'otp input not found';
                    $this->logger->error($this->DebugInfo);

                    return false;
                }
                $this->http = $selenium->http;
                $this->State['2fa_hold_session'] = true;
                $this->holdSession();
                $this->AskQuestion('We’ve sent the PIN code to your e-mail address', null, 'Question');

                return false;
            }

            /*
            if ($this->http->FindSingleNode('//span[contains(text(), "Invalid Captcha")]')) {
                $this->captchaReporting($selenium->recognizer, false);

                $captchaField = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "mat-input-2"]'), 5);
                $this->savePageToLogs($selenium);

                if ($captchaField) {
                    $captcha = $selenium->parseCaptchaImg();

                    if ($captcha === false) {
                        return false;
                    }

                    $captchaField->sendKeys($captcha);

                    $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
                    $this->savePageToLogs($selenium);

                    if (!$button) {
                        return false;
                    }

                    $button->click();

                    $selenium->waitForElement(WebDriverBy::xpath('
                        //span[contains(@class, "bwc-logo-header__user-name")]
                        | //span[contains(text(), "Invalid Captcha")]
                        | //div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)]
                        | //div[contains(@class, "bwc-form-errors")]/span
                    '), 15);
                    $this->savePageToLogs($selenium);

                    if ($this->http->FindSingleNode('//span[contains(text(), "Invalid Captcha")]')) {
                        $this->captchaReporting($selenium->recognizer, false);
                    } else {
                        $this->captchaReporting($selenium->recognizer);
                    }
                }
            } else {
                $this->captchaReporting($selenium->recognizer);
            }
            */

            $this->acceptCookies($selenium);
            $this->savePageToLogs($selenium);

            $solvingStatus =
                $this->http->FindSingleNode('//a[@title="AntiCaptcha: Captcha solving status"]')
                ?? $this->http->FindSingleNode('//a[@class = "status"]')
            ;

            if ($solvingStatus) {
                $this->logger->error("[AntiCaptcha]: {$solvingStatus}");

                if (
                    strstr($solvingStatus, 'Proxy response is too slow,')
                    || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection refused')
                    || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection timeout')
                    || strstr($solvingStatus, 'Captcha could not be solved by 5 different workers')
                    || strstr($solvingStatus, 'Solving is in process...')
                    || strstr($solvingStatus, 'Proxy IP is banned by target service')
                    || strstr($solvingStatus, 'Recaptcha server reported that site key is invalid')
                ) {
                    $selenium->markProxyAsInvalid();

                    throw new CheckRetryNeededException(3, 3, self::CAPTCHA_ERROR_MSG);
                }

                $this->DebugInfo = $solvingStatus;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
//            $retry = true;
        } catch (
            UnexpectedJavascriptException
            | NoSuchDriverException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            if (empty($this->State['2fa_hold_session'])) {
                $selenium->http->cleanup();
            }

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function acceptCookies($selenium)
    {
        $this->logger->notice(__METHOD__);

        $selenium->driver->executeScript('
            try {
                BWCookieBanner.acceptAllCookies();
            } catch (e) {}         
            try {
                document.querySelector(\'#cookiebarModal\').remove();
            } catch (e) {}
        ');
    }

    private function parseCaptchaImg()
    {
        $this->logger->notice(__METHOD__);
        $img = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'asfc-svg-captcha']"), 0);

        if (!$img) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $pathToScreenshot = $this->takeScreenshotOfElement($img);
        $parameters = [
            "regsense" => 1,
        ];
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot, $parameters);
        unlink($pathToScreenshot);

        return $captcha;
    }

    private function switchToCurl()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $curl = new HttpBrowser("none", new CurlDriver());
        $curl->setHttp2(true);
        $curl->SetBody($this->http->Response['body']);
        $this->http->cleanup();
        $state = $this->http->driver->getState();
        $cookies = $state['BrowserCookies'] ?? $state['Cookies'] ?? [];
        $this->http->brotherBrowser($curl);

        foreach ($cookies as $cookie) {
            $curl->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
        $this->http = $curl;
    }
}
