<?php

namespace AwardWallet\Engine\shangrila;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class ShangrilaExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.shangri-la.com/corporate/shangrilacircle/online-services/account-summary/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('/input[@placeholder="Password"] | //input[@placeholder="Verification Code"] | //div[@class="login-form"] | //div[@data-track-button-name="Click_SLC_Dashboard_MemberCard"]/span[text() and not(contains(text(), "|"))]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@data-track-button-name="Click_SLC_Dashboard_MemberCard"]/span[text() and not(contains(text(), "|"))]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(5);
        $loginType = $tab->evaluate('
            //div[@class="login-form"]//input[@name="email-code"]
            | //div[@class="login-form"]//input[@name="email-password"]
            | //input[@placeholder="Password"]
            | //input[@placeholder="Verification Code"]
        ');

        if (
            strstr(strtolower($loginType->getAttribute('name')), "code")
        ) {
            $tab->evaluate('(//div[@class="shangrila-react-login-receive"]//div[contains(text(), "Change to use password")])[1]')->click();

            if (filter_var($credentials->getLogin(), FILTER_VALIDATE_EMAIL) === false) {
                $tab->evaluate('//span[@class="shangrila-react-login-box-switch-icon-gc"]')->click();
            }
        }

        if (
            strstr(strtolower($loginType->getAttribute('placeholder')), "code")
        ) {
            $tab->evaluate('//span[@class="p-login-switch-icon-gc"]')->click();
        }

        $login = $tab->evaluate('//input[@placeholder="Membership Number"] | //div[@class="login-form"]//input[@name="email" or @name="gc"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@placeholder="Password"] | //div[@class="login-form"]//input[contains(@name,"-password")]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[@class="content-login"] | //div[@class="login-form"]//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('
            //div[contains(@class, "content-error")]
            | //div[@class="yo-toast" and not(@style="display: none;")]
            | //div[contains(@class, "geetest_panel_box") and contains(@class, "geetest_panelshowclick")]
            | //div[@data-track-button-name="Click_SLC_Dashboard_MemberCard"]/span[text() and not(contains(text(), "|"))]
            | //div[@class="login-form"]//div[contains(@class, "shangrila-react-login-box-common-err") and text()]
        ');

        if (strstr($submitResult->getAttribute('class'), 'geetest_panelshowclick')) {
            $tab->showMessage(Message::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('
                //div[contains(@class, "content-error")]
                | //div[@class="yo-toast" and not(@style="display: none;")]
                | //div[@data-track-button-name="Click_SLC_Dashboard_MemberCard"]/span[text() and not(contains(text(), "|"))]
                | //div[@class="login-form"]//div[contains(@class, "shangrila-react-login-box-common-err") and text()]
            ');
        }

        if (strstr($submitResult->getAttribute('class'), 'content-error')) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        if (
            strstr($submitResult->getAttribute('class'), 'yo-toast')
            || strstr($submitResult->getAttribute('class'), "shangrila-react-login-box-common-err")
        ) {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Membership Number/Password is not valid. Please try again.")
                || strstr($error, "Email/Password is not valid or email is not verified. Please try again")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[@class="sl-header-desktop-user-name"]/..', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//span[contains(text(), "Sign Out")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//input[@placeholder="Password"] | //input[@placeholder="Verification Code"] | //div[@class="login-form"] | //span[@class="header-login-react-wrapper"] | //span[@id="header-login-btn"]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        // Membership Number
        $statement->addProperty("Number", $tab->findText('//div[@data-track-button-name="Click_SLC_Dashboard_MemberCard"]/span[text() and not(contains(text(), "|"))]', FindTextOptions::new()->nonEmptyString()));
        $tab->evaluate('//div[@data-track-button-name="Click_SLC_Dashboard_Nights"]')->click();
        // Qualifying Nights Completed
        $statement->addProperty("QualifiedRoomNights", $tab->findText('//span[contains(text(), "Night")]', FindTextOptions::new()->nonEmptyString()->preg('/(\d+)/')));
        $tab->evaluate('//div[@data-track-button-name="Click_SLC_Dashboard_Tier_Points"]')->click();
        // Tier Points.
        $statement->addProperty("TierPoints", $tab->findText('//div[not(@data-track-button-name="Click_SLC_Dashboard_Tier_Points")]/span[contains(text(), "Tier Points")]', FindTextOptions::new()->nonEmptyString()->preg('/(\d+)/')));
        // Current Tier
        $statement->addProperty("CurrentTier", $tab->findText('//div[contains(@class, "flex") and contains(@class, "justify-between")]/span[1] | //div[contains(@class, "justify-center")]/div/div[contains(@class, "title-bold")]', FindTextOptions::new()->nonEmptyString()));
        // Balance - GC Award Points Balance
        $statement->SetBalance($tab->findText('//a[contains(@href, "points") and span[contains(text(), "Points")]]/span[not(contains(text(), "Points"))]', FindTextOptions::new()->nonEmptyString()->visible(false)));

        $tab->evaluate('//div[@data-track-button-name="Click_SLC_Dashboard_MemberCard"]')->click();

        // Member Since
        $statement->addProperty("MemberSince", $tab->findText('//div[contains(text(), "Member Since")]/following-sibling::div', FindTextOptions::new()->nonEmptyString()));
        // Name
        $statement->addProperty('Name', $tab->findText('//div[contains(text(), "Points Balance")]/../../preceding-sibling::div[text()]', FindTextOptions::new()->nonEmptyString()));
    }

    public function ParseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        // Upcoming reservations
        $page = 1;
        $noUpcoming = false;

        do {
            $tab->gotoUrl("https://www.shangri-la.com/en/corporate/shangrilacircle/online-services/reservations-list/?orderType=UPCOMING&orderConsumeType=HOTEL&page={$page}");
            $stop = $tab->findText('//script[contains(., "var __pageData")]', FindTextOptions::new()->preg('/"hotelOrderList":\[\]/')->timeout(15)->visible(false));
            $this->logger->debug('Stop: ' . $stop . ', Page: ' . $page);

            if (!$tab->findText('//script[contains(., "var __pageData")]', FindTextOptions::new()->preg('/var __pageData\s*=\s*.+?"hotelOrderList":\[\],"totalCount":0,/s')->allowNull(true)->timeout(5)->visible(false))) {
                $itemsJson = $tab->findText('//script[contains(., "var __pageData")]', FindTextOptions::new()->preg('/var __pageData\s*=\s*(\{.+?\});/s')->allowNull(true)->timeout(5)->visible(false));
                $this->logger->debug($itemsJson);
                $items = json_decode($itemsJson) ?? [];

                if (isset($items->orderDatas->hotelOrderList)) {
                    foreach ($items->orderDatas->hotelOrderList as $item) {
                        $url = $item->detailUrl;
                        $tab->gotoUrl($url);
                        $this->ParseItinerary($tab, $master, $options, $parseItinerariesOptions);
                    }
                }
            } elseif ($page === 1) {
                $noUpcoming = true;
            }
            $page++;
        } while ($page < 5 && !$stop);

        // Past reservations
        if ($parseItinerariesOptions->isParsePastItineraries()) {
            $tab->gotoUrl('https://www.shangri-la.com/en/corporate/golden-circle/online-services/reservations-list/?orderType=PAST&orderConsumeType=HOTEL');
            $noPast = false;

            if (!$tab->findText('//script[contains(., "var __pageData")]', FindTextOptions::new()->preg('/var __pageData\s*=\s*.+?"hotelOrderList":\[\],"totalCount":0,/s')->timeout(15)->visible(false))) {
                $itemsJson = $tab->findText('//script[contains(., "var __pageData")]', FindTextOptions::new()->preg('/var __pageData\s*=\s*(\{.+?\});/s')->allowNull(true)->timeout(5)->visible(false));
                $this->logger->debug($itemsJson);
                $items = json_decode($itemsJson) ?? [];

                if (isset($items->orderDatas->hotelOrderList)) {
                    foreach ($items->orderDatas->hotelOrderList as $item) {
                        $url = $item->detailUrl;
                        $tab->gotoUrl($url);
                        $this->ParseItinerary($tab, $master, $options, $parseItinerariesOptions);
                    }
                }
            } else {
                $noPast = true;
            }

            if ($noPast && $noUpcoming) {
                $master->setNoItineraries(true);
            }
        } elseif ($noUpcoming) {
            $master->setNoItineraries(true);
        }
    }

    public function ParseItinerary(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ) {
        $this->logger->notice(__METHOD__);

        $itJson = $tab->findText('//script[contains(., "var __pageData")]', FindTextOptions::new()->preg('/var __pageData\s*=\s*(\{.+?\});/s')->timeout(15)->visible(false));
        $this->logger->debug($itJson);
        $it = json_decode($itJson);

        if (!isset($it->orderInfo, $it->orderInfo->base->confirmationNo, $it->orderInfo->reservationInfo)) {
            return;
        }

        $order = $it->orderInfo;

        $h = $master->createHotel();
        // ConfirmationNumber
        $h->general()->confirmation($order->base->confirmationNo); // TODO: description
        $h->general()->status(beautifulName($order->base->orderStatus));

        $h->hotel()->name($order->reservationInfo->hotelName);
        $h->hotel()->phone($order->reservationInfo->hotelPhone);

        $h->hotel()->address(str_replace('<br />', ', ', $order->reservationInfo->hotelAddress));

        $h->booked()->checkIn(strtotime($order->reservationInfo->checkInDate, false));
        $h->booked()->checkOut(strtotime($order->reservationInfo->checkOutDate, false));

        $h->booked()->rooms($order->reservationInfo->roomNum);
        $h->booked()->guests($order->reservationInfo->adultNum);
        $h->booked()->kids($order->reservationInfo->childrenNum);

        $r = $h->addRoom();
        $r->setType("{$order->reservationInfo->room->roomName} ({$order->reservationInfo->room->bedName})");

        if (isset($order->costDetail->chargeDetail->roomCost->amount)) {
            $h->price()->cost($order->costDetail->chargeDetail->roomCost->amount);
        }

        if (isset($order->costDetail->chargeDetail->serviceChargeAndTax->amount)) {
            $h->price()->tax($order->costDetail->chargeDetail->serviceChargeAndTax->amount);
        }

        if (isset($order->costDetail->chargeDetail->totalCost->amount)) {
            $h->price()->total($order->costDetail->chargeDetail->totalCost->amount);
        }

        $currency =
            $order->costDetail->chargeDetail->totalCost->currency
            ?? $order->costDetail->chargeDetail->serviceChargeAndTax->currency
            ?? null
        ;
        $h->price()->currency($currency);

        if (is_object($order->personalInfo)) {
            $firstName = $order->personalInfo->firstName ?? null;
            $h->general()->traveller("{$firstName} {$order->personalInfo->lastName}", true);
        } else {
            $this->notificationSender->sendNotification('refs #24741 - shangrila - Check GuestNames > 1 // IZ');
        }

        $h->general()->cancellation($order->reservationInfo->cancelPolicy);
    }
}
