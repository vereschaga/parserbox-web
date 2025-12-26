<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerKlm extends TAccountChecker
{
    use ProxyList;
    use PriceTools;
    use SeleniumCheckerHelper;

    private $airCodes;

    // private $badAccount = false;
    private $currentItin = 0;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $firstName = null;
    private $lastName = null;
    private $sha256HashReservations = '4e3f2e0b0621bc3b51fde95314745feb4fd1f9c10cf174542ab79d36c9dd0fb2';
    private $sha256HashReservation = 'c24b8f630b0b3a7f5d9013205e684c6bc381c3d850dcbcbc854363c0db212269';

    private $headers = [
        'AFKL-Travel-Country' => 'US',
        'country'             => 'US',
        'AFKL-TRAVEL-Host'    => 'kl',
        'Accept'              => 'application/json, text/plain, *',
        'Content-Type'        => 'application/json',
        'Referer'             => 'https://login.klm.com/login/account',
        "Accept-Encoding"     => "gzip, deflate, br",
    ];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerKlmSelenium.php";

        return new TAccountCheckerKlmSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (isset($this->State['2fa_hold_session'], $this->Step) && $this->Step == 'Question') {
            unset($this->State['2fa_hold_session']);
            $this->UseSelenium();
            $this->useGoogleChrome();
            $this->useCache();
            $this->http->saveScreenshots = true;

            return;
        }

//        $this->http->setHttp2(true); // causing error "Network error 92 - HTTP/2 stream 0 was not closed cleanly"
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.klm.us/endpoint/v1/oauth/login?ref=/profile/flying-blue/dashboard", [], 20);

        // crocked server workaround
        if ($this->http->Response['code'] == 403) {
            $this->http->SetProxy($this->proxyDOP());
            $this->http->GetURL("https://www.klm.us/endpoint/v1/oauth/login?ref=/profile/flying-blue/dashboard", [], 20);
        }

        $this->http->JsonLog();
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg("/\"isLoggedIn\":true,/")) {
            return true;
        }

        return false;
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.klm.com/ams/lsi/endpoint/v1/deeplink?country=us&language=en&target=/profile");

        return true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        /*
        $this->http->GetURL("https://www.klm.us/endpoint/v1/oauth/login?ref=https://www.klm.us/");
        $params = $this->http->JsonLog()->cidLogonParams ?? null;

        $loginEndpoint = $params->loginEndpoint ?? null;
        $pathname = $params->pathname ?? null;
        $clientId = $params->clientId ?? null;
        $redirectSuffix = $params->redirect_uri_suffix ?? null;

        if (!isset($loginEndpoint, $pathname, $clientId, $redirectSuffix)) {
            if ($this->http->Response['code'] === 403) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 1);
            }

            return $this->checkErrors();
        }

        $this->http->GetURL("{$loginEndpoint}{$pathname}?client_id={$clientId}&source=homepage.homepage&loginPathname={$pathname}&brand=kl&locale=US/en-GB&response_type=code&scope=login:afkl&redirect_uri=https://www.klm.us{$redirectSuffix}");
        */
        $this->http->GetURL("https://www.klm.com/endpoint/v1/oauth/redirect?loginPrompt=&source=homepage.homepage&locale=US/en-GB");

        // provider bug fix
        if ($this->http->currentUrl() == 'https://login.klm.com/login/page-not-found') {
            $this->http->GetURL("https://www.klm.com/endpoint/v1/oauth/redirect?loginPrompt=&source=profile&locale=US/en-GB");
        }

        if ($this->http->currentUrl() !== 'https://login.klm.com/login/otp') {
            return $this->checkErrors();
        }

//        if ($this->http->Response['code'] !== 200) {
//            if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Unavailable")]')) {
//                throw new CheckException("Due to technical issues, you may experience difficulties on our website. We're doing our best to resole these as soon as possible. Please note: the app is still working.", ACCOUNT_PROVIDER_ERROR);
//            }
//
//            return $this->checkErrors();
//        }

        $this->selenium();

        if (isset($this->State['2fa_hold_session'])) {
            return false;
        }

        return true;

        /*
        $this->http->GetURL("https://login.klm.com/oauthcust/oauth/authorize?scope=logon-type%3Aafkl&response_type=code&client_id=4pm9rktn3a689dy6593r29vr&state=1037583100636003.6&brand=kl&locale=US%2Fen&minLoa=4&redirect_uri=https%3A%2F%2Fwww.klm.us%2Fendpoint%2Fv1%2Foauth%2Fcontinue%2F");

        $this->http->setDefaultHeader($this->http->FindSingleNode("//meta[@id = 'csrf_header']/@content"), $this->http->FindSingleNode("//meta[@id = 'csrf']/@content"));

        if (!$this->http->FindSingleNode("//meta[@id = 'disableCaptcha' and @content = 'false']/@content")) {
            return $this->checkErrors();
        }
//        $this->http->SetInputValue("username", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
//        $this->http->SetInputValue("g-recaptcha-response", $captcha);
        $data = [
            "username"          => $this->AccountFields['Login'],
            "password"          => $this->AccountFields['Pass'],
            "type"              => "normal",
            "recaptchaResponse" => $captcha,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://login.klm.com/oauthcust/oauth/api/login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;
        */

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
//        $this->http->SetInputValue("g-recaptcha-response", $captcha);
//        $data = [
//            "username"          => $this->AccountFields['Login'],
//            "password"          => $this->AccountFields['Pass'],
//            "type"              => "normal",
//            "recaptchaResponse" => $captcha,
//        ];

        $data = [
            "operationName" => "login",
            "variables"     => [
                "loginParams" => [
                    "loginId"           => $this->AccountFields['Login'],
                    "password"          => $this->AccountFields['Pass'],
                    "persistent"        => false,
                    "recaptchaResponse" => $captcha,
                    "type"              => is_numeric($this->AccountFields['Login']) ? "FLYINGBLUE" : "EMAIL",
                ],
            ],
            "query"         => "query login(\$loginParams: LoginParams) {\n  login(input: \$loginParams) {\n    code\n    redirectUri\n    errors {\n      code\n      description\n      __typename\n    }\n    __typename\n  }\n}\n",
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://login.klm.com/login/gql/gql-login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Login is temporarily unavailable.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Login is temporarily unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Login has been disabled by KLM in response to technical issues.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Login has been disabled by KLM in response to technical issues.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("
                //td[contains(text(), 'Service Unavailable')]
                | //h1[contains(text(), 'Internal Server Error')]
                | //h1[contains(text(), 'Service Unavailable - Zero size object')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        //# An error occurred while processing your request
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The proxy server did not receive a timely response from the upstream server
        if (
            $this->http->FindPreg("/(The proxy server did not receive a timely response from the upstream server\.)/ims")
            || $this->http->FindSingleNode("//*[contains(text(), 'The server is currently unavailable (because it is overloaded or down for maintenance)')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# The server is temporarily unable to service your request. Please try again later.
        if ($message = $this->http->FindPreg("/(The server is temporarily unable to service your request\.\s*Please try again\s*later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
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

//                $this->http->GetURL("https://account.bluebiz.com/ctms/auth/asfc/callback?code={$response->data->login->code}");

//                return $this->loginSuccessful();//todo
                return true;
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
                    || strstr($message, 't find the e-mail address or Flying Blue number you entered')
                    || strstr($message, 't find the e-mail address or Flying Blue number entered')
                    || strstr($message, 'The password you entered is not valid.')
                    || strstr($message, 'More than 1 passenger is registered with this e-mail address. Please log in with your Flying Blue number instead, so we can uniquely identify you.')
                    || $message == 'Please enter a valid password.'
                    || $message == 'Please enter a valid e-mail address.'
                    || $message == 'Your temporary password has expired.'
                    || strstr($message, 'Sorry, we can\'t recognise your password due to a technical error')
                    || strstr($message, 'Sorry, we cannot log you in right now. Contact us via the')
                    || strstr($message, 'Oops, the login details you entered are incorrect.')
                    || strstr($message, 'Your e-mail address seems to be invalid.')
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    strstr($message, 'Unfortunately, your account is blocked.')
                    || strstr($message, 'Your account is blocked. Please wait 24 hours before clicking "Forgot password?" to reset your password.')
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if (
                    strstr($message, 'Authentication failed: recaptchaResponse')
                    || strstr($message, 'Access denied: Ineligible captcha score')
                    || $message == 'Invalid Captcha'
                ) {
                    $this->captchaReporting($this->recognizer, false);
                    $this->DebugInfo = $message;

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

                if (
                    $message == 'Retrieved unexpected result from mashery.'
                    || $message == 'Forbidden'
                    || $message == 'Sorry, an unexpected technical error occurred. Please try again later or contact the KLM Customer Contact Centre.'
                ) {
                    $this->captchaReporting($this->recognizer);
                    $this->DebugInfo = $message;

                    throw new CheckException("Sorry, an unexpected technical error occurred. Please try again later or contact the KLM Customer Contact Centre.", ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    $message == 'Sorry, our system fell asleep. Please restart your login.'
                    || strstr($message, 'Communication email is invalid')
                    || strstr($message, 'Sorry, we cannot verify your password due to a technical issue')
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            return $this->checkErrors();
        }

        if (
            $this->http->FindSingleNode('//span[contains(@class, "bwc-logo-header__user-name")]')
            || $this->http->FindSingleNode('//div[contains(@class, "bw-profile-recognition-box__info")]/h1')
            || $this->http->FindSingleNode('//h1[contains(text(), "Dashboard")]')
        ) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($this->http->FindSingleNode('//div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Update your password")]')) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        /*
        $this->http->RetryCount = 0;
        $retry = 0;

        do {
//            $this->http->GetURL('https://www.klm.com/ams/lsi/endpoint/v1/deeplink?country=us&language=en&target=/profile', [], 30);
            $this->http->GetURL('https://www.klm.com/endpoint/v1/oauth/login', [], 30);
            $this->http->JsonLog();
            $xsrf = $this->http->getCookieByName('XSRF-TOKEN');
            $retry++;
        } while (!$xsrf && $retry < 1);
        $this->http->RetryCount = 2;

        $this->http->setDefaultHeader('x-xsrf-token', $xsrf);

        // Network error 28
        if (strstr($this->http->Error, 'Network error 28 - Operation timed out after')) {
            throw new CheckRetryNeededException(3);
        }
        */

        $data = [
            "operationName" => "ProfileAccountInfoQuery",
            "variables"     => new stdClass(),
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => "fe2cd257408d9cf35e82132e33e562531aef7f39f96fabfb903380f68990069f",
                ],
            ],
        ];
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $this->http->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE1", json_encode($data), $headers);
        $accountInfo = $this->http->JsonLog();
        // Name
        if (isset($accountInfo->data->account->givenNames, $accountInfo->data->account->familyName)) {
            $this->firstName = $accountInfo->data->account->givenNames;
            $this->lastName = $accountInfo->data->account->familyName;
            $this->SetProperty("Name", beautifulName($accountInfo->data->account->givenNames . ' ' . $accountInfo->data->account->familyName));
        }

        if (!isset($accountInfo->data->account->flyingBlueNumber)) {
            $this->logger->error("something went wrong");
            // not a member
            if (isset($accountInfo->data->account->isFlyingBlue) && $accountInfo->data->account->isFlyingBlue === false
                && !empty($this->Properties['Name'])) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
            /*
            else
                $this->parseMainPage();
            */
            return;
        }// if (!isset($accountInfo->data->account->flyingBlueNumber))

        $this->SetProperty("Number", $accountInfo->data->account->flyingBlueNumber);

        $data = [
            "operationName" => "ProfileFlyingBlueDashboardQuery",
            "variables"     => [
                "fbNumber" => $this->Properties['Number'],
            ],
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => "75235467716d05abac908894c4ec567c7eb6f2c24fe7bb144f8618eaa00e4db7",
                ],
            ],
        ];
        $this->http->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE1", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        // provider bug workaround
        if ($this->http->FindPreg("/^\{\"errors\":\[\{\"message\":\"An unknown error occurred\",\"locations\":\[\{\"line\":2,\"column\":3\}\],\"path\":\[\"flyingBlueDashboard\"],\"extensions\":\{\"code\":\"500\",\"exception\":\{\"name\":\"AviatoError\",\"data\":\{\"statusCode\":500,\"errors\":\[\{\"code\":500,\"name\":\"unknown error\"\}\]\}\}\}\}\],\"data\":\{\"flyingBlueDashboard\":null,\"account\":\{\"_id\":\"AccountCUO:\d+\",\"givenNames\":\"[^\"]+\",\"familyName\":\"[^\"]+\",\"__typename\":\"Account\"\}\}(?:,\"extensions\":\{\"aviatoCacheControl\":\{\"hasPrivateData\":true\}\}|)\}$/")) {
            sleep(5);
            $this->http->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE1", json_encode($data), $headers);
            $response = $this->http->JsonLog();
        }

        if (!$response || !isset($response->data)) {
            return;
        }
        $dashboard = $response->data->flyingBlueDashboardV2;
        // Balance
        if (isset($dashboard->miles->amount)) {
            $this->SetBalance($dashboard->miles->amount ?? null);
        } elseif (
            (
            $dashboard === null
            && isset($response->errors[0]->message)
            && ($response->errors[0]->message == 'Unauthorized'
                || $response->errors[0]->message == 'An unknown error occurred')
            )
            || ($this->http->FindPreg('/"message":"An unknown error occurred",/') && $this->http->FindPreg('/"name":"AviatoError","data":/'))
        ) {
            // AccountID: 2829853
            /*
            $this->parseMainPage();

            // AccountID: 1307660, 3470430
            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && $this->http->FindSingleNode("//div[contains(@class, 'mya-profile-widget')]/@data-components", null, false, "/^\[\{\s*\"path\":\s*\"[^\"]+\",\s*\"module\":\"profilewidget\"\s*\}\]$/")
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
            */

            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && ($this->http->FindPreg("/^\{\"errors\":\[\{\"message\":\"An unknown error occurred\",\"locations\":\[\{\"line\":2,\"column\":3\}\],\"path\":\[\"flyingBlueDashboard\"\],\"extensions\":\{\"code\":\"500\",\"exception\":\{\"name\":\"AviatoError\",\"data\":\{\"statusCode\":500,\"errors\":\[\{\"code\":500,\"name\":\"unknown error\"\}\]\}\}\}\}\],\"data\":\{\"flyingBlueDashboard\":null,\"account\":\{\"givenNames\":\"[^\"]+\",\"familyName\":\"[^\"]+\",\"__typename\":\"Account\"\}\}\}$/")
                || $this->http->FindPreg("/^\{\"errors\":\[\{\"message\":\"An unknown error occurred\",\"locations\":\[\{\"line\":2,\"column\":3\}\],\"path\":\[\"flyingBlueDashboard\"],\"extensions\":\{\"code\":\"500\",\"exception\":\{\"name\":\"AviatoError\",\"data\":\{\"statusCode\":500,\"errors\":\[\{\"code\":500,\"name\":\"unknown error\"\}\]\}\}\}\}\],\"data\":\{\"flyingBlueDashboard\":null,\"account\":\{\"_id\":\"AccountCUO:\d+\",\"givenNames\":\"[^\"]+\",\"familyName\":\"[^\"]+\",\"__typename\":\"Account\"\}\}(?:,\"extensions\":\{\}|)\}$/"))
                || ($this->http->FindPreg('/"message":"An unknown error occurred",/') && $this->http->FindPreg('/(?:"name":"AviatoError","data":|"name":"AviatoError","message":"An unknown error occurred","data":\{"statusCode":500,"errors":\[\{"code":500,"name":"unknown error"\}\]\}\},"code":"500")/'))
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }

            return;
        }
        // Experience Points
        $this->SetProperty("ExperiencePoints", $dashboard->detailStatus->currentTierLevel->currentTierLevelXP->xpAmount ?? null);
        // Status
        if (isset($dashboard->fbLevel)) {
            $this->SetProperty("Status", beautifulName($dashboard->fbLevel));
            // refs
            if ($dashboard->fbLevel == 'Explorer' && !isset($this->Properties['ExperiencePoints'])
                && $this->http->FindPreg("/\{\"tierLevel\":\"Explorer\",\"tierLevelCode\":\"A\",\"currentTierLevelXP\":null,/")) {
                $this->SetProperty("ExperiencePoints", 0);
            }
        }

        // AccountId: 4138182
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name']) && !empty($this->Properties['Number'])) {
                $this->SetBalanceNA();
            }

            return;
        }

        // refs #16824
//        $this->http->GetURL("https://wwws.klm.no/en/profile/flying-blue/benefits");
        $data = [
            "operationName" => "ProfileFlyingBlueBenefitsQuery",
            "variables"     => [
                "fbNumber" => $this->Properties['Number'],
            ],
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => "ee0498f9ac6236f86f09013c8621ab2894e36e17dd0d0d8fb80b856514b23379",
                ],
            ],
        ];
        $this->http->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if (isset($response->data->flyingBlueBenefits->currentBenefits)) {
            foreach ($response->data->flyingBlueBenefits->currentBenefits as $benefit) {
                if ($benefit->label == "Flying Blue Petroleum") {
                    $this->SetProperty("PetroleumMembership", 'Yes');

                    break;
                }
            }
        }// foreach ($response->data->flyingBlueBenefits->currentBenefits as $benefit)

        if (!$this->Balance || $this->Balance < 0) {
            return;
        }
        $this->logger->info('Expiration Date', ['Header' => 3]);

        if (isset($this->Properties['Status']) && $this->Properties['Status'] == 'Explorer') {
            // Expiration Date
            $response = $this->getHistory(10);

            if (isset($response->data->flyingBlueTransactionHistory->milesValidities[0]->validityDate)) {
                $result = [];

                foreach ($response->data->flyingBlueTransactionHistory->milesValidities as $row) {
                    $result[$row->validityDate]['validityDate'] = $row->validityDate;

                    if (isset($result[$row->validityDate]['milesAmount'])) {
                        $result[$row->validityDate]['milesAmount'] += $row->milesAmount;
                    } else {
                        $result[$row->validityDate]['milesAmount'] = $row->milesAmount;
                    }
                }
                $result = array_values($result);

                usort($result, function ($a, $b) {
                    $a2 = strtotime($a['validityDate']);
                    $b2 = strtotime($b['validityDate']);

                    if ($a2 < $b2) {
                        return -1;
                    }

                    if ($a2 > $b2) {
                        return 1;
                    }

                    return 0;
                });
                $first = current($result);

                $this->logger->debug("Exp Date: {$first['validityDate']}");

                if ($exp = strtotime($first['validityDate'], false)) {
                    $this->SetExpirationDate($exp);
                    $this->SetProperty('ExpiringBalance', $first['milesAmount']);
                }
            }
        } elseif (isset($this->Properties['Status']) && in_array($this->Properties['Status'],
                ['Silver', 'Gold', 'Platinum', 'Ultimate', 'Platinum For Life', 'Ultimate Club 2000'])) {
            $this->SetExpirationDateNever();
            $this->SetProperty('AccountExpirationWarning', 'do not expire with elite status');
            $this->ClearExpirationDate();
        } elseif (isset($this->Properties['Status'])) {
            $this->sendNotification("new status {$this->Properties['Status']} // MI");
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["RequestMethod"] = "POST";
        //$arg["PreloadAsImages"] = false;
        $arg["URL"] = 'https://www.klm.com/passage/account/lightbox/signInForm.htm?country=gb&lang=en&carrier=AF';
        $arg["PostValues"] = [
            '_rememberUserId'	 => 'on',
            'emailorfbnumber'	 => $this->AccountFields['Login'],
            'enrollOption'		   => 'true',
            'fastEnrollOption'	=> 'false',
            'passwordpincode'	 => $this->AccountFields['Pass'],
            'sessionExpired'	  => 'false',
        ];
        $arg["SuccessURL"] = 'https://www.klm.com/travel/us_en/apps/myaccount/myahome.htm';

        return $arg;
    }

    public function ParseItineraries()
    {
        $trips = $this->getReservationsJson();

        if (is_array($trips)) {
            if (empty($trips)) {
                $this->itinerariesMaster->setNoItineraries(true);

                return [];
            }
            $cntSkipped = 0;

            foreach ($trips as $trip) {
                $scheduledReturn = $trip->scheduledReturn;
                $this->logger->debug("[scheduledReturn]: '{$scheduledReturn}'");
                $isPast = strtotime($scheduledReturn) < strtotime(date("Y-m-d"));

                if (!$this->ParsePastIts && $scheduledReturn != '' && $isPast) {
                    $cntSkipped++;
                    $this->logger->notice("Skipping booking {$trip->bookingCode}: past itinerary");

                    continue;
                }

                if ($trip->historical == true || $isPast) {
                    $this->parseReservationJson($trip->bookingCode, $trip, true);
                } else {
                    $reservation = $this->getReservationJson($trip->bookingCode, $trip->lastName);

                    if ($reservation === null) {
                        $this->logger->error("Skipping reservation: {$reservation}");

                        continue;
                    }

                    if (is_string($reservation)) {
                        $this->logger->error("Skipping reservation 2: {$reservation}");

                        continue;
                    }
                    $this->parseReservationJson($trip->bookingCode, $reservation);
                }
            }

            if (count($trips) === $cntSkipped && count($this->itinerariesMaster->getItineraries()) === 0) {
                $this->itinerariesMaster->setNoItineraries(true);
            }
        }

        return [];
    }

    public function ParseItineraryNewDetailed($arFields)
    {
        $this->logger->notice(__METHOD__);
        $itinerary['Kind'] = 'T';
        // TicketNumbers
        $tickets = $this->http->FindNodes("//div[contains(@class, 'mmb-banner-pax-passengers')]/table//td[contains(@class, 'passenger-name-bold')]/following-sibling::td[1]");
        $itinerary['TicketNumbers'] = array_unique(array_filter($tickets));

        $this->http->GetURL('https://www.klm.com/ams/mytrip/travel.xhtml');
        $itinerary['RecordLocator'] = $this->http->FindSingleNode('//*[@data-label-key="bookingdetails.booking.code"]', null, false, '#Booking\s+code\s*:\s+([\w\-]+)#i');
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $itinerary['RecordLocator']), ['Header' => 3]);
        $paymentData = $this->parsePaymentData($arFields["ConfNo"], $arFields["LastName"]);

        if ($paymentData) {
            $itinerary = array_merge($itinerary, $paymentData);
        }
        // Passengers
        $itinerary['Passengers'] = $this->http->FindNodes('//h3[contains(text(), "Your seat")]/following-sibling::ul/li/strong');
        $itinerary['Passengers'] = array_values(array_unique($itinerary['Passengers']));

        $segmentNodes = $this->http->XPath->query('//h2[@class="g-h1"]/following-sibling::div[not(@class = "mmb-message-warning")][1]');
        $this->logger->info(sprintf('Found %s segments', $segmentNodes->length));
        $airSegments = [];
        $trainSegments = [];

        foreach ($segmentNodes as $segmentNode) {
            $segment = [];
            $s = $this->http->FindSingleNode('./preceding-sibling::h2[1]', $segmentNode, false);

            if ($s && preg_match('#^(\w{2})\s+(\d+)\s+(.*)\s+-\s+(.*)$#i', $s, $m)) {
                $segment['FlightNumber'] = $m[2];
                // AirlineName
                $segment['AirlineName'] = $m[1];
                // DepName
                $segment['DepName'] = $m[3];
                // ArrName
                $segment['ArrName'] = $m[4];
                // Operator
                $operator = $this->http->FindSingleNode(".//strong[contains(text(), 'Operated by')]/following-sibling::span", $segmentNode);
                $operator = $this->http->FindPreg('/^(.+?)\s*(?:DBA|DELTA CONNECTION|\bFOR\b)/i', false, $operator) ?: $operator;

                if (!empty($operator)) {
                    $segment['Operator'] = $operator;
                }
                // Status
                $segment['Status'] = str_replace('. Time change occurred.', '', $this->http->FindSingleNode(".//strong[contains(text(), 'Flight status')]/following-sibling::span", $segmentNode));

                if ($this->http->FindPreg('/mmb.bookingstatuscode/', false, $segment['Status'])) {
                    $segment['Status'] = null;
                }
                // Class
                $segment['Cabin'] = $this->http->FindSingleNode(".//strong[contains(text(), 'Travel class')]/following-sibling::span", $segmentNode);
                // Duration
                $segment['Duration'] = $this->http->FindSingleNode(".//strong[contains(text(), 'Total travel time')]/following-sibling::span", $segmentNode);
                // Seats
                $arrSeats = [];
                $seats = $this->http->FindNodes('./following-sibling::div/h3[contains(text(), "Your seat")]/following-sibling::ul/li/span', $segmentNode);

                for ($s = 0; $s < count($seats); $s++) {
                    $match = null;

                    if (!preg_match('/Not available/', $seats[$s]) and preg_match('/^\s*(\d+[A-Z]+)/', $seats[$s], $match)) {
                        $arrSeats[] = $match[1];
                    }
                }

                if (isset($arrSeats[0])) {
                    $segment['Seats'] = array_values(array_unique($arrSeats));
                }
                // Meal
                $arrMeals = [];
                $meal = $this->http->FindNodes("./following-sibling::div/h3[contains(text(), 'Your meal')]/following-sibling::ul/li/span", $segmentNode);

                for ($s = 0; $s < count($meal); $s++) {
                    if (!preg_match('/Not available/', $meal[$s])) {
                        $arrMeals[] = $meal[$s];
                    }
                }

                if (isset($arrMeals[0])) {
                    $segment['Meal'] = implode('; ', array_values(array_unique($arrMeals)));
                }
                // Aircraft
                $segment['Aircraft'] = $this->http->FindSingleNode(".//strong[contains(text(), 'Aircraft type')]/following-sibling::span", $segmentNode);
            }

            if ($this->http->FindSingleNode('./preceding-sibling::div[1]', $segmentNode, false, '#This flight has been cancelled#i')) {
                $segment['Cancelled'] = true;
            }
            $r = '#(?:Departure|Arrival)\s*(?:\w+\s+(?<date>\d+\s+\w+\s+\d{4}),?\s+(?<time>\d+:\d+))?\s*(?:Terminal\s+(?<term>\w+))?\s+(?<name>.*?)\s+\((?<code>\w{3})\)(?<name2>.*)#i';

            foreach (['Dep' => 'Departure', 'Arr' => 'Arrival'] as $key => $value) {
                $s = $this->http->FindSingleNode('.//li[contains(., "' . $value . '")]', $segmentNode);
                $this->http->Log($value . ' info: ' . $s);

                if (preg_match($r, $s, $m)) {
                    if (isset($m['date'], $m['time']) && !empty($m['date'])) {
                        $segment[$key . 'Date'] = strtotime($m['date'] . ', ' . $m['time']);
                    } else {
                        $segment[$key . 'Date'] = MISSING_DATE;
                    }
                    $segment[$key . 'Name'] = $m['name'] . $m['name2'];

                    if (isset($m['term']) && !empty($m['term'])) {
                        if ($key == 'Dep') {
                            $segment['DepartureTerminal'] = $m['term'];
                        } else {
                            $segment['ArrivalTerminal'] = $m['term'];
                        }
                    }
                    $segment[$key . 'Name'] = (
                        $this->http->FindPreg('/^(.+?)\s*\(Terminal/', false, $segment[$key . 'Name']) ?:
                        $segment[$key . 'Name']
                    );
                    $segment[$key . 'Code'] = $m['code'];
                }
                $segment[$value . 'Terminal'] = $this->http->FindSingleNode('.//li[contains(., "' . $value . '")]', $segmentNode, null, "/Terminal\s*([\da-z]+)/ims");
            }

            if ($this->http->FindPreg('/^\s*Train/', false, ArrayVal($segment, 'Aircraft'))) {
                unset($segment['Aircraft']);
                unset($segment['Operator']);
                $trainSegments[] = $segment;
            } else {
                $airSegments[] = $segment;
            }
        }

        if (!empty($trainSegments)) {
            $air = $itinerary;
            $train = $itinerary;
            $train['TripCategory'] = TRIP_CATEGORY_TRAIN;
            $air['TripSegments'] = $airSegments;

            if ($this->allSegmentsCancelled($air)) {
                $this->sendNotification('klm check cancelled // MI');
            }
            $train['TripSegments'] = $trainSegments;
            $itinerary = [$air, $train];
        } else {
            $itinerary['TripSegments'] = $airSegments;
            // Cancelled
            if ($this->allSegmentsCancelled($itinerary)) {
                $itinerary['Cancelled'] = true;
            }
            $itinerary = [$itinerary];
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($itinerary, true), ['pre' => true]);

        return $itinerary;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Reservation code",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Passenger surname",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
//        return "https://www.klm.com/ams/mytrip/start.xhtml?LANG=EN&COUNTRY=NL&POS=NL";
        return "https://www.klm.com/trip";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->seleniumRetrieve($this->ConfirmationNumberURL($arFields))) {
            return null;
        }
        $reservation = $this->getReservationJson($arFields['ConfNo'], $arFields['LastName']);

        if ($reservation === null) {
            return null;
        }

        if (is_string($reservation)) {
            return $reservation;
        }
        $this->parseReservationJson($arFields['ConfNo'], $reservation);

        return null;

        $this->http->GetURL('https://www.klm.com/ams/mytrip/mmbDashboard.xhtml?method=loadSearchBookingWidget&LANG=en&COUNTRY=nl&POS=nl&CARRIER=KL&entryreason=DEFAULT&mmbBookingCode=&mmbLastName=&_=' . time() . date('B'));
        $captcha = $this->parseCaptcha();

        $this->http->FormURL = "https://www.klm.com/ams/mytrip/mmbDashboard.xhtml?method=retrieveBookingJson";
        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Referer'          => 'https://www.klm.com/ams/mytrip/start.xhtml?LANG=EN&COUNTRY=NL&POS=NL',
            'afkl.apikey'      => 'mytrip',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $this->http->Form = [
            "host"                 => "KL",
            "bookingCode"          => $arFields["ConfNo"],
            "lastName"             => $arFields["LastName"],
            "g-recaptcha-response" => $captcha,
            "LANG"                 => "en",
            "COUNTRY"              => "us",
            "CARRIER"              => "kl",
            "POS"                  => "us",
            "entryreason"          => "DEFAULT",
        ];

        if (!$this->http->PostForm($headers)) {
            return null;
        }

        if ($this->http->FindPreg('/"errorText":"Unfortunately we are unable to find your booking details\./')) {
            return "Unfortunately we are unable to find your booking details. Please check the name and booking code or contact the KLM Customer Contact Centre.";
        }

        if ($this->http->FindPreg('/"errorText":"(?:This booking is not active anymore, because travel voucher|We are unable to show this booking; this booking code is not active)/')) {
            $data = $this->http->JsonLog();

            return $data->errorText;
        }
        $this->http->GetURL("https://www.klm.com/ams/mytrip/overview.xhtml?clurl=gb_en");
        $it = $this->ParseItineraryNewDetailed($arFields);

        return null;
    }

    // old refs #4630
    public function GetHistoryColumns()
    {
        return [
            "Date"              => "PostingDate",
            "Transaction"       => "Description",
            "Travel Date"       => "Info.Date",
            "Award Miles"       => "Miles",
            "Bonus Miles"       => "Bonus",
            "Experience Points" => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (empty($this->Properties['Number'])) {
            return $result;
        }

        $page = 0;
        $this->getHistory();
        $page++;
        $this->logger->info("[History page: {$page}]", ['Header' => 3]);
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 3, true);

        if (isset($response['data']['flyingBlueTransactionHistory']['transactions']['transactionsList'])
            && is_array($response['data']['flyingBlueTransactionHistory']['transactions']['transactionsList'])) {
            foreach ($response['data']['flyingBlueTransactionHistory']['transactions']['transactionsList'] as $row) {
                $dateStr = ArrayVal($row, 'transactionDate');
                $postDate = strtotime($dateStr, false);

                if (!$postDate) {
                    continue;
                }

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    break;
                }// if (isset($startDate) && $postDate < $startDate)
                $result[$startIndex]['Date'] = $postDate;

                $description = Html::cleanXMLValue(ArrayVal($row, 'description'));
                // 'description' => 'My Trip to {#/transactions/transactionsList[46]/finalDestination}',
                $finalDestination = ArrayVal($row, 'finalDestination', null);
                // 'description' => 'Car & Taxi - {#/transactions/transactionsList[35]/complementaryDescriptionData[0]}',
                $complementaryDescriptionData = ArrayVal($row, 'complementaryDescriptionData', null);

                if (isset($finalDestination)) {
                    $transaction = preg_replace('/\{.+?finalDestination\}/i', $finalDestination, $description);
                } elseif (isset($complementaryDescriptionData)) {
                    for ($i = 0; $i < count($complementaryDescriptionData); $i++) {
                        $transaction = preg_replace("/\{.+?complementaryDescriptionData\[{$i}\]\}/i", trim($complementaryDescriptionData[$i]), $description);
                    }
                } else {
                    $transaction = preg_replace('/\{.+?\}/i', '', $description);
                }

                $details = ArrayVal($row, 'details', []);
                $mainInfo = $result[$startIndex];

                foreach ($details as $detail) {
                    $complementaryDescription = ArrayVal($detail, 'description', null);
                    $complementaryDetailDescriptionData = ArrayVal($detail, 'complementaryDetailDescriptionData', []);
                    $ancillaryLabelCategory = ArrayVal($detail, 'ancillaryLabelCategory', null);

                    if (
                        $complementaryDescription
                        && (
                            !empty($complementaryDetailDescriptionData)
                            || !empty($ancillaryLabelCategory)
                        )
                    ) {
                        for ($i = 0; $i < count($complementaryDetailDescriptionData); $i++) {
                            $complementaryDescription = preg_replace("/\{.+?complementaryDetailDescriptionData\[{$i}\]\}/i", trim($complementaryDetailDescriptionData[$i]), $complementaryDescription);
                        }

                        // https://redmine.awardwallet.com/issues/18358#note-8
                        if ($finalDestination && strpos($finalDestination, 'My Trip to') == 0) {
                            $result[$startIndex] = $mainInfo;
                            $result[$startIndex]['Transaction'] = $transaction . "; " . $complementaryDescription;

                            $result[$startIndex]['Travel Date'] = strtotime(ArrayVal($detail, 'activityDate'), false);

                            if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Transaction'])) {
                                $result[$startIndex]['Bonus Miles'] = ArrayVal($detail, 'milesAmount');
                            } else {
                                $result[$startIndex]['Award Miles'] = ArrayVal($detail, 'milesAmount');
                            }

                            $xpAmount = ArrayVal($detail, 'xpAmount', null);

                            if (isset($xpAmount)) {
                                $result[$startIndex]['Experience Points'] = $xpAmount;
                            }

                            $startIndex++;

                            continue;
                        }// if ($finalDestination && strpos($finalDestination, 'My Trip to') == 0)

                        $transaction .= "; " . $complementaryDescription;
                    } elseif (
                        $transaction === ''
                        && !empty($complementaryDescription)
                        && empty($complementaryDetailDescriptionData)
                    ) {
                        $transaction = $complementaryDescription;
                    }
                }

                if ($finalDestination && strpos($finalDestination, 'My Trip to') == 0) {
                    $this->logger->notice("skip {$finalDestination} / {$transaction}");

                    continue;
                }

                $result[$startIndex]['Transaction'] = $transaction;

                if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Transaction'])) {
                    $result[$startIndex]['Bonus Miles'] = ArrayVal($row, 'milesAmount');
                } else {
                    $result[$startIndex]['Award Miles'] = ArrayVal($row, 'milesAmount');
                }

                $xpAmount = ArrayVal($row, 'xpAmount', null);

                if (isset($xpAmount)) {
                    $result[$startIndex]['Experience Points'] = $xpAmount;
                }

                $startIndex++;
            }// foreach ($response as $row)
        }

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function seleniumRetrieve($url)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL($url);

            // login
            $bookingCode = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(),'Log in with my booking reference')]"), 3);

//            if (!$bookingCode) {
//                return false;
//            }
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); // TODO
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $code = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $otpInput = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);

        if (!$otpInput) {
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

        if (!$this->solveCaptchaImg()) {
            return false;
        }

        if (!$button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3)) {
            return false;
        }
        $button->click();
        $this->waitForElement(WebDriverBy::xpath('
            //span[contains(@class, "bwc-logo-header__user-name")]
            | //div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)]
            | //div[contains(@class, "bwc-form-errors")]/span
        '), 15);
        $captchaAttempt = 0;

        while ($this->waitForElement(WebDriverBy::xpath('//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)] | //div[contains(@class, "bwc-form-errors")]/span'), 0)) {
            $this->saveResponse();
            $error = $this->http->FindSingleNode('//div[contains(@class, "bwc-form-errors")]/span')
                ?? $this->http->FindSingleNode('(//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)])[last()]');
            $this->logger->error("[Error]: $error");

            if (strstr($error, 'Invalid Captcha') && ++$captchaAttempt < 3) {
                if (!$this->solveCaptchaImg()
                    || !$button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3)
                ) {
                    return false;
                }
                $button->click();
                sleep(3);

                continue;
            }

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

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode("//meta[@id = 'recaptchaSiteKey']/@content")
            ?? $this->http->FindPreg("/recaptchaSiteKey\":\"([^\"]+)/")
        ;

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        if ($this->http->FindPreg("/\"reCaptchaV2\":false,\"reCaptchaV3\":true,/")) {
            $parameters += [
                "version"   => "v3",
                "action"    => "customer_login",
                "min_score" => 0.9,
            ];
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function parseCaptchaImg()
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

    private function parseMainPage($actions = null)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.klm.com/ams/account/widget/profileWidget.htm?country=us&lang=en&carrier=KL");
        // Balance - Miles
        $this->SetBalance($this->http->FindSingleNode("//em[contains(@class, 'j-award-miles-balance')]"));
        // Status
        $this->SetProperty("Status", $this->http->FindPreg("/\"name\":\"([^\"]+)/"));

        if (!empty($this->Properties['Name'])) {
            return true;
        }

        // Name
        if (!empty($actions->myaActions)) {
            foreach ($actions->myaActions as $action) {
                if (
                    $action->eventName && $action->eventName == 'mya:system:login'
                    && isset($action->eventArguments[0]->firstName)
                    && isset($action->eventArguments[0]->lastName)
                ) {
                    $this->SetProperty("Name", beautifulName($action->eventArguments[0]->firstName . ' ' . $action->eventArguments[0]->lastName));
                }
            }
        } else {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[@class = 'g-h3']")));
        }

        return true;
    }

    private function getHistory($size = 100)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $data = [
            "operationName" => "ProfileFlyingBlueTransactionHistoryQuery",
            "variables"     => [
                "size"     => $size,
                "offset"   => 1,
                "fbNumber" => $this->Properties['Number'],
            ],
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => "a4da5deea24960ece439deda2d3eac6c755e88ecfe1dfc15711615a87943fba7",
                ],
            ],
        ];
        $this->http->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        return $response;
    }

    private function getReservationJson(string $conf, string $lastName)
    {
        $this->logger->notice(__METHOD__);

        if (!$conf || !$lastName) {
            return null;
        }
        /*$variantsSha256Hash = [
            '5862217c780db7597694b8736e2846f235c5deedcc0322e5c09b6f6ca4c8006d',
        ];
        $current = Cache::getInstance()->get('klm_sha256Hash');

        if (empty($current)) {
            $this->logger->debug('[getting new value]');
            $last = Cache::getInstance()->get('klm_sha256Hash_last');

            if (empty($last)) {
                $this->logger->debug('get first value');
                $current = $variantsSha256Hash[0];
            } else {
                $this->logger->debug('get next value');
                $previousNum = array_search($last, $variantsSha256Hash, true);

                if (!is_int($previousNum) || $previousNum >= count($variantsSha256Hash) - 1) {
                    $current = $variantsSha256Hash[0];
                } else {
                    $current = $variantsSha256Hash[$previousNum + 1];
                }
            }
        }
        $curNum = array_search($current, $variantsSha256Hash, true);
        $this->logger->debug('request with key: ' . $curNum);*/

        $headers = [
            'content-type' => 'application/json',
        ];
        $payload = [
            'operationName' => 'TripReservationQuery',
            'variables'     => [
                'bookingCode' => $conf,
                'lastName'    => $lastName,
            ],
            'extensions' => [
                'persistedQuery' => [
                    'version'    => 1,
                    'sha256Hash' => $this->sha256HashReservation,
                ],
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE", json_encode($payload), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (is_null($response)) {
            return null;
        }

        /*$i = 0;

        while (isset($response->errors)
            && isset($response->errors[0]->message) && $response->errors[0]->message === 'PersistedQueryNotFound'
            && $i < count($variantsSha256Hash) - 1
        ) {
            $previousNum = array_search($current, $variantsSha256Hash, true);

            if (!is_int($previousNum) || $previousNum >= count($variantsSha256Hash) - 1) {
                if ($current === $variantsSha256Hash[0]) {
                    $this->logger->error('something wrong with getting new value sha256Hash');
                    $this->sendNotification('check sha256Hash for reservation');

                    return null;
                }
                $current = $variantsSha256Hash[0];
            } else {
                $current = $variantsSha256Hash[$previousNum + 1];
            }
            $curNum = array_search($current, $variantsSha256Hash, true);
            $this->logger->debug('request with key: ' . $curNum);
            // it works: вечером на одном sha256Hash не работает, а утром опять заработло
            $payload['extensions']['persistedQuery']['sha256Hash'] = $current;
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE", json_encode($payload),
                $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
            $i++;
        }*/

        if (isset($response->errors)
            && isset($response->errors[0]->message) && $response->errors[0]->message === 'PersistedQueryNotFound'
        ) {
            $this->sendNotification('update sha256Hash for reservation!!! // MI');

            return null;
        }
        /*Cache::getInstance()->set('klm_sha256Hash', $current, 60 * 60 * 3);
        Cache::getInstance()->set('klm_sha256Hash_last', $current, 60 * 60 * 24);*/

        $error = $this->checkJsonError($response);

        if (is_string($error)) {
            return $error;
        }

        if (!isset($response->data, $response->data->reservation)) {
            /*
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE", json_encode($payload),
                $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (is_null($response)) {
                return null;
            }
            $error = $this->checkJsonError($response);

            if (is_string($error)) {
                return $error;
            }

            if (!isset($response->data, $response->data->reservation)) {
                $this->sendNotification("check retry // ZM");
            }
            */

            return null;
        }
        $message = $response->data->reservation->messages[0]->name ?? null;

        if (
            $message === 'A travel voucher has been requested for this reservation.'
            || $message === 'A travel voucher has been issued for this reservation.'
            || $message === 'Multiple travel vouchers have been issued for this reservation'
            || $message === 'Refund Eligibility'
        ) {
            return $message;
        }

        return $response->data->reservation;
    }

    private function checkJsonError($response)
    {
        if (
            isset($response->errors)
            && isset($response->errors[0]->message)
            && isset($response->data)
            && property_exists($response->data, 'reservation')
            && $response->data->reservation === null
        ) {
            $this->logger->error($response->errors[0]->message);

            return $response->errors[0]->message;
        }

        if ($this->http->FindPreg("/^\{\"errors\":\[\{\"message\":\"Internal server error\",\"extensions\":\{\"code\":\"INTERNAL_SERVER_ERROR\"\\}}/")
        ) {
            $this->logger->error($response->errors[0]->message);

            return $response->errors[0]->message;
        }

        if ($this->http->FindPreg("/^\{\"errors\":\[\{\"message\":\"Sorry, we couldn't find your booking details. Please/")
            && $this->http->FindPreg("/\"extensions\":\{\"code\":\"RESERVATION_NOT_FOUND\"/")
        ) {
            $this->logger->error($response->errors[0]->message);

            return $response->errors[0]->message;
        }

        return null;
    }

    private function getReservationsJson(): ?array
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'content-type' => 'application/json',
        ];

        if ($token = $this->http->getCookieByName('XSRF-TOKEN')) {
            $headers['X-Xsrf-Token'] = $token;
        }
        $variantsSha256Hash = [
            '4e3f2e0b0621bc3b51fde95314745feb4fd1f9c10cf174542ab79d36c9dd0fb2',
        ];
        $current = Cache::getInstance()->get('klm_reservations_sha256Hash');

        if (empty($current)) {
            $this->logger->debug('[getting new value]');
            $last = Cache::getInstance()->get('klm_reservations_sha256Hash_last');

            if (empty($last)) {
                $this->logger->debug('get first value');
                $current = $variantsSha256Hash[0];
            } else {
                $this->logger->debug('get next value');
                $previousNum = array_search($last, $variantsSha256Hash, true);

                if (!is_int($previousNum) || $previousNum >= count($variantsSha256Hash) - 1) {
                    $current = $variantsSha256Hash[0];
                } else {
                    $current = $variantsSha256Hash[$previousNum + 1];
                }
            }
        }
        $curNum = array_search($current, $variantsSha256Hash, true);
        $this->logger->debug('request with key: ' . $curNum);

        $payload = [
            'operationName' => 'TripReservationsQuery',
            'variables'     => [
                'daysBack' => 180,
            ],
            'extensions'    => [
                'persistedQuery' => [
                    'version'    => 1,
                    'sha256Hash' => $this->sha256HashReservations,
                ],
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE", json_encode($payload),
            $headers);

        if ($this->http->FindPreg('/Operation timed out/', false, $this->http->Error)
            || $this->http->FindPreg('/\{"errors":\[\{"extensions":\{"code":"400"\}\}\],"data":\{"reservations":null\}\}/')
        ) {
            $this->increaseTimeLimit();
            $this->http->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE", json_encode($payload),
                $headers);

            if ($this->http->FindPreg('/\{"errors":\[\{"extensions":\{"code":"400"\}\}\],"data":\{"reservations":null\}\}/')) {
                $this->sendNotification("retry not work // ZM");
            }
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $i = 0;

        while (isset($response->errors)
            && isset($response->errors[0]->message) && $response->errors[0]->message === 'PersistedQueryNotFound'
            && $i < count($variantsSha256Hash) - 1
        ) {
            $previousNum = array_search($current, $variantsSha256Hash, true);

            if (!is_int($previousNum) || $previousNum >= count($variantsSha256Hash) - 1) {
                if ($current === $variantsSha256Hash[0]) {
                    $this->logger->error('something wrong with getting new value sha256Hash');
                    $this->sendNotification('check sha256Hash for reservations');

                    return null;
                }
                $current = $variantsSha256Hash[0];
            } else {
                $current = $variantsSha256Hash[$previousNum + 1];
            }
            $curNum = array_search($current, $variantsSha256Hash, true);
            $this->logger->debug('request with key: ' . $curNum);
            // it works: вечером на одном sha256Hash не работает, а утром опять заработло
            $payload['extensions']['persistedQuery']['sha256Hash'] = $current;
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://wwws.klm.us/gql/v1?bookingFlow=LEISURE", json_encode($payload),
                $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (is_null($response)) {
                return null;
            }
            $i++;
        }

        if (isset($response->errors)
            && isset($response->errors[0]->message) && $response->errors[0]->message === 'PersistedQueryNotFound'
        ) {
            $this->sendNotification('update sha256Hash (reservations)');

            return null;
        }

        if (is_null($response) || !isset($response->data)) {
            return null;
        }
        Cache::getInstance()->set('klm_reservations_sha256Hash', $current, 60 * 60 * 3);
        Cache::getInstance()->set('klm_reservations_sha256Hash_last', $current, 60 * 60 * 24);

        if (
            isset($response->errors)
            && isset($response->errors[0]->message)
            && isset($response->data)
        ) {
            $this->logger->error($error = $response->errors[0]->message);
        }

        if (isset($response->data) && (!isset($response->data->reservations) || !isset($response->data->reservations->trips))) {
            if (!isset($error)
                && isset($response->errors[0], $response->errors[0]->extensions, $response->errors[0]->extensions->code)
                && $response->errors[0]->extensions->code == 400
            ) {
                $this->logger->error('something went wrong');

                return null;
            }

            if (!isset($error) || $error !== 'Sorry, something went wrong. Please contact the KLM Customer Contact Centre.') {
                $this->sendNotification("check getting its, json // ZM");
            }

            return null;
        }

        return $response->data->reservations->trips;
    }

    private function parseReservationJson(string $conf, $reservation, ?bool $fromTrip = false): ?string
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        $r = $this->itinerariesMaster->add()->flight();
        $r->general()->confirmation($conf);
        $totalMiles = 0;
        $accounts = [];
        $passengers = [];
        $infants = [];

        foreach ($reservation->passengers ?? [] as $passenger) {
            if ($passenger->type == 'INFANT') {
                $infants[] = beautifulName($passenger->firstName . ' ' . $passenger->lastName);
            } else {
                $passengers[] = beautifulName($passenger->firstName . ' ' . $passenger->lastName);
            }

            if (!$fromTrip) {
                foreach ($passenger->ticketNumber as $ticketNumber) {
                    if (!in_array($ticketNumber, array_column($r->getTicketNumbers(), 0))) {
                        $r->issued()->ticket($ticketNumber, false);
                    }
                }
                $memberships = $passenger->memberships ?? [];

                foreach ($memberships as $membership) {
                    $accounts[] = $membership->number;
                }
                $totalMiles += $passenger->earnQuote->totalMiles ?? 0;
            }
        }

        if (!empty($infants)) {
            $r->general()->infants($infants, true);
        }

        if (!empty($passengers)) {
            $r->general()->travellers($passengers, true);
        }

        if (!empty($totalMiles)) {
            $r->program()->earnedAwards($totalMiles);
        }
        $accounts = array_values(array_unique($accounts));

        if (!empty($accounts)) {
            $r->program()->accounts($accounts, false);
        }

        // SpentAwards
        $totalPrice = $reservation->ticketInfo->totalPrice ?? [];

        foreach ($totalPrice as $item) {
            $currencyCode = $item->currencyCode;
//             $currency = $this->currency($currencyCode);

            if ($currencyCode == 'MLS') {
                $r->price()->spentAwards($item->amount);

                continue;
            }

            if (empty($r->getPrice()) || empty($r->getPrice()->getTotal()) || $item->amount > $r->getPrice()->getTotal()) {
                $r->price()->total($item->amount);
                $r->price()->currency($currencyCode);
            }
        }

        $connections = $reservation->itinerary->connections ?? [];

        if (count($connections) === 0) {
            if (isset($reservation->messages[0], $reservation->messages[0]->code)
                && $reservation->messages[0]->code === 'EMD_CDET_REFUNDED_SINGLE_VOUCHER'
            ) {
                $this->logger->error($msg = ($reservation->messages[0]->description ?? ''));
                $this->itinerariesMaster->removeItinerary($r);

                if (!empty($msg)) {
                    return $msg;
                }

                return null;
            }
            $this->sendNotification('check connections');
        }

        foreach ($connections as $connection) {
            foreach ($connection->segments as $segment) {
                if ((isset($segment->flight->equipment->code) && $segment->flight->equipment->code === 'TRN')
                    || stripos($segment->destination->airportName, 'Railway Station') !== false
                    || stripos($segment->origin->airportName, 'Railway Station') !== false
                ) {
                    // train segment
                    if (!isset($train)) {
                        $train = $this->itinerariesMaster->add()->train();
                        $train->general()->confirmation($conf);

                        if (!empty($passengers)) {
                            $train->general()->travellers($passengers, true);
                        }
                    }
                    $s = $train->addSegment();
                    $s->extra()->service($segment->flight->carrierName);

                    if (!empty($segment->flight->flightNumber)) {
                        $s->extra()->number($segment->flight->flightNumber);
                    } else {
                        $s->extra()->noNumber();
                    }
                } else {
                    // flight segment
                    $s = $r->addSegment();
                    $s->airline()
                        ->name($segment->flight->carrierCode);

                    if (!empty($segment->flight->flightNumber)) {
                        $s->airline()->number($segment->flight->flightNumber);
                    } else {
                        $s->airline()->noNumber();
                    }
                    $s->extra()->aircraft($segment->flight->equipment->name ?? null, false, true);
                }

                if ($segment->isCancelled) {
                    $s->setCancelled(true);
                }

                if (!empty($segment->flight->newDepartureDate)) {
                    $s->departure()->date2($segment->flight->newDepartureDate);
                } else {
                    $s->departure()->date2($segment->flight->departureDate);
                }

                $s->departure()
                    ->code($segment->origin->airportCode)
                    ->name($segment->origin->airportName);
                $s->arrival()
                    ->code($segment->destination->airportCode)
                    ->name($segment->destination->airportName);

                if (!empty($segment->flight->newArrivalDate)) {
                    $s->arrival()->date2($segment->flight->newArrivalDate);
                } elseif (!empty($segment->flight->arrivalDate)) {
                    $s->arrival()->date2($segment->flight->arrivalDate);
                } else {
                    $s->arrival()->noDate();
                }
                $duration = round($segment->flight->duration / 60) . 'h' . round($segment->flight->duration % 60) . 'm';
                $s->extra()
                    ->duration($duration)
                    ->cabin($segment->flight->cabinClass, false, true);
//                $segment->flight->newArrivalDate, $segment->flight->newDepartureDate - not print on page(site)
                if (!empty($segment->ancillaries->meals) && is_array($segment->ancillaries->meals)) {
                    foreach ($segment->ancillaries->meals as $meal) {
                        if (isset($meal->name)) {
                            $s->extra()->meal($meal->name);
                        }
                    }
                }

                if (isset($segment->ancillaries->seats)) {
                    $seatNumbers = [];

                    foreach ($segment->ancillaries->seats as $seat) {
                        if ($seat->seatNumbers) {
                            foreach ($seat->seatNumbers as $seatNumber) {
                                if ($this->http->FindPreg('#^[A-Z\d\-/]{1,7}$#i', false, $seatNumber)) {
                                    $seatNumbers[] = $seatNumber;
                                }
                            }
                        }
                    }
                    $seatNumbers = array_unique($seatNumbers);

                    if (!empty($seatNumbers)) {
                        $s->extra()->seats($seatNumbers);
                    }
                }
            }
        }

        if (count($r->getSegments()) > 0) {
            $allCanceled = true;

            foreach ($r->getSegments() as $s) {
                if (!$s->getCancelled()) {
                    $allCanceled = false;

                    break;
                }
            }

            if ($allCanceled) {
                $r->general()->cancelled();
            }
        }

        if (isset($train)) {
            if (count($r->getSegments()) === 0) {
                $this->logger->debug("no flight-segments --> delete flight");
                $this->itinerariesMaster->removeItinerary($r);

                if (!empty($totalMiles)) {
                    $train->program()->earnedAwards($totalMiles);
                }

                if (!empty($accounts)) {
                    $train->program()->accounts($accounts, false);
                }
            } else {
                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
            }
            $allCanceled = true;

            foreach ($train->getSegments() as $s) {
                if (!$s->getCancelled()) {
                    $allCanceled = false;

                    break;
                }
            }

            if ($allCanceled) {
                $train->general()->cancelled();
            }
            $this->logger->debug('Parsed Itinerary (Train):');
            $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);

            return null;
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];
        $payload = [
            "operationName" => "TripReservationTicketPriceBreakdownQuery",
            "variables"     => ["id" => $reservation->id],
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => '2645ba4eec72a02650ae63c2bd78d14a3f0025dddfca698f570b96a630667fe0',
                ],
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE", json_encode($payload), $headers);
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, 2);

        if (!empty($data->data->reservation)) {
            $taxes = [];

            foreach ($data->data->reservation->ticketInformation->passengersTicketInformation as $information) {
                if (isset($information->taxes->totalPrice->amount)) {
                    $taxes[] = $information->taxes->totalPrice->amount;
                }
            }

            if (!empty($taxes)) {
                $r->price()->tax(array_sum($taxes));
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return null;
    }

    private function allSegmentsCancelled(array $flight): bool
    {
        $this->logger->notice(__METHOD__);
        $segments = ArrayVal($flight, 'TripSegments', []);

        if (count($segments) === 0) {
            return false;
        }

        foreach ($segments as $seg) {
            if (ArrayVal($seg, 'Cancelled') !== true) {
                return false;
            }
        }

        return true;
    }

    // refs #18794, 20255
    private function parsePaymentData($conf, $lastName): array
    {
        $this->logger->notice(__METHOD__);

        if (!$conf) {
            return [];
        }
        $this->logger->info("Payment Data for Itinerary #{$conf}", ['Header' => 4]);
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);

        // Request in itineraries from profile

        // $http2->GetURL('https://www.klm.com/profile/flying-blue/dashboard');
        // for csrf
        if (!$http2->getCookieByName('XSRF-TOKEN')) {
            $http2->GetURL('https://www.klm.com/trip');
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];
        $payload = [
            "operationName" => "reservation",
            "variables"     => [
                "bookingCode" => $conf,
                "lastName"    => $lastName,
            ],
            "extensions" => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => $this->sha256HashReservation,
                ],
            ],
        ];
        $http2->RetryCount = 0;

        if ($token = $http2->getCookieByName('XSRF-TOKEN')) {
            $headers['X-Xsrf-Token'] = $token;
        }
        $http2->PostURL("https://www.klm.com/gql/v1?bookingFlow=LEISURE", json_encode($payload), $headers);
        $http2->RetryCount = 2;
        $data = $http2->JsonLog(null, 3, true);

        if (empty($data)) {
            // todo: need to check request from profile, also need to check sha256Hash
            $this->sendNotification('check itinerary price // MI');

            return [];
        }

        $res = [];
        // SpentAwards
        if (isset($data['data']['reservation']['ticketInfo']['totalPrice'])) {
            $totalPrice = $data['data']['reservation']['ticketInfo']['totalPrice'];
        } else {
            return [];
        }

        foreach ($totalPrice as $item) {
            $currencyCode = ArrayVal($item, 'currencyCode');

            if ($currencyCode == 'MLS') {
                $res['SpentAwards'] = ArrayVal($item, 'amount');

                break;
            }
        }
        // TotalCharge and Currency
        foreach ($totalPrice as $item) {
            $currencyCode = ArrayVal($item, 'currencyCode');
            $currency = $this->currency($currencyCode);

            if (!$currency) {
                if ($currencyCode !== 'MLS') {
                    $this->sendNotification('check currency // MI');

                    break;
                } else {
                    continue;
                }
            }
            $res['TotalCharge'] = ArrayVal($item, 'amount');
            $res['Currency'] = $currency;

            break;
        }

        return $res;
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

            $selenium->useChromePuppeteer();
            $selenium->useCache();
            $selenium->http->saveScreenshots = true;

            $selenium->setProxyGoProxies();

            if ($this->attempt > 0) {
                $wrappedProxy = $this->services->get(WrappedProxyClient::class);
                $proxy = $wrappedProxy->createPort($selenium->http->getProxyParams());
                $selenium->seleniumOptions->antiCaptchaProxyParams = $proxy;
                $selenium->seleniumOptions->addAntiCaptchaExtension = true;
            }

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

            try {
                $selenium->http->GetURL("https://www.klm.com/profile/flying-blue/dashboard");
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $selenium->waitForElement(WebDriverBy::xpath('//a[@aria-label="Log in with your password instead?"] | //span[contains(text(), "This site can’t be reached")] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "The page you\'re looking for cannot be found")]'), 20);
            $this->savePageToLogs($selenium);

            // provider bug fix
            if ($this->http->FindSingleNode('//div[contains(text(), "The page you\'re looking for cannot be found")]')) {
                $selenium->http->GetURL("https://www.klm.com/endpoint/v1/oauth/redirect?loginPrompt=&source=profile&locale=US/en-GB");
                $selenium->waitForElement(WebDriverBy::xpath('//a[@aria-label="Log in with your password instead?"] | //span[contains(text(), "This site can’t be reached")] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "The page you\'re looking for cannot be found")]'), 20);
                $this->savePageToLogs($selenium);
            }

            $loginWithPass = $selenium->waitForElement(WebDriverBy::xpath('//a[@aria-label="Log in with your password instead?"]'), 0);

            if (!$loginWithPass) {
                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")] | //h1[contains(text(), "Access Denied")]')) {
                    $retry = true;
                }

                return false;
            }

            $this->acceptCookies($selenium);
            $loginWithPass->click();

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password"]'), 10);
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="loginId"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
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
            $this->logger->debug("click by 'remember me'");

            // remember me
            $selenium->driver->executeScript('
                var rememberme = document.querySelector(\'[id = "mat-slide-toggle-1-input"]\');
                if (rememberme)
                    rememberme.click();
            ');

            $captchaField = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 0);

            if ($captchaField) {
                $captcha = $selenium->parseCaptchaImg();

                if ($captcha === false) {
                    return false;
                }

                $captchaField->sendKeys($captcha);
                $this->savePageToLogs($selenium);

                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
                $this->savePageToLogs($selenium);

                if (!$button) {
                    if ($captcha === '') {
                        $this->captchaReporting($selenium->recognizer, false);

                        throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
                    }

                    return false;
                }

                $button->click();
            }

            $this->logger->debug("click by btn");
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
            $this->savePageToLogs($selenium);

            if (!$button) {
                return false;
            }
            $button->click();

            $captchaField = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 15);
            $this->savePageToLogs($selenium);

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
                    } catch (
                        Facebook\WebDriver\Exception\StaleElementReferenceException
                        | Facebook\WebDriver\Exception\ElementClickInterceptedException
                        $e
                    ) {
                        $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                    }
                }
            }

            $selenium->waitForElement(WebDriverBy::xpath('
                //span[contains(@class, "bwc-logo-header__user-name")]
                | //span[contains(text(), "Invalid Captcha")]
                | //div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Update your password")]
                | //div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Get your one-time PIN code")]
                | //div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)]
                | //div[contains(@class, "bwc-form-errors")]/span
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
                    $this->savePageToLogs($selenium);
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

                if (!$result) {
                    $this->savePageToLogs($selenium);
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
            if (
                $this->http->FindSingleNode('//span[contains(text(), "Invalid Captcha")]')
                || (isset($captcha) && $captcha === '')
            ) {
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

                    $loginInput->click();
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
        } catch (UnknownServerException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
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

    private function switchToCurl()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $curl = new HttpBrowser("none", new CurlDriver());
//        $curl->setHttp2(true); // causing error "Network error 92 - HTTP/2 stream 0 was not closed cleanly"
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

    private function solveCaptchaImg()
    {
        $this->logger->notice(__METHOD__);

        if ($captchaField = $this->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 0)) {
            $captcha = $this->parseCaptchaImg();

            if ($captcha === false) {
                $this->saveResponse();

                return false;
            }

            $captchaField->sendKeys($captcha);
        }

        return true;
    }
}
