<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\ItineraryArrays\Hotel;

class TAccountCheckerOmnihotels extends TAccountChecker
{
    use PriceTools;
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://bookings.omnihotels.com/membersarea/overview';

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->useFirefoxPlaywright();

        /*
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        */
        $this->http->SetProxy($this->proxyDOP());
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
    }

    /*
    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }
    */

    public function LoadLoginForm()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $url = "https://bookings.omnihotels.com/login";

        if ($this->attempt > 0) {
            $url = "https://ssl.omnihotels.com/Omni?Phoenix_state=clear&pagedst=SI&loginName=" . urlencode($this->AccountFields['Login']);
        }

        $this->http->GetURL($url);

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "email"]'), 15);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "submit_btn"]'), 0);
        $this->driver->executeScript('var c = document.getElementById("onetrust-accept-btn-handler"); if (c) c.click();');
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            return false;
        }

        $this->driver->executeScript("document.querySelector('button[id = \"submit_btn\"]').scrollIntoView({block: \"end\"});");
        $this->driver->executeScript("document.querySelector('button[id = \"submit_btn\"]').scrollIntoView({block: \"end\"});");
//        $login->sendKeys($this->AccountFields['Login']);
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->sendKeys($login, $this->AccountFields['Login'], 5);
        $pass->sendKeys($this->AccountFields['Pass']);

        $this->saveResponse();
        $btn->click();

        /*
        if (!$this->http->ParseForm("login-form")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        */

//        $captcha = $this->parseCaptcha();
//
//        if ($captcha) {
//            $this->http->SetInputValue('g-recaptcha-response', $captcha);
//        }

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, \"logout\")] | //p[contains(text(), \"Select Guest Member No\")] | //p[@class = 'help-block']"), 15);
        $this->saveResponse();
        /*
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm([], 180)) {
            if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Something went wrong.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;
        */
        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // Please check to make sure you entered the correct password.
        if ($message = $this->http->FindSingleNode("//p[@class = 'help-block']")) {
            $this->logger->error("[Error]: " . $message);

            if (
                $message == 'Please use your email address to login to your account'
                || $message == 'These credentials do not match our records.'
                || strstr($message, 'Profile Not Found. Please contact Member Services')
                || strstr($message, 'Too many failed login attempts')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'There is an issue with your account. Please try logging in later or contact our membership services team')
                || strstr($message, 'There is an issue with your account. Please contact our membership services team a')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message = $this->http->FindSingleNode("//span[@id = 'error_msg']"))

        if ($this->http->FindSingleNode('//h3[contains(text(), "Update your Username")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if (
            $this->AccountFields['Login'] == 'glenn.roper@gmail.com'
            && $this->http->FindPreg("/<title>403<\/title>403 Forbidden/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are elevating the Select Guest experience. Your account information will be available once our upgrade is complete.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//h2[contains(@class, 'greetings')]"));
        // Level
        $this->SetProperty("Level", $this->http->FindSingleNode("//h2[contains(@class, 'tier-level-name')]"));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//p[contains(text(), 'Member Since')]", null, false, '/Member Since\s+(.+)/'));
        // Select Guest Member No
        $this->SetProperty("Number",
            $this->http->FindSingleNode("//p[contains(text(), 'Select Guest Member #')]", null, false, '/#(.+)/')
            ?? $this->http->FindSingleNode("//div[contains(@class, 'header-desktop')]//span[contains(text(), 'Member Number')]/following-sibling::span")
        );
        // Spend $1000 more by Dec 31, 2024 to achieve Insider Status through 2025.
        $this->SetProperty("SpendNextLevel", $this->http->FindSingleNode("//p[contains(., 'Spend') and contains(., 'to achieve') and contains(., ' Status through')]/span[1]"));
        // Spend $1000 more by Dec 31, 2024 to achieve Insider Status through 2025.
        $this->SetProperty("CreditsFreeNight", $this->http->FindSingleNode("//p[contains(., 'more Omni Credits to earn a free night stay in any Omni Hotel or Resort!')]/span[1]"));
        // Status Valid Through
        $this->SetProperty("StatusValidThrough", $this->http->FindSingleNode("//p[contains(text(), '* Valid through')]", null, false, '/Valid through\s+(.+)/'));
        // Balance - Free Nights
        $this->SetBalance($this->http->FindSingleNode('//div[@class = "redeem-free-nights-content"]/p/span'));

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
            && !empty($this->Properties['Number'])
            && !empty($this->Properties['MemberSince'])
        ) {
            $this->SetBalance(0);
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && ($message = $this->http->FindSingleNode('//h2[contains(text(), "Something went wrong.")]'))
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $credits = $this->http->FindSingleNode('//div[@class="credit-balance"]//div[@class="points-bg"]/span');

        if (isset($credits)) {
            $this->AddSubAccount([
                'Code'           => 'OmniCreditBalance',
                'DisplayName'    => 'Omni Credit Balance',
                'Balance'        => $credits,
            ]);
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://ssl.omnihotels.com/sg?pagesrc=SG6&pagedst=SG6";

        return $arg;
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://bookings.omnihotels.com/membersarea/reservations');

        if ($this->http->FindSingleNode("//p[contains(text(),'You have no upcoming reservations')]")) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        $nodes = $this->http->XPath->query("//div[contains(@class,'profile-content-box reservation-card')]//form[button[contains(text(),'view details')]]");
        $this->logger->debug("Found: $nodes->length itineraries");

        $datas = [];

        foreach ($nodes as $node) {
            $datas[] = [
                '_token'             => $this->http->FindSingleNode("./input[@name='_token']/@value", $node),
                'lastNameOnBooking'  => $this->http->FindSingleNode("./input[@name='lastNameOnBooking']/@value", $node),
                'confirmationNumber' => $this->http->FindSingleNode("./input[@name='confirmationNumber']/@value", $node),
            ];
        }

        foreach ($datas as $data) {
            $this->http->PostURL('https://bookings.omnihotels.com/retrieveSubmit?showCancelButton=show', $data);
            $this->parseItineraryV3();
        }

        // Cancelled
        $nodes = $this->http->XPath->query("//h2[contains(text(),'Cancelled Reservations')]/following-sibling::tr/td/div/div[contains(@class,'desktop-grid')]");
        $this->logger->debug("Found: $nodes->length cancelled itineraries");

        foreach ($nodes as $node) {
            $h = $this->itinerariesMaster->createHotel();
            $h->general()->cancelled();
            $h->general()->confirmation($this->http->FindSingleNode(".//p[contains(text(),'Confirmation:')]", $node, false, '/Confirmation:\s*(.+)/'));
            $h->hotel()->name($this->http->FindSingleNode(".//h2", $node));
        }

        return [];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://bookings.omnihotels.com/retrieve";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("retrieve_form")) {
            return null;
        }
        $this->http->SetInputValue('lastNameOnBooking', $arFields['LastName']);
        $this->http->SetInputValue('confirmationNumber', $arFields['ConfNo']);

        if (!$this->http->PostForm()) {
            return null;
        }

        if ($msg = $this->http->FindSingleNode('(//ul[@class = "errors-list retrieve-form-error-message"]/li)[1]')) {
            return $msg;
        }

        $this->parseItineraryV3();

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Arrival"      => "PostingDate",
            "Departure"    => "Info",
            "Confirmation" => "Info",
            "Hotel/Resort" => "Description",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->http->FilterHTML = false;
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->http->GetURL('https://ssl.omnihotels.com/sg?pagesrc=SG6&pagedst=SG6_2');
        $startIndex = sizeof($result);
        $this->saveResponse();
        $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    private function parseItineraryV3()
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->createHotel();
        // confirmation number
        $conf = $this->http->FindSingleNode("//span[contains(@class,'confirmation-number--id')]");
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $conf), ['Header' => 3]);

        $h->general()->confirmation($conf);
        // hotel name
        $hotelName = $this->http->FindSingleNode("//div[contains(@class,'confirmation-hotel')]/h2");
        $h->hotel()->name($hotelName);
        // address
        $address = $this->http->FindSingleNode("//div[contains(@class,'confirmation-hotel')]//div[contains(@class,'confirmation-hotel-links')]/a[1]");
        $h->hotel()->address($address);
        // phone
        $phone = $this->http->FindSingleNode("//div[contains(@class,'confirmation-hotel')]//div[contains(@class,'confirmation-hotel-links')]/a[contains(@href,'tel:')]");
        $h->hotel()->phone($phone);
        // check-in date
        $checkInDate = strtotime($this->http->FindSingleNode("//div[contains(@class,'booking-data')]/p[contains(text(),'Arrival Date')]/following-sibling::p"));
        $checkInDateTime = $this->http->FindSingleNode("//div[contains(@class,'booking-data')]/p[contains(text(),'Check-In')]/following-sibling::p", null, false, '/After (\d+:\d+\s*[AP]M)/');
        $h->booked()->checkIn(strtotime($checkInDateTime, $checkInDate));

        // check-out date
        $nights = $this->http->FindSingleNode("//div[contains(@class,'booking-data')]/p[contains(text(),'Nights')]/following-sibling::p", null, false, '/^(\d+)/');
        $checkOutDateTime = $this->http->FindSingleNode("//div[contains(@class,'booking-data')]/p[contains(text(),'Check-Out')]/following-sibling::p", null, false, '/Before (\d+:\d+\s*[AP]M)/');
        $checkOutDate = strtotime("+$nights day", $checkInDate);
        $h->booked()->checkOut(strtotime($checkOutDateTime, $checkOutDate));

        $guests = $this->http->FindSingleNode("//div[contains(@class,'booking-data')]/p[contains(text(),'Guests')]/following-sibling::p");
        $h->booked()->guests($this->http->FindPreg('/(\d+) adult/', false, $guests));
        $h->booked()->kids($this->http->FindPreg('/(\d+) child/', false, $guests), false, true);

        $rooms = $this->http->FindSingleNode("//div[contains(@class,'booking-data')]/p[contains(text(),'Rooms')]/following-sibling::p",
            null, false, '/^(\d+)/');
        $h->booked()->rooms($rooms);

        $account = $this->http->FindSingleNode("//p[contains(text(),'Your Select Guest number is')]", null, false,
            '/number is (\d+)/');

        if ($account) {
            $h->program()->account($account, false);
        }

        $tax = $this->http->FindSingleNode("//div[contains(@class,'booking-data')]/p[text()='Taxes']/following-sibling::p", null, false, '/([\d.,\s]+)/');

        if ($tax) {
            $h->price()->tax(PriceHelper::cost($tax));
        }

        $total = $this->http->FindSingleNode("//h2[contains(@id,'grand-total-price')]");
        // $681.96 USD
        if (preg_match('/([\d.,\s]+)\s*([A-Z]{3})/', $total, $m)) {
            $h->price()->total(PriceHelper::cost($m[1]));
            $h->price()->currency($m[2]);
        }

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindNodes('//a[contains(@href, "logout")] | //p[contains(text(), "Select Guest Member No")]')
            && !$this->http->FindSingleNode("//span[@id = 'error_msg']")
        ) {
            return true;
        }

        return false;
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("(//div[@class = 'g-recaptcha']/@data-sitekey)[1]");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function ParseHistoryPage($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//table[contains(@id, 'past_stays_table')]/tbody/tr[td]");
        $this->logger->debug("Found {$nodes->length} items");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $dateStr = $this->http->FindSingleNode("td[2]", $node);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Arrival'] = $postDate;
            $result[$startIndex]["Hotel/ Resort Name"] = $this->http->FindSingleNode("td[1]", $node);
            $result[$startIndex]["Departure"] = $this->http->FindSingleNode("td[3]", $node);
            $result[$startIndex]['Confirmation'] = $this->http->FindSingleNode("td[4]", $node);
            $startIndex++;
        }

        return $result;
    }
}
