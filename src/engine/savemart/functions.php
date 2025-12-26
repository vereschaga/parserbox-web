<?php
// use AwardWallet\Engine\ProxyList;

class TAccountCheckerSavemart extends TAccountChecker
{
    // use ProxyList;

    private string $swiftlyUserId;
    private string $chainId;
    private string $tsmcCardId;
    public $regionOptions = [
        ""                   => "Select your brand",
        "savemart"           => "Save Mart",
        "luckysupermarkets"  => "Lucky Supermarkets"
    ];

    private $swiftlyDomain = 'sm';

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields["Login2"]["Options"] = $this->regionOptions;
    }

    protected function checkRegionSelection($region)
    {
        if (empty($region) || !in_array($region, array_flip($this->regionOptions))) {
            $region = 'savemart';
        }

        if($region == 'savemart') {
            $this->swiftlyDomain = 'sm';
        } else {
            $this->swiftlyDomain = 'lu';
        }

        return $region;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'AvailableCashback')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        //$this->http->SetProxy($this->proxyReCaptcha()); // If graphql requests is not response
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
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

        if(!$this->http->GetURL('https://' . $this->AccountFields['Login2'] . '.com/accounts')) {
            return $this->checkErrors();
        }

        $data = [
            'email'     => $this->AccountFields['Login'],
            'password'  => $this->AccountFields['Pass'],
        ];
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://' . $this->AccountFields['Login2'] . '.com/api/pingCloudLogin', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->token->access_token)) {
            $this->http->setCookie('authToken', $response->token->access_token, '.savemart.com');
            $this->State['access_token'] = $response->token->access_token;

            return $this->loginSuccessful();
        }

        if ($message = $response->message ?? null) {
            $this->logger->error("[Error]: {$message}");

            if ($message == "Invalid Credentials") {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $userData = $this->http->JsonLog(null, 0);
        $fullName = $userData->message->FirstName . " " . $userData->message->LastName;
        // Name
        $this->SetProperty('Name', beautifulName($fullName));
        // Loyalty ID
        $this->SetProperty('Number', $userData->message->InitialCardID);

        if (empty($userData->message->HomeStore)) {
            return;
        }

        $postData = [
            "query" => 'query storeByNumber($storeNumber: Int!) { storeByNumber(storeNumber: $storeNumber) { storeId }}',
            "variables" => ["storeNumber" => $userData->message->HomeStore],
        ];
        $headers = [
            "Accept"        => "*/*",
            'Authorization' => "Bearer {$this->State['access_token']}",
            "Origin"        => 'https://' . $this->AccountFields['Login2'] . '.com',
            "Content-Type"  => "application/json",
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://" . $this->swiftlyDomain . ".swiftlyapi.net/graphql", json_encode($postData), $headers);
        $response = $this->http->JsonLog();


        if ((!$storeId = $response->data->storeByNumber->storeId ?? null) && $this->AccountFields['Login2'] == 'savemart') {
            return;
        };

        $postData = [
            "query" => 'query Rewards($storeId: UUID) { availableLoyaltyRewards(storeId: $storeId) { description displayName images { backgroundColor imageDensity purpose url } pointCost rewardId termsAndConditions } loyaltySummary { availablePoints summaryPoints { expiresOn points } issuedRewards { reward { description displayName images { backgroundColor imageDensity purpose url } pointCost rewardId termsAndConditions } expiryDateTime } } loyaltyCard { loyaltyId }} ',
            "variables" => ["storeId" => $storeId],
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://" . $this->swiftlyDomain . ".swiftlyapi.net/graphql", json_encode($postData), $headers);
        $rewards = $this->http->JsonLog();

        $this->http->GetURL("https://rebates.swiftlyapi.net/rebates/wallet/{$this->chainId}/{$this->swiftlyUserId}", $headers);
        $this->http->RetryCount = 2;
        $wallet = $this->http->JsonLog();

        // Available Cashback
        $this->AddSubAccount([
            'Code'           => ucfirst($this->AccountFields['Login2']) . 'AvailableCashback' . $userData->message->InitialCardID,
            'DisplayName'    => 'Available Cashback',
            'Balance'        => $this->http->FindPreg('/\d+/', false, $wallet->cashbackDisplay),
        ]);
        // Available points
        $this->SetBalance($rewards->data->loyaltySummary->availablePoints);

        $summaryPoints = $rewards->data->loyaltySummary->summaryPoints ?? null;

        if(isset($summaryPoints)) {
            if(count($summaryPoints) > 1) {
                $this->sendNotification("refs #17975 - need to check exp date // IZ");
            }

            if(count($summaryPoints) == 1 && isset($summaryPoints[0]->points, $summaryPoints[0]->expiresOn)) {
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $summaryPoints[0]->points);
                // Expiration Balance
                $this->SetExpirationDate(strtotime($summaryPoints[0]->expiresOn));
            }
        }

        $issuedRewards = $rewards->data->loyaltySummary->issuedRewards ?? [];

        foreach($issuedRewards as $reward) {

            if(isset($reward->reward->pointCost) && $reward->reward->pointCost !== 0) {
                $this->sendNotification("refs #23078 -  need to check subaccount balance // IZ");
                continue;
            }

            if(strtotime($reward->expiryDateTime) < time()) {
                continue;
            }

            $this->AddSubAccount([
                'Code' => ucfirst($this->AccountFields['Login2']) . $reward->reward->rewardId,
                'DisplayName' => $reward->reward->description,
                'Balance' => NULL,
                'ExpirationDate' => strtotime($reward->expiryDateTime)
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if(!isset($this->State['access_token'])) {
            return false;
        }

        $payload = $this->jwtExtractPayload($this->State['access_token']);

        if (!isset($payload->chain, $payload->sub)) {
            return false;
        }

        $this->swiftlyUserId = $payload->sub;
        $this->chainId = $payload->chain;
        $this->tsmcCardId = $payload->tsmc_card_id;

        $headers = [
            'Authorization' => $this->State['access_token'],
        ];

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://" . $this->AccountFields['Login2'] . ".com/api/getShimmedProfile?swiftlyUserId={$this->swiftlyUserId}&cardId={$this->tsmcCardId}&tsmcToken=null", $headers);
        $this->http->RetryCount = 0;

        $response = $this->http->JsonLog();
        $email = $response->data->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return true;
    }

    private function convertBase64UrlToBase64(string $input): string
    {
        $remainder = \strlen($input) % 4;

        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= \str_repeat('=', $padlen);
        }

        return \strtr($input, '-_', '+/');
    }

    private function jwtExtractPayload(string $jwt)
    {
        $this->logger->debug('DECODING JWT: ' . $jwt);
        $bodyb64 = explode('.', $jwt)[1];
        $this->logger->debug('JWT BODY: ' . $bodyb64);
        $bodyb64Prepared = $this->convertBase64UrlToBase64($bodyb64);
        $this->logger->debug('JWT BODY B64 PREPARED: ' . $bodyb64Prepared);
        $payloadRaw = base64_decode($bodyb64Prepared);
        $this->logger->debug('JWT PAYLOAD RAW: ' . $bodyb64Prepared);

        return $this->http->JsonLog($payloadRaw);
    }
}
