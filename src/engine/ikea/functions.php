<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerIkea extends TAccountCheckerExtended
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public $regionOptions = [
        ""            => "Select your country",
        "Australia"   => "Australia",
        "Canada"      => "Canada",
        "Ireland"     => "Ireland",
        "Singapore"   => "Singapore",
        "Sweden"      => "Sweden",
        "Switzerland" => "Switzerland",
        "UK"          => "UK",
        "USA"         => "USA",
        "Netherlands" => "Netherlands",
    ];

    private $idp_reguser = null;
    /** @var CaptchaRecognizer */
    private $recognizer;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        $this->logger->notice("Region => {$this->AccountFields['Login2']}");
        $this->http->SetProxy($this->proxyReCaptcha());

        switch ($this->AccountFields['Login2']) {
            case 'Netherlands':
                $this->getCookiesFromSelenium('https://www.ikea.com/nl/en/profile/login'); // long URL like Ireland result in redirects

                return true;

            case 'Singapore':
                $this->http->GetURL("https://family.ikea.com.sg/login");

                if (!$this->http->FindNodes("//input[@id = 'mobilenuminput']/@id")) {
                    return $this->checkErrors();
                }

                $captcha = $this->parseCaptcha();

                if (!$captcha) {
                    return false;
                }

                $data = [
                    "searchValue"  => $this->AccountFields['Login'],
                    "password"     => $this->AccountFields['Pass'],
                    "loginSource"  => "Online",
                    "rememberMe"   => true,
                    "captchaToken" => $captcha,
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://family.ikea.com.sg/api/sea/family/LoginMember.php", json_encode($data));
                $this->http->RetryCount = 2;

                break;

            case 'Ireland':
                if (empty($url)) {
                    // $url = "https://www.ikea.com/ie/en/profile/#/login";
                    $url = "https://ie.accounts.ikea.com/authorize?client_id=sa3xnsi140oH8M2QavGN26n5BLF1lWkr&redirect_uri=https%3A%2F%2Fwww.ikea.com%2Fie%2Fen%2Fprofile%2Flogin%2F&response_type=code&ui_locales=en-IE&code_challenge=_fron8e7DchYH5ziXSvr0jiypYcpBpFr1Bkm3gIsdT0&code_challenge_method=S256&scope=openid%20profile%20email%20offline_access&audience=https%3A%2F%2Fretail.api.ikea.com&registration=%7B%7D&consumer=OWF&state=ouMyXnbs_AqoOtfgzuqqv8j9MxoZi-vM&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xOS4wIn0%3D";
                }
                // no break
            case 'Australia':
                if (empty($url)) {
                    // $url = "https://www.ikea.com/webapp/wcs/stores/servlet/UpdateUser?storeId=18&langId=-26";
                    $url = "https://au.accounts.ikea.com/authorize?client_id=HXUSz2Yppi5snAqTk9ikVOP5s7WdabJR&redirect_uri=https%3A%2F%2Fwww.ikea.com%2Fau%2Fen%2Fprofile%2Flogin%2F&response_type=code&ui_locales=en-AU&code_chalenge=FWloO8ZBBcSoJ68A-5olFMxA1wHp_JvPbtNPQu6CWc8&code_chalenge_method=S256&scope=openid%20profile%20email&audience=https%3A%2F%2Fretail.api.ikea.com&registration=%7B%7D&consumer=OWF&state=V6d2Utn00MOQUybElL2x_qPM6T7PXKlG&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xNS4wIn0%3D";
                }
                // no break
            case 'Canada':
                if (empty($url)) {
//                    $url = "https://secure.ikea.com/webapp/wcs/stores/servlet/UpdateUser?storeId=3&langId=-15";
                    $url = "https://ca.accounts.ikea.com/authorize?client_id=jUflsyuJkwbC0RQg1i8bgo1dyld6NM5d&redirect_uri=https%3A%2F%2Fwww.ikea.com%2Fca%2Fen%2Fprofile%2Flogin%2F&response_type=code&ui_locales=en-CA&code_chalenge=l8v1GQ45t9Qvg03Wk4VDA1q013iElQtCyK4zqj_YG30&code_chalenge_method=S256&scope=openid%20profile%20email&audience=https%3A%2F%2Fretail.api.ikea.com&registration=%7B%7D&consumer=OWF&state=t4uO0coVKfoOISRgv6OQfkyUzMZplD6D&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xNS4wIn0%3D";
//                    $AuthClient = "eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTMuMiJ9";
                }
                // no break
            case 'UK':
                if (empty($url)) {
                    $url = "https://gb.accounts.ikea.com/authorize?client_id=gqzXNCUVxgtykE7iLmA8VLJtz5MbRFh0&redirect_uri=https%3A%2F%2Fwww.ikea.com%2Fgb%2Fen%2Fprofile%2Flogin%2F&response_type=code&ui_locales=en-GB&code_chalenge=AEnoBhUBVh5ZTh2kUnG_3BE8V_tiMd_imerkUCcgFyo&code_chalenge_method=S256&scope=openid%20profile%20email&audience=https%3A%2F%2Fretail.api.ikea.com&consumer=OWF&state=tZxHm8Dhmm6Le41HwMRV4-RiVFVExG_o&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xMi4yIn0%3D";
                }
                // no break
            case 'Sweden':
                if (empty($url)) {
//                    $url = "https://secure.ikea.com/webapp/wcs/stores/servlet/UpdateUser?storeId=2&langId=-11";
                    $url = "https://se.accounts.ikea.com/authorize?client_id=PnoYxSpbEzktCnCV28q0L9slqW7879ln&redirect_uri=https%3A%2F%2Fwww.ikea.com%2Fse%2Fsv%2Fprofile%2Flogin%2F&response_type=code&ui_locales=sv-SE&code_chalenge=GS4yZnYW6MVldu8LNIgkN3ZeWFii_A8FWcZ6M0k8nDI&code_chalenge_method=S256&scope=openid%20profile%20email&audience=https%3A%2F%2Fretail.api.ikea.com&registration=%7B%7D&consumer=OWF&state=17QgYKQ5VBsCQb.yhFIZK9flu_xgz3Pu&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xNS4wIn0%3D";
                }
                // no break
            case 'Switzerland':
                if (empty($url)) {
//                    $url = "https://secure.ikea.com/webapp/wcs/stores/servlet/UpdateUser?storeId=6&langId=-17";
                    $url = "https://ch.accounts.ikea.com/authorize?client_id=bs5g5nRzaloE8XlV2UrLC1Vx0r4Y0tpU&redirect_uri=https%3A%2F%2Fwww.ikea.com%2Fch%2Fde%2Fprofile%2Flogin%2F&response_type=code&ui_locales=de-CH&code_chalenge=1-KHuRKWAOMQgWPPZ60WiNeyV3CEI7ag6q_nE53g-us&code_chalenge_method=S256&scope=openid%20profile%20email&audience=https%3A%2F%2Fretail.api.ikea.com&registration=%7B%7D&consumer=OWF&state=01oEm0JD98TpneAYZ.j1E66Rf8FgUG8O&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xNS4wIn0%3D";
                }
                // no break
            default:
                if (empty($url)) {
                    $this->http->SetProxy($this->proxyReCaptcha());
//                    $url = "https://secure.ikea.com/webapp/wcs/stores/servlet/UpdateUser?storeId=12&langId=-1";
                    $url = "https://us.accounts.ikea.com/authorize?client_id=ADzEosFiFb9v9HujH78E5kV2267U1vN4&redirect_uri=https%3A%2F%2Fwww.ikea.com%2Fus%2Fen%2Fprofile%2Flogin%2F&response_type=code&ui_locales=en-US&code_chalenge=er9kIdhXpWqkVGUxYn_U5AewDap2cEDKQcxePFsz4jI&code_chalenge_method=S256&scope=openid%20profile%20email&audience=https%3A%2F%2Fretail.api.ikea.com&registration=%7B%7D&consumer=OWF&state=sAXsOag7q-YwRiYu67pJBRBYs7eeZkC1&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xNS4wIn0%3D";
                }
                $this->http->GetURL($url);
                $clientId = $this->http->FindPreg('/client=(.+?)&/', false, $this->http->currentUrl());
                $state = $this->http->FindPreg('/state=(.+?)&/', false, $this->http->currentUrl());
                $redirect_uri = $this->http->FindPreg('/redirect_uri=(.+?)&/', false, $this->http->currentUrl());
                $_csrf = $this->http->getCookieByName('_csrf', null, '/usernamepassword/login');

                if (!isset($clientId, $state, $_csrf)) {
                    return $this->checkErrors();
                }

//                if (in_array($this->AccountFields['Login2'], ['UK', 'Canada', 'USA', 'Australia', 'Switzerland'])) {
//                    $this->sendSensorData();
                    $this->getCookiesFromSelenium($url);

                    return true;

                    if ($this->http->FindPreg('/"name":"ValidationError"/')) {
                        return true;
                    }
//                }

                $this->http->RetryCount = 0;
                $data = [
                    'audience'      => 'https://retail.api.ikea.com',
                    'client_id'     => $clientId,
                    'connection'    => 'Username-Password-Authentication',
                    'password'      => $this->AccountFields['Pass'],
                    'redirect_uri'  => urldecode($redirect_uri),
                    'response_type' => 'code',
                    'scope'         => 'openid profile email',
                    'state'         => $state,
                    'tenant'        => 'ikea-prod-' . $this->http->FindPreg("/^([^\.]+)/", false, $this->http->getCurrentHost()),
                    'username'      => $this->AccountFields['Login'],
                    '_csrf'         => $_csrf,
                    '_intstate'     => 'deprecated',
                ];
                $headers = [
                    'Content-Type'        => 'application/json',
                    'Auth0-Client'        => 'eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTMuMiJ9',
                    'Akamai-BM-Telemetry' => '7a74G7m23Vrp0o5c9354451.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:102.0) Gecko/20100101 Firefox/102.0,uaend,11059,20100101,en-US,Gecko,5,0,0,0,407658,9132752,1536,871,1536,960,1536,461,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6086,0.810385311405,828414566375.5,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,0,2,0,0,864,-1,1;1,2,0,1,883,-1,1;-1,2,-94,-108,0,1,23717,-2,0,0,864;1,3,23718,-2,0,0,864;2,1,23746,…2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,25610054;2077731666;dis;,7;true;true;true;-300;true;30;30;true;false;1-1,2,-94,-80,5320-1,2,-94,-116,27398220-1,2,-94,-118,322277-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) HD Graphics 400,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;4;11;0',
                ];
                $this->http->PostURL('https://' . $this->http->getCurrentHost() . '/usernamepassword/login', json_encode($data), $headers);
                $this->http->RetryCount = 2;

                break;
        }// switch ($this->AccountFields['Login2'])

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case 'UK':
            case 'Ireland':
                // Sorry, the IKEA website is temporarily unavailable.
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, the IKEA website is temporarily unavailable.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // We're doing some work on this page, getting it ready for you. So please check back later!
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'doing some work on this page, getting it ready for you. So please check back later!')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Australia':
                break;

            case 'Switzerland':
                break;

            case 'Singapore':
                break;

            case 'Canada':
                break;

            case 'Sweden':
                break;

            default:
                break;
        }// switch ($this->AccountFields['Login2'])
        // Unfortunately a technical error has prevented us from displaying the page you requested.
        if ($this->http->FindSingleNode("//td[contains(text(), 'Unfortunately a technical error has prevented us from displaying the page you requested.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->BodyContains('<h2>The server encountered a temporary error and could not complete your request.<p>Please try again in 30 seconds.</h2>', false)) {
            throw new CheckRetryNeededException();
        }

        if ($this->http->BodyContains('<TITLE>Access Denied', false)) {
            $this->logger->error($this->DebugInfo = "Request blocked");

            throw new CheckRetryNeededException(3);
        }

        return false;
    }

    public function Login()
    {
        if ($this->idp_reguser) {
            return true;
        }

        /*
        if (
            !in_array($this->AccountFields['Login2'], [
                'Singapore',
                'Ireland',
                'UK',
                'Canada',
                'USA',
                'Switzerland',
                'Australia',
            ])
            && !$this->http->PostForm()
            && $this->http->Response['code'] != 401
        ) {
            return false;
        }
        */

        switch ($this->AccountFields['Login2']) {
            case 'Singapore':
                if ($authorization = $this->http->FindPreg('/^"([\d\w]+)"$/')) {
                    $this->captchaReporting($this->recognizer);
                    $this->http->setDefaultHeader("authorization", $authorization);

                    return true;
                }
                // Password/Membership ID is incorrect
                if ($this->http->FindPreg('/^""$/') && $this->http->Response['code'] == 401
                    || $this->http->BodyContains('\"title\":\"Unauthorized\",\"status\":401', false)
                ) {
                    throw new CheckException("Password/Membership ID is incorrect", ACCOUNT_INVALID_PASSWORD);
                }
                // Invalid captcha
                if ($this->http->Response['code'] == 400
                    && $this->http->BodyContains('"Invalid reCAPTCHA"', false)
                ) {
                    $this->captchaReporting($this->recognizer, false);

                    throw new CheckRetryNeededException(2, 0);
                }

                break;

            case 'Switzerland':
//                // Find sign out link
//                if ($this->http->FindNodes("//a[contains(@href, 'Logoff')]/@href")) {
//                    return true;
//                }
//                // Entweder hat dein Benutzername oder dein Passwort nicht funktioniert.
//                if ($message = $this->http->FindPreg('/"description":"Wrong email or password."/')) {
//                    throw new CheckException('Entweder hat dein Benutzername oder dein Passwort nicht funktioniert.', ACCOUNT_INVALID_PASSWORD);
//                }
//
//                if (strstr($this->http->currentUrl(), 'https://ch.accounts.ikea.com/resources/ch/static/html/hard_email_verification_error.html')) {
//                    $this->throwProfileUpdateMessageException();
//                }
//
//                break;

            case 'Sweden':
            case 'Australia':
            case 'Canada':
            case 'UK':
            case 'Ireland':
            default:
                if ($this->http->ParseForm("hiddenform")) {
                    $this->http->PostForm();
                }

                $code = $this->http->FindPreg("/\?code=([^&]+)/", false, $this->http->currentUrl());

                if ($code) {
                    $country = 'us';
                    $language = 'en';
                    $client_id = "ADzEosFiFb9v9HujH78E5kV2267U1vN4";
                    $code_verifier = "0dLXuKKYHuYlSDSrXWWGbgWQNhAr-kLT2eu42MmEUT4";

                    if ($this->AccountFields['Login2'] == 'Australia') {
                        $country = 'au';
                        $client_id = "HXUSz2Yppi5snAqTk9ikVOP5s7WdabJR";
                        $code_verifier = "udwHri2v4gfL7LT_01i1YqyP_guhpXzi7fXPEpK02L4";
                    }

                    if ($this->AccountFields['Login2'] == 'UK') {
                        $country = 'gb';
                        $client_id = "gqzXNCUVxgtykE7iLmA8VLJtz5MbRFh0";
                        $code_verifier = "mzGAtFKe2TSkfXtToIHwTbMB0C_RzNzxZVq2mbjPQc0";
                    }

                    if ($this->AccountFields['Login2'] == 'Canada') {
                        $country = 'ca';
                        $client_id = "jUflsyuJkwbC0RQg1i8bgo1dyld6NM5d";
                        $code_verifier = "ig9WnVU_bL86uNyrHbKtusstt2ZlEpThoL28dWKBBDk";
                    }

                    if ($this->AccountFields['Login2'] == 'Sweden') {
                        $country = 'se';
                        $client_id = "PnoYxSpbEzktCnCV28q0L9slqW7879ln";
                        $code_verifier = "Jkxv5KJ-NIZYh21mPsMa-Pzj85SfX8dm4Ro8duxKGno";
                    }

                    if ($this->AccountFields['Login2'] == 'Switzerland') {
                        $country = 'ch';
                        $client_id = "bs5g5nRzaloE8XlV2UrLC1Vx0r4Y0tpU";
                        $code_verifier = "YuZg4OBO1BysN4Pz5-018F_ugpgjrl2Kiet8yu2yC-o";
                    }

                    $data = [
                        "grant_type"    => "authorization_code",
                        "client_id"     => $client_id,
                        "code_verifier" => $code_verifier,
                        "code"          => $code,
                        "redirect_uri"  => "https://www.ikea.com/{$country}/{$language}/profile/login/",
                        "scope"         => "openid profile email",
                    ];
                    $headers = [
                        'Accept'       => '*/*',
                        'Content-Type' => 'application/json',
                    ];
                    $this->http->PostURL("https://{$country}.accounts.ikea.com/oauth/token", json_encode($data), $headers);
                    $response = $this->http->JsonLog();

                    if (isset($response->access_token)) {
                        return true;
                    }
                }

                // Find sign out link
                if ($this->http->FindNodes("//a[contains(@href, 'Logoff')]/@href")) {
                    return true;
                }
                // Either your user name or password didn't work
                if ($message = $this->http->FindPreg('/"description":"Wrong email or password."/')) {
                    throw new CheckException("Either your user name or password didn't work", ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindPreg('/description":"This login attempt has been blocked because the password you\'re using was previously disclosed through a data breach \(not in this application\)\. Please check your email for more information\.","name":"AnomalyDetected","code":"password_leaked"/')) {
                    throw new CheckException("For your own security, IKEA needs you to create a new password. Please reset password.", ACCOUNT_LOCKOUT);
                }

                // Please confirm your email address!
                if (
                    strstr($this->http->currentUrl(), '/static/html/hard_email_verification_error.html?')
                    || strstr($this->http->currentUrl(), '/identity/reauthenticate/?token')
                ) {
                    throw new CheckException("Please confirm your email address!", ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindSingleNode('//p[contains(text(), "Something went wrong while trying to log you in. Please try to log in again.")]')) {
                    throw new CheckRetryNeededException(2, 7, $message);
                }

                break;
        }// switch ($this->AccountFields['Login2'])

        return $this->checkErrors();
    }

    public function Parse()
    {
        $number = null;

        switch ($this->AccountFields['Login2']) {
            case 'Ireland':
                // Membership number
                $number = $this->http->FindSingleNode("//p[contains(text(), 'Membership number')]/following-sibling::p[1]", null, false);
                $this->SetProperty('Number', $number);
                // Name
                $name = trim($this->http->FindSingleNode("//p[contains(text(), 'First name')]/following-sibling::p[1]") . " " . $this->http->FindSingleNode("//p[contains(text(), 'Surname') or contains(text(), 'Last name')]/following-sibling::p[1]"));
                $this->SetProperty('Name', beautifulName($name));

                if (!$number && $this->http->FindSingleNode("//h3[contains(text(), 'Join IKEA Family') or contains(text(), 'Join IKEA FAMILY')]")) {
                    $this->SetBalanceNA();
                }
                // We're doing some work on this page, getting it ready for you. So please check back later!
                elseif ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re doing some work on this page, getting it ready for you. So please check back later!")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Singapore':
                $this->http->GetURL("https://family.ikea.com.sg/api/sea/family/GetProfile.php");
                $response = $this->http->JsonLog($this->http->JsonLog());
                // Name
                $this->SetProperty('Name', beautifulName($response->firstName . " " . $response->lastName));
                // Number
                $this->SetProperty('Number', $response->membershipNumber);
                // Status
                $this->SetProperty('Status', $response->scheme ?? null);

                $this->http->GetURL("https://family.ikea.com.sg/api/sea/family/GetPointsBalance.php");
                $response = $this->http->JsonLog($this->http->JsonLog());
                // Balance - Points available for redemption
                $this->SetBalance($response->currentBalance ?? null);
                // Expiration Date - Bonus Points that will expire on 31/12/2019
                $nodes = $response->pointsExpiring ?? [];
                $minDate = strtotime('01/01/3018');
                $expNode = null;

                foreach ($nodes as $node) {
                    $expDate = strtotime($node->expiryDate);

                    if ($expDate && $expDate < $minDate) {
                        $this->logger->debug("Expiration Date: $expDate");
                        $minDate = $expDate;
                        $this->SetExpirationDate($minDate);
                        $this->SetProperty('ExpiringBalance', $node->totalExpiring ?? null);
                    }// if ($expDate && $expDate < $minDate)
                }// foreach ($nodes as $node)

                break;

            case 'Netherlands':
                if (!isset($country)) {
                    $country = 'nl';
                }

                // no break
            case 'UK':
                if (!isset($country)) {
                    $country = 'gb';
                }
                // no break
            case 'Australia':
                if (!isset($country)) {
                    $country = 'au';
                }

                // no break
            case 'Sweden':
                if (!isset($country)) {
                    $country = 'se';
                }
                // no break

            case 'Switzerland':
                if (!isset($country)) {
                    $country = 'ch';
                }

                // no break
            case 'Canada':
                if (!isset($country)) {
                    $country = 'ca';
                }

                // no break
            default:
                if (!isset($country)) {
                    $country = 'us';
                }

                $response = $this->http->JsonLog(null, 0);
                $accessToken = $response->access_token ?? $this->idp_reguser;
                $headers = [
                    "Auth0-Client"  => "eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xNS4wIn0=",
                    "Authorization" => "Bearer {$accessToken}",
                ];
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://{$country}.accounts.ikea.com/userinfo", $headers);
                $response = $this->http->JsonLog(null, 3, true);
                // Name
                $name = ArrayVal($response, 'name', null);
                $this->SetProperty('Name', beautifulName($name));

                if ($this->http->FindPreg("/\"https:\/\/accounts\.ikea\.com\/loyaltyPrograms\":\[\],/")) {
                    $this->SetWarning(self::NOT_MEMBER_MSG);

                    return;
                }

                $memberId = ArrayVal($response, 'https://accounts.ikea.com/memberId', null);

                if (empty($memberId)) {
                    return;
                }
                $headers2 = [
                    "Origin"                    => "https://www.ikea.com",
                    "Accept"                    => "*/*",
                    "Referer"                   => "https://www.ikea.com/",
                    "Authorization"             => $accessToken,
                ];
                $this->http->setHttp2(true);
                $this->http->GetURL("https://api.wlo.ingka.com/loyalty/v1/{$country}/customer-data", $headers2);
                $response = $this->http->JsonLog(null, 4);

                if (empty($response->customers[0]->loyaltyMemberships[0]->membershipCards[0]->cardNumber) /*|| count($response->customers[0]->loyaltyMemberships[0]->membershipCards) > 1*/) {
                    $this->logger->error("something went wrong");

                    if (isset($response->name) && $response->name == "Internal Server Error.") {
                        throw new CheckException("An error ocurred while getting your profile. Please try again. If the problem persists, contact the Customer Support Center.", ACCOUNT_PROVIDER_ERROR);
                    }

                    // AccountID: 4005908
                    if (
                        isset($response->customers[0]->loyaltyMemberships)
                        && $response->customers[0]->loyaltyMemberships === []
                        && !empty($name)
                    ) {
                        $this->SetBalanceNA();
                    }

                    return;
                }
                // Number
                $number = $response->customers[0]->loyaltyMemberships[0]->membershipCards[0]->cardNumber ?? null;
                $this->SetProperty('Number', $number);

                break;
        }// switch ($this->AccountFields['Login2'])

        if (
            $number !== null && !empty($name)
            && !in_array($this->AccountFields['Login2'], ['UK', 'Australia'])
        ) {
            $this->SetBalanceNA();
        } elseif (isset($accessToken)) {
            /**
             * TODO: if it breaks, get params from https://www.ikea.com/global/assets/rke/rewards/config/default.json.
             */
            $headers = [
                "Auth0-Client"     => "eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xNS4wIn0=",
                "Authorization"    => "Bearer {$accessToken}",
                "x-client-id"      => "fbe97bda-0003-4c45-894a-c6d9b89ce11c",
                "rexConsumerId"    => "rexFE-721b",
                "x-correlation-id" => "da1201be-3f21-4ad5-bed8-25270018c332",
            ];

            $this->http->GetURL('https://web-api.ikea.com/customer-engagement/reward-keys-experience/v2/customer/balance?keyExpirationDetail=true', $headers);

            $balanceData = $this->http->JsonLog();

            if (isset($balanceData->keysBalance)) {
                $this->SetBalance($balanceData->keysBalance);
            }

            if (isset($balanceData->keysExpirationDetails) && count($balanceData->keysExpirationDetails)) {
                $this->sendNotification("refs #24824 - need to check keysExpirationDetails // IZ");
            }
        }
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);

        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9354451.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:102.0) Gecko/20100101 Firefox/102.0,uaend,11059,20100101,en-US,Gecko,5,0,0,0,407658,9132752,1536,871,1536,960,1536,461,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6086,0.811729005405,828414566375.5,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://gb.accounts.ikea.com/login?state=hKFo2SBUMlpHa3VGZ3N1UllneFN6Z29DU3RzUmQ1bzlJUDV3cqFupWxvZ2luo3RpZNkgbEZUVzhHYloyN3AxdWttRllsa3Bia3k2ZW1jS2hULVCjY2lk2SBncXpYTkNVVnhndHlrRTdpTG1BOFZMSnR6NU1iUkZoMA&client=gqzXNCUVxgtykE7iLmA8VLJtz5MbRFh0&protocol=oauth2&redirect_uri=https%3A%2F%2Fwww.ikea.com%2Fgb%2Fen%2Fprofile%2Flogin%2F&response_type=code&ui_locales=en-GB&code_chalenge=AEnoBhUBVh5ZTh2kUnG_3BE8V_tiMd_imerkUCcgFyo&code_chalenge_method=S256&scope=openid%20profile%20email&audience=https%3A%2F%2Fretail.api.ikea.com&consumer=OWF&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xMi4yIn0%3D-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1656829132751,-999999,17724,0,0,2954,0,0,3,0,0,F244D682A0C3F9ECD9E1243B316F4E2E~-1~YAAQy2vcFyPRV7yBAQAAlT+2wghgXMCJDhOxMFCu2ShC7wT9no4mzhczHSUFPFCPzt1lwlzKiSFFPQ/h+nL3cZf8z5GNS13rYkpjHw4tZOMS2/gWyRIjZQOR5livrx2+uOKC64z+3DjXP/wZfL83moHuZyKDDdywGDUoXN3VOQwRUM0k1CqBZrGPjcLHc+D1KZUU0iOWFzvw214i/eLs+0PCADGyMMPOfQUWolwmHGSVxFx/YDaTjNQijhdyToDvK1HDkWnw04qBJFZqUhuaI+TnoJRS5gmUsna7EMFG7tOY05KoIRceeuqZLV6ch4hrMFIdL/AmbYNHwJzYnRwh82x7nwdjbjGI+q650mhncUfBPuLrU9wQq2WsMkuBJiktEy5Me16q0sEy~-1~-1~1656832710,37240,-1,-1,26067385,PiZtE,47400,79,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,27398220-1,2,-94,-118,133333-1,2,-94,-129,-1,2,-94,-121,;10;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9354451.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:102.0) Gecko/20100101 Firefox/102.0,uaend,11059,20100101,en-US,Gecko,5,0,0,0,407658,9132752,1536,871,1536,960,1536,461,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6086,0.15739305378,828414566375.5,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,0,2,0,0,864,-1,0;1,2,0,1,883,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://gb.accounts.ikea.com/login?state=hKFo2SBUMlpHa3VGZ3N1UllneFN6Z29DU3RzUmQ1bzlJUDV3cqFupWxvZ2luo3RpZNkgbEZUVzhHYloyN3AxdWttRllsa3Bia3k2ZW1jS2hULVCjY2lk2SBncXpYTkNVVnhndHlrRTdpTG1BOFZMSnR6NU1iUkZoMA&client=gqzXNCUVxgtykE7iLmA8VLJtz5MbRFh0&protocol=oauth2&redirect_uri=https%3A%2F%2Fwww.ikea.com%2Fgb%2Fen%2Fprofile%2Flogin%2F&response_type=code&ui_locales=en-GB&code_chalenge=AEnoBhUBVh5ZTh2kUnG_3BE8V_tiMd_imerkUCcgFyo&code_chalenge_method=S256&scope=openid%20profile%20email&audience=https%3A%2F%2Fretail.api.ikea.com&consumer=OWF&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xMi4yIn0%3D-1,2,-94,-115,1,32,32,0,0,0,0,516,0,1656829132751,3,17724,0,0,2954,0,0,517,0,0,F244D682A0C3F9ECD9E1243B316F4E2E~-1~YAAQy2vcF37RV7yBAQAAKEG2wgju4dT4cLIOoDeplH026NdimeYLOyJH4y5zt0/8GexcARl3ksMCNHOBx9u16sW8LzkmQobFjMesh9Bw71jnH3Tl290yKFHolcKkTpWAc1SmC7gk3IxcSj1sZPOrefga80EHvT4FT7hZBRNrco4DxtPoQFJfbWxxaWya/asyqa+ckvDMarwwvIKtRSnP2+25Aw5c+eKzf/clxJeoFGCiBXQLlwjWP8V+EJRb9KgheFqDpKmS1TfTdjDp10Txu/3udAbW+8vM3m+YGAou/4zpHQfoxdOMeLCnfxhOQZm8+s0/ztGhSijw6X4R1609X07A9zMOeAyDflrADqtLfHcYv7ng/aNYWzn8FzPg6YVIwFwuP1DH5dby~-1~-1~1656832625,37202,213,-977199188,26067385,PiZtE,32486,55,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,25610054;2077731666;dis;,7;true;true;true;-300;true;30;30;true;false;1-1,2,-94,-80,5320-1,2,-94,-116,27398220-1,2,-94,-118,136242-1,2,-94,-129,,,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,,,,0-1,2,-94,-121,;3;11;0",
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
            "Content-type"  => "application/json",
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

    private function getCookiesFromSelenium($url)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->useGoogleChrome();

            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

//            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL($url);

            if ($loginWithPass = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log in with your password") or contains(text(), "Sign in with password")] | //button[@id = "loginWithEmail"] | //a[contains(text(), "Log in with password")]'), 5)) {
                $loginWithPass->click();
            }

            $login = $selenium->waitForElement(WebDriverBy::id('username'), 5);
            $pass = $selenium->waitForElement(WebDriverBy::id('password'), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$pass) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->logger->debug("enter Login");
            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);

            $selenium->driver->executeScript('let rememberMe = document.querySelector(\'#remember-me\'); if (rememberMe) rememberMe.checked = true;');

            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[
                contains(., "Continue")
                or contains(., "Login")
                or contains(., "Log in")
                or contains(., "Weiter")
                or contains(., "Fortsätt")
            ]'), 0);
            $this->savePageToLogs($selenium);

            $this->logger->debug("click");
            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "member-card__title")]
                | //p[contains(text(), "Please check to make sure you used the right email address and password")]
                | //span[contains(normalize-space(text()), "An error occurred while getting your profile.")]
                | //span[contains(normalize-space(text()), "Please enter a valid email address or verified mobile number")]
                | //span[contains(normalize-space(text()), "Ange en giltig e-postadress eller verifierat mobilnummer")]
                | //span[contains(normalize-space(text()), "You must enter a valid email address")]
                | //p[contains(normalize-space(text()), "For your own security, IKEA needs you to create a new password.")]
                | //p[contains(normalize-space(text()), "Check your email for your verification link")]
                | //p[contains(normalize-space(text()), "Try to reset your password, it may solve your log in problem")]
                | //p[contains(normalize-space(text()), "We suggest resetting your password to get back into your account")]
            '), 10);
            $this->savePageToLogs($selenium);

            if ($message = $this->http->FindSingleNode('
                    //span[contains(normalize-space(text()), "An error occurred while getting your profile.")]
                    | //p[contains(normalize-space(text()), "Check your email for your verification link")]
                    | //span[contains(normalize-space(text()), "Ange en giltig e-postadress eller verifierat mobilnummer")]
                ')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode('
                    //span[contains(normalize-space(text()), "Please enter a valid email address or verified mobile number")]
                    | //span[contains(normalize-space(text()), "You must enter a valid email address")]
                ')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindSingleNode('//p[contains(normalize-space(text()), "For your own security, IKEA needs you to create a new password.")]')) {
                throw new CheckException("For your own security, IKEA needs you to create a new password. Please reset password.", ACCOUNT_LOCKOUT);
            }

            /** @var SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
//                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if (strpos($xhr->request->getUri(), '.accounts.ikea.com/oauth/token') !== false) {
                    $this->logger->debug("xhr response {$n} body: " . htmlspecialchars(json_encode($xhr->response->getBody())));
                    $responseProfileData = json_encode($xhr->response->getBody());
                }

                if (strpos($xhr->request->getUri(), 'accounts.ikea.com/usernamepassword/login') !== false) {
//                    $this->logger->debug("xhr response {$n}");
                    $this->logger->debug("xhr response {$n} body: " . htmlspecialchars(json_encode($xhr->response->getBody())));
                    $responseData = json_encode($xhr->response->getBody());
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'idp_reguser') {
                    $this->idp_reguser = $cookie['value'];
                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);
            }

            if (!empty($responseProfileData)) {
                $this->http->SetBody($responseProfileData);
            }
        } catch (
            UnknownServerException
            | SessionNotCreatedException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\InvalidSessionIdException
            $e
        ) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//script[starts-with(@src, "https://www.google.com/recaptcha/api.js?render=")]/@src', null, true, '/render=(\w+)/');
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            'pageurl'   => $this->http->currentUrl(),
            'proxy'     => $this->http->GetProxy(),
            'version'   => 'v3',
            'action'    => 'submit',
            'min_score' => 0.3,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
