<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

// refs #1408
TAccountCheckerExtended::requireTools();

require_once __DIR__ . '/../aviancataca/functions.php';

class TAccountCheckerTapportugal extends TAccountCheckerAviancataca
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $error = null;
    private $selenium = false;
    private $sc_userdata = null;
    private $currentItin = 0;
    private $errorRetrieve = null;
    private $authToken = null;
    // for properties
    private $tokenProperties = null;

    // ==== History ====
    private $endHistory = false;
    /**
     * @var mixed
     */
    private $accountStorage;
    /**
     * @var mixed
     */
    private $userData;
    private $stepItinerary = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        //$this->http->SetProxy($this->proxyReCaptcha());
        //$this->http->SetProxy($this->proxyDOP());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.flytap.com/en-us/my-account", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }
        $this->delay();

        return false;
    }

    public function LoadLoginForm()
    {

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false
            && !$this->http->FindPreg('/^(\d{7,})/', false, $this->AccountFields['Login'])) {
            throw new CheckException("The email or Customer Number (TP) you provided is not valid.",
                ACCOUNT_INVALID_PASSWORD);
        }

        $this->AccountFields['Login'] = preg_replace("/^TP\s*/ims", "", $this->AccountFields['Login']);

        $this->http->removeCookies();

        // funCaptcha workaround
        if ($this->attempt >= 0) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.flytap.com/en-us/");

            if ($this->http->Response['code'] == 403) {
                $this->setProxyGoProxies();
                $this->http->removeCookies();
                $this->http->GetURL("https://www.flytap.com/en-us/");
            }

            $this->http->RetryCount = 2;

            return $this->selenium();
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.flytap.com/en-us/");
        $this->http->RetryCount = 2;
        $this->delay();
        $this->distil();

        if ($this->http->FindSingleNode('//div[@id="distilIdentificationBlock"]/@id')) {
            $this->distil();
        }

        if (strstr($this->http->currentUrl(), 'https://book.flytap.com/r3air/TAPUS/Search.aspx')) {
            $this->http->GetURL("https://www.flytap.com/en-us/my-account");
            $this->delay();
            /*
            if (!$this->http->ParseForm("aspnetForm")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('ctl00$HeaderLoginPanel$LoginForm$EmailOrTPNumber', $this->AccountFields['Login']);
            $this->http->SetInputValue('ctl00$HeaderLoginPanel$LoginForm$PasswordOrPin', $this->AccountFields['Pass']);
            $this->http->SetInputValue('ctl00$HeaderLoginPanel$LoginForm$SignInButton', "Login");
            $this->http->SetInputValue('ctl00$PageContent$FlightScheduler$CurrentFlightType', "return");
            $this->http->SetInputValue('ctl00$PageContent$FlightScheduler$ReturnScheduler$AdditionalOptionsSelector$DirectFlights', "");
            $this->http->SetInputValue('ctl00$PageContent$FlightScheduler$OnewayScheduler$AdditionalOptionsSelector$DirectFlights', "");
            $this->http->SetInputValue('ctl00$PageContent$FlightScheduler$MulticityScheduler$AdditionalOptionsSelector$DirectFlights', "");
            if (isset($this->http->Form['ctl00$PageContent$FlightScheduler$ReturnScheduler$ReturnTripDatePicker$DatepickerFrom']))
                $this->http->SetInputValue('datepicker-from-value', $this->http->Form['ctl00$PageContent$FlightScheduler$ReturnScheduler$ReturnTripDatePicker$DatepickerFrom']);
            if (isset($this->http->Form['ctl00$PageContent$FlightScheduler$ReturnScheduler$ReturnTripDatePicker$DatepickerTo']))
                $this->http->SetInputValue('datepicker-to-value', $this->http->Form['ctl00$PageContent$FlightScheduler$ReturnScheduler$ReturnTripDatePicker$DatepickerTo']);
            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }

            return true;
            */
        }

        if (
            !$this->http->ParseForm("js-login-account-modal")
            && !$this->http->FindSingleNode('//input[@id = "GlobalRedirectLoginPageUrl"]/@value')
        ) {
            return $this->checkErrors();
        }

        $data = [
            'username' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
            'remember' => true,
        ];
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/json",
            "Accept"           => "*/*",
            "Cache-Control"    => "no-cache",
        ];

        if ($token = $this->http->FindSingleNode("//input[@name= '__RequestVerificationToken']/@value")) {
            $headers['RequestVerificationToken'] = $token;
        }
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.flytap.com/api/LoginAjax?sc_mark=US&sc_lang=en-US", json_encode($data), $headers, 60);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        if ($this->selenium) {
            return true;
        }

        $this->distil();
        $response = $this->http->JsonLog();

        if (isset($response->RedirectURL)) {
            $targetUrl = $response->RedirectURL;
            // TAP Account - Change password
            if (strstr($targetUrl, '/en-us/login/change-pin-to-password?clientNumber=')
                // Maybe your mail is not update, please confirm
                || strstr($targetUrl, '/en-us/login/pick-email-migration?clientNumber=')) {
                $this->throwProfileUpdateMessageException();
            }

            $this->http->NormalizeURL($targetUrl);
            $this->http->GetURL($targetUrl);
            $this->delay();

            if (($this->http->FindPreg('#/en-us/customer-area#', false, $this->http->currentUrl()) && $this->http->Response['code'] == 404)) {
                $this->http->GetURL('https://www.flytap.com/en-us/customer-area/my-profile');
            }
        }
        // Not a member + Complete your profile and enjoy even more benefits.
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Complete your profile and enjoy even more benefits.')]")
            && $this->http->FindNodes("//a[contains(text(), 'Upgrade to Victoria')]")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // TAP Account - Change password
        if ($this->http->FindSingleNode("//p[contains(text(), 'Here you can replace the PIN you have been using with a new password.')]")
            // Daniele Review your permissions from our communications channels in accordance with the new personal data privacy rules.
            || $this->http->FindSingleNode("//h1[contains(text(), 'Consent Reconfirmation')]")
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if (isset($response->Message)) {
            $this->logger->error("[Error]: '{$response->Message}'");
            // Sorry, but we are unable to validate the information provided at this time. Please try again later.
            if (
                $response->Message == 'Sorry, but we are unable to validate the information provided at this time. Please try again later.'
                || $response->Message == 'Sorry, the service is temporarily unavailable. Please try again later.'// AccountID: 5319864
                || $response->Message == 'We are sorry, but it is currently not possible to validate the information provided. Please try again later.'
            ) {
                throw new CheckException($response->Message, ACCOUNT_PROVIDER_ERROR);
            }
            // Maybe your mail is not update, please confirm
            if ($response->Message == 'Migrate customer to TAP Id') {
                $this->throwProfileUpdateMessageException();
            }

            if (
                // Your login data is incorrect. Have you forgotten your password?  Invalid attempts: ...
                strstr($response->Message, 'Your login data is incorrect.')
                || $response->Message == 'Incorrect PIN. Please check it, or contact us.'
                || $response->Message == 'Your login data is incorrect. Have you forgotten your password?'
                || $response->Message == 'Sorry, but this service is temporarily unavailable. Please try again later.'
                || $response->Message == 'To ensure your security, please log in with your password.'
                || $response->Message == 'To access your TAP Miles&Go Account, click “recover password” and enter the email address you used to register. Please contact us if you have forgotten your access details.'
                || $response->Message == 'If is your first login to the new website please use your client number (TP).'
                || $response->Message == "You logged in with a social network. So password recovery must be done on that platform."
                || $response->Message == "Your e-mail is associated with more than one TAP Miles&Go Account. For this reason you must use your Client Number (TP) and your password to login."
                || $response->Message == "The access data entered is incorrect. Please correct them and try again."
            ) {
                throw new CheckException($response->Message, ACCOUNT_INVALID_PASSWORD);
            }
            // Your PIN is blocked. Please contact us
            if ($response->Message == 'Your PIN is blocked. Please contact us.') {
                throw new CheckException($response->Message, ACCOUNT_LOCKOUT);
            }

            if ($response->Message == 'LockedUser') {
                throw new CheckException('Your account is locked', ACCOUNT_LOCKOUT);
            }

            if (
                strstr($response->Message, "Your account is temporarily locked.")
                || strstr($response->Message, "Your account is temporarily blocked.")
            ) {
                throw new CheckException($response->Message, ACCOUNT_LOCKOUT);
            }

            if (strstr($response->Message, 'To access your Victoria Account, click “recover password” and enter the email address you used to register.')) {
                throw new CheckException('Your account is locked', ACCOUNT_LOCKOUT);
            }

            if (false !== strpos($response->Message, 'Your email is associated with more than') && false !== strpos($response->Message, 'you must use your Client Number (TP) and your password to login')) {
                throw new CheckException('Your email is associated with more than one Victoria account. For this reason you must use your Client Number (TP) and your password to log in.', ACCOUNT_INVALID_PASSWORD);
            }
        }// if (isset($response->Message))

        if (in_array($this->AccountFields['Login'], [
            'matheusvieira_96@hotmail.com',
            'thehermanmak@gmail.com',
        ])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $data = $this->http->JsonLog($this->accountStorage);
        if (empty($data)) {
            return;
        }

        if (isset($data->state->busy) && $data->state->busy) {
            throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG);
        }

        $LoyaltyAccount = $data->state->loyaltyAccountDetails ?? $data->state->customerDetails->LoyaltyAccount;

        // Balance - Miles Balance
        $this->SetBalance($LoyaltyAccount->MemberMileageStatus->TotalMiles);
        // Client Number
        $this->SetProperty("AccountNumber", $data->state->customerDetails->CustLoyalty[0]->MembershipID);
        // Name
        $this->SetProperty("Name", beautifulName($data->state->customerDetails->FullName));

        // Status
        $this->SetProperty('Status', $LoyaltyAccount->MemberAccountInfo->LoyalLevel);
        // Status Miles
        $this->SetProperty('StatusMiles', $LoyaltyAccount->MemberMileageStatus->StatusMiles);

        $exp = null;
        foreach ($LoyaltyAccount->MemberMileageStatus->ExpiredMIles ?? [] as $item) {
            $date = $item->ExpirationDate;
            if (!isset($exp) && $date || $exp > strtotime($date)) {
                $exp = strtotime($date);
                $this->SetExpirationDate($exp);
                // Expiration Date
                $this->SetProperty("MilesToExpire", $item->Amount);
            }
        }

        return;
        $loggedID = $this->http->FindPreg("/loggedID':\s*'([^\']+)/ims");

        if (!$loggedID) {
            $sc_milesdata =
                $this->http->getCookieByName('sc_milesdata')
                ?? $this->http->getCookieByName('sc_milesdata', "www.flytap.com", "/", true)
            ;
            $sc_milesdata = $this->http->JsonLog(base64_decode($sc_milesdata), 3, true);
            $loggedID = ArrayVal($sc_milesdata, 'UserId');
        }

        if (!$loggedID) {
            return;
        }

        $this->sc_userdata =
            $this->http->getCookieByName('sc_userdata')
            ?? $this->http->getCookieByName('sc_userdata', "www.flytap.com", "/", true)
        ;
        $sc_userdata = $this->http->JsonLog(base64_decode($this->sc_userdata), 3, true);
        $token = ArrayVal($sc_userdata, 'Token');

        if (!$token) {
            $this->logger->error("token not found");

            return;
        }

        // Balance - Miles Balance
        $this->SetBalance(ArrayVal($sc_userdata, 'MilesBalance'));
        // Client Number
        $this->SetProperty("AccountNumber", ArrayVal($sc_userdata, 'TpNumber'));
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($sc_userdata, 'FullName')));

        // Status
        $this->SetProperty('Status', ArrayVal($sc_userdata, 'TierLevel'));
        // Status Miles
        $this->SetProperty('StatusMiles', ArrayVal($sc_userdata, 'StatusMilesBalance'));

        if (ArrayVal($sc_userdata, 'StatusMilesToExpire') > 0) {
            $this->sendNotification("Need to check StatusMilesToExpire // RR");
        }
        // Expiring Miles
        $milesToExpire = ArrayVal($sc_userdata, 'MilesToExpire');

        if ($milesToExpire > 0) {
            $this->SetProperty("MilesToExpire", $milesToExpire);
        }
        // Expiration Date
        $expireDate = ArrayVal($sc_userdata, 'MilesToExpireDate');

        if ($expireDate && $milesToExpire > 0) {
            $expireDate = $this->ModifyDateFormat($expireDate, "/", true);
            $this->SetExpirationDate(strtotime($expireDate));
        }

        $data = [
            "flyTapLogin" => "true",
            "customerId"  => $loggedID,
            "flyTapToken" => $token,
        ];

        $headers = [
            "Content-Type"     => "application/json",
            "Accept"           => "application/json, text/plain, */*",
            "Origin"           => "https://www.flytap.com",
        ];

        if (empty($this->tokenProperties) && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Request URL: https://booking.flytap.com/widget/main.js?v=7c5a5a4b-01d5-44b0-b330-a79e3d02313e
            $this->http->PostURL("https://booking.flytap.com/bfm/rest/session/create", '{"clientId":"-bqBinBiHz4Yg+87BN+PU3TaXUWyRrn1T/iV/LjxgeSA=","clientSecret":"DxKLkFeWzANc4JSIIarjoPSr6M+cXv1rcqWry2QV2Azr5EutGYR/oJ79IT3fMR+qM5H/RArvIPtyquvjHebM1Q==","referralId":"h7g+cmbKWJ3XmZajrMhyUpp9.cms35","market":"PT","language":"pt","userProfile":null,"appModule":"0"}', $headers);
            $response = $this->http->JsonLog();

            if (!isset($response->id)) {
                sleep(3);
                $this->logger->notice('retry'); // sometimes help
                $this->http->PostURL("https://booking.flytap.com/bfm/rest/session/create", '{"clientId":"-bqBinBiHz4Yg+87BN+PU3TaXUWyRrn1T/iV/LjxgeSA=","clientSecret":"DxKLkFeWzANc4JSIIarjoPSr6M+cXv1rcqWry2QV2Azr5EutGYR/oJ79IT3fMR+qM5H/RArvIPtyquvjHebM1Q==","referralId":"h7g+cmbKWJ3XmZajrMhyUpp9.cms35","market":"PT","language":"pt","userProfile":null,"appModule":"0"}', $headers);
                $response = $this->http->JsonLog();

                if (!isset($response->id)) {
                    $this->logger->notice('id not found');

                    return;
                }
            }
        }

        // digitalCustomer request and all related stuff was commented out because this data present in previous request
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $headers = [
                "Content-Type"     => "application/json",
                "Accept"           => "application/json, text/plain, */*",
                "Authorization"    => "Bearer " . ($this->tokenProperties ?? $response->id), //todo: evil's root
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://booking.flytap.com/bfm/rest/login/digitalCustomer", json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog(null, 3, true);
            // Name
            $data = ArrayVal($response, 'data');
            $userProfile = ArrayVal($data, 'userProfile');
            $this->SetProperty("Name", beautifulName(ArrayVal($userProfile, 'firstName') . " " . ArrayVal($userProfile, 'lastName')));
            // Client Number
            $loyaltyAccount = ArrayVal($userProfile, 'ffNumber');
            $this->SetProperty("AccountNumber", $loyaltyAccount);
            // Balance - Miles Balance
            $balance2 = ArrayVal($userProfile, 'currentPoints');
            $this->SetBalance($balance2);

            // strange provider bug workaround
            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && ($response === 'seumalandro')
            ) {
                throw new CheckRetryNeededException(2, 1);
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                $this->http->FindPreg("/\{\"status\":\"200\",\"errors\":\[\],\"timeService\":null,\"timeBackend\":null,\"data\":\{\"userProfile\":null,\"userPnrs\":\[\],\"translate\":null,\"userAddress\":null,\"defaultCurrency\":\"[^\"]+\"\}\}/")
                // AccountID: 3750421
                || $this->http->FindPreg("/\{\"status\":\"500\",\"errors\":\[\{\"code\":\"500\",\"desc\":\"Error fetching customer account:java\.lang\.NullPointerException\"\}\],\"timeService\":null,\"timeBackend\":null,\"data\":\{\"userProfile\":null,\"userPnrs\":\[\],\"translate\":null,\"userAddress\":null,\"defaultCurrency\":null\}\}/")
                || $this->http->FindPreg("/\{\"status\":\"500\",\"errors\":\[\{\"desc\":\"504 Gateway Time-out\"\}\],\"timeService\":null,\"timeBackend\":null,\"data\":\{\"userProfile\":null,\"userPnrs\":\[\],\"translate\":null,\"userAddress\":null,\"defaultCurrency\":null\}\}/")
                || $this->http->FindPreg("/\{\"status\":\"500\",\"errors\":\[\{\"code\":\"500\",\"desc\":\"Error fetching customer account:javax.xml.ws.WebServiceException: Could not receive Message.\"\}\],\"timeService\":null,\"timeBackend\":null,\"data\":\{\"userProfile\":null,\"userPnrs\":\[\],\"translate\":null,\"userAddress\":null,\"defaultCurrency\":null\\}}/")
                || $this->http->FindPreg("/\{\"status\":\"500\",\"errors\":\[\{\"code\":\"500\",\"desc\":\"Error fetching customer account:es.indra.bfm.clients.exeptions.RequestExecutionException: Error 100.TAP: E058 - Customer validation data does not match\"\}\],\"timeService\":null,\"timeBackend\":null,\"data\":\{\"userProfile\":null,\"userPnrs\":\[\],\"translate\":null,\"userAddress\":null,\"defaultCurrency\":null\}\}/")
                || $this->http->FindPreg("/\{\"status\":\"500\",\"errors\":\[\{\"desc\":\".u001/")
                || $this->http->FindPreg("/^0$/") !== null
            ) {
                $this->SetProperty("Name", beautifulName(ArrayVal($sc_userdata, 'FullName')));
                // Client Number
                $loyaltyAccount = ArrayVal($sc_userdata, 'TpNumber');
                $this->SetProperty("AccountNumber", $loyaltyAccount);
                // Balance - Miles Balance
                $this->SetBalance(ArrayVal($sc_userdata, 'MilesBalance'));
            }

            // AccountID: 5411545
            if (
                ArrayVal($sc_userdata, 'isLoyalty') === 'false'
                && ArrayVal($sc_userdata, 'TpNumber') === ''
                && !empty($this->Properties['Name'])
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            // AccountID: 5401479
            } elseif (
                ArrayVal($sc_userdata, 'isLoyalty') === 'true'
                && ArrayVal($sc_userdata, 'TpNumber') !== ''
                && ArrayVal($sc_userdata, 'FullName') !== ''
//                && empty($this->Properties['Name'])
                && ArrayVal($sc_userdata, 'MilesToExpire') === ''
                && ArrayVal($sc_userdata, 'StatusMilesToExpire') === ''
                && ArrayVal($sc_userdata, 'MilesBalance') === ''
                && ArrayVal($sc_userdata, 'StatusMilesBalance') === ''
            ) {
                $this->SetBalanceNA();
                // Name
                $this->SetProperty("Name", ArrayVal($sc_userdata, 'FullName'));
                // Client Number
                $loyaltyAccount = ArrayVal($sc_userdata, 'TpNumber');
                $this->SetProperty("AccountNumber", $loyaltyAccount);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    // refs #16040
    public function ParseItineraries()
    {
        $this->http->setHttp2(true);
        $data = $this->http->JsonLog($this->userData);
        if (empty($data)) {
            return [];
        }
        if ($this->http->FindPreg('/,"userPnrs":\[]/', false, $this->userData)) {
            $this->itinerariesMaster->setNoItineraries(true);
            return [];
        }

        $headers = [
            "Content-Type"     => "application/json",
            "Accept"           => "application/json, text/plain, */*",
            'Origin' => 'https://myb.flytap.com',
        ];
        $this->http->PostURL("https://myb.flytap.com/bfm/rest/session/create",
            '{"clientId":"-bqBinBiHz4Yg+87BN+PU3TaXUWyRrn1T/iV/LjxgeSA=","clientSecret":"DxKLkFeWzANc4JSIIarjoPSr6M+cXv1rcqWry2QV2Azr5EutGYR/oJ79IT3fMR+qM5H/RArvIPtyquvjHebM1Q==","referralId":"h7g+cmbKWJ3XmZajrMhyUpp9.cms35","market":"US","language":"en-us","userProfile":null,"appModule":"0"}', $headers);
        $response = $this->http->JsonLog();
        $userPnrs = $data->userPnrs ?? [];

        foreach ($userPnrs as $item) {
            //$this->http->GetURL("https://myb.flytap.com/my-bookings/details/$item->pnr/$item->lastname");
            $headers = [
                "Accept" => "application/json, text/plain, */*",
                'Content-Type' => 'application/json',
                'Origin' => 'https://myb.flytap.com',
                'Referer' => "https://myb.flytap.com/my-bookings/details/$item->pnr/$item->lastname",
                "Authorization" => "Bearer " . ($this->tokenProperties ?? $response->id)
            ];
            $data = [
                'lastName' => $item->lastname,
                'pnrNumber' => $item->pnr,
            ];
            $this->http->PostURL("https://myb.flytap.com/bfm/rest/booking/pnrs/search?skipAncillariesCatalogue=true",
                json_encode($data), $headers);
            if ($this->parseReservationFlytap_2() === false) {
                $this->itinerariesMaster->add()->flight(); //for broke result

                return [];
            }
        }


        return [];
        $this->http->setHttp2(true);
        //$this->setProxyMount();
        $result = [];
        $this->http->RetryCount = 0;

        $this->http->GetURL('https://myb.flytap.com/my-bookings', [], 20);

        if ($this->http->Response['code'] == 403 || strstr($this->http->Error, 'Network error ')) {
            $this->http->GetURL('https://myb.flytap.com/my-bookings', [], 40);

            // sometimes it helps
            if (strstr($this->http->Error, 'Network error')) {
                $this->http->GetURL('https://myb.flytap.com/my-bookings', [], 40);
            }
        }

        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//span/em[contains(text(),'You have no active reservations at this time.')]")) {
            return $this->noItinerariesArr();
        }

        $itinData = [];
        $nodes = $this->xpathQuery("//button[contains(text(),'Booking details')]/ancestor::app-booked-trip");
        $preIts = [];
        $checkSum = 0;

        foreach ($nodes as $node) {
            $lastName = $this->http->FindSingleNode('//span[contains(text(),"Reservation code")]/following-sibling::span', false, $fullName);
            $confNo = $this->http->FindSingleNode(".//div[@class='booked-code']/strong", $node);

            if (!empty($confNo)) {
                $data[] = [
                    'lastName'      => $lastName,
                    'pnrNumber'        => $confNo,
                ];


                $this->http->PostURL("https://myb.flytap.com/bfm/rest/booking/pnrs/search?skipAncillariesCatalogue=true");



                /*$it = $preIts[$confNo] ?? [];
                $seg = [];
                $flight = $this->http->FindSingleNode(".//div[contains(@class,'flight-id')]//strong", $node);
                $seg['AirlineName'] = $this->http->FindPreg("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/", false, $flight);
                $seg['FlightNumber'] = $this->http->FindPreg("/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", false, $flight);

                $date = strtotime(str_replace('/', '.',
                    $this->http->FindSingleNode(".//div[contains(@class,'flight-id')]//span", $node)));
                $depTime = $this->http->FindSingleNode(".//div[contains(@class,'flight-hours')]//div[contains(@class,'origin')]//strong", $node);

                if (!empty($depTime) && $date) {
                    $seg['DepDate'] = strtotime($depTime, $date);
                } else {
                    $seg['DepDate'] = false;
                }
                $arrTime = $this->http->FindSingleNode(".//div[contains(@class,'flight-hours')]//div[contains(@class,'destination')]//strong",
                    $node);

                if (!empty($arrTime) && $date) {
                    $seg['ArrDate'] = strtotime($arrTime, $date);
                } else {
                    $seg['ArrDate'] = false;
                }
                $seg['DepCode'] = $this->http->FindSingleNode(".//div[contains(@class,'flight-hours')]//div[contains(@class,'origin')]//span[last()]",
                    $node, false, "/\(([A-Z]{3})\)/");
                $seg['ArrCode'] = $this->http->FindSingleNode(".//div[contains(@class,'flight-hours')]//div[contains(@class,'destination')]//span[last()]",
                    $node, false, "/\(([A-Z]{3})\)/");
                $seg['Duration'] = $this->http->FindSingleNode(".//div[contains(@class,'flight-stops')]//strong", $node);
                $it['TripSegments'][] = $seg;

                $passengers = $this->http->FindNodes(".//div[contains(@class,'flight-passengers')]//strong", $node);

                if (isset($it['Passengers'])) {
                    $it['Passengers'] = array_merge($it['Passengers'], $passengers);
                } else {
                    $it['Passengers'] = $passengers;
                }
                $it['Passengers'] = array_map('beautifulName', array_unique($it['Passengers']));
                $preIts[$confNo] = $it;
                $this->collectFromPreParse($it, $confNo);*/
            }
        }

        /*
        $this->logger->debug('itinData:');
        $this->logger->debug(var_export([
            'itinData' => $itinData,
        ], true), ['pre' => true]);


        if (!empty($itinData)) {
             $result = $this->parseItinerariesFlytap($itinData, $preIts);
//         $result = $this->parseItinerariesAmadeus($itinData);
        }
        */

        return $result;
    }

    /*private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();
        $f = $this->itinerariesMaster->add()->flight();
        $this->logger->info(sprintf("[%s] Parse Itinerary #%s", $this->stepItinerary++, $response->data->pnr),
            ['Header' => 3]);
        $f->general()
            ->confirmation($response->data->pnr, "Reservation code", true);

        foreach ($response->data->infoPax->listPax ?? [] as $traveler) {
            $f->general()->traveller(beautifulName("$traveler->name $traveler->surname"));
        }
        foreach ($response->data->infoTicket->istTicket ?? [] as $ticket) {
            $f->issued()->ticket($ticket->ticket, false);
        }

        foreach ($data->data->air->bounds ?? [] as $bound) {

        }
    }*/

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking reference",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Last name",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    // TODO -  23533#note-3
    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.flytap.com/en-us/";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        /*$error = $this->CheckConfirmationNumberInternalFlytap($arFields, $it);
//        $error = $this->CheckConfirmationNumberInternalAmadeus($arFields, $it);
        if ($error) {
            return $error;
        }*/

        return null;
    }

    public function CheckConfirmationNumberInternalAmadeus($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $it = [];

        $this->http->GetURL('https://book.tp.amadeus.com/plnext/TAPATCDX/Override.action?SO_SITE_QUEUE_OFFICE_ID=LISTP08AA&SO_SITE_QUEUE_CATEGORY=0C0&SO_SITE_NEW_UI_ENABLED=TRUE&SO_SITE_RUI_MULTIDEV_ENABLED=TRUE&SO_SITE_RUI_TABLET_PG_LIST=ALL&SO_SITE_RUI_MOBILE_PG_LIST=ALL&SO_SITE_RESERV_RESPONSIVE=TRUE&SO_SITE_RUI_COLLAPSE_BOUND_T=THREE_STEPS&SO_SITE_RUI_UPSLL_T_MDL=TRUE&SO_SITE_RUI_UPSLL_T_MDL_ATC=TRUE&SO_SITE_RUI_UPSLL_HIDE_BTNS=TRUE&SO_SITE_RUI_DPICKER_NATIVE=TABLET%2CMOBILE&SO_SITE_RUI_SHOW_CAL_LINK=TRUE&SO_SITE_RC_CAL_DATE_RANGE=3&TRIP_FLOW=YES&EMBEDDED_TRANSACTION=GetPNRsList&DIRECT_RETRIEVE=TRUE&SO_SITE_ALLOW_DIRECT_RT=TRUE&SO_SITE_PNR_SERV_REQ_LOGIN=NO&SO_SITE_DISPL_SPECIAL_REQS=TRUE&SO_SITE_ALLOW_PNR_SERV=YES&SO_SITE_ALLOW_PNR_MODIF=Y&SO_SITE_ALLOW_TKT_PNR_MODIF=Y&SO_SITE_RT_SHOW_PRICES=TRUE&SO_SITE_ETKT_VIEW_ENABLED=TRUE&SO_SITE_RT_PRICE_FROM_TST=TRUE&SITE=E000ENEW&LANGUAGE=GB#/TLIST');

        $jsessionId = $this->http->FindPreg("/,\s*sessionId\s*:\s*\"([^\"]+)\"/");

        if (empty($jsessionId)) {
            $this->sendNotification("request with jsessionId changed// ZM");
        }
        $data = [
            'ACTION'                   => 'MODIFY',
            'DIRECT_RETRIEVE'          => 'true',
            'DIRECT_RETRIEVE_LASTNAME' => $arFields['LastName'],
            'REC_LOC'                  => $arFields['ConfNo'],
            'FF_LAST_NAME'             => $arFields['LastName'],
            'ORIGINAL_FF_LAST_NAME'    => $arFields['LastName'],
            'LAST_NAME'                => '',
            'ACCOUNT_NUMBER_0'         => '',
            'BOOL_CONFIRMATION'        => 'true',
            'TICKET_NUMBER'            => '',
            'COUNTRY_SITE'             => 'GB',
            'SITE'                     => 'E000ENEW',
            'LANGUAGE'                 => 'GB',
            'TRIP_FLOW'                => 'YES',
        ];
        $this->http->PostURL('https://book.tp.amadeus.com/plnext/TAPATCDX/RetrievePNR.action;jsessionid=' . $jsessionId, $data);
        $this->distil();
        $res = $this->ParseItineraryConfirmation($arFields, 'tapportugal');

        if (is_string($res)) {
            return $res;
        }

        if ($this->itinerariesMaster === null && empty($this->itinerariesMaster->getItineraries())) {
            $this->sendNotification('failed to retrieve itinerary by conf #');
        }

        return null;
    }

    // refs #16040
    public function CheckConfirmationNumberInternalFlytap($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->setProxyGoProxies();
        /*$this->http->GetURL($this->ConfirmationNumberURL($arFields), [], 20);

        if ($this->http->Response['code'] == 403) {
            $this->http->removeCookies();
            $this->http->GetURL($this->ConfirmationNumberURL($arFields), [], 20);
        }*/

        if (!$this->http->FindSingleNode("//title[contains(text(),'official website | TAP Air Portugal')]")) {
            $this->sendNotification("tapportugal - failed to retrieve itinerary by conf // MI");

            return null;
        }

        $data = ['formData' => ['ReservationCode' => $arFields['ConfNo'], 'LastName' => $arFields['LastName']]];
        $this->http->PostURL('https://www.flytap.com/toolbar/ChangeBooking', json_encode($data), [
            'Accept'           => '*/*',
            'Content-Type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $response = $this->http->JsonLog();

        if (isset($response->Status) && isset($response->Url)) {
            $this->http->GetURL($response->Url);

            if ($fileMain = $this->http->FindPreg("/<script type=\"text\/javascript\" src=\"(main\.\w+\.js)\">/")) {
                $this->http->GetURL("https://myb.flytap.com/{$fileMain}");

                if ($this->http->Response['code'] == 200) {
                    $mainJS = $this->http->Response['body'];
                }
            }

            if (isset($mainJS)) {
                $itinData[] = [
                    'ConfNo'        => $arFields['ConfNo'],
                    'DepartureDate' => null,
                    'LastName'      => $arFields['LastName'],
                ];
                $this->parseItinerariesFlytap($itinData, [], $mainJS, $response->Url);

                if (is_string($this->errorRetrieve)) {
                    return $this->errorRetrieve;
                }

                return null;
            }
            $res = $this->ParseItineraryConfirmation($arFields, 'tapportugal');

            if (!is_array($res)) {
                return $res;
            }
            $it = $res;
        }

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            'Date'        => 'PostingDate',
            'Description' => 'Description',
            'Miles'       => 'Miles',
            'Balance'     => 'MilesBalance',
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $query = http_build_query([
            'sc_mark'              => 'EN',
            'sc_lang'              => 'en',
            'pageNumber'           => '1',
            'itemId'               => 'a2f326e3-f828-46b3-b024-015a865f7be3',
            'transactionType'      => '0',
            'transactionStartDate' => date("d/m/Y", strtotime('-1 year')),
            'transactionEndDate'   => date("d/m/Y"),
            '_'                    => microtime(),
        ]);
        $this->increaseTimeLimit();
        $this->http->GetURL("https://www.flytap.com/VictoriaProgramme/UpdateMovementsTable?" . $query, [
            'Accept'           => '*/*',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate));

        usort($result, function ($a, $b) { return $b['Date'] - $a['Date']; });

        $this->getTime($startTimer);

        return $result;
    }

    private function delay()
    {
        $delay = rand(3, 10);
        $this->logger->debug("Delay -> {$delay}");
        sleep($delay);
    }

    private function loginSuccessful()
    {
        // Access is allowed
        if (
            $this->http->FindNodes('//h2[contains(text(),"Customer Area")]')
            || $this->http->FindNodes("//input[contains(@onclick, 'LogoutButton')]/@onclick") // aspnetform
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Server Error in '/' Application.
        if (
        $this->http->FindPreg("/(Server Error in \'\/\' Application\.)/ims")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function distil($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $referer = $this->http->currentUrl();
        $distilLink = $this->http->FindPreg('/meta http-equiv="refresh" content="\d+;\s*url=(.+?)"/ui');

        if (isset($distilLink)) {
            sleep(2);
            $this->http->NormalizeURL($distilLink);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($distilLink);
            $this->http->RetryCount = 2;
        }// if (isset($distil))

        if (!$this->http->ParseForm('distilCaptchaForm')) {
            $this->logger->debug("distil false");

            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;
        $captcha = $this->parseFunCaptcha($retry);

        if ($captcha !== false) {
            $this->http->FormURL = $formURL;
            $this->http->Form = $form;
            $this->http->SetInputValue('fc-token', $captcha);
        } elseif ($this->http->FindSingleNode('//form[@id = "distilCaptchaForm" and @class="geetest_easy"]/@class')) {
            if (!$this->parseGeetestCaptcha()) {
                return false;
            }
        } else {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        $this->getTime($startTimer);

        return true;
    }

    private function parseGeetestCaptcha()
    {
        $this->logger->notice(__METHOD__);
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
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
        $request = $this->http->JsonLog($captcha, 3, true);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
            $request = $this->http->JsonLog($captcha, 3, true);
        }

        if (empty($request)) {
            $this->logger->error("geetest failed = true");

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->http->NormalizeURL($verifyUrl);
        $payload = [
            'ticket'            => $ticket,
            'geetest_challenge' => $request['geetest_challenge'],
            'geetest_validate'  => $request['geetest_validate'],
            'geetest_seccode'   => $request['geetest_seccode'],
        ];
        $this->http->PostURL($verifyUrl, $payload);

        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $login = false;
        $retry = false;
        $selenium = clone $this;
        $this->selenium = true;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if (!isset($this->State["Resolution"])) {
                $resolutions = [
                    [1152, 864],
                    [1280, 720],
                    [1280, 768],
                    [1280, 800],
                    [1360, 768],
                    [1920, 1080],
                ];
                $this->State["Resolution"] = $resolutions[array_rand($resolutions)];
            }

            $selenium->setScreenResolution($this->State["Resolution"]);

            if ($this->attempt == 2) {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
            } elseif ($this->attempt == 1) {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);

                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if ($fingerprint !== null) {
                    $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $selenium->http->setUserAgent($fingerprint->getUseragent());
                    $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
                }

                $selenium->seleniumOptions->addAntiCaptchaExtension = true;
                $selenium->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
            } else {
                $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
//                $selenium->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);
                $selenium->setKeepProfile(true);
            }

            /*
            $selenium->disableImages();
            $selenium->useCache();
            */
            $selenium->usePacFile(false);

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL('https://www.flytap.com/en-us/my-account');
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $selenium->driver->executeScript('window.stop();');
                $selenium->saveResponse();
            }

            /*
            $captchaIssues = '//a[
                contains(text(), "Could not connect to proxy related to the task")
                or contains(text(), "Proxy IP is banned by target service")
                or contains(text(), "Could not connect to proxy related to the task")
                or contains(text(), "Captcha could not be solved")
                or contains(text(), "Proxy login and password are incorrect")
            ]';
            $res = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "login-user-account"]
            | //*[self::h2 or self::h1][contains(text(), "Pardon Our Interruption")]'), 60);

            if (!$res && $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0)) {
                $res = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "login-user-account"] | //*[self::h2 or self::h1][contains(text(), "Pardon Our Interruption")]'), 60);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            try {
                $this->savePageToLogs($selenium);

                if (!$res && ($iframe = $selenium->waitForElement(WebDriverBy::xpath("//iframe[@id = 'main-iframe']"), 0))) {
                    $selenium->driver->switchTo()->frame($iframe);
                    $this->savePageToLogs($selenium);
                }

                if ($selenium->waitForElement(WebDriverBy::xpath($captchaIssues), 0)) {
                    $retry = true;
                }
            } catch (UnknownServerException $e) {
                $this->logger->error("[Exception]: {$e->getMessage()}");
            }
            // save page to logs
            $this->savePageToLogs($selenium);
            */
            if ($cookieAccept = $selenium->waitForElement(WebDriverBy::xpath('//button[@id="onetrust-accept-btn-handler"]'), 5)) {
                $this->savePageToLogs($selenium);
                $cookieAccept->click();

                $selenium->waitFor(function () use ($selenium) {
                    $this->logger->warning("accept cookies wait...");
                    sleep(1);
                    $this->savePageToLogs($selenium);

                    return !$selenium->waitForElement(WebDriverBy::xpath('//button[@id="onetrust-accept-btn-handler"]'), 0);
                }, 10);

                $this->savePageToLogs($selenium);
                if ($loginPopup = $selenium->waitForElement(WebDriverBy::xpath('//div[span[normalize-space()="person"]]'), 0)) {
                    $loginPopup->click();
                    sleep(1);
                    $this->savePageToLogs($selenium);
                }
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 5);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@type="password"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput && $passwordInput) {
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 5);
            }

            if (!$loginInput || !$passwordInput) {
                $this->logger->notice("[Current URL]: {$selenium->http->currentUrl()}");

                if ($this->http->FindSingleNode("
                        //span[contains(text(), 'This site can’t be reached')]
                        | //h1[contains(text(), 'Access Denied')]
                        | //h1[contains(text(), 'The proxy server is refusing connections')]
                        | //div[@class = 'error-description' and contains(text(), 'www.flytap.com Additional security check is required')]
                    ")
                ) {
                    $retry = true;
                }

                return false;
            }

            if ($cookieAccept = $selenium->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 0)) {
                $cookieAccept->click();
                sleep(1);
                $this->savePageToLogs($selenium);
            }

            if ($this->attempt == 2) {
                $loginInput->sendKeys($this->AccountFields['Login']);
                $passwordInput->sendKeys($this->AccountFields['Pass']);
            } else {
                $mover = new MouseMover($selenium->driver);
                $mover->logger = $this->logger;
                $mover->duration = rand(300, 500);
                $mover->steps = rand(10, 20);

                try {
                    $mover->moveToElement($loginInput);
                    $this->increaseTimeLimit(300);
                    $mover->click();
                    $this->increaseTimeLimit(300);
                    $mover->sendKeys($loginInput, $this->AccountFields['Login'], rand(5, 7));
                } catch (StaleElementReferenceException | \Facebook\WebDriver\Exception\StaleElementReferenceException | NoSuchElementException | \Facebook\WebDriver\Exception\NoSuchElementException $e) {
                    $this->logger->error(get_class($e) . " on login field: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

                    if (strstr($selenium->http->currentUrl(), 'https://www.flytap.com/en-us/support')) {
                        $selenium->http->GetURL('https://www.flytap.com/en-us/my-account');
                    }
                    $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 10);

                    if (!$loginInput) {
                        $this->savePageToLogs($selenium);

                        return false;
                    }
                    $mover->moveToElement($loginInput);
                    $loginInput->clear();
                    $mover->sendKeys($loginInput, $this->AccountFields['Login'], rand(5, 7));
                }

                try {
                    $mover->moveToElement($passwordInput);
                    $this->increaseTimeLimit(300);
                    $mover->click();
                    $this->increaseTimeLimit(300);
                    $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], rand(5, 7));
                } catch (StaleElementReferenceException | \Facebook\WebDriver\Exception\StaleElementReferenceException | NoSuchElementException | \Facebook\WebDriver\Exception\NoSuchElementException $e) {
                    $this->logger->error(get_class($e) . " on password field: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

                    if (strstr($selenium->http->currentUrl(), 'https://www.flytap.com/en-us/support')) {
                        $selenium->http->GetURL('https://www.flytap.com/en-us/my-account');
                        $loginInput = $selenium->waitForElement(WebDriverBy::id('login-user-account'), 10);
                        $this->savePageToLogs($selenium);

                        if (!$loginInput) {
                            return false;
                        }
                        $mover->moveToElement($loginInput);
                        $mover->sendKeys($loginInput, $this->AccountFields['Login'], rand(5, 7));
                    }
                    $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@type="password"]'), 0);

                    if (!$passwordInput) {
                        $this->savePageToLogs($selenium);

                        return false;
                    }
                    $mover->moveToElement($passwordInput);
                    $passwordInput->clear();
                    $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], rand(5, 7));
                }
            }

            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(., "Login")]'), 3);

            if (!$button) {
                $this->savePageToLogs($selenium);

                return false;
            }

            try {
                $this->logger->debug("click by btn");
                $button->click();
            } catch (UnrecognizedExceptionException $e) {
                $this->logger->error("[ElementClickInterceptedException]: {$e->getMessage()}");
                $this->savePageToLogs($selenium);
            } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                $this->logger->error("[ElementClickInterceptedException]: {$e->getMessage()}");
                $this->savePageToLogs($selenium);
//                $selenium->driver->executeScript("try { document.querySelector('').click() } catch (e) {}");
            }

            $this->logger->debug("waitung results...");

            $startTime = time();
            $time = time() - $startTime;
            $sleep = 60;

            $xpathSuccess = '
                //div[@class = "user-name"]
                | //div[@class = "profile-client-name"]
                | //div[@role = "main"]//div[@class = "profile-client-name"]
                | //*[contains(text(), "Logout")]
                | //a[@class = "js-profile-name"]
                | //li[@role="status"]//div[contains(@class, "text") and contains(text(), \'Welcome to TAP\')]
            ';

            $xpathError = "
                //div[@class = 'half-area' or contains(@class, 'hightlight-message')]//li[@class = 'error-item']
                | //h1[contains(text(), 'Consent Reconfirmation')]
                | //li[@role=\"status\"]//div[contains(@class, \"text\") and not(contains(text(), 'Welcome to TAP'))]
            ";

            $clickOneMoreTime = false;

            while ($time < $sleep && !$login) {
                $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");

                if (
                    $selenium->waitForElement(WebDriverBy::xpath($xpathSuccess), 0)
                    || $selenium->waitForElement(WebDriverBy::xpath($xpathSuccess), 0, false)
                ) {
                    $login = true;
                    break;
                }

                if ($message = $selenium->waitForElement(WebDriverBy::xpath($xpathError), 0)
                ) {
                    $error = $this->http->FindPreg("/^[\w.]*\:\:?\s*([^<]+)/ims", false, $message->getText());

                    if (!$error) {
                        $error = $message->getText();
                    }
                    $this->error = $error;

                    break;
                }
                // TAP Account - Change password
                if (strstr($selenium->http->currentUrl(), '/en-us/login/change-pin-to-password?clientNumber=')
                    // Maybe your mail is not update, please confirm
                    || strstr($selenium->http->currentUrl(), '/en-us/login/pick-email-migration?clientNumber=')) {
                    $this->error = "website is asking you to update your profile";

                    break;
                }

                if ($time > 10 && $clickOneMoreTime === false) {
                    $clickOneMoreTime = true;
                    $button = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'login-save-account-submit']"), 0);
                    $this->savePageToLogs($selenium);

                    if ($button) {
                        try {
                            $button->click();
                        } catch (
                            UnrecognizedExceptionException
                            | Facebook\WebDriver\Exception\ElementClickInterceptedException
                            $e
                        ) {
                            $this->logger->error("Exception: "); // . $e->getMessage()
                        } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                            $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
                            sleep(3);
                            $this->savePageToLogs($selenium);

                            if ($button = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'login-save-account-submit']"), 0)) {
                                $button->click();
                            }
                        }
                    }
                }

                // save page to logs
                $this->savePageToLogs($selenium);
                $time = time() - $startTime;
            }

            if ($login && empty($this->error)) {
                $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
                $xpathSuccess = str_replace('
                | //li[@role="status"]//div[contains(@class, "text") and contains(text(), \'Welcome to TAP\')]', ' | //span[contains(text(), "Profile date")]/span', $xpathSuccess); // wait loadiing props in sessionStorage

                $selenium->http->GetURL('https://www.flytap.com/en-us/my-account');
                $selenium->waitForElement(WebDriverBy::xpath($xpathSuccess), 10); // wait loadiing props in sessionStorage
                $this->savePageToLogs($selenium);
                $login = true;

                $this->tokenProperties = $this->http->FindPreg("/\"([^\"]+)/", false, $selenium->driver->executeScript("return sessionStorage.getItem('token');"));
                $this->logger->info("[Form token]: " . $this->tokenProperties);
                $this->accountStorage = $selenium->driver->executeScript('return sessionStorage.getItem("account-storage")');
                $this->logger->debug("[account-storage]: $this->accountStorage");

                // provider bug workaround, it helps
                if (
                    $this->accountStorage == '{"state":{"busy":false,"refresh":false},"version":0}'
                    || $this->accountStorage == '{"state":{"busy":busy,"refresh":false},"version":0}'
                ) {
                    $selenium->http->GetURL('https://www.flytap.com/en-us/my-account');
                    $selenium->waitForElement(WebDriverBy::xpath($xpathSuccess), 10); // wait loadiing props in sessionStorage
                    $this->savePageToLogs($selenium);
                    $this->accountStorage = $selenium->driver->executeScript('return sessionStorage.getItem("account-storage")');
                    $this->logger->debug("[account-storage]: $this->accountStorage");
                }

                if ($this->ParseIts) {
                    $selenium->http->GetURL('https://myb.flytap.com/my-bookings');
                    sleep(7);
                    $this->userData = $selenium->driver->executeScript('return sessionStorage.getItem("userData")');
                    $this->logger->debug("[userData]: $this->userData");
                }

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
            // hard code (AccountID: 2891187)
            elseif ($this->AccountFields['Login'] == '403775352') {
                $this->error = "website is asking you to update your profile";
            } elseif ($selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'loader-wrapping')]"), 0)) {
                $retry = true;
            } elseif (
                empty($this->error)
                && !$selenium->waitForElement(WebDriverBy::xpath('//div[@id = "loading"]'), 0)
                && ($selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'login-save-account-submit']"), 0))
            ) {
                $retry = true;
            }
            $this->logger->debug("[Current Selenium URL]: {$selenium->http->currentUrl()}");
            // save page to logs
            $this->savePageToLogs($selenium);
        } catch (
            NoSuchDriverException
            | WebDriverCurlException
            | TimeOutException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'Element not found in the cache')) {
                $retry = true;
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                if (in_array($this->AccountFields['Login'], [
                    "sdpjr.sp@gmail.com",
                    "rafaelsousadossantos34@gmail.com",
                    'matheusvieira_96@hotmail.com',
                    'jo.josebenedito@gmail.com',
                    'ju.jumariagoes@gmail.com',
                    'ro.rosceliarodrigues@gmail.com',
                    'kvertongen@exabyte.be',
                    'thehermanmak@gmail.com',
                    '524422765',
                ])) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                throw new CheckRetryNeededException(3, 0);
            }
        }
        $this->getTime($startTimer);

        if (!is_null($this->error)) {
            $this->logger->error("[Error]: '{$this->error}'");

            if (
                // Maybe your mail is not update, please confirm
                $this->error == "website is asking you to update your profile"
                // Daniele Review your permissions from our communications channels in accordance with the new personal data privacy rules.
                || trim($this->error) == 'Consent Reconfirmation'
            ) {
                $this->throwProfileUpdateMessageException();
            }
            // Your login data is incorrect.
            if (strstr($this->error, "Your login data is incorrect.")
                // To ensure your security, please log in with your password.
                || strstr($this->error, "To ensure your security, please log in with your password.")
                // Your Account Was Deleted
                || strstr($this->error, "Your Account Was Deleted")
                // Incorrect PIN. Please check it, or contact us.
                || strstr($this->error, "Incorrect PIN. Please check it, or contact us.")
                // Please enter at least 4 characters.
                || strstr($this->error, "Please enter at least 4 characters.")
                || strstr($this->error, "E-mail or customer number: Invalid data.")
                || strstr($this->error, "Enter your email or Client (TP) Number: Invalid data.")
                || stristr($this->error, "Your e-mail is associated with more than one TAP Miles&Go Account")
                || strstr($this->error, "You logged in with a social network. So password recovery must be done on that platform.")
                || $this->error == "The access data entered is incorrect. Please correct them and try again."
                || $this->error == "Enter your email or Customer (TP) Number: Invalid data."
                || strstr($this->error, "An error occurred while logging in. Please check your details and try again")
            ) {
                throw new CheckException($this->error, ACCOUNT_INVALID_PASSWORD);
            }
            // E-mail or customer number:: Invalid data.
            if (strstr($this->error, "E-mail or customer number:: Invalid data.")) {
                throw new CheckException("Customer number (TP) or email: Invalid data.", ACCOUNT_INVALID_PASSWORD);
            }
            // Your account is temporarily locked. Please retrieve your password.
            if ($this->error == "LockedUser") {
                throw new CheckException("Your account is temporarily locked. Please retrieve your password.", ACCOUNT_LOCKOUT);
            }
            // Your PIN is blocked.
            if (
                strstr($this->error, "Your PIN is blocked.")
                || strstr($this->error, "Your account is temporarily locked. Please retrieve your password.")
                || strstr($this->error, "Your account is temporarily blocked. Please, recover your password.")
                || strstr($this->error, "To access your TAP Miles&Go Account, click “recover password” and enter the email address you used to register. Please contact us if you have forgotten your access details.")
                || strstr($this->error, "Your Account is locked")
            ) {
                throw new CheckException($this->error, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($this->error, "Sorry, but this service is temporarily unavailable. Please try again later.")
                // Sorry, but we are unable to validate the information provided at this time. Please try again later.
                || strstr($this->error, "Sorry, but we are unable to validate the information provided at this time. Please try again later.")
                || strstr($this->error, "Due to a cyber attack, which TAP has blocked, the website and the app are registering some instability")
                || $this->error == 'We are sorry, but it is currently not possible to validate the information provided. Please try again later.'
                || strstr($this->error, "The website and the app are registering some instability. Without login, online bookings and check-in are functioning properly and miles can be claimed later.")
            ) {
                throw new CheckException($this->error, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $this->error;
        }

        if (!$login) {
            // AccountID: 5627669
            if ($this->AccountFields['Login'] == 'robert.scheib@gmail.com') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return $login;
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm']//div[@class = 'g-recaptcha']/@data-sitekey");
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
        $this->increaseTimeLimit(300);

//        $postData = array_merge(
//            [
//                "type"             => "FunCaptchaTask",
//                "websiteURL"       => $this->http->currentUrl(),
//                "websitePublicKey" => $key,
//            ],
//            $this->getCaptchaProxy()
//        );
//        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $recognizer->RecognizeTimeout = 120;
//        $captcha = $this->recognizeAntiCaptcha($recognizer, $postData, $retry);

        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        $this->getTime($startTimer);

        return $captcha;
    }

    private function xpathQuery($query, $parent = null)
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent);
        $this->logger->info(sprintf('found %s nodes: %s', $res->length, $query));

        return $res;
    }

    private function getAuthToken(?string $url = "https://myb.flytap.com/my-bookings", $mainJS = null)
    {
        $this->logger->notice(__METHOD__);

        if (isset($this->authToken)) {
            return $this->authToken;
        }

        if (null === $mainJS) {
            $this->http->GetURL($url, ['Referer' => 'https://www.flytap.com/']);

            if ($fileMain = $this->http->FindPreg("/<script type=\"text\/javascript\" src=\"(main\.\w+\.js)\">/")) {
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://myb.flytap.com/{$fileMain}");
                $this->http->RetryCount = 2;

                if ($this->http->Response['code'] == 200) {
                    $mainJS = $this->http->Response['body'];
                } else {
                    return false;
                }
            }
        }

        if (preg_match('/,\s*(.)\s*=\s*"(?<market>[^"]+)"\s*,\s*(\w)=\s*"(?<language>[^"]+)"\s*,\s*\w\s*=\s*\{\s*clientId\s*:\s*"(?<clientId>[^"]+)"\s*,\s*clientSecret\s*:\s*"(?<clientSecret>[^"]+)"\s*,\s*referralId\s*:\s*"(?<referralId>[^"]+)"\s*,\s*market\s*:\s*\1\s*,\s*language\s*:\s*\3\s*,\s*userProfile\s*:\s*null\s*,\s*appModule\s*:\s*"0"\s*\}/',
            $mainJS, $m)) {
            Cache::getInstance()->set('tapportugal_mainjs_data', $m, 60 * 60 * 24);
            $this->logger->debug(var_export($m, true));
        } else {
            $m = Cache::getInstance()->get('tapportugal_mainjs_data');

            if (empty($m)) {
                $this->sendNotification("can't get main.js // ZM");

                return null;
            }
        }
        $postData = [
            "clientId"     => $m['clientId'],
            "clientSecret" => $m['clientSecret'],
            "referralId"   => $m['referralId'],
            "market"       => $m['market'],
            "language"     => $m['language'],
            "userProfile"  => null,
            "appModule"    => "0",
        ];
        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/json",
            "Origin"          => "https://myb.flytap.com",
            "Referer"         => $url,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://myb.flytap.com/bfm/rest/session/create", json_encode($postData), $headers);
        $this->http->RetryCount = 2;

        if (
            $this->http->Response['code'] == 502
            || strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
        ) {
            sleep(2);
            $this->logger->notice('retry'); // sometimes help
            $this->increaseTimeLimit();
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://myb.flytap.com/bfm/rest/session/create", json_encode($postData), $headers);
            $this->http->RetryCount = 2;
        }

        if ($this->http->Response['code'] == 403) {
            return false;
        }
        $response = $this->http->JsonLog();

        if (null === $response) {
            return null;
        }

        if (isset($response->id)) {
            return $response->id;
        }

        return null;
    }

    /*
    private function parseItinerariesAmadeus($itinData)
    {
        $this->logger->notice(__METHOD__);
        $res = [];

        $confSet = [];

        foreach ($itinData as $i => $data) {
            if ($i > 5) {
                $this->logger->info('Time limit increased');
                $this->increaseTimeLimit();
            }
            $conf = $data['ConfNo'];

            if (isset($confSet[$conf])) {
                continue;
            }
            $confSet[$conf] = true;
            $arFields = [
                'ConfNo'   => $conf,
                'LastName' => $data['LastName'],
            ];
            $it = [];
            $this->CheckConfirmationNumberInternal($arFields, $it);

            if (ArrayVal($it, 'RecordLocator')) {
                $res[] = $it;
            }
        }

        return $res;
    }*/

    private function parseItinerariesFlytap($itinData, array $preParseData, ?string $mainJS = null, $url = null)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $confSet = [];
        $confSetPre = [];

        $firstReservation = $itinData[0];

        if (isset($this->sc_userdata)) {
            $sc_userdata = base64_decode($this->sc_userdata);
            $sc_userdata = $this->http->JsonLog($sc_userdata);
        } else {
            $this->logger->error("sc_userdata empty");
        }
        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/json",
            "Origin"          => "https://myb.flytap.com",
            "Referer"         => "https://myb.flytap.com/my-bookings/details/{$firstReservation['ConfNo']}/{$firstReservation['LastName']}",
        ];

        if (isset($sc_userdata)) {
            $linkForMainJS =
            $headers["Referer"] = "https://myb.flytap.com/my-bookings/details?pnr={$firstReservation['ConfNo']}&lastname={$firstReservation['LastName']}&source=flytap&market=US&language=en-US&tapid={$sc_userdata->UserId}&email={$sc_userdata->Email}";
        }

        $authToken = $this->getAuthToken($url, $mainJS);

        if (!$authToken) {
            if (isset($linkForMainJS)) {
                $authToken = $this->getAuthToken($linkForMainJS, $mainJS);
            } else {
                $authToken = $this->getAuthToken("https://myb.flytap.com/my-bookings/details/{$firstReservation['ConfNo']}/{$firstReservation['LastName']}",
                    $mainJS);
            }
            //$this->sendNotification("retry response code: {$this->http->Response['code']} // MI");
        }

        if (!$authToken) {
            return $result;
        }

        if (isset($sc_userdata)) {
            $referer = "https://myb.flytap.com/my-bookings/details?pnr=-CONF-NO-&lastname=-LAST-NAME-&source=flytap&market=US&language=en-US&tapid={$sc_userdata->UserId}&email={$sc_userdata->Email}";
        } else {
            $referer = "https://myb.flytap.com/my-bookings/details/-CONF-NO-/-LAST-NAME-";
        }

        foreach ($itinData as $i => $itinDatum) {
            $conf = $itinDatum['ConfNo'];

            if (isset($confSet[$conf])) {
                continue;
            }
            $this->increaseTimeLimit(100);
            $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$conf}", ['Header' => 3]);
            $this->currentItin++;
            $confSet[$conf] = true;

            if (isset($authToken)) {
                $headers += [
                    "Authorization" => "Bearer {$authToken}",
                ];
                $data = [
                    "lastName"  => $itinDatum['LastName'],
                    "pnrNumber" => $conf,
                ];
                $headers['Referer'] = str_replace(['-LAST-NAME-', '-CONF-NO-'], [$data['lastName'], $data['pnrNumber']], $referer);
                $this->http->PostURL("https://myb.flytap.com/bfm/rest/booking/pnrs/search?skipAncillariesCatalogue=true", json_encode($data), $headers);

                if ($this->http->FindPreg('/\{"status":"500","errors":\[\{"code":"500","desc":"For input string:/')
                    || $this->http->FindPreg('/\{"status":"500","errors":\[\{"code":"500"/')) {
                    $this->logger->error('Skip: The reservation may be far in the future, because of this the error "Booking not found!"');

                    continue;
                }

                if ($this->http->FindPreg('/"status":"400","errors":/')) {
                    sleep(3);
                    $this->http->PostURL("https://myb.flytap.com/bfm/rest/booking/pnrs/search?skipAncillariesCatalogue=true", json_encode($data), $headers);
                }

                if ($this->http->FindPreg('/1930 - NO MATCH FOR RECORD LOCATOR - NAME/') && isset($itinDatum['FullName'])) {
                    $this->logger->error('Retrying with a different last name');
                    // hardCode
                    $newFullName = preg_replace("/^([A-Z]{3,})(MRS|MR|MSTR|MISS) /", '$1 $2 ', $itinDatum['FullName']);

                    if ($newFullName === $itinDatum['FullName']) {
                        // CESAR AGUSTOMR SEIGUER MILDER JR
                        $itinDatum['FullName'] = preg_replace("/^(\w+\s+[A-Z]{3,})(MRS|MR|MSTR|MISS) /", '$1 $2 ', $itinDatum['FullName']);
                    } else {
                        $itinDatum['FullName'] = $newFullName;
                    }

                    $lastName = (
                    $this->http->FindPreg('/\b(?:MR|MRS|MSTR|MISS)\s+(.+)$/i', false, $itinDatum['FullName'])
                        ?: $this->http->FindPreg('/\b((?:DE|DOS|DA|DO)\s+.+)$/i', false, $itinDatum['FullName'])
                        ?: $this->http->FindPreg('/(\w+\s+\w+)\s*$/i', false, $itinDatum['FullName'])
                    );
                    $data = [
                        "lastName"  => $lastName,
                        "pnrNumber" => $conf,
                    ];
                    $headers['Referer'] = str_replace(['-LAST-NAME-', '-CONF-NO-'], [$data['lastName'], $data['pnrNumber']], $referer);
                    $this->http->PostURL("https://myb.flytap.com/bfm/rest/booking/pnrs/search?skipAncillariesCatalogue=true", json_encode($data), $headers);
                }

                if ($this->http->FindPreg('/1930 - NO MATCH FOR RECORD LOCATOR - NAME/') && isset($itinDatum['FullName'])) {
                    $lastName = (
                    $this->http->FindPreg('/^.+\s+(\w+\s+(?:DE|DOS|DA|DO)\s+.+)$/iu', false, $itinDatum['FullName'])
                        ?: $this->http->FindPreg('/(\w+\s+\w+)\s*$/i', false, $itinDatum['FullName'])
                    );

                    if (!empty($lastName)) {
                        $this->logger->error('Retrying with a different last name');
                        $data = [
                            "lastName"  => $lastName,
                            "pnrNumber" => $conf,
                        ];
                        $headers['Referer'] = str_replace(['-LAST-NAME-', '-CONF-NO-'], [$data['lastName'], $data['pnrNumber']], $referer);
                        $this->http->PostURL("https://myb.flytap.com/bfm/rest/booking/pnrs/search?skipAncillariesCatalogue=true",
                            json_encode($data), $headers);
                    }
                }

                if ($this->http->FindPreg('/1930 - NO MATCH FOR RECORD LOCATOR - NAME/') && isset($itinDatum['FullName'])) {
                    $lastName = (
                    $this->http->FindPreg('/^.+\s+(\w+\s+\w+\s+(?:DE|DOS|DA|DO)\s+.+)$/iu', false, $itinDatum['FullName'])
                        ?: $this->http->FindPreg('/(\w+\s+\w+)\s*$/i', false, $itinDatum['FullName'])
                    );

                    if (!empty($lastName)) {
                        $this->logger->error('Retrying with a different last name');
                        $data = [
                            "lastName"  => $lastName,
                            "pnrNumber" => $conf,
                        ];
                        $headers['Referer'] = str_replace(['-LAST-NAME-', '-CONF-NO-'], [$data['lastName'], $data['pnrNumber']], $referer);
                        $this->http->PostURL("https://myb.flytap.com/bfm/rest/booking/pnrs/search?skipAncillariesCatalogue=true",
                            json_encode($data), $headers);
                    }
                }

                if ($this->http->FindPreg('/1930 - NO MATCH FOR RECORD LOCATOR - NAME/') && isset($itinDatum['FullName'])) {
                    $lastName = $this->http->FindPreg('/\w+\s+(\w+\s+\w+\s+\w+)\s*$/i', false, $itinDatum['FullName']);

                    if (!empty($lastName)) {
                        $this->logger->error('Retrying with a different last name');
                        $data = [
                            "lastName"  => $lastName,
                            "pnrNumber" => $conf,
                        ];
                        $headers['Referer'] = str_replace(['-LAST-NAME-', '-CONF-NO-'], [$data['lastName'], $data['pnrNumber']], $referer);
                        $this->http->PostURL("https://myb.flytap.com/bfm/rest/booking/pnrs/search?skipAncillariesCatalogue=true",
                            json_encode($data), $headers);
                    }
                }

                if ($this->http->Response['code'] != 200
                    || strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
                ) {
                    $this->logger->info('some API error');
//                    $this->go2retrieve($conf, $data['lastName']); // uncomment if retrieve throw amadeus
                    // collect from main page
                    if (isset($preParseData[$conf])) {
                        $confSetPre[$conf] = true;
                        $this->collectFromPreParse($preParseData[$conf], $conf);
                    }
                } else {
                    $cntParsed = count($this->itinerariesMaster->getItineraries());

                    if ($this->parseReservationFlytap_2() === false) {
                        $this->itinerariesMaster->add()->flight(); //for broke result

                        return [];
                    }

                    /*
                    if ($cntParsed === count($this->itinerariesMaster->getItineraries())) {
//                        $this->go2retrieve($conf, $data['lastName']); // uncomment if retrieve throw amadeus
                    }
                    */

                    if ($cntParsed === count($this->itinerariesMaster->getItineraries())) {
                        // collect from main page
                        if (isset($preParseData[$conf])) {
                            $confSetPre[$conf] = true;
                            $this->collectFromPreParse($preParseData[$conf], $conf);
                        }
                    }
                }

                if ($i > 10) {
                    $this->logger->info('Time limit increased');
                    $this->increaseTimeLimit();
                }
            } else {
//                $this->parseReservationFlytap_1($itinDatum, $i, $result); // теперь на сайте в итоге выход на через skipAncillariesCatalogue
            }
        }

        if ($confSetPre == $confSet && count($this->itinerariesMaster->getItineraries()) > 2) {
            $this->sendNotification("looks like detail parsing is broken // ZM");
        }

//        if(!empty($result))
//            $result = uniteAirSegments($result);
        return $result;
    }

    private function collectFromPreParse(array $preParseData, string $conf)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->flight();
        $r->general()
            ->confirmation($conf, 'Reservation code')
            ->travellers($preParseData['Passengers'], true);

        foreach ($preParseData['TripSegments'] as $seg) {
            $s = $r->addSegment();
            $s->departure()
                ->code($seg['DepCode'])
                ->date($seg['DepDate']);
            $s->arrival()
                ->code($seg['ArrCode'])
                ->date($seg['ArrDate']);
            $s->airline()
                ->name($seg['AirlineName'])
                ->number($seg['FlightNumber']);
            $s->extra()->duration($seg['Duration']);
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function go2retrieve($conf, $lastname)
    {
        // try throw retrieve
        $arFields = [
            'ConfNo'   => $conf,
            'LastName' => $lastname,
        ];
        $res = [];
        $this->CheckConfirmationNumberInternalAmadeus($arFields, $res);
    }

    private function parseReservationFlytap_1($data, $i, &$result)
    {
        $this->logger->notice(__METHOD__);
        $conf = $data['ConfNo'];
        $paramDetail = [
            'PNR'           => $conf,
            'DepartureDate' => $data['DepartureDate'],
        ];
        $this->http->PostURL('https://www.flytap.com/api/Reservations/SeeDetail?sc_mark=US&sc_lang=en-US', $paramDetail, [
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $this->http->GetURL('https://www.flytap.com/en-us/customer-area/my-bookings/booking');
        $this->distil();

        $link = $this->http->FindSingleNode(" //a[(contains(@href,'e-travel.com') or contains(@href,'my-bookings/details')) and contains(.,'Manage reservation')]/@href");

        if (!$link) {
            $link = $this->http->FindSingleNode("//a[contains(@href, 'book.tp.amadeus.com') and contains(., 'Manage reservation')]/@href");
        }

        if ($link) {
            $this->http->GetURL($link);

            if (empty($this->http->Response['body'])) {
                $this->http->GetURL($link);
            }

            if ($i > 5) {
                $this->logger->info('Time limit increased');
                $this->increaseTimeLimit();
            }
            $res = $this->ParseItineraryConfirmation([], 'tapportugal');

            if (!empty($res) && is_array($res)) {
                $result[] = $res;
            }
        }
    }

    private function parseReservationFlytap_2()
    {
        $this->logger->notice(__METHOD__);
        //   link to fare total, taxes
        //   https://myb.flytap.com/bfm/rest/booking/pnrs/LKNXF9/fares/breakdown
        $response = $this->http->JsonLog(null, 1, true, "data");

        if (isset($response['status']) && $response['status'] == '400'
            && isset($response['ok']) && $response['ok'] == true
            && isset($response['errors']) && isset($response['errors'][0]['desc'])
            && (stripos($response['errors'][0]['desc'], 'NO MATCH FOR RECORD LOCATOR - NAME') !== false
                || stripos($response['errors'][0]['desc'], '100030 - Input data invalid') !== false
                || stripos($response['errors'][0]['desc'], '15623 - RESTRICTED ON OPERATING PNR') !== false
                || stripos($response['errors'][0]['desc'], 'Invalid last name format') !== false
                || stripos($response['errors'][0]['desc'], '284 - SECURED PNR') !== false)
        ) {
            if (
                stripos($response['errors'][0]['desc'], 'NO MATCH FOR RECORD LOCATOR - NAME') !== false
                && ArrayVal($this->AccountFields, 'Login')
            ) {
                $this->sendNotification('check no match for record locator // ZM');
            }
            $this->logger->debug('Booking not found!');
            $this->errorRetrieve = 'Booking not found!';

            return true;
        }

        if (isset($response['status']) && $response['status'] == '400'
            && isset($response['ok']) && $response['ok'] == true
            && isset($response['errors']) && isset($response['errors'][0]['desc'])
            && (stripos($response['errors'][0]['desc'], 'An error occurred while parsing') !== false
                || stripos($response['errors'][0]['desc'], '55 - IGNORE AND RE-ENTER') !== false)
        ) {
            return false; //false for break parsing
        }

        if (!isset($response['data']['pnr'])) {
            if (isset($response['status']) && $response['status'] == '400') {
                $this->sendNotification("other format Json - parseReservationFlytap_2 // MI");
            }

            return false;
        }

        $this->logger->error($response['errors'][0]['desc'] ?? null);

        if (isset($response['status']) && $response['status'] == '423'
            && (stripos($response['errors'][0]['desc'], 'Not present or wrong verification code') !== false)
        ) {
            $this->logger->error("Skip: " . $response['errors'][0]['desc']);

            return true; //false for break parsing
        }

        $f = $this->itinerariesMaster->add()->flight();
        $conf = $response['data']['pnr'];
        $f->general()->confirmation($conf);

        foreach ($response['data']['infoTicket']['listTicket'] as $ticket) {
            $f->issued()->ticket($ticket['ticket'], false);
        }
        $passengers = [];

        foreach ($response['data']['infoPax']['listPax'] as $pax) {
            $passengers[] = beautifulName($pax['name'] . ' ' . $pax['surname']);
        }
        $f->general()->travellers($passengers, true);

        if (!empty($response['data']['fare']['flightPrice']['totalPrice']['currency'])
            && (!empty($total['price']) || !empty($response['data']['fare']['flightPrice']['totalPoints']))
        ) {
            $total = $response['data']['fare']['flightPrice']['totalPrice'];
            $f->price()
                ->total($total['price'])
                ->cost($total['basePrice'])
                ->tax($total['tax'])
                ->currency($total['currency']);

            if (!empty($response['data']['fare']['flightPrice']['totalPoints']['price'])) {
                // не отображается на сайте
                //$f->price()->spentAwards($response['data']['fare']['flightPrice']['totalPoints']['price']);
            }
        }

        if (isset($response['data']['fare']['listOutbound'])
            && is_array($response['data']['fare']['listOutbound'])
            && empty($response['data']['fare']['listOutbound'])
        ) {
            $this->itinerariesMaster->removeItinerary($f);
            $this->logger->debug('skip reservation. no info');

            return true;
        }
        $fields = ['listOutbound', 'inbound'];

        foreach ($fields as $field) {
            if (isset($response['data']['fare'][$field])) {
                if (isset($response['data']['fare'][$field]['idFlight'])) {
                    $list = [$response['data']['fare'][$field]];
                } else {
                    $list = $response['data']['fare'][$field];
                }

                foreach ($list as $l) {
                    foreach ($l['listSegment'] as $segment) {
                        if ($segment['status'][0] === '22') {
                            if (count($f->getSegments()) < 2) {
                                $this->sendNotification("check skipped segment // ZM");
                            }

                            continue; // skip reservation. duplicate(previous stops)
                        }

                        if (trim($segment['equipment']) === 'TRN') {
                            // train segment
                            if (!isset($train)) {
                                $train = $this->itinerariesMaster->add()->train();
                                $train->general()->confirmation($conf);

                                if (!empty($passengers)) {
                                    $train->general()->travellers($passengers, true);
                                }
                            }
                            $s = $train->addSegment();
                            $s->extra()->service($segment['operationCarrier']);

                            if (!empty($segment['flightNumber'])) {
                                $s->extra()->number($segment['flightNumber']);
                            } else {
                                $s->extra()->noNumber();
                            }
                        } else {
                            // flight segment
                            $s = $f->addSegment();

                            if (isset($segment['flightNumber'])) {
                                $s->airline()
                                    ->number($segment['flightNumber'])
                                    ->name($segment['carrier'])
                                    ->operator($segment['operationCarrier']);
                            }

                            if (isset($segment['equipment'])) {
                                $min = $segment['duration'] % 60;
                                $hours = round(($segment['duration'] - $min) / 60);
                                $duration = (($hours > 0) ? $hours . 'h ' : '') . $min . 'min';
                                $s->extra()
                                    ->aircraft(trim($segment['equipment']), true)
                                    ->duration($duration);
                            }
                            $s->departure()
                                ->terminal($segment['departureTerminal'], false, true);
                            $s->arrival()
                                ->terminal($segment['arrivalTerminal'], false, true);
                        }

                        if (isset($segment['departureAirport'])) {
                            $s->departure()
                                ->code($segment['departureAirport'])
                                ->date(strtotime($segment['departureDate']));
                        }

                        if (isset($segment['arrivalAirport'])) {
                            $s->arrival()
                                ->code($segment['arrivalAirport'])
                                ->date(strtotime($segment['arrivalDate']));
                        }
                        $s->extra()
                            ->cabin($segment['cabin']);

                        if (isset($segment['cabinMeal']) && !empty($segment['cabinMeal'])) {
                            $this->sendNotification("not empty cabinMeal // ZM");
                        }

                        if (isset($segment['status']) && is_array($segment['status'])) {
                            if (in_array($segment['status'][0], ['16', '21', '41', '31'])) {
                                $s->extra()
                                    ->status('cancelled')
                                    ->cancelled();
                            }

                            if (in_array($segment['status'][0], ['80'])) {
                                $s->extra()
                                    ->status('flown');
                            }
                            // 17, 32 - one flight, 39, 46 - with stop (2+ flights), 21 - cancelled alone, 16 - cancelled with stops, 41 - cancelled 10,18 - round trip, 80 - flown, 31 - cancelled in pare with 21
                            if (!in_array($segment['status'][0], ['10', '16', '17', '18', '19', '21', '39', '41', '46', '27', '24', '80', '31', '32', '34']) && !is_null($segment['status'][0])) {
                                $this->sendNotification("check status {$segment['status'][0]} //ZM");
                            }
                        }

                        // Seats
                        if (isset($response['data']['ancillaries']['seat']['journey'])) {
                            $seats = [];

                            foreach ($response['data']['ancillaries']['seat']['journey'] as $journey) {
                                if ($l['idFlight'] == $journey['flightId'] && $segment['idInfoSegment'] == $journey['segmentId']) {
                                    foreach ($journey['passengers'] as $passenger) {
                                        foreach ($passenger['ancillaryInfo'] as $info) {
                                            if (isset($info['seatCode'])) {
                                                $seats[] = $info['seatCode'];
                                            }
                                        }
                                    }
                                }
                            }

                            if (!empty($seats)) {
                                $s->extra()->seats(array_unique($seats));
                            }
                        }
                    }
                }
            }
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        if (isset($train)) {
            $this->logger->debug('Parsed Itinerary (train):');
            $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);
        }

        return true;
    }

    private function ParseHistoryPage($startIndex, $startDate)
    {
        $result = [];
        $rows = $this->http->XPath->query("//table[@class='desktop-only']//tr[contains(@class,'movements-pagination-index')]");
        $this->logger->debug("Total {$rows->length} transactions were found");

        foreach ($rows as $row) {
            $date = $this->ModifyDateFormat($this->http->FindSingleNode("td[@class='date']", $row, false, '#\d+/\d+/\d{4}#'));
            $description = $this->http->FindSingleNode("td[@class='description']", $row);
            $miles = $this->http->FindSingleNode("td[contains(@class,'miles')]", $row, false, '/[+\d\.\,\-]+/');
            $balance = $this->http->FindSingleNode("td[@class='balance']", $row, false, '/[+\d\.\,\-]+/');

            $dateTime = strtotime($date, false);

            if (isset($startDate) && $dateTime < $startDate) {
                $this->logger->notice("break at date {$dateTime} ({$date})");
                $this->endHistory = true;

                return $result;
            }

            $result[] = [
                'Date'        => $dateTime,
                'Description' => $description,
                'Miles'       => $miles,
                'Balance'     => $balance,
            ];
        }

        return $result;
    }
}
