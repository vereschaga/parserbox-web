<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;

class TAccountCheckerHandm extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const ABCK_CACHE_KEY = 'handm_abck';
    public $regionOptions = [
        ""         => "Select your country",
        "en_us"    => "United States",
        "en_gb"    => "United Kingdom",
    ];

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields["Login2"]["Options"] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
        $this->http->setUserAgent(HttpBrowser::PROXY_USER_AGENT);
        $this->http->RetryCount = 0;
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www2.hm.com/' . $this->AccountFields['Login2'] . '/index.html');

        if (!$this->http->FindSingleNode('//title[contains(text(), "H&M")]') || $this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        if (!$this->getAbckFromCache()) {
            $this->getAbckFromSelenium();
        }

        $headers = [
            'accept'       => 'application/json',
            'content-type' => 'application/x-www-form-urlencoded',
        ];
        $data = [
            'j_username'                   => $this->AccountFields['Login'],
            'j_password'                   => $this->AccountFields['Pass'],
            'asyncCall'                    => 'true',
            '_spring_security_remember_me' => 'on',
        ];
        $this->http->PostURL('https://www2.hm.com/' . $this->AccountFields['Login2'] . '/j_spring_security_check', $data, $headers);

        return true;
    }

    public function Login()
    {
        $authFailed = $this->http->Response['headers']['x-validation-failure'] ?? null && $this->http->Response['code'] == 401;

        if ($authFailed) {
            throw new CheckException('Wrong email or password, please try again', ACCOUNT_INVALID_PASSWORD);
        }

        if (!strstr($this->http->currentUrl(), 'j_spring_security_check') && $this->loginSuccessful()) {
            return true;
        }

        $wrongSensorData = $this->http->FindSingleNode('//div[@class="generalError"]');

        if ($wrongSensorData) {
            Cache::getInstance()->delete(self::ABCK_CACHE_KEY);

            throw new CheckRetryNeededException(5, 3);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $userData = $this->http->JsonLog(null, 0, true);
        // Name
        $this->SetProperty('Name', beautifulName($userData['displayName'] ?? null));
        // Elite level
        $this->SetProperty('EliteLevel', $userData['currentTierCopy'] ?? null);
        // Balance - points
        $this->SetBalance($userData['pointsBalance'] ?? null);
        // Membership renewal date
        $mrd = strtotime($userData['pointsExpirationDate'] ?? null) ?? null;
        $this->SetProperty('RenewalDate', $mrd);
        // Expiration date
        if ($mrd !== null) {
            $this->SetExpirationDate($mrd);
        }

        $progressText = $userData['mainMembershipCardCopy'] ?? null;
        $progressNextReward = $this->http->FindPreg('/^You’re+\s+([0-9]+)/', false, $progressText);
        $progressNextEliteStatus = $this->http->FindPreg('/and+\s+([0-9]+)/', false, $progressText);
        // Points till next reward
        $this->SetProperty('NextReward', $progressNextReward);
        // Points till next elite level
        $this->SetProperty('NextLevel', $progressNextEliteStatus);
        // Account number
        $this->SetProperty('Number', $userData['customerLoyaltyId'] ?? null);
        // Member since
        $this->SetProperty('MemberSince', strtotime($userData['formattedFullClubMemberStartDateAndTime'] ?? null) ?? null);

        $this->http->GetURL('https://www2.hm.com/' . $this->AccountFields['Login2'] . '/v1/offersProposition');
        $offerKeysData = $this->http->JsonLog(null, 3, true) ?? [];

        foreach ($offerKeysData as $okd) {
            $this->http->GetURL('https://www2.hm.com/' . $this->AccountFields['Login2'] . '/member/memberOffers.v1.json?offerKeys=' . $okd['offerKey']);
            $offerData = $this->http->JsonLog(null, 3, true);
            $offerDataPrepared = $offerData[0] ?? null;
            $expDatePersonalized = strtotime($okd['personalizedExpireDate'] ?? null);
            $expDateCommon = strtotime($okd['endDateTime'] ?? null);

            $this->AddSubAccount([
                "Code"           => $okd['offerPropositionId'] ?? null,
                "DisplayName"    => $offerDataPrepared['headline'] ?? null,
                "Balance"        => null,
                "ExpirationDate" => $expDatePersonalized ? $expDatePersonalized : $expDateCommon,
            ]);
        }
    }

    protected function checkRegionSelection($region)
    {
        if (empty($region) || !in_array($region, array_flip($this->regionOptions))) {
            $region = 'en_us';
        }

        return $region;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www2.hm.com/' . $this->AccountFields['Login2'] . '/v2/user', [
            'accept'       => 'application/json',
            'content-type' => 'application/json',
        ]);

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        $log = $this->http->JsonLog(null, 3, true);
        $email = $log['email'] ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function getAbckFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->removeCookies();
            $selenium->http->GetURL('https://www2.hm.com/' . $this->AccountFields['Login2'] . '/login');

            $loginField = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="email"]'), 15);
            $this->savePageToLogs($selenium);

            if (!$loginField) {
//                $retry = true; // TODO: what this??

                return;
            }

            $cookiesAcceptBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id="onetrust-accept-btn-handler"]'), 0);

            if ($cookiesAcceptBtn) {
                $cookiesAcceptBtn->click();
            }

            sleep(1);
            $loginField->click();
            sleep(1);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if (
                    !in_array($cookie['name'], [
                        '_abck',
                    ])
                ) {
                    continue;
                }

                $result = $cookie['value'];
                $this->logger->debug("set new _abck: {$result}");
                Cache::getInstance()->set(self::ABCK_CACHE_KEY, $cookie['value'], 60 * 60);
                $this->http->setCookie("_abck", $result, ".hm.com");
            }
        } finally {
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(4, 3);
            }
        }
    }

    private function getAbckFromCache()
    {
        $result = Cache::getInstance()->get(self::ABCK_CACHE_KEY);

        if (empty($result) && $this->attempt != 0) {
            return false;
        }

        $this->logger->debug("set _abck from cache: {$result}");
        $this->http->setCookie("_abck", $result, ".hm.com");

        return true;
    }
}
