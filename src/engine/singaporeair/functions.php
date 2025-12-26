<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\WeekTranslate;

class TAccountCheckerSingaporeair extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public const REWARDS_PAGE_URL = 'https://www.singaporeair.com/krisflyer/account-summary/elite';

    private $currentItin = 0;
    private $geetestFailed = false;
    private $badCaptcha;

    /*
     * GET https://www.singaporeair.com/kfLogin.form
     * HTTP/1.1 403 Forbidden
     */
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        // refs #13486
        $this->setProxyBrightData();
        $this->http->setRandomUserAgent();
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);

        if ($this->http->Response['code'] !== 200
            && strpos($this->http->Response['errorMessage'], 'Operation timed out after') !== false) {
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        }

        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.singaporeair.com/kfLogin.form';

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->setCookie("AKAMAI_SAA_LOCALE_COOKIE", "en_UK", "www.singaporeair.com");
        $this->http->setCookie("AKAMAI_SAA_COUNTRY_COOKIE", "US", "www.singaporeair.com");
        $this->http->setCookie("saadevice", "desktop", "www.singaporeair.com");
        $this->http->setCookie("AKAMAI_SAA_DEVICE_COOKIE", "desktop", ".sq.com.sg");

        // check Login
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            $this->AccountFields['Login'] = preg_replace("/[^\d]+/", "", $this->AccountFields['Login']);
            $this->logger->debug("Login: {$this->AccountFields['Login']}");

            if (
                empty($this->AccountFields['Login']) || strlen($this->AccountFields['Login']) < 10
                || strlen($this->AccountFields['Login']) > 10
            ) {
                throw new CheckException("The KrisFlyer membership number and/or PIN you've entered isn't valid. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }
        }

        if ($tech = $this->http->FindSingleNode('//p[normalize-space()="Our website is experiencing technical difficulties, and we\'re working hard to fix them."]')) {
            throw new CheckException($tech, ACCOUNT_PROVIDER_ERROR);
        }
        // retries
        $this->http->RetryCount = 0;

        if (
            (
                !$this->http->GetURL('https://www.singaporeair.com/kfLogin.form')
                && !in_array($this->http->Response['code'], [404, 500])
            )
            || $this->http->Response['code'] == 403
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 3);
        }
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//div[@id='distilIdentificationBlock']/@id")) {
            $this->distil();
        }

        if (!$this->loginForm()) {
            return false;
        }

        return true;
    }

    public function loginForm()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm('kfLoginForm')) {
            return $this->checkErrors();
        }

        // refs #25270
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            $this->logger->notice("Form has been changed");
            $this->http->FormURL = 'https://www.singaporeair.com/home/kfLoginHomePage.form';
            $this->http->Form = [];
            $this->http->SetInputValue('kfNumber', $this->AccountFields['Login']);
            $this->http->SetInputValue('pin', $this->AccountFields['Pass']);
            $this->http->SetInputValue('redeemData', "false");
            $this->http->SetInputValue('rememberMe', "false");
            $this->http->SetInputValue('pageUrl', "kfLogin.form");
            $this->http->SetInputValue('nds_pmd', "");
            $this->http->SetInputValue('nuDataInitsessionID', "");
            $this->http->SetInputValue('pageName', "");
            $this->http->SetInputValue('expressBookingChecked', "false");
            $this->http->SetInputValue('feedbackURL', "/kfLogin.form");

            return true;
        }

        $this->http->SetInputValue('kfNumber', $this->AccountFields['Login']);
        $this->http->SetInputValue('pin', $this->AccountFields['Pass']);
        $this->http->SetInputValue('rememberMe', "true");
        $this->http->SetInputValue('pinLogin', "");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Technical difficulties
        if ($message = $this->http->FindPreg('/(We are currently experiencing some technical difficulties on singaporeair\.com, and are working to rectify the problem\.)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//div[@class='tableContents'][contains(p/font/text(), 'technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are unable to process your request at this moment due to a temporary technical issue in this page.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We are unable to process your request at this moment due to a temporary technical issue in this page.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Website is unavailable
        if ($this->http->FindPreg("/maintenance\/english\/maintenance.html\s*is\s*unavailable/ims")) {
            throw new CheckException("Website is currently unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        // Error 404--Not Found
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Error 404--Not Found')]")
            || $this->http->FindPreg("/(Error 404--Not Found)/ims")
            || $this->http->FindSingleNode("//p[contains(text(), 'The requested URL /kfLogin.form was not found on this server.')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")
            // Error 500--Internal Server Error
            || $this->http->FindPreg('/<H2>Error 500--Internal Server Error<\/H2>/')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We regret that our website is temporarily unavailable')]")) {
            throw new CheckException($message . " This is because Singapore Airlines and SilkAir are currently undergoing a transition to a new reservations system at this time.", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Singaporeair.com will be undergoing scheduled maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(Our website will be undergoing scheduled maintenance[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, you've caught us in the middle of an upgrade
        if ($message = $this->http->FindPreg("/(Sorry, you've caught us in the middle of an upgrade)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently experiencing some technical difficulties on singaporeair.com, and are working to rectify the problem.
        if ($message = $this->http->FindPreg("/(We are currently experiencing some technical difficulties on singaporeair\.com, and are working to rectify the problem\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // retires
        if (
            count($this->http->FindNodes('//body/script')) == 11
            || $this->http->Response['code'] == 0
            || $this->http->FindSingleNode("//div[@id='distilIdentificationBlock']/@id")
        ) {
            throw new CheckRetryNeededException(3, 7);
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.singaporeair.com/kfLogin.form');
        $this->http->RetryCount = 2;

        if ($message = $this->http->FindPreg("/(Our website will be undergoing scheduled maintenance[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->ParseMetaRedirects = false;
        $headers = [
            "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        ];
        sleep(2);

        $this->http->RetryCount = 0;
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        if (!$this->http->PostForm($headers)) {
            // bug on the site (returned 404 error)
            if ($this->loginSuccessful()) {
                return true;
            }

            return $this->checkErrors();
        }

        // distill workaround
        if ($key = $this->http->FindSingleNode('//iframe[contains(@src,"recaptcha")]/@data-key')) {
            $token = $this->parseReCaptchaItinerary($key);

            if ($token) {
                $this->http->GetURL("https://www.singaporeair.com/_sec/cp_challenge/verify?cpt-token={$token}");
                $this->http->PostURL($formURL, $form, $headers);
            }
        }

        // it helps
        if ($key = $this->http->FindSingleNode('//iframe[contains(@src,"recaptcha")]/@data-key')) {
            $token = $this->parseReCaptchaItinerary($key);

            if ($token) {
                $this->http->GetURL("https://www.singaporeair.com/_sec/cp_challenge/verify?cpt-token={$token}");
                $this->http->PostURL($formURL, $form, $headers);
            }

            // if captcha persist do retries
            if ($this->http->FindSingleNode('//iframe[contains(@src,"recaptcha")]/@data-key')) {
                throw new CheckRetryNeededException(3, 7);
            }
        }

        if ($this->http->ParseForm("chlge")) {
            $this->badCaptcha = false;
            $this->distil();

            if ($this->loginForm() && !$this->http->PostForm($headers)) {
                if ($this->badCaptcha) {
                    $this->sendNotification('check retry captcha // ZM');
                    $this->distil();
                }
                // bug on the site (returned 404 error)
                if ($this->loginSuccessful()) {
                    return true;
                }

                return $this->checkErrors();
            }
        }

        $this->http->RetryCount = 2;

        $this->http->ParseMetaRedirects = true;

        if ($this->http->FindSingleNode("//p[contains(text(), 'Browser checks in progress')]")) {
            $this->http->GetURL("https://www.singaporeair.com/kfDashBoardPPS.form");

            if ($this->http->FindSingleNode("//div[@id='distilIdentificationBlock']/@id")) {
                $this->distil();
            }
        }

        // Check JS redirect
        if ($redirectURL = $this->http->FindSingleNode('//body/script', null, true, '/location\.href\s*=\s*"(.+)";/im')) {
            $this->http->GetURL($redirectURL);
        }

        // Check login
        if ($this->loginSuccessful()) {
            return true;
        }

        $this->checkProviderErrors();

        if (($this->http->FindSingleNode("//h2[contains(text(), 'Enter security code')]")
            || $this->http->FindSingleNode("//label[contains(text(), 'For verification purposes, enter the text you see in the box below.')]"))
            && $this->loginForm()
        ) {
            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->SetInputValue("flowExecutionURL", "");
            $this->http->unsetInputValue("fromScoot");
            $this->http->unsetInputValue("fromPoints");
            $this->http->unsetInputValue("rememberMe");
            $this->http->unsetInputValue("kfNumber");
            $this->http->unsetInputValue("pin");

            $this->http->ParseMetaRedirects = false;
            $this->http->RetryCount = 0;
            $this->http->PostForm($headers);
            $this->http->RetryCount = 2;
            $this->http->ParseMetaRedirects = true;

            if ($this->loginSuccessful()) {
                return true;
            }

            if (
                $this->http->FindSingleNode("//p[contains(text(), 'The text entered in the box below is not correct. Please try again.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Browser checks in progress')]")
            ) {
                throw new CheckRetryNeededException(4, 7);
            }
            // AccountID: 4587584, 4386117
            if (in_array($this->AccountFields['Login'], ['8825905877', '8824552943'])) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->checkProviderErrors();
        } else {
            // Enter numbers only.
            if (
                ($this->AccountFields['Login'] == '8839709877' && preg_match("/[a-z]/ims", $this->AccountFields['Pass']))
                || ($this->AccountFields['Login'] == '8853658567' && strlen($this->AccountFields['Pass']) == 5)
                || strstr($this->AccountFields['Pass'], "-")
                || strlen($this->AccountFields['Pass']) < 6
            ) {
                throw new CheckException("The KrisFlyer membership number and/or PIN you've entered isn't valid. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $this->AccountFields['Login'] == '721118955496'
            ) {
                throw new CheckException("Enter a valid KrisFlyer membership number or email", ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindPreg("/account.locked/ims", false, $this->http->currentUrl())) {
                throw new CheckException('Your account is now locked', ACCOUNT_LOCKOUT);
            }
        }

        // refs #25270
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            $this->http->GetURL("https://www.singaporeair.com/kfDashBoardPPS.form");
        }

        // it's works sometimes
        if (
            $this->http->currentUrl() == 'https://www.singaporeair.com/kfLogin.form?filterFlowExecutionURL=kfDashBoardPPS.form'
            && in_array($this->http->Response['code'], [200])
            && $this->http->ParseForm('kfLoginForm')
        ) {
            $this->logger->notice("try to login one more time");
            sleep(3);

            if ($this->http->currentUrl() != "https://www.singaporeair.com/kfLogin.form?filterFlowExecutionURL=kfDashBoardPPS.form") {
                $this->http->GetURL("https://www.singaporeair.com/kfLogin.form?filterFlowExecutionURL=kfDashBoardPPS.form");
            }

            if (!$this->loginForm()) {
                return false;
            }
            $this->http->ParseMetaRedirects = false;

            if (!$this->http->PostForm($headers)) {
                // bug on the site (returned 404 error)
                if ($this->loginSuccessful()) {
                    return true;
                }

                return $this->checkErrors();
            }
            $this->http->ParseMetaRedirects = true;
            // Check JS redirect
            if ($redirectURL = $this->http->FindSingleNode('//body/script', null, true, '/location\.href\s*=\s*"(.+)";/im')) {
                $this->http->GetURL($redirectURL);
            }

            // it works sometimes
            if ($this->http->FindSingleNode("//p[contains(text(), 'Browser checks in progress')]")) {
                $this->http->GetURL("https://www.singaporeair.com/kfDashBoardPPS.form");

                if ($this->http->FindSingleNode("//div[@id='distilIdentificationBlock']/@id")) {
                    $this->distil();

                    if (!$this->loginForm()) {
                        return false;
                    }
                    $this->http->ParseMetaRedirects = false;
                    $this->http->PostForm($headers);
                    $this->http->ParseMetaRedirects = true;
                }
            }

            // Check login
            if ($this->loginSuccessful()) {
                return true;
            }

            if (
                $this->http->currentUrl() == 'https://www.singaporeair.com/kfLogin.form?filterFlowExecutionURL=kfDashBoardPPS.form'
                && in_array($this->http->Response['code'], [200])
                && $this->http->ParseForm('kfLoginForm')
            ) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $this->checkErrors();
    }

    public function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);
        // Your KrisFlyer account has expired. Enrol again as a KrisFlyer member at krisflyer.com
        if ($message = $this->http->FindSingleNode('(//p[
                contains(text(), "Your KrisFlyer account has expired. Enrol again as a KrisFlyer member at")
                or contains(text(), "The KrisFlyer membership number/ email address and/or password you have entered do not match our records. Please reset your login details")
                or contains(text(), "The KrisFlyer membership number/ email address and/or password you have entered do not match our records. As you have reached the maximum number")
                or contains(text(), "The KrisFlyer membership number and/or password you have entered do not match our records. Please reset your login details")
            ])[1]')
        ) {
            $message = str_replace(' Please reset your login details here.', '', $message);
            $message = str_replace(', please reset your password here.', ', please reset your password.', $message);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The KrisFlyer membership number and/or PIN you've entered isn't valid. Please try again.
        // The KrisFlyer membership number and/or PIN you have entered do not match our records
        if ($message =
                $this->http->FindSingleNode('//p[
                        contains(text(), "The KrisFlyer membership number and/or PIN you have entered do not match our records")
                        or contains(text(), "The KrisFlyer membership number and/or PIN you\'ve entered isn\'t valid. Please try again.")
                    ]
                    | //div[@data-focus="true"]//p[contains(text(), "The details you have provided do not match our records")]
                ')
                ?? $this->http->FindSingleNode('//div[contains(@class, "alert__message")]/p[contains(text(), "The details you have provided do not match our records")]
                ')
        ) {
            $message = str_replace(' Please reset your login details here.', '', $message);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The KrisFlyer membership number or PIN entered is invalid.
        // The information entered should not contain special characters (e.g. %$~)
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "The KrisFlyer membership number or PIN entered is invalid.")]
                | //div[@class = "alert__message" and contains(text(), "The information entered should not contain special")]
                | //div[@class = "alert__message"]/p[contains(text(), "Please enter a valid password")]
                | //div[contains(@class, "active checkin-alert")]//div[@class = "alert__message"]/p[contains(text(), "Please log in with your KrisFlyer membership number instead")]
        ')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // For security reasons, your account is locked.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "For security reasons, your account is locked.")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Please try again later.
        // We cannot process your request right now. Please get in touch with your
        // Your profile status is incomplete. Please update your profile.
        // We are unable to process your request now. Please try again later.
        if ($message = $this->http->FindSingleNode('
                //p[normalize-space(text()) = "Please try again later."]
                | //p[contains(text(), "We cannot process your request right now.")]
                | //p[contains(text(), "Your profile status is incomplete. Please update your profile.")]
                | //p[contains(text(), "We are unable to process your request now. Please try again later.")]
                | //div[contains(@class, "active checkin-alert")]//div[@class = "alert__message"]/p[contains(text(), "We are unable to process your request.")]
                | //p[contains(text(), "We are unable to process your request at this moment due to a temporary technical issue in this page.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We cannot process your request right now, but we’re working on it. Please try again later, or get in touch with")]
            ')
        ) {
            $message = str_replace(', or get in touch with', '', strip_tags($message));

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // wrong translation -> "???kf.pwd.reset???"
        if ($this->http->FindSingleNode("//p[contains(text(), '???kf.pwd.reset???')]")
            // wrong translation -> "???kf.pwd.issued???"
            || $this->http->FindSingleNode("//p[contains(text(), '???kf.pwd.issued???')]")) {
            throw new CheckException("As part of our ongoing efforts to improve the security of our members’ accounts, PINs which are repetitive (eg 111111) and consecutive (eg 123456) are no longer accepted. To continue to access your account, please change your PIN, ensuring that it does not contain repetitive numbers or consecutive numbers.", ACCOUNT_INVALID_PASSWORD);
        }

//        foreach ($this->http->FindNodes('//div[contains(@class, "main-full")]//div[contains(@class, "error-alert") and not(contains(@class, "hidden"))]//div[contains(@class, "alert__message") and contains(., "Error code")]') as $node) {
//            $this->logger->debug("$node");
//        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "main-full")]//div[contains(@class, "error-alert") and not(contains(@class, "hidden"))]//div[contains(@class, "alert__message") and contains(., "Error code")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'We are unable to process your request right now. Please try again in 24hour')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $timeout = null;
            // AccountID: 4250651
            if ($this->AccountFields['Login'] == '8100123724') {
                $timeout = 180;
            }

            $this->http->GetURL(self::REWARDS_PAGE_URL, [], $timeout);

            // AccountID: 4250651
            if (
                $this->AccountFields['Login'] == '8100123724'
                && $this->http->FindPreg("/(^Internal Server Error$|<h1>502 Bad Gateway<\/h1>|<H1>Access Denied<\/H1>)/ims")
            ) {
                $this->http->GetURL("https://www.singaporeair.com/home/getNonCacheableData.form");
            }
        }

        // it's works
        if ($this->http->FindSingleNode("//div[@id='distilIdentificationBlock']/@id")) {
            $this->distil();
        }

        // is this a proper page
        $node = $this->http->FindSingleNode('//div[@class="pageHeadline"]//h2');

        if (is_null($node) || strtolower($node) != "my statement") {
            $this->logger->debug("[URL]: " . $this->http->currentUrl());
            $this->logger->debug("[CODE]: " . $this->http->Response['code']);
            // retries
            if (
                in_array($this->http->Response['code'], [0])
                || $this->http->FindSingleNode('//div[@class = "dials_error"]//div[@class="alert__message" and p[contains(text(), "We cannot process your request right now. Please try again later, or get in touch with your")]]')
            ) {
                throw new CheckRetryNeededException(3, 10);
            }
        }

        // Main balance
        $this->SetBalance(rtrim($this->http->FindPreg("/ffpMiles.?\":.?\"([^\\\"]+)/"), '\\'));
        // Name
        $this->SetProperty('Name', beautifulName(rtrim($this->http->FindPreg("/firstName.?\":.?\"([^\\\"]+)/"), '\\') . " " . rtrim($this->http->FindPreg("/lastName.?\":.?\"([^\\\"]+)/"), '\\')));
        // KRISFLYER # / PPS CLUB #
        $this->SetProperty("AccountNumber", rtrim($this->http->FindPreg("/kfNumber.?\":.?\"([^\\\"]+)/"), '\\'));
        // Current Tier
        $this->SetProperty('CurrentTier', beautifulName(
            $this->http->FindPreg("/tierDescription.?\":.?\"([^\"]+).?\"/") ??
            rtrim($this->http->FindPreg("/currentTier.?\":.?\"([^\\\"]+)/"), '\\'))// AccountID: 4250651
        );

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && stripos($this->http->currentUrl(), '/kfDashBoardPPS.form') !== false
        ) {
            // Main balance
            $value = $this->http->FindSingleNode("//div[@class = 'slide__left-desc']//p[contains(text(), 'KrisFlyer') and contains(text(), 'miles')]");

            if (isset($value)) {
                $value = preg_replace('/\D/', '', $value);
                $this->SetBalance(intval($value));
            }

            // Name
            $this->SetProperty('Name', trim(beautifulName($this->http->FindSingleNode("//div[@class = 'slide__right-desc']/p[1]/text()[1]", null, true, "/([^\|]+)/"))));
            // KRISFLYER # / PPS CLUB #
            $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//p[contains(text(), 'KRISFLYER') or contains(text(), 'PPS CLUB')]/span"));
            // Current Tier
            $this->SetProperty('CurrentTier', beautifulName($this->http->FindSingleNode("//p[@class='slide__text slide__text--style-1']/text()[1]")));

            if ($this->http->FindPreg('/^[0-9]{6}$/', false, $this->AccountFields['Pass'])) {
                $this->SetWarning('As part of our ongoing efforts to safeguard your account, you must replace your numeric PIN with a complex password.');
            }
        }

        if ($this->Properties['CurrentTier'] != 'Krisflyer') {
            $this->SetProperty('CurrentTier', preg_replace('/^Krisflyer\s+/', '', $this->Properties['CurrentTier']));
        }

        $this->http->GetURL("https://www.singaporeair.com/kfStatementForAtaGlance.form?_=" . time() . date("B"));

        if (isset($this->Properties['CurrentTier']) && $this->Properties['CurrentTier'] == 'Krisflyer') {
            $eliteMilesWording = 'qualification';
        } else {
            $eliteMilesWording = 'requalification';
        }
        $this->logger->debug('[eliteMilesWording]: ' . $eliteMilesWording);
        // Status miles
        $this->SetProperty('CurrentEliteMiles', $this->http->FindSingleNode("//p[contains(., 'Elite miles') and contains(., '{$eliteMilesWording}')]//ancestor::div[@class = 'dials-chart__item-desc']/following-sibling::div//span[@data-kf-points]/text()[1]", null, true, '/\:\s*([\d\,\.\-]+)$/'));
        // Status miles to remain status
        $this->SetProperty('MilesRequired', $this->http->FindSingleNode("//p[contains(., 'Elite miles') and contains(., '{$eliteMilesWording}')]//ancestor::div[@class = 'dials-chart__item-desc']/following-sibling::div//span[@data-kf-required]/text()[1]", null, true, '/\:\s*([\d\,\.\-]+)$/'));

        // Status expiration date
        $this->SetProperty('TierExpirationDate', $this->http->FindSingleNode("//p[contains(., 'Elite miles') and contains(., 'requalification')]//ancestor::div[@class = 'dials-chart__item-desc']/following-sibling::div//span[@data-kf-required]/text()[last()]", null, true, "/by\s*([^<]+)/"));

        // PPS value - Earned: 26,499 PPS Value
        $this->SetProperty('CurrentPPSValue', $this->http->FindSingleNode("(//p[contains(., 'PPS Value')]//ancestor::div[@class = 'slides']//span[@data-kf-points]/text()[1])[1]", null, true, '/:\s*([\d\,\.\-]+)/'));
        // PPS target value
        $this->SetProperty('AdditionalPPSValue', $this->http->FindSingleNode("(//p[contains(., 'PPS Value')]//ancestor::div[@class = 'slides']//span[@data-kf-required]/text()[1])[1]", null, true, '/\:\s*SGD\s*([\d\,\.\-]+)$/'));

        // Need to check exp date
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $notExpMiles = $this->http->FindPreg("/You don't have KrisFlyer miles expiring in the next six months\./");

        // Expiration Date   // refs #4370, #12555
        $this->http->GetURL("https://www.singaporeair.com/krisflyer/miles/expiring-miles");
        // First table with expiring miles
        $response = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'), 3, false, "expiryMonth");
        $expiringMilesList = array_merge(
            $response->props->pageProps->initialData->pageData->milesValidity->expiringMilesList ?? [],
            $response->props->pageProps->initialData->pageData->milesValidity->expiringMilesSixMonthsList ?? [],
        );
        $this->logger->debug("Total ".count($expiringMilesList)." exp node were found");
        $noExpiringPoints = 0;

        if (!empty($expiringMilesList)) {
            foreach ($expiringMilesList as $expiringMilesRow) {
                $date = $expiringMilesRow->expiryMonth;
                $miles = $expiringMilesRow->totalExpiringMiles;
                $this->logger->debug("[{$date}]: '{$miles}'");

                if ($miles > 0) {
                    // Miles to Expire
                    $this->SetProperty("MilesToExpire", $miles);
                    // Expiration Date
                    $exp = $date;
                    $this->logger->debug("Expiration Date $exp - " . var_export(strtotime($exp), true));

                    if (strtotime($exp)) {
                        // refs #12555
                        $lastDay = date("t", strtotime($exp));
                        $this->SetExpirationDate(strtotime($lastDay . " " . $exp));
                    }// if (strtotime($exp))

                    break;
                }// if ($miles > 0)
                elseif ($miles === '0') {
                    $noExpiringPoints++;
                }
            }// foreach ($expiringMilesList as $expiringMilesRow)

            if (
                !isset($this->Properties['MilesToExpire'])
                && $noExpiringPoints === 12
                && count($expiringMilesList) === 12
            ) {
                $this->ClearExpirationDate();
            }
        }// if (!empty($expiringMilesList))
        else {
            // refs #10283
            if ($notExpMiles) {
                $this->ClearExpirationDate();
            }
            $this->logger->notice(">>>> $notExpMiles <<<<");
        }
    }

    public function ParseItineraries()
    {
        $kfvstoken = $this->http->getCookieByName('_kfvstoken');

        if (!isset($kfvstoken)) {
            $this->http->GetURL('https://www.singaporeair.com/krisflyer/bookings/upcoming-flights');

            if (!isset($kfvstoken)) {
                $this->logger->error('Empty _kfvstoken');

                return [];
            }
            $this->sendNotification('check _kfvstoken // MI');
        }
        $headers = [
            'Accept'              => '*/*',
            'Content-Type'        => 'application/json',
            'x-sec-clge-req-type' => 'ajax',
        ];
        $data = [
            'data' => [
                'companyId'          => 'SQ',
                'isFromCheckins'     => false,
                'isFromYourBookings' => true,
                'membershipNumber'   => $this->Properties['AccountNumber'],
            ],
            '_kfvstoken' => $kfvstoken,
        ];
        $this->http->PostURL('https://www.singaporeair.com/krisflyer/account-summary/getPnrList', json_encode($data), $headers);
        $bookings = $this->http->JsonLog(null, 2);

        if ($bookings === null) {
            return [];
        }

        if ($this->http->FindPreg('/^\[\]$/') && count($bookings) == 0) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        // more than 130 itineraries and 500 requests
        $this->http->maxRequests = 700;

        foreach ($bookings as $booking) {
            $this->logger->info('Parse itinerary #' . $booking->referenceNumber, ['Header' => 3]);

            if (!empty($booking->destCityName)) {
                $data = [
                    'data' => [
                        'destCityName'         => $booking->destCityName,
                        'isEligibleForCheckin' => $booking->isEligibleForCheckin,
                        'kfNumber'             => $this->Properties['AccountNumber'],
                        'lastName'             => $booking->lastName,
                        'originCityName'       => $booking->originCityName,
                        'pnr'                  => $booking->referenceNumber,
                    ],
                    '_kfvstoken' => $kfvstoken,
                ];
                $this->increaseTimeLimit();
                $this->http->PostURL('https://www.singaporeair.com/krisflyer/account-summary/yourBookings', json_encode($data), $headers);
                $yourBooking = $this->http->JsonLog();

                if (isset($yourBooking->error) && $yourBooking->error == 'Invalid request payload input') {
                    $this->logger->error("Invalid request payload input");
                }

                if (isset($yourBooking->status) && $yourBooking->status == 'FAILURE') {
                    $this->logger->error("There is an error loading the details of {$booking->referenceNumber}. Please try again later, or get in touch with your Singapore Airlines Office.");
                }

                if (isset($yourBooking->bookings->encryptedURL)) {
                    $this->http->NormalizeURL($yourBooking->bookings->encryptedURL);
                    $this->increaseTimeLimit(120);
                    $this->http->GetURL($yourBooking->bookings->encryptedURL);

                    if ($this->http->FindPreg('/errorCategory=managebooking&errorKey=unmapped\.middleware\.error/', false, $this->http->currentUrl())) {
                        $this->logger->error('We cannot process your request right now. Please try again later, or get in touch with your local Singapore Airlines office.');
                        continue;
                    }
                    $response = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'), 0);

                    if ($response) {
                        $this->parseItinerary($response);
                    }
                }
            } else {
                $data = [
                    'data' => [
                        'kfNumber'             => $this->Properties['AccountNumber'],
                        'lastName'             => $booking->lastName,
                        'pnr'                  => $booking->referenceNumber,
                    ],
                    '_kfvstoken' => $kfvstoken,
                ];
                $this->http->PostURL('https://www.singaporeair.com/krisflyer/account-summary/retrieveBooking', json_encode($data), $headers);
                $yourBooking = $this->http->JsonLog();

                if (isset($yourBooking->encryptedData)) {
                    $this->http->NormalizeURL($yourBooking->encryptedData);
                    $this->http->GetURL($yourBooking->encryptedData);
                    $response = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'), 0);

                    if ($response) {
                        $this->parseItinerary($response);
                    }
                }
            }
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking ref. or Ticket number",
                "Type"     => "string",
                "Size"     => 13,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 20,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.singaporeair.com/en_UK/us/home#/managebooking";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $result = $this->seleniumRetrieve("https://www.singaporeair.com/en_UK/us/home", $arFields);
        $this->logger->debug($result);

        if ($result && is_string($result)) {
            return $result;
        }
        //$this->sendFingerPrint("https://www.singaporeair.com/en_UK/us/home");
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        // 6LcOpwAaAAAAAEfzm1qPIKUjJgLr7NbKufK-3NCO
        // Access Blocked

//        if ($this->http->Response['code'] != 200) {
//            $this->sendNotification('failed to retrieve itinerary by conf #');
//
//            return null;
//        }

        /*$form['lastName'] = $arFields['ConfNo'];
        $form['pnr'] = $arFields['LastName'];

        if (!$this->postItinerary($form)) {
            return null;
        }*/

        $entered = $this->enterIntoCheckConfirmationNumber($arFields);

        if (null === $entered) {
            return null;
        }

        if (in_array($this->http->currentUrl(), [
            'https://www.singaporeair.com/home.form',
            'https://www.singaporeair.com/en_UK/us/home',
        ])) {
            // re-enter (with cookie)
            $entered = $this->enterIntoCheckConfirmationNumber($arFields);

            if (null === $entered) {
                return null;
            }
        }
        $this->distil(false);
        $this->distil(false);

        if ($this->geetestFailed) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        if ($itinError = $this->itinUrlError()) {
            return $itinError;
        }

        // No match found for the entered SQ booking reference and last name, etc.
        if ($err = $this->http->FindSingleNode("//div[@class = 'booking_form_error']")) {
            return $err;
        }
        // JS redirect
        if ($redirectURL = $this->http->FindSingleNode('//a[@class="ajaxRedirect"]/@href')) {
            $this->logger->notice("JS redirect -> {$redirectURL}");
            $this->http->GetURL($redirectURL);
        }
        // Handle additional info request popup
        /*if ($this->http->FindPreg('#we\s+require\s+additional\s+information\s+to\s+retrieve\s+your\s+booking#i')) {
            $status = $this->http->ParseForm('retrieveOfflinePNRForm');

            if (!$status) {
                $status = $this->http->ParseForm(null, 1, true, '//form[@modelattribute = "retrieveOfflinePNRForm"]');
            }

            if (!$status) {
                $this->logger->error('Failed to parse additional info request form');
                $this->sendNotification('failed to retrieve itinerary by conf #');

                return null;
            }

            $this->http->SetInputValue('_eventId_retrieveOffline', 'Find Booking');
            $status = $this->http->PostForm();

            if (!$status) {
                $this->logger->error('Failed to post additional info request form');
                $this->sendNotification('failed to retrieve itinerary by conf #');

                return null;
            }
        }*/
        $response = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'), 0);

        if ($response) {
            $this->parseItinerary($response);
        }

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date of purchase" => "PostingDate",
            "Transaction"      => "Description",
            "KrisFlyer miles"  => "Miles",
            "Elite miles"      => "Info",
            "PPS Value(SGD)"   => "Info",
        ];
    }

    public function ParseHistory($startDate = 0)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->increaseTimeLimit();
        //		$this->http->GetURL('https://www.singaporeair.com/en_UK/ppsclub-krisflyer/booking-history/');
        $this->http->GetURL('https://www.singaporeair.com/kfstatements-json.form');
        $this->distil(false);
        $this->distil(false);

        if ($this->http->Response['body'] === 'null') {
            $this->sendNotification('check null history // MI');

            return [];
        }
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate));

        // Sort by date
        usort($result, function ($a, $b) {
            if ($a['Date of purchase'] == $b['Date of purchase']) {
                return 0;
            }

            return ($a['Date of purchase'] < $b['Date of purchase']) ? 1 : -1;
        });

        $this->getTime($startTimer);

        return $result;
    }

    public function ParseHistoryPage($startIndex, $startDate = null)
    {
        $result = [];
        $nodes = $this->http->JsonLog(null, 0);

        if (isset($nodes->statement)) {
            $this->logger->debug("Found " . count($nodes->statement) . " items");

            foreach ($nodes->statement as $statement) {
                $dateStr = $statement->date;
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    continue;
                }
                $result[$startIndex]['Date of purchase'] = $postDate;
                $result[$startIndex]['Transaction'] = $statement->type . ": " . $statement->detail;
                $result[$startIndex]['KrisFlyer miles'] = $statement->miles;
                $result[$startIndex]['Elite miles'] = $statement->elite;
                $result[$startIndex]['PPS Value(SGD)'] = $statement->pps;

                $startIndex++;
            }// foreach ($nodes->statement as $statement)
        }// if (isset($nodes->statement))

        return $result;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'kfLoginForm']//div[@id = 'recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    protected function parseReCaptchaItinerary($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    protected function parseReCaptchaDistil($retry)
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm']//div[@class = 'g-recaptcha']/@data-sitekey")
            ?? $this->http->FindSingleNode("//iframe[@id = 'sec-cpt-if']/@data-key")
        ;
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    private function seleniumRetrieve($url, $arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            //$selenium->http->removeCookies();
            //$selenium->disableImages();
            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.singaporeair.com/en_UK/us/home#/managebooking');
            $selenium->driver->executeScript('window.scrollTo(0,400)');
            $pnr = $selenium->waitForElement(WebDriverBy::id('bookingReferenceFieldBR'), 10);
            $lastName = $selenium->waitForElement(WebDriverBy::id('last_familyNameFieldBR'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@type='submit' and contains(text(),'Manage booking')]"), 0);

            $pnr->sendKeys($arFields["ConfNo"]);
            $lastName->sendKeys($arFields["LastName"]);
            $btn->click();

            // Access Blocked
            $blocked = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Access Blocked')]"), 10);

            if ($blocked) {
                $this->sendNotification('debug reload // MI');
                $selenium->driver->executeScript('window.location.reload();');
            } else {
                $error = $selenium->waitForElement(WebDriverBy::xpath("//div[@class='msgcommon error']"), 0);

                if ($error && stristr($error->getText(),
                        'No match found for the entered SQ booking reference and last name.')) {
                    return $error->getText();
                }
            }

            $this->saveToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            return true;
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return false;
    }

    private function saveToLogs($selenium)
    {
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//a[contains(@href, "logOut.form")]/@href')
            || $this->http->FindSingleNode('//input[@id = "kfnumber"]/@id')
            || $this->http->FindPreg("/kfNumber\":\"([^\"]+)/")
            || $this->http->FindPreg("/customerID\'\s*:\s*\'([^\']+)/")
            || ($this->AccountFields['Login'] == '8100123724' && strstr($this->http->currentUrl(), 'account-summary/pps-club')) // AccountID: 4250651
        ) {
            return true;
        }

        return false;
    }

    private function itinUrlError(): ?string
    {
        $this->logger->notice(__METHOD__);
        // No booking found. Enter a valid SQ/MI Booking Reference/E-ticket number.
        if ($error = $this->http->FindPreg('/errorKey=manageBooking.bookedFlights.fullyFlown/', false, $this->http->currentUrl())) {
            return 'No booking found. Enter a valid SQ/MI Booking Reference/E-ticket number.';
        }
        // No booking found. Enter a valid SQ/MI Booking Reference/E-ticket number.
        if ($error = $this->http->FindPreg('/errorKey=booking.invalid.reference#\/managebooking/', false, $this->http->currentUrl())) {
            return 'No booking found. Enter a valid SQ/MI Booking Reference/E-ticket number.';
        }
        // No booking found. Enter a valid SQ/MI Booking Reference/E-ticket number.
        if ($error = $this->http->FindPreg('/errorKey=booking.invalid.reference/', false, $this->http->currentUrl())) {
            return 'No booking found. Enter a valid SQ/MI Booking Reference/E-ticket number.';
        }
        // At least one of the flights on your itinerary has been changed.
        if ($error = $this->http->FindPreg('/errorKey=scheduleChange.AlertMessage/', false, $this->http->currentUrl())) {
            return 'At least one of the flights on your itinerary has been changed.';
        }
        // As you have already checked in for this booking, your booking may only be accessed via the Online Check-in function.
        if ($error = $this->http->FindPreg('/errorKey=saar5.l.bookingOverview.bookedFlights.partialFlown/', false, $this->http->currentUrl())) {
            return 'As you have already checked in for this booking, your booking may only be accessed via the Online Check-in function.';
        }
        // We cannot process your request right now. Please try again later, or get in touch with your local Singapore Airlines office.
        if ($error = $this->http->FindPreg('/errorKey=unmapped.middleware.error/', false, $this->http->currentUrl())) {
            return 'We cannot process your request right now. Please try again later, or get in touch with your local Singapore Airlines office.';
        }
        // Your ticket has been converted to an open ticket
        if ($error = $this->http->FindPreg('/errorKey=error.open.pnr.segments/', false, $this->http->currentUrl())) {
            return 'Your ticket has been converted to an open ticket';
        }
        // No match found for the entered SQ booking reference and last name.
        if ($error = $this->http->FindPreg('/errorKey=error.last.name.not.found/', false, $this->http->currentUrl())) {
            return 'No match found for the entered SQ booking reference and last name.';
        }
        // We cannot process your request right now.
        if ($error = $this->http->FindPreg('/errorKey=common.application_error/', false, $this->http->currentUrl())) {
            return 'We cannot process your request right now. Please get in touch with your local Singapore Airlines office.';
        }

        if ($this->http->FindPreg('/errorKey=/', false, $this->http->currentUrl())) {
            $this->sendNotification('check new itinerary error');
        }

        return null;
    }

    private function postItinerary($form)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Accept'              => '*/*',
            'x-requested-with'    => 'XMLHttpRequest',
            'x-sec-clge-req-type' => 'ajax',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'no-cache',
            'Content-Type'        => 'application/x-www-form-urlencoded; charset=UTF-8',
        ];
        $form = [
            "ajaxError"        => "ajaxError",
            "ajaxian"          => "true",
            "bookingForm"      => "currentBooking",
            "isCurrentBooking" => "true",
            "lastName"         => $form['lastName'],
            "pnr"              => $form['pnr'],
        ];
        $this->increaseTimeLimit(120);
        $this->http->RetryCount = 0;
        $postURL = $this->http->PostURL('https://www.singaporeair.com/mbEntry.form', $form, $headers);
        $response = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'));

        if ($this->http->Response['code'] == 403) {
            sleep(1);
            $postURL = $this->http->PostURL('https://www.singaporeair.com/mbEntry.form', $form, $headers);
            $response = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'));
        }

        if (isset($response->provider_secret_public)) {
            $token = $this->parseReCaptchaItinerary($response->provider_secret_public);

            if ($token) {
                $this->http->GetURL("https://www.singaporeair.com/_sec/cp_challenge/verify?cpt-token={$token}");
                $postURL = $this->http->PostURL('https://www.singaporeair.com/mbEntry.form', $form, $headers);
                $response = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'));
            }
        }

        if (!$postURL) {
            return false;
        }
        $this->http->RetryCount = 2;
        // Redirect
        if ($resURL = $this->http->FindPreg('/QajaxRedirect[\"\']+\s*href\s*=\s*[\"\']+(.+)[\"\']+/im')) {
            $this->http->NormalizeURL($resURL);
            $this->http->GetURL($resURL);
        }

        return true;
    }

    private function distil($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $referer = $this->http->currentUrl();
        $distilLink = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"\d+;\s*url=([^\"]+)/ims");

        if (isset($distilLink)) {
            sleep(2);
            $this->http->NormalizeURL($distilLink);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($distilLink);
            $this->parseGeetestCaptcha($retry);
            $this->http->RetryCount = 2;
        }// if (isset($distil))

        if (!$this->http->ParseForm('distilCaptchaForm')) {
            if (!$this->http->ParseForm('chlge')) {
                return false;
            }

            $captcha = $this->parseReCaptchaDistil($retry);

            /*
            if ($frame = $this->http->FindSingleNode("//iframe[@id = 'sec-cpt-if']/@src")) {
                $this->http->NormalizeURL($frame);
                $this->http->PostURL($frame, "captcha_response={$captcha}");
            } else {
            */
                $this->http->GetURL("https://www.singaporeair.com/_sec/cp_challenge/verify?cpt-token={$captcha}&formPost=true");
//            }

            $this->http->JsonLog();

            if ($this->http->FindPreg('/^\s*\{"success":\s*"false"\}/')) {
                $this->badCaptcha = true;
                $this->logger->error('bad captcha');
            }

            // provider bug fix
            if ($this->http->Response['code'] == 404 && $retry) {
                throw new CheckRetryNeededException(3);
            }

            $this->http->GetURL($referer);

            return true;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $captcha = $this->parseFunCaptcha($retry);
        $key = null;

        if ($captcha !== false) {
            $key = 'fc-token';
        } elseif (($captcha = $this->parseReCaptchaDistil($retry)) !== false) {
            $key = 'g-recaptcha-response';
        } else {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue($key, $captcha);

        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        $this->getTime($startTimer);

        return true;
    }

    private function parseGeetestCaptcha($retry = false)
    {
        $this->logger->notice(__METHOD__);
        $this->geetestFailed = false;

        $gt = $this->http->FindPreg("/gt:\s*'(.+?)'/");
        $apiServer = $this->http->FindPreg("/api_server:\s*'(.+?)'/");
        $ticket = $this->http->FindSingleNode('//input[@name = "dCF_ticket"]/@value');

        if (!$gt || !$apiServer || !$ticket) {
            $this->logger->notice('Not a geetest captcha');

            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        /** @var HTTPBrowser $http2 */
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        if (!$challenge) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters, $retry);
        $response = $this->http->JsonLog($captcha, 3, true);

        if (empty($response)) {
            $this->geetestFailed = true;
            $this->logger->error("geetestFailed = true");

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->http->NormalizeURL($verifyUrl);
        $payload = [
            'dCF_ticket'        => $ticket,
            'geetest_challenge' => $response['geetest_challenge'],
            'geetest_validate'  => $response['geetest_validate'],
            'geetest_seccode'   => $response['geetest_seccode'],
        ];
        $this->http->PostURL($verifyUrl, $payload);

        return true;
    }

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $key = $this->http->FindSingleNode("//div[@id = 'funcaptcha']/@data-pkey");

        if (!$key) {
            $key = $this->http->FindPreg('/funcaptcha.com.+?pkey=([\w\-]+)/');
        }

        if (!$key) {
            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        $postData = array_merge(
            [
                "type"             => "FunCaptchaTask",
                "websiteURL"       => $this->http->currentUrl(),
                "websitePublicKey" => $key,
            ],
            $this->getCaptchaProxy()
        );
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeAntiCaptcha($recognizer, $postData, $retry);

        // // RUCAPTCHA version
        // $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        // $recognizer->RecognizeTimeout = 120;
        // $parameters = [
        //     "method" => 'funcaptcha',
        //     "pageurl" => $this->http->currentUrl(),
        //     "proxy" => $this->http->GetProxy(),
        // ];
        // $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        $this->getTime($startTimer);

        return $captcha;
    }

    private function postFirstName()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm('form--popup-firstname')) {
            return true;
        }
        $firstName = $this->http->FindPreg('/(?:(?:Mr|Mrs|Ms)\s+)?(\w+)\s+/i', false, $this->Properties['Name']);

        if (!$firstName) {
            $this->logger->error('Could not parse first name');

            return false;
        }
        $this->http->SetInputValue('firstName', $firstName);
        $this->http->SetInputValue('_eventId_validateFirstName', 'Submit');

        if (!$this->http->PostForm()) {
            return false;
        }

        return true;
    }

    private function parsePastItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Past Itineraries", ['Header' => 2]);
        $startTimer = $this->getTime();
        $result = [];
//        $this->http->GetURL("https://www.singaporeair.com/en_UK/ppsclub-krisflyer/bookings/flight-history/");
        $this->http->GetURL("https://www.singaporeair.com/kfFlightHistoryJson.form");
        $pastIts = $this->http->JsonLog(null, 0, true) ?: [];
        $this->logger->debug("Total " . count($pastIts) . " past reservations were found");
        // {"noResults":"NO_FLIGHT_HISTORY"}
        $noResults = ArrayVal($pastIts, 'noResults', null);

        if ($noResults) {
            $this->logger->notice("No results found.");

            return $result;
        }

        foreach ($pastIts as $pastIt) {
            $date = ArrayVal($pastIt, 'longDate', null);
            $details = ArrayVal($pastIt, 'number', null);
            $f = $this->itinerariesMaster->add()->flight();
            $f->general()->noConfirmation();
            $segment = $f->addSegment();
            $segment->airline()
                ->number($this->http->FindPreg("/^[A-Z]+(\d+)/", false, $details))
                ->name($this->http->FindPreg("/^([A-Z]+)\d+/", false, $details));
            $segment->departure()
                ->noCode()
                ->name(ArrayVal($pastIt, 'from', null))
                ->date(strtotime($date));
            $segment->arrival()
                ->noCode()
                ->name(ArrayVal($pastIt, 'to', null))
                ->date(strtotime($date));
            $segment->setCabin(ArrayVal($pastIt, 'classType', null));
        }// for ($i = 0; $i < $pastIts->length; $i++)
        $this->getTime($startTimer);

        return $result;
    }

    private function enterIntoCheckConfirmationNumber($arFields)
    {
        $this->logger->notice(__METHOD__);

        if ($this->postRetrieve($arFields) === null) {
            return null;
        }

        if ($this->http->Response['code'] === 200
            && $this->http->FindPreg("/<a href=\"javascript:window.location.reload\(\);\">click here<\/a> if the page doesn’t reload.<\/p>/")) {
            $this->logger->debug('reload');
            sleep(15);

            if ($this->postRetrieve($arFields) === null) {
                return null;
            }
        }

        return true;
    }

    private function postRetrieve($arFields)
    {
        $headers = [
            'Accept'              => '*/*',
            //'x-requested-with'    => 'XMLHttpRequest',
            'x-sec-clge-req-type' => 'ajax',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'no-cache',
            'Content-Type'        => 'application/x-www-form-urlencoded; charset=UTF-8',
        ];

        $form = [
            "isHomePageSearch"         => "false",
            "pnr"                      => $arFields["ConfNo"],
            "lastName"                 => $arFields["LastName"],
            "isManageBooking"          => "true",
            "isCheckIn"                => "false",
            "_eventId_validatePNR"     => "",
            "ajaxError"                => "ajaxError",
            "ajaxian"                  => "true",
            "ismbSecondaryLandingPage" => "true",
            "isCreditCheck"            => "false",
        ];
//        $this->http->FormURL = 'https://www.singaporeair.com/mp-manageBooking-flow.form';
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.singaporeair.com/mbEntry.form', $form, $headers);
        $this->http->RetryCount = 1;
        $response = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'));

        if (isset($response->provider_secret_public)) {
            $token = $this->parseReCaptchaItinerary($response->provider_secret_public);

            if ($token) {
                $this->http->GetURL("https://www.singaporeair.com/_sec/cp_challenge/verify?cpt-token={$token}");
                $this->http->PostURL('https://www.singaporeair.com/mbEntry.form', $form, $headers);
                $response = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'));
            }
        }

        return true;
    }

    private function parseItinerary($response)
    {
        $this->logger->notice(__METHOD__);
        $badItinerary = false;

        if (empty($response->props)) {
            return false;
        }
        $conf = $response->props->pageProps->overviewPageData->pnr ?? null;

//        $this->logger->info('Parse Flight #'.$conf, ['Header' => 3]);
        $f = $this->itinerariesMaster->add()->flight();
        // RecordLocator
        $f->addConfirmationNumber($conf, 'Booking Reference', true);

        $tickets = [];
        $accounts = [];
        $earned = [];

        foreach ($response->props->pageProps->overviewPageData->ticketsAndReceiptsData->flights as $passenger) {
            $number = null;
            // Passengers
            $f->general()->traveller(beautifulName(Html::cleanXMLValue(($passenger->firstName ?? null) . " " . $passenger->lastName)), true);

            if (isset($passenger->eTicketNum)) {
                $tickets[] = $passenger->eTicketNum;
            }
        }

        if (!empty($tickets)) {
            $f->issued()->tickets(array_unique($tickets), false);
        }

        if (!empty($accounts)) {
            $f->program()->accounts(array_unique($accounts), false);
        }

        if (!empty($earned)) {
            if (count($earned) > 1) {
                $this->logger->debug(var_export($earned, true));
                $this->sendNotification('check many earned // ZM');
            } else {
                $f->program()->earnedAwards(array_shift($earned));
            }
        }

        // TripSegments
        $flights = $response->props->pageProps->overviewPageData->flights ?? [];
        $this->logger->debug("Total " . count($flights) . " flights were found");

        foreach ($flights as $flight) {
            $segments = $flight->segments ?? [];
            $this->logger->debug("Total " . count($segments) . " segments were found");

            foreach ($segments as $segment) {
                $seg = $f->addSegment();
                $seg->extra()->status($segment->segmentStatus ?? null, true, true);

                $seg->airline()
                    ->name($this->http->FindPreg('/([A-Z]{1,2})\s*\d+/', false, $segment->flightNumber))
                    ->number($this->http->FindPreg('/[A-Z]{1,2}\s*(\d+)/', false, $segment->flightNumber))
                    ->operator($segment->operatingAirline)
                ;

                if ($flightDuration = $segment->segmentDuration ?? null) {
                    $seg->setDuration(sprintf("%0sh %0sm", floor($flightDuration / 60 / 60), ($flightDuration / 60) % 60));
                }

                $seg->setCabin(beautifulName($segment->cabinClass) ?? null, false, true);
                $seg->setBookingCode($segment->sellingClass ?? null, true, true);

                $seg->setDepTerminal($segment->origin->terminal ?? null, true, true);
                $seg->setDepCode($segment->origin->airportCode);
                $seg->departure()->date2($segment->origin->date);

                $seg->setArrTerminal($segment->destination->terminal ?? null, true, true);
                $seg->setArrCode($segment->destination->airportCode);
                $seg->arrival()->date2($segment->destination->date);

                $seg->setAircraft($segment->aircraftName, true, true);

                if ($seg->getArrCode() === $seg->getDepCode() && $seg->getDepDate() === $seg->getArrDate()) {
                    $this->logger->notice("[{$seg->getAirlineName()} {$seg->getFlightNumber()}]: skip wrong segment: from {$seg->getDepCode()} to {$seg->getArrCode()}");

                    if (count($segments) == 1) {
                        $badItinerary = true;
                    }
                    $f->removeSegment($seg);
                }
            }
        }

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        if ($badItinerary) {
            $this->logger->notice("Remove wrong itinerary from result");
            $this->itinerariesMaster->removeItinerary($f);
        }

        return true;
    }

    private function parseReservation()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        $badItinerary = false;

        if (!$response) {
            return false;
        }
        $conf = $response->bookingReference ?? null;

//        $this->logger->info('Parse Flight #'.$conf, ['Header' => 3]);
        $f = $this->itinerariesMaster->add()->flight();
        // RecordLocator
        $f->addConfirmationNumber($conf, 'Booking Reference', true);

        $tickets = [];
        $accounts = [];
        $earned = [];

        foreach ($response->passengers as $passenger) {
            $number = null;
            // Passengers
            $f->general()->traveller(beautifulName(Html::cleanXMLValue(($passenger->firstName ?? null) . " " . $passenger->lastName)), true);
            // AccountNumbers
            $number = $passenger->frequentFlyerDetails->number ?? null;

            if ($number) {
                $accounts[] = $passenger->frequentFlyerDetails->airlineCode . " " . $number;
            }
            // Frequent flyer number
            if (!empty($this->Properties['AccountNumber']) && $number == $this->Properties['AccountNumber']) {
                if (!empty($passenger->accrualMiles)) {
                    $earned[] = $passenger->accrualMiles;
                }
            }

            $ticket = $passenger->ticketNumbers ?? [];

            if (!empty($ticket)) {
                foreach ($ticket as $t) {
                    $tickets[] = $t;
                }
            }
        }// foreach ($response->passengers as $passenger)

        if (!empty($tickets)) {
            $f->issued()->tickets(array_unique($tickets), false);
        }

        if (!empty($accounts)) {
            $f->program()->accounts(array_unique($accounts), false);
        }

        if (!empty($earned)) {
            if (count($earned) > 1) {
                $this->logger->debug(var_export($earned, true));
                $this->sendNotification('check many earned // ZM');
            } else {
                $f->program()->earnedAwards(array_shift($earned));
            }
        }

        // TripSegments
        $flights = $response->flights ?? [];
        $this->logger->debug("Total " . count($flights) . " flights were found");

        foreach ($flights as $flight) {
            $segments = $flight->flightSegments ?? [];
            $this->logger->debug("Total " . count($segments) . " segments were found");

            foreach ($segments as $segment) {
                $seg = $f->addSegment();

                $seg->airline()
                    ->name($segment->marketingAirline->code)
                    ->operator($segment->operatingAirline->code)
                    ->number($segment->flightNumber);

                $seg->extra()->status($segment->segmentStatusName ?? null, false, true);

                if ($flightDuration = $segment->flightDuration ?? null) {
                    $seg->setDuration(sprintf("%0shrs %0smins", floor($flightDuration / 60 / 60), ($flightDuration / 60) % 60));
                }

                $seg->setCabin($segment->cabinClass ?? null, false, true);
                $seg->setBookingCode($segment->sellingClass ?? null, true, true);

                // DepartureTerminal
                $seg->setDepTerminal($segment->departureTerminal ?? null, true, true);
                // DepName

                // DepCode
                $seg->setDepCode($segment->origin->airportCode);
                // DepDate
                $depDateStr = $segment->departureDateTime;
                $this->logger->debug("[DepDate]: {$depDateStr}");
                $weekDay = $this->http->FindPreg("/\(([^\)]+)/", false, $depDateStr);
                $this->logger->debug("[DepDate]: {$weekDay}");
                $weekDateNumber = WeekTranslate::number1($weekDay, 'en');
                $this->logger->debug("[DepDate]: {$weekDateNumber}");
                $depDate = $this->parseDateUsingWeekDay($this->http->FindPreg("/([^\(]+)/", false, $depDateStr), $weekDateNumber);
                $seg->setDepDate($depDate);

                // ArrivalTerminal
                $seg->setArrTerminal($segment->arrivalTerminal ?? null, true, true);
                // ArrName
                // ArrCode
                $seg->setArrCode($segment->destination->airportCode);
                // ArrDate
                $arrDateStr = $segment->arrivalDateTime;
                $this->logger->debug("[ArrDate]: {$arrDateStr}");
                $weekDay = $this->http->FindPreg("/\(([^\)]+)/", false, $arrDateStr);
                $this->logger->debug("[ArrDate]: {$weekDay}");
                $weekDateNumber = WeekTranslate::number1($weekDay, 'en');
                $this->logger->debug("[ArrDate]: {$weekDateNumber}");
                $arrDate = $this->parseDateUsingWeekDay($this->http->FindPreg("/([^\(]+)/", false, $arrDateStr), $weekDateNumber);
                $seg->setArrDate($arrDate);
                // Aircraft
                $seg->setAircraft(trim($segment->aircraft->name ?? ''), true);

                if ($seg->getArrCode() === $seg->getDepCode() && $seg->getDepDate() === $seg->getArrDate()) {
                    $this->logger->notice("[{$seg->getAirlineName()} {$seg->getFlightNumber()}]: skip wrong segment: from {$seg->getDepCode()} to {$seg->getArrCode()}");

                    if (count($segments) == 1) {
                        $badItinerary = true;
                    }
                    $f->removeSegment($seg);
                }
            }// foreach ($segments as $segment)
        }// foreach ($flights as $flight)

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        if ($badItinerary) {
            $this->logger->notice("Remove wrong itinerary from result");
            $this->itinerariesMaster->removeItinerary($f);
        }

        return true;
    }

    private function parseDateUsingWeekDay(string $dateStr, int $dayNumber, int $yearLimit = 3)
    {
        $date = strtotime($dateStr);

        if ($date === false || $date < strtotime('01/01/2010')) {
            return null;
        }
        $year = (int) date('Y');

        for ($i = 0; $i <= $yearLimit; $i++) {
            $try = strtotime(sprintf('%s %s', $dateStr, $year + $i));

            if ((int) date('N', $try) === $dayNumber) {
                return $try;
            }

            if ($i === 0) {
                continue;
            }
            $try = strtotime(sprintf('%s %s', $dateStr, $year - $i));

            if ((int) date('N', $try) === $dayNumber) {
                return $try;
            }
        }

        return null;
    }

    private function sendFingerPrint($urlRef): ?string
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"  => "*/*",
            "Origin"  => "https://www.singaporeair.com",
            "Referer" => $urlRef,
        ];
        $this->http->GetURL("https://www.singaporeair.com/sngprrdstlxhr.js", $headers);
        $ajax_header = $this->http->FindPreg("/ajax_header:\s*\"(\w+)\"/");
        $pid = $this->http->FindPreg("/path:\s*\"\/sngprrdstl.js\?PID=([^\"]+)\"/");

        if (!$ajax_header) {
            return null;
        }
        $headers['Content-Type'] = "text/plain;charset=UTF-8";
        $headers['X-Distil-Ajax'] = $ajax_header;
        // chrome
        $payload = "p=%7B%22proof%22%3A%22237%3A1608824204508%3AjyCNlHnWsEsIHf1PTYck%22%2C%22fp2%22%3A%7B%22userAgent%22%3A%22Mozilla%2F5.0(WindowsNT10.0%3BWin64%3Bx64)AppleWebKit%2F537.36(KHTML%2ClikeGecko)Chrome%2F87.0.4280.88Safari%2F537.36%22%2C%22language%22%3A%22en-US%22%2C%22screen%22%3A%7B%22width%22%3A1536%2C%22height%22%3A864%2C%22availHeight%22%3A824%2C%22availWidth%22%3A1536%2C%22pixelDepth%22%3A24%2C%22innerWidth%22%3A2048%2C%22innerHeight%22%3A485%2C%22outerWidth%22%3A1536%2C%22outerHeight%22%3A824%2C%22devicePixelRatio%22%3A0.9375%7D%2C%22timezone%22%3A4%2C%22indexedDb%22%3Atrue%2C%22addBehavior%22%3Afalse%2C%22openDatabase%22%3Atrue%2C%22cpuClass%22%3A%22unknown%22%2C%22platform%22%3A%22Win32%22%2C%22doNotTrack%22%3A%22unknown%22%2C%22plugins%22%3A%22ChromePDFPlugin%3A%3APortableDocumentFormat%3A%3Aapplication%2Fx-google-chrome-pdf~pdf%3BChromePDFViewer%3A%3A%3A%3Aapplication%2Fpdf~pdf%3BNativeClient%3A%3A%3A%3Aapplication%2Fx-nacl~%2Capplication%2Fx-pnacl~%22%2C%22canvas%22%3A%7B%22winding%22%3A%22yes%22%2C%22towebp%22%3Atrue%2C%22blending%22%3Atrue%2C%22img%22%3A%22bee56b0a4dcf5bc3040ee3f938f6efd48ad5fd68%22%7D%2C%22webGL%22%3A%7B%22img%22%3A%22bd6549c125f67b18985a8c509803f4b883ff810c%22%2C%22extensions%22%3A%22ANGLE_instanced_arrays%3BEXT_blend_minmax%3BEXT_color_buffer_half_float%3BEXT_disjoint_timer_query%3BEXT_float_blend%3BEXT_frag_depth%3BEXT_shader_texture_lod%3BEXT_texture_compression_bptc%3BEXT_texture_compression_rgtc%3BEXT_texture_filter_anisotropic%3BWEBKIT_EXT_texture_filter_anisotropic%3BEXT_sRGB%3BKHR_parallel_shader_compile%3BOES_element_index_uint%3BOES_fbo_render_mipmap%3BOES_standard_derivatives%3BOES_texture_float%3BOES_texture_float_linear%3BOES_texture_half_float%3BOES_texture_half_float_linear%3BOES_vertex_array_object%3BWEBGL_color_buffer_float%3BWEBGL_compressed_texture_s3tc%3BWEBKIT_WEBGL_compressed_texture_s3tc%3BWEBGL_compressed_texture_s3tc_srgb%3BWEBGL_debug_renderer_info%3BWEBGL_debug_shaders%3BWEBGL_depth_texture%3BWEBKIT_WEBGL_depth_texture%3BWEBGL_draw_buffers%3BWEBGL_lose_context%3BWEBKIT_WEBGL_lose_context%3BWEBGL_multi_draw%22%2C%22aliasedlinewidthrange%22%3A%22%5B1%2C1%5D%22%2C%22aliasedpointsizerange%22%3A%22%5B1%2C1024%5D%22%2C%22alphabits%22%3A8%2C%22antialiasing%22%3A%22yes%22%2C%22bluebits%22%3A8%2C%22depthbits%22%3A24%2C%22greenbits%22%3A8%2C%22maxanisotropy%22%3A16%2C%22maxcombinedtextureimageunits%22%3A32%2C%22maxcubemaptexturesize%22%3A16384%2C%22maxfragmentuniformvectors%22%3A1024%2C%22maxrenderbuffersize%22%3A16384%2C%22maxtextureimageunits%22%3A16%2C%22maxtexturesize%22%3A16384%2C%22maxvaryingvectors%22%3A30%2C%22maxvertexattribs%22%3A16%2C%22maxvertextextureimageunits%22%3A16%2C%22maxvertexuniformvectors%22%3A4096%2C%22maxviewportdims%22%3A%22%5B32767%2C32767%5D%22%2C%22redbits%22%3A8%2C%22renderer%22%3A%22WebKitWebGL%22%2C%22shadinglanguageversion%22%3A%22WebGLGLSLES1.0(OpenGLESGLSLES1.0Chromium)%22%2C%22stencilbits%22%3A0%2C%22vendor%22%3A%22WebKit%22%2C%22version%22%3A%22WebGL1.0(OpenGLES2.0Chromium)%22%2C%22vertexshaderhighfloatprecision%22%3A23%2C%22vertexshaderhighfloatprecisionrangeMin%22%3A127%2C%22vertexshaderhighfloatprecisionrangeMax%22%3A127%2C%22vertexshadermediumfloatprecision%22%3A23%2C%22vertexshadermediumfloatprecisionrangeMin%22%3A127%2C%22vertexshadermediumfloatprecisionrangeMax%22%3A127%2C%22vertexshaderlowfloatprecision%22%3A23%2C%22vertexshaderlowfloatprecisionrangeMin%22%3A127%2C%22vertexshaderlowfloatprecisionrangeMax%22%3A127%2C%22fragmentshaderhighfloatprecision%22%3A23%2C%22fragmentshaderhighfloatprecisionrangeMin%22%3A127%2C%22fragmentshaderhighfloatprecisionrangeMax%22%3A127%2C%22fragmentshadermediumfloatprecision%22%3A23%2C%22fragmentshadermediumfloatprecisionrangeMin%22%3A127%2C%22fragmentshadermediumfloatprecisionrangeMax%22%3A127%2C%22fragmentshaderlowfloatprecision%22%3A23%2C%22fragmentshaderlowfloatprecisionrangeMin%22%3A127%2C%22fragmentshaderlowfloatprecisionrangeMax%22%3A127%2C%22vertexshaderhighintprecision%22%3A0%2C%22vertexshaderhighintprecisionrangeMin%22%3A31%2C%22vertexshaderhighintprecisionrangeMax%22%3A30%2C%22vertexshadermediumintprecision%22%3A0%2C%22vertexshadermediumintprecisionrangeMin%22%3A31%2C%22vertexshadermediumintprecisionrangeMax%22%3A30%2C%22vertexshaderlowintprecision%22%3A0%2C%22vertexshaderlowintprecisionrangeMin%22%3A31%2C%22vertexshaderlowintprecisionrangeMax%22%3A30%2C%22fragmentshaderhighintprecision%22%3A0%2C%22fragmentshaderhighintprecisionrangeMin%22%3A31%2C%22fragmentshaderhighintprecisionrangeMax%22%3A30%2C%22fragmentshadermediumintprecision%22%3A0%2C%22fragmentshadermediumintprecisionrangeMin%22%3A31%2C%22fragmentshadermediumintprecisionrangeMax%22%3A30%2C%22fragmentshaderlowintprecision%22%3A0%2C%22fragmentshaderlowintprecisionrangeMin%22%3A31%2C%22fragmentshaderlowintprecisionrangeMax%22%3A30%2C%22unmaskedvendor%22%3A%22GoogleInc.%22%2C%22unmaskedrenderer%22%3A%22ANGLE(Intel(R)HDGraphics630Direct3D11vs_5_0ps_5_0)%22%7D%2C%22touch%22%3A%7B%22maxTouchPoints%22%3A0%2C%22touchEvent%22%3Afalse%2C%22touchStart%22%3Afalse%7D%2C%22video%22%3A%7B%22ogg%22%3A%22probably%22%2C%22h264%22%3A%22probably%22%2C%22webm%22%3A%22probably%22%7D%2C%22audio%22%3A%7B%22ogg%22%3A%22probably%22%2C%22mp3%22%3A%22probably%22%2C%22wav%22%3A%22probably%22%2C%22m4a%22%3A%22maybe%22%7D%2C%22vendor%22%3A%22GoogleInc.%22%2C%22product%22%3A%22Gecko%22%2C%22productSub%22%3A%2220030107%22%2C%22browser%22%3A%7B%22ie%22%3Afalse%2C%22chrome%22%3Atrue%2C%22webdriver%22%3Afalse%7D%2C%22window%22%3A%7B%22historyLength%22%3A3%2C%22hardwareConcurrency%22%3A8%2C%22iframe%22%3Afalse%2C%22battery%22%3Atrue%7D%2C%22location%22%3A%7B%22protocol%22%3A%22https%3A%22%7D%2C%22fonts%22%3A%22Calibri%3BMarlett%22%2C%22devices%22%3A%7B%22count%22%3A2%2C%22data%22%3A%7B%220%22%3A%7B%22deviceId%22%3A%22%22%2C%22kind%22%3A%22audioinput%22%2C%22label%22%3A%22%22%2C%22groupId%22%3A%22e721f7c3b5bdc558883dad14a341a9f83033a45592c7b07ff645ce7650f3a608%22%7D%2C%221%22%3A%7B%22deviceId%22%3A%22%22%2C%22kind%22%3A%22audiooutput%22%2C%22label%22%3A%22%22%2C%22groupId%22%3A%22cfe2424730182ebb8c904a1517be2b3cd6a872a7bc9945dbe63afc11e891e448%22%7D%7D%7D%7D%2C%22cookies%22%3A1%2C%22setTimeout%22%3A0%2C%22setInterval%22%3A0%2C%22appName%22%3A%22Netscape%22%2C%22platform%22%3A%22Win32%22%2C%22syslang%22%3A%22en-US%22%2C%22userlang%22%3A%22en-US%22%2C%22cpu%22%3A%22%22%2C%22productSub%22%3A%2220030107%22%2C%22plugins%22%3A%7B%220%22%3A%22ChromePDFPlugin%22%2C%221%22%3A%22ChromePDFViewer%22%2C%222%22%3A%22NativeClient%22%7D%2C%22mimeTypes%22%3A%7B%220%22%3A%22application%2Fpdf%22%2C%221%22%3A%22PortableDocumentFormatapplication%2Fx-google-chrome-pdf%22%2C%222%22%3A%22NativeClientExecutableapplication%2Fx-nacl%22%2C%223%22%3A%22PortableNativeClientExecutableapplication%2Fx-pnacl%22%7D%2C%22screen%22%3A%7B%22width%22%3A1536%2C%22height%22%3A864%2C%22colorDepth%22%3A24%7D%2C%22fonts%22%3A%7B%220%22%3A%22Calibri%22%2C%221%22%3A%22Cambria%22%2C%222%22%3A%22Constantia%22%2C%223%22%3A%22DejaVuSerif%22%2C%224%22%3A%22Georgia%22%2C%225%22%3A%22SegoeUI%22%2C%226%22%3A%22Candara%22%2C%227%22%3A%22DejaVuSans%22%2C%228%22%3A%22TrebuchetMS%22%2C%229%22%3A%22Verdana%22%2C%2210%22%3A%22Consolas%22%2C%2211%22%3A%22LucidaConsole%22%2C%2212%22%3A%22DejaVuSansMono%22%2C%2213%22%3A%22CourierNew%22%2C%2214%22%3A%22Courier%22%7D%7D";
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.singaporeair.com/sngprrdstlxhr.js?PID=" . $pid, $payload, $headers);
        $response = $this->http->JsonLog(null, 0);

        if (!isset($response)) {
            sleep(3);
            $this->http->PostURL("https://www.singaporeair.com/sngprrdstlxhr.js?PID=" . $pid, $payload, $headers);
            $response = $this->http->JsonLog(null, 0);

            if (isset($response)) {
                $this->sendNotification('success retry // MI');
            }
        }
        $this->http->RetryCount = 2;

        return $ajax_header;
    }
}
