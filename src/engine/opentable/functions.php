<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerOpentable extends TAccountChecker
{
    use ProxyList;
    //use OtcHelper;
    use SeleniumCheckerHelper;
    private const JSON_REGEXP = '/__INITIAL_STATE__\s*=\s*(.+);/';
    /** @var CaptchaRecognizer */
    private $recognizer;

    private $lastUrl = null;
    private $reps = 0;
    private $countryId = 'US';
    private $domain = 'com';
    private $databaseRegion = 'na';
    private $history = [];

    private $regionOptions = [
        ""    => "Select your region",
        "CA"  => "Canada",
        "UK"  => "United Kingdom",
        "USA" => "United States",
    ];
    private $currentItin = 0;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerOpentableSelenium.php";

        return new TAccountCheckerOpentableSelenium();
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields['Login2']['Options'] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setUserAgent(\HttpBrowser::PROXY_USER_AGENT);
        //$this->http->setRandomUserAgent();
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);

        if ($this->AccountFields['Login2'] == 'CA') {
            $this->countryId = 'CA';
            $this->domain = 'ca';
            $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_USA));
            $this->http->setUserAgent("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7");
        } elseif ($this->AccountFields['Login2'] == 'UK') {
            $this->countryId = 'UK';
            $this->domain = 'co.uk';
            $this->databaseRegion = 'eu';
            $this->http->SetProxy($this->proxyUK());
            $this->http->setUserAgent("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7");
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->GetUrl("https://www.opentable.{$this->domain}/my/Profile", 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful() && $this->http->currentUrl() != "https://www.opentable.{$this->domain}/my/login?ra=mp") {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setMaxRedirects(15);

        /* $this->State['data']['correlationId'] = '974a8b89-98d8-4af1-b56c-a4ce8df53faa';
         $this->selenium('');
         return;*/
        $this->GetUrl("https://www.opentable.{$this->domain}/my/Profile");

        if ($this->http->Response['code'] == 403) {
            $this->setProxyGoProxies();
            $this->GetUrl("https://www.opentable.{$this->domain}/my/Profile");
        }

        // switch to ol auth, it helps
        /*
        if (strstr($this->http->currentUrl(), '/authenticate/start?isPopup=false&ra=mp')) {
            $this->http->removeCookies();
            $this->GetUrl("https://www.opentable.{$this->domain}/my/Profile");

            if (strstr($this->http->currentUrl(), '/authenticate/start?isPopup=false&ra=mp')) {
                return false;
            }
        }
        */

        if ($this->http->ParseForm('Login')) {
            $this->http->SetInputValue("txtUserEmail", $this->AccountFields['Login']);
            $this->http->SetInputValue("txtUserPassword", $this->AccountFields['Pass']);
            $this->http->SetInputValue("btnMember1", "Sign in");
        } // new form
        elseif ($this->http->ParseForm('loginForm')) {
            $this->http->SetInputValue("Email", $this->AccountFields['Login']);
            $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
            $this->http->SetInputValue("RememberMe", 'true');

            $captcha = $this->parseFunCaptcha();

            if ($captcha !== false) {
                $this->http->SetInputValue('fc-token', $captcha);
            } elseif (
                $this->http->FindPreg('/window\.OT\.SRS\.disableRecaptchaV3 = false;/')
                && $this->http->FindSingleNode('//form[@id = "loginForm"]//input[@name = "recaptchav3-response"]/@id')
            ) {
                $captcha = "";
//                $captcha = $this->parseCaptcha();
//                if ($captcha === false) {
//                    return false;
//                }
                $this->http->SetInputValue('recaptchav3-response', $captcha);
            }
        } elseif (strstr($this->http->currentUrl(), '/authenticate/start?isPopup=false&ra=')) {
            $captchaKey = $this->http->FindPreg("/6Ldcf5gUAAAAANNm2f66Vs0s7G5muVGh9j17Neex/");

            if (!$captchaKey) {
                return false;
            }

            $this->State['pageurl'] = $this->http->currentUrl();

            $data = [
                "operationName" => "SendVerificationCodeEmail",
                "variables"     => [
                    "verifyEmail"     => false,
                    "email"           => strtolower($this->AccountFields['Login']), // refs #21067
                    "loginType"       => "profile",
                    "requestedAction" => "mp",
                    "path"            => $this->http->currentUrl(),
                ],
                "extensions"    => [
                    "persistedQuery" => [
                        "version"    => 1,
                        "sha256Hash" => "b2d378ab902bfbc650cc50fe6e20415c9ce3803c9a82fe830f7834ef31c403fe",
                    ],
                ],
            ];
            $headers = [
                "Accept"          => "*/*",
                "content-type"    => "application/json",
                "x-csrf-token"    => $this->http->getCookieByName("_csrf"),
                "ot-page-type"    => "authentication_start",
                "ot-page-group"   => "user",
                "X-Query-Timeout" => "2000",
            ];

            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $this->State['userAgent'] = $this->http->userAgent;

            $this->http->PostURL("https://www.opentable.{$this->domain}/dapi/fe/gql?optype=mutation&opname=SendVerificationCodeEmail", json_encode($data), $headers);

            $response = $this->http->JsonLog();
            $correlationId = $response->data->sendVerificationCodeEmail->correlationId ?? null;

            if (!isset($correlationId)) {
                $this->logger->error("Something went wrong");

                // it helps
                if (
                    isset($response->errors[0]->message)
                    && in_array($response->errors[0]->message, [
                        'Timeout for login-service-na exceeded',
                        "Error response from login-service-na: 400",
                    ])
                ) {
                    throw new CheckRetryNeededException(2, 0);
                }

                return false;
            }

            $this->AskQuestion("We've sent a code to {$this->AccountFields['Login']}. Enter the code to continue.", null, "Question");
            $this->State['captchaKey'] = $captchaKey;
            $this->State['headers'] = [
                "Accept"          => "*/*",
                "Content-Type"    => "application/json",
                "x-csrf-token"    => $this->http->getCookieByName("_csrf"),
            ];
            $this->State['data'] = [
                "checkExistingEmailEnabled" => false,
                "email"                     => strtolower($this->AccountFields['Login']), // refs #21067
                "correlationId"             => $correlationId,
                "verificationCode"          => '',
                "loginContextToken"         => '{"loginType":"popup-redirect","requestedAction":"https://www.opentable.com/"}',
                "recaptchaToken"            => '',
                "tld"                       => $this->domain,
                "databaseRegion"            => $this->databaseRegion,
                "verifyCredentialsEnabled"  => false,
            ];

            return true;
        } else {
            return $this->checkErrors();
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if (!empty($this->State['userAgent'])) {
            $this->logger->notice("restore UA from State");
            $this->http->setUserAgent($this->State['userAgent']);
        }

        $this->State['data']['verificationCode'] = $this->Answers[$this->Question];

        /*$this->selenium($this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);
        $this->http->GetURL('https://www.opentable.com/user/dining-dashboard');
        unset($this->State['data']);

        return true;*/

        $i = 0;

        do {
            $this->logger->debug("[#{$i}]: captcha recognising...");
            $captcha = $this->parseReCaptcha($this->State['captchaKey'], 'RuCaptcha');
            $i++;
        } while (
            $captcha === false
            && $i < 5
        );

        if ($captcha === false) {
            throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->State['data']['recaptchaToken'] = $captcha;
        $headers = $this->State['headers'] + [
            'Referer'         => "https://www.opentable.{$this->domain}/authenticate/verify-medium?isPopup=true&rp=https%3A%2F%2Fwww.opentable.com%2F&srs=1",
            "X-Forwarded-For" => "{$this->http->getIpAddress()}, 2.21.96.78, 23.211.108.102",
            "ot-page-type"    => "authentication_verify_medium",
            "ot-page-group"   => "user",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.opentable.{$this->domain}/dapi/fe/proxy/authentication-consumer-backend/authentication/start-passwordless-login", json_encode($this->State['data']), $headers);

        if ($this->http->Response['code'] == 403) {
            $this->sendNotification("403, debug // RR");
            $this->setProxyGoProxies();
            $this->http->PostURL("https://www.opentable.{$this->domain}/dapi/fe/proxy/authentication-consumer-backend/authentication/start-passwordless-login", json_encode($this->State['data']), $headers);
        }

        // refs #21067
        if ($this->http->Response['code'] == 401) {
            throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        // todo: debug
        if ($this->http->Response['code'] == 401 || (isset($response->nextStep) && $response->nextStep == 'Failure')) {
            if (isset($response->nextStep) && $response->nextStep == 'Failure') {
                $this->sendNotification("Failure // RR");
            }

            $captcha = $this->parseReCaptcha($this->State['captchaKey']);

            if ($captcha === false) {
                $captcha = $this->parseReCaptcha($this->State['captchaKey']);

                if ($captcha === false) {
                    $captcha = $this->parseReCaptcha($this->State['captchaKey']);

                    if ($captcha === false) {
                        throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }
                }
            }

            $this->State['data']['recaptchaToken'] = $captcha;
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.opentable.{$this->domain}/dapi/fe/proxy/authentication-consumer-backend/authentication/start-passwordless-login", json_encode($this->State['data']), $headers);
            $response = $this->http->JsonLog();
            unset($this->State['data']);
        }

        // 500 / Internal Server Error === Something went wrong. Request a new code.
        if ($this->http->Response['code'] == 500 && $this->http->FindPreg("/^Internal Server Error$/")) {
            $this->AskQuestion("We've sent a code to {$this->AccountFields['Login']}. Enter the code to continue.", "Something went wrong. Request a new code.", "Question");

            return false;
        }

        if (isset($response->loggedIn) && $response->loggedIn === true) {
            $this->http->GetURL('https://www.opentable.com/user/dining-dashboard');
        }

        return true;
    }

    public function Login()
    {
        sleep(2);

        if (isset($this->State['data']['checkExistingEmailEnabled'])) {
            return false;
        }

        if ($this->AccountFields['Login2'] == 'CA') {
            $headers = ["User-Agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/604.5.6 (KHTML, like Gecko) Version/11.0.3 Safari/604.5.6"];
        } elseif ($this->AccountFields['Login2'] == 'UK') {
            $headers = ["User-Agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/604.5.6 (KHTML, like Gecko) Version/11.0.3 Safari/604.5.6"];
        } else {
            $headers = [
                //                "User-Agent" => HttpBrowser::PROXY_USER_AGENT,
                "Accept"     => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
            ];
        }

        if (!$this->http->PostForm($headers) && !strstr($this->http->currentUrl(), 'https://secure.opentable.com/www.opentable.com/')) {
            return $this->checkErrors();
        }
        // redirect bug fix
        if ($this->http->Response['code'] == 404
            && $this->http->currentUrl() == 'http://www.opentable.com/www.opentable.com/orange-county-restaurants') {
            $this->logger->notice("Redirect fix");
            $this->http->GetURL('http://www.opentable.com/orange-county-restaurants');
        }

        // Your email and password don't match.
        if ($message = $this->http->FindSingleNode('//*[contains(text(), "Your email and password don\'t match.")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * We're sorry but we were unable to complete your request.
         * Our technical team has been notified of the problem and will resolve it shortly. Thank you.
         */
        if ($message = $this->http->FindSingleNode('//*[contains(text(), "We\'re sorry but we were unable to complete your request.")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Your account has been deactivated and is therefore prohibited from making additional reservations.
        if ($message = $this->http->FindPreg('/(Your account has been deactivated and is therefore prohibited from making additional reservations\.)/ims')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        //# Need select a location of restaurant
        if (
            $this->http->FindPreg('/home\.aspx/ims', false, $this->http->currentUrl())
            && $this->http->FindSingleNode("//span[contains(text(), 'Select a location to begin')]")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException('Please select a location of the restaurant', ACCOUNT_PROVIDER_ERROR); /*checked*/
        }

        if ($this->http->currentUrl() != "https://www.opentable.{$this->domain}/my/Profile") {
            $this->GetUrl("https://www.opentable.{$this->domain}/my/Profile");
        }
        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $state = $this->http->JsonLog($this->http->FindPreg(self::JSON_REGEXP), 3, false, 'vipEligibilityWindowEnd');
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindPreg("/userProfile\":\{\"firstName\":\"([^\"]+)/") . " " . $this->http->FindPreg("/userProfile\":\{\"firstName\":\"[^\"]+\",\"lastName\":\"([^\"]+)\"/")));
        // Status - vip
        $status = $this->http->FindPreg("/userInfo.isVip\s*=\s*([a-z]+)/ims");
        $this->logger->debug("Status: {$status}");

        if (isset($status)) {
            $this->SetProperty('Status', ($status == 'true') ? 'Vip' : "Member");
        }

        // Balance
        $this->SetBalance($state->diningDashboard->points);
        // You are only 2,800 points away from a $50 reward!
        $this->SetProperty("NeededNextReward", $state->diningDashboard->nextReward->requiredPoints ?? null);
        // Expiring balance
        $this->SetProperty("ExpiringBalance", $state->diningDashboard->userExpiringPoints->expiringPoints ?? null);

        if (
            isset($state->diningDashboard->userExpiringPoints->expiringPoints)
            && $state->diningDashboard->userExpiringPoints->expiringPoints > 0
            && $state->diningDashboard->userExpiringPoints->eligibleForExpiration == true
        ) {
            $this->SetExpirationDate(strtotime($state->diningDashboard->userExpiringPoints->expirationDate));
        }

        $this->history = $state->diningDashboard->pastReservations;

        /*
        $__ot_conservedHeaders = $this->http->FindPreg("/__ot_conservedHeaders:\s*'([^\']+)/");

        // TODO: AccountID: 3500329, Old site came back
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && empty($this->Properties['Name'])) {
            $this->parseOldSite();
        }

        // Expiration Date, refs #5443
        $this->http->RetryCount = 0; //todo
        $this->history = $this->getHistoryPage();

        // first date
        if (!empty($this->history) && is_array($this->history)) {
            foreach ($this->history as $current) {
                if (isset($current->ReservationDate, $current->DateFormat)) {
                    if (
                        $current->IsCancelled == true
                        || $current->ReservationPoints <= 0
                    ) {
                        $this->logger->notice("Skip not eligible transaction: {$current->ReservationPoints} / {$current->IsCancelled}");

                        continue;
                    }

                    $lastActivity = $current->ReservationDate;
                    $this->SetProperty("LastActivity", $lastActivity);

                    if (stripcslashes($current->DateFormat) == 'dd/MM/yyyy') {
                        $d = strtotime(str_replace('/', '-', $lastActivity), false);
                    } elseif (stripcslashes($current->DateFormat) == 'MM/dd/yyyy') {
                        $d = strtotime($lastActivity, false);
                    } else {
                        $this->sendNotification('opentable: Exp Date - new date format ' . $current->DateFormat);
                    }

                    if (isset($d) && $d !== false) {
                        $this->SetExpirationDate(strtotime("+1 year", $d));
                    }

                    break;
                }// if (isset($current->ReservationDate))
                // An error has occurred
                elseif ($error = $this->http->FindPreg("/\{\"Message\":\"(An error has occurred).\"\}/")) {
                    $this->logger->error($error);
                }
            }
        }// if (!empty($this->history) && is_array($this->history))

        // Points
        $this->http->setDefaultHeader("Accept", "application/json, text/plain, *
        /*");
        $this->GetUrl("https://www.opentable.{$this->domain}/my/api/reservations/points");
        $response = $this->http->JsonLog();

        if (!empty($response) && isset($response->Earned)) {
            // Balance
            $this->SetBalance($response->Earned);
            // You are only 2,800 points away from a $50 reward!
            if (
                isset($response->NextRewardPoints, $response->MaximumRewardReached)
                && $response->NextRewardPoints > 0
                && $response->MaximumRewardReached == false
            ) {
                $this->SetProperty("NeededNextReward", $response->NextRewardPoints - $response->Earned);
            }

            // refs #15565
            if (isset($__ot_conservedHeaders)) {
                $__ot_conservedHeaders = "&__ot_conservedHeaders={$__ot_conservedHeaders}";
            }
            /*
            if (!isset($__ot_conservedHeaders)) {
                if ($this->Balance > 0) {
                    $this->sendNotification("need to check exp date - refs #15565");
                }

                return;
            }
            * /

            if ($this->Balance <= 0) {
                return;
            }

            $this->GetUrl("https://oc-registry.opentable.{$this->domain}/v2/oc-points-widget/1.X.X/?gpid=" . urlencode($response->GlobalPersonId) . "&authToken=($response->AuthToken)&domain=com&culture=en&referrer=https://www.opentable.{$this->domain}/my/Profile{$__ot_conservedHeaders}&__oc_Retry=0");

            if (
                in_array($this->http->Response['code'], [503, 404])
                || $this->http->FindPreg('/(?: timed out after|Received HTTP code 503 from proxy after CONNECT)/', false, $this->http->Error)
                || $this->http->FindPreg("/(w\?ز5\?W\?u\?ޫj\?S\?伷\?|Ԙ|\?\?\?\?\?)i/", false, $this->http->Response['body'])
            ) {
                sleep(5);
                $this->GetUrl("https://oc-registry.opentable.{$this->domain}/v2/oc-points-widget/1.X.X/?gpid=" . urlencode($response->GlobalPersonId) . "&authToken=($response->AuthToken)&domain=com&culture=en&referrer=https://www.opentable.{$this->domain}/my/Profile{$__ot_conservedHeaders}&__oc_Retry=0");
            }

            $expBalance = $this->http->FindPreg("/>([\-\d\,\.]+) points? expiring on ([^<]+)<\/h4/");
            $exp = $this->http->FindPreg("/>[\-\d\,\.]+ points? expiring on ([^<]+)<\/h4/");
            $this->logger->debug("[Exp date]: {$expBalance} expiring on '{$exp}'");

            if (
                !$this->http->FindPreg("/,\"name\":\"oc-points-widget\"/")
                && !$this->http->FindPreg('/Received HTTP code 503 from proxy after CONNECT/', false, $this->http->Error)
            ) {
                $this->sendNotification("need to check exp date - refs #15565");
            }

            if (
                $exp && $expBalance
                && (!isset($this->Properties["AccountExpirationDate"]) || strtotime($exp) < $this->Properties["AccountExpirationDate"])
            ) {
                $this->SetExpirationDate(strtotime($exp));
                $this->SetProperty("ExpiringBalance", $expBalance);
            }
        }// if(!empty($response) && isset($response->Earned))
        */
    }

    public function ParseItineraries()
    {
        $this->http->FilterHTML = false;

        $result = $links = [];

        $state = $this->http->JsonLog($this->http->FindPreg(self::JSON_REGEXP), 3, false, 'upcomingReservations');
        $noLinkReservations = $state->diningDashboard->upcomingReservations;

        if ($noLinkReservations == []) {
            if ($this->ParsePastIts) {
                $pastItineraries = $this->parsePastItineraries();

                if (!empty($pastItineraries)) {
                    return $pastItineraries;
                }
            }// if ($this->ParsePastIts)

            return $this->noItinerariesArr();
        }

        /*
        if ($this->http->currentUrl() != "https://www.opentable.{$this->domain}/my/Profile") {
            $this->GetUrl("https://www.opentable.{$this->domain}/my/Profile");
        }

        $this->http->setDefaultHeader("Accept", "application/json, text/plain, *
        /*");
        $this->http->GetURL("https://www.opentable.{$this->domain}/my/api/reservations/upcoming");

        if ($this->http->Response['code'] == 500) {
            sleep(3);
            $this->http->GetURL("https://www.opentable.{$this->domain}/my/api/reservations/upcoming");
        }
        $response = $this->http->JsonLog();

        if (!empty($response) && is_array($response)) {
            foreach ($response as $it) {
                if (isset($it->ViewReservationUrl)) {
                    if ($it->ViewReservationUrl === "") {
                        $noLinkReservations[] = $it;
                    } else {
                        $link = $it->ViewReservationUrl;
                        $this->http->NormalizeURL($link);
                        $links[] = [
                            'ViewReservationUrl'   => $link,
                            'ReservationPartySize' => $it->ReservationPartySize ?? null,
                            'ReservationType'      => $it->ReservationType ?? null,
                        ];
                    }
                }
            }// foreach ($response as $it)
        }// if (isset($response) && is_array($response))
        elseif ($this->http->Response['body'] == '[]') {
            if ($this->ParsePastIts) {
                $pastItineraries = $this->parsePastItineraries();

                if (!empty($pastItineraries)) {
                    return $pastItineraries;
                }
            }// if ($this->ParsePastIts)

            return $this->noItinerariesArr();
        }

        foreach ($links as $link) {
            $ps = null;

            if (isset($link['ReservationPartySize'])) {
                $ps = (int) $link['ReservationPartySize'];
            }

            // Takeout order
            if (isset($link['ReservationType']) && $link['ReservationType'] == 4
                && $this->http->FindPreg('#/takeout/#', false, $link['ViewReservationUrl'])) {
                if ($it = $this->ParseEvent($link['ViewReservationUrl'], $ps)) {
                    $result[] = $it;
                }
            } elseif ($it = $this->ParseRestaurant($link['ViewReservationUrl'], $ps)) {
                $result[] = $it;
            }
        }
        */

        foreach ($noLinkReservations as $res) {
            $this->parseRestaurantMinimal($res);
        }

        if ($this->ParsePastIts) {
            $result = array_merge($result, $this->parsePastItineraries());
        }

        return $result;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"       => "PostingDate",
            "Restaurant" => "Description",
            "Points"     => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->http->FilterHTML = false;
        $this->logger->debug('[History start date: ' . (isset($startDate) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();
        $startIndex = sizeof($result);
        $result = $this->ParseHistoryPage($startIndex, $startDate);
        $this->getTime($startTimer);

        return $result;
    }

    protected function parseReCaptcha($key, $retry = false, $type = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        if ($type == 'RuCaptcha') {
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
            $this->recognizer->RecognizeTimeout = 120;
            $parameters = [
                "pageurl" => "https://www.opentable.{$this->domain}", //$this->State['pageurl'],
                "proxy"   => $this->http->GetProxy(),
                //"invisible" => 1,
                "version"   => "v3",
                "action"    => 'login',
                "min_score" => 0.9,
            ];

            return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters, $retry);
        }
        $postData = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => "https://www.opentable.{$this->domain}", //$this->State['pageurl'],
            "websiteKey" => $key,
            "minScore"   => 0.3,
            "pageAction" => "login",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    private function selenium($question)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $allCookies = array_merge($this->http->GetCookies(".opentable.com"), $this->http->GetCookies(".opentable.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("www.opentable.com"), $this->http->GetCookies("www.opentable.com", "/", true));

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.opentable.com/dfsdfs');

            foreach ($allCookies as $key => $value) {
                if (stristr($key, 'OT-Session')) {
                    $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => "opentable.com"]);
                } else {
                    $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".opentable.com"]);
                }
            }

            $selenium->http->GetURL('https://www.opentable.com/authenticate/verify-medium?isPopup=true&rp=https%3A%2F%2Fwww.opentable.com%2F&srs=1');

            $selenium->driver->executeScript('
            var date = {
                timestamp: new Date().getTime(),
                data: {
                    loginMedium:"email",
                    showEmailUpdatedToast: false,
                    showCCPA: false,
                    showContinueAsGuest: false,
                    showEmailMarketingOptIn: true,
                    emailMarketingOptInDefaultValue: false,
                    showPrivacyPolicyOptIn: false,
                    correlationId: "' . $this->State['data']['correlationId'] . '",
                    email: "' . $this->AccountFields['Login'] . '",
                    loginContextToken: "{\"loginType\":\"popup-redirect\",\"requestedAction\":\"https://www.opentable.com/\"}",
                    verifyEmail: false,
                    currentRoute: "/verify-medium"
                }
            };
            sessionStorage.setItem("passwordlessAuthentication", JSON.stringify(date));'
        );

//            if ($btnLogin = $selenium->waitForElement(WebDriverBy::xpath("//header//button[contains(text(),'Sign in')]"), 7)) {
//                $btnLogin->click();

            if ($emailVerificationCode = $selenium->waitForElement(WebDriverBy::id('emailVerificationCode'), 2)) {
                $emailVerificationCode->sendKeys($question);
            }
            $this->savePageToLogs($selenium);
            $selenium->waitForElement(WebDriverBy::xpath("//header//button[contains(@aria-label,'User account dropdown')]"), 7);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                throw new CheckRetryNeededException(3, 10);
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }
    }

    private function ParseEvent($url, ?int $partySize = null)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL($url);

        if (
            $this->http->Response['code'] == 500
//            && $this->http->FindSingleNode('//h1[contains(text(), "sorry, but we cannot complete your request due to a technical issue.")]')
        ) {
            sleep(3);
            $this->http->GetURL($url);

            if ($this->http->Response['code'] == 500) {
                return [];
            }
        }

        if (!empty($this->http->FindSingleNode("//script[@id = 'client-restaurant' and @type = 'application/json']"))) {
            //$result = $this->parseJsons($partySize);
        } elseif ($scriptData = $this->http->FindSingleNode("//script[@id = 'client-initial-state' and @type = 'application/json']")) {
            //$result = $this->parseJsons2($partySize, $scriptData);
        } elseif ($scriptData = $this->http->FindPreg('/w\.__INITIAL_STATE__\s*=\s*(.+?);w\.__TRANSLATIONS/u', true, null, false)) {
            $scriptData = preg_replace('/ "([\w\s]+)" /', ' $1 ', $scriptData);
            $this->logger->debug($scriptData);
            $result = $this->parseEventJsons3($partySize, $scriptData);
        } else {
            if ($this->http->FindSingleNode("//h1[contains(.,'Your purchase is complete, enjoy!')]")
                && $this->http->FindSingleNode("//p[contains(.,'Takeout orders')]")
                && $this->http->FindSingleNode("//span[@data-test='icSuccess']/following-sibling::span[contains(.,'Order received')]")
            ) {
                $this->logger->error("skip: takeout order, received");

                return [];
            }
            //$result = $this->parseHtml();
        }

        return $result;
    }

    private function parseEventJsons3(int $partySize, string $scriptData)
    {
        $this->logger->notice(__METHOD__);
        $data = $this->http->JsonLog($scriptData, 0);

        $event = $this->itinerariesMaster->add()->event();
        $event->setEventType(EVENT_EVENT);

        $confNo = $data->pickupData->reservation->order->orderNumber;
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);
        $event->general()->confirmation($confNo);

        $data->restaurant->address = array_filter((array) $data->restaurant->address, function ($v, $k) {
            return $k != '__typename' && !empty($v);
        }, ARRAY_FILTER_USE_BOTH);
        $event->place()
            ->name($data->restaurant->name)
            ->phone($data->restaurant->contactInformation->formattedPhoneNumber ?? null)
            ->address(implode(', ', $data->restaurant->address));

        if (isset($partySize)) {
            $event->booked()->guests($partySize);
        }

        $event->booked()
            ->start(strtotime($data->pickupData->reservation->localDateTime))
            ->noEnd();

        $event->price()->total($data->pickupData->reservation->order->totalAmount / 100);
        $event->price()->tax($data->pickupData->reservation->order->taxesAmount / 100);
        $event->price()->currency($data->pickupData->reservation->order->currency);

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($event->toArray(), true), ['pre' => true]);

        return [];
    }

    private function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'USA';
        }

        return $region;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // Access is allowed
        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href | //a[contains(text(), 'Account Details')]")) {
            return true;
        }

        return false;
    }

    private function GetUrl($url, $timeout = null)
    {
        $this->lastUrl = $url;
        $this->http->GetURL($this->lastUrl, [], $timeout);

        if ($this->http->FindPreg("/but we encountered a failure during the last operation. If you were in the process of making a reservation/ims")) {
            if ($this->reps < 3) {
                $this->GetUrl($this->lastUrl, $timeout);
                $this->reps++;
            } else {
                throw new CheckException('The server didn\'t respond in time. Please try again later.', ACCOUNT_PROVIDER_ERROR);
            }
        }
    }

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $key = $this->http->FindPreg('/FunCaptcha\(\{\s*public_key:\s*\'([\w-]+)/ims');

        if (!$key) {
            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        // // anticaptcha version
        // $postData = array_merge(
        //     [
        //         "type"             => "FunCaptchaTask",
        //         "websiteURL"       => $this->http->currentUrl(),
        //         "websitePublicKey" => $key,
        //     ],
        //     []
        //     // $this->getCaptchaProxy()
        // );
        // $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        // $recognizer->RecognizeTimeout = 120;
        // $captcha = $this->recognizeAntiCaptcha($recognizer, $postData, $retry);

        // rucaptcha version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);

        if ($this->isBackgroundCheck()) {
            $recognizer->RecognizeTimeout = 120;
        } else {
            $recognizer->RecognizeTimeout = 100;
        }
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry, 3, 1);
        $this->increaseTimeLimit(300);

        $this->getTime($startTimer);

        return $captcha;
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/window.OT.SRS.recaptchaV3Key = \"([^\"]+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => "loginpage",
            "min_score" => 0.5,
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We’re making a few adjustments, so our site is currently down.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "We’re making a few adjustments, so our site is currently down.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //Site unknown error
        if ($message = $this->http->FindSingleNode('//span[@id="lblMsgSubTitle" and contains(text(), "we encountered a failure")]')) {
            throw new CheckException('We\'re sorry, but we encountered a failure during the last operation.', ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error - Read
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")
            // Server Error
            || $this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")
            // Service Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            // It's not you, it's us. We're aware of the issue and are fixing it.
            || $this->http->FindSingleNode('//h4[contains(text(), "It\'s not you, it\'s us. We\'re aware of the issue and are fixing it.")]')
            // traces
            || $this->http->FindPreg("/ at process._fatalException/")
            || ($this->http->FindPreg("/s.pageName=\"500error\"/") && $this->http->Response['code'] == 500)
            || $this->http->FindPreg("/An error occurred while processing your request\./")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# The server is temporarily unable to service your request. Please try again later.
        if ($message = $this->http->FindPreg("/(The server is temporarily unable to service your request\.\s*Please try again\s*later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Sorry about that!
         * It's not you, it's us. We're aware of the issue and are fixing it.
         * Please give us a moment, then try again.
         * Thanks for your patience!
         */
        if ($message = $this->http->FindSingleNode('//span[@id = "lblErrorMsg" and contains(., "It\'s not you, it\'s us. We\'re aware of the issue and are fixing it.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->setMaxRedirects(0);
        $this->GetUrl("https://www.opentable.{$this->domain}/");
        //# Site is currently unavailable
        if ($message = $this->http->FindSingleNode("//span[@id = 'lblRedirectText']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# www.opentable.com is currently unavailable
        if ($this->http->FindSingleNode("//body[contains(text(), 'The system cannot find the file specified')]")) {
            throw new CheckException("www.opentable.{$this->domain} is currently unavailable as we make improvements to the site. Please check back shortly to make your reservations online. We appreciate your patience and apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Server is too busy
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Server is too busy')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred while processing your request
        if ($this->http->FindPreg("/An error occurred while processing your request\./")
            || $this->http->FindPreg("/The server is temporarily unable to service your request\./")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parsePastItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Past Itineraries", ['Header' => 2]);
        $startTimer = $this->getTime();
        $result = [];

        /*
        if (empty($this->history)) {
            $pastIts = $this->getHistoryPage();
        } else {
        */
        $pastIts = $this->history;
//        }

        if (!isset($pastIts)) {
            return $result;
        }

        $this->logger->debug("Total " . count($pastIts) . " history items were found");

        /*
        // An error has occurred
        if (isset($pastIts->Message) && $pastIts->Message == 'An error has occurred.') {
            $this->logger->error($pastIts->Message);

            return $result;
        }// if (isset($response->Message) && $response->Message == 'An error has occurred.')
        */

        $i = 0;
        $cnt = 0;

        foreach ($pastIts as $pastIt) {
            if (!isset($pastIt->dateTime)) {
                $this->logger->error("Something went wrong with date");

                continue;
            }

            if ($pastIt->restaurantId == null) {
                $this->logger->notice("skip not itineraries {$pastIt->restaurantName}");

                continue;
            }
            $i++;

            $this->parseRestaurantMinimal($pastIt);
            $cnt++;

            if ($i >= 50) {
                break;
            }
        }
        $this->getTime($startTimer);
        $this->logger->debug("Total " . $cnt . " past reservations found");

        return $result;
    }

    private function parseRestaurantMinimal(object $res)
    {
        $this->logger->notice(__METHOD__);
        $confNo = $res->confirmationNumber;
        $this->logger->info(sprintf('[%s] Parse Past Itinerary #%s', $this->currentItin++, $confNo), ['Header' => 3]);

        if ($this->currentItin > 70) {
            $this->increaseTimeLimit(90);
        }

        if (empty($confNo) || $confNo == 0 || $confNo == '0') {
            $this->logger->error('Skip: bug itinerary');

            return;
        }
//        $date = $res->ReservationDate;
        $postDate = strtotime($res->dateTime);

        // for Address / Phone
        $browser = clone $this;
        $this->http->brotherBrowser($browser->http);
        $browser->http->setMaxRedirects(0);
        $browser->http->GetURL("https://www.opentable.{$this->domain}/book/view?rid={$res->restaurantId}&confnumber={$confNo}&token={$res->securityToken}");

        //$this->logger->debug(var_export($browser->http->Response['headers'], true), ['pre' => true]);
        if (isset($browser->http->Response['headers']['location'])) {
            $location = $browser->http->Response['headers']['location'];
            $browser->http->setMaxRedirects(5);
            $browser->http->GetURL(strpos($location, 'http') === false ? $this->http->getCurrentScheme() . ":" . $location : $location);
        }
        $r = $this->itinerariesMaster->add()->event();
        $r->setEventType(EVENT_RESTAURANT);
        $r->general()->noConfirmation();

        if (isset($res->reservationState) && stristr($res->reservationState, 'Cancel')) {
            $r->general()
                ->status('Cancelled')
                ->cancelled();
        }
        $r->booked()
            ->start($postDate)
            ->noEnd()
            ->guests($res->partySize);
        $address =
            $browser->http->FindSingleNode("//span[@itemprop = 'streetAddress']")
            ?? $browser->http->FindSingleNode("//a[contains(@href,'google.com/maps/')]/ancestor::div[./preceding-sibling::a[contains(@href,'google.com/maps/')]]//a")
            ?? implode(', ', $browser->http->FindNodes('//a[@data-test="phone-number"]/preceding-sibling::p'))
        ;

        /*
        if ($address) {
            $address = $browser->http->FindPreg('/"schema-json".+?"streetAddress":"(.+?)",/s', false, $address);
        }
        */

        $r->place()
            ->name($res->restaurantName)
            ->address($address)
            ->phone(
                $browser->http->FindSingleNode("//div[span[contains(text(), 'Phone number')]]/following-sibling::div[1][string-length(normalize-space(.)) > 0]")
                ?? $browser->http->FindSingleNode('//a[@data-test="phone-number"]')
            );

        if (isset($res->points)) {
            $r->program()->earnedAwards($res->points);
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function ParseHistoryPage($startIndex, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $response = $this->history;

        if (isset($response)) {
            $this->logger->debug("Total " . ((is_array($response) || ($response instanceof Countable)) ? count($response) : 0) . " history items were found");

            // An error has occurred
            if (isset($response->Message) && $response->Message === 'An error has occurred.') {
                $this->logger->error($response->Message);

                return $result;
            }// if (isset($response->Message) && $response->Message == 'An error has occurred.')

            if (isset($response->message) && $response->message === 'Bad Gateway') {
                $this->logger->error($response->message);

                return $result;
            }// if (isset($response->message) && $response->message == 'Bad Gateway')

            foreach ($response as $transaction) {
                if (!isset($transaction->dateTime)) {
                    $this->sendNotification("history - Something went wrong with date");

                    continue;
                }

                $postDate = strtotime($transaction->dateTime);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice('Skip date ' . date('Y-m-d', $postDate) . "({$postDate})");

                    continue;
                }
                $result[$startIndex]['Date'] = $postDate;
                $result[$startIndex]['Restaurant'] = $transaction->restaurantName;
                $result[$startIndex]['Points'] = $transaction->points;
                $startIndex++;
            }
        }

        return $result;
    }
}
