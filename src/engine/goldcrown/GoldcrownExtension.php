<?php

namespace AwardWallet\Engine\goldcrown;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use function AwardWallet\ExtensionWorker\beautifulName;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class GoldcrownExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface
{
    use TextTrait;

    private $itCount = 0;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.bestwestern.com/en_US/rewards/member-dashboard.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $loginFieldOrLogOut = $tab->evaluate('//form[@id="guest-login-form"] | //p[@id="rewards-card-number"] | //span[@id="nav-logged-in-expander-points-amount"]');

        if ($loginFieldOrLogOut->getNodeName() == 'SPAN') {
            $loginFieldOrLogOut->click();
            $tab->evaluate('//div[@class="accountLinkContainer"]/a')->click();
            $tab->evaluate('//p[@id="rewards-card-number"]');

            return true;
        }

        return (bool) $this->findPreg('/^Account /i', $loginFieldOrLogOut->getInnerText());
    }

    public function getLoginId(Tab $tab): string
    {
        $accountNumber = $tab->findText('//p[@id="rewards-card-number"]', FindTextOptions::new()->nonEmptyString()->preg('/Account\s*(\w+)$/'));
        $this->logger->debug("Account Number: $accountNumber");

        return $accountNumber ?? '';
    }

    public function logout(Tab $tab): void
    {
        $tab->gotoUrl('https://www.bestwestern.com/');
        sleep(1);
        $tab->evaluate('//a[contains(@class,"accountNavLink loginButton")]')->click();
        sleep(1);
        $tab->evaluate('//button[contains(text(),"LOG-OUT")]')->click();
        $tab->evaluate('//a[@id="nav-login-link"]');
        $tab->gotoUrl('https://www.bestwestern.com/en_US/rewards/member-dashboard.html');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $login = $tab->evaluate('//form[@id="guest-login-form"]//input[@id="guest-user-id-1"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//form[@id="guest-login-form"]//input[@id="guest-password-1"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//form[@id="guest-login-form"]//button[@id="login-button-modal-recaptcha"]')->click();

        $errorOrLogOut = $tab->evaluate($xpath = '//div[contains(@class,"errorMessage__")] | //div[@id = "credentials-failed-error-msg"]//span[contains(@class, "defaultMessage")] | //p[@id="rewards-card-number"] | //iframe[contains(@src,"/captcha/")]');

        // Captcha
        if ($errorOrLogOut->getNodeName() == 'IFRAME') {
            $this->logger->info('Waiting for the captcha solution for 90 seconds...');
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $errorOrLogOut = $tab->evaluate($xpath, EvaluateOptions::new()->timeout(30));
        }

        if ($this->findPreg('/^Account /', $errorOrLogOut->getInnerText())) {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } elseif ($errorOrLogOut->getNodeName() == 'IFRAME') {
            return LoginResult::providerError('We could not recognize captcha. Please try again later.');
        } else {
            $this->logger->info('error logging in');
            $error = $errorOrLogOut->getInnerText();

            if (
                str_contains($error, "We are unable to process your request. Please try again. If you continue to have difficulties, please contact a")
                || strstr($error, "Your username or password is incorrect.")
            ) {
                return LoginResult::invalidPassword($error);
            }

            return new LoginResult(false);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        // Balance - Current Point Balance
        $statement->setBalance(str_replace(',', '', $tab->findText('//p[@id="points-available"]', FindTextOptions::new()->preg('/^[\d.,]+$/'))));

        // Number
        $statement->addProperty('Number', $tab->findText('//p[@id="rewards-card-number"]', FindTextOptions::new()->preg('/Account\s*(\w+)/')));
        // Status
        $statement->addProperty('Level', $tab->findText('//p[@id="rewards-card-tier"]', FindTextOptions::new()->preg('/(.+?)\s*Member/')));
        // Nights to Next Level
        //$statement->addProperty('Nights', $tab->evaluate('//div[@id="progress-nights"]')->getAttribute('data-needed'));
        $statement->addProperty('Nights', $tab->findTextNullable('//div[@id="progress-nights"]/@data-needed'));
        // Stays to Next Level
        $statement->addProperty('Stays', $tab->findTextNullable('//div[@id="progress-stays"]/@data-needed') ?? '');
        // Points to Next Level
        $statement->addProperty('PointsToNextLevel', $tab->findTextNullable('//div[@id="progress-points"]/@data-needed') ?? '');
        // refs #8349
        $this->logger->debug("Region {$accountOptions->login2}");

        if (in_array($accountOptions->login2, ["America", "Mexico", "Asia"])) {
            $this->logger->notice("expiration date set to never");
            //data.AccountExpirationDate = 'false';
        }

        $tab->gotoUrl('https://www.bestwestern.com/content/best-western/en_US/rewards/profile-and-preferences.html');
        $tab->evaluate('//span[@id="full-name"]');
        sleep(1);
        // Name
        $statement->addProperty('Name', $tab->findText('//span[@id="full-name"]'));
    }

    public function parseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        try {
            $json = $tab->fetch('https://www.bestwestern.com/bin/bestwestern/bwrmemberidproxy?gwServiceURL=RESERVATIONS_LOOKUP&langCode=en_US')->body;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return;
        }
        $this->logger->info($json);
        $resvList = json_decode($json)->resvList ?? [];

        foreach ($resvList as $item) {
            $checkinDate = strtotime($item->checkinDate);
            $isArchived = 'false';

            if (!$parseItinerariesOptions->isParsePastItineraries() && $checkinDate < time()) {
                $this->logger->notice("skip past #{$item->bookNumber}");

                continue;
            } elseif ($parseItinerariesOptions->isParsePastItineraries() && $checkinDate < time()) {
                $isArchived = 'true';
            }
            $this->parseItinerary($tab, $master, $item, $isArchived);
        }
    }

    private function parseItinerary(Tab $tab, Master $master, object $item, string $isArchived)
    {
        $this->logger->notice(__METHOD__);

        $json = $tab->fetch("https://www.bestwestern.com/bin/bestwestern/proxy?gwServiceURL=RESERVATION_BOOKING&confirmationnumber={$item->bookNumber}&langCode=en_US&isArchived={$isArchived}")->body;
        $this->logger->info($json);
        $booking = json_decode($json);

        $options = [
            'cors'        => 'no-cors',
            'credentials' => 'omit',
            'method'      => 'get',
        ];

        $json = $tab->fetch("https://public-services.bestwestern.com/resort/{$item->resort}/summary", $options)->body;
        $this->logger->info($json);
        $summary = json_decode($json);

        $h = $master->createHotel();
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->itCount++, $booking->bookNumber), ['Header' => 3]);
        $h->general()->confirmation($booking->bookNumber);

        if (isset($booking->roomResvStatus)) {
            $h->general()->status(beautifulName($booking->roomResvStatus));

            if ($booking->roomResvStatus == 'CANCELED') {
                $h->general()->cancelled();
            }
        }

        if (count($booking->reservationItineraryDetailsMap->{$item->resort}) > 1) {
            $this->logger->notice('reservationItineraryDetailsMap > 1 // MI');
        }

        $detail = $booking->reservationItineraryDetailsMap->{$item->resort}[0];
        $h->booked()->guests($detail->numAdult);
        $h->booked()->kids($detail->numChild);
        $h->price()->total($detail->roomPrice);
        $h->price()->currency($detail->roomCurrency);

        foreach ($detail->ratePlan->roomDetailsList ?? [] as $roomDetailsList) {
            $room = $h->addRoom();
            $room->setDescription($roomDetailsList->description);
        }

        $h->hotel()->name($summary->name);
        $h->hotel()->phone($summary->phoneNumber);
        $h->hotel()->fax($summary->faxNumber);
        $address = "$summary->address1, $summary->city";

        if (isset($summary->state)) {
            $address .= ", $summary->state";
        }

        if (isset($summary->country)) {
            $address .= ", $summary->country";
        }
        $h->hotel()->address($address);
        $h->booked()->checkIn2("$detail->checkinDate, $summary->checkInNoticeTime");
        $h->booked()->checkOut2("$detail->checkoutDate, $summary->checkOutNoticeTime");

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }
}
