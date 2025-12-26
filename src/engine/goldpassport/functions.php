<?php

// refs #2247

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerGoldpassport extends TAccountChecker
{
    use ProxyList;
    use PriceTools;
    use SeleniumCheckerHelper;

    private const PROFILE_DATA_URL = "https://www.hyatt.com/profile/api/member/profile";

    private $goldpassportId = null;
    private $selenium = true;
    private $chrome = false;
    private $seleniumURL = null;
    private $firstName = null;
    private $lastName = null;
    private $headers = [
        "Accept"       => "application/json",
        "Content-Type" => "application/json",
        "Referer"      => "https://www.hyatt.com/profile/account-overview",
    ];
    private $profileHeaders = [
        "Accept"                    => "application/json",
        "TE"                        => "Trailers",
        "Referer"                   => "https://www.hyatt.com/profile/account-overview",
    ];
    private $itineraryRequest = [];
    private $currentItin = 0;

    private $mainInfo;
    private $awards;
    private $itineraries;
    private $history = null;

    private $endHistory = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        /*
        $this->http->SetProxy($this->proxyDOP());
        */
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $lastName = trim($this->AccountFields['Login2']);

        if (empty($lastName)) {
            throw new CheckException("To update this World of Hyatt account you need to fill in the ‘Last Name’ field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        $this->http->removeCookies();
        $this->http->RetryCount = 0;

        if (!$this->http->GetURL('https://www.hyatt.com/en-US/home/') && $this->http->Response['code'] != 0) {
            $this->checkErrors();
        } elseif ($this->http->Response['code'] == 0) {
            $this->logger->notice("Try load page one more time");
            // refs #13486
            /*
            $this->setProxyBrightData();
            */

            if (!$this->http->GetURL('https://www.hyatt.com/en-US/home/')) {
                $this->checkErrors();
            }
        }

        $this->http->RetryCount = 2;

        if ($this->selenium) {
            $this->setProxyBrightData();
            $this->selenium();
        } else {
            // get csrf token
            $http2 = clone $this->http;
            $this->http->brotherBrowser($http2);
            $http2->GetURL(self::PROFILE_DATA_URL, $this->profileHeaders);
            $response = $http2->JsonLog(null, 3, true);
            $csrf = ArrayVal($response, 'csrf');

            // retries
            if ($this->http->Response['code'] == 0) {
                throw new CheckRetryNeededException(4, 7);
            }

            if (!$this->http->ParseForm("signin-form") || !$csrf) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('csrf', $csrf);
            $this->http->SetInputValue('userId', $this->AccountFields['Login']);
            $this->http->SetInputValue('lastName', $this->AccountFields['Login2']);
//            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
            $this->http->SetInputValue('returnUrl', 'https://www.hyatt.com/profile/account-overview');
        }

        return true;
    }

    public function Login()
    {
        if (!$this->selenium) {
            sleep(1);

            if (!$this->http->PostForm()) {
                // retries
                if ($this->http->Response['code'] == 0) {
                    throw new CheckRetryNeededException(2, 10);
                }

                return $this->checkErrors();
            }// if (!$this->http->PostForm())
        }// if (!$this->selenium)

        if ($message = $this->http->FindSingleNode("//ul[@class = 'error-0']/li[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // maybe not english text
        if ($this->http->FindSingleNode('//h2[@class="hbDetailsSideBlockName"]/following::span[1]/following::span[1][@class="hbDefinition"]/@class')) {
            return true;
        }

        if ($message = $this->http->FindPreg('/The password you entered does not correspond/ims')) {
            throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->selenium && $this->http->FindSingleNode("//input[@class = 'required error-invalid']/@class")) {
            throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $this->logger->debug("[Selenium URL]: {$this->seleniumURL}");

        $error = $this->http->FindSingleNode("//span[contains(@class, 'error-message')]");
        $this->logger->error("[Error]: {$error}");

        if (!$error && (strstr($this->http->currentUrl(), 'https://world.hyatt.com/content/gp/en/signin-error.html')
                || strstr($this->seleniumURL, 'https://world.hyatt.com/content/gp/en/signin-error.html'))
            && $this->http->ParseForm("signin-form") && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->logger->notice("Try to restore session");

            throw new CheckRetryNeededException(2, 7, self::PROVIDER_ERROR_MSG);
        }
        // provider error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - Zero size object')]")) {
            throw new CheckRetryNeededException(2, 7, $message);
        }

        // Sorry, we're experiencing technical difficulties and were unable to complete your request.
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Sorry, we\'re experiencing technical difficulties and were unable to complete your request.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // You have entered a temporary password, please set a new permanent password.
        if (stristr($this->http->currentUrl(), 'https://goldpassport.hyatt.com/content/gp/en/set-password.html?token=')
            || $this->http->FindSingleNode("//p[contains(text(), 'You have entered a temporary password, please set a new permanent password.')]")) {
            throw new CheckException("You have entered a temporary password, please set a new permanent password.", ACCOUNT_PROVIDER_ERROR);
        }
        // We have locked your account to keep it secure.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We have locked your account to keep it secure.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The information you have entered does not match what we have on file.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // An unexpected error has occurred while attempting to process your request. Please try again later.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'An unexpected error has occurred while attempting to process your request. Please try again later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($error) {
            // An Error Has Occurred
            if ($error == 'signIn.error-SystemError'
                && $this->http->FindPreg("/(?:An E<span class=\&quot;border-bottom\&quot;>rror Has O<\/span>ccurred<\/span>|An<span class=&quot;border-bottom&quot;>meldefe<\/span>hler<\/span>)/")) {
                throw new CheckRetryNeededException(2, 7, self::PROVIDER_ERROR_MSG);
            }

            if ($error == '{{ i18n(\'signIn.error-\'+errorCode) }}'
                && (
                    strstr($this->http->currentUrl(), 'https://world.hyatt.com/content/gp/en/signin-error.html')
                    || strstr($this->http->currentUrl(), 'https://world.hyatt.com/content/gp/de/signin-error.html')
                    || strstr($this->seleniumURL, 'https://world.hyatt.com/content/gp/en/signin-error.html')
                    || strstr($this->seleniumURL, 'https://world.hyatt.com/content/gp/de/signin-error.html')
                )
            ) {
                throw new CheckRetryNeededException(2, 7);
            }
            // The information you have entered does not match what we have on file. Please review your account information and try signing in again.
            if (strstr($error, 'The information you have entered does not match what we have on file.')) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, 'Die von Ihnen eingegebenen Informationen stimmen nicht mit den ')) {
                throw new CheckException("The information you have entered does not match what we have on file. Please review your account information and try signing in again.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, 'Wir haben Ihr Konto gesperrt, um es sicher zu halten')) {
                throw new CheckException("We have locked your account to keep it secure. To unlock your account, please call the Global Contact Center at (800) 544-9288 or Hyatt in your region.", ACCOUNT_LOCKOUT);
            }
            // We are unable to access your information. Please contact World of Hyatt Customer Service at (800) 544-9288 or Hyatt in your region.
            if (strstr($error, 'We are unable to access your information.')) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, 'Wir können nicht auf Ihre Informationen zugreifen.')) {
                throw new CheckException("We are unable to access your information. Please contact World of Hyatt Customer Service at (800) 544-9288 or Hyatt in your region.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, 'Es ist ein Fehler aufgetreten, versuchen Sie es bitte erneut.')
                || strstr($error, 'Es ist ein Fehler aufgetreten. Versuchen Sie es bitte erneut.')
            ) {
                throw new CheckException("Something went wrong, please try again!", ACCOUNT_PROVIDER_ERROR);
            }

            if ($error == 'signIn.error-SystemError') {
                throw new CheckException("Sign in error occured", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        sleep(1);

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            $this->logger->notice("Access Denied");
            $this->DebugInfo = 'Access Denied';
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            if ($this->http->Response['code'] == 403) {
                throw new CheckRetryNeededException(3);
            }
        }// if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]"))
        // I am not ...
        if (!$this->http->FindPreg("/\"goldpassportId\"/ims") && $this->http->FindPreg("/\"username\":\"([^\"]+)/ms")
            && $this->http->FindPreg("/\"userLName\":\"[^\"]+\",\"atgflag\":false,\"goldpassportUser\":\"[^\"]+\"/ims")) {
            $this->logger->notice("I am not ... .May be auth was failed");
            $this->DebugInfo = "I am not ...";
            $this->http->GetURL(self::PROFILE_DATA_URL, $this->profileHeaders);
        }

        if ($this->http->FindPreg("/\"accountNumber\"/ims") || $this->mainInfo) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $responseProfile = $this->mainInfo ?? $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 3, true);
        $profile = ArrayVal(ArrayVal($responseProfile, 'profile'), 'full');

        // Name
        $firstName = $this->firstName = ArrayVal($profile, 'firstName');
        $lastName = $this->lastName = ArrayVal($profile, 'lastName');
        $name = Html::cleanXMLValue(ArrayVal($profile, 'prefix') . " " . $firstName . " " . $lastName);
        $this->SetProperty("Name", beautifulName($name));
        // World  Passport #
        $this->goldpassportId = ArrayVal($profile, 'accountNumber');
        $this->SetProperty("Number", $this->goldpassportId);
        // Tier
        $tierData = ArrayVal($profile, 'profile');
        $tier = ArrayVal($tierData, 'tier');

        switch ($tier) {
            case 'P':
                $this->SetProperty("Tier", "Platinum");

                break;

            case 'D':
                $this->SetProperty("Tier", "Diamond");

                break;

            case 'l':
                $this->SetProperty("Tier", "Lifetime Diamond");

                break;

            case 'C':
                $this->SetProperty("Tier", "Courtesy");

                break;

            case 'G':
                $this->SetProperty("Tier", "Gold");

                break;
            // new statuses
            case 'M':
                $this->SetProperty("Tier", "Member");

                break;

            case 'E':
                $this->SetProperty("Tier", "Explorist");

                break;

            case 'V':
                $this->SetProperty("Tier", "Discoverist");

                break;

            case 'B':
                $this->SetProperty("Tier", "Globalist");

                break;

            case 'L':
                $this->SetProperty("Tier", "Lifetime Globalist");

                break;

            default:
                if ($this->ErrorCode == ACCOUNT_CHECKED) {
                    $this->sendNotification("goldpassport. New tier was found: {$tier}");
                }

                break;
        }// switch ($tier)
        // Tier Expiration
        $tierExpireDate = ArrayVal($tierData, 'tierExpireDate');

        if (!empty($tierExpireDate) && strtotime($tierExpireDate) && strtotime($tierExpireDate) < 2553937585 /*Tue, 06 Dec 2050 11:06:25 GMT*/) {
            $this->SetProperty("TierExpiration", strtotime($tierExpireDate));
        }
        // Member since
        $enrollDate = ArrayVal($tierData, 'enrollDate');

        if (!empty($enrollDate) && strtotime($enrollDate)) {
            $this->SetProperty("MemberSince", strtotime($enrollDate));
        }
        // Lifetime Points
        $this->SetProperty("LifetimePoints", number_format(ArrayVal($profile, 'lifePoints')));
        // Balance - Current Points
        $this->SetBalance(ArrayVal($profile, 'points'));
        // Qualified Nights YTD
        $this->SetProperty("Nights", ArrayVal($profile, 'ytdNights'));
        // Base Points YTD
        $this->SetProperty("BasePointsYTD", ArrayVal($profile, 'ytdBasePoints'));

        if (!empty($this->goldpassportId)) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            $this->itineraryRequest = [
                "goldPassportId" => $this->goldpassportId,
                "locale"         => "en",
                "firstName"      => $firstName,
                "lastName"       => $lastName,
            ];

            $pastActivitiesResponse = $this->http->JsonLog($this->history, 0, true) ?? $this->getHistoryRows();
            $pastActivities = $pastActivitiesResponse['pastActivity'] ?? [];
            // Expiration date  // refs #6360, 12414
            $this->logger->debug("Total " . ((is_array($pastActivities) || ($pastActivities instanceof Countable)) ? count($pastActivities) : 0) . " activity rows were found");

            if (empty($pastActivities)) {
                return;
            }

            foreach ($pastActivities as $activity) {
                $activityDate = $this->http->FindPreg("/(.+)T?/", false, $activity['transaction']['date'] ?? '');
                $this->logger->debug("Activity Date: {$activityDate}");

                if (!$activityDate) {
                    $this->logger->error('Skipping activity with no date');

                    continue;
                }

                $d = strtotime($activityDate);

                if (!isset($lastActivity) && $d !== false) {
                    $lastActivity = $activityDate;
                    $lastActivityUnixTime = $d;
                    $this->logger->debug("Last Activity: {$lastActivity} / {$lastActivityUnixTime}");
                }// if (!isset($lastActivity) && $d !== false)

                if ($d !== false && $d <= time()) {
                    $exp = strtotime("+2 year", $d);
                }

                if (isset($exp, $lastActivityUnixTime)) {
                    $this->logger->debug("Exp: $exp");
                    $this->logger->debug("lastActivityUnixTime: $lastActivityUnixTime");

                    if ($exp <= $lastActivityUnixTime) {
                        $this->SetProperty("LastActivity", strtotime($activityDate));
                        $this->SetExpirationDate($exp);
                    } else {
                        $exp = strtotime("+2 year", $lastActivityUnixTime);
                        $this->SetExpirationDate($exp);
                        $this->SetProperty("LastActivity",strtotime($lastActivity));
                    }

                    break;
                }// if (isset($exp, $lastActivityUnixTime))
            }// foreach ($pastActivities as $activity)

            // SubAccounts - Awards     // refs #24201
            $this->logger->info('Awards', ['Header' => 3]);

            if (!$this->awards) {
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://www.hyatt.com/profile/api/loyalty/awarddetail?locale=en-US",
                    $this->headers);
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog();
            } else {
                $response = $this->http->JsonLog($this->awards);
            }

            $awardCategories = $response->awardCategories ?? [];
            $this->logger->debug("Total nodes found: " . count($awardCategories));
            $subAccount = [];

            foreach ($awardCategories as $awardCategory) {
                foreach ($awardCategory->awards ?? [] as $award) {
                    $subAcc = [
                        'Code' => 'goldpassport' . $award->code . $award->expirationDate . str_replace(' ', '',
                                $award->title),
                        'DisplayName'    => $award->title,
                        'Balance'        => 1,
                        'ExpirationDate' => strtotime($award->expirationDate),
                    ];

                    $code = $award->expirationDate . str_replace(' ', '', $award->title);

                    if (isset($subAccount[$code])) {
                        $subAcc = $subAccount[$code];
                        ++$subAcc['Balance'];
                    }
                    $subAccount[$code] = $subAcc;
                }
            }
            unset($subAcc);

            foreach ($subAccount as $subAcc) {
                $this->AddSubAccount($subAcc);
            }
        }// if (!empty($this->goldpassportId))
    }

    public function ParseItineraries()
    {
        /** @var TAccountCheckerGoldpassport $checker */
        $result = [];

        if (empty($this->itineraryRequest)) {
            return $result;
        }

        if ($this->itineraries) {
            if ($this->itineraries == 'Error retrieving reservations') {
                $this->itinerariesMaster->setNoItineraries(true);

                return [];
            }
            $response = $this->http->JsonLog($this->itineraries, 3, true);
            $upcomingStays = ArrayVal($response, 'reservations', []);
        } else {
            $this->http->RetryCount = 0;
            $headers = [
                'Accept'              => 'application/json',
                'Referer'             => 'https://www.hyatt.com/profile/my-stays',
            ];
            $this->http->GetURL("https://www.hyatt.com/profile/api/stay/reservation?locale=en-US&firstName=$this->firstName}&lastName={$this->lastName}", $headers);
            $this->http->RetryCount = 2;

            $newResult = $this->http->JsonLog(null, 3, true);
            $upcomingStays = ArrayVal($newResult, 'reservations', []);
        }

        $countUpcomingStays = count($upcomingStays);
        $this->logger->debug("Total {$countUpcomingStays} itineraries were found");

        if ($this->chrome) {
            foreach ($upcomingStays as $reservation) {
                $itin = $this->ParseItinerary($reservation, $this);

                if ($itin) {
                    $result = array_merge($result, $itin);
                }
            }// foreach ($upcomingStays as $stays)
        } else {
            $result = $this->parseWithCheckSelenium($upcomingStays);
        }
        // no Itineraries
        if ($countUpcomingStays == 0
            && (
                $this->http->FindPreg("/\{\"upcomingStays\":\[\]\}/")
                || $this->http->FindPreg("/\{\"reservations\":\[\],\"totalCount\":0\}/", false, $this->itineraries)
                || $this->http->FindPreg("/\{\"reservations\":\[\],\"totalCount\":0\}/")
            )
        ) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        // trips/addConfirmation.php
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "CheckInDate" => [
                "Caption"  => "Check-in Date",
                "Size"     => 40,
                "Type"     => "date",
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        // return "https://www.hyatt.com/hyatt/reservations/retrieveReservation.jsp";
        return "https://www.hyatt.com/reservation/find";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        if ($this->attempt == 0) {
            $this->setProxyBrightData();
        } elseif ($this->attempt == 1) {
            $this->setProxyGoProxies();
        } elseif ($this->attempt == 2) {
            $this->setProxyMount();
        }
        $checker2 = $this->seleniumItinerary();
        $error = $this->CheckConfirmationNumberInternalSelenium($arFields, $it, $checker2);
        // $error = $this->CheckConfirmationNumberInternalCurl2($arFields, $it);
        return $error ? $error : null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Check-in Date"    => "Info.Date",
            "Check-out Date"   => "Info.Date",
            "Transaction Date" => "PostingDate",
            "Description"      => "Description",
            "Type"             => "Info",
            "Bonus"            => "Bonus",
            "Points"           => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $data = $this->http->JsonLog($this->history, 0, true) ?? $this->getHistoryRows();
        $result = $this->parseHistoryData($startDate, $data);

        $this->getTime($startTimer);

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $this->seleniumSettings($selenium);

            try {
                $selenium->http->start();
            } catch (Exception $e) {
                $this->logger->error("Exception on selenium start: " . $e->getMessage(), ['HtmlEncode' => true]);
                $retry = true;
            }

            try {
                $selenium->http->GetURL('https://www.hyatt.com/de-DE/member/sign-in/traditional?returnUrl=https%3A%2F%2Fwww.hyatt.com%2Fprofile%2Faccount-overview');
                $selenium->Start();
                $selenium->driver->manage()->window()->maximize();
            } catch (TimeOutException | ScriptTimeoutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);

                if (!isset($selenium->driver)) {
                    $retry = true;

                    return;
                }
                $selenium->driver->executeScript('window.stop();');
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage(), ['HtmlEncode' => true]);
                $retry = true;

                return;
            }

            if ($this->chrome) {
                $selenium->waitForElement(WebDriverBy::xpath('//body'));
                $mover = new MouseMover($selenium->driver);
                $mover->logger = $this->logger;
                $mover->duration = rand(90000, 120000);
                $mover->steps = rand(40, 70);
            }// if ($this->chrome)

            $form = "//form[@name = 'signin-form']";

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
            /*
            $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'userId'] | //a[contains(text(), 'Sign in with password')]"), 10);
            $this->savePageToLogs($selenium);


            if ($signInWithPass = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign in with password')]"), 0)) {
                $signInWithPass->click();
            }
            */

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'userId']"), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@type = 'password']"), 0);
            $nameInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'lastName']"), 0);
            $this->savePageToLogs($selenium);

            // todo: debug
            if (!$loginInput || !$passwordInput || !$nameInput) {
                $selenium->http->GetURL('https://www.hyatt.com/en-US/member/sign-in/traditional?returnUrl=https%3A%2F%2Fwww.hyatt.com%2Fprofile%2Faccount-overview');
                $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'userId']"), 10);
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@type = 'password']"), 0);
                $nameInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'lastName']"), 0);
            }

            if (!$loginInput || !$passwordInput || !$nameInput) {
                $this->logger->error("something went wrong");
                // save page to logs
                $this->savePageToLogs($selenium);

                if ($this->http->FindPreg('/fingerprint\/script\/kpf\.js\?url=/')) {
                    throw new CheckRetryNeededException(3, 5);
                }

                // retries, maintenance message - often it's just block by provider
                if ($message = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'main']/p[contains(text(), 'The page you are trying to access is currently down for maintenance.')]"), 0)) {
                    throw new CheckRetryNeededException(3, 10, $message->getText());
                }

                if ($message = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'The World of Hyatt account system is offline for maintenance. We will be back shortly.')]"), 0, false)) {
                    throw new CheckRetryNeededException(3, 10, $message->getText());
                }

                if ($message = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Das Kontosystem von World of Hyatt ist derzeit aufgrund von Wartungsarbeiten offline. Das System wird Ihnen in Kürze wieder zur Verfügung stehen.')]"), 0)) {
                    throw new CheckException("The World of Hyatt account system is offline for maintenance. We will be back shortly.", ACCOUNT_PROVIDER_ERROR);
                }
                // retries
                if ($selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Reside outside the U.S. & Canada')]"), 0)
                    && !$selenium->waitForElement(WebDriverBy::xpath("//*[contains(., 'maintenance')]"), 0)) {
                    throw new CheckRetryNeededException(3, 10, $message->getText());
                }

                if (!empty($this->http->Response['body']) && mb_strlen($this->http->Response['body']) < 150) {
                    $this->logger->error("White page detection");

                    throw new CheckRetryNeededException(2, 0);
                }

                return $this->checkErrors();
            }

            if ($this->chrome) {
                $this->logger->debug("set credentials");
                $mover->moveToElement($loginInput);
                $mover->click();
                $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
                $mover->moveToElement($passwordInput);
                $mover->click();
                $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);
                $this->savePageToLogs($selenium);

                $nameInput->click();
                $mover->sendKeys($nameInput, $this->AccountFields['Login2'], 10);

                $button = $selenium->waitForElement(WebDriverBy::xpath("{$form}//button[contains(@class, 'submit-btn')]"), 10);
                $this->logger->debug("click 'Sign In'");
                $mover->moveToElement($button);
            } else {
                $this->logger->debug("login click");
                $loginInput->click();
                usleep(300000);
                $loginInput->sendKeys($this->AccountFields['Login']);
                $passwordInput->click();
                usleep(300000);
                $passwordInput->sendKeys($this->AccountFields['Pass']);
                $nameInput->click();
                usleep(300000);
                $nameInput->sendKeys($this->AccountFields['Login2']);
                $this->savePageToLogs($selenium);
            }

            usleep(rand(400000, 1300000));
            $button = $selenium->waitForElement(WebDriverBy::xpath("{$form}//button[contains(@class, 'submit-btn')]"), 10);

            if (!$button) {
                $jsSuccess = $selenium->driver->executeScript("
                    function isBtnHere() {
                        let btn = document.forms['signin-form'].querySelector('button.submit-btn');
                        if (btn) return true;
                        else return false;
                    }
                    isBtnHere();
                ");

                if (!$jsSuccess) {
                    $this->logger->error("sign in btn not found");

                    return false;
                }

                $button = $selenium->waitForElement(WebDriverBy::xpath("{$form}//button[contains(@class, 'submit-btn')]"), 10);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("click login btn");

            if ($this->chrome) {
                $button->click();
//                $mover->moveToElement($button);
//                $mover->click();
            } else {
                // strange bug with populating fields on de page version
                // we need to switch focus to enable button
                $header = $selenium->waitForElement(WebDriverBy::xpath("//h1"), 3);
                $header->click();
                usleep(200000);
                $passwordInput->click();
                usleep(100000);
                $button->click();
            }

            // Access Denied
            sleep(3);
            $timeLimit = 10;
            $memberInfo = $selenium->waitFor(function () use ($selenium) {
                return
                    $selenium->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Access Denied')] | //title[contains(text(), 'Access Denied')] | //*[contains(text(), 'Current point balance')] | //*[contains(text(), 'Aktueller Punktestand')]"), 0)
                    || $selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Current point balance')]"), 0, false);
            }, $timeLimit);
            // save page to logs
            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
                $this->DebugInfo = 'Access Denied';
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $retry = true;
            } else {
                $this->ErrorReason = null;
                $this->DebugInfo = null;
            }

            try {
                $this->savePageToLogs($selenium);
            } catch (NoSuchWindowException $e) {
                $this->logger->error("NoSuchWindowException: " . $e->getMessage(), ['HtmlEncode' => true]);
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("Logs weren't saved");
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $retry = true;
            }

            // retries, maintenance message - often it's just block by provider
            if (!$memberInfo && ($message = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'main']/p[contains(text(), 'The page you are trying to access is currently down for maintenance.')]"), 0))) {
                throw new CheckRetryNeededException(3, 7, $message->getText());
            }
            // retries
            if (!$memberInfo && $selenium->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'The proxy server is refusing connections')]"), 0)) {
                $retry = true;
            }

            if (!$retry && !$this->http->FindSingleNode("//span[contains(@class, 'error-message')]")) {
                $this->logger->info('Profile data request', ['Header' => 3]);
                $selenium->http->GetURL(self::PROFILE_DATA_URL, $this->profileHeaders);
                $this->mainInfo = $this->http->JsonLog($selenium->http->FindSingleNode('//pre[not(@id)]'), 3, true);

                /*
                // this is not working, but calls retries
                if (
                    !$this->mainInfo
                    && (
                        $this->http->FindSingleNode('//pre[contains(text(), "Internal Server Error")]')
                        || empty($this->http->Response['body'])
                        || $this->http->FindPreg("/<head><\/head><body><script>window.KPSDK=\{\};KPSDK\.now=typeof performance!=='undefined'\&/")
                    )
                ) {
                    $selenium->http->GetURL(self::PROFILE_DATA_URL, $this->profileHeaders);
                    $this->mainInfo = $this->http->JsonLog($selenium->http->FindSingleNode('//pre[not(@id)]'), 3, true);
                }
                */

                // World  Passport #
                $this->goldpassportId = ArrayVal(ArrayVal(ArrayVal($this->mainInfo, 'profile'), 'full'), 'accountNumber');
                $this->logger->debug("goldpassportId -> {$this->goldpassportId}");

                try {
                    $selenium->http->GetURL('https://www.hyatt.com/profile/account-overview');
                    $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(@ng-class, "gpUser.points")] | //div[@data-locator  ="points-balance"]'), 10);
                    $this->savePageToLogs($selenium);
                } catch (UnexpectedAlertOpenException $e) {
                    $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $error = $selenium->driver->switchTo()->alert()->getText();
                    $this->logger->debug("alert -> {$error}");
                    $selenium->driver->switchTo()->alert()->accept();
                    $this->logger->debug("alert, accept");
                }// catch (UnexpectedAlertOpenException $e)
                catch (NoAlertOpenException $e) {
                    $this->logger->debug("no alert, skip");
                } catch (TimeOutException $e) {
                    $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $selenium->driver->executeScript('window.stop();');
                } catch (UnexpectedJavascriptException | WebDriverCurlException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }

                if ($this->mainInfo) {
                    $this->logger->debug("executeScript awards");
                    $this->logger->info('Awards request', ['Header' => 3]);
                    $selenium->http->GetURL("https://www.hyatt.com/profile/api/loyalty/awarddetail?locale=en-US");
                    $this->awards = $selenium->http->FindSingleNode('//pre[not(@id)]');
                    $this->logger->info("[Form response]: " . $this->awards);

                    $this->logger->debug("executeScript history");
                    $this->logger->info('History request', ['Header' => 3]);
                    $filterStartDate = date("Y-m-d", strtotime("-5 years"));
                    $selenium->http->GetURL("https://www.hyatt.com/profile/api/stay/pastactivity?pageSize=100&pageIndex=0&transactionType=AT&locale=en-US&startDate={$filterStartDate}&endDate=");
                    $this->history = $selenium->http->FindSingleNode('//pre[not(@id)]');
                    $this->logger->info("[Form history]: " . $this->history);

                    $this->logger->debug("executeScript itineraries");
                    $this->logger->info('Itineraries request', ['Header' => 3]);
                    $profile = ArrayVal(ArrayVal($this->mainInfo, 'profile'), 'full');
                    $selenium->http->GetURL("https://www.hyatt.com/profile/api/member/reservation?locale=en-US&firstName=" . urlencode(ArrayVal($profile, 'firstName')) . "&lastName=" . urlencode(ArrayVal($profile, 'lastName')));
                    $this->itineraries = $selenium->http->FindSingleNode('//pre[not(@id)]');
                    $this->logger->info("[Form itineraries]: " . $this->itineraries);
                }// if ($this->mainInfo)

                try {
                    $cookies = $selenium->driver->manage()->getCookies();
                } catch (UnexpectedAlertOpenException $e) {
                    $cookies = $selenium->http->driver->browserCommunicator->getCookies();
                    $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $error = $selenium->driver->switchTo()->alert()->getText();
                    $this->logger->debug("alert -> {$error}");
                    $selenium->driver->switchTo()->alert()->accept();
                    $this->logger->debug("alert, accept");
                }// catch (UnexpectedAlertOpenException $e)
                catch (NoAlertOpenException $e) {
                    $this->logger->debug("no alert, skip");
                    $cookies = $selenium->driver->manage()->getCookies();
                }

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }// if (!$retry)

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Selenium URL]: {$this->seleniumURL}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);

            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')
                || strstr($e->getMessage(), 'Timed out waiting for page load')) {
                $retry = true;
            }
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
//            if (strstr($e->getMessage(), 'Document was unloaded during execution'))
//                $retry = true;
        } catch (NoSuchDriverException | NoSuchWindowException $e) {
            $this->logger->error("NoSuchDriverException | NoSuchWindowException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (WebDriverCurlException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);

            if (strstr($e->getMessage(), 'Element not found in the cache')) {
                $retry = true;
            }
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage(), ['HtmlEncode' => true]);
//            if (strstr($e->getMessage(), 'Connection refused (Connection refused)'))
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); // todo

            $this->logger->debug("[retry]: {$retry}");

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 10);
            }
        }

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // GoldPassport.com and other related sites are currently down for maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'GoldPassport.com and other related sites are currently down for maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The page you are trying to access is currently down for maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The page you are trying to access is currently down for maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Temporarily Unavailable
        if ($message = $this->http->FindPreg('/Service Temporarily Unavailable/ims')) {
            throw new CheckException("The server is temporarily unable to service your request due to maintenance downtime or capacity problems. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, we're experiencing technical difficulties and were unable to complete your request.
        if ($message = $this->http->FindSingleNode('//h2[contains(text(),"Sorry, we\'re experiencing technical difficulties and were unable to complete your request.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The World of Hyatt account system is offline for maintenance. We will be back shortly.
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "The World of Hyatt account system is offline for maintenance. We will be back shortly.")]
                | //p[contains(text(), "Hyatt.com, world.hyatt.com, and other related sites are currently down for maintenance.")]
                | //strong[contains(text(), "The World of Hyatt account system is offline for maintenance. We will be back shortly.")]
                | //p[contains(text(), "Hyatt.com and other related sites are currently down for maintenance. Please come back soon.")]
            ')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // provider error
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ((($this->http->FindPreg("/\"csrf\":\"[\"]+/ims") && $this->http->FindPreg("/\"enrollDate\":\"" . date("Ymd") . "\"/ims"))
            || $this->http->FindPreg("/^\{\"csrf\":\"[^\"]+\",\"token\":\"[^\"]+\",\"atgDown\":false,\"atgflag\":false}$/ims")
            || $this->http->FindPreg("/^\{\"csrf\":\"[^\"]+\",\"token\":\"[^\"]+\",\"atgflag\":false\}$/ims")
            || $this->http->FindPreg("/^\{\"csrf\":\"[^\"]+\",\"cToken\":\"\",\"token\":\"[^\"]+\",\"atgDown\":false,\"atgflag\":false\}$/ims"))
            && (ConfigValue(CONFIG_SITE_STATE) !== SITE_STATE_DEBUG)) {
            throw new CheckRetryNeededException(3, 10);
        }

        if ($error = $this->http->FindPreg("/(?:Secure Connection Failed|Unable to connect|The proxy server is refusing connections|Failed to connect parent proxy|The connection has timed out|You don't have permission to access \/content\/gp\/en\/sign-in\.html\s*on this server\.|The proxy server received an invalid response from an upstream server\.)/")) {
            $this->logger->error($error);

            if ($error == 'You don\'t have permission to access /content/gp/en/sign-in.html on this server.') {
                $this->DebugInfo = self::ERROR_REASON_BLOCK;
            }

            throw new CheckRetryNeededException(4, 10);
        } else {
            $this->DebugInfo = null;
        }

        return false;
    }

    private function getHistoryRows($logs = 0)
    {
        $filterStartDate = date("Y-m-d", strtotime("-5 years"));
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.hyatt.com/profile/api/stay/pastactivity?pageSize=100&pageIndex=0&transactionType=AT&locale=en-US&startDate={$filterStartDate}&endDate=", $this->headers);
        $this->http->RetryCount = 2;

        return $this->http->JsonLog(null, $logs, true);
    }

    // dictionary -> https://world.hyatt.com/libs/cq/i18n/dict.en.json
    private function setSubAccountName($code)
    {
        switch ($code) {
            case 'TUPUS':
            case 'ADJUPUS':
                $displayName = "Suite Upgrade Award";

                break;

            case 'DIAMD':
                $displayName = "Diamond Suite Upgrade";

                break;

            // broken subacc
            case 'DISVGIFTAW':
                $displayName = null;

                break;

            case 'PBUPUR':
            case 'GOHLEGACY':
            case 'GOHCY14M':
                $displayName = "Club Access Award";

                break;

            case 'UPUS2':
                $displayName = "Suite Upgrade Award";

                break;

            case 'TUPUS2':
            case 'TUPUSM':
                $displayName = "Tier Suite Upgrade Award";

                break;

            case 'MS75UH':
                $displayName = 'One Free Night - 75 Unique Hotels';

                break;

            case 'MSBL10B':
                $displayName = 'One Free Night in a Suite - 1 million base points';

                break;

            case 'CHASE_FN':
                $displayName = 'Free Night Award';

                break;

            case 'CAT17RM':
            case 'CAT17RM365':
                $displayName = 'Promotional Free Night Award';

                break;

            case 'CAT14RM365':
                $displayName = "Category 1-4 Free Night Award 365";

                break;

            case 'CAT14RM':
                $displayName = "Category 1-4 Promotion Award";

                break;

            case 'CHRM1':
                $displayName = "Standard Free Night Award";

                break;

            case 'CHRM2':
                $displayName = "Category 1-4 Standard Award";

                break;

            case 'CHASE_ANIV':
                $displayName = "Anniversary Free Night Award";

                break;

            default:
                $displayName = null;
                $this->sendNotification("goldpassport - refs #14615 World of Hyatt - changing the subaccount's name. New award type was found: {$code}");
        }

        return $displayName;
    }

    private function parseWithCheckSelenium($upcomingStays, bool $isRetry = false)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $countUpcomingStays = count($upcomingStays);

        try {
            // refs #14290
            if ($countUpcomingStays > 0) {
                $checker = $this->seleniumItinerary();

                foreach ($upcomingStays as $reservation) {
                    $itin = $this->ParseItinerary($reservation, $checker);
                    sleep(2);

                    if ($itin) {
                        $result = array_merge($result, $itin);
                    }
                }// foreach ($upcomingStays as $stays)
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
        } catch (WebDriverCurlException $e) {
            $this->handleConnectException($e);
        } catch (NoSuchDriverException | NoSuchWindowException $e) {
            $this->handleConnectException($e);
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage(), ['HtmlEncode' => true]);

            if (strpos($e->getMessage(), 'Connection refused (Connection refused)') !== false) {
                $this->handleConnectException($e);
            }
        } finally {
            $this->logger->debug("finally");
            // close Selenium browser
            if ($countUpcomingStays > 0) {
                if (isset($checker)) {
                    $checker->http->cleanup();
                } else {
                    if (!$isRetry) {
                        sleep(5);
                        $result = $this->parseWithCheckSelenium($upcomingStays, true);
                    }
                }
            }
        }

        return $result;
    }

    private function handleConnectException(Exception $e)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->error("SeleniumDriver::handleConnectException: " . $e->getMessage(), ['HtmlEncode' => true]);

        if (stripos($e->getMessage(), 'timed out') === false) {
            throw new CheckRetryNeededException(3, 10);
        }
    }

    private function ParseItinerary($reservation, $checker2)
    {
        $this->logger->notice(__METHOD__);
        // ConfirmationNumber
        $confNo = ArrayVal($reservation, 'hotelReservationId', null) ?? ArrayVal($reservation, 'confirmationNumber');
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $confNo), ['Header' => 3]);
        $reservationToken = ArrayVal($reservation, 'reservationToken', null);

        if (!$reservationToken) {
            return [];
        }

//        $checker2->http->GetURL('https://www.hyatt.com/profile/my-stays');
//        $checker2->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Stay Details")]'), 10);
        //$this->increaseTimeLimit();
//        $this->savePageToLogs($checker2);
        /*$headers = [
            'Referer' => 'https://www.hyatt.com/profile/my-stays',
            'Accept'  => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,* / *;q=0.8,application/signed-exchange;v=b3;q=0.7',
        ];
        $this->http->GetURL($detailsLink, $headers);*/
        $this->increaseTimeLimit();
        $checker2->http->GetURL("https://www.hyatt.com/reservation/detail/$reservationToken");

        $checker2->waitForElement(WebDriverBy::xpath('//input[@id="firstName"]'), 10);

        $arFields = [
            'FirstName'   => $this->firstName,
            'LastName'    => $this->lastName,
            'ConfNo'      => $confNo,
            'CheckInDate' => ArrayVal($reservation, 'checkinDate'),
        ];
        $this->CheckConfirmationNumberInternalSelenium($arFields, $it, $checker2, false);

        return [];

        try {
            $checker2->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Confirmation: #")]'), 10);
            $this->savePageToLogs($checker2);
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $checker2->driver->executeScript('window.stop();');
        } catch (
            Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\WebDriverException
            | WebDriverException
            $e
        ) {
            $this->logger->error("WebDriverCurlException | WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        $this->http->RetryCount = 2;
        $unableToProcess = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, we are currently unable to process your request.")]');

        if ($unableToProcess) {
            $this->logger->error($unableToProcess);
            $this->logger->notice("Parse info from json");
            $this->parseItineraryFromJson($reservation);

            return [];
        }

        $this->parseMultiItinerary2018($reservation);

        return [];
    }

    private function getHotelAddress($reservation, $hotelName)
    {
        $this->logger->notice(__METHOD__);

        $propertyUrl = ArrayVal($reservation, 'propertySite');

        if (!$propertyUrl) {
            return $hotelName;
        }
        $this->http->GetURL($propertyUrl);
        $res = $this->http->FindSingleNode('//div[contains(@class, "address")]//a[contains(@class, "google-map-link-unwrap")]');

        return $res;
    }

    private function parseItineraryFromJson($reservation): bool
    {
        $this->logger->notice(__METHOD__);

        if (empty(ArrayVal($reservation, 'hotelName'))) {
            return false;
        }
        $h = $this->itinerariesMaster->createHotel();
        $confNo = ArrayVal($reservation, 'hotelReservationId');
        $h->general()->confirmation($confNo);
        $hotelName = ArrayVal($reservation, 'hotelName');

        if (!$hotelName) {
            return false;
        }

        $h->hotel()->name($hotelName);
        $h->hotel()->address($this->getHotelAddress($reservation, $hotelName));
        $h->booked()->guests(ArrayVal($reservation, 'guests'));
        $h->booked()->kids(ArrayVal($reservation, 'kids'));
        $h->setCancellation(ArrayVal($reservation, 'cancellationDescription'));
        $checkIn = ArrayVal($reservation, 'startDateText');

        if (!empty($checkIn) && strtotime($checkIn)) {
            $h->booked()->checkIn(strtotime($checkIn));
        }
        $checkOut = ArrayVal($reservation, 'endDateText');

        if (!empty($checkOut) && strtotime($checkOut)) {
            $h->booked()->checkOut(strtotime($checkOut));
        }
        $h->booked()->rooms(ArrayVal($reservation, 'numberOfRooms'));
        $r = $h->addRoom();
        $r->setType(ArrayVal($reservation, 'roomPreference'));
        $r->setDescription(ArrayVal($reservation, 'specialRequests'));

        $h->price()->currency(ArrayVal($reservation, 'currencyType'));
        $h->price()->cost(ArrayVal($reservation, 'totalCost'));
        $h->price()->total(ArrayVal($reservation, 'subTotalCost'));
        $h->price()->spentAwards(ArrayVal($reservation, 'points'));

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);

        return true;
    }

    private function xpathQuery($query, $parent = null): DOMNodeList
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent) ?: new DOMNodeList();
        $this->logger->info("found {$res->length} nodes: {$query}");

        return $res;
    }

    private function parseMultiItinerary2018($reservation = null)
    {
        $this->logger->notice(__METHOD__);

        $hotelNodes = $this->xpathQuery('//div[contains(@class, "p-room-stay") or contains(@class, "m-modify-reservation")]');

        if ($hotelNodes->length == 0) {
            $hotelNodes = $this->xpathQuery('//div[contains(@class, "p-hotel-stay") or contains(@class, "m-modify-reservation")]');
        }

        foreach ($hotelNodes as $hotelNode) {
            $this->parseOneItineraryFromHtml($hotelNode);
        }

        if ($reservation && empty($res[0]['ConfirmationNumber'])) {
            $this->sendNotification('parseItineraryFromJson // MI');
            $this->parseItineraryFromJson($reservation);
        }
    }

    private function parseOneItineraryFromHtml($hotelNode): bool
    {
        $this->logger->notice(__METHOD__);
        $hotelInfoNodes = $this->xpathQuery('.//div[contains(@class, "hotel-info-container") or contains(@class, "m-hotel-card")]', $hotelNode);

        foreach ($hotelInfoNodes as $hotelInfoNode) {
            $roomNodes = $this->xpathQuery('.//div[contains(@class, "m-reservation-details")]', $hotelNode);

            foreach ($roomNodes as $roomNode) {
                $h = $this->itinerariesMaster->createHotel();
                // ConfirmationNumber
                $h->general()->confirmation($this->http->FindSingleNode('//div[contains(text(), "Confirmation:")]', null, false, '/(\w+)$/'));
                // Cancelled
                if ($this->http->FindSingleNode('//div[contains(@class, "p-cancelled-reservation")]//div[contains(text(), "This reservation has been canceled")]')) {
                    $h->general()->cancelled();
                }
                // CancellationPolicy
                $h->setCancellation($this->http->FindSingleNode('(.//div[contains(@class, "cancellation-policy")]/div[contains(text(), "Cancellation Policy")]/following-sibling::div[1])[1]',
                    $hotelNode));

                if (!$hotelInfoNode) {
                    $this->logger->error('something went wrong');

                    continue;
                }
                $hotelName = $this->http->FindSingleNode('.//div[contains(@class, "b-text_display-1")]',
                    $hotelInfoNode);

                if (empty($hotelName)) {
                    $hotelName = $this->http->FindSingleNode('.//div[contains(@class, "b-text_style-uppercase")]',
                        $hotelInfoNode);
                }

                if (empty($hotelName)) {
                    $hotelName = $this->http->FindSingleNode('(.//span[contains(@class, "hotel-name")])[1]',
                        $hotelInfoNode);
                }

                if (empty($hotelName)) {
                    $hotelName = $this->http->FindSingleNode('(.//span[@data-locator = "hotel-name"])[1]',
                        $hotelInfoNode);
                }

                if (empty($hotelName)) {
                    $hotelName = $this->http->FindSingleNode('(.//div[contains(@class, "b-text_style-uppercase")])[1]',
                        $hotelInfoNode);
                }
                $h->hotel()->name($hotelName);

                $address = implode(', ',
                    array_filter($this->http->FindNodes('.//button[@data-js = "cancel-button"]/ancestor::div[1]/preceding-sibling::div[2]',
                        $hotelInfoNode)));

                if (empty($address)) {
                    $address = implode(', ',
                        $this->http->FindNodes('(.//span[@data-locator = "hotel-name"])[2]/ancestor::div[1]/following-sibling::div[1]',
                            $hotelInfoNode));
                    $address = $this->http->FindPreg('/^(.+?)(?:\s*Tel:|$)/', false, $address);
                }

                if (empty($address)) {
                    $address = $this->http->FindSingleNode('.//a[@data-js = "print-button"]/ancestor::ul[1][count(./preceding-sibling::div)=3 or count(./preceding-sibling::div)=4]/ancestor::div[1]/div[2]',
                        $hotelInfoNode);
                }
                $h->hotel()->address($address);

                $phone = $this->http->FindSingleNode('.//div[contains(@class, "b-text_display-1")]/following-sibling::div[contains(text(), "Tel:")]',
                    $hotelInfoNode, true, '/Tel:\s*(.+)/ims');

                if (empty($phone)) {
                    $phone = $this->http->FindSingleNode('.//div[contains(text(), "Tel:")]', $hotelInfoNode, true,
                        '/Tel:\s*(.+)/ims');
                }
                // +49 89 904 219 1234​
                $phone = str_replace('–', '-', trim($phone, '​'));
                $phone = preg_replace('/[+＋]/', '+', $phone);
                $h->hotel()->phone($phone, true, true);

                $fax = $this->http->FindSingleNode('.//div[contains(@class, "b-text_display-1")]/following-sibling::div[contains(text(), "Fax:")]',
                    $hotelInfoNode, true, '/Fax:\s*(.+)/ims');
                $h->hotel()->fax($fax, false, true);

                $checkIn = $this->http->FindSingleNode('.//dt[normalize-space(text()) = "Check-in"]/following-sibling::dd[1]',
                    $roomNode);
                $checkInTrimmed = $this->http->FindPreg('/^(.+?)\s*Invalid date/i', false, $checkIn);
                $h->booked()->checkIn($checkInTrimmed ? strtotime($checkInTrimmed) : strtotime($checkIn));
                $h->booked()->checkOut(strtotime($this->http->FindSingleNode('.//dt[normalize-space(text()) = "Check-out" or normalize-space(text()) = "Checkout"]/following-sibling::dd[1]',
                    $roomNode)));

                $h->booked()->guests($this->http->FindSingleNode('.//dt[contains(text(), "Guests")]/following-sibling::dd[1]',
                    $roomNode, true, '/(\d+)\s*Guests?/i'), false, true);
                $h->general()->travellers(array_map(function ($elem) {
                    return beautifulName($elem);
                }, $this->http->FindNodes('.//dt[contains(text(), "Name")]/following-sibling::dd[1]', $roomNode)));

                $h->program()->accounts($this->http->FindNodes('.//dt[contains(text(), "World of Hyatt Membership #")]/following-sibling::dd[1]',
                    $roomNode), false);
                $total = $this->http->FindSingleNode('.//span[@data-js = "cash-total-price"]/@data-price', $roomNode);

                if (isset($total)) {
                    $h->price()->total(round($total, 2));
                    $h->price()->currency($this->http->FindSingleNode('.//span[@data-js = "cash-total-price"]/@data-currency',
                        $roomNode));

                    $cost = $this->http->FindSingleNode('.//span[@data-js = "subtotal-price"]/@data-price', $roomNode);

                    if (isset($cost)) {
                        $h->price()->cost(round($cost, 2));
                    }
                    // Taxes
                    $tax = $this->http->FindSingleNode('.//span[@data-js = "taxes-fees-price"]/@data-price',
                        $roomNode);

                    if (isset($tax)) {
                        $h->price()->tax(round($tax, 2));
                    }
                }

                $h->price()->spentAwards($this->http->FindSingleNode('.//div[contains(text(), "Total Points")]/following-sibling::div[1]', $roomNode), false, true);
                $roomInfo = $this->http->FindSingleNode('.//div[contains(@class, "p-reservation-summary")]//dt[contains(text(), "Room")]/following-sibling::dd[1]', $roomNode);
                $h->booked()->rooms($this->http->FindPreg('/^\s*\((\d+)\)/i', false, $roomInfo));
                $r = $h->addRoom();

                if (!in_array($roomInfo, ['(1)'])) {
                    $r->setType($this->http->FindPreg('/^\s*\(\d+\)\s*([^<]+)/i', false, $roomInfo));
                }

                //$r->addRate($this->http->FindSingleNode('.//dt[contains(text(), "Rate")]/following-sibling::dd[1]', $roomNodes));
                //$r->setDescription($this->http->FindSingleNode('.//div[contains(@class, "room-details")]//div[contains(@class, "description")]', $roomNodes));

                $freeNight = 0;
                $rates = $this->http->XPath->query("(.//div[contains(text(), 'Total Cash Per Room')]/../following-sibling::div)[1]/div[contains(@class,'summary-row')]/div[contains(@class,'b-text_align-right')]/span",
                    $hotelNode);

                foreach ($rates as $rate) {
                    $r->addRate($rate->nodeValue);

                    $this->logger->notice($rate->getAttribute("data-price"));

                    if ($rate->getAttribute("data-price") == '0') {
                        $freeNight++;
                    }
                }

                if (empty($r->getRate())) {
                    $h->removeRoom($r);
                }

                if ($freeNight > 0) {
                    $h->booked()->freeNights($freeNight);
                }

                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
            }
        }

        return true;
    }

    private function seleniumItinerary(): TAccountCheckerGoldpassport
    {
        $this->logger->notice(__METHOD__);
        // get cookies from curl
        $allCookies = array_merge($this->http->GetCookies("world.hyatt.com"), $this->http->GetCookies("world.hyatt.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies(".hyatt.com"), $this->http->GetCookies(".hyatt.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("www.hyatt.com"), $this->http->GetCookies("www.hyatt.com", "/", true));

        $checker2 = clone $this;
        $this->http->brotherBrowser($checker2->http);
        $this->logger->notice("Running Selenium...");
        $checker2->UseSelenium();

        //$this->seleniumSettingsRetrieve($checker2);
        $this->seleniumSettings($checker2);

        $this->logger->debug("open window...");
        $checker2->http->start();
        $checker2->Start();
        $checker2->driver->manage()->window()->maximize();
        $this->logger->debug("open url...");
//        $checker2->http->GetURL("https://www.hyatt.com/profile/my-stays");
        sleep(5);

        $this->logger->debug("set cookies...");

//        foreach ($allCookies as $key => $value) {
//            $checker2->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".hyatt.com"]);
//        }

        return $checker2;
    }

    private function seleniumSettingsRetrieve(self $selenium)
    {
        $this->logger->notice(__METHOD__);
        $selenium->setProxyBrightData();
        $selenium->useChromePuppeteer();
        $selenium->seleniumOptions->userAgent = null;

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 5;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($fingerprint)) {
            $selenium->http->setUserAgent($fingerprint->getUseragent());
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
        }
    }

    private function seleniumSettings(self $selenium)
    {
        $this->logger->notice(__METHOD__);

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            [1920, 1080],
        ];

//        if (!isset($this->State['Resolution']) || $this->attempt > 1) {
//            $this->logger->notice("set new resolution");
//            $resolution = $resolutions[array_rand($resolutions)];
//            $this->State['Resolution'] = $resolution;
//        } else {
//            $this->logger->notice("get resolution from State");
//            $resolution = $this->State['Resolution'];
//            $this->logger->notice("restored resolution: " . join('x', $resolution));
//        }
//        $selenium->setScreenResolution($resolution);

        $selenium->useChromeExtension(SeleniumFinderRequest::CHROME_EXTENSION_DEFAULT);

        if (!isset($this->AccountFields["UserID"]) || $this->AccountFields["UserID"] != 7) {
            $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        }
        /*
        $selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
        $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        */
//        $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['linux']];
//        $selenium->setProxyBrightData(null, "us_residential");
//        $selenium->http->SetProxy($this->proxyDOP(), false);
//        $selenium->setProxyGoProxies();
        /*
        $selenium->setProxyBrightData();
        */
        $selenium->seleniumOptions->addPuppeteerStealthExtension = false;
        $selenium->seleniumOptions->addHideSeleniumExtension = false;
        $selenium->seleniumOptions->userAgent = null;

        /*
        $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
        $selenium->http->SetProxy($this->proxyDOP(), false);
        $selenium->seleniumOptions->fingerprintParams = \FingerprintParams::vanillaFirefox();
        $selenium->seleniumOptions->addHideSeleniumExtension = false;
        $selenium->setKeepProfile(true);
        $selenium->disableImages();
        $selenium->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:59.0) Gecko/20100101 Firefox/59.0');
        */

        $selenium->http->saveScreenshots = true;
        // It breaks everything
        $selenium->usePacFile(false);
//        $selenium->useCache();
    }

    private function CheckConfirmationNumberInternalCurl2($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetProxy(null, false);
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm(null, '//form[@action = "/en-US/reservation/lookup"]')) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }
        $this->http->SetInputValue('confirmationNumber', $arFields['ConfNo']);
        $this->http->SetInputValue('firstName', $arFields['FirstName']);
        $this->http->SetInputValue('lastName', $arFields['LastName']);

        if (!$this->http->PostForm()) {
            return null;
        }
        $it = $this->parseMultiItinerary2018();

        if (!is_array($it)) {
            $it = [$it];
        }

        return null;
    }

    private function CheckConfirmationNumberInternalCurl($arFields, &$it)
    {
        // not used at the moment
        $this->logger->notice(__METHOD__);
        $this->http->LogHeaders = true;
        $this->http->SetProxy(null, false);
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("retrieveReservation")) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }
        $this->http->Form['/atg/userprofiling/ReservationFormHandler.retrieveReservations'] = 'submit';
        $this->http->Form['/atg/userprofiling/ReservationFormHandler.value.firstName'] = $arFields['FirstName'];
        $this->http->Form['/atg/userprofiling/ReservationFormHandler.value.lastName'] = $arFields['LastName'];
        $this->http->Form['/atg/userprofiling/ReservationFormHandler.value.confirmationNum'] = $arFields['ConfNo'];

        if (!$this->http->PostForm()) {
            return null;
        }

        if (!$this->http->ParseForm("form1")) {
            return null;
        }

        if (!$this->http->PostForm()) {
            return null;
        }
        $it = $this->ParseConfirmationGoldpassport();
        $it = [$it];

        return null;
    }

    private function CheckConfirmationNumberInternalSelenium($arFields, &$it, TAccountCheckerGoldpassport $checker2, $runStartUrl = true)
    {
        $this->logger->notice(__METHOD__);
        $this->http->LogHeaders = true;

        if ($runStartUrl) {
            $checker2->http->GetURL($this->ConfirmationNumberURL($arFields));
        }

        sleep(3);

        $acceptBtn = $checker2->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 5);

        if (!$acceptBtn) {
            $acceptBtn = $checker2->waitForElement(WebDriverBy::xpath("//div[@id='onetrust-close-btn-container']/button"), 0);
        }

        if ($acceptBtn) {
            $acceptBtn->click();
        }

        $checker2->driver->executeScript("
        var iframes = document.querySelectorAll('div[class*=\"QSISlider SI_\"]');
        for (var i = 0; i < iframes.length; i++) {
            iframes[i].parentNode.removeChild(iframes[i]);
        }");

        $this->savePageToLogs($checker2);
        $validateButton = $checker2->waitForElement(WebDriverBy::xpath('//button[@data-js = "find-reservation"]'), 10);
        $firstNameInput = $checker2->waitForElement(WebDriverBy::xpath("//input[@id='firstName']"), 0);
        $lastNameInput = $checker2->waitForElement(WebDriverBy::xpath("//input[@id='lastName']"), 0);
        $confInput = $checker2->waitForElement(WebDriverBy::xpath("//input[@id='confirmationNumber']"), 0);
        $checkinDate = $checker2->waitForElement(WebDriverBy::xpath("//input[@name='checkinDate']"), 0);
        $isoDate = $checker2->waitForElement(WebDriverBy::xpath("//input[@id='isoDate']"), 0, false);

        if (!$validateButton || !$firstNameInput || !$lastNameInput || !$confInput || !$checkinDate || !$isoDate) {
            $this->savePageToLogs($checker2);

            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }
        $firstNameInput->sendKeys($arFields['FirstName']);
        $lastNameInput->click();
        $lastNameInput->sendKeys($arFields['LastName']);
        $confInput->click();
        //$confInput->clear();
        $confInput->sendKeys($arFields['ConfNo']);
        $confInput->click();
        $dateSend = preg_replace("/^(\d+)\/(\d+)\/(\d{4})$/", "$3-$1-$2", $arFields['CheckInDate']);
        $checkinDate->click();
        // Fri, Oct 20
        $checkinDate->sendKeys($dateSend);
        $checker2->driver->executeScript("document.querySelector('input[name=\"isoDate\"]').value='$dateSend'");
        $this->savePageToLogs($checker2);

        //$confInput->click();
        $checker2->driver->executeScript("document.querySelector('button[data-js=\"find-reservation\"]').click()");

        $checker2->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Confirmation: #")]'), 10);

        $checker2->http->SaveResponse();

        $findButton = $checker2->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Find Reservation")]'), 10);
        $this->savePageToLogs($checker2);

        if ($findButton) {
            $findButton->click();
        }

        /*try {
            $alert = $checker2->driver->switchTo()->alert();

            if ($alert) {
                $this->logger->info('Alert text:');
                $this->logger->info($alert->getText());
                $alert->accept();
            } else {
                $this->logger->info('No alert present');
            }
        } catch (Exception $e) {
            $this->logger->info('Alert exception: ' . $e->getMessage());
        }*/

//        $checker2->waitForElement(WebDriverBy::xpath('//p[@class="bw"]'), 10);
        $validateButton = $checker2->waitForElement(WebDriverBy::xpath('//button[@onclick = "validateConfNumber(event);"]'), 0);
        $checker2->http->SaveResponse();

        if ($validateButton) {
            return "We can't find your reservation. Please check its details.";
        }

        $msg = $checker2->waitForElement(WebDriverBy::xpath("//p[contains(.,'Something’s not right. The information you have entered does')][./following::text()[normalize-space()!=''][1][contains(.,'Find another reservation')]]"), 0);

        if ($msg) {
            return $msg->getText();
        }

        try {
            sleep(3);
            $this->savePageToLogs($checker2);

            if (!empty($this->http->Response['body']) && mb_strlen($this->http->Response['body']) < 150) {
                $this->logger->error("White page detection");

                throw new CheckRetryNeededException(2, 0);
            }
        } catch (UnknownServerException $e) {
            $this->sendNotification('Failed to decode response from marionette // MI');
            sleep(3);
            $this->savePageToLogs($checker2);
        }

        $this->parseMultiItinerary2018();

        return null;
    }

    private function ParseConfirmationGoldpassport()
    {
        $this->logger->notice(__METHOD__);
        $http = $this->http;
        $result = ['Kind' => "R"];
        // confirmation number
        $result['ConfirmationNumber'] = $http->FindSingleNode('//div[@class="right_info"]', null, false, '/Confirmation #:\s+(\d+)/ims');
        $result['HotelName'] = $http->FindSingleNode('(//p[@class="bw"])[1]');
        $result['Address'] = implode(', ', $http->FindNodes('(//p[@class="bw"])[1]/following-sibling::p[@class="dim" and contains(text(), "Tel:")]/preceding-sibling::p[@class="dim"]'));
        $result['CheckInDate'] = strtotime($http->FindSingleNode('(//*[.="through"])[1]/preceding-sibling::node()[1 and self::text()]'));
        $result['CheckOutDate'] = strtotime($http->FindSingleNode('(//*[.="through"])[1]/following-sibling::node()[1 and self::text()]'));
        $result['Fax'] = $http->FindSingleNode('(//p[@class="dim" and contains(text(), "Fax:")])[1]', null, true, '/Fax:(.+)/ims');
        $result['Phone'] = $http->FindSingleNode('(//p[@class="dim" and contains(text(), "Tel:")])[1]', null, true, '/Tel:(.+)/ims');
        // 1 Room / 1 Adult / No Child
        if (preg_match('|(\d+)\s*Room\s*/\s*(\d+)\s*Adults?\s*/\s*(\S+)\s*Child|ims', $http->FindSingleNode('(//div[@class="bottom_info"])[1]'), $matches)) {
            $result['Rooms'] = $matches[1];
            $result['Guests'] = $matches[2];
            $result['Kids'] = (stripos($matches[3], 'no') === false) ? $matches[3] : 0;
        }
        $result['RoomType'] = implode(', ', array_unique($http->FindNodes('//div[@class="roomTypeName"]')));
        $result['Rate'] = $http->FindSingleNode('//div[@class="charge_details"]//*[contains(text(), "Daily Rate")]/following-sibling::*[1]');
        $chargeData = $http->FindSingleNode('(//div[@class="charge_details"]//*[contains(text(), "Total Per Room")]/following-sibling::*[1])[1]');
        $chargeNumber = $chargeData ? $this->http->FindPreg("/([\d\,\.\-\s]+)/", false, $chargeData) : null;

        if (stristr($chargeData, 'points')) {
            $result['SpentAwards'] = $chargeNumber;
        } else {
            $result['Total'] = $chargeNumber;
            $result['Currency'] = $this->currency($chargeData);

            if ($result['Currency'] === 'VND') {
                $result['Total'] = PriceHelper::cost($result['Total'], '.', ',');
            } else {
                $result['Total'] = PriceHelper::cost($result['Total']);
            }

            if (!$result['Total']) {
                $this->sendNotification('goldpassport - check total');
            }
        }

        $result['GuestNames'] = array_map(function ($elem) {
            return beautifulName(trim(preg_replace("/\s*\[\[[^\]]*\]\]\s*/", "", $elem)));
        }, $http->FindNodes('(//div[@class="guest_info"])[1]/*[1]'));
        $result['AccountNumbers'] = implode(', ', $http->FindNodes('(//div[@class="guest_extra_info"])[1]/ul/li[contains(text(), "World of Hyatt #")]/following-sibling::*[1]'));

        return $result;
    }

    private function historyCodeToLabel($code)
    {
        $this->logger->notice(__METHOD__);
        $labels = [
            'CHRM2'      => 'Category 1-4 - Standard Award',
            'XFRPTS'     => 'Points Transfer',
            'PCRF'       => 'Held for Future - Partner Credit',
            'CHRM1'      => 'Standard Free Night Award',
            '5K02NC'     => 'Chase Credit Card Night Credits',
            'CHASE_ANIV' => 'Anniversary Free Night Award ',
            'TUPUSM'     => 'Tier Suite Upgrade Award',
            'UPUS2'      => 'Suite Upgrade Award',
            'NE05NC'     => 'Chase Credit Card Night Credits',
            'AA05NC'     => 'Chase Credit Card Night Credits',
            '20FRN'      => 'Category 1-7 Standard Award',
            'CHASE_FN'   => 'Free Night Award',
            'PBUPUR'     => 'Club Access Award',
            'GPMBONUS'   => 'Meeting or Event Bonus',
            'GR'         => 'Guest Relations Bonus',
            'CAT14RM365' => 'Category 1-4 Promotion Award',
            'SIGNVAR'    => 'Planner Signing Bonus',
            'TUPUS2'     => 'Tier Suite Upgrade Award',
            'hhhpfn'     => 'Free Night Award',
            'CAT17RM'    => 'Promotional Free Night Award',
            'CAT17RM365' => 'Promotional Free Night Award',
            'WHYSTL'     => 'Promotional Free Night Award',
            'QARVAR'     => 'Quality Assurance Bonus',
            'CAT14RM'    => 'Category 1-4 Promotion Award',
        ];

        return ArrayVal($labels, $code);
    }

    private function parseHistoryData($startDate, $pastActivity)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $pastActivities = $pastActivity['pastActivity'] ?? [];
        $this->logger->debug("Total " . ((is_array($pastActivities) || ($pastActivities instanceof Countable)) ? count($pastActivities) : 0) . " activity rows were found");
        $noTransactionDate = false;

        if (!$pastActivities) {
            $this->logger->error("no history found");

            return [];
        }

        foreach ($pastActivities as $activity) {
            $row = [];
            // Transaction Date
            $transactionDateStr = $this->http->FindPreg("/(.+)T?/", false, $activity['transaction']['date'] ?? '');

            if (!$transactionDateStr) {
                $this->logger->error('Skipping activity with no date');
                $noTransactionDate = true;

                continue;
            }
            $transactionDate = strtotime($transactionDateStr);

            if (isset($startDate) && $transactionDate < $startDate) {
                $this->logger->notice("break at date {$transactionDateStr} ($transactionDate)");
                $this->endHistory = true;

                break;
            }
            $row['Transaction Date'] = $transactionDate;
            // Check-out Date
            $checkOutDate = $this->http->FindPreg("/(.+)T?/", false, $activity['stay']['endDate'] ?? '');

            if (!empty($checkOutDate)) {
                $row['Check-out Date'] = strtotime($checkOutDate);
            }
            // Type and Description
            $transactionType = ArrayVal($activity['transaction'], 'category');
            $transactionSubType = ArrayVal($activity['transaction'], 'subCategory');
            $checkInDatePresent = true;
            $type = null;
            $description = null;

            switch ($transactionType) {
                case 'A':
                    $type = 'Points Redeemed';

                    if ($transactionSubType == 'FreeNight'
                        && ArrayVal($activity['transaction'], 'totalAmount') >= 0
                    ) {
                        $type = 'Free Night Award';
                        $checkInDatePresent = false;
                    }
                    $description = ArrayVal(ArrayVal($activity, 'hotelDetail'), 'name');

                    if ($description == '') {
                        $description = ArrayVal($activity['misc'], 'description');
                    }

                    break;

                case 'B':
                    $type = 'Bonus';
                    $actionCode = ArrayVal($activity['misc'], 'bonusCode');
                    $description = $this->historyCodeToLabel($actionCode) ?: 'Reward Bonus';

                    break;

                case 'F':
                    $type = 'Points earned';
                    $description = ArrayVal(ArrayVal($activity, 'hotelDetail'), 'name');

                    if ($description == '') {
                        $description = ArrayVal($activity['misc'], 'description');
                    }

                    break;

                case 'G':
                    $type = 'Gift';
                    $description = 'Gift';

                    break;

                case 'P':
                    $type = 'Point Purchase';
                    $description = 'Purchase';

                    break;

                case 'N':
                    $type = 'Other';

                    if ($transactionSubType == 'NonStay') {
                        $type = 'Points earned';
                    }
                    $description = ArrayVal(ArrayVal($activity, 'hotelDetail'), 'name');
                    $facilityName = ArrayVal($activity['misc'], 'facilityName');

                    if (!empty($facilityName)) {
                        $description .= " / " . $facilityName;
                    }

                    break;

                case 'O':
                    $type = 'Adjustment';
                    $description = ArrayVal(ArrayVal($activity, 'hotelDetail'), 'name');
                    $facilityName = ArrayVal($activity['misc'], 'facilityName');

                    if (!empty($facilityName)) {
                        $this->sendNotification("need to check history // RR");
                        $description .= " / " . $facilityName;
                    }

                    if ($description == '') {
                        $description = ArrayVal($activity['misc'], 'adjustmentDescription');
                    }

                    break;

                case 'V':
                    $type = 'Stay';

                    if ($transactionSubType == 'Stay') {
                        $type = 'Stay - Points earned';
                    }
                    $description = ArrayVal($activity['misc'], 'description');

                    break;

                case 'T':
                    $type = 'Gift';
                    $description = ArrayVal($activity['misc'], 'description');

                    break;

                default:
                    $this->sendNotification("Unknown transaction type was found: {$transactionType}");
                    $this->logger->debug(var_export($activity, true), ["pre" => true]);

                    break;
            }// switch ($transactionType)
            $row['Description'] = $description;
            $row['Type'] = $type;
            // Check-in Date
            if ($checkInDatePresent) {
                $checkIn = $this->http->FindPreg("/(.+)T/", false, ArrayVal($activity['stay'], 'startDate', ''));

                if ($checkIn) {
                    $row['Check-in Date'] = strtotime($checkIn);
                }
            }
            // Bonus and Points
            $totalPoints = ArrayVal($activity['transaction'], 'totalAmount', '');

            if ($type == 'Bonus') {
                $row['Bonus'] = $totalPoints;
            } else {
                $row['Points'] = $totalPoints;
            }
            $result[] = $row;
        }// foreach ($pastActivities as $activity)

        if ($noTransactionDate) {
            $this->sendNotification('check history items with no transaction date');
        }

        return $result;
    }
}
