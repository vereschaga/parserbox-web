<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerHertz extends TAccountCheckerExtended
{
    use SeleniumCheckerHelper;
    use DateTimeTools;
    use ProxyList;

    public const REWARDS_PAGE_URL = "https://www.hertz.com/rentacar/emember/printMembershipCard.do";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $months = [];

    private $currentItin = 0;
    private $seleniumURL = null;

    // fix for itinerary report
    private $graphql = false;
    private $graphql_token = null;
    private $lastName = '';

    private $collectedHistory = true;
    private $endHistory = false;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerHertzSelenium.php";

        return new TAccountCheckerHertzSelenium();

        /*
        $testAccounts = [
            '57116178',
            '63643046',
        ];

        if (in_array($accountInfo['Login'], $testAccounts)) {
            require_once __DIR__ . "/TAccountCheckerHertzSelenium.php";

            return new TAccountCheckerHertzSelenium();
        }

        return new static();
        */
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        // todo: needed proxy, otherwise our amazon account will be blocked, 14 Jul 2020
        if ($this->attempt > 0) {
            $this->setProxyGoProxies(null, "de");
        } else {
            $this->setProxyGoProxies();
        }

        /*
        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
        */

        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 1) {
            $this->http->setRandomUserAgent(7);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(10);
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->setMaxRedirects(5);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        //$this->http->GetURL('https://www.hertz.com/rentacar/reservation/');

        $this->http->setCookie("AKA_POS", "en", "www.hertz.com");
        $this->http->setCookie("AKA_POS", "en", "www.hertz.com", "/rentacar");
        $this->http->setCookie("AKA_Lang", "US", "www.hertz.com");
        $this->http->setCookie("AKA_Lang", "US", "www.hertz.com", "/rentacar");
        $this->http->setCookie("AKA_Dialect", "enUS", "www.hertz.com");
        $this->http->setCookie("AKA_Dialect", "enUS", "www.hertz.com", "/rentacar");

        if ($this->http->currentUrl() != 'https://www.hertz.com/rentacar/member/login' || empty($this->http->Response['body'])) {
            $this->http->GetURL("https://www.hertz.com/rentacar/member/login");
        }

        // proxy issues
        if (
            strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            || strstr($this->http->Error, 'Network error 56 - Proxy CONNECT aborted')
            || strstr($this->http->Error, 'Network error 35 - OpenSSL SSL_connect: SSL_ERROR_SYSCALL')
            || strstr($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT')
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        $this->incapsulaWorkaround();

        if (!$this->http->ParseForm("submitLogin") && !$this->http->FindPreg('/_Incapsula_Resource/')) {
            return $this->checkErrors();
        }

        return $this->selenium();

        $this->http->SetInputValue("loginId", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("cookieMemberOnLogin", "true");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.hertz.com/rentacar/reservation/home";
        //$arg["SuccessURL"] = "https://www.hertz.com/rentacar/emember/affinity/account/index.jsp?targetPage=awardsView.jsp";
        return $arg;
    }

    public function Login()
    {
        //		if (!$this->http->PostForm())
        //			return $this->checkErrors();
//
        //		$this->incapsulaWorkaround(true);

        // Switch to English
        if ($this->http->currentUrl() == 'http://www.hertzarabic.com/?pos=SA&lang=ar') {
            $this->switchToEnglish();
        }

        $error =
            $this->http->FindPreg("/<span class=\"errorMessage\">([^<]+)</ims")
            ?? $this->http->FindSingleNode("//div[@id = 'error-list']/ul/li")
            ?? $this->http->FindSingleNode("//div[@id = 'error-message']")
            ?? $this->http->FindSingleNode('
                //div[contains(@class, "MuiAlert-message")]/p
                | //div[@id = "error-list" and @style = "display: block;"]
                | //ul[@class = "errorMessage"]/li[1]/span[1]
            ')
        ;

        if (!isset($error)) {
            $error = $this->http->FindPreg("/<span class=\"ememberPageTitle\">(Forgot Your Password\?)</ims");
        }

        if (!isset($error)) {
            $error = $this->http->FindPreg('/Please enter your Hertz Member Number\/User ID and Password in the fields noted./');
        }
        $badPass = $this->http->FindPreg("/The request is not valid/");

        if (
            isset($error)
            || $this->http->Response['code'] == 403
            || $badPass
        ) {
            $this->logger->error("[Error]: {$error}");

            if ($error == 'Username, password, or captcha value is invalid') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, $error, ACCOUNT_INVALID_PASSWORD);
            } else {
                $this->captchaReporting($this->recognizer);
            }

            // AccountID: 2602348 on prod returns error, try json authorization
            if ($error == 'The information you entered is incorrect. Please try again. [NZX235]' || $this->http->Response['code'] == 403 || $badPass
                || $error == 'You must login to view the page you have requested. [NZX235]'
                || $error == 'Sie müssen sich mit Ihrem Profil anmelden, um auf diese Seite zu gelangen. [NZX235]'
                || $error == 'No matching membership profiles found. Please verify the data that you have entered and try again. [WS2305]'
                || $error == 'Please enter your Hertz Member Number/User ID and Password in the fields noted.'
            ) {
                $this->logger->error(">>> Hertz on the prod return error \"{$error}\", Try json authorization");
//                $data = '{"loginCreateUserID":"","loginId":"'.$this->AccountFields['Login'].'","password":"'.$this->AccountFields['Pass'].'","cookieMemberOnLogin":false,"loginForgotPassword":""}';
                if (
                    empty($this->seleniumURL)
                    || $error == 'No matching membership profiles found. Please verify the data that you have entered and try again. [WS2305]'
                ) {
                    return $this->jsonAuth();
                }
            }
            // AccountID: 90161, 2525207
            if (strstr($error, ' - We are unable to process your request. Please try your submission again or Contact Us for assistance. [DZX006]')) {
                return $this->graphql($error);
            }

            if (!empty($error)) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
        }

        if ($message = $this->http->FindPreg("/For security reasons, your access has been locked/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("You have entered your password incorrectly. For security reasons, your access has been locked. Call 1-877-826-8782", ACCOUNT_LOCKOUT);
        }

        // Your member account has been locked.
        if (
            $this->http->FindSingleNode("//section[@id = 'lockedSection']//div[@id = 'lockedContactInfo']")
            || $this->http->FindSingleNode("//section[@id = 'lockedSection']//p[
                    contains(text(), 'Vill du ha omedelbar åtkomst till ert Hertz Gold Plus Rewards-konto')
                    or contains(text(), 'Du har prøvet at logge på for mange gange uden succes.')
                ]")
        ) {
            throw new CheckException("Your member account has been locked.", ACCOUNT_LOCKOUT);
        }

        if ($this->http->FindSingleNode('//form[@id="submitRecognize"]')) {
            throw new CheckException("You'll need to verify your identity on www.hertz.com", ACCOUNT_LOCKOUT);
        }

        if ($this->seleniumURL == 'https://www.hertz.com/') {//todo
            return $this->graphql(null);
        }

        // Access successful
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if (
            // AccountID: 4218535, 4046511, 4180222, 2569412, 673878, 896263, 2014726
            $this->http->FindPreg("/<div id=\"app\">/")
            // AccountID: 5021445
            || $this->seleniumURL == 'https://www.hertz.com/rentacar/member/legacy/gold/recognize'
            || $this->http->FindPreg("/\"systemErrors\":\s*\[\s*\"We're sorry your session has timed out. Please try again.\s*\[NZX305\]\"\s*\]/")// TODO
        ) {
            return $this->graphql(null);
        }

        // TODO
        if ($this->http->FindPreg("/\"systemErrors\":\s*\[\s*\"We're sorry your session has timed out. Please try again.\s*\[NZX305\]\"\s*\]/")) {
            if ($this->jsonAuth()) {
                return true;
            }
        }

        // authorization failed
        if (
            $this->http->FindNodes('//button[@id = "loginBtn"]/@id')
            && !$this->http->FindSingleNode("//div[@id = 'loading-fg']/@id")
        ) {
            if ($this->jsonAuth()) {
                return true;
            }

            throw new CheckRetryNeededException(2, 1);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // AccountID: 1473915, good profile
//        if ($this->graphql === true) {
//            return;
//        }

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // AccountID: 1553993, 5186275
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->graphql === true
            && $this->http->currentUrl() == 'https://www.hertz.com/'
            && !empty($this->Properties['Name'])
            && !empty($this->Properties['MembershipNumber'])
            && !empty($this->Properties['Status'])
        ) {
            $this->SetBalanceNA();
        } elseif ($this->graphql === true) {
            return;
        } elseif (
            $this->graphql === false && $this->http->currentUrl() == 'https://www.hertz.com/rentacar/member/login'
            || strstr($this->http->currentUrl(), 'https://hertz-prod.us.auth0.com/login?')
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT') !== false
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 5);
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $this->logger->debug("[Current URL]: " . strstr($this->http->currentUrl(), 'https://hertz-prod.us.auth0.com/authorize?'));

        // Membership number
        $this->SetProperty('MembershipNumber', $this->http->FindSingleNode("//div[@id = 'popup']/div/text()[1]"));
        // Name
        $this->SetProperty("Name", beautifulName(implode(' ', $this->http->FindNodes("//div[@id = 'popup']/div/text()[position() > 1]"))));

        $this->http->GetURL("https://www.hertz.com/rentacar/member/account/navigation?_=" . date("UB"));
        $response = $this->http->JsonLog();

        $clubTier = $response->data->clubTier ?? null;
        $status = null;

        switch ($clubTier) {
            case 'PC':
                $status = 'President\'s Circle';

                break;

            case 'G':
            case '1':
            case 'EXP':
                $status = 'Member';

                break;

            case 'FS':
                $status = 'Five Star';

                break;

            case 'P':
                $status = 'Platinum';

                break;

            default:
                /*
                $this->sendNotification("refs #19768. Unknown status {$clubTier}");
                */
        }
        // Status
        $this->SetProperty('Status', $status);

        //# Balance - Current #1 Awards Balance
        if (!$this->SetBalance($response->data->rewardsPoints ?? null)) {
            // from left menu
            if (!$this->SetBalance($this->http->FindSingleNode("//a[contains(text(), 'Earn Hertz Gold Plus Rewards')]/following-sibling::span[1]"))) {
                // from json
                $http2 = clone $this->http;
                $http2->GetURL("https://www.hertz.com/rentacar/rest/member/rewards/statement/?_=" . date("UB"));
                $response = $http2->JsonLog();
                $this->SetBalance($response->data->totalPoints ?? null);
                // We'll be back shortly
                if (
                    $this->ErrorCode == ACCOUNT_ENGINE_ERROR && (
                    $this->http->FindPreg("#re working quickly to recover from an issue and are incredible sorry for the inconvenience#usmi")
                    || $this->http->FindPreg('/("We\'re sorry your session has timed out\.\s*Please try again\.\s*\[NZX305\])/')
                )) {
                    throw new CheckRetryNeededException(3, 0);
                }
            }

            // session no created?
            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && ($this->http->currentUrl() == 'https://www.hertz.com/rentacar/reservation/' || $this->http->currentUrl() == 'https://www.hertz.com/')
            ) {
                if ($this->attempt == 0) {
                    throw new CheckRetryNeededException(3, 0);
                }

                return;
            }
        }
        /*
        if ($this->http->FindPreg("/The current page is not available in English/ims") !== null)
            $this->SetBalanceNA();

        if ($message = $this->http->FindPreg("/Driver's License Expiration Date/ims"))
            throw new CheckException("Driver's License Expiration Date.", ACCOUNT_PROVIDER_ERROR);

        // https://redmine.awardwallet.com/issues/11557#note-16
        $pointsToExpire = $this->http->FindSingleNode("//span[contains(text(), 'Points Expiring December 31st:')]/following-sibling::span");
        if ($pointsToExpire)
            $this->sendNotification("hertz - refs #11557. Need to check exp date");
        */

        // New Expiration Date   // refs #11557, 23160

        //		$this->http->GetURL("https://www.hertz.com/rentacar/emember/modify/statementTab");
        $this->switchToEnglish();
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $this->http->GetURL("https://www.hertz.com/rentacar/rest/member/rewards/statement/?_=" . time() . date("B"));
        $response = $this->http->JsonLog(null, 0, true);

        if (isset($response['data']['months'])) {
            foreach ($response['data']['months'] as $month) {
                $keyMonth = array_keys($month);
                $this->months[] = $keyMonth[0];
            }// foreach ($response['data']['months'] as $month)

            // Sort by date // refs #11914
            usort($this->months, function ($a, $b) {
                if ($a == $b) {
                    return 0;
                }

                return ($a < $b) ? 1 : -1;
            });
            $this->logger->debug("Months:");
            $this->logger->debug(var_export($this->months, true), ['pre' => true]);

            for ($i = 0; $i < count($this->months); $i++) {
                $this->logger->debug("Date: " . $this->months[$i]);
                $this->increaseTimeLimit(120);
                $this->http->GetURL("https://www.hertz.com/rentacar/rest/member/rewards/statement/" . $this->months[$i] . "?_=" . time() . date("B"));
                $responseTransactions = $this->http->JsonLog(null, 0);

                if (isset($responseTransactions->data->transactions)) {
                    $this->logger->debug("Date: " . $this->months[$i]);
                    $this->logger->debug(var_export($responseTransactions->data->transactions, true), ['pre' => true]);

                    foreach ($responseTransactions->data->transactions as $transaction) {
                        // refs https://redmine.awardwallet.com/issues/11557#note-19
                        if (
                            !empty($prevTransaction)
                            && $prevTransaction->date == $transaction->date
                            && (
                                $prevTransaction->type == 'RewardCancellation' && $transaction->type == 'RewardRedemption'
                                || $prevTransaction->type == 'RewardRedemption' && $transaction->type == 'RewardCancellation'
                            )
                            && $prevTransaction->desc == $transaction->desc
                            && $prevTransaction->date == $transaction->date
                            && $prevTransaction->points == -$transaction->points
                        ) {
                            $this->logger->notice("Skip canceled activity");
                        } elseif ($transaction->rentalAgreementNumber != 'N/A'
                            || ($transaction->type == 'RewardRedemption' && stristr($transaction->desc, 'Free') && stristr($transaction->desc, 'Rental'))) {
                            $exp = $transaction->date;
                            $this->logger->notice($transaction->points);

                            break;
                        } else {
                            $this->logger->notice("Skip activity");
                        }

                        $prevTransaction = $transaction;
                    }// foreach ($response->data->transactions as $transaction)
                }// if (isset($response->data->transactions))

                if (isset($exp)) {
                    break;
                }
            }// for ($i = 0; $i < count($nodes); $i++)
        }// if (isset($response['data']['months']))

        // Expiration Date
        /*
         * Last Activity may be in next formats:
         * 30/10/2014
         * Sep 3, 2013
         * 10 Févr 2014
         * etc.
         */
        if (isset($exp)) {
            $this->SetProperty("LastActivity", $exp);

            if (strstr($exp, '/')) {
                $exp = $this->ModifyDateFormat($exp, '/', true);
            } else {
                $exp = $this->dateStringToEnglish($exp);
            }

            if ($exp = strtotime($exp)) {
                $this->SetExpirationDate(strtotime("+12 month", $exp));
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->incapsulaWorkaround(true);
        }

        // uselsess code
        return;

        // refs #19944
        $this->logger->info('Zip Code', ['Header' => 3]);
        $this->logger->debug('ZipCodeParseDate: ' . ($this->State['ZipCodeParseDate'] ?? null));
        $this->logger->debug('Time: ' . strtotime("-1 month"));

        if (
            !isset($this->State['ZipCodeParseDate'])
            || $this->State['ZipCodeParseDate'] < strtotime("-1 month")
        ) {
            $this->http->GetURL("https://www.hertz.com/rentacar/emember/modify/profile.do");
            $headers = [
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "Accept-Language"  => "en-US,en;q=0.5",
                "Accept-Encoding"  => "gzip, deflate, br",
                "Content-Type"     => "application/json; charset=utf-8",
                "X-Requested-With" => "XMLHttpRequest",
                "Origin"           => "https://www.hertz.com",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.hertz.com/rentacar/rest/hertz/v2/member/modify/meta/data", "{}", $headers);
            $response = $this->http->JsonLog(null, 2);

            if (empty($response->data->uuid)) {
                return;
            }
            $this->http->PostURL("https://www.hertz.com/rentacar/rest/hertz/v2/member/modify/form/data", "{\"processUUID\":\"{$response->data->uuid}\"}", $headers);
            $response = $this->http->JsonLog();

            $zip = $response->data->personalZipCode ?? null;
            $country = $response->data->personalCountry ?? null;

            if ($country == 'US' && strlen($zip) == 9) {
                $zipCode = substr($zip, 0, 5) . " " . substr($zip, 5);
            } else {
                $zipCode = $zip;
            }
            $this->SetProperty("ZipCode", $zipCode);
            $street = $response->data->personalAddress1 ?? null;

            if (!empty($response->data->personalAddress2)) {
                $street .= $response->data->personalAddress2;
            }
            $state = '';

            if (isset($response->data->personalStateProvince)) {
                $state = ", " . $response->data->personalStateProvince;
            }

            if ($zipCode && $street) {
                $this->SetProperty("ParsedAddress",
                    $street
                    . ", " . $response->data->personalCity
                    . $state
                    . ", " . $zipCode
                    . ", " . $country
                );
            }// if ($zipCode)
            $this->State['ZipCodeParseDate'] = time();
        }// if (!isset($this->State['ZipCodeParseDate']) || $this->State['ZipCodeParseDate'] > strtotime("-1 month"))
    }

    public function ParseItineraries()
    {
        if ($this->graphql === true) {
            if (!$this->itinerariesMaster->getNoItineraries()) {
                $this->graphqlIts();
            }

            return [];
        }

        /*
        $name = strtoupper(ArrayVal($this->Properties, 'Name'));

        if (!empty($name)) {
            $name = explode(' ', $name);
            $name = $name[count($name) - 1];
        }
        $this->http->GetURL('https://www.hertz.com/rentacar/member/top/navigation?_=' . time() . date("B"));
        $data = json_decode($this->http->Response['body'], true);

        if (is_array($data) && isset($data['data']['lastName'])) {
            $name = $data['data']['lastName'];
        }
        */

        $result = [];
        $this->http->GetURL("https://www.hertz.com/rentacar/rest/hertz/v2/reservations/member/reservation-list?_=" . time() . date("B"));
        $response = $this->http->JsonLog();

//        if (
//            $this->http->FindPreg('#<head>\s*<META NAME="robots" CONTENT="noindex,nofollow">\s*<script src="/_Incapsula_Resource\?SWJIYLWA=[^\"]+">\s*</script>\s*<body>#')
//            || empty($this->http->Response['body'])
//        ) {
//            $this->selenium($this->ConfirmationNumberURL($arFields));
//        }

        if ($this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")) {
            $this->incapsula(false);

            $this->http->GetURL("https://www.hertz.com/rentacar/rest/hertz/v2/reservations/member/reservation-list?_=" . time() . date("B"));
            $response = $this->http->JsonLog();
        }

        if (!empty($response->message->messages[0])) {
            sleep(5);
            $this->logger->error($response->message->messages[0]);
            $this->http->GetURL("https://www.hertz.com/rentacar/rest/hertz/v2/reservations/member/reservation-list?_=" . time() . date("B"));
            $response = $this->http->JsonLog();

            if (empty($response->message->messages[0])) {
                $this->sendNotification('success retry reservation-list // MI');
            }
        }

        if (preg_match_all('/"confirmationNumber":\s*"(?<conf>[^"]+)"/ims', $this->http->Response['body'], $matches)) {
            foreach ($matches['conf'] as $conf) {
                $post = [
                    'confirmationNumber'                 => $conf,
                    'lastName'                           => "",
                    'quickReservationConfirmationNumber' => '-1',
                    'arrivingUpdate'                     => "",
                ];
                $headers = [
                    'Accept'           => 'application/json, text/javascript, */*; q=0.01',
                    'Content-Type'     => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ];
                $this->http->setCookie('IWGF', '0');

                $this->http->RetryCount = 0;
                $this->http->PostURL('https://www.hertz.com/rentacar/rest/hertz/v2/reservations/reservation', json_encode($post), $headers);
                $this->http->RetryCount = 2;

                if ($itinError = $this->checkRetrieveErrors()) {
                    if ($itinError === 'This reservation has already been cancelled. [DE56]') {
                        $result[] = [
                            'Kind'      => 'L',
                            'Number'    => $conf,
                            'Cancelled' => true,
                        ];
                    } else {
                        $this->logger->error("Skipping itin: {$itinError}");
                    }

                    continue;
                }

                if (
                    $this->http->FindPreg('/"Unable to process request\.\s*Please try your submission again\.\s*\[DE99\]"/')
                    || $this->http->FindPreg('/"Unable to process request\.\s*Please try your submission again\.\s*\[PE102\]"/')
                    || $this->http->FindPreg('/empty body/', false, $this->http->Error)
                    || $this->http->FindPreg('/Operation timed out/', false, $this->http->Error)
                ) {
                    sleep(5);
                    $this->http->RetryCount = 0;
                    $this->http->PostURL('https://www.hertz.com/rentacar/rest/hertz/v2/reservations/reservation', json_encode($post), $headers);
                    $this->http->RetryCount = 2;
                }
                $parsedRes = $this->ParseItineraryJS();

                if (is_array($parsedRes)) {
                    $result[] = $parsedRes;
                }
            }// foreach ($matches['conf'] as $conf)
        } elseif ($this->http->FindPreg("/,\"reservationList\"\s*:\s*\[\s*\]\s*\}\s*,\s*\"message\"\s*:\s*\{\s*\}\s*\}$/")) {
            return $this->noItinerariesArr();
        } elseif ($this->http->FindPreg("/\{\"resultInfo\":\{\"result\":\"success\",\"resultCode\":200\}/")) {
            $data = $this->http->JsonLog();

            if (isset($data->message, $data->message->messages) && is_array($data->message->messages) && !empty($data->message->messages)) {
                $this->logger->error($data->message->messages[0]);
            }
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.hertz.com/rentacar/reservation";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL('https://www.hertz.com/rentacar/reservation/#search');
        //$this->checkConfirmationSelenium('https://www.hertz.com/rentacar/reservation/#search');
        /*$this->http->PostURL("https://www.hertz.com/rentacar/ChangeLanguageHandler", [
            'returnURL'    => '/rentacar/reservation/reviewmodifycancel/index.jsp?targetPage=reviewReservationView.jsp',
            'country_code' => 'US',
            'language'     => 'en',
        ]);*/
        $post = [
            'confirmationNumber'                 => $arFields['ConfNo'],
            'lastName'                           => $arFields['LastName'],
            'quickReservationConfirmationNumber' => '-1',
            'arrivingUpdate'                     => '',
        ];
        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer'          => 'https://www.hertz.com/rentacar/reservation/',
            'Origin'           => 'https://www.hertz.com',
        ];
        $this->http->setCookie('IWGF', '0');

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.hertz.com/rentacar/rest/hertz/v2/reservations/reservation', json_encode($post), $headers);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")) {
            $this->incapsula(false);
//            $this->incapsulaWorkaround(true);

            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.hertz.com/rentacar/rest/hertz/v2/reservations/reservation', json_encode($post), $headers);
            $this->http->RetryCount = 2;
            //$this->sendNotification('check retrieve // MI');
            //$this->incapsulaWorkaround(true);
        }

        if ($error = $this->checkRetrieveErrors()) {
            return $error;
        }

        $it = $this->ParseItineraryJS();
        $it = [$it];

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"         => "Confirmation #",
                "Type"            => "string",
                "Size"            => 20,
                "Required"        => true,
                "InputAttributes" => ' style="width:144px;" ',
            ],
            "LastName" => [
                "Type"            => "string",
                "Size"            => 40,
                "Value"           => $this->GetUserField('LastName'),
                "InputAttributes" => ' style="width:144px;" ',
                "Required"        => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"             => "PostingDate",
            "Type"             => "Info",
            "Description"      => "Description",
            "Rental Agreement" => "Info",
            "Points"           => "Miles",
            "Bonus Points"     => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->FilterHTML = false;
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->logger->debug(var_export($this->months, true), ['pre' => true]);

        if (!$this->collectedHistory) {
            return $result;
        }

        $page = 0;

        foreach ($this->months as $month) {
            $this->logger->debug("[Page: {$page}]");
            $this->logger->debug("Date: " . $month);

            if ($this->endHistory) {
                break;
            }

            $this->increaseTimeLimit();
            $this->http->GetURL("https://www.hertz.com/rentacar/rest/member/rewards/statement/{$month}?_=" . date("UB"));

            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
            $page++;
        }// for ($i = 0; $i < count($nodes); $i++)

        $this->getTime($startTimer);

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    protected function parseCaptcha($data)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('captcha: ' . $data);
        $imageData = $this->http->FindPreg("/svg\+xml;base64\,\s*([^<]+)/ims", false, $data);
        $this->logger->debug("jpeg;base64: {$imageData}");

        if (!empty($imageData)) {
            $this->logger->debug("decode image data and save image in file");
            // decode image data and save image in file
            $imageData = base64_decode($imageData);

            if (!extension_loaded('imagick')) {
                $this->DebugInfo = "imagick not loaded";
                $this->logger->error("imagick not loaded");

                return false;
            }

            $im = new Imagick();
            $im->setBackgroundColor(new ImagickPixel('transparent')); //$im->setResolution(300, 300); // for 300 DPI example
            $im->readImageBlob($imageData);

            /*png settings*/
            $im->setImageFormat("png32");

            $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true) . ".jpeg";

            $im->writeImage($file);
            $im->clear();
            $im->destroy();
        }

        if (!isset($file)) {
            return false;
        }

        $this->logger->debug("file: " . $file);
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file, ["regsense" => 1]);
        unlink($file);

        return $captcha;
    }

    protected function parseHCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class='h-captcha']/@data-sitekey");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"    => "hcaptcha",
            "pageurl"   => $currentUrl ?? $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function checkConfirmationSelenium($url)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
            //$selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL($url);
            sleep(6);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            // save page to logs
            $selenium->http->SaveResponse();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }
    }

    private function incapsula($isRedirect = true)
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");

        if (isset($incapsula)) {
            sleep(2);
            $this->http->NormalizeURL($incapsula);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($incapsula);
            $this->http->RetryCount = 2;
        }// if (isset($distil))
        $this->logger->debug("parse captcha form");
        $formURL = $this->http->FindPreg("/\"POST\", \"(\/_Incapsula_Resource\?[^\"]+)\"\s*,/");

        if (!$formURL) {
            return false;
        }
        $captcha = $this->parseHCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->NormalizeURL($formURL);
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        if ($isRedirect) {
            $this->http->GetURL($referer);

            if ($this->http->Response['code'] == 503) {
                $this->http->GetURL($this->http->getCurrentScheme() . "://" . $this->http->getCurrentHost());
                sleep(1);
                $this->http->GetURL($referer);
            }
        }

        return true;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'form_container']/div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->increaseTimeLimit(300);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, true, 3, 1);

        $this->increaseTimeLimit($recognizer->RecognizeTimeout);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            ($this->seleniumURL && !in_array($this->seleniumURL, [
                'https://www.hertz.com/rentacar/member/legacy/gold/recognize',
                'https://www.hertz.com/rentacar/member/login',
            ])
            )
            || !empty($this->http->FindSingleNode('//div[@class = "cardUserDetails"]/text()[1]'))
            || $this->http->FindSingleNode("//span[contains(@class, 'memberNumber')]")
        ) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if ($this->attempt == 1) {
                $selenium->useFirefoxPlaywright();
            } elseif ($this->attempt == 2) {
                $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_59);
                $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->setKeepProfile(true);
                $selenium->disableImages();
                $selenium->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:59.0) Gecko/20100101 Firefox/59.0');
            } else {
                $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
                $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->setKeepProfile(true);
            }

            $selenium->useCache();
            $selenium->disableImages();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $loginURL = "https://www.hertz.com";

//            if ($this->attempt == 1) {
//                $loginURL = "https://www.hertz.com/rentacar/member/login";
//            }

            try {
                $selenium->http->GetURL($loginURL);
                $selenium->http->GetURL("https://www.hertz.com/rentacar/emember/printMembershipCard.do");
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            $loginInputTimeout = 15;
            $resultTimeout = 25;
            sleep(3);

            $this->waitLoginForm($selenium);

            if ($accept = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Accept Cookies") or @aria-label="allow cookies"]'), 0)) {
                $accept->click();
                sleep(1);
                $this->savePageToLogs($selenium);
            }

            if ($loginForm = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "loginLink"] | //li[@id = "loginLinkClub"]/a'), 0)) {
                $loginForm->click();
            } else {
                $selenium->driver->executeScript('try { document.querySelector(\'#loginLink, #loginLinkClub a\').click() } catch (e) {}');
            }

            $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "email" or @id = "loginId"] | //iframe[contains(@src, "_Incapsula_Resource") and @id = "main-iframe"]'), 7);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "email" or @id = "loginId"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput && ($loginForm = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "loginLink"]'), 0))) {
                try {
                    $loginForm->click();
                } catch (UnrecognizedExceptionException $e) {
                    $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    sleep(5);
                    $this->savePageToLogs($selenium);
                    $loginForm = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "loginLink"]'), 0);
                    $loginForm->click();
                }

                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "email" or @id = "loginId"]'), $loginInputTimeout);
                $this->savePageToLogs($selenium);
            }

            if (!$loginInput) {
                $this->callRetries($selenium);

                return $this->checkErrors();
            }

            // password
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            // Sign In
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "btn-login" or @id = "loginBtn"]'), 0);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->savePageToLogs($selenium);
                $this->checkErrors();

                $this->callRetries($selenium);

                return $this->checkErrors();
            }

            $selenium->driver->executeScript('var rememberMe = document.querySelector(\'#loginBox input[name = "cookieMemberOnLogin"]\'); if (rememberMe && rememberMe.checked == false) rememberMe.click();');
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            if ($captchaData = $this->http->FindSingleNode('//div[@id = "captcha-container"]//img/@src')) {
                $captchaInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "captcha"]'), 0);
                $this->savePageToLogs($selenium);

                if (!$captchaInput) {
                    $this->logger->error("captcha input not found");

                    return false;
                }

                $captcha = $this->parseCaptcha($captchaData);

                if (!$captcha) {
                    return false;
                }

                $captchaInput->sendKeys($captcha);
                $this->savePageToLogs($selenium);
            }

            $button->click();

            $res = $selenium->waitForElement(WebDriverBy::xpath("
                //div[@id = 'error-list' and @style = 'display: block;']
                | //div[@id = 'error-message']
                | //span[contains(@class, 'memberNumber')]
                | //div[@id = 'headerWelcomeBoxMemberId']
                | //div[contains(text(), 'To login to your account, enter the code sent to your email.')]
            "), 15);
            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            if ($question = $this->http->FindSingleNode("//div[contains(text(), 'To login to your account, enter the code sent to your email.')]")) {
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }

                $this->Question = $question;
                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Step = "Question";

                return false;
            }

            // provider bug fix
            try {
                if (!$res && $selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'loading-fg']"), 0)) {
                    //            if ($selenium->http->currentUrl() == 'https://www.hertz.com/') {
                    $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

                    $retry = true;

                    return $result;

                    if ($selenium->http->currentUrl() != 'https://www.hertz.com/rentacar/member/login') {
                        $selenium->http->GetURL("https://www.hertz.com/rentacar/emember/modify/profile");
                        $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
                    }

                    $this->savePageToLogs($selenium);

                    if ($selenium->http->currentUrl() == 'https://www.hertz.com/rentacar/member/login') {
                        $this->waitLoginForm($selenium);

                        $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "loginId"]'), $loginInputTimeout);
                        // password
                        $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
                        // Sign In
                        $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "loginBtn" or @id = "loginButton"]'), 0);
                        $this->savePageToLogs($selenium);

                        if (!$loginInput || !$passwordInput || !$button) {
                            return $this->checkErrors();
                        }

                        $this->savePageToLogs($selenium);
                        $loginInput->sendKeys($this->AccountFields['Login']);
                        $passwordInput->sendKeys($this->AccountFields['Pass']);
                        $button->click();
                    }

                    $selenium->waitForElement(WebDriverBy::xpath('
                        //span[contains(@class, "memberNumber")]
                        | //div[@id = "headerWelcomeBoxMemberId"]
                        | //div[contains(@class, "MuiAlert-message")]/p
                    '), $resultTimeout);
                    $this->savePageToLogs($selenium);
                // AccountID: 5593926, 2913422, 3177078, 668559)
    //            } elseif ($selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'loading-fg']"), 0)) {
                } elseif (!$res && $selenium->waitForElement(WebDriverBy::id('loginUsername'), 0)) {
                    //                $selenium->http->GetURL("https://www.hertz.com/login");
                    //                sleep(5);
                    $loginInput = $selenium->waitForElement(WebDriverBy::id('loginUsername'), $loginInputTimeout);
                    // password
                    $passwordInput = $selenium->waitForElement(WebDriverBy::id('loginPassword'), 0);
                    // Sign In
                    $this->savePageToLogs($selenium);

                    if (!$loginInput || !$passwordInput) {
                        return $this->checkErrors();
                    }

                    $this->savePageToLogs($selenium);
                    $loginInput->sendKeys($this->AccountFields['Login']);
                    $passwordInput->sendKeys($this->AccountFields['Pass']);

                    $button = $selenium->waitForElement(WebDriverBy::xpath('//button[not(@disabled) and @id = "loginFormLoginButton"]'), 3);
                    $this->savePageToLogs($selenium);
                    $button->click();

                    $res = $selenium->waitForElement(WebDriverBy::xpath('
                        //span[contains(@class, "memberNumber")]
                        | //div[@id = "headerWelcomeBoxMemberId"]
                        | //div[contains(@class, "MuiAlert-message")]/p
                    '), $resultTimeout);
                    $this->savePageToLogs($selenium);
                }
            } catch (WebDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->savePageToLogs($selenium);
            }

            if (!$selenium->waitForElement(WebDriverBy::xpath('
                    //div[@id = "error-list" and @style = "display: block;"]
                    | //div[@id = \'error-message\']
                    | //div[contains(@class, "MuiAlert-message")]/p
                    | //input[@name = "loginId"]
                '), 0)
                && $selenium->http->currentUrl() != 'https://www.hertz.com/rentacar/member/login'
            ) {
                try {
                    $selenium->http->GetURL("https://www.hertz.com/rentacar/rest/hertz/v2/reservations/member/reservation-list?_=" . time() . date("B"));
                } catch (StaleElementReferenceException | UnknownServerException | NoSuchDriverException | NoSuchWindowException $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                }
            }

            $this->savePageToLogs($selenium);
            $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

            if ($this->http->FindPreg("/\"result\":\s*\"session-timeout\"/i")) {
                throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $result = true;
            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->seleniumURL}");
        } catch (
            StaleElementReferenceException
            | UnknownServerException
            | NoSuchDriverException
            | NoSuchWindowException
            | WebDriverCurlException $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (ScriptTimeoutException | TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "TimeoutException";
            // retries
            if (
                strstr($e->getMessage(), 'timeout')
                || strstr($e->getMessage(), 'Timeout loading page after')
                || strstr($e->getMessage(), 'Command timed out in client when executing')
            ) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return $result;
    }

    private function waitLoginForm($selenium)
    {
        $this->logger->notice(__METHOD__);
        $time = 0;

        while (
            $selenium->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "fullPageSpinnerModule")]
            '), 0)
            && $time < 7
        ) {
            $time++;
            sleep(1);

            try {
                $this->savePageToLogs($selenium);
            } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                $this->logger->error("exception: " . $e->getMessage());
            }
        }
    }

    private function callRetries($selenium)
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//*[self::h1 or self::span][contains(text(), 'This site can’t be reached') or contains(text(), 'Unable to connect')]")
            || $this->http->FindSingleNode('//iframe[contains(@src, "_Incapsula_Resource") and @id = "main-iframe"]/@src')
            || $this->http->FindSingleNode("
                //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                | //p[contains(text(), 'Health check')]
            ")
        ) {
            $selenium->markProxyAsInvalid();

            if ($this->attempt == 2) {
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
            }

            throw new CheckRetryNeededException(3, 0);
        }
    }

    private function incapsulaWorkaround($retry = false)
    {
        $this->logger->notice(__METHOD__);
        // incapsula workaround
        if (
            $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")
            || $this->http->FindPreg("/<head>\s*<META NAME=\"robots\" CONTENT=\"noindex,nofollow\">\s*<script src=\"\/_Incapsula_Resource\?SWJIYLWA=[^\"]+\">\s*<\/script>\s*<body>/")
        ) {
            if ($retry) {
                throw new CheckRetryNeededException(3, 1);
            }

            return true;
        }

        return false;
    }

    private function jsonAuth()
    {
        $this->logger->notice(__METHOD__);
        $data = [
            "loginCreateUserID"   => false,
            "loginId"             => $this->AccountFields['Login'],
            "password"            => $this->AccountFields['Pass'],
            "cookieMemberOnLogin" => 'true',
            "loginForgotPassword" => "",
            "enteredcaptcha"      => null,
            "isLINK"              => false,
        ];
        $headers = [
            "Content-Type"     => "application/json",
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.hertz.com/rentacar/member/authentication", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (
            $this->http->FindPreg("/\"result\": \"success\"\,/")
            || $this->http->FindPreg("/\"result\": \"success_to_profile\"\,/")
            || $this->http->FindPreg("/\"result\": \"success_to_upgrade\"\,/")
            || $this->http->FindPreg("/\"result\": \"success_econsent_required\"\,/")
            || $this->http->FindPreg("/\"result\": \"success_to_erenewal\"\,/")
            || $this->http->FindPreg("/\"result\": \"login_sms_failed\"\,/")
        ) {
            return true;
        }
        /**
         * Help Us Recognize You.
         *
         * Establishing online access to your Gold Plus Rewards account is simple.
         * First, we'll need you to verify your identity by providing the following information on file with Hertz.
         * Then you will have the opportunity to create your own password so that you can access your account.
         * If you enrolled in Gold by phone - via our reservation call center - or if your enrollment originated in Brazil,
         * please use your drivers license, not credit card, to verify your identity.
         * Otherwise, we invite you to use either - your Drivers License or Credit Card - on file.
         */
        if ($this->http->FindPreg("/\"result\": \"legacy_recognize\"\,/")) {
            $this->throwProfileUpdateMessageException();
        }
        // Your member account has been locked.
        if ($this->http->FindPreg("/\"resultInfo\":\s*\{\s*\"result\":\s*\"reset_locked\",/")) {
            throw new CheckException("Your member account has been locked.", ACCOUNT_LOCKOUT);
        }
        $message = $response->message->systemErrors[0] ?? null;

        if (isset($message[0])) {
            $message = $message[0];
        }

        $this->logger->error("[Error]: {$message}");

        if (in_array(Html::cleanXMLValue($message), [
            'The User Id and Password combination does not exist [NEX144]',
            'Member Number - A valid member number must be entered to log in or to create/find a password. [NZX005]',
            'No matching membership profiles found. Please verify the data that you have entered and try again. [WS2305]',
            'The information you entered is incorrect. Please try again. [NZX235]',
            'Log in - Er is een probleem met uw login informatie. Neem alstublieft contact met ons op om het probleem op te lossen. [NEX100]',
            'No matching membership profiles found. Please verify the data that you have entered and try again.                                  [WS2305]',
            'The User Id and Password combination does not exist [NEX144]',
            'Member Name does not match, please call us [NEX179]',
            'Multiple profiles found based on search criteria. Please verify the data that youve entered or contact us for assistance. [WS2306]',
            'Member Number does not match, please call us [NEX180]',
            'Log In- There is a problem with your login information. Please contact us to resolve. [NEX100]',
        ])
            || strstr($message, ' - We are unable to process your request.')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (in_array(Html::cleanXMLValue($message), [
            'You must login to view the page you have requested. [NZX235]',
            'Sie müssen sich mit Ihrem Profil anmelden, um auf diese Seite zu gelangen. [NZX235]',
            'Vous devez vous connecter pour accéder à la page demandée. [NZX235]',
            '您输入的信息是不正确的。请再试一次。 [NZX235]',
            '입력하신 정보가 잘못되었습니다. 다시 시도하십시오. [NZX235]',
            'Per visualizzare la pagina richiesta devi effettuare il log in. [NZX235]',
            'Sinun täytyy kirjautua sisään nähdäksesi sivun sisällön [NZX235]',
        ])
        ) {
            throw new CheckException('The information you entered is incorrect. Please try again. [NZX235]', ACCOUNT_INVALID_PASSWORD);
        }

        if (in_array(Html::cleanXMLValue($message), [
            'An unexpected error has occurred on your login. Please try again. If the issue persists, please contact us. [NEX248]',
        ])
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/^\s*\[DZX006-77-ERR-\d+-\d{2}\]\s*$/", false, Html::cleanXMLValue($message))) {
            throw new CheckException("No matching membership profiles found. Please verify the data that you have entered and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/\"message\":\s*\{\s*\"fieldErrors\":\s*\[\s*\[\s*\"loginId\",\s*\[\s*\"((?:User ID - Only specific special characters will be accepted: period\(\.\); hyphen\(\-\); underscore\(_\); and .\"at.\" symbol \(@\)\.\s*\[NEX142\]|Member Number - A valid member number must be entered to log in or to create\/find a password\.\s*\[NZX005\]))\"\s*\]/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("#WSHTTP POST on resource https://esb.rtf-pci-prod.hertz.com:443/sys-api-sfdc-legacy-v3/api/identifyCustomer failed: internal server error \(500\)\.#")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->incapsulaWorkaround(true);

        return false;
    }

    private function switchToEnglish()
    {
        $this->logger->notice("Switch to English");
        $this->http->PostURL('https://www.hertz.com/rentacar/ChangeLanguageHandler', [
            'country_code' => 'US',
            'language'     => 'en',
            'returnURL'    => '/rentacar/emember/login.do',
        ]);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/(An unexpected error has occured\. Please try again\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The Hertz website is experiencing a system error at this time
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The Hertz website is experiencing a system error at this time')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(The Hertz website is experiencing a system error at this time\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Nous faisons tout notre possible pour remédier rapidement à ce problème.
         * Veuillez nous excuser pour la gêne occasionnée.
         */
        if ($message = $this->http->FindPreg("/(Nous faisons tout notre possible pour rem\&eacute;dier rapidement \&agrave; ce probl\&egrave;me\.\s*Veuillez nous excuser pour la g&ecirc;ne occasionn\&eacute;e.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "We\'re performing some maintenance on the website right now and should be ready to serve you again shortly.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The Hertz website is experiencing a system error at this time
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'The Hertz website is experiencing a system error at this time')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# System maintenance
        if (($message = $this->http->FindPreg("/website is experiencing a system error at this time/ims"))
            || ($message = $this->http->FindPreg("/(The Hertz website is experiencing a system error at this time\.)/ims"))) {
            throw new CheckException("The Hertz website is experiencing a system error at this time.", ACCOUNT_PROVIDER_ERROR);
        }
        //# The service provider's website is currently not operational
        if ($message = $this->http->FindPreg("/The function that you have requested is temporarily not available. We apologize for the inconvenience./ims")) {
            throw new CheckException("The service provider's website is currently not operational. Please try your request again at a later time.", ACCOUNT_PROVIDER_ERROR);
        }
        //# We'll be back shortly
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re working quickly to recover from an issue and are incredibly sorry for the inconvenience.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# System error
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "system error")]', null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# System error
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'The Hertz website is experiencing a system error at this time.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Gateway Timeout
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Gateway Timeout')]")
            // Internal Server Error
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")
            // Internal Server Error
            || $this->http->FindPreg("/<TITLE>Internal Server Error<\/TITLE>/ims")
            // Http/1.1 Service Unavailable
            || $this->http->FindPreg("/<body><b>Http\/1\.1 Service Unavailable<\/b><\/body>/ims")
            // Error page exception
            || $this->http->FindSingleNode("//h1[contains(text(), 'Error page exception')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '503 Service Unavailable')]")
            || $this->http->FindSingleNode("//h4[contains(text(), 'SRVE0260E: The server cannot use the error page specified for your application to handle the Original Exception printed below.')]")
            || $this->http->FindSingleNode("//h3[contains(text(), 'SRVE0255E: A WebGroup/Virtual Host to handle www.hertz.com:443 has not been defined.')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The server encountered an internal error or misconfiguration and was unable to complete your request.
        if ($message = $this->http->FindPreg("/(The server encountered an internal error or misconfiguration and was unable to complete your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // hard code, site bug
        if ($this->http->currentUrl() == 'https://www.hertzarabic.com/?pos=SA&lang=ar') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function graphql($error = null): bool
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->GetURL("https://www.hertz.com");
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json",
        ];
        $this->http->GetURL("https://www.hertz.com/config.json", $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->user_token_client_creds)) {
            if (
                (
                    $this->http->Response['code'] == 404
                    || $this->http->FindPreg('#<head>\s*<META NAME="robots" CONTENT="noindex,nofollow">\s*<script src="/_Incapsula_Resource\?SWJIYLWA=[^\"]+">\s*</script>\s*<body>#')
                )
                && isset($error)
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "locale"        => "en-us",
            "p1Account"     => "false",
            "brand"         => "hertz",
        ];
        $headers += [
            "Authorization" => "Basic {$response->user_token_client_creds}",
        ];
        $data = [
            "grant_type" => "password",
            "username"   => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.hertz.com/api/login/token", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->access_token)) {
            if ($error) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }
            $message = $response->error_description ?? null;

            if ($message == 'bad credentials.') {
                throw new CheckException('The information you have entered is incorrect. Please note that your account will be locked after 3 failed attempts. If you have forgotten your Member ID or Password, please use the links below for assistance.', ACCOUNT_INVALID_PASSWORD);
            }
            $message = $response->exception ?? null;

            if ($message == 'org.springframework.dao.IncorrectResultSizeDataAccessException') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindPreg('/HttpClientErrorException occurs while connecting/', false, $response->message ?? null)) {
                throw new CheckRetryNeededException();
            }

            return false;
        }
        $this->graphql_token = $response->access_token;
        $this->logger->info('Parse graphql', ['Header' => 2]);

        // Membership number
        $this->SetProperty('MembershipNumber', $response->memberId ?? null);

        $headers = [
            "Accept"          => "*/*",
            "systemId"        => "digital",
            "locale"          => "en-us",
            "p1Account"       => "false",
            "brand"           => "hertz",
            "content-type"    => "application/json",
            "Authorization"   => "Bearer {$response->access_token}",
            "Referer"         => "Referer",
            "Accept-Encoding" => "gzip, deflate, br",
        ];
        $this->http->RetryCount = 0;
        $data = '{"operationName":null,"variables":{"options":{"isLogin":true},"localeDummy":"en-us"},"query":"query ($options: UpcomingReservationOptions) {\n  account {\n    dialect\n    firstName\n    lastName\n    cdpNumbers {\n      cdpName\n      cdpPreferred\n      cdpNumber\n      isValid\n      __typename\n    }\n    frequentTravelerNumbers {\n      frequentTravelerNumber\n      frequentTravelerName\n      __typename\n    }\n    creditCards {\n      id\n      prefSetId\n      prefId\n      paymentAccountId\n      creditCardType\n      creditCardNumber\n      creditCardExpirationDate\n      creditCardMask\n      primaryCardIndicator\n      seqNum\n      isHertzCard\n      hertzChargeCardStatus\n      __typename\n    }\n    driversLicences {\n      driversLicenseCountry\n      driversLicenseNumber\n      driversLicenseStateOfIssue\n      driversLicenseDateOfIssue\n      driversLicenseExpirationDate\n      __typename\n    }\n    rentalPreferences {\n      global {\n        fuelPurchaseOption\n        __typename\n      }\n      EMEA {\n        extras {\n          hertzNeverLost\n          siriusXmSatelliteRadio\n          __typename\n        }\n        countryRentalPreferences {\n          EMEA {\n            insuranceAndProtection {\n              collisionDamageWaiver\n              theftProtection\n              superCover\n              personalAccidentInsurance\n              __typename\n            }\n            vehicleClass\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      USCA {\n        extras {\n          hertzNeverLost\n          siriusXmSatelliteRadio\n          __typename\n        }\n        countryRentalPreferences {\n          US {\n            insuranceAndProtection {\n              lossDamageWaiver\n              liabilitySupplementInsurance\n              liabilitySupplementInsuranceCaliforniaOnly\n              personalAccidentInsurance\n              __typename\n            }\n            vehicleClass\n            __typename\n          }\n          CA {\n            insuranceAndProtection {\n              lossDamageWaiver\n              personalAccidentInsurance\n              __typename\n            }\n            vehicleClass\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      AUNZ {\n        extras {\n          hertzNeverLost\n          siriusXmSatelliteRadio\n          __typename\n        }\n        countryRentalPreferences {\n          AU {\n            insuranceAndProtection {\n              maximumCover\n              accidentAccessReduction\n              declineAllOptionalService\n              __typename\n            }\n            vehicleClass\n            __typename\n          }\n          NZ {\n            insuranceAndProtection {\n              accidentAccessReduction\n              personalAccidentInsurance\n              personalEffectCoverage\n              __typename\n            }\n            vehicleClass\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    communicationPreferences {\n      dataSharingElection\n      prefIdeReturn\n      prefSetIdeReturn\n      ereturnRequired\n      prefSetIdPrivacy\n      prefIdPrivacy\n      emailMarketingElection\n      prefSetIdEmailAlerts\n      prefIdEmailAlerts\n      emailNotificationRequired\n      phoneNumberBusinessId\n      phoneNumberPersonalId\n      phoneNumberMobileId\n      faxId\n      faxNumber\n      phoneNumberBusiness\n      phoneNumberCountryCode\n      phoneNumberMobile\n      phoneNumberPersonal\n      prefSetIdMail\n      prefIdMail\n      postMailMarketingElection\n      prefSetIdSmsAlerts\n      prefIdSmsAlerts\n      smsNotificationRequired\n      preferedContact\n      prefSetIdDoc\n      prefIdDoc\n      __typename\n    }\n    memberStatusCd\n    memberTierCd\n    addresses {\n      id\n      addressType\n      preferredAddress\n      businessName\n      address1\n      address2\n      countryCode\n      city\n      stateProvinceCode\n      postalCode\n      postalCodePlus5\n      __typename\n    }\n    emailAddresses {\n      emailType\n      id\n      emailAddress\n      preferedEmail\n      __typename\n    }\n    electronicSignatures {\n      geoLocatorCd\n      __typename\n    }\n    __typename\n  }\n  loyaltyRewards {\n    rentalsYTD\n    pointsBalance\n    __typename\n  }\n  upcomingReservations(options: $options) {\n    nextTrip {\n      reservationStatus\n      confirmationNumber\n      pickUpLocationOagCode\n      pickUpDateTime\n      dropOffLocationOagCode\n      pickUpLocationName\n      dropOffLocationName\n      sipp\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->PostURL("https://www.hertz.com/graphql", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        // provider bug fix - sometimes it's helps
        $error = $response->errors[0]->message ?? null;

        if (
            $error == 'Infact API Failure: 404'
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
        ) {
            $this->logger->notice("provider bug fix");
            sleep(5);
            $this->http->PostURL("https://www.hertz.com/graphql", $data, $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
        }

        $this->SetBalance($response->data->loyaltyRewards->pointsBalance ?? null);
        $firstName = $response->data->account->firstName ?? '';
        $lastName = $response->data->account->lastName ?? '';
        $this->lastName = $lastName;
        $this->SetProperty("Name", trim(beautifulName("{$firstName} {$lastName}")));
        $status = $response->data->account->memberTierCd ?? null;

        switch ($status) {
            case 'RG':
                $this->SetProperty('Status', "Gold");

                break;

            case 'FG':
                $this->SetProperty('Status', "Five Star");

                break;

            case 'PL':
                $this->SetProperty('Status', "Platinum");

                break;

            case 'PC':
                $this->SetProperty('Status', "President's Circle");

                break;

            case 'N1':
                $this->SetProperty('Status', "Member");

                break;

            default:
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && isset($response->data->account) && $response->data->account !== null) {
                    $this->sendNotification("unknown status: {$status}");
                }
        }

        $this->graphql = true;
        // fix for itinerary report
        if ((isset($response->data->upcomingReservations) && $response->data->upcomingReservations->nextTrip == null)
            || (property_exists($response->data,
                    'upcomingReservations') && $this->http->FindPreg("/,\"upcomingReservations\":null}}$/"))
        ) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return true;
    }

    private function graphqlIts()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"          => "*/*",
            "systemId"        => "digital",
            "locale"          => "en-us",
            "p1Account"       => "false",
            "brand"           => "hertz",
            "content-type"    => "application/json",
            "Authorization"   => "Bearer {$this->graphql_token}",
            "Referer"         => "https://www.hertz.com/reservations/lookup",
            "Accept-Encoding" => "gzip, deflate, br",
        ];
        $this->http->RetryCount = 0;
        $payload = '{"operationName":null,"variables":{"options":{"lastName":"' . $this->lastName . '"},"localeDummy":"en-us"},"query":"query ($options: UpcomingReservationOptions) {\n  upcomingReservations(options: $options) {\n    upcomingReservations {\n      reservationStatus\n      confirmationNumber\n      details {\n        ...CurrentReservationContent\n        personalInfo {\n          firstName\n          lastName\n          email\n          brand\n          __typename\n        }\n        confirmationNumber\n        address {\n          address1\n          address2\n          country\n          city\n          state\n          zipCode\n          __typename\n        }\n        totalsAndTaxes {\n          rateType\n          totalAmount\n          totalCurrency\n          payOnBooking {\n            totalAmount\n            totalCurrency\n            totalFees\n            totalExtras\n            totalTaxes\n            rateDetails {\n              rateUnitCharge\n              rateUnitQuantity\n              rateUnitName\n              rateChargeAmount\n              rateCurrency\n              __typename\n            }\n            includedNotIncluded {\n              name\n              value\n              __typename\n            }\n            fees {\n              description\n              name\n              amount\n              currency\n              applicability\n              requiredInd\n              __typename\n            }\n            taxes {\n              description\n              name\n              amount\n              currency\n              applicability\n              requiredInd\n              __typename\n            }\n            extras {\n              categoryName\n              quantity\n              name\n              amount\n              currency\n              extraSqCode\n              __typename\n            }\n            __typename\n          }\n          payAtCounter {\n            totalAmount\n            totalCurrency\n            totalFees\n            totalExtras\n            totalTaxes\n            rateDetails {\n              rateUnitCharge\n              rateUnitQuantity\n              rateUnitName\n              rateChargeAmount\n              rateCurrency\n              __typename\n            }\n            includedNotIncluded {\n              name\n              value\n              __typename\n            }\n            fees {\n              description\n              name\n              amount\n              currency\n              applicability\n              requiredInd\n              __typename\n            }\n            taxes {\n              description\n              name\n              amount\n              currency\n              applicability\n              requiredInd\n              __typename\n            }\n            extras {\n              categoryName\n              quantity\n              name\n              amount\n              currency\n              extraSqCode\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        pickup {\n          pickupLocationId\n          pickupCountry\n          pickupCategoryCode\n          pickUpLocationOag6Code\n          pickUpLocationOag3Code\n          pickUpLocationName\n          pickupDateTime\n          pickupLocationAddressLine1\n          pickupLocationAddressLine2\n          pickupLocationCity\n          pickupLocationCountryCode\n          pickupLocationFax\n          pickupLocationHours\n          pickupLocationPhone\n          pickupLocationState\n          pickupLocationStateCode\n          pickupLocationType\n          pickupLocationZip\n          pickupLocationLat\n          pickupLocationLong\n          openHours_monday\n          openHours_tuesday\n          openHours_wednesday\n          openHours_thursday\n          openHours_friday\n          openHours_saturday\n          openHours_sunday\n          hoursOfOperation\n          counterBypassEnabled\n          __typename\n        }\n        dropoff {\n          dropoffLocationId\n          dropoffCountry\n          dropoffCategoryCode\n          dropoffLocationOag6Code\n          dropoffLocationOag3Code\n          dropoffLocationName\n          dropoffDateTime\n          dropoffLocationAddressLine1\n          dropoffLocationAddressLine2\n          dropoffLocationCity\n          dropoffLocationCountryCode\n          dropoffLocationFax\n          dropoffLocationHours\n          dropoffLocationPhone\n          dropoffLocationState\n          dropoffLocationStateCode\n          dropoffLocationType\n          dropoffLocationZip\n          dropoffLocationLat\n          dropoffLocationLong\n          openHours_monday\n          openHours_tuesday\n          openHours_wednesday\n          openHours_thursday\n          openHours_friday\n          openHours_saturday\n          openHours_sunday\n          hoursOfOperation\n          __typename\n        }\n        vehicleDetails {\n          rates {\n            payNow {\n              rateQuoteId\n              includedMileageText\n              creditCardRequired\n              paymentRateAmount\n              paymentCurrency\n              paymentRatePeriod\n              approximateTotal\n              rateCode\n              rateQualifier\n              paymentRules {\n                ruleType\n                dateTime\n                startOffset\n                endOffset\n                absoluteDeadline\n                rank\n                cancelDescription\n                amount\n                currencyCode\n                decimalPlaces\n                __typename\n              }\n              payOnBooking {\n                rateQuoteId\n                rateType\n                prepaidType\n                creditCardRequired\n                __typename\n              }\n              payAtCounter {\n                rateQuoteId\n                rateType\n                prepaidType\n                creditCardRequired\n                __typename\n              }\n              priceDelta\n              __typename\n            }\n            payLater {\n              rateQuoteId\n              includedMileageText\n              creditCardRequired\n              paymentRateAmount\n              paymentCurrency\n              paymentRatePeriod\n              approximateTotal\n              rateCode\n              rateQualifier\n              paymentRules {\n                ruleType\n                dateTime\n                startOffset\n                endOffset\n                absoluteDeadline\n                rank\n                cancelDescription\n                amount\n                currencyCode\n                decimalPlaces\n                __typename\n              }\n              payOnBooking {\n                rateQuoteId\n                rateType\n                prepaidType\n                creditCardRequired\n                __typename\n              }\n              payAtCounter {\n                rateQuoteId\n                rateType\n                prepaidType\n                creditCardRequired\n                __typename\n              }\n              priceDelta\n              __typename\n            }\n            __typename\n          }\n          sippCode\n          vehicleCategoryName\n          vehicleCategory\n          vehicleType\n          canBookExactVehicle\n          vehicleDescription\n          vehicleCollection\n          isSpecialCollection\n          numberOfPassengers\n          numberOfSuitcases\n          numberOfSmallSuitcases\n          numberOfLargeSuitcases\n          fuelEconomyValue\n          fuelEconomyUnit\n          numberOfDoors\n          co2EmissionRange\n          transmissionAndDriveType\n          attributes\n          image {\n            title\n            sources {\n              size\n              renditions {\n                density\n                src\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          transmissionType\n          driveType\n          dreamCar\n          rateRestricted\n          vehicleFilterType\n          callToBook\n          ldwRequired\n          includedInRate\n          isPremium\n          clickForQuote\n          __typename\n        }\n        cdpDisplayName\n        discountCodePromoCoupon\n        discountCodeConventionNumber\n        discountCodeVoucherNumber\n        discountCodeCDP {\n          codeText\n          quoteCompanyRates\n          cdpCodeType\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment CurrentReservationContent on ReservationDetail {\n  currentReservation {\n    confirmationNumber\n    lastName\n    brand\n    pickUpDate\n    pickUpDateTime\n    pickUpTime\n    dropOffDate\n    dropOffDateTime\n    dropOffTime\n    discountCodes {\n      cdp\n      cdpName\n      conventionNumber\n      promoCode\n      rateCode\n      travelType\n      voucherNumber\n      negotiatedRateQuestion\n      __typename\n    }\n    pickupLocation {\n      oag6code\n      name\n      countryCode\n      pickUpLocationObject {\n        address2\n        category_code\n        city\n        country_code\n        country_name\n        hoursOfOperation\n        id\n        lat\n        lon\n        name\n        oag3\n        oag6_code\n        openHours_friday\n        openHours_monday\n        openHours_saturday\n        openHours_sunday\n        openHours_thursday\n        openHours_tuesday\n        openHours_wednesday\n        __typename\n      }\n      __typename\n    }\n    dropoffLocation {\n      oag6code\n      name\n      countryCode\n      dropOffLocationObject {\n        address2\n        category_code\n        city\n        country_code\n        country_name\n        hoursOfOperation\n        id\n        lat\n        lon\n        name\n        oag3\n        oag6_code\n        openHours_friday\n        openHours_monday\n        openHours_saturday\n        openHours_sunday\n        openHours_thursday\n        openHours_tuesday\n        openHours_wednesday\n        __typename\n      }\n      __typename\n    }\n    selectedVehicle {\n      vehicleInfo {\n        rates {\n          payNow {\n            rateQuoteId\n            includedMileageText\n            creditCardRequired\n            paymentRateAmount\n            paymentCurrency\n            paymentRatePeriod\n            approximateTotal\n            rateCode\n            rateQualifier\n            paymentRules {\n              ruleType\n              dateTime\n              startOffset\n              endOffset\n              absoluteDeadline\n              rank\n              cancelDescription\n              amount\n              currencyCode\n              decimalPlaces\n              __typename\n            }\n            payOnBooking {\n              rateQuoteId\n              rateType\n              prepaidType\n              creditCardRequired\n              __typename\n            }\n            payAtCounter {\n              rateQuoteId\n              rateType\n              prepaidType\n              creditCardRequired\n              __typename\n            }\n            priceDelta\n            __typename\n          }\n          payLater {\n            rateQuoteId\n            includedMileageText\n            creditCardRequired\n            paymentRateAmount\n            paymentCurrency\n            paymentRatePeriod\n            approximateTotal\n            rateCode\n            rateQualifier\n            paymentRules {\n              ruleType\n              dateTime\n              startOffset\n              endOffset\n              absoluteDeadline\n              rank\n              cancelDescription\n              amount\n              currencyCode\n              decimalPlaces\n              __typename\n            }\n            payOnBooking {\n              rateQuoteId\n              rateType\n              prepaidType\n              creditCardRequired\n              __typename\n            }\n            payAtCounter {\n              rateQuoteId\n              rateType\n              prepaidType\n              creditCardRequired\n              __typename\n            }\n            priceDelta\n            __typename\n          }\n          __typename\n        }\n        sippCode\n        vehicleCategoryName\n        vehicleCategory\n        vehicleType\n        canBookExactVehicle\n        vehicleDescription\n        vehicleCollection\n        isSpecialCollection\n        numberOfPassengers\n        numberOfSuitcases\n        numberOfSmallSuitcases\n        numberOfLargeSuitcases\n        fuelEconomyValue\n        fuelEconomyUnit\n        numberOfDoors\n        co2EmissionRange\n        transmissionAndDriveType\n        attributes\n        image {\n          title\n          sources {\n            size\n            renditions {\n              density\n              src\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        transmissionType\n        driveType\n        dreamCar\n        rateRestricted\n        vehicleFilterType\n        callToBook\n        ldwRequired\n        includedInRate\n        isPremium\n        clickForQuote\n        __typename\n      }\n      __typename\n    }\n    currentTaxesAndTotals {\n      rateQualifier\n      rateType\n      totalAmount\n      totalCurrency\n      payOnBooking {\n        totalAmount\n        totalCurrency\n        totalFees\n        totalExtras\n        totalTaxes\n        rateDetails {\n          rateUnitCharge\n          rateUnitQuantity\n          rateUnitName\n          rateChargeAmount\n          rateCurrency\n          __typename\n        }\n        includedNotIncluded {\n          name\n          value\n          __typename\n        }\n        fees {\n          description\n          name\n          amount\n          currency\n          applicability\n          requiredInd\n          __typename\n        }\n        taxes {\n          description\n          name\n          amount\n          currency\n          applicability\n          requiredInd\n          __typename\n        }\n        extras {\n          categoryName\n          quantity\n          name\n          amount\n          currency\n          extraSqCode\n          __typename\n        }\n        __typename\n      }\n      payAtCounter {\n        totalAmount\n        totalCurrency\n        totalFees\n        totalExtras\n        totalTaxes\n        rateDetails {\n          rateUnitCharge\n          rateUnitQuantity\n          rateUnitName\n          rateChargeAmount\n          rateCurrency\n          __typename\n        }\n        includedNotIncluded {\n          name\n          value\n          __typename\n        }\n        fees {\n          description\n          name\n          code\n          amount\n          currency\n          applicability\n          requiredInd\n          __typename\n        }\n        taxes {\n          description\n          name\n          amount\n          currency\n          applicability\n          requiredInd\n          __typename\n        }\n        extras {\n          categoryName\n          quantity\n          name\n          amount\n          currency\n          extraSqCode\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n"}';
        $this->http->PostURL("https://www.hertz.com/graphql", $payload, $headers);
        $response = $this->http->JsonLog();
        // provider bug fix - sometimes it's helps
        $error = $response->errors[0]->message ?? null;

        if (
            $error === 'Infact API Failure: 404'
            || $error === "Cannot read property 'data' of undefined"
            || strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
        ) {
            $this->logger->notice("provider bug fix");
            sleep(5);
            $this->http->PostURL("https://www.hertz.com/graphql", $payload, $headers);
            $response = $this->http->JsonLog();

            if (!isset($response->errors)) {
                $this->sendNotification("graphql retry helped // ZM");
            }
        }
        $this->http->RetryCount = 2;

        if (
            isset($response->data)
            && isset($response->data->upcomingReservations)
            && isset($response->data->upcomingReservations->upcomingReservations)
        ) {
            foreach ($response->data->upcomingReservations->upcomingReservations as $i => $reservation) {
                if (!isset($reservation->details)) {
                    $this->sendNotification("empty details // ZM");

                    if (isset($response->errors, $response->errors[$i])) {
                        $this->logger->error($response->errors[$i]->message);
                    }

                    continue;
                }
                $r = $this->itinerariesMaster->add()->rental();

                if ($reservation->reservationStatus !== 'S') {
                    $this->sendNotification("check status // ZM");
                }

                $this->logger->info("[$this->currentItin] Parse Itinerary #{$reservation->confirmationNumber}", ['Header' => 3]);
                $this->currentItin++;

                $r->general()
                    ->confirmation($reservation->confirmationNumber, 'Reservation Number')
                    ->traveller(beautifulName($reservation->details->personalInfo->firstName . ' ' . $reservation->details->personalInfo->lastName),
                        true);

                $totalsAndTaxes = $reservation->details->totalsAndTaxes;
                $r->price()
                    ->total(PriceHelper::cost($totalsAndTaxes->totalAmount))
                    ->tax(PriceHelper::cost($totalsAndTaxes->payAtCounter->totalTaxes), false, true)
                    ->currency($totalsAndTaxes->totalCurrency)
                    ->cost(PriceHelper::cost($totalsAndTaxes->payAtCounter->rateDetails[0]->rateChargeAmount ?? null), false, true);
                $fees = $totalsAndTaxes->payAtCounter->fees;

                foreach ($fees as $fee) {
                    $r->price()->fee($fee->name, PriceHelper::cost($fee->amount));
                }

                $pickup = $reservation->details->pickup;
                $r->pickup()
                    ->location($pickup->pickUpLocationName)
                    ->date2($pickup->pickupDateTime)
                    ->openingHours($pickup->pickupLocationHours ?? null, false, true)
                    ->phone(mb_strlen($pickup->pickupLocationPhone) > 1 ? $pickup->pickupLocationPhone : null, false, true)
                    ->fax($pickup->pickupLocationFax ?? null, false, true);

                $dAddress = $r->pickup()->detailed();
                $dAddress
                    ->address($pickup->pickupLocationAddressLine1 . ' ' . ($pickup->pickupLocationAddressLine2 ?? ''))
                    ->city($pickup->pickupLocationCity)
                    ->country($pickup->pickupCountry);

                if (isset($pickup->pickupLocationZip)) {
                    $dAddress->zip($pickup->pickupLocationZip);
                }

                if (isset($pickup->pickupLocationState)) {
                    $dAddress->state($pickup->pickupLocationState);
                }
                $dropoff = $reservation->details->dropoff;
                $r->dropoff()
                    ->location($dropoff->dropoffLocationName)
                    ->date2($dropoff->dropoffDateTime)
                    ->openingHours($dropoff->dropoffLocationHours ?? null, false, true)
                    ->phone($dropoff->dropoffLocationPhone)
                    ->fax($dropoff->dropoffLocationFax ?? null, false, true);

                $dAddress = $r->dropoff()->detailed();
                $dAddress
                    ->address($dropoff->dropoffLocationAddressLine1 . ' ' . ($dropoff->dropoffLocationAddressLine2 ?? ''))
                    ->city($dropoff->dropoffLocationCity)
                    ->country($dropoff->dropoffCountry);

                if (isset($dropoff->dropoffCountry)) {
                    $dAddress->zip($dropoff->dropoffCountry);
                }

                if (isset($dropoff->dropoffLocationState)) {
                    $dAddress->state($dropoff->dropoffLocationState);
                }

                $urlImage = $reservation->details->vehicleDetails->image->sources[0]->renditions[0]->src ?? null;

                if (isset($urlImage)) {
                    $this->http->NormalizeURL($urlImage);
                    $r->car()->image($urlImage);
                }

                $r->car()
                    ->model($reservation->details->vehicleDetails->vehicleDescription)
                    ->type($reservation->details->vehicleDetails->vehicleCategoryName . ' (' . $reservation->details->vehicleDetails->sippCode . ')')
                    ->image($urlImage);

                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
            }
        } else {
            $this->sendNotification("graphql its, other response // ZM");
        }
    }

    private function checkRetrieveErrors(): ?string
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        $message = $response->message->messages[0] ?? $response->message->systemErrors[0] ?? null;
        $this->logger->error($message);

        if ($error = $this->http->FindPreg('/CANNOT COMPLETE PAYMENT - CALL HERTZ /')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/"(Unable to process request\.\s+Please try your submission again\.) \[DE99\]"/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/We are sorry but this reservation cannot be Viewed, Modified or Cancelled on-line\./')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/FAX NOT AVAILABLE THIS RATE PLAN/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Please log in to review this reservation/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Please modify your request to include the Negotiate Rate or remove the discount\. \[DE147\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Rate Code - We are not able to find any rate that meets the criteria you\'ve requested\./')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/We\'re unable to retrieve your reservation at this time\./')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Arrival Information - This location is not able to confirm availability for customers who are arriving via the airline listed\./')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Promotional Coupon - The coupon requires a minimum rental length in order to confirm use\.  Please modify your return date or remove the promotion\. \[DE170\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Promotional Coupon - The rate requested cannot be combined with a coupon\.  Please modify your rate request or remove the promotion. \[DE145\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Promotional Coupon - The coupon cannot be used at this location.  Please modify your pick-up location or remove the promotion. \[DE167\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/INVALID NAME \[DE61\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Return Date or Time - This location is closed at the time indicated. Please adjust your return or select an alternate location\. \[DE357\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg("/We\'re unable to process your request at this time.\s*Please/")) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/CREDIT CARD EXPIRATION DATE NOT VALID \[DE193\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Return Location - You cannot return your vehicle to the location specified\.  Please choose an alternate return location\. \[DE32\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/REFERENCE NUMBER REQUIRED FOR BILLING REF \[DE358\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/This reservation has already been cancelled. \[DE56\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/RESERVATION NOT ON FILE \[DE27\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Discount \(CDP\) Number - the requested rate requires a corresponding discount \(CDP\) number\. Please enter the proper CDP or modify the rate request\. \[DE106\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Discount \(CDP\) Number - The promotional coupon requires a corresponding discount \(CDP\) number\.  Please enter the proper CDP or remove the coupon\. \[DE20\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/Arrival Information - This location is only available to customers who are arriving via airline or train\. Please enter a valid arriving airline and flight number, train or choose a different location\. \[DE245\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/We\'re unable to process your request at this time\.  Please \\\\"Contact Us\\\\"\. \[DE119\]/')) {
            return $error;
        }

        if ($error = $this->http->FindPreg('/PROMOTION COUPON BOOKING SOURCE NOT MET \[DE815\]/')) {
            return $error;
        }

        return null;
    }

    private function ParseItineraryJS(): ?array
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $data = $this->http->JsonLog(null, 2, true);

        if (
            is_array($data) && isset($data['resultInfo'])
            && isset($data['resultInfo']['result'])
            && $data['resultInfo']['result'] = 'fail'
            && isset($data['message']['systemErrors'][0])
            && strpos($data['message']['systemErrors'][0], 'We are not able to find a rate') !== false
        ) {
            $this->logger->debug('skip reservation: not enough info');
            $this->logger->error($data['message']['systemErrors'][0]);

            return null;
        }

        if (is_array($data) && isset($data['data'])) {
            $data = $data['data'];
            $result['Kind'] = "L";
            $result['Number'] = ArrayVal($data['confirmationSummaryInfo'], 'confirmationNumber');
            $this->logger->info("[$this->currentItin] Parse Itinerary #{$result['Number']}", ['Header' => 3]);
            $this->currentItin++;

            if ($date = ArrayVal($data['confirmationSummaryInfo'], 'pickupDate')) {
                $result['PickupDatetime'] = strtotime($date);
            }

            if ($date = ArrayVal($data['confirmationSummaryInfo'], 'dropoffDate')) {
                $result['DropoffDatetime'] = strtotime($date);
            }

            $result['PickupLocation'] = ArrayVal($data['itinerary']['pickupLocation'], 'name');

            if (
                !empty($result['PickupLocation'])
                && (
                    strpos($result['PickupLocation'], 'Airport') !== false
                    || strpos($result['PickupLocation'], 'International') !== false
                )
            ) {
                if ($code = preg_replace('/^([A-Z]{3}).+/', '$1', ArrayVal($data, 'pickUpLocationCode'))) {
                    $result['PickupLocation'] .= " ({$code})";
                }
            }

            $pickupAddress =
                ArrayVal($data['itinerary']['pickupLocation'], 'addressLine1') . ', ' .
                ArrayVal($data['itinerary']['pickupLocation'], 'city') . ', ' .
                ArrayVal($data['itinerary']['pickupLocation'], 'stateCode') . ' ' .
                ArrayVal($data['itinerary']['pickupLocation'], 'countryCode') . ' ' .
                ArrayVal($data['itinerary']['pickupLocation'], 'zip');
            $pickupAddress = trim($pickupAddress, ', ');

            if (!empty($result['PickupLocation']) && !empty($pickupAddress)) {
                $result['PickupLocation'] .= ': ' . $pickupAddress;
            }

            // if Pickup == Return Location
            if (isset($data['itinerary']['dropoffLocation'])) {
                $result['DropoffLocation'] = ArrayVal($data['itinerary']['dropoffLocation'], 'name');

                $dropoffAddress =
                    ArrayVal($data['itinerary']['dropoffLocation'], 'addressLine1') . ', ' .
                    ArrayVal($data['itinerary']['dropoffLocation'], 'city') . ', ' .
                    ArrayVal($data['itinerary']['dropoffLocation'], 'stateCode') . ' ' .
                    ArrayVal($data['itinerary']['dropoffLocation'], 'countryCode') . ' ' .
                    ArrayVal($data['itinerary']['dropoffLocation'], 'zip');
                $dropoffAddress = trim($dropoffAddress, ', ');

                if (!empty($result['DropoffLocation']) && !empty($dropoffAddress)) {
                    $result['DropoffLocation'] .= ': ' . $dropoffAddress;
                }
            }

            if (empty($result['DropoffLocation'])) {
                $result['DropoffLocation'] = $result['PickupLocation'];
            }

            $pickupPhone = $data['itinerary']['pickupLocation']['phone'] ?? '';
            $result['PickupPhone'] = $this->http->FindPreg('/(.+?)\*$/', false, $pickupPhone) ?: $pickupPhone ?: null;
            $pickupFax = $data['itinerary']['pickupLocation']['fax'] ?? null;
            $result['PickupFax'] = $pickupFax === '.' ? null : $pickupFax;
            $result['PickupHours'] = str_replace("<br>", "; ", $data['itinerary']['pickupLocation']['hours'] ?? '') ?: null;

            $dropoffPhone = $data['itinerary']['dropoffLocation']['phone'] ?? '';
            $result['DropoffPhone'] = $this->http->FindPreg('/(.+?)\*$/', false, $dropoffPhone) ?: $dropoffPhone ?: null;
            $dropoffFax = $data['itinerary']['dropoffLocation']['fax'] ?? null;
            $result['DropoffFax'] = $dropoffFax === '.' ? null : $dropoffFax;
            $result['DropoffHours'] = str_replace("<br>", "; ", $data['itinerary']['dropoffLocation']['hours'] ?? '') ?: null;

            $result['CarType'] = htmlspecialchars(ArrayVal($data['rateDetails']['vehicle'], 'carTypeDisplay'));

            $result['CarModel'] = ArrayVal($data['rateDetails']['vehicle'], 'name');

            $result['RenterName'] = trim(beautifulName(ArrayVal($data['confirmationSummaryInfo'], 'firstName') . ' ' . ArrayVal($data['confirmationSummaryInfo'], 'lastName')));

            $result['TotalCharge'] = ArrayVal($data['rateDetails']['rateInfo'], 'approxTotal', null);
            $result['Currency'] = ArrayVal($data['rateDetails']['rateInfo'], 'currency', null);
            $result['TotalTaxAmount'] = ArrayVal($data['rateDetails']['rateInfo'], 'tax', null);
            // refs #10899
            $result['PaymentMethod'] = ArrayVal($data['rateDetails']['generalInfo'], 'paymentMethod');
            $this->logger->info("Payment Method -> {$result['PaymentMethod']}");

            if (strtolower($result['PaymentMethod']) == 'pay later') {
                $this->logger->notice('remove total and tax: pay later');
                unset($result['TotalCharge']);
                unset($result['TotalTaxAmount']);
            }// if (strtolower($result['PaymentMethod']) == 'pay later')
        }

        /*// security
        foreach ($result as $key => $value) {
            if ($value && is_string($value)) {
                $result[$key] = Html::cleanXMLValue($value);
            }
        }*/

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function ParsePageHistory($startIndex, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $responseTransactions = json_decode($this->http->Response['body']);

        if (isset($responseTransactions->data->transactions)) {
            $this->logger->debug("Found " . count($responseTransactions->data->transactions) . " items");
//            $this->logger->debug(var_export($responseTransactions->data->transactions, true), ['pre' => true]);
            foreach ($responseTransactions->data->transactions as $transaction) {
                $dateStr = $transaction->date;

                if (isset($responseTransactions->data->locale)) {
                    $locale = $responseTransactions->data->locale;

                    switch ($locale) {
                        case 'pt_BR':
                        case 'es_AR':
                        case 'de_DE':
                        case 'es_MX':
                            $dateStr = $this->ModifyDateFormat($dateStr);

                            break;

                        case 'fr_FR':
                        case 'es_ES':
                        case 'nl_NL':
                        case 'it_IT':
                            $dateStr = $this->dateStringToEnglish($dateStr);

                            break;

                        default:
                            $this->logger->debug("Locale: {$locale}");
                    }// switch ($locale)
                }// if (isset($responseTransactions->data->locale))
                $postDate = strtotime($dateStr);
                // debug
                if (!$postDate) {
                    $this->sendNotification("hertz. Please check history for this account", "awardwallet");
                }

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");
                    $this->endHistory = true;

                    continue;
                }
                $result[$startIndex]['Date'] = $postDate;

                preg_match_all('/\-?[A-Z][a-z]+/', $transaction->type, $type);

                if ($transaction->type == 'CustomerServiceAdjustment') {
                    preg_match_all('/\-?[A-Z][a-z]+/', $transaction->desc, $desc);
                    $description = str_replace('-', '- ', implode(' ', $desc[0]));
                    unset($desc);
                } // if ($transaction->type == 'CustomerServiceAdjustment')
                else {
                    $description = $transaction->desc;
                }

                $result[$startIndex]['Type'] = implode(' ', $type[0]);
                $result[$startIndex]['Description'] = $description;
                $result[$startIndex]['Rental Agreement'] = $transaction->rentalAgreementNumber;

                $hasPoints = true;

                if (isset($transaction->rewardsList)) {
                    foreach ($transaction->rewardsList as $reward) {
                        if (!isset($reward->type)) {
                            $hasPoints = false;

                            break;
                        }

                        switch ($reward->type) {
                            case 'GPRBasePoints':
                            case 'GPRBasePoints-ReprocessActivity':
                                $result[$startIndex]['Points'] = $reward->points;

                                break;

                            case 'GPRBonusPoints':
                            case 'GPRBonusPoints-ReprocessActivity':
                                $result[$startIndex]['Bonus Points'] = $reward->points;

                                break;

                            default:
                                $this->sendNotification("hertz - Hertz Gold Plus Rewards. History: new points type - {$reward->type}");

                                break;
                        }// switch ($reward->type)
                    }// foreach ($transaction->rewardsList as $reward)
                } // if (isset($transaction->rewardsList))
                else {
                    $result[$startIndex]['Points'] = $transaction->points;
                }

                if ($hasPoints === false) {
                    $result[$startIndex]['Points'] = $transaction->points;
                }

                $startIndex++;
            }// foreach ($response->data->transactions as $transaction)
        }// if (isset($response->data->transactions))

        return $result;
    }
}
