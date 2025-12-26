<?php

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\WeekTranslate;

class TAccountCheckerScandichotels extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    private $currentItin = 0;

    private $endHistory = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        /*
        if (!is_numeric($this->AccountFields['Login'])) {
            throw new CheckException('Invalid membership number', ACCOUNT_INVALID_PASSWORD);
        }
        */

        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        /*
                $this->http->GetURL("https://www.scandichotels.com");
                $this->sendSensorData();
        */

        $this->selenium();

        $this->http->PostURL("https://www.scandichotels.com/ajax/startpage/checkuserauthentication", []);
        $response = $this->http->JsonLog(null, 0);
        $this->http->SetBody($response->outUserStateHtml ?? null);
        $this->http->SaveResponse();

        if ($jsLogin = $this->http->FindSingleNode("//div[@id='js-login-modal']/@data-js-login")) {
            $this->http->GetURL($jsLogin);
        }

        if (!$this->http->ParseForm('authenticate-login-form')) {
            return $this->checkErrors();
        }

        $this->http->FormURL = "https://login.scandichotels.com/authn/authenticate/scandic";
        $this->http->SetInputValue('userName', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('keep-me-logged-in', "on");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            // An error occurred while processing your request.
            $this->http->FindPreg("/An error occurred while processing your request\./")
            || ($this->http->Response['code'] == 500 && $this->http->FindSingleNode("//h3[contains(text(), 'An error occurred. Please try again later.')]"))
            || $this->http->FindSingleNode("//h2[contains(text(), '502 - Web server received an invalid response while acting as a gateway or proxy server.')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - Zero size object')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - DNS failure')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re currently updating our platform until")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
            if ($this->http->FindSingleNode('//h1[contains(text(), "Set new password")]')) {
                $this->throwProfileUpdateMessageException();
            }

            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode("//text()[contains(., 'Something went wrong. Please check your username and password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//text()[contains(., 'Enter a membership number or your email address registered on your account.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Something went wrong. Please check your username and password.
        if ($message = $this->http->FindSingleNode('//div[
                contains(text(), "Something went wrong. Please check your username and password.")
                or contains(text(), "We have upgraded the security for Scandic Friends, therefore you need to set a new password in order to be able to login to your account.")
                or contains(text(), "Please check your username and password. Tip! If you\'re trying to log in with your email for the first time, you need to login first with your membership number and activate your email.")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]')) {
            $this->logger->error("[Error]: {$message}");

            return false;
        }

        if ($this->http->FindSingleNode('
                //div[contains(text(), "You must verify your email address for security purposes.")]
            ')
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->ParseForm('skipMigrationForm')) {
            $this->http->FormURL = 'https://login.scandichotels.com/authn/authenticate/scandic/migrate-continue';

            if (!$this->http->PostForm(['Content-Type' => 'application/x-www-form-urlencoded'], 120)) {
                return $this->checkErrors();
            }
        }
        $this->http->RetryCount = 2;

        if ($this->http->ParseForm('form1')) {
            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }

            if ($this->http->ParseForm('form1')) {
                $this->http->PostForm();
            }

            // An error occurred while logging in. Please try again later.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'An error occurred while logging in. Please try again later.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        $this->http->GetURL("https://www.scandichotels.com/en/scandic-friends/my-pages/overview");
        // Access is allowed
        if ($this->http->FindNodes("//a[contains(text(), 'Log out')]")) {
            return true;
        }

        // Sorry, something went wrong! // xpath and regexp not working
        if (
            $this->http->Response['code'] == 500
            && $this->http->currentUrl() == 'https://www.scandichotels.com/scandic-friends/my-profile'
            && in_array($this->AccountFields['Login'], [
                '30812383581834',
                '30812316008525',
                '30812323432395',
            ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h3[contains(@class, "friend_name")]')));
        // Balance - My balance
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'Your points to spend')]/following-sibling::h2"));
        // Membership No
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//code[contains(@class,'membershipNumber_icon__')]"));
        // Member level
        $this->SetProperty("MemberLevel", $this->http->FindSingleNode("//*[contains(@class,'levels_level__')]/preceding-sibling::p"));
        // ... spendable points expiring by ...
        $this->SetProperty("PointsToExpire", $this->http->FindSingleNode('//p[contains(., "spendable point") and contains(., "expiring by")]', null, true, "/(.+) spendable/"));
        // Points needed to level up
        $this->SetProperty("PointsNeededToLevelUp", $this->http->FindSingleNode('//p[contains(text(), "Points needed to level up")]/following-sibling::h2'));

        $expDate = $this->http->FindSingleNode('//p[contains(., "spendable point") and contains(., "expiring by")]', null, true, "/expiring by (.+)/");

        if ($expDate = strtotime($expDate)) {
            $this->SetExpirationDate($expDate);
        }
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://www.scandichotels.com/en/scandic-friends/my-pages/stays');

        if (
            $this->http->FindSingleNode('//h3[contains(text(), "You have no upcoming stays.")]')
            && !$this->ParsePastIts
        ) {
            return $this->noItinerariesArr();
        }
        // future links
        $futureLinks = $this->http->FindNodes('//div[contains(@class, "my-future-bookings")]//a[contains(@href, "/hotelreservation/my-booking")]/@href');
        $futureLinks = array_values(array_unique($futureLinks));
        $this->logger->info(sprintf('Found %s future bookings', count($futureLinks)));
        // past links
        if ($this->ParsePastIts) {
            $pastLinks = $this->http->FindNodes('//div[contains(@class, "table__container--historical-bookings")]//a[contains(@href, "/hotelreservation/my-booking")]/@href');
            $pastLinks = array_values(array_unique($pastLinks));
            $this->logger->info(sprintf('Found %s past bookings', count($pastLinks)));
        }

        foreach ($futureLinks as $link) {
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
            $this->parseItinerary();
        }

        if ($this->ParsePastIts) {
            foreach ($pastLinks as $link) {
                $this->http->NormalizeURL($link);
                $this->http->GetURL($link);
                $this->parseItinerary(true);
            }
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            'ConfNo'  => [
                'Caption'  => 'Booking number',
                'Type'     => 'string',
                'Size'     => 20,
                'Required' => true,
            ],
            'LastName' => [
                'Caption'  => 'Last name',
                'Type'     => 'string',
                'Size'     => 50,
                'Value'    => $this->GetUserField('LastName'),
                'Required' => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://www.scandichotels.com/hotelreservation/get-booking';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $this->http->FilterHTML = true;
        $this->http->GetURL($this->ConfirmationNumberURL([]));
        $this->http->setCookie('_abck', '12BB11809C7C3DADB6F1C537FD76FF01~0~YAAQT717XLad4xyEAQAAcU2VPAgsdZHnrXNa9U64grHRwJCsSVl8HhP8JgVW+6708Tza3aIXM/lk4YpwUHba8CfBHTwy3i3j0Gxza66rb5Bm1uIoUSTyEkrhPbHCnBIlp1i73EzNILW+YWwaDxHikGze3oIL+2Sq6fmzTHkLxuSzeBVlGCxKg9v4jq+CCLVWB51U6qucc1AbbAtTmfz6yaE6Aj/FKVBvhFEtV7nuqccPSc2Gwh0MkTRF9xKuwEiWFqGf/w20NpOGMjYmzXj5UpsFKHI3oAci2f4bU1ylId4WWpAI5mgNMEyPmC6BRUFXx4u2wZhA0ZCAA0pjBmldVIL9DPO1ezpyyoTimRm39MxWEBY4oW5IG+i0FwtZ33jEdbEiIgWA+mHzV3Huk06n+aldepkFRaQsXOitGT1/~-1~||-1||~-1', '.scandichotels.com');
//        $this->sendSensorData();
//        $this->http->GetURL($this->ConfirmationNumberURL([]));

        if (!$this->http->ParseForm('getBookingForm')) {
            return null;
        }
        $this->http->SetInputValue('BookingId', $arFields['ConfNo']);
        $this->http->SetInputValue('LastName', $arFields['LastName']);

        if (!$this->http->PostForm()) {
            return null;
        }

        $error = $this->http->FindPreg('#<div class="speech-bubble speech-bubble--point-down" data-test="error">\s*(No booking found)\s*</div>#');
        //$error = $this->http->FindSingleNode('//form//div[contains(text(), "No booking found")]');

        if ($error) {
            return $error;
        }
        $error = $this->http->FindSingleNode('//main//p[contains(normalize-space(text()), "A BOOKING ERROR HAS OCCURRED. The booking you just tried to make is not confirmed and you should make the booking again.")]');

        if ($error) {
            return 'A BOOKING ERROR HAS OCCURRED. The booking you just tried to make is not confirmed and you should make the booking again.';
        }
        $error = $this->http->FindSingleNode('//main//*[contains(normalize-space(text()), "And you must be logged in to the same member account that made the booking.")]');

        if ($error) {
            return $error;
        }
        $this->parseItinerary(true); // sometimes gets partly info, as past (but future)

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Check in date"          => "PostingDate",
            "Description"            => "Description",
            "Booking number"         => "Info",
            "Qualifying nights"      => "Info.Int",
            "Base points"            => "Bonus",
            "Bonus points"           => "Bonus",
            "Total points"           => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $this->http->GetURL('https://www.scandichotels.com/scandic-friends/my-profile');

        if ($this->http->ParseForm(null, '//div[@id = "previous-transactions"]/form[1]')) {
            $now = strtotime('now');
            $dateFrom = date('m/d/Y', strtotime('-3 years', $now));
            $dateTo = date('m/d/Y', $now);
            $this->http->SetInputValue('FilterFromSelectedDate.RawDateString', $dateFrom);
            $this->http->SetInputValue('FilterToSelectedDate.RawDateString', $dateTo);
        }

        $transactions = $this->http->XPath->query('//div[contains(@class, "historical-bookings l-separator-s")]//tr[contains(@class, "table__row") and not(contains(@class, "heading"))]') ?: [];
        $this->logger->info("Found {$transactions->length} historical transactions");

        $result = [];

        foreach ($transactions as $node) {
            $row = [];
            /*// Transaction date
            $dateStr = $this->http->FindSingleNode('./td[4]', $node);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                break;
            }
            $row['Transaction date'] = $postDate ?: null;*/

            // Check in date
            $checkInDate = $this->http->FindSingleNode('./td[1]', $node);
            $row['Check in date'] = $checkInDate ? strtotime($checkInDate) : null;
            // Description
            $description = $this->http->FindSingleNode('./td[2]', $node);
            $row['Description'] = $this->http->FindPreg('/Read more about (.+)$/', false, $description) ?: $description;
            // Booking number
            $row['Booking number'] = $this->http->FindSingleNode('./td[3]', $node) ?: null;
            // Points
            $row['Qualifying nights'] = intval(preg_replace('/\s+/', '', $this->http->FindSingleNode('./td[4]/span[contains(@class, "text-bold")]', $node)));
            $row['Base points'] = intval(preg_replace('/\s+/', '', $this->http->FindSingleNode('./td[5]/span[contains(@class, "text-bold")]', $node)));
            $row['Bonus points'] = intval(preg_replace('/\s+/', '', $this->http->FindSingleNode('./td[6]/span[contains(@class, "text-bold")]', $node)));
            $row['Total points'] = intval(preg_replace('/\s+/', '', $this->http->FindSingleNode('./td[7]/span[contains(@class, "text-bold")]', $node)));
            $result[] = $row;
        }

        return $result;
    }

    private function parseItinerary($past = false): bool
    {
        $this->logger->notice(__METHOD__);

        if ($error = $this->http->FindSingleNode('//h1[contains(., "My booking")]/following-sibling::div[1][contains(normalize-space(), "The booking can\'t be shown")]')) {
            $this->logger->error("Skipping itinerary: {$error}");

            return false;
        }
        $hotel = $this->itinerariesMaster->createHotel();
        // conf
        $conf = $this->http->FindSingleNode('//span[contains(text(), "Booking number")]/following-sibling::strong[1]');
        $this->logger->info("[{$this->currentItin}] Parse Hotel #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        $hotel->addConfirmationNumber($conf, 'Booking number', true);

        if ($this->http->FindSingleNode('//li[contains(text(), "Your booking has been cancelled")]')) {
            $hotel->general()->cancelled();
        }

        // Cancellation number
        $hotel->setCancellationNumber($this->http->FindSingleNode('//span[contains(text(), "Cancellation number")]/following-sibling::strong[1]'), false, true);

        // reservation date
        $bookingDateStr = $this->http->FindSingleNode('//span[contains(text(), "Booking date")]/following-sibling::strong[1]');
        $bookingDate = strtotime(str_replace("/", "-", $bookingDateStr));
        $this->logger->notice("booking date: {$bookingDate}");
        $hotel->setReservationDate($bookingDate);
        // hotel name
        $hotel->setHotelName($this->http->FindSingleNode('//h2[contains(@class, "hotel__heading hotel__heading--full-width")]'));
        // address
        $hotel->setAddress($this->http->FindSingleNode('//div[contains(@class, "hotel__address")]/p[1]'));
        // phone
        $hotel->setPhone($this->http->FindSingleNode('//div[contains(@class, "hotel__address")]/p[2]', null, false, '/^[+\-\d()\s]{6,20}/'));
        // check in date
        $date1Str = $this->http->FindSingleNode('//span[contains(text(), "Check in")]/following-sibling::div[1]');
        $this->logger->debug("check in date: {$date1Str}");
        // Sat 27 Feb, 15:00
        $hotel->setCheckInDate($this->normalizeDate($date1Str, $bookingDate));
        // check out date
        $date2Str = $this->http->FindSingleNode('//span[contains(text(), "Check out")]/following-sibling::strong[1]');
        $this->logger->debug("check out date: {$date2Str}");
        // Sat 28 Feb, 15:00
        $hotel->setCheckOutDate($this->normalizeDate($date2Str, $bookingDate));
        // guest count
        $guestsText = $this->http->FindSingleNode('//li[@id = "booking-summary-guests"]');
        $guestCount = $this->http->FindPreg('/(\d+)\s*Adults?/', false, $guestsText);
        $hotel->setGuestCount($guestCount);
        // kids count
        $kidsCount = $this->http->FindPreg('/(\d+)\s*Child/', false, $guestsText);
        $hotel->setKidsCount($kidsCount, false, true);
        // room type
        $roomNodes = $this->http->XPath->query('//div[contains(@class, "booking__info--confirmation")]');

        foreach ($roomNodes as $node) {
            $room = $hotel->addRoom();
            $roomType = $this->http->FindSingleNode('.//h2[contains(@class, "room__heading-level1")]', $node);
            $room->setType($roomType ?: null, false, $past);
            // room type description
            $roomDesc = $this->http->FindSingleNode('.//p[contains(@class, "room__space-info")]', $node);
            $room->setDescription($roomDesc ?: null, false, $past);

            if (empty($roomType) && empty($roomDesc)) {
                $hotel->removeRoom($room);
            }
        }
        // total, currency, spent awards
        $totalText = strip_tags($this->http->FindSingleNode('//div[@id = "booking-summary"]/@data-ng-init', null, true, "/^init\('(.+)'\)$/"));

        if (!$totalText && !in_array($this->http->FindSingleNode('//div[@id = "booking-summary"]/@data-ng-init'), ["init('')", "init('<span class=\"\"></span>')"])) {
            $this->sendNotification('check price // MI');
        }
        $totalStr = $this->http->FindPreg('/([\d.,]+)\s*[A-Z]{3}$/', false, $totalText);

        if ($totalStr) {
            $hotel->price()->total(PriceHelper::cost($totalStr));
            $hotel->price()->currency($this->currency($totalText));
        }
        $vouchers = $this->http->FindPreg('/(\d+\s+Vouchers?)/i', false, $totalText);
        $points = $this->http->FindPreg('/(\d+\s+Points?)/i', false, $totalText);

        if ($vouchers && $points) {
            $this->sendNotification('check spent awards // MI');
        } elseif ($vouchers) {
            $hotel->price()->spentAwards($vouchers);
        } elseif ($points) {
            $hotel->price()->spentAwards($points);
        }
        // non refundable
        $rateCondition = $this->http->FindSingleNode('(//span[contains(@class, "room-price-info__rate-condition")])[1]');

        if ($rateCondition) {
            $hotel->general()->cancellation($rateCondition);

            if ($this->http->FindPreg('/Non-refundable/', false, $rateCondition)) {
                $hotel->setNonRefundable(true);
            }
        }
        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($hotel->toArray(), true), ['pre' => true]);

        return true;
    }

    private function normalizeDate($date, $relativeDate)
    {
        $in = [
            // Sat 27 Feb, 15:00
            '/^(\w{3}) (\d+) (\w{3,8}), (\d+:\d+)$/',
        ];
        $out = [
            '$1 $2 $3 {year}, $4',
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("#^(?<week>\w{3}) (?<date>\d+ \w{3,8}.+)#", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], 'en'));
            $m['date'] = str_replace('{year}', date('Y', $relativeDate), $m['date']);
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } else {
            return null;
        }

        return $str;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            return false;
        }

//        $abck = [
//        ];
//        $key = array_rand($abck);
//        $this->logger->notice("key: {$key}");
//        $this->DebugInfo = "key: {$key}";
//        $this->http->setCookie("_abck", $abck[$key]); // todo: sensor_data workaround
//
//        return true;

        $this->http->NormalizeURL($sensorDataUrl);
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];

        $sensorData = [
            // 0
            '3;0;1;2048;4338228;x9bEmBIawP9jDPDAHEV5KHVDx7JzFrDQ3OZxezJ7XQ8=;30,0,0,1,3,0;"O" "0$I"Z"_6j-L"U5d"j7N"5"(+Q>."3S2em("i"E<P"0fP"mm6"qui"oO1wP"AX`"HKCt+"0`9"Su{de]iq2zU"|XhB"0ebNj"<H&"M""#n&"BfB"t/S.s"E3J"N"Wr|lb 1e+[@9^Or"]"48W"w"J7"~3h"5t/U"drsf~"=3"-O{9L"/U8"V"K":X7"aCe"USldTw3nf#w Tj~j0XbbB"|@DH"vQCKxw0>J/{^ND7?yHvQZA|>U"G"<<h")b"p2N"BYL`SV(fZ$*]2,yc~"`vk)")y.%yI~0Ap71maa^"Ox"o:FV&E80t""J"DfT"g""I"B7"S"16T_a"2J["_Ud"Q""=_m"P86"u"!"YFo"nI"2"LW"P"s{L"R"2;`cuYCMqY:=$<@Vey"e"m-V"D"Sz"U}H"-$2-"]"s|1w:?O#KDE*r…")!a)(jusT#8Abjg2pr|Q}jDr!;q qmR$7:mO"*TC"U-+"fe8h* "(qi`"=%*{w4v|"h4;"& B^o"<,?o"9"pK"3"{G("jS5p+=xeW"qv"4T_%!"e6"@#cYXjs$0H8;t[`2o"FG{"2H" [X"G;@" TOUa"){"h""0"PK)"x""-X}"cny"4""^"&8O"U""k"U/U"T"Qc1V:Z;^"@_M"^xf"C""u"<Oq"2"Qrxe,;wC0hBh=Y}mmN"k"`]2">O1.?$:X"%q5"aZg"p>/@"7-]"wh8"@R~yJcR;D~RMZu"gIM"}""Z"jg_"V"" "h@d"|:@BuK^Y"U2X"c""q"Qde"c^U}4"|`8~"+0zrJ"V:_"L""&"4P4"-hEPGdj"rY~g"Nb]"/:Q"6oG`4"xi8$"w""S"vH2"xO+@!&"$_*B~`Y"oWMF="GO"B 0P~"5a[7"xpP"MK]"8"C0J8K08u^}C=+SjZ68f#MC+UP3m76{RE2A8]JW?BmZ`~YP"A/s"I6',
            // 1
            '3;0;1;2048;4539696;IED9/tNduPSKUBWaU12nlaNR/mZMpIAePaiTd9rfe1M=;228,0,0,1,3,0;"_"zS0"%S"UJzku"`:" "T}"i"|09"BJa?&}"qzJB"{"aNi0m_pt<C6N0Ky"("@e/" 0~BLVq&"*^a"jT6ZK"@|;"Y"=IC6U(uZ"_35"X7G"U"Y1"h34"&`)r"6"_^Z1VM=Q^)Ei)I"RP7"+5x"1V,s.}?"*F>QtHP",-Mr2"qyJ"]""e"m#l"Y"kG"-l_"9]Xc"h.:$5"w/%"Ah$#JYOs"*%h"8^k#Iuiff"qU?X8Ti>Q ddwx!fL7@V54_3=sTZ;Ap#T~~"U#CLkr/RI3mz9,mxc"@"myy"<>bhvJ""K")(8"OXrY,+eqFT/"@%c"~]vtZ "8vXC"j{.fYo["0B*Sq":Ju"o/1tP"Y hJ>~b=o"A1-"<oMA*/ccKO,"1NRF"V(RO)"VM0"Iicj9]P{,!"(&."E""+"o#Q"I8mX;m""H"[T0"waYF]]F:J46_y;!"l`E:S"LH,vAgwl"maL"5"c"w"mR0">"M2oY0x$YVkh4udsF"kp-" (HNhcqE!5A|XQ-:$"v+}"z"9ORPW"HYt"Bfx"O"x:D{P3T%3|(-/|c^cQ<Xogt(11Q^Dv&/[2P*jJy~0j+@".".Mu"gylEM".f*"!,}r8Y*|y"&!")"<;)s2RI5Zf<V(H*N{4"9"ERP"j""6"O{w"D"Oorg 1i,{E,I!/MFJy","m!F"X"$*A"0"fkm"mDQMp"eKs"X""L"z5"B""9"Usi"l8:j8op/"A@d"ao"N"^"Jrv"YFQ"f"~&Y@"0n%"0*m"]YG"kqY1"f+(v#"!Pv2"n1"~vZ"="nK"o"c9N"k?DPn"n(!?"d0.xev*:k9yfW"7`b"C>N44BO"rc6%[X""["A.E"b"y_gU[<8q[d!s]"*"oX2"}dtgc"h`s6"rc]^NLE3"Dl&";VqQ{`;/ib9s%>:!"_9+"E""EF8"LrR"Tp!l;"MYL3"o*;K4"ve"$""y"gvZ"?k^As(}#l?N%"RNW/"Y)C?u"+?a"{""Y"JVY"pT*4k"XH"W7+"8X="M`ew!BL^Y6xI"B,W:"RFQH?twC"jjV"VEnw1FYhA^YBwNa7igTRQgQw(8^4QrdJHO^q"Gzy",(|">"}"CI@"EHM"{""8[7"j%1"G"Vn>mQ2{5M4mQs!,hd/Uj`Jg0oEgM;7)&{vhO-VcV?im^o"8WW"rxS"7D70:"P{R~"Gny"dH#"]EU>[lF8ITiTNm<B<IW4n)jA1"r"lY5"$`_I>"bx<"2"";"7Ai"$""["K|O"+{Exs"v!C"ai9<jjg""f"m8`"S _<k"=Q"PyvZ{/1"2nHX&"=zF"Ql6"y-3"A-G"Y""^si"Ao&"i"TxE&dK],)(Fzt*~-xIELjpb47:S:czmMn5JXb}<wro6OuW_j2VD!/K1}(.p0[AF+"4 W',
            // 2
            '3;0;1;2048;3551811;IED9/tNduPSKUBWaU12nlaNR/mZMpIAePaiTd9rfe1M=;225,0,0,0,2,0;"w"7Lj"<u"i4.:S"HZ"@"0S"^"_{%"U8zhRB"L)ih"="(2Zz^5p9)/tz.qb"v"`ZM"YJ2.d0MW"#*@"lEgbz",Zn"5";N3bdIQA"&|H"9ho"%"^;"KFk"!Jd6"="hM23}101+(dBi"gi&"x=|"1o[sco"!y<jH67"aZTQW"t3h"&""I"X1="="Ok" 1Q"g:CJ"(8 6p"*GH"UeW.-cnI"xW2")!b+$RWUF" D]63]1`q>]I)c&7&x-,`HKU(%y7.)LBWe:"T4e~0)#(]tm;ueU^5"9"[4=" {:9}:"">"FJ_"@.,59HGT+*R"4HU"N:`[/@"m)5:"6@IyUDg"9q]/Q"bVe"UGZQ?"*@w*[Z[E&"a=$"KZR`ao?8gdv"y_x#".aqR^"4K/"l/w_kp4|JN"mYM".""3"<h~"g/L^f`""k"hEb"R…H5"g#%0+oa7"UF&"f)8j,Rp87G,$7A>e"Qjy"a""hU4"_OJ" FO3H"z_gs"|<RG$"|0"3""L"zz/" j{[=LA1ftOP"RVj:"FK=on">h1"5""l"A_t"?r( 9"%3"]H:"~{<"A^|JLeVb%HR9"lK|S"EjJc.t+D"^Ny"*5l5Yl|w<6[no`zY8|RI~ZlY}5`:LR)Aq<k3"i?/"m:N"K"F"eG;"Eb4"&""+7Y"-l/"p"yU;g&:GpidlXmnC?yfburYbn442BIMXP@#[y.~RlY+4v}"LPr"ayU"+)H!Z"_)zE"VYA"A@5"!bj;B+;c,KvVEXzS bDB0I-c4"F">HB"<&!V("R5!"*""/"Y;+"!""y"Rh}"_rM51"%11"M%q#okf""x"&yn"tLqF_"?y"T58 7-q"++tH("/nx"D/s"7UH"Pu}"(""w8("?US"s"<uGzNyopArC,7K30kjG|Y}lZ[KQrwj)#^.<STXNb5W4b0uqq{%5F&TK;3;nlx.`b"9#V',
        ];

        $secondSensorData = [
            // 0
            null,
            // 1
            null,
            // 2
            null,
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        if ($secondSensorData[$key]) {
            $data = [
                "sensor_data" => $secondSensorData[$key],
            ];
            $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
            $this->http->JsonLog();
            $this->http->RetryCount = 2;
            sleep(1);
        }

        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            //$selenium->disableImages();
//            $selenium->useCache();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL($this->ConfirmationNumberURL([]));

            $selenium->waitForElement(WebDriverBy::id('BookingId'), 7);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }
    }
}
