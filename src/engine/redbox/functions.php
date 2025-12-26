<?php

class TAccountCheckerRedbox extends TAccountChecker
{
    use SeleniumCheckerHelper;
    private const WAIT_TIMEOUT = 10;

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $loginRequest;

    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/115.0');
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn(): bool
    {
        return !empty($this->State['x-redbox-token'])
            && !empty($this->State['apiKey'])
            && $this->loginSuccessful();
    }

    public function LoadLoginForm(): bool
    {
        $this->http->removeCookies();

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            // $selenium->useFirefoxPlaywright();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);

            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->saveScreenshots = true;
            $selenium->seleniumOptions->recordRequests = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL('https://www.redbox.com/');

            /*
            if ($cookieBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id="truste-consent-button"]'), self::WAIT_TIMEOUT)) {
                $cookieBtn->click();
            }
            */

            $signInButton = $selenium->waitForElement(WebDriverBy::xpath('//button[@id="myredbox"]'), self::WAIT_TIMEOUT);
            $selenium->saveResponse();

            if (!$signInButton) {
                return $this->checkErrors();
            }

            $signInButton->click();

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="userName"]'), self::WAIT_TIMEOUT);
            $password = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="password"]'), 0);
            $selenium->saveResponse();

            if (!$login || !$password) {
                return $this->checkErrors();
            }

            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $password->sendKeys($this->AccountFields['Pass']);

            /*
            if ($selenium->waitForElement(WebDriverBy::xpath('//iframe[contains(@id, "captchaId")]'), self::WAIT_TIMEOUT)) {
                if (!$this->parseCaptcha($selenium)) {
                    return $this->checkErrors();
                }
            }
            */

            $submit = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-test-id="rb-registration-submit"]'), self::WAIT_TIMEOUT);
            $selenium->saveResponse();

            if (!$submit) {
                return $this->checkErrors();
            }

            $submit->click();

            $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "form-alert-error")] | //div[contains(@class,"profile-dropdown")]'), self::WAIT_TIMEOUT); // TODO

            $selenium->saveResponse();

            if ($message = $selenium->http->FindSingleNode('//h2[contains(text(), "Oops. Something unexpected happened.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->loginRequest = $this->catchLoginRequest($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return true;
    }

    public function Login(): bool
    {
        // $response = $this->http->JsonLog(null, 3, false, 'email');
        $response = $this->loginRequest;

        if (!empty($response->d->data->user->token)) {
            $this->State['x-redbox-token'] = $response->d->data->user->token;
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        $code = $response->d->data->loginResultCode ?? null;

        if (!empty($response->d->msg)) {
            $message = $response->d->msg;
            $this->logger->error("[Error]: {$message}");

            if (str_contains($message, 'The email/password combination is incorrect')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (str_contains($message, 'Sorry something went wrong, please try again')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (str_contains($message, 'Sorry, something went wrong. Please wait a couple of minutes and try again.')) {
                $this->captchaReporting($this->recognizer, false);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse(): void
    {
        $token = $this->http->getCookieByName('rbwl');

        if (empty($token)) {
            return;
        }

        $headers = [
            'content-type'  => 'application/json',
            'authorization' => 'Bearer ' . $token,
        ];
        $graphQLQuery = '{"operationName":"getUserProfile","variables":{"fetchSubscriptions":true},"query":"query getUserProfile($fetchSubscriptions: Boolean!) {\n  me {\n    account {\n      settings {\n        kioskLogInOptIn\n        hasSubscription\n        marketingEmailOptIn\n        __typename\n      }\n      perks {\n        perksInfo {\n          gameboardUrl\n          loyaltyTier: currentTier\n          loyaltyTierCounter: currentTierCounter\n          levelYear\n          numFreeRentals\n          loyaltyPointBalance: pointBalance\n          pointsExpirationDate\n          pointsExpiring\n          progress {\n            tier: currentTier\n            year: levelYear\n            percent\n            remaining\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    profile {\n      firstName\n      lastName\n      phone\n      dayOfBirth\n      monthOfBirth\n      displayName\n      addressLine1\n      addressLine2\n      city\n      state\n      zip\n      customerProfileNumber\n      favoriteFormats\n      favoriteGenres\n      __typename\n    }\n    subscriptionPlans @include(if: $fetchSubscriptions) {\n      plan {\n        id\n        subscription {\n          vendor\n          __typename\n        }\n        __typename\n      }\n      endDate\n      status\n      __typename\n    }\n    wishlist {\n      items {\n        name\n        number\n        productPage\n        id: productGroupId\n        genres\n        titleDetails {\n          redboxTitleId\n          releaseType\n          __typename\n        }\n        mediaFormats: titleDetails {\n          redboxTitleId\n          mediumType\n          __typename\n        }\n        rating {\n          name\n          __typename\n        }\n        previews\n        type\n        images {\n          boxArtVertical\n          __typename\n        }\n        children {\n          items {\n            name\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      hasMore\n      queryId\n      total\n      __typename\n    }\n    userStores: stores {\n      items {\n        nickname\n        preferenceOrder\n        store {\n          id\n          hideTax\n          online\n          profile {\n            name\n            location {\n              address\n              city\n              isIndoor\n              latitude\n              longitude\n              state\n              zip\n              __typename\n            }\n            vendor\n            hasKeypad\n            canSellMovies\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.redbox.com/gapi/ondemand/hcgraphql/', $graphQLQuery, $headers);
        $this->http->RetryCount = 2;
        $responseData = $this->http->JsonLog(null, 6) ?? null;
        $response = $responseData->data->me ?? null;
        // Points Balance
        $this->SetBalance($response->account->perks->perksInfo->loyaltyPointBalance ?? null);

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && isset($response->account->perks, $responseData->errors[0]->message)
            && $response->account->perks->perksInfo === null
            && in_array($responseData->errors[0]->message, [
                "Not supported http status code, ServiceUnavailable.",
                "The server encoutered unexpected error.",
            ])
        ) {
            $this->SetWarning("Our points system is in maintenance at the moment. Don't worry - your points are still there.");
        } elseif (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $response->account->perks->perksInfo === null
            && !empty($response->profile->customerProfileNumber)
        ) {
            $this->SetBalanceNA();
        }

        // Elite status (You're a Member/Star/Superstar/Legend)
        $this->SetProperty('Status', ucfirst(strtolower($response->account->perks->perksInfo->loyaltyTier ?? null)));
        // Level Progress
        $this->SetProperty('RentalsOrPurchasesToNextStatus', $response->account->perks->perksInfo->progress->remaining ?? null);
        // Free 1-night DVD rentals
        $this->SetProperty('FreeRentals', $response->account->perks->perksInfo->numFreeRentals ?? null);
        // Name
        $name = $response->profile->displayName ?? null;
        $first = $response->profile->firstName ?? null;
        $last = $response->profile->lastName ?? null;

        if (!empty($first) || !empty($last)) {
            $name = beautifulName($first . ' ' . $last);
        }
        $this->SetProperty('Name', $name);
        // Profile number
        $this->SetProperty('Number', $response->profile->customerProfileNumber ?? null);

        // Offers
        $offers = $response->account->perks->offersInfo ?? [];
        $offersNotify = false;

        foreach ($offers as $o) {
            if (empty($o->code) || empty($o->name)) {
                continue;
            }
            $params = [
                'Code'           => $o->code,
                'DisplayName'    => $o->name,
                'Balance'        => null,
            ];

            if (!empty($o->endDate)) {
                $dt = new DateTime($o->endDate);
                $params['ExpirationDate'] = $dt->getTimestamp();
            }
            $this->AddSubAccount($params);
        }

        // Points expiration
        if (!empty($response->account->perks->perksInfo->pointsExpirationDate)) {
            $this->sendNotification('refs #14861 pointsExpirationDate not null');
        }
        // Promos
        $promos = $response->account->perks->promosInfo ?? [];
        $parsedPromos = [];

        foreach ($promos as $campaign) {
            if (empty($campaign->campaign)
                || empty($campaign->promoCodes)
            ) {
                continue;
            }
            $expirations = [];

            foreach ($campaign->promoCodes as $code) {
                if (!empty($code->status)
                    && $code->status === 'Active'
                    && !empty($code->expirationDate)
                ) {
                    $exp = new DateTime($code->expirationDate);
                    $expirations[] = $exp->getTimestamp();
                }
            }
            $nearestExpirationDate = min($expirations);
            $campaignCode = preg_replace('/[^a-z\d]/ui', '', $campaign->campaign) . $nearestExpirationDate;
            // here we prevent adding of duplicate promo campaign and sum up balances instead
            if (array_key_exists($campaignCode, $parsedPromos)) {
                $parsedPromos[$campaignCode]['Balance'] += count($expirations);

                continue;
            }
            $parsedPromos[$campaignCode] = [
                'Code'           => $campaignCode,
                'DisplayName'    => $campaign->campaign,
                'Balance'        => count($expirations),
                'ExpirationDate' => $nearestExpirationDate,
            ];
        }

        foreach ($parsedPromos as $promo) {
            $this->AddSubAccount($promo);
        }
    }

    protected function parseCaptcha($siteKey)
    {
        $this->logger->notice(__METHOD__);

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => 'https://www.redbox.com',
            "websiteKey"   => $siteKey,
            "minScore"     => 0.3,
            "pageAction"   => "account_login",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'x-redbox-token'  => $this->State['x-redbox-token'],
            'x-redbox-apikey' => $this->State['apiKey'],
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.redbox.com/rbweb/api/account/user', $headers);
        $this->http->RetryCount = 2;
        $email = $this->http->JsonLog(null, 3, false, 'email')->d->data->user->email ?? null;
        $this->logger->debug("[Email]: {$email}");
        $this->logger->debug("[Login]: " . strtolower($this->AccountFields['Login']));

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors(): bool
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function catchLoginRequest($selenium)
    {
        $this->logger->notice(__METHOD__);

        try {
            $requests = $selenium->http->driver->browserCommunicator->getRecordedRequests();
        } catch (AwardWallet\Common\Selenium\BrowserCommunicatorException $e) {
            $this->logger->error("BrowserCommunicatorException: " . $e->getMessage(), ['HtmlEncode' => true]);

            $requests = [];
        }

        foreach ($requests as $xhr) {
            if (strstr($xhr->request->getUri(), 'login')) {
                $this->logger->debug('Catched login request');

                return $this->http->JsonLog(json_encode($xhr->response->getBody()));
            }
        }
    }
}
