<?php

namespace AwardWallet\Engine\kroger;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;
use Countable;
use function AwardWallet\ExtensionWorker\beautifulName;

class KrogerExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private int $stepItinerary = 0;
    private array $headers = [
        'Accept' => 'application/json, text/plain, */*',
        'x-kroger-channel' => 'WEB'
    ];

    private function getDomain(string $login2): string
    {
        $domain = "kroger.com";
        if ($login2 !== '') {
            $domain = $login2;
        }
        return $domain;
    }
    public function getStartingUrl(AccountOptions $options): string
    {
        $domain = $this->getDomain($options->login2);
        return "https://www.$domain/account/update";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="Sign in name"] | //input[@id = "SignIn-emailInput" or @id = "signInName"] | //span[contains(text(),"Card Number:")]/following-sibling::span');
        return $result->getNodeName() == 'SPAN';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//span[contains(text(),"Card Number:")]/following-sibling::span',
            FindTextOptions::new()->preg('/^[\d]+$/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//button[contains(@id,"WelcomeButton-")]')->click();
        $tab->evaluate('//li[@id="WelcomeMenu-signOut"]/button[contains(text(),"Sign out")]')->click();
        sleep(3);
        $tab->evaluate('//button[contains(@id,"WelcomeButton-")]');
     }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $login = $tab->evaluate('//input[@name="Sign in name"] | //input[@id = "SignIn-emailInput" or @id = "signInName"]');
        $login->setValue($credentials->getLogin());
        $password = $tab->evaluate('//input[@name="Password"] | //input[@id = "SignIn-passwordInput" or @id = "password"]');
        $password->setValue($credentials->getPassword());
        $submit = $tab->evaluate('//button[@id="next" or @id="continue"]');
        $submit->click();

        $result = $tab->evaluate('
                //div[contains(@class,"error pageLevel")]
                | //span[contains(text(),"Card Number:")] 
            ');

         // The email or password is incorrect. Please try again or click "Forgot password".
        if (str_starts_with($result->getInnerText(), 'The email or password is incorrect. Please try again or click "Forgot password".')) {
            return LoginResult::invalidPassword($result->getInnerText());
        }

        if (str_contains($result->getInnerText(), "Card Number:")) {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        $options = [
            'method' => 'get',
            'headers' => $this->headers
        ];

        // Name
        $domain = $this->getDomain($accountOptions->login2);
        $profile = $tab->fetch("https://www.$domain/atlas/v1/customer-profile/v1/profile?projections=customerProfile.full", $options)->body;
        $this->logger->info($profile);
        $profile = json_decode($profile)->data->profile ?? null;
        //# Rewards Card Number
        if (isset($profile->loyaltyCardNumber)) {
            $st->addProperty('Number', $profile->loyaltyCardNumber);
        }
        // Name
        if (isset($profile->firstName, $profile->lastName)) {
            $st->addProperty('Name', beautifulName($profile->firstName . ' ' . $profile->lastName));
        }

        if (isset($profile->bannerSpecificDetails[0]->preferredStore->locationId)) {
            $store = $tab->fetch("https://www.$domain/atlas/v1/stores/v1/details/{$profile->bannerSpecificDetails[0]->preferredStore->locationId}", $options)->body;
            $this->logger->info($store);
            $store = json_decode($store);
            // Preferred Store
            if (isset($store->data->storeDetails->address->address)) {
                $address = $store->data->storeDetails->address->address;
                $address = "{$address->addressLines[0]}, $address->name, $address->stateProvince $address->postalCode";
                $st->addProperty("PreferredStore", $address);
            }
        }



        $summary = $tab->fetch("https://www.$domain/accountmanagement/api/points-summary", $options)->body;
        $this->logger->info($summary);
        $summary = json_decode($summary);

        if (is_array($summary) && !empty($summary)) {
            foreach ($summary as $subAcc) {
//                $this->logger->debug(var_export($subAcc, true), ['pre' => true]);
                $points = $subAcc->programBalance->balanceDescription;
                $title = $subAcc->programDisplayInfo->loyaltyProgramName;
                $titleBalance = ['Your Year-to-Date Plus Card Savings', 'Annual Savings', 'Annual Advantage Card Savings', 'Year-to-Date V.I.P. Card Savings', 'Annual rewards Card Savings'];

                if (in_array($title, $titleBalance)) {
                    // Balance - Annual Savings
                    $st->addProperty("AnnualSavings", $points);
                    //$st->setNoBalance(true);

                    continue;
                }

                if (isset($points)) {
                    $points = preg_replace('/[^\d\-.,]+/', '', $points);
                    if (
                        strstr($title, 'Fuel Points')
                        || strstr($title, 'Fuel Program')
                    ) {
                        if (!isset($fuelBalance)) {
                            $fuelBalance = 0;
                        }
                        $fuelBalance += $points;
                    }
                    elseif ($points == 0) {
                        $this->logger->notice("Skip zero subaccount: {$title} / {$points}");
                        $st->setNoBalance(true);

                        continue;
                    }// elseif ($points == 0)
                    $subAccount = [
                        'Code'        => $domain . preg_replace(["/\s*/i", "/\'/i"], '', $title),
                        'DisplayName' => $title,
                        'Balance'     => $points,
                    ];

                    if (strstr($title, 'Fuel Points')) {
                        $subAccount['BalanceInTotalSum'] = true;
                    }

                    $expiration = preg_replace('/T.+/ims', '', $subAcc->programDisplayInfo->redemptionEndDate);

                    if ($expiration = strtotime($expiration)) {
                        $subAccount['ExpirationDate'] = $expiration;
                    }
                    $st->AddSubAccount($subAccount);
                }
            }

            if (!empty($st->getSubAccounts()) && count($st->getSubAccounts()) > 0) {
                // TODO: temporarily fix, remove it in  2014
                // "ralphs.com" - 1406283, 1124792
                // "dillons.com" - 1255118
//                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
//                    $st->setNoBalance(true);
//                }
                $this->logger->debug("Total subAccounts: " . count($st->getSubAccounts()));

                // refs #14490
                if (isset($fuelBalance)) {
                    $st->setBalance($fuelBalance);
                    $st->addProperty("Currency", "points");
                }
            }
        }
        elseif (isset($response->hasErrors) && $response->hasErrors == 'true') {
            if (isset($response->errorCode) && $response->errorCode == 'BannerProfileNotFound') {
                 $master->setWarning("We are not able to display your Points Summary at this time, either because you do not have a preferred store selected or you do not have a Plus Card on file. Please update your Account Summary in order to view your points.");
            }
            // Please add a Plus Card to view your points.
            if (isset($response->errorCode) && $response->errorCode == 'UserDoesNotHaveACard') {
                $this->logger->error("Please add a Plus Card to view your points.");
                return;
                //throw new CheckException("Please add a Plus Card to view your points.", ACCOUNT_PROVIDER_ERROR);
            }
            // We're sorry, we are currently experiencing technical difficulties. Please try again later.
            if (isset($response->errorMessage)
                && $response->errorMessage == 'We\'re sorry, we are currently experiencing technical difficulties. Please try again later.') {
                $this->logger->error("We're sorry, we are currently experiencing technical difficulties. Please try again later.");
                return;
                //throw new CheckException("We're sorry, we are currently experiencing technical difficulties. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        // refs #15112
        $this->logger->info('Coupons', ['Header' => 3]);
        $tab->gotoUrl("https://www.$domain/savings/cl/mycoupons/");

        /*
        $facilityID = $tab->findText('//script[contains(text(), "window.__INITIAL_STATE__") and contains(text(), "JSON.parse")]', FindTextOptions::new()->preg('/\"fallbackFulfillment\"\:\s*\"(.+?)\"/')->visible(false)->);
        $geolocationID = $tab->findText('//script[contains(text(), "window.__INITIAL_STATE__") and contains(text(), "JSON.parse")]', FindTextOptions::new()->preg('/\"geoLocationV1\":\s*{\s*\"id\":\s*\"(.*)\"/')->visible(false));
        $proxyStore = $tab->findText('//script[contains(text(), "window.__INITIAL_STATE__") and contains(text(), "JSON.parse")]', FindTextOptions::new()->preg('/\"geoLocationV1\":\s*{\s*\"id\":\s*\".*\",\s*\"proxyStore\":\s*\"(.*)\"/')->visible(false));
        $modalityType = 'PICKUP';
        $geolocationSettings = ['id' => $geolocationID, 'proxyStore' => $proxyStore];
        $modalitySettings = ['type' => $modalityType, 'locationId' => $facilityID];

        $pageSettingsRaw = $tab->findText($tab->findText('//script[contains(text(), "window.__INITIAL_STATE__") and contains(text(), "JSON.parse")]', FindTextOptions::new()->preg('/window\.__INITIAL_STATE__\s*=\s*JSON\.parse\(\'(.*)\'\)\s*window\.__BANNER_NAME__/')->visible(false)));
        $pageSettings = json_decode($pageSettingsRaw);
        $lafObject = $pageSettings->calypso->useCases->getModalityPreferences->default->response->data->modalityPreferences->lafObject ?? null;

        $headers = [
            'x-ab-test' => '[{"testVersion":"B","testID":"9e9260","testOrigin":"0f"},{"testVersion":"B","testID":"a8463d","testOrigin":"a7"},{"testVersion":"A","testID":"c3ac3b","testOrigin":"cb"},{"testVersion":"B","testID":"533bb9","testOrigin":"7c"}]',
            'x-call-origin' => '{"page":"coupons","component":"clipped coupons"}',
            'x-kroger-channel' => 'WEB',
            'x-modality-type' => $modalityType,
            'x-facility-id' => $facilityID,
            'x-modality' => json_encode($modalitySettings),
            'x-geo-location-v1' => json_encode($geolocationSettings),
            'x-laf-object' => json_encode($lafObject)
        ];
        */

        $headers = [
            'x-ab-test' => '[{"testVersion":"B","testID":"9e9260","testOrigin":"0f"},{"testVersion":"B","testID":"a8463d","testOrigin":"a7"},{"testVersion":"A","testID":"c3ac3b","testOrigin":"cb"},{"testVersion":"B","testID":"533bb9","testOrigin":"7c"}]',
            'x-call-origin' => '{"page":"coupons","component":"clipped coupons"}',
            'x-facility-id' => '09700284',
            'x-geo-location-v1' => '{"id":"ac902f45-f0d6-456f-aefc-90923b21f531","proxyStore":"09700812"}',
            'x-kroger-channel' => 'WEB',
            'x-laf-object' => '[{"modality":{"type":"PICKUP","handoffLocation":{"storeId":"09700284","facilityId":"13706"},"handoffAddress":{"address":{"addressLines":["43300 Southern Walk Plz"],"cityTown":"Ashburn","name":"Broadlands Marketplace","postalCode":"20148","stateProvince":"VA","residential":false,"countryCode":"US"},"location":{"lat":39.0086515,"lng":-77.5032675}}},"sources":[{"storeId":"09700284","facilityId":"13706"}],"assortmentKeys":["09700284"],"listingKeys":["09700284"]},{"modality":{"type":"SHIP","handoffAddress":{"address":{"postalCode":"97202","stateProvince":"OR"},"location":{"lat":45.4839859,"lng":-122.63969421}}},"sources":[{"storeId":"MKTPLACE","facilityId":"00000"}],"assortmentKeys":["MKTPLACE"],"listingKeys":["MKTPLACE"]}]',
            'x-modality' => '{"type":"PICKUP","locationId":"09700284"}',
            'x-modality-type' => 'PICKUP'
        ];

        $options = [
            'cors' => 'no-cors',
            'credentials' => 'omit',
            'method' => 'get',
            'headers' => $headers
        ];

        $coupons = $tab->fetch("https://www.$domain/atlas/v1/savings-coupons/v1/coupons?filter.status=active&projections=coupons.compact", $options)->body;
        $this->logger->info($coupons);
        $coupons = json_decode($coupons)->data->coupons ?? [];
        $this->logger->debug("Total " . ((is_array($coupons) || ($coupons instanceof Countable)) ? count($coupons) : 0) . " coupons were found");
        $allCoupons = [];

        foreach ($coupons as $coupon) {
            $displayName = $coupon->shortDescription;
            $code = $coupon->id;
            $exp = $coupon->expirationDate;
            $savings = $coupon->savings;

            $subAccount = [
                'Code'        => "krogerCoupons$domain$code",
                'DisplayName' => $displayName,
                'Balance'     => ($savings == '') ? null : $savings,
            ];

            if ($expiration = strtotime($exp)) {
                $subAccount['ExpirationDate'] = $expiration;
            }
            $allCoupons[] = $subAccount;
        }

        usort($allCoupons, function ($a, $b) {
            $key = 'ExpirationDate';
            return $a[$key] == $b[$key] ? 0 : ($a[$key] > $b[$key] ? 1 : -1);
        });
        $hotCoupons = array_slice($allCoupons, 0, 10);
        unset($coupon);

        foreach ($hotCoupons as $coupon) {
            $st->addSubAccount($coupon);
        }
    }

}
