<?php

namespace AwardWallet\Engine\goldpassport;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseHistoryInterface;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Common\Statement;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;
use Exception;
use function AwardWallet\ExtensionWorker\beautifulName;

class GoldpassportExtension
    extends AbstractParser
    implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface, ParseHistoryInterface
{
    use TextTrait;
    private int $attempt = -1;
    private array $activitiesSummary = [];
    private object $wso2AuthToken;
    private int $itCount = 0;
    private int $skipPastCount = 0;
    private string $goldpassportId;
    private array $itineraryRequest = [];
    private $profile = null;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.hyatt.com/en-US/member/sign-in/traditional?returnUrl=https%3A%2F%2Fwww.hyatt.com%2Fprofile%2Faccount-overview';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        try {
            $json = $tab->fetch('https://www.hyatt.com/profile/api/member/profile')->body;
            $this->logger->info($json);
            $this->profile = json_decode($json);
            return isset($this->profile->profile->full->accountNumber);
        } catch (Exception $e) {
            return false;
        }

    }

    public function getLoginId(Tab $tab): string
    {
        if (isset($this->profile->profile->full->accountNumber))
            return $this->profile->profile->full->accountNumber;
        return '';
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $this->profile = null;
        $tab->evaluate('//div[@data-locator="account-panel"]//button')->click();
        $tab->evaluate('//form[contains(@class,"_profile-signout-form")]/button')->click();
        $tab->evaluate('//a[contains(text(),"I am not ")] 
        | //a[contains(text(),"Sign in with password")] 
        | //form[@name="signin-form"]//input[@name = "userId"]',
            EvaluateOptions::new()->allowNull(true)->timeout(20));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $this->attempt++;
        if ($this->attempt > 2) {
            return new LoginResult(false);
        }
        $typeLogin = $tab->evaluate('//a[contains(text(),"I am not ")] 
        | //a[contains(text(),"Sign in with password")] 
        | //form[@name="signin-form"]//input[@name = "userId"]', EvaluateOptions::new()->visible(false));
        if ($typeLogin->getNodeName() == 'A') {
            $typeLogin->click();
            $tab->gotoUrl('https://www.hyatt.com/en-US/member/sign-in/traditional?returnUrl=https%3A%2F%2Fwww.hyatt.com%2Fprofile%2Faccount-overview');
        }

        $login = $tab->evaluate('//form[@name="signin-form"]//input[@name = "userId"]');

        $login->setValue($credentials->getLogin());

        $lastName = $tab->evaluate('//form[@name="signin-form"]//input[@name = "lastName"]');
        $lastName->setValue($credentials->getLogin2());

        $password = $tab->evaluate('//form[@name="signin-form"]//input[@name = "password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//form[@name="signin-form"]//button[@type="submit"]')->click();

        sleep(1);
        $errorOrTitle = $tab->evaluate('//div[contains(@class,"MemberCard_container_")] | //span[@data-e2e = "errorText"] | //span[contains(@class,"error-message")]');

        $this->fileLogger->logFile($tab->getHtml(), ".html");

        if (stristr($errorOrTitle->getInnerText(), 'Something went wrong. Please try again.')) {
            $this->logger->error('login retry');
            sleep(1);
            return $this->login($tab, $credentials);
        } elseif (str_contains($errorOrTitle->getAttribute('class'), 'MemberCard_container_')) {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } else {
            $this->logger->error('error logging in');
            $error = $errorOrTitle->getInnerText();
            if (str_contains($error,
                "Something went wrong, and your request wasn't submitted. Please try again later.")) {
                return LoginResult::invalidPassword($error);
            }
            //  The information you have entered does not match what we have on file. Please review your account information and try signing in again.
            if (str_contains($error, "The information you have entered does not match what we have on file")) {
                return LoginResult::invalidPassword($error);
            }
            return new LoginResult(false);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        // Balance - Current Point Balance
        $statement->setBalance(str_replace(',','',
            $tab->findText('//span[contains(., "Current Point Balance")]/following-sibling::div',
                FindTextOptions::new()->preg('/^[\d.,]+$/'))));

        $json = $tab->fetch('https://www.hyatt.com/profile/api/member/profile')->body;
        $this->logger->info($json);
        $profile = json_decode($json);
        // Number
        $statement->addProperty('Number', $profile->profile->full->accountNumber);
        $this->goldpassportId = $profile->profile->full->accountNumber;
        // Name
        $this->firstName = $profile->profile->full->firstName;
        $this->lastName = $profile->profile->full->lastName;

        $statement->addProperty('Name', beautifulName("{$this->firstName} {$this->lastName}"));
        // Tier
        switch ($profile->profile->full->profile->tier??null) {
            case 'P':
                $statement->addProperty("Tier", "Platinum");

                break;

            case 'D':
                $statement->addProperty("Tier", "Diamond");

                break;

            case 'l':
                $statement->addProperty("Tier", "Lifetime Diamond");

                break;

            case 'C':
                $statement->addProperty("Tier", "Courtesy");

                break;

            case 'G':
                $statement->addProperty("Tier", "Gold");

                break;
            // new statuses
            case 'M':
                $statement->addProperty("Tier", "Member");

                break;

            case 'E':
                $statement->addProperty("Tier", "Explorist");

                break;

            case 'V':
                $statement->addProperty("Tier", "Discoverist");

                break;

            case 'B':
                $statement->addProperty("Tier", "Globalist");

                break;

            case 'L':
                $statement->addProperty("Tier", "Lifetime Globalist");

                break;

            default:
                break;
        }
        // Tier Expiration
        $tierExpireDate = $profile->profile->full->profile->tierExpireDate;
        if (!empty($tierExpireDate) && strtotime($tierExpireDate) && strtotime($tierExpireDate) < 2553937585 /*Tue, 06 Dec 2050 11:06:25 GMT*/) {
            $statement->addProperty("TierExpiration", strtotime($tierExpireDate));
        }
        // Member since
        $enrollDate = $profile->profile->full->profile->enrollDate;
        if (!empty($enrollDate) && strtotime($enrollDate)) {
            $statement->addProperty("MemberSince", strtotime($enrollDate));
        }
        // Lifetime Points
        $statement->addProperty("LifetimePoints", number_format($profile->profile->full->lifePoints));
        // Qualified Nights YTD
        $statement->addProperty("Nights", $profile->profile->full->ytdNights);
        // Base Points YTD
        $statement->addProperty("BasePointsYTD", $profile->profile->full->ytdBasePoints);

        if (!empty($this->goldpassportId)) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            $this->itineraryRequest = [
                "goldPassportId" => $this->goldpassportId,
                "locale" => "en",
                "firstName" => $this->firstName,
                "lastName" => $this->lastName,
            ];

            $filterStartDate = date("Y-m-d", strtotime("-5 years"));
            $json = $tab->fetch("https://www.hyatt.com/profile/api/stay/pastactivity?pageSize=100&pageIndex=0&transactionType=AT&locale=en-US&startDate={$filterStartDate}&endDate=")->body;
            $this->logger->info($json);
            $pastActivities = json_decode($json)->pastActivity ?? [];

            // Expiration date  // refs #6360, 12414
            $this->logger->debug("Total " . ((is_array($pastActivities) || ($pastActivities instanceof Countable)) ? count($pastActivities) : 0) . " activity rows were found");

            if (empty($pastActivities)) {
                return;
            }

            foreach ($pastActivities as $activity) {
                $activityDate = $this->findPreg("/(.+)T?/", $activity->transaction->date ?? '');
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
                }

                if ($d !== false && $d <= time()) {
                    $exp = strtotime("+2 year", $d);
                }

                if (isset($exp, $lastActivityUnixTime)) {
                    $this->logger->debug("Exp: $exp");
                    $this->logger->debug("lastActivityUnixTime: $lastActivityUnixTime");
                    if ($exp <= $lastActivityUnixTime) {
                        $statement->addProperty("LastActivity", strtotime($activityDate));
                        $statement->setExpirationDate($exp);
                    } else {
                        $exp = strtotime("+2 year", $lastActivityUnixTime);
                        $statement->setExpirationDate($exp);
                        $statement->addProperty("LastActivity", strtotime($lastActivity));
                    }

                    break;
                }
            }

            // SubAccounts - Awards     // refs #4425
            $this->logger->info('Awards', ['Header' => 3]);
            $json = null;
            try {
                $json = $tab->fetch('https://www.hyatt.com/profile/api/loyalty/awarddetail?locale=en-US')->body;
                if (empty($json)) {
                    $json = $tab->fetch("https://www.hyatt.com/mse/memberaward/v1/members/details/{$this->goldpassportId}?locale=en-US")->body;
                }
                $this->logger->info($json);
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }

            $awardCategories = json_decode($json)->awardCategories ?? [];
            $this->logger->debug("Total Awards found: " . count($awardCategories));
            $subAccount = [];

            foreach ($awardCategories as $awardCategory) {
                foreach ($awardCategory->awards ?? [] as $award) {
                    $subAcc = [
                        'Code' => 'goldpassport' . $award->code . $award->expirationDate . str_replace(' ', '',
                                $award->title),
                        'DisplayName' => $award->title,
                        'Balance' => 1,
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
                $statement->addSubAccount($subAcc);
            }
        }
    }

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
                $this->logger->notice("goldpassport - refs #14615 World of Hyatt - changing the subaccount's name. New award type was found: {$code}");
        }

        return $displayName;
    }

    private $headers = [
        "Accept"       => "application/json",
        "Content-Type" => "application/json",
        "Referer"      => "https://www.hyatt.com/profile/account-overview",
    ];

    private function getHistoryRows(Tab $tab, $logs = 0)
    {
        $filterStartDate = date("Y-m-d", strtotime("-5 years"));
        $json = null;
        try {
            $json = $tab->fetch("https://www.hyatt.com/profile/api/stay/pastactivity?pageSize=100&pageIndex=0&transactionType=AT&locale=en-US&startDate={$filterStartDate}&endDate=")->body;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $this->logger->info($json);
        return json_decode($json);
    }


    public function parseItineraries(Tab $tab, Master $master, AccountOptions $options, ParseItinerariesOptions $parseOptions) : void
    {
        $this->logger->debug("isParsePastItineraries {$parseOptions->isParsePastItineraries()}");
        $options = [
            'method'  => 'get',
            'headers' => [
                'Accept'              => 'application/json',
                'Referer'             => 'https://www.hyatt.com/profile/my-stays',
            ],
        ];
        $json = null;
        try {
            $json = $tab->fetch("https://www.hyatt.com/profile/api/stay/reservation?locale=en-US&firstName=$this->firstName}&lastName={$this->lastName}", $options)->body;
            $this->logger->info($json);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $upcomingStays = json_decode($json)->reservations ?? [];
        $countUpcomingStays = count($upcomingStays);
        $this->logger->debug("Total {$countUpcomingStays} itineraries were found");

        $i = 0;
        foreach ($upcomingStays as $reservation) {
            try {
                $this->parseItinerary($tab, $master, $reservation);
                $this->watchdogControl->increaseTimeLimit(300);
                if ($i >= 100) {
                    break;
                }
                $i++;
            } catch (\Exception $e) {
                $this->logger->info($e->getMessage());
                continue;
            }
        }

        // no Itineraries
        if ($countUpcomingStays == 0
            && (
                $this->findPreg("/\{\"upcomingStays\":\[\]\}/", $json)
                || $this->findPreg("/\{\"reservations\":\[\],\"totalCount\":0\}/", $json)
                || $this->findPreg("/\{\"reservations\":\[\],\"totalCount\":0\}/", $json)
            )
        ) {
            $master->setNoItineraries(true);
        }
    }

    private function parseItinerary(Tab $tab, Master $master, $reservation)
    {
        $this->logger->notice(__METHOD__);
        // ConfirmationNumber
        $confNo = $reservation->hotelReservationId ?? $reservation->confirmationNumber;
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->itCount++, $confNo), ['Header' => 3]);
        $reservationToken = $reservation->reservationToken;

        if (empty($reservationToken)) {
            $this->logger->error('token not found');
            return;
        }

        $tab->gotoUrl("https://www.hyatt.com/reservation/detail/$reservationToken");
        $confNoPage = $tab->findText('//p[contains(text(), "Confirmation:")]', FindTextOptions::new()->preg('/(\w+)$/'));
        if ($confNoPage != $confNo) {
            $this->notificationSender->sendNotification('The page has not changed // MI');
            sleep(5);
            $confNoPage = $tab->findText('//p[contains(text(), "Confirmation:")]', FindTextOptions::new()->preg('/(\w+)$/'));
            if ($confNoPage != $confNo) {
                $this->notificationSender->sendNotification('The page has not changed 2 // MI');
                return;
            }
        }
        $hotelNodes = $tab->evaluateAll('//div[contains(@class, "p-room-stay") or contains(@class, "m-modify-reservation")]');
        if (count($hotelNodes) == 0) {
            $hotelNodes = $tab->evaluateAll('//div[contains(@class, "p-hotel-stay") or contains(@class, "m-modify-reservation")]');
        }
        //$tab->saveScreenshot();
        foreach ($hotelNodes as $hotelNode) {
            $this->parseOneItineraryFromHtml($tab, $master, $hotelNode);
        }
    }

    private function parseOneItineraryFromHtml(Tab $tab, Master $master, $hotelNode): bool
    {
        $this->logger->notice(__METHOD__);
        $hotelInfoNodes = $tab->evaluateAll('.//div[contains(@class, "hotel-info-container") or contains(@class, "m-hotel-card")]', EvaluateOptions::new()->contextNode($hotelNode));

        foreach ($hotelInfoNodes as $hotelInfoNode) {
            $roomNodes = $tab->evaluateAll('.//div[contains(@class, "m-reservation-details")]', EvaluateOptions::new()->contextNode($hotelNode));

            foreach ($roomNodes as $roomNode) {
                $h = $master->createHotel();
                // ConfirmationNumber
                $h->general()->confirmation($tab->findText('//p[contains(text(), "Confirmation:")]', FindTextOptions::new()->preg('/(\w+)$/')));
                // Cancelled
                if ($tab->findTextNullable('//div[contains(@class, "p-cancelled-reservation")]//div[contains(text(), "This reservation has been canceled")]', FindTextOptions::new()->timeout(0))) {
                    $h->general()->cancelled();
                }
                // CancellationPolicy
                $h->setCancellation($tab->findText('(.//div[contains(@class, "cancellation-policy")]/div[contains(text(), "Cancellation Policy")]/following-sibling::div[1])[1]',
                    FindTextOptions::new()->contextNode($hotelNode)));

                if (!$hotelInfoNode) {
                    $this->logger->error('something went wrong');

                    continue;
                }
                $hotelName = $tab->findText('.//div[contains(@class, "b-text_display-1")]',
                    FindTextOptions::new()->contextNode($hotelInfoNode));

                if (empty($hotelName)) {
                    $hotelName = $tab->findText('.//div[contains(@class, "b-text_style-uppercase")]',
                        FindTextOptions::new()->contextNode($hotelInfoNode));
                }

                if (empty($hotelName)) {
                    $hotelName = $tab->findText('(.//span[contains(@class, "hotel-name")])[1]',
                        FindTextOptions::new()->contextNode($hotelInfoNode));
                }

                if (empty($hotelName)) {
                    $hotelName = $tab->findText('(.//span[@data-locator = "hotel-name"])[1]',
                        FindTextOptions::new()->contextNode($hotelInfoNode));
                }

                if (empty($hotelName)) {
                    $hotelName = $tab->findText('(.//div[contains(@class, "b-text_style-uppercase")])[1]',
                        FindTextOptions::new()->contextNode($hotelInfoNode));
                }
                $h->hotel()->name($hotelName);

                $address = implode(', ',
                    array_filter($tab->findTextAll('.//button[@data-js = "cancel-button"]/ancestor::div[1]/preceding-sibling::div[2]',
                        FindTextOptions::new()->contextNode($hotelInfoNode))));

                if (empty($address)) {
                    $address = implode(', ',
                        $tab->findTextAll('(.//span[@data-locator = "hotel-name"])[2]/ancestor::div[1]/following-sibling::div[1]',
                            FindTextOptions::new()->contextNode($hotelInfoNode)));
                    $address = $this->findPreg('/^(.+?)(?:\s*Tel:|$)/', $address);
                }

                if (empty($address)) {
                    $address = $tab->findText('.//a[@data-js = "print-button"]/ancestor::ul[1][count(./preceding-sibling::div)=3 or count(./preceding-sibling::div)=4]/ancestor::div[1]/div[2]',
                        FindTextOptions::new()->contextNode($hotelInfoNode));
                }
                $h->hotel()->address($address);

                $phone = $tab->findText('.//div[contains(@class, "b-text_display-1")]/following-sibling::div[contains(text(), "Tel:")]',
                    FindTextOptions::new()->contextNode($hotelInfoNode)->preg('/Tel:\s*(.+)/iu')->allowNull(true));

                if (empty($phone)) {
                    $phone = $tab->findText('.//div[contains(text(), "Tel:")]',
                        FindTextOptions::new()->contextNode($hotelInfoNode)->preg('/Tel:\s*(.+)/iu')->allowNull(true));
                }
                // +49 89 904 219 1234​
                $phone = str_replace('–', '-', trim($phone, '​'));
                $phone = trim(preg_replace('/[+＋]/u', '+', $phone));
                $h->hotel()->phone($phone, true, true);

                $fax = $tab->findTextNullable('.//div[contains(@class, "b-text_display-1")]/following-sibling::div[contains(text(), "Fax:")]',
                    FindTextOptions::new()->contextNode($hotelInfoNode)->preg('/Fax:\s*(.+)/iu')->timeout(0));
                $h->hotel()->fax($fax, false, true);

                $checkIn = $tab->findText('.//dt[normalize-space(text()) = "Check-in"]/following-sibling::dd[1]',
                    FindTextOptions::new()->contextNode($roomNode));
                $checkInTrimmed = $this->findPreg('/^(.+?)\s*Invalid date/i', $checkIn);
                $h->booked()->checkIn($checkInTrimmed ? strtotime($checkInTrimmed) : strtotime($checkIn));
                $h->booked()->checkOut(strtotime($tab->findText('.//dt[normalize-space(text()) = "Check-out" or normalize-space(text()) = "Checkout"]/following-sibling::dd[1]',
                    FindTextOptions::new()->contextNode($roomNode))));

                $h->booked()->guests($tab->findText('.//dt[contains(text(), "Guests")]/following-sibling::dd[1]',
                    FindTextOptions::new()->contextNode($roomNode)->preg('/(\d+)\s*Guests?/i')), false, true);
                $h->general()->travellers(array_map(function ($elem) {
                    return beautifulName($elem);
                }, $tab->findTextAll('.//dt[contains(text(), "Name")]/following-sibling::dd[1]', FindTextOptions::new()->contextNode($roomNode))));

                $h->program()->accounts($tab->findTextAll('.//dt[contains(text(), "World of Hyatt Membership #")]/following-sibling::dd[1]',
                    FindTextOptions::new()->contextNode($roomNode)), false);

                $total = $tab->findTextNullable('.//span[@data-js = "cash-total-price"]/@data-price', FindTextOptions::new()->contextNode($roomNode));
                if (isset($total)) {
                    $h->price()->total(round($total, 2));
                    $h->price()->currency($tab->findTextNullable('.//span[@data-js = "cash-total-price"]/@data-currency',
                        FindTextOptions::new()->contextNode($roomNode)));

                    $cost = $tab->findTextNullable('.//span[@data-js = "subtotal-price"]/@data-price', FindTextOptions::new()->contextNode($roomNode));

                    if (isset($cost)) {
                        $h->price()->cost(round($cost, 2));
                    }
                    // Taxes
                    $tax = $tab->findTextNullable('.//span[@data-js = "taxes-fees-price"]/@data-price', FindTextOptions::new()->contextNode($roomNode));
                    if (isset($tax)) {
                        $h->price()->tax(round($tax, 2));
                    }
                }

                $h->price()->spentAwards($tab->findTextNullable('.//div[contains(text(), "Total Points")]/following-sibling::div[1]', FindTextOptions::new()->contextNode($roomNode)), false, true);
                $roomInfo = $tab->findText('.//div[contains(@class, "p-reservation-summary")]//dt[contains(text(), "Room")]/following-sibling::dd[1]', FindTextOptions::new()->contextNode($roomNode));
                $h->booked()->rooms($this->findPreg('/^\s*\((\d+)\)/i', $roomInfo));
                $r = $h->addRoom();

                if (!in_array($roomInfo, ['(1)'])) {
                    $r->setType($this->findPreg('/^\s*\(\d+\)\s*([^<]+)/i', $roomInfo));
                }

                // TODO attributes not implemented
                //$r->addRate($this->http->FindSingleNode('.//dt[contains(text(), "Rate")]/following-sibling::dd[1]', $roomNodes));
                //$r->setDescription($this->http->FindSingleNode('.//div[contains(@class, "room-details")]//div[contains(@class, "description")]', $roomNodes));

                /*$freeNight = 0;
                $rates = $tab->evaluateAll("(.//div[contains(text(), 'Total Cash Per Room')]/../following-sibling::div)[1]/div[contains(@class,'summary-row')]/div[contains(@class,'b-text_align-right')]/span",
                    $hotelNode);

                foreach ($rates as $rate) {
                    $r->addRate($rate->getInnerText());

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
                }*/

                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
            }
        }

        return true;
    }

    public function parseHistory(Tab $tab, Master $master, AccountOptions $accountOptions, ParseHistoryOptions $historyOptions): void
    {
        $startDate = $historyOptions->getStartDate();
        $this->logger->debug('[History start date: ' . ($startDate ? $startDate->format('Y/m/d H:i:s') : 'all') . ']');
        if (isset($startDate)) {
            $startDate = $startDate->format('U');
        } else {
            $startDate = 0;
        }
        $pastActivity = $this->getHistoryRows($tab);
        if (empty($pastActivity))
            return;
        $statement = $master->getStatement();
        $this->parseHistoryData($statement, $startDate, $pastActivity);
    }

    private function parseHistoryData(Statement $statement, $startDate, $pastActivity)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $pastActivities = $pastActivity->pastActivity ?? [];
        $this->logger->debug("Total " . ((is_array($pastActivities) || ($pastActivities instanceof Countable)) ? count($pastActivities) : 0) . " activity rows were found");
        $noTransactionDate = false;

        if (!$pastActivities) {
            $this->logger->error("no history found");

            return;
        }

        foreach ($pastActivities as $activity) {
            $row = [];
            // Transaction Date
            $transactionDateStr = $this->findPreg("/(.+)T?/", $activity->transaction->date ?? '');

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
            $checkOutDate = $this->findPreg("/(.+)T?/", $activity->stay->endDate ?? '');

            if (!empty($checkOutDate)) {
                $row['Check-out Date'] = strtotime($checkOutDate);
            }
            // Type and Description
            $transactionType = $activity->transaction->category;
            $transactionSubType = $activity->transaction->subCategory;
            $checkInDatePresent = true;
            $type = null;
            $description = null;

            switch ($transactionType) {
                case 'A':
                    $type = 'Points Redeemed';

                    if ($transactionSubType == 'FreeNight'
                        && $activity->transaction->totalAmount >= 0
                    ) {
                        $type = 'Free Night Award';
                        $checkInDatePresent = false;
                    }
                    $description = $activity->hotelDetail->name ?? '';

                    if ($description == '') {
                        $description = $activity->misc->description;
                    }

                    break;

                case 'B':
                    $type = 'Bonus';
                    $actionCode = $activity->misc->bonusCode;
                    $description = $this->historyCodeToLabel($actionCode) ?: 'Reward Bonus';

                    break;

                case 'F':
                    $type = 'Points earned';
                    $description = $activity->hotelDetail->name ?? '';

                    if ($description == '') {
                        $description = $activity->misc->description;
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
                    $description = $activity->hotelDetail->name;
                    $facilityName = $activity->misc->facilityName;

                    if (!empty($facilityName)) {
                        $description .= " / " . $facilityName;
                    }

                    break;

                case 'O':
                    $type = 'Adjustment';
                    $description = $activity->hotelDetail->name ?? '';
                    $facilityName = $activity->misc->facilityName;

                    if (!empty($facilityName)) {
                        $this->logger->notice("need to check history // RR");
                        $description .= " / " . $facilityName;
                    }

                    if ($description == '') {
                        $description = $activity->misc->adjustmentDescription;
                    }

                    break;

                case 'V':
                    $type = 'Stay';

                    if ($transactionSubType == 'Stay') {
                        $type = 'Stay - Points earned';
                    }
                    $description = $activity->misc->description;

                    break;

                case 'T':
                    $type = 'Gift';
                    $description = $activity->misc->description;

                    break;

                default:
                    $this->logger->notice("Unknown transaction type was found: {$transactionType}");
                    $this->logger->debug(var_export($activity, true), ["pre" => true]);

                    break;
            }// switch ($transactionType)
            $row['Description'] = $description;
            $row['Type'] = $type;
            // Check-in Date
            if ($checkInDatePresent) {
                $checkIn = $this->findPreg("/(.+)T/", $activity->stay->startDate);

                if ($checkIn) {
                    $row['Check-in Date'] = strtotime($checkIn);
                }
            }
            // Bonus and Points
            $totalPoints = $activity->transaction->totalAmount;

            if ($type == 'Bonus') {
                $row['Bonus'] = $totalPoints;
            } else {
                $row['Points'] = $totalPoints;
            }
            $statement->addActivityRow($row);
        }// foreach ($pastActivities as $activity)

        if ($noTransactionDate) {
            $this->logger->notice('check history items with no transaction date');
        }
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
        return $labels[$code] ?? null;
    }
}
