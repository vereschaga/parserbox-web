<?php

namespace AwardWallet\Engine\eva;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ConfNoOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithConfNoInterface;
use AwardWallet\ExtensionWorker\LoginWithConfNoResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\RetrieveByConfNoInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class EvaExtension extends AbstractParser implements LoginWithIdInterface,
    ParseInterface, ParseItinerariesInterface, LoginWithConfNoInterface, RetrieveByConfNoInterface
{
    use TextTrait;
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.evaair.com/en-global/index.html';
     }

    public function isLoggedIn(Tab $tab): bool
    {
        $tab->setCookie('_EVAlang', 9, '/', ['domain' => '.evaair.com']);
        $tab->gotoUrl('https://eservice.evaair.com/flyeva/eva/ffp/frequent-flyer.aspx');
        $acceptCookies = $tab->evaluate('//button[@data-all="Accept All Cookies"] | //form[contains(@action, "login")] | //a[@href="personal-data.aspx"]/../dl//span[2]',
            EvaluateOptions::new()->allowNull(true)->timeout(5));
        if (isset($acceptCookies) && stristr($acceptCookies->getInnerText(), 'Accept All Cookies')) {
            $acceptCookies->click();
        }
        $el = $tab->evaluate('//form[contains(@action, "login")] | //a[@href="personal-data.aspx"]/../dl//span[2]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//a[@href="personal-data.aspx"]/../dl//span[2]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="content_wuc_login_Account"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="content_wuc_login_Password"]');
        $password->setValue($credentials->getPassword());

        $rememberMe = $tab->evaluate('//input[@id="content_wuc_login_Remember"]');
        if (!$rememberMe->checked()) {
            $rememberMe->click();
        }

        $tab->showMessage(Message::captcha('Login'));

        try {
            $submitResult = $tab->evaluate('//a[@href="personal-data.aspx"]/../dl//span[2] 
            | //div[@id="wuc_Error"]//li 
            | //input[@id="wuc_SSE_txt_CertificationCode"]', EvaluateOptions::new()->timeout(100));
        } catch (\Exception $e) {
            $tab->showMessage($e->getMessage());
            return LoginResult::captchaNotSolved();
        }


        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } elseif ($submitResult->getAttribute('id') == 'wuc_SSE_txt_CertificationCode') {
            $tab->showMessage(Message::identifyComputer('Confirm'));
            $errorOrTitle = $tab->evaluate('//a[@href="personal-data.aspx"]/../dl//span[2] | //div[@id="wuc_Error"]//li ',
                EvaluateOptions::new()->timeout(120)->allowNull(true));
            if ($errorOrTitle) {
                return new LoginResult(true);
            } else {
                return LoginResult::identifyComputer();
            }
        } else {
            $error = $submitResult->getInnerText();

            if (strstr($error, "You have input wrong password") && strstr($error, "For privacy security policy, the log-in function will be inaccesssible until you finish the procedure of")) {
                return new LoginResult(false, $error, null, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($error, "You have input wrong password")
                || strstr($error, "Invalid Membership Number")
                || strstr($error, "Wrong CAPTCHA entry. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="header-main"]//li[contains(@class, "toolbar-item") and contains(@class, "login")]/a[not(contains(@href, "login"))]')->click();
        $tab->evaluate('//div[@class="header-main"]//li[contains(@class, "toolbar-item") and contains(@class, "login")]/a[contains(@href, "login")]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        // Balance -  Self Award Miles
        // refs #5696
        $st->setBalance($tab->evaluate('//div[h3[contains(text(), "Self Award Miles")]]/following-sibling::p/span[contains(@class, "color-green") and contains(@class, "vertical-baseline")]')->getInnerText());
        // Membership status
        $st->addProperty('Status', $tab->findTextNullable("//dt[contains(text(), 'Your current membership status is')]",
            FindTextOptions::new()->preg('/([A-Za-z]+)\s*card/ims')));

        // Expiration Date  // refs #19216
        // Search all dates with miles > 0
        $nodes = $tab->evaluateAll("//div[h3[contains(text(), 'Own Earned miles which will expire within 6 months')]]/following-sibling::table//tr[td[3]]");
        $this->logger->debug("Total " . count($nodes) . " exp nodes were found");

        foreach ($nodes as $node) {
            $miles = $tab->findTextNullable('td[2]', FindTextOptions::new()->contextNode($node));

            if ($miles > 0) {
                $expire[] = [
                    'date' => "01 " . $tab->findText('td[1]', FindTextOptions::new()->contextNode($node)),
                    'miles' => $miles,
                ];
            }
        }

        if (!empty($expire)) {
            // Find the nearest date with non-zero balance
            $N = count($expire);
            $this->logger->debug(">>> Total nodes with expiring miles " . $N);
            // Log
            $this->logger->debug(var_export($expire, true), ['pre' => true]);
            $i = 0;

            while (($N > 0) && ($i < $N)) {
                if ($date = strtotime($expire[$i]['date'])) {
                    $st->setExpirationDate($date);
                    // Set Property 'Expiring Balance' (Mileage Balance)
                    $st->addProperty('ExpiringBalance', $expire[$i]['miles']);

                    break;
                }
                $i++;
            }
        } elseif ($tab->findTextNullable('//h3[contains(text(), "There is no mile which will be expired within 6 months.")]')) {
            $st->addProperty('ClearExpirationDate', 'Y');
        }

        $earnedFlightMiles = $tab->findTextNullable('//p/node()[contains(normalize-space(.), "more Status Miles")]/preceding-sibling::span[1]');
        $earnedSectors = $tab->findTextNullable('//p/node()[contains(normalize-space(.), "more Status Miles")]/following-sibling::span[1]');
        if (!isset($earnedSectors) && isset($this->Properties['EarnedFlightMiles'])) {
            $earnedSectors = $tab->findTextNullable('//div[h2[contains(normalize-space(text()), "Upgrade")]]/following-sibling::div[2]//p[contains(normalize-space(.), "more Sectors")]/node()[contains(normalize-space(.), "You need to obtain")]/following-sibling::span[1]');
        }
        // Needed Status Miles to Next Level
        $st->addProperty('EarnedFlightMiles', $earnedFlightMiles);
        // Needed Sectors to Next Level
        if ($earnedSectors) {
            $st->addProperty('EarnedSectors', $earnedSectors);
        }



        // (Validity) Status Expiration Date
        $statusExpirationDate = $tab->findTextNullable('//dt[contains(text(), "Validity")]/following-sibling::dd',
            FindTextOptions::new()->preg('/\-\s*([^<]+)/'));
        if ($statusExpirationDate) {
            $st->addProperty('StatusExpirationDate', $statusExpirationDate);
        }
        // Membership Number
        $st->addProperty('MemberNo',
            $tab->findTextNullable('//span[contains(text(), "Membership Number:")]/following-sibling::span'));
        // Name
        $st->addProperty('Name', beautifulName($tab->findTextNullable('(//p[contains(text(), "Hello,")]/span)[1]',
            FindTextOptions::new()->visible(false))));
    }

    public function parseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        if ($msg = $tab->findTextNullable('//*[self::p or self::li][contains(text(), "You do not have any upcoming trip. Book a flight now!")]')) {
            $this->logger->notice($msg);
            $master->setNoItineraries(true);
            return;
        }
        $count = $tab->findTextNullable('//text()[contains(.,"upcoming flights trips and you can manage your trip.")]/preceding-sibling::span');
        if ($count > 1) {
            $this->notificationSender->sendNotification("check upcoming $count // MI");
        }

        $tab->evaluate('//a[contains(text(), "Check Booking List")]')->click();

        if ($tab->evaluate('//h1[contains(text(), "Manage Your Trip")]',
            EvaluateOptions::new()->allowNull(true)->timeout(30))) {
            if ($msg = $tab->findTextNullable('//li[
                    contains(text(), "Sorry, there is no booked itinerary under your booking number.")
                    or contains(normalize-space(text()), "Sorry! Your booking record cannot be retrieved.Please check your data and try again!Thank you!")
                ]')) {
                $this->logger->notice($msg);
                $master->setNoItineraries(true);
                return;
            }
            $this->parseItinerary($tab, $master);
        } else {
            $this->notificationSender->sendNotification("not found 'Manage Your Trip'// MI");
        }
    }

    public function getLoginWithConfNoStartingUrl(array $confNoFields, ConfNoOptions $options): string
    {
        return 'https://booking.evaair.com/flyeva/EVA/B2C/manage-your-trip/log_in.aspx';
    }

    public function loginWithConfNo(Tab $tab, array $confNoFields, ConfNoOptions $options): LoginWithConfNoResult
    {
        $tab->evaluate('//input[@id="content_wuc_PNR_txt_Code"]')->setValue($confNoFields['ConfNo']);
        $tab->evaluate('//input[@id="content_wuc_PNR_txt_LastName"]')->setValue($confNoFields['LastName']);
        $tab->evaluate('//input[@id="content_wuc_PNR_txt_FirstName"]')->setValue($confNoFields['FirstName']);

        $errorOrSuccessXpath = '//h1[contains(text(), "Manage Your Trip")]
            | //li[contains(text(),"Please double-check the booking reference and the spelling of the name you entered are accurate.")]';

        if ($tab->evaluate('//img[contains(@id,"logincaptcha_CaptchaImage")]')) {
            $tab->showMessage(Message::captcha('Log In'));
            $loginResult = $tab->evaluate($errorOrSuccessXpath,
                EvaluateOptions::new()->timeout(120));

        } else {
            $tab->evaluate('//input[@id="content_wuc_PNR_btn_Go"]')->click();
            $loginResult = $tab->evaluate($errorOrSuccessXpath);
        }

        if ($loginResult) {
            $error = $loginResult->getInnerText();
            if (stristr($error, 'Please double-check the booking reference and the spelling of the name you entered are accurate.')) {
                return LoginWithConfNoResult::error($error);
            }
        }

        $tab->saveScreenshot();
        $tab->saveHtml();
        return LoginWithConfNoResult::success();
    }
    public function retrieveByConfNo(Tab $tab, Master $master, array $fields, ConfNoOptions $options): void {
        $this->parseItinerary($tab, $master,  $fields['ConfNo'], $fields['LastName']);
    }


    private function parseItinerary(Tab $tab, Master $master): bool
    {
        $this->logger->notice(__METHOD__);

        if ($tab->findTextNullable('//li[contains(text(), "The system is temporarily unavailable, so please try it later. We apologize for any inconvenience that might have caused you.")]')) {
            $this->logger->info('Broken Itinerary', ['Header' => 3]);
            $this->logger->error("skip broken itinerary");

            return false;
        }

        // RecordLocator
        $confNo = $tab->evaluate("//dt[contains(text(), 'Booking Reference')]/following-sibling::dd/span")->getInnerText();
        $confNo = preg_replace('/\s*\(.+\)/ims', '', $confNo);

        $this->logger->info('Parse Itinerary #' . $confNo, ['Header' => 3]);

        if (!$confNo && !$tab->findTextNullable("//div[@aria-label='Please select the segment you want to manage.']/div/button")
            && $tab->findTextNullable("//dt[contains(text(), 'Booking Reference')]/following-sibling::dd[contains(text(),'(Online booking)')]")) {
            $this->logger->error("skip broken itinerary");

            return false;
        }

        $f = $master->createFlight();
        $f->general()->confirmation($confNo, 'Booking Reference', true);
        // travellers
        $passengerItems = $tab->findTextAll("//dt[contains(@id, 'dt_Passanger_Name')]");
        $passengers = array_filter(array_unique(array_map(function ($item) {
            return beautifulName($item);
        }, $passengerItems)));
        $paxFromSegmentItems = $tab->findTextAll('//div[contains(@id, "Segment_meal_") or contains(@id, "Segment_seat_")]//dd[@class = "task-status"]/preceding-sibling::dt[1]');
        $pax = array_filter(array_unique(array_map(function ($item) {
            return beautifulName($item);
        }, $paxFromSegmentItems)));

        $segments = $tab->evaluateAll("//div[starts-with(translate(@id,'0123456789','dddddddddd'),'flightSegment')]",
            EvaluateOptions::new()->visible(false));

        if (count($pax) > count($passengers)) {
            $f->general()->travellers($pax, true);
        } elseif (!empty($passengers)) {
            $f->general()->travellers($passengers, true);
        } elseif (count($segments) == 0) {
            return false;
        }

        $f->setTicketNumbers(array_unique(array_filter($tab->findTextAll("//dd[contains(@id, 'dd_TicketNo_')]/span[not(contains(text(), 'No Ticket'))]"))),
            false);

        $f->setAccountNumbers(array_unique(
            $tab->findTextAll("//dd[contains(@id, 'dd_FrequentFlyerNumber_')]", FindTextOptions::new()->preg('#([^/]+)#'))
        ), true);

        $this->logger->debug("Total " . count($segments) . " segments were found");

        foreach ($segments as $key => $segment) {
            $s = $f->addSegment();
            $tab->evaluate("//button[@id='flightSegmentTab$key']")->click();

            $departure = $tab->findText('.//div[contains(@class, "flightSegment-airport--chooseStart")]/p[2]',
                FindTextOptions::new()->contextNode($segment));
            $s->departure()
                ->code($tab->findText('.//div[contains(@class, "flightSegment-airport--chooseStart")]/p[1]',
                    FindTextOptions::new()->contextNode($segment)))
                ->terminal($this->findPreg("/\(Terminal\s*([^)]+)/", $departure), false, true)
                ->date2($tab->findText('.//div[contains(@class, "flightSegment-airport--chooseStart")]/p[3]',
                    FindTextOptions::new()->contextNode($segment)));

            if ($dep = $this->findPreg("/([^(]+)/", $departure)) {
                $s->departure()->name($dep);
            }

            $arrival = $tab->findText('.//div[contains(@class, "flightSegment-airport--chooseEnd")]/p[2]',
                FindTextOptions::new()->contextNode($segment));
            $s->arrival()
                ->code($tab->findText('.//div[contains(@class, "flightSegment-airport--chooseEnd")]/p[1]',
                    FindTextOptions::new()->contextNode($segment)))
                ->terminal($this->findPreg("/\(Terminal\s*([^)]+)/", $arrival), false, true)
                ->date2($tab->findText('.//div[contains(@class, "flightSegment-airport--chooseEnd")]/p[3]',
                    FindTextOptions::new()->contextNode($segment)));

            if ($arr = $this->findPreg("/([^(]+)/", $arrival)) {
                $s->arrival()->name($arr);
            }

            $number = $tab->findText('.//li[span[contains(text(), "Flight Number")]]/text()[last()]',
                FindTextOptions::new()->contextNode($segment));
            $s->airline()
                ->name($this->findPreg("/([A-Z]{1,2})/", $number))
                ->number($this->findPreg("/[A-Z]{1,2}(\d+)/", $number))
                ->operator($tab->findTextNullable('.//dd[contains(@id, "dd_AirlineName_")]',
                    FindTextOptions::new()->contextNode($segment)), false, true);

            $cabin = $tab->findText('.//li[contains(@id, "Segment_li_Flight_Cabin_")]',
                FindTextOptions::new()->contextNode($segment));
            $s->extra()
                ->duration($tab->findText('.//p[span[contains(text(), "Flight time")]]/text()[last()]',
                    FindTextOptions::new()->contextNode($segment)), true, true)
                ->cabin($this->findPreg("/([^(]+)/", $cabin), false, true)
                ->bookingCode($this->findPreg("/\(([^)]+)/", $cabin), false, true)
                ->status($tab->findTextNullable('.//dd[contains(@id, "dd_FlightStatus_")]',
                    FindTextOptions::new()->contextNode($segment)), false, true)
                ->aircraft($tab->findTextNullable('.//dd[contains(@id, "dd_Aircraft_")]/text()[1]',
                    FindTextOptions::new()->contextNode($segment)), true, true)
                ->seats($tab->findTextAll('.//div[contains(@id, "Segment_seat_")]//dd[contains(@class, "task-status") and not(contains(text(), "Unable to select seat") or contains(text(), "Unselected")) and not(contains(@class, "statusAction"))]',
                    FindTextOptions::new()->contextNode($segment)))
                ->meal(implode(', ',
                    $tab->findTextAll('.//div[contains(@id, "Segment_meal_")]//dd[@class = "task-status" and not(contains(text(), "unordered"))]',
                        FindTextOptions::new()->allowNull(true)->contextNode($segment))), true);

            if ($s->getDepCode() === $s->getArrCode()) {
                $this->logger->error('Removing invalid segment');

                if ($s->getDepCode() !== 'TPE') {
                    $this->notificationSender->sendNotification('check remove invalid segment // MI');
                }
                $f->removeSegment($s);
            }
        }// foreach ($segments as $segment)

        if (count($segments) > 0 && count($f->getSegments()) === 0) {
            $this->logger->error('Removing invalid itinerary');
            $master->removeItinerary($f);

            return false;
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return false;
    }
}
