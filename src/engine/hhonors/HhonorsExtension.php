<?php

namespace AwardWallet\Engine\hhonors;

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
use AwardWallet\ExtensionWorker\QuerySelectorOptions;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Common\Statement;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;
use UnexpectedJavascriptException;

class HhonorsExtension
    extends AbstractParser
    implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface, ParseHistoryInterface
{
    use TextTrait;

    private array $activitiesSummary = [];
    private object $wso2AuthToken;
    private int $itCount = 0;
    private int $skipPastCount = 0;
    /**
     * @var true
     */
    private bool $logout = false;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.hilton.com/en/hilton-honors/guest/my-account/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        sleep(3);
        $loginFieldOrSuccess = $tab->evaluate('//form//input[@name = "username"] | //span[starts-with(text(),"Hilton Honors #")]');
        return str_contains($loginFieldOrSuccess->getInnerText(), 'Hilton Honors #');
    }

    public function getLoginId(Tab $tab): string
    {
        /*$loginId = $tab->findText(
            '//span[starts-with(text(),"Hilton Honors #")]',
            FindTextOptions::new()->nonEmptyString()->preg('/#\s*(\d+)/')->allowNull(true)->timeout(20)
        );
        $this->logger->debug("getLoginId: $loginId");*/
        $loginId = $this->findPreg('/"hhonorsNumber":"(\w+)",/', $tab->getHtml());

        return $loginId ?? '';
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $cookies = $tab->getCookies();
        $wso2AuthToken = json_decode($cookies['wso2AuthToken']);
        $options = [
            'method'  => 'post',
            'headers' => [
                'Accept'        => '*/*',
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$wso2AuthToken->accessToken}"
            ],
        ];
        $json = $tab->fetch("https://www.hilton.com/dx-customer/auth/guests/logout?appName=dx_guests_app", $options)->body;
        $this->logger->info($json);
        sleep(1);
        $tab->gotoUrl('https://www.hilton.com/en/hilton-honors/guest/my-account/');
        $tab->evaluate('//form//input[@name = "username"]');
        $this->logout = true;
    }

    private int $attempt = -1;
    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $this->attempt++;
        if ($this->attempt > 2) {
            return new LoginResult(false);
        }
        $tab->saveScreenshot();
        $login = $tab->evaluate('//input[@name = "username"]');
        $login->setValue($credentials->getLogin());
        if ($this->logout) {
            $login->setValue('');
            $login->setValue($credentials->getLogin());
            $this->logout = false;
        }

        $password = $tab->evaluate('//input[@name = "password"]');
        $password->setValue('');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//form//button[@type="submit"]')->click();
        sleep(3);
        if ($this->attempt > 0) {
            $errorOrTitle = $tab->evaluate('//span[starts-with(text(),"Hilton Honors #")] | //iframe[@id="sec-cpt-if"]');
        } else {
            $errorOrTitle = $tab->evaluate('//span[starts-with(text(),"Hilton Honors #")] | //span[@data-e2e = "errorText"] | //iframe[@id="sec-cpt-if"]');
        }

        if ($errorOrTitle->getAttribute('id') === 'sec-cpt-if') {
            $this->logger->info('sleep(~30)');
            $this->waitFor(function (Tab $tab) {
                return !$tab->evaluate('//iframe[@id="sec-cpt-if"]',
                    EvaluateOptions::new()->allowNull(true)->timeout(0));
            }, $tab, 35);
            sleep(1);
            $btn = $tab->evaluate('//form//button[@type="submit"]', EvaluateOptions::new()->timeout(0));
            if ($btn) {
                $btn->click();
                sleep(3);
                $errorOrTitle = $tab->evaluate('//span[starts-with(text(),"Hilton Honors #")] | //span[@data-e2e = "errorText"]');
            }
        }
        if (stristr($errorOrTitle->getInnerText(), 'We need your username and password to login.')) {
            $this->logger->error('login retry');
            sleep(1);
            return $this->login($tab, $credentials);
        } elseif (stripos($errorOrTitle->getInnerText(), 'Hilton Honors #') !== false) {
            $this->logger->info('logged in');
            return new LoginResult(true);
        } else {
            $this->logger->info('error logging in');
            $error = $errorOrTitle->getInnerText();
            if (stristr($error, "Something went wrong, and your request wasn't submitted. Please try again later.")
                || stristr($error, "Please try again. Be careful: too many attempts will lock your account.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }
            return new LoginResult(false);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        $myAccount = $this->getMyAccount($tab);

        // Name
        $statement->addProperty('Name', $myAccount->data->guest->personalinfo->name->firstName ?? null);
        // Hilton Honors number
        $statement->addProperty('Number', $myAccount->data->guest->hhonors->hhonorsNumber ?? null);
        // Current tier
        $statement->addProperty('Status', $myAccount->data->guest->hhonors->summary->tierName ?? null);
        // Qualification Period
        $statement->addProperty('YearBegins', strtotime("1 JAN"));
        // Stays
//        $number = $tab->findText('//span[contains(text(),"current tier")]/preceding-sibling::span', ['notEmptyString' => false]);
//        $statement->addProperty('Status', $number);
        // Request BrandGuest
        // Nights
        $statement->addProperty('Nights', $myAccount->data->guest->hhonors->summary->qualifiedNights ?? null);
        // BasePoints
        $statement->addProperty('BasePoints', $myAccount->data->guest->hhonors->summary->qualifiedPointsFmt ?? null);
        // To Maintain Current Level
        $statement->addProperty('ToMaintainCurrentLevel', $myAccount->data->guest->hhonors->summary->qualifiedNightsMaint ?? null);
        // To Reach Next Level
        $statement->addProperty('ToReachNextLevel', $myAccount->data->guest->hhonors->summary->qualifiedNightsNext ?? null);
        // Points To Next Level
        $statement->addProperty('PointsToNextLevel', $myAccount->data->guest->hhonors->summary->qualifiedPointsNextFmt ?? null);

        // Balance - Current Points
       /* $balance = $tab->findText('(//div[@data-testid="honorsPointsBlock"]/span)[1]', FindTextOptions::new()->preg('/([\d,.]+)/')->allowNull(true));
        $statement->setBalance(str_replace(',', '', $balance));*/

        //if (empty($balance) && isset($myAccount)) {
        $this->logger->debug('Balance is not found');
        $balance = str_replace(',', '', $myAccount->data->guest->hhonors->summary->totalPointsFmt ?? null);
        if (!empty($balance)) {
            $statement->setBalance($balance);
            $this->logger->debug("totalPoints === '{$statement->getBalance()}'");
        } elseif ($balance === '') {
            $this->logger->debug("provider bug fix balance === '$balance'");
            $statement->setBalance(0);
        }

        // refs #19889

        //}
        if ($statement->getNoBalance()) {
            $this->logger->debug('Balance is not found');
            // TODO
        }
        $this->logger->info('Free Night Rewards', ['Header' => 3]);
        $availableCoupons = $myAccount->data->guest->hhonors->amexCoupons->available ?? [];
        $this->logger->debug('>>> Free Night Rewards: Ready to use (' . count($availableCoupons) . ')');
        foreach ($availableCoupons as $coupon) {
            $code = str_replace('••••• ', '', $coupon->codeMasked);
            $exp = strtotime(str_replace('T00:00:00', '', $coupon->endDate));
            $displayName = "$coupon->offerName Certificate # $coupon->codeMasked";
            $statement->addSubAccount([
                'Code'           => "hhonorsAmexFreeNightRewards$code$exp",
                'DisplayName'    => $displayName,
                'Balance'        => $coupon->points,
                'Number' => $coupon->codeMasked,
                'ExpirationDate' => $exp,
            ]);
        }
        $heldCoupons = $myAccount->data->guest->hhonors->amexCoupons->held ?? [];
        $this->logger->debug('>>> Free Night Rewards: Reserved for upcoming stay (' . count($heldCoupons) . ')');
        foreach ($heldCoupons as $coupon) {
            $code = str_replace('••••• ', '', $coupon->codeMasked);
            $exp = strtotime(str_replace('T00:00:00', '', $coupon->endDate));
            $displayName = "Reserved $coupon->offerName Certificate # $coupon->codeMasked";
            $statement->addSubAccount([
                'Code'           => "hhonorsAmexFreeNightRewardsReserved$code$exp",
                'DisplayName'    => $displayName,
                'Balance'        => $coupon->points,
                'Number' => $coupon->codeMasked,
                'ExpirationDate' => $exp,
            ]);
        }


        $cookies = $tab->getCookies();
        $this->wso2AuthToken = json_decode($cookies['wso2AuthToken']);
        $activitySummary = $this->getActivitySummary($tab);
        $this->activitiesSummary = $activitySummary->data->guest->activitySummaryOptions->guestActivitiesSummary ?? [];
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $this->logger->debug('History: ' . count($this->activitiesSummary) . ' transactions were found');
        $this->logger->debug('Status: ' . ($statement->getProperties()['Status'] ?? null));

        if (isset($statement->getProperties()['Status']) && $statement->getProperties()['Status'] != 'Lifetime Diamond') {
            foreach ($this->activitiesSummary as $transaction) {
                if (isset($transaction->departureDate) && in_array(strtolower($transaction->guestActivityType),
                        ['past', 'other']) &&
                    // https://redmine.awardwallet.com/issues/21728#note-8
                    $transaction->totalPoints != 0
                ) {
                    $departureDate = $transaction->departureDate;
                    $departureDateTime = strtotime($departureDate);
                    if (!isset($maxTime) || $departureDateTime > $maxTime) {
                        $maxTime = $departureDateTime;
                        $exp = strtotime("+24 months", $departureDateTime);
                        if ($exp) {
                            $statement->addProperty('LastActivity', $departureDateTime);
                            $statement->setExpirationDate($exp);
                        }
                    }
                }
            }
        } elseif (isset($this->Properties['Status']) && $this->Properties['Status'] == 'Lifetime Diamond') {
            $statement->addProperty('AccountExpirationDate', false);
            $statement->addProperty('AccountExpirationWarning', 'do not expire with elite status');
            $statement->addProperty('ClearExpirationDate', 'Y');
        }
    }

    private function getMyAccount(Tab $tab)
    {
        $this->logger->notice(__METHOD__);
        $cookies = $tab->getCookies();
        $wso2AuthToken = json_decode($cookies['wso2AuthToken']);
        $options = [
            'method'  => 'post',
            'headers' => [
                'Accept'        => '*/*',
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$wso2AuthToken->accessToken}",
            ],
            'body' => '{"query":"query guest_hotel_MyAccount($guestId: BigInt!, $language: String!) {\n  guest(guestId: $guestId, language: $language) {\n    id: guestId\n    guestId\n    personalinfo {\n      name {\n        firstName @toTitleCase\n      }\n      emails {\n        validated\n      }\n      phones {\n        validated\n      }\n      hasUSAddress: hasAddressWithCountry(countryCodes: [\"US\"])\n    }\n    hhonors {\n      hhonorsNumber\n      isTeamMember\n      isLifetimeDiamond\n      isSMBMember\n      isOwner\n      isOwnerHGV\n      isAmexCardHolder\n      summary {\n        tier\n        tierName\n        nextTier\n        requalTier\n        pointsExpiration\n        tierExpiration\n        nextTierName\n        totalPointsFmt\n        qualifiedNights\n        qualifiedNightsNext\n        qualifiedPoints\n        qualifiedPointsNext\n        qualifiedPointsFmt\n        qualifiedPointsNextFmt\n        qualifiedNightsMaint\n        rolledOverNights\n        showRequalMaintainMessage\n        showRequalDowngradeMessage\n        milestones {\n          applicableNights\n          bonusPoints\n          bonusPointsFmt\n          bonusPointsNext\n          bonusPointsNextFmt\n          maxBonusPoints\n          maxBonusPointsFmt\n          maxNights\n          nightsNext\n          showMilestoneBonusMessage\n        }\n      }\n      amexCoupons {\n        _available {\n          totalSize\n        }\n        _held {\n          totalSize\n        }\n        _used {\n          totalSize\n        }\n        available(sort: {by: startDate, order: asc}) {\n          checkInDate\n          checkOutDate\n          codeMasked\n          checkOutDateFmt(language: $language)\n          endDate\n          endDateFmt(language: $language)\n          location\n          numberOfNights\n          offerName\n          points\n          rewardType\n          startDate\n          status\n          hotel {\n            name\n            images {\n              master(imageVariant: honorsPropertyImageThumbnail) {\n                url\n                altText\n              }\n            }\n          }\n        }\n        held {\n          checkInDate\n          checkOutDate\n          codeMasked\n          checkOutDateFmt(language: $language)\n          endDate\n          endDateFmt(language: $language)\n          location\n          numberOfNights\n          offerName\n          points\n          rewardType\n          startDate\n          status\n          hotel {\n            name\n            images {\n              master(imageVariant: honorsPropertyImageThumbnail) {\n                url\n                altText\n              }\n            }\n          }\n        }\n        used {\n          checkInDate\n          checkOutDate\n          codeMasked\n          checkOutDateFmt(language: $language)\n          endDate\n          endDateFmt(language: $language)\n          location\n          numberOfNights\n          offerName\n          points\n          rewardType\n          startDate\n          status\n          hotel {\n            name\n            images {\n              master(imageVariant: honorsPropertyImageThumbnail) {\n                url\n                altText\n              }\n            }\n          }\n        }\n      }\n    }\n  }\n}","operationName":"guest_hotel_MyAccount","variables":{"guestId":'.$wso2AuthToken->guestId.',"language":"en"}}'
        ];

        $json = $tab->fetch('https://www.hilton.com/graphql/customer?appName=dx_guests_app&operationName=guest_hotel_MyAccount&originalOpName=guest_hotel_MyAccount&bl=en',
            $options)->body;
        $this->logger->info($json);
        return json_decode($json);
    }

    private function getActivitySummary(Tab $tab)
    {
        $this->logger->notice(__METHOD__);
        $startDate = date("Y-m-d", strtotime("-1 year"));
        $endDate = date("Y-m-d", strtotime("+1 year"));

        $options = [
            'method'  => 'post',
            'headers' => [
                'Accept'        => '*/*',
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$this->wso2AuthToken->accessToken}",
            ],
            'body' => '{"query":"query guest_guestActivitySummaryOptions_hotel($guestId: BigInt!, $language: String!, $startDate: String!, $endDate: String!, $guestActivityTypes: [GuestActivityType], $sort: [StayHHonorsActivitySummarySortInput!], $first: Int, $after: String, $guestActivityDisplayType: GuestActivityDisplayType) {\n  guest(guestId: $guestId, language: $language) {\n    id: guestId\n    guestId\n    hhonors {\n      summary {\n        tierName\n        totalPoints\n      }\n    }\n    activitySummaryOptions(\n      input: {groupMultiRoomStays: true, startDate: $startDate, endDate: $endDate, guestActivityTypes: $guestActivityTypes, guestActivityDisplayType: $guestActivityDisplayType}\n    ) {\n      _guestActivitiesSummary {\n        totalSize\n        size\n        start\n        end\n        nextCursor\n        prevCursor\n      }\n      guestActivitiesSummary(sort: $sort, first: $first, after: $after) {\n        ...StayActivitySummary\n      }\n    }\n  }\n}\n\n      \n    fragment StayActivitySummary on StayHHonorsActivitySummary {\n  numRooms\n  _id\n  stayId\n  arrivalDate\n  departureDate\n  hotelName\n  desc\n  descFmt: desc @toTitleCase\n  guestActivityType\n  ctyhocn\n  brandCode\n  roomDetails(sort: {by: roomSeries, order: asc}) {\n    ...StayRoomDetails\n    transactions {\n      ...StayTransaction\n    }\n  }\n  transactions {\n    ...StayTransaction\n  }\n  bookAgainUrl\n  checkinUrl\n  confNumber\n  cxlNumber\n  timeframe\n  lengthOfStay\n  viewFolioUrl\n  viewOrEditReservationUrl\n  earnedPoints\n  earnedPointsFmt\n  totalPoints\n  totalPointsFmt\n  usedPoints\n  usedPointsFmt\n}\n    \n    fragment StayRoomDetails on StayHHonorsActivityRoomDetail {\n  _id\n  bonusPoints\n  bonusPointsFmt\n  cxlNumber\n  guestActivityType\n  roomSeries\n  roomTypeName\n  roomTypeNameFmt: roomTypeName @truncate(byWords: true, length: 3)\n  bookAgainUrl\n  usedPointsFmt(language: $language)\n  transactions {\n    transactionId\n    transactionType\n    partnerName\n    baseEarningOption\n    guestActivityPointsType\n    description\n    descriptionFmt: description @toTitleCase\n    basePoints\n    basePointsFmt\n    bonusPoints\n    bonusPointsFmt\n    status\n    usedPointsFmt(language: $language)\n  }\n  totalPoints\n  totalPointsFmt(language: $language)\n  viewFolioUrl(type: link)\n}\n    \n\n    fragment StayTransaction on StayHHonorsTransaction {\n  transactionId\n  transactionType\n  partnerName\n  baseEarningOption\n  guestActivityPointsType\n  description\n  descriptionFmt: description @toTitleCase\n  basePoints\n  basePointsFmt\n  bonusPoints\n  bonusPointsFmt\n  earnedPoints\n  earnedPointsFmt\n  status\n  usedPoints\n  usedPointsFmt(language: $language)\n  hiltonForBusiness {\n    _id\n    h4bFlag\n    h4bName\n  }\n}\n    ","operationName":"guest_guestActivitySummaryOptions_hotel","variables":{"guestId":'.$this->wso2AuthToken->guestId.',"language":"en","startDate":"'.$startDate.'","endDate":"'.$endDate.'","after":"","first":100,"guestActivityDisplayType":"bankStatement","guestActivityTypes":["cancelled","past","upcoming","other"]}}'
        ];

        $json = $tab->fetch('https://www.hilton.com/graphql/customer?appName=dx_guests_app&operationName=guest_guestActivitySummaryOptions_hotel&originalOpName=guest_guestActivitySummaryOptions_hotel&bl=en',
            $options)->body;
        //$this->logger->info($json);
        return json_decode($json);
    }

    public function parseItineraries(Tab $tab, Master $master, AccountOptions $options, ParseItinerariesOptions $parseOptions) : void
    {
        $this->logger->debug("isParsePastItineraries {$parseOptions->isParsePastItineraries()}");
        $activitiesSummaryText = json_encode($this->activitiesSummary);
        $upcoming = [];
        $cancelled = [];
        $past = [];
        $this->skipPastCount = 0;

        foreach ($this->activitiesSummary as $activity) {
            $type = $activity->guestActivityType;
            if ($type === 'upcoming') {
                $upcoming[] = $activity;
            } elseif ($type === 'cancelled') {
                $cancelled[] = $activity;
            } elseif ($type === 'past') {
                $past[] = $activity;
            } elseif ($type === 'other') {
                $this->logger->notice('Skipping type other');
            } else {
                $this->logger->notice("New type: {$type}");
            }
        }

        $cntUpcoming = count($upcoming);
        $cntCancelled = count($cancelled);
        $cntPast = count($past);
        $this->logger->info(sprintf('Found %s upcoming itineraries', $cntUpcoming));
        $this->logger->info(sprintf('Found %s cancelled itineraries', $cntCancelled));
        $this->logger->info(sprintf('Found %s past itineraries', $cntPast));
        if (empty($activities) && ($cntUpcoming + $cntCancelled + $cntPast) == 0 && $this->findPreg('/^\[\]$/', $activitiesSummaryText)) {
            $master->setNoItineraries(true);

            return;
        }

        $this->logger->info("Parse main info for itineraries (total: {$cntUpcoming})", ['Header' => 3]);

        foreach ($upcoming as $i => $activity) {
            if ($i >= 50) {
                $this->logger->debug("Save $i reservations");

                break;
            }

            $reservationData = $this->getReservationData($tab, $activity);

            if ($reservationData) {
                $this->parseItinerary($tab, $master, $reservationData, $parseOptions->isParsePastItineraries());
            } else {
                $this->parseMinimalItinerary($tab, $master, $activity, $parseOptions->isParsePastItineraries());
            }
        }
        $this->logger->info("Parse info for cancelled itineraries (total: {$cntCancelled})", ['Header' => 3]);

        foreach ($cancelled as $activity) {
            $this->parseMinimalItinerary($tab, $master, $activity, $cntCancelled <= 20, $parseOptions->isParsePastItineraries());
        }

        if ($parseOptions->isParsePastItineraries()) {
            $this->logger->info("Parse info for past itineraries (total: {$cntPast})", ['Header' => 3]);

            foreach ($past as $activity) {
                $this->parseMinimalItinerary($tab, $master, $activity, false, $parseOptions->isParsePastItineraries());
            }
        } else {
            // cause not interest
            $cntPast = 0;
        }
        $this->logger->debug("cntSkippedPast " . $this->skipPastCount);
        $this->logger->debug("cntUpcoming " . $cntUpcoming);
        $this->logger->debug("cntCancelled " . $cntCancelled);
        $this->logger->debug("cntPast " . $cntPast);
        // NoItineraries
        if (!empty($activities) && count($master->getItineraries()) === 0
            && $cntUpcoming + $cntCancelled + $cntPast === $this->skipPastCount
        ) {
            $master->setNoItineraries(true);
        }
    }

    private function getReservationData(Tab $tab, $item)
    {
        $this->logger->notice(__METHOD__);
        $confNumber = $item->confNumber;
        $lastName = $this->findPreg('/lastName=(.+)/', $item->viewOrEditReservationUrl);
        $this->logger->debug("[lastName]: '$lastName'");
        $this->logger->debug("'[confNumber]: '$confNumber'");
        $this->logger->debug("'[confNo]: '$confNumber'");

        if (!$lastName) {
            $this->logger->error('lastName is missing');

            return null;
        }
        $arrivalDate = $item->arrivalDate;
        $this->logger->error("'[arrivalDate]: '$arrivalDate'");

        try {
            $result = $this->sendReservationRequest($tab, $confNumber, $lastName, $arrivalDate);
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);

            sleep(5);
            $result = $this->sendReservationRequest($tab, $confNumber, $lastName, $arrivalDate);
        }

        $message = $result->errors[0]->message ?? null;

        if ($message && in_array($message, ['Gateway Timeout', 'Service Unavailable'])) {
            sleep(5);
            $this->logger->error("[Retrying]: {$message}");
            $result = $this->sendReservationRequest($tab, $confNumber, $lastName, $arrivalDate);
        }

        return $result;
    }

    private function sendReservationRequest(Tab $tab, $confNumber, $lastName, $arrivalDate)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->wso2AuthToken->guestId)) {
            $this->logger->error('guestId missing');

            return null;
        }

        if (!isset($this->wso2AuthToken->accessToken)) {
            $this->logger->error('auth token missing');

            return null;
        }
        $options = [
            'method'  => 'post',
            'headers' => [
                'Accept'        => '*/*',
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$this->wso2AuthToken->accessToken}",
            ],
            'body' => '{"query":"query reservation($confNumber: String!, $language: String!, $guestId: BigInt, $lastName: String!, $arrivalDate: String!) {\n  reservation(\n    confNumber: $confNumber\n    language: $language\n    authInput: {guestId: $guestId, lastName: $lastName, arrivalDate: $arrivalDate}\n  ) {\n    ...RESERVATION_FRAGMENT\n  }\n}\n\n      \n    fragment RESERVATION_FRAGMENT on Reservation {\n  addOnsResModifyEligible\n  confNumber\n  arrivalDate\n  departureDate\n  cancelEligible\n  modifyEligible\n  cxlNumber\n  restricted\n  adjoiningRoomStay\n  adjoiningRoomsFailure\n  scaRequired\n  autoUpgradedStay\n  showAutoUpgradeIndicator\n  specialRateOptions {\n    corporateId\n    groupCode\n    hhonors\n    pnd\n    promoCode\n    travelAgent\n    familyAndFriends\n    teamMember\n    owner\n    ownerHGV\n    smb\n  }\n  clientAccounts {\n    clientId\n    clientType\n    clientName\n    programAccountId\n  }\n  comments {\n    generalInfo\n  }\n  disclaimer {\n    diamond48\n    fullPrePayNonRefundable\n    hgfConfirmation\n    hgvMaxTermsAndConditions\n    hhonorsCancellationCharges\n    hhonorsPointsDeduction\n    hhonorsPrintedConfirmation\n    lengthOfStay\n    rightToCancel\n    totalRate\n    teamMemberEligibility\n    smbEligibility\n    vatCharge\n  }\n  certificates {\n    totalPoints\n    totalPointsFmt\n  }\n  cost {\n    currency {\n      currencyCode\n      currencySymbol\n      description\n    }\n    roomRevUSD: totalAmountBeforeTax(currencyCode: \"USD\")\n    totalAddOnsAmount\n    totalAddOnsAmountFmt\n    totalAmountBeforeTax\n    totalAmountAfterTaxFmt: guestTotalCostAfterTaxFmt\n    totalAmountAfterTax: guestTotalCostAfterTax\n    totalAmountBeforeTaxFmt\n    totalServiceCharges\n    totalServiceChargesFmt\n    totalServiceChargesUSD: totalServiceCharges(currencyCode: \"USD\")\n    totalTaxes\n    totalTaxesFmt\n    totalTaxesUSD: totalTaxes(currencyCode: \"USD\")\n  }\n  guestBenefits {\n    foodAndBeverageCreditBenefit {\n      description\n      heading\n      linkLabel\n      linkUrl\n    }\n  }\n  guarantee {\n    depositRequired\n    nonRefundable\n    cxlPolicyCode\n    cxlPolicyDesc\n    guarPolicyCode\n    guarPolicyDesc\n    guarMethodCode\n    taxDisclaimers {\n      text\n      title\n    }\n    disclaimer {\n      legal\n    }\n    paymentCard {\n      cardCode\n      cardName\n      cardNumberMasked(format: masked)\n      cardExpireDate\n      cardPaymentType\n      expireDate: cardExpireDateFmt(format: \"MMM yyyy\")\n      expireDateFull: cardExpireDateFmt(format: \"MMMM yyyy\")\n      expired\n      policy {\n        bankValidationMsg\n      }\n    }\n    deposit {\n      amount\n    }\n    taxDisclaimers {\n      text\n      title\n    }\n  }\n  guest {\n    guestId\n    tier\n    name {\n      firstName\n      lastName\n      nameFmt\n    }\n    emails {\n      emailAddressMasked\n      emailType\n    }\n    addresses {\n      addressLine1\n      addressLine2\n      city\n      country\n      state\n      postalCode\n      addressFmt\n      addressType\n    }\n    hhonorsNumber\n    phones {\n      phoneNumberMasked(format: masked)\n      phoneType\n    }\n  }\n  propCode\n  nor1Upgrade(provider: \"DOHWR\") {\n    content {\n      button\n      description\n      firstName\n      title\n    }\n    offerLink\n    requested\n    success\n  }\n  notifications {\n    subType\n    text\n    type\n  }\n  requests {\n    specialRequests {\n      pets\n      servicePets\n    }\n  }\n  rooms {\n    gnrNumber\n    resCreateDateFmt(format: \"yyyy-MM-dd\")\n    addOns {\n      addOnCost {\n        amountAfterTax\n        amountAfterTaxFmt\n      }\n      addOnDetails {\n        addOnAvailType\n        addOnDescription\n        addOnCode\n        addOnName\n        addOnPricing\n        amountAfterTax\n        amountAfterTaxFmt\n        averageDailyRate\n        averageDailyRateFmt\n        categoryCode\n        counts {\n          numAddOns\n          fulfillmentDate\n          rate\n          rateFmt\n        }\n        numAddOnDays\n      }\n    }\n    additionalNames {\n      firstName\n      lastName\n    }\n    certificates {\n      certNumber\n      totalPoints\n      totalPointsFmt\n    }\n    numAdults\n    numChildren\n    childAges\n    autoUpgradedStay\n    isStayUpsell\n    isStayUpsellOverAutoUpgrade\n    priorRoomType {\n      roomTypeName\n    }\n    cost {\n      currency {\n        currencyCode\n        currencySymbol\n        description\n      }\n      amountAfterTax: guestTotalCostAfterTax\n      amountAfterTaxFmt: guestTotalCostAfterTaxFmt\n      amountBeforeTax\n      amountBeforeTaxFmt\n      amountBeforeTaxFmtTrunc: amountAfterTaxFmt(decimal: 0, strategy: trunc)\n      serviceChargeFeeType\n      serviceChargePeriods {\n        serviceCharges {\n          amount\n          amountFmt\n          description\n        }\n      }\n      totalServiceCharges\n      totalServiceChargesFmt\n      totalTaxes\n      totalTaxesFmt\n      rateDetails(perNight: true) {\n        effectiveDateFmt(format: \"medium\")\n        effectiveDateFmtAda: effectiveDateFmt(format: \"long\")\n        rateAmount\n        rateAmountFmt\n        rateAmountFmtTrunc: rateAmountFmt(decimal: 0, strategy: trunc)\n      }\n      upgradedAmount\n      upgradedAmountFmt\n    }\n    guarantee {\n      cxlPolicyCode\n      cxlPolicyDesc\n      guarPolicyCode\n      guarPolicyDesc\n    }\n    numAdults\n    numChildren\n    ratePlan {\n      confidentialRates\n      hhonorsMembershipRequired\n      advancePurchase\n      promoCode\n      disclaimer {\n        diamond48\n        fullPrePayNonRefundable\n        hhonorsCancellationCharges\n        hhonorsPointsDeduction\n        hhonorsPrintedConfirmation\n        lengthOfStay\n        rightToCancel\n        totalRate\n      }\n      ratePlanCode\n      ratePlanName\n      ratePlanDesc\n      specialRateType\n      serviceChargesAndTaxesIncluded\n    }\n    roomType {\n      adaAccessibleRoom\n      roomTypeCode\n      roomTypeName\n      roomTypeDesc\n      roomOccupancy\n    }\n  }\n  taxPeriods {\n    taxes {\n      description\n    }\n  }\n  paymentOptions {\n    cardOptions {\n      policy {\n        bankValidationMsg\n      }\n    }\n  }\n  totalNumAdults\n  totalNumChildren\n  totalNumRooms\n  unlimitedRewardsNumber\n}\n    ","operationName":"reservation","variables":{"confNumber":"'.$confNumber.'","language":"en","guestId":'.$this->wso2AuthToken->guestId.',"lastName":"'.$lastName.'","arrivalDate":"'.$arrivalDate.'"}}'
        ];
        $json = $tab->fetch('https://www.hilton.com/graphql/customer?appName=dx-res-ui&operationName=reservation&originalOpName=getReservation&bl=en',
            $options)->body;
        $this->logger->info($json);
        return json_decode($json);
    }

    private function parseItinerary(Tab $tab, Master $master, $data, bool $isParsePastItineraries = false): void
    {
        $this->logger->notice(__METHOD__);
        $reservation = $data->data->reservation;

        if (!$reservation) {
            $this->logger->error('check parse itinerary');

            return;
        }
        $departureDate = $reservation->departureDate ?? '';
        $isPast = strtotime($departureDate) < strtotime('-1 day', time());

        if ($isPast && !$isParsePastItineraries) {
            $this->logger->info('Skipping hotel: in the past');
            $this->skipPastCount++;
            return;
        }
        $arrivalDate = $reservation->arrivalDate ?? '';

        if ($arrivalDate && $arrivalDate === $departureDate) {
            $this->logger->error('Skipping hotel: the same arrival / departure dates');
            $this->skipPastCount++;
            return;
        }

        $hotel = $master->createHotel();
        // confirmation number
        $conf = $reservation->confNumber ?? null;
        //$this->logger->info("[%s] Parse Itinerary #$conf", ['Header' => 3]);
        $this->logger->info(sprintf("[%s] Parse Itinerary #%s", $this->itCount++, $conf), ['Header' => 3]);
        $hotel->addConfirmationNumber($conf, 'Confirmation number', true);
        // check in date
        $hotel->setCheckInDate(strtotime($arrivalDate));
        // check out date
        $hotel->setCheckOutDate(strtotime($departureDate));
        // cancellation policy
        $hotel->setCancellation($reservation->disclaimer->hhonorsCancellationCharges, false, true);

        if ($reservation->cost->totalTaxes && strpos($reservation->cost->totalTaxes, '-') === false) {
            // total
            $hotel->obtainPrice()->setTotal($reservation->cost->totalAmountBeforeTax);
            // tax
            $hotel->obtainPrice()->setTax($reservation->cost->totalTaxes);
            // currency
            $hotel->obtainPrice()->setCurrencyCode($reservation->cost->currency->currencyCode);
            // spent awards
            $hotel->obtainPrice()->setSpentAwards($reservation->cost->certificates->totalPointsFmt ?? null, false, true);
        }
        // rooms
        foreach ($reservation->rooms as $key => $roomData) {
            $room = $hotel->addRoom();
            $rateDetails = $roomData->cost->rateDetails;

            // the number of entries in the rates (1) does not match the number of nights (0)
            if ($hotel->getCheckInDate() != $hotel->getCheckOutDate()) {
                foreach ($rateDetails as $rateDetail) {
                    // TODO: Different number of rates for each room, because of this an error occurs
                    if ($key > 0 && count($hotel->getRooms()[0]->getRates()) != count($rateDetails)) {
                        continue;
                    }
                    $room->addRate($rateDetail->rateAmountFmt);
                }
            }

            // type
            $room->setType($roomData->roomType->roomTypeName);
            // description
            $desc = $roomData->roomType->roomTypeDesc;

            if ($desc) {
                $desc = preg_replace('/\s+/', ' ', strip_tags($desc));
                $room->setDescription($desc ? trim($desc) : null, false, true);
            }
            // cancellation policy
            $cancelation =$roomData->guarantee->cxlPolicyDesc;

            if (empty($hotel->getCancellation()) && !empty($cancelation)) {
                $hotel->setCancellation($cancelation);
            }
        }

        $hotel->parseNonRefundable('/If you cancel for any reason, attempt to modify this reservation, or do not arrive on your specified check-in date, your payment is non-refundable/');
        // Deadline
        $this->detectDeadLine($hotel);
        // guest count
        $hotel->setGuestCount($reservation->totalNumAdults ?? null);
        // kids count
        $hotel->setKidsCount($reservation->totalNumChildren ?? null, false, true);
        // hotel name
        $hotelData = $this->getHotelData($tab, $reservation->propCode ?? null);

        if (isset($hotelData)) {
            $skip = $this->addHotelData($hotel, $hotelData, $arrivalDate, $departureDate);

            if ($skip) {
                $this->logger->error('Skipping hotel: the same arrival / departure dates');
                $master->removeItinerary($hotel);
                $this->skipPastCount++;

                return;
            }
        }

        $this->logger->info('Parsed Hotel:');
        $this->logger->info(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function parseMinimalItinerary(Tab $tab, Master $master, $data, ?bool $withDetails = true, bool $isParsePastItineraries = false)
    {
        $this->logger->notice(__METHOD__);

        if (!$data) {
            $this->logger->error('check parse minimal itinerary');

            return;
        }
        $departureDate = $data->departureDate;
        $isPast = strtotime($departureDate) < strtotime('-1 day');

        if ($isPast && !$isParsePastItineraries) {
            $this->logger->info('Skipping hotel: in the past');
            $this->skipPastCount++;

            return;
        }
        $cancelled = null;
        // cancelled
        if ($data->guestActivityType === 'cancelled') {
            $cancelled = true;
        }
        $arrivalDate = $data->arrivalDate;

        if ($arrivalDate && $arrivalDate === $departureDate) {
            $this->logger->error('Skipping hotel: the same arrival / departure dates');

            if ($cancelled) {
                $this->skipPastCount++;
            }

            return;
        }
        $propCode = $this->findPreg('/ctyhocn=(\w+)/', $data->bookAgainUrl);
        $rooms = $data->roomDetails?? [];

        if (!$propCode) {
            $propCodes = [];

            foreach ($rooms as $room) {
                $propCodes[] = $this->findPreg('/ctyhocn=(\w+)/', $room->bookAgainUrl);
            }
            $propCodes = array_unique($propCodes);

            if (count($propCodes) === 1) {
                $propCode = array_shift($propCodes);
            }
        }

        if (!$propCode && !$cancelled) {
            $this->logger->error('Skipping hotel: property code is missing');

            return;
        }
        $hotel = $master->createHotel();

        if (isset($cancelled)) {
            $hotel->setCancelled(true);
        }
        // check in date
        $hotel->setCheckInDate(strtotime($arrivalDate));
        // check out date
        $hotel->setCheckOutDate(strtotime($departureDate));

        if ($propCode && $withDetails) {
            // hotel name, address, check in / check out times
            $hotelData = $this->getHotelData($tab, $propCode);

            if ($hotelData) {
                $skip = $this->addHotelData($hotel, $hotelData, $arrivalDate, $departureDate);

                if ($skip) {
                    $this->logger->error('Skipping hotel: the same arrival / departure dates');
                    $this->skipPastCount++;

                    return;
                }
            }
        } else {
            $hotel->hotel()
                ->name($data->hotelName)
                ->noAddress();
            $cxlNumber = [];

            foreach ($rooms as $room) {
                $r = $hotel->addRoom();
                $r->setType($room->roomTypeName);
                $cxlNumber[] = $room->cxlNumber;
            }
            $cxlNumber = array_values(array_unique($cxlNumber));

            if (!empty($cxlNumber) && !empty($cxlNumber[0])) {
                $hotel->general()->cancellationNumber($cxlNumber[0]);
            }
        }

        // confirmation number
        $conf = $data->confNumber;
        $this->logger->info(sprintf("[%s] Parse Itinerary #%s", $this->itCount++, $conf), ['Header' => 3]);
        $hotel->addConfirmationNumber($conf, 'Confirmation number', true);
        // spent awards
        $usedPoints = (int)($data->usedPoints ?? 0);

        if ($usedPoints) {
            $hotel->obtainPrice()->setSpentAwards($data->usedPointsFmt);
        }

        $this->logger->info('Parsed Hotel:');
        $this->logger->info(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    public function detectDeadLine(Hotel $h)
    {
        if (empty($h->getCancellation())) {
            return;
        }

        // Free cancellation before 12 noon local hotel time on 27 Dec 2021
        // Free cancellation before 11:59 PM local hotel time on 24 May 2023.
        if (preg_match('/Free cancellation before (\d+(?::\d+\s*[AP]M|\s*noon)) local hotel time on (\d+ \w+ \d{4})/', $h->getCancellation(), $m)) {
            $m[1] = str_replace('12 noon', '12:00 AM', $m[1]);
            $h->booked()->deadlineRelative($m[2], $m[1]);
        } elseif ($this->findPreg('/Free cancellation/', $h->getCancellation())) {
            $this->logger->notice('check deadline // MI');
        }
    }

    private function getHotelData(Tab $tab, $propCode): ?object
    {
        $this->logger->notice(__METHOD__);
        if (!$propCode) {
            $this->logger->error('hotel property code is missing');

            return null;
        }

        $options = [
            'method'  => 'post',
            'headers' => [
                'Accept'        => '*/*',
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$this->wso2AuthToken->accessToken}",
            ],
            'body' => '{"query":"query brand_hotel_shopAvailOptions($language: String!, $ctyhocn: String!) {\n  hotel(ctyhocn: $ctyhocn, language: $language) {\n    ctyhocn\n    externalResSystem\n    brandCode\n    contactInfo {\n      phoneNumber\n      networkDisclaimer\n    }\n    display {\n      preOpenMsg\n      open\n      resEnabled\n      treatments\n    }\n    creditCardTypes {\n      guaranteeType\n      code\n      name\n    }\n    address {\n      addressStacked: addressFmt(format: \"stacked\")\n      addressLine1\n      countryName_noTx: countryName\n      country\n      state\n      mapCity\n    }\n    brand {\n      formalName\n      formalName_noTx: formalName\n      isPartnerBrand\n      name\n      phone {\n        supportNumber\n        supportIntlNumber\n      }\n      url\n      searchOptions {\n        url\n      }\n    }\n    localization {\n      currency {\n        currencyCode\n        currencySymbol\n        description\n      }\n    }\n    overview {\n      resortFeeDisclosureDesc\n    }\n    name\n    propCode\n    shopAvailOptions {\n      maxArrivalDate\n      maxDepartureDate\n      minArrivalDate\n      minDepartureDate\n      maxNumOccupants\n      maxNumChildren\n      maxNumRooms\n      ageBasedPricing\n      adultAge\n      adjoiningRooms\n    }\n    hotelAmenities: amenities(filter: {groups_includes: [hotel]}) {\n      id\n      name\n    }\n    stayIncludesAmenities: amenities(\n      filter: {groups_includes: [stay]}\n      useBrandNames: true\n    ) {\n      id\n      name\n    }\n    images {\n      master(imageVariant: bookPropertyImageThumbnail) {\n        _id\n        altText\n        variants {\n          size\n          url\n        }\n      }\n    }\n    familyPolicy\n    registration {\n      checkinTimeFmt(language: $language)\n      checkoutTimeFmt(language: $language)\n      earlyCheckinText\n    }\n    pets {\n      description\n    }\n    tripAdvisorLocationSummary {\n      ratingFmt(decimal: 1)\n    }\n  }\n}","operationName":"brand_hotel_shopAvailOptions","variables":{"language":"en","ctyhocn":"'.$propCode.'"}}'
        ];
        $json = $tab->fetch("https://www.hilton.com/graphql/customer?appName=dx-res-ui&operationName=brand_hotel_shopAvailOptions&originalOpName=getHotel&bl=en&ctyhocn=$propCode",
            $options)->body;
        $this->logger->info($json);
        return json_decode($json);
    }


    public function addHotelData(Hotel $hotel, $hotelData, $arrivalDate, $departureDate)
    {
        $this->logger->notice(__METHOD__);
        $hotelName = $hotelData->data->hotel->name ?? null;

        if (!empty($hotelName)) {
            $hotelName = preg_replace("/\s+/", ' ', $hotelName);
        }
        // hotel name
        $hotel->setHotelName($hotelName);
        // address
        $hotel->setAddress($hotelData->data->hotel->address->addressStacked??null);
        // check in time
        $checkinTimeFmt = $hotelData->data->hotel->registration->checkinTimeFmt;

        if ($checkinTimeFmt) {
            $hotel->setCheckInDate(strtotime($checkinTimeFmt, $hotel->getCheckInDate()));
        }
        // check out time
        $checkoutTimeFmt = $hotelData->data->hotel->registration->checkoutTimeFmt;

        if ($checkoutTimeFmt) {
            $hotel->setCheckOutDate(strtotime($checkoutTimeFmt, $hotel->getCheckOutDate()));
        }

        if ($arrivalDate == $departureDate && $hotel->getCheckOutDate() < $hotel->getCheckInDate()) {
            return true;
        }

        return false;
    }

    // History

    public function parseHistory(Tab $tab, Master $master, AccountOptions $accountOptions, ParseHistoryOptions $historyOptions): void
    {
        $startDate = $historyOptions->getStartDate();
        $this->logger->debug('[History start date: ' . ($startDate ? $startDate->format('Y/m/d H:i:s') : 'all') . ']');
        if (isset($startDate)) {
            $startDate = strtotime('-4 day', $startDate->format('U'));
            $this->logger->debug('>> [set historyStartDate date -4 days]: ' . $startDate);
        } else {
            $startDate = 0;
        }
        $st = $master->getStatement();
        $this->ParsePageHistory($st, $this->activitiesSummary, 0, $startDate);
    }

    public function ParsePageHistory(Statement $st, $guestActivitiesSummary, $startIndex, $startDate)
    {
        $this->logger->debug("Total " . ((is_array($guestActivitiesSummary) || ($guestActivitiesSummary instanceof Countable)) ? count($guestActivitiesSummary) : 0) . " history transactions were found");

        foreach ($guestActivitiesSummary as $transaction) {
            $result = [];
            $dateStr = $transaction->arrivalDate;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result['Date'] = $postDate;
            $result['Check-out Date'] = strtotime($transaction->departureDate);
            $result['Description'] = $transaction->descFmt;

            $parseDetails = true;
            $skipTransaction = false;

            switch ($transaction->guestActivityType) {
                case 'past':
                    $result['Type'] = 'Points activity';

                    break;

                case 'cancelled':
                    $parseDetails = false;
                    $result['Type'] = 'Cancellation ' . $transaction->cxlNumber;
                    $now = strtotime('-1 day', time());

                    if ($result['Check-out Date'] > $now) {
                        $this->logger->info('skipping cancelled in the future');
                        $skipTransaction = true;
                    }

                    break;

                case 'other':
                    $parseDetails = false;
                    $result['Date'] = $result['Check-out Date'];
                    unset($result['Check-out Date']);
                    $result['Type'] = 'Points earned';

                    if ($transaction->totalPoints < 0) {
                        $result['Type'] = 'Points used';
                    }

                    break;

                case 'upcoming':
                    $this->logger->notice("skip upcoming reservation: {$result['Date']} / {$result['Description']}");
                    unset($result);
                    $skipTransaction = true;

                    break;

                default:
                    $this->logger->notice("new history type was found: {$transaction->guestActivityType}");
                    //$this->sendNotification("new history type was found: {$transaction->guestActivityType}");

                    break;
            }

             if ($skipTransaction === true) {
                 continue;
             }

             $result['Points Earned'] = $transaction->totalPointsFmt;
//                $result[$startIndex]['Miles Earned'] = $this->http->FindSingleNode("td[6]", $nodes->item($i));

             $st->addActivityRow($result);
             $startIndex++;

             if ($parseDetails) {
                 $transactionDetails = $transaction->transactions ?? [];

                foreach ($transactionDetails as $transactionDetail) {
                    $result['Date'] = $postDate;
                    $result['Type'] = 'Details';
                    $result['Description'] = $transactionDetail->descriptionFmt;

                    if ($transactionDetail->guestActivityPointsType === "pointsUsed") {
                        $result['Points'] = $transactionDetail->usedPointsFmt;
                    } else {
                        $result['Points'] = $transactionDetail->basePointsFmt;
                    }
                    $result['Bonus'] = $transactionDetail->bonusPointsFmt;
                    //                $result[$startIndex]['Miles'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
                    $st->addActivityRow($result);
                    $startIndex++;
                }

                $roomDetails = $transaction->roomDetails ?? [];

                foreach ($roomDetails as $i => $room) {
                    $roomIndex = $i + 1;
                    $transactionDetails = $room->transactions ?? [];

                    foreach ($transactionDetails as $transactionDetail) {
                        $result['Date'] = $postDate;
                        $result['Type'] = 'Details';
                        $result['Description'] = "Room {$roomIndex}: {$transactionDetail->descriptionFmt}";

                        if ($transactionDetail->guestActivityPointsType === "pointsUsed") {
                            $result['Points'] = $transactionDetail->usedPointsFmt;
                        } else {
                            $result['Points'] = $transactionDetail->basePointsFmt;
                        }
                        $result['Bonus'] = $transactionDetail->bonusPointsFmt;
                        //                $result[$startIndex]['Miles'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
                        $startIndex++;
                    }
                }
            }
        }
    }

    public function waitFor($whileCallback, Tab $tab, $timeoutSeconds = 15)
    {
        $start = time();

        do {
            try {
                if (call_user_func($whileCallback, $tab)) {
                    return true;
                }
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
            sleep(1);
        } while ((time() - $start) < $timeoutSeconds);

        return false;
    }

}
