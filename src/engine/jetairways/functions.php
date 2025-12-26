<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerJetairways extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    private $clientId;
    private $auth0Client = 'eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTEuMyJ9';
    private $attemptViaProxy = false;
    private $verifychannel;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $result = $this->selenium();

        if (is_string($result)) {
            $this->logger->error("[Error]: {$result}");

            if (
                $result == 'Login ID or Password entered is invalid'
                || strstr($result, 'You have entered wrong password for more than 5 times.')
                || $result == 'Account does not exist. Please enter correct details.'
            ) {
                throw new CheckException($result, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $result == 'Could not locate your account. Please enter your unique Intermiles number to proceed'
                || $result == 'Enter verification code to verify your account and create a new password for your account.'
            ) {
                $this->throwProfileUpdateMessageException();
            }

            if (
                $result == 'Website is Under Maintenance Please Come Back After Some Time.'
                || $result == 'We are facing a technical issue. please try again later or Please write to us on memberservices@intermiles.com with the screen shot of the error you are facing.'
                || $result == 'Access Restricted: This is a child account with limited permissions. Please use a parent or guardian account to manage settings for the child account.'
                || $result == 'Sorry, your account is being investigated. Please write to us on memberservices@intermiles.com'
            ) {
                throw new CheckException($result, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $result;

            return $this->checkErrors();
        } elseif (!$result) {
            $this->checkErrors();
        }

        return true;

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "InterMiles is down for some scheduled maintenance!")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://sso.intermiles.com/login');
        $this->http->RetryCount = 2;

        $loginAction = $this->http->FindPreg('/"loginAction":\s*"(.+?)",/');

        if (!isset($loginAction)) {
            return $this->checkErrors();
        }
        $headers = [
            "Accept"          => "*/*",
            "Content-Type"    => "application/json; charset=utf-8",
            "Origin"          => "https://sso.intermiles.com",
            "Referer"         => null,
        ];
        $data = [
            'input' => $this->AccountFields['Login'],
        ];
        $this->http->PostURL("https://imapp.intermiles.com/api/profile/encdec/v1/encrypt", json_encode($data), $headers);
        $encrypt = $this->http->JsonLog();

        $headers = [
            "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
            "Content-Type"    => "application/x-www-form-urlencoded",
            "Origin"          => null,
            "Referer"         => null,
        ];

        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $pass = $jsExecutor->executeString('
        var t = CryptoJS.HmacSHA256("' . $this->AccountFields['Pass'] . '", "bff29f3a-90d4-42d5-94f0-448e699991c1");
          sendResponseToPhp(CryptoJS.enc.Base64.stringify(t));', 5, ['https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/crypto-js.min.js']);
        $this->logger->debug("Pass: {$pass}");

        $data = [
            'username'   => $this->AccountFields['Login'],
            'password'   => $pass,
            'rememberMe' => 'on',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL($loginAction, json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Oops...the site is under maintenance and will be up after some time. Kindly check back later
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Oops...the site is under maintenance and will be up after some time. Kindly check back later')]", null, true, "/^“(.+)”$/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Something new is in the works. We’ll be back soon!
        if ($message = $this->http->FindSingleNode("//span[strong[contains(text(), 'Something new is in the works.')]]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Invalid URL
        if (
            ($this->http->FindSingleNode("//title[contains(text(), 'Invalid URL')]") && $this->http->Response['code'] == 400)
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
            || $this->http->FindSingleNode('//h2[contains(text(), "500 - Internal server error.")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway") or contains(text(), "504 Gateway Time-out")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);
        // Login ID or Password entered is invalid
        if (
            $this->http->FindPreg('/,\s*Message=\s*Invalid Username or Password"/')
            || $this->http->FindPreg('/,\s*Message=\s*Invalid Membership Card Number"/')
            || $this->http->FindPreg('/,\s*Message= Invalid Email Address specified, Please verify/')
            || $this->http->FindPreg('/"error":"access_denied","error_description":"GetCustomerProfile error: ErrorNumber=7604, Message= Invalid USERNAME Specified"/')
        ) {
            throw new CheckException('Login ID or Password entered is invalid', ACCOUNT_INVALID_PASSWORD);
        }
        // Let's get you logged in. Please enter your JP number to proceed
        if (
            $this->http->FindPreg('/, Message= Mobile\/Email not verified/')
        ) {
            $this->throwProfileUpdateMessageException();
        }
        // Sorry! Your account has been locked due to 5 unsuccessful login attempts. An email has been sent to your registered email ID raXXXah@gXl.com with instructions to unlock your account. In case your registered email id has changed, please contact our service centre.
        if ($this->http->FindPreg('/,\s*Message=\s*Your account is locked. Please contact service desk"/')) {
            throw new CheckException('Sorry! Your account has been locked due to 5 unsuccessful login attempts. An email has been sent to your registered email', ACCOUNT_LOCKOUT);
        }
        // Your account access has been restricted. Please contact our Service Centre
        if (
            $this->http->FindPreg('/"error":"access_denied","error_description":"Your account access has been restricted. Please contact our Service Centre/')
            || $this->http->FindPreg('/"error":"unauthorized","error_description":"Your account access has been restricted\.\.\. Please contact our Service Centre/')
        ) {
            throw new CheckException('Your account access has been restricted. Please contact our Service Centre.', ACCOUNT_LOCKOUT);
        }

        // Sorry, login may be delayed because of an internal system issue. Please try again in sometime.
        if ($this->http->FindPreg('/ErrorNumber=-1000, Message=The input stream is not a valid binary format. The starting contents \(in bytes\) are:/')) {
            throw new CheckException('Sorry, login may be delayed because of an internal system issue. Please try again in sometime.', ACCOUNT_PROVIDER_ERROR);
        }
        // Enter verification code to verify your account and create a new password for your account.
        if (
            $this->http->FindPreg('/"error":"access_denied","error_description":"Password_Reset_Required:[0]+' . $this->AccountFields['Login'] . '"/')
            || (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false && $this->http->FindPreg('/"error":"access_denied","error_description":"Password_Reset_Required:[0]+\d+"\}$/'))
        ) {
            throw new CheckException("{$this->AccountFields['DisplayName']} website is asking you to create a new password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        // WARNING - this is wrong error in response
        if ($this->http->Response['code'] == 403 && $this->http->FindPreg('/^\{"error":"access_denied","error_description":"Invalid user credentials."\}/')) {
            $this->logger->notice("Wrong error message");

            throw new CheckRetryNeededException(2, 15);
        }
        // Sorry, login may be delayed because of an internal system issue. Please try again in sometime.
        if ($this->http->Response['code'] == 403 && $this->http->FindPreg('/^\{"error":"access_denied","error_description":"Wrong email or password."\}/')) {
            throw new CheckRetryNeededException(2, 10, "Sorry, login may be delayed because of an internal system issue. Please try again in sometime.");
        }

        if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            $this->DebugInfo = 403;

            throw new CheckRetryNeededException(2);
        }

        return false;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        // selenium issue
        if ($this->http->Response['body'] == '{"error":"not_authenticated","description":"The user does not have an active session or is not authenticated"}') {
            throw new CheckRetryNeededException(2, 0);
        }
        // The user does not have an active session or is not authenticated
        if ($this->http->Response['code'] == 500 && in_array($this->AccountFields['Login'], [
            '153312924',
            // msg: "Something went wrong! Please try again after some time."
            '150100414',
        ])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $headers = [
            "Accept"          => "*/*",
            "Content-Type"    => "application/json; charset=utf-8",
        ];
        $this->http->GetURL('https://www.intermiles.com/api/auth/me', $headers);
        $response = $this->http->JsonLog(null);
        //Total JPMiles
        $this->SetBalance($response->PointsBalance);
        //JP no
        $this->SetProperty('AccountNumber', preg_replace('/^00/', '', $response->JPNumber));
        // Name
        $this->SetProperty('Name', beautifulName($response->Name));
        // Current Tier
        $this->SetProperty('Status', beautifulName($response->Tier));

        $headers += [
            'Authorization' => "Bearer {$this->State['accessToken']}",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://imapp.intermiles.com/api/profile/user/v2/profile-summary", $headers);
        $this->http->RetryCount = 2;
        $profileSymmary = $this->http->JsonLog();
        // Tier Validity
        $tierValidTillDate = $profileSymmary->data->tierValidityDate ?? null;

        if ($tierValidTillDate = strtotime($tierValidTillDate)) {
            // 28 Feb 2023
            $this->SetProperty('TierValidTill', date('d M Y', $tierValidTillDate));
        }

        // Total JPMiles in MyFamily+
        $this->SetProperty('MilesInFamilyAccount', $profileSymmary->data->imMiles ?? null);

        // Expiration Date     // refs #17815
        $expBalance = $profileSymmary->data->nextExpiryMiles ?? null;
        $this->SetProperty('ExpiringBalance', $expBalance);
        $exp = $profileSymmary->data->milesExpiryDate ?? null;

        if ($expBalance && $expBalance > 0 && $exp) {
            $exp = $this->formatDate($exp);
            $this->logger->debug("Expiration Date: {$exp}");
            $exp = strtotime($exp, false);

            if ($exp) {
                $this->SetExpirationDate($exp);
            }
        }

        $this->http->RetryCount = 1;
        $this->http->GetURL('https://imapp.intermiles.com/api/profile/transactions/v2/activity-tracker', $headers);
        $this->http->RetryCount = 2;
        $tracker = $this->http->JsonLog();

        // Total InterMiles Redeemed
        $this->SetProperty('Redeemed', $tracker->data->REDEEMED->totalmiles ?? null);

        $categoryDetails = $tracker->data->EARNED->categories ?? [];

        foreach ($categoryDetails as $categoryDetail) {
            $miles = array_sum(array_column($categoryDetail->userActivities, 'miles'));

            switch ($categoryDetail->partnerCode) {
                case 'FLIGHTS':
                    // Flights Activities
                    $this->SetProperty('FlightsActivities', $miles);

                    break;

                case 'CARDS':
                    // Cards Activities
                    $this->SetProperty('CardsActivities', $miles);

                    break;

                case 'HOTEL':
                    // Hotels Activities
                    $this->SetProperty('HotelsActivities', $miles);

                    break;

                case 'SHOP':
                    // Shop Activities
                    $this->SetProperty('ShopActivities', $miles);

                    break;

                case 'DIN':
                    // Dine Activities
                    $this->SetProperty('DineActivities', $miles);

                    break;
            }
        }

        $this->http->GetURL('https://www.intermiles.com/my-account/activity-details');
        $response = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'), 2);

        // by earning 80,000 InterMiles
        $this->SetProperty('MilesToNextTier', $response->props->pageProps->upgradationData->data->Upgradation[0]->TotalMilesRequired ?? null);
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"             => "PostingDate",
            "Description"      => "Description",
            "Partner"          => "Info",
            "InterMiles"       => "Miles",
            "Bonus InterMiles" => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $page = 0;
        // from=01102020&to=11112022&partnerCode=ALL
        $query = [
            'from'        => date('dmY', strtotime('-3 year')),
            'to'          => date('dmY'),
            'partnerCode' => 'ALL',
        ];
        $headers = [
            'Accept'           => 'application/json, text/plain, */*',
            'Content-Type'     => 'application/json; charset=utf-8',
            'Authorization'    => "Bearer {$this->State['accessToken']}",
        ];
        $this->http->RetryCount = 1;
        $this->increaseTimeLimit();
        $this->http->GetURL('https://imapp.intermiles.com/api/profile/transactions/v2/full-statement?' . http_build_query($query), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $page++;
        $this->logger->debug("[Page: {$page}]");
        $userActivityList = $response->data->userActivityList ?? [];

        foreach ($userActivityList as $item) {
            if ($res = $this->ParsePageHistory($item, $startDate)) {
                $result[] = $res;
            }
        }

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($item, $startDate)
    {
        $nodes = $this->http->XPath->query("//table[contains(@id, 'tbAccountStatement')]//tr[td]");
        $this->logger->debug("Total {$nodes->length} history items were found");

        $dateStr = $this->formatDate($item->transactionDate);
        $postDate = strtotime($dateStr);

        if ((!empty($startDate) && $postDate < $startDate)) {
            $this->logger->notice("break at date {$dateStr} ($postDate)");

            return false;
        }

        if ($item->title == 'TOTAL') {
            $this->logger->notice("skip {$item->title}");

            return false;
        }
        $result = [
            'Date'        => $postDate,
            'Description' => trim($item->title),
            'Partner'     => trim($item->partnerName),
        ];

        $value = 0;

        if ($item->transactionType == 'Earned') {
            $value = $item->miles;
        } elseif ($item->transactionType == 'Redeemed') {
            $value = -$item->miles;
        }

        if ($this->http->FindPreg('/Bonus/i', false, $item->title)) {
            $result['BonusInterMiles'] = $value;
        } else {
            $result['InterMiles'] = $value;
        }

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    private function formatDate($str)
    {
        return preg_replace('/(\d{2})(\d{2})(\d{4})/', '$1-$2-$3', $str);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"          => "*/*",
            "Content-Type"    => "application/json; charset=utf-8",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.intermiles.com/api/auth/token', $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->status) && $response->status == 'ok') {
            $this->State['accessToken'] = $response->accessToken ?? null;

            return true;
        }

        return false;
    }

    private function selenium($retry = false)
    {
        $this->logger->notice(__METHOD__);

        $cache = Cache::getInstance();
        $cacheKey = "sensor_data_choice" . sha1($this->http->getDefaultHeader("User-Agent"));
        $data = $cache->get($cacheKey);

        if (!empty($data) && $this->attempt <= 1 && $retry === false) {
            return $data;
        }

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            switch (rand(0, 0)) {
                case 2:
                    $selenium->useFirefox();

                    break;

                default:
                    $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            }
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
//            $selenium->keepCookies(false);
//            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.intermiles.com/login");
            $login = $selenium->waitForElement(WebDriverBy::id('userName'), 5);

            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@type='button']"), 0);
            $this->savePageToLogs($selenium);

            if ($login) {
                $login->sendKeys($this->AccountFields['Login']);
                $pass = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'input-text-parent-container')]//input[@name='password']"), 2);
                $pass->sendKeys($this->AccountFields['Pass']);
                sleep(1);
                $this->savePageToLogs($selenium);

                $iframeError = $selenium->waitForElement(WebDriverBy::xpath('//iframe[@id = "wiz-iframe-intent"]'), 0);

                if ($iframeError) {
                    $selenium->driver->switchTo()->frame($iframeError);
                    $this->savePageToLogs($selenium);

                    if ($error = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Website is Under Maintenance Please Come Back After Some Time.")]'), 0)) {
                        return $error->getText();
                    }

                    $selenium->driver->switchTo()->defaultContent();
                    $this->savePageToLogs($selenium);
                }

                $btn->click();
            }

            $panel = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(@id,'PanelSearchControl_GlobalNotification_spJPMiles')]"), 10);
            $this->savePageToLogs($selenium);

            if (!$panel) {
                $error = $selenium->waitForElement(WebDriverBy::xpath("
                    //div[contains(@class,'text-container')]//span[contains(text(),'Login ID or Password entered is invalid')]
                    | //p[contains(text(),'Could not locate your account. Please enter your unique Intermiles number to proceed')]
                    | //div[contains(@class, 'error')]//div[contains(@class,'text-container')]//span[@class = 'text body_14 bold']
                    | //span[contains(text(),'Enter verification code to verify your account and create a new password for your account.')]
                "), 0);

                if ($error) {
                    return $error->getText();
                }

                // hard code, broken account (AccountID: 2919299, 3563010)
                if (in_array($this->AccountFields['Login'], [
                    '200919935',
                    '211879172',
                    '132216836',
                    '153949121',
                    '197903370',
                    '139390720',
                ])
                    && $selenium->waitForElement(WebDriverBy::id('userName'), 0)
                ) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (TimeOutException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage(), ['pre' => true]);
            $this->DebugInfo = "Exception";
            // retries
            $retry = true;
        }// catch (TimeOutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup(); // todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
