<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerHortons extends TAccountChecker
{
    use OtcHelper;
    use ProxyList;

    public static function FormatBalance($fields, $properties)
    {
        $format = [
            'CAD' => 'CA$',
            'USD' => '$',
        ];

        if (isset($properties['SubAccountCode'])
            && isset($properties['Currency'])
            && str_starts_with($properties['SubAccountCode'], 'hortonsGiftCard')
            && array_key_exists($properties['Currency'], $format)
        ) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "{$format[$properties['Currency']]}%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
//        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_USA));
    }

    public function IsLoggedIn(): bool
    {
        if (empty($this->State['authorization'])
            || empty($this->State['api_url'])
        ) {
            return false;
        }
        $url = 'https://www.timhortons.com/account';
        $this->http->GetURL($url);

        if ($url !== $this->http->currentUrl()) {
            return false;
        }

        $this->http->RetryCount = 0;
        $query = '{"operationName":"GetLoyaltyCards","variables":{},"query":"query GetLoyaltyCards {\n  getLoyaltyCards {\n    count\n    cards {\n      barcode\n      cardFormat\n      cardId\n      cardType\n      name\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->PostURL($this->State['api_url'], $query, ['authorization' => $this->State['authorization']] + $this->headers());
        $this->http->RetryCount = 2;

        return !empty($this->http->JsonLog()->data->getLoyaltyCards);
    }

    public function LoadLoginForm(): bool
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.timhortons.com/signin');

        if (!str_contains($this->http->FindSingleNode('//title'), '')) {
            return $this->checkErrors();
        }

        $urlToScript = $this->http->FindPreg('/(\/static\/js\/main\.\w+\.chunk\.js)/');

        if (is_null($urlToScript)) {
            return $this->checkErrors();
        }

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $this->http->GetURL('https://www.timhortons.com' . $urlToScript);

        // essential configurations from JS
        $config = $this->http->FindPreg('/aws:(\{gqlApiUrl:[^}]+})/');
        $this->State['api_url'] = $this->http->FindPreg('/\WgqlApiUrl:\s*"([^"]+)/', false, $config);
        $this->State['api_url_gateway'] = $this->http->FindPreg('/\WgqlGatewayApiUrl:\s*"([^"]+)/', false, $config);
        $this->State['region'] = $this->http->FindPreg('/\Wregion:\s*"([^"]+)/', false, $config);
        $this->State['userPoolClientId'] = $this->http->FindPreg('/\WuserPoolClientId:\s*"([^"]+)/', false, $config);

        $this->State['sessionId'] = $this->generate_uuid();
        $data = '{"operationName":"CreateOTP","variables":{"input":{"email":"' . $this->AccountFields['Login'] . '","platform":"web","sessionId":"' . $this->State['sessionId'] . '"}},"query":"mutation CreateOTP($input: CreateOTPInput!) {\n  createOTP(input: $input) {\n    maxValidateAttempts\n    ttl\n    __typename\n  }\n}\n"}';
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"  => "*/*",
            'Referer' => 'https://www.timhortons.com/signin',
        ];
        $this->http->PostURL($this->State['api_url_gateway'], $data, $this->headers() + $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login(): bool
    {
        $message = $this->http->JsonLog()->errors[0]->message ?? null;

        if (isset($message)
            && str_contains($message, 'Request failed')
        ) {
            // means OTP has been already generated recently, so we need to press 'Send New Code' button
            $data = '{"operationName":"ResendCurrentLoginOTP","variables":{"input":{"email":"' . $this->AccountFields['Login'] . '","platform":"web"}},"query":"mutation ResendCurrentLoginOTP($input: ResendOTPInput!) {\n  resendCurrentLoginOTP(input: $input) {\n    maxValidateAttempts\n    ttl\n    __typename\n  }\n}\n"}';
            $this->http->RetryCount = 0;
            $this->http->PostURL($this->State['api_url_gateway'], $data, $this->headers());
            $this->http->RetryCount = 2;
            $message = $this->http->JsonLog()->errors[0]->message ?? null;
        }

        if (isset($message)) {
            $this->logger->error($message);

            if (str_contains($message, 'email not registered')) {
                throw new CheckException('This user does not exist', ACCOUNT_INVALID_PASSWORD);
            }

            if (str_contains($message, 'Request throttled')) {
                $this->sendNotification('refs #9994 request throttled // BS');
            }
            $this->DebugInfo = $message;

            return false;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step): bool
    {
        $this->logger->notice(__METHOD__);
        $data = '{"operationName":"ValidateOTP","variables":{"input":{"code":"' . $this->Answers[$this->Question] . '","email":"' . $this->AccountFields['Login'] . '","sessionId":"' . $this->State['sessionId'] . '"}},"query":"mutation ValidateOTP($input: ExchangeOTPCodeForCredentialsInput!) {\n  exchangeOTPCodeForCognitoCredentials(input: $input) {\n    challengeCode\n    sessionId\n  }\n}\n"}';
        unset($this->Answers[$this->Question]);
        $this->http->RetryCount = 0;
        $this->http->PostURL($this->State['api_url_gateway'], $data, $this->headers());
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if (is_array($response->errors ?? null)) {
            $error = $response->errors[0]->message ?? null;
            $this->logger->error($error);

            if (str_contains($error, 'OTP Validation failed')) {
                $this->AskQuestion($this->Question, "The code you entered doesn't match the code we sent. Check your messages and try typing it in again.", 'Question');

                return false;
            }
            $this->DebugInfo = $error;

            return false;
        }

        $answer = $response->data->exchangeOTPCodeForCognitoCredentials->challengeCode ?? null;
        $sessionId = $response->data->exchangeOTPCodeForCognitoCredentials->sessionId ?? null;

        if (empty($answer) || empty($sessionId)) {
            return $this->checkErrors();
        }

        $data = [
            'ChallengeName'      => 'CUSTOM_CHALLENGE',
            'ChallengeResponses' => [
                'ANSWER'   => $answer,
                'USERNAME' => $this->AccountFields['Login'],
            ],
            'ClientId' => $this->State['userPoolClientId'],
            'Session'  => $sessionId,
        ];
        $this->http->PostURL('https://cognito-idp.' . $this->State['region'] . '.amazonaws.com/', json_encode($data), [
            'Content-Type'     => 'application/x-amz-json-1.1',
            'Origin'           => 'https://www.timhortons.com',
            'X-Amz-Target'     => 'AWSCognitoIdentityProviderService.RespondToAuthChallenge',
            'X-Amz-User-Agent' => 'aws-amplify/5.0.4 js',
        ]);
        $response = $this->http->JsonLog()->AuthenticationResult ?? null;
        $tokenType = $response->TokenType ?? null;
        $token = $response->IdToken ?? null;

        if (is_null($tokenType) || is_null($token)) {
            $error = $response->message ?? null;
            $this->logger->error($error);

            if ($error == 'User does not exist.') {
                throw new CheckException('This user does not exist', ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        $this->State['authorization'] = $tokenType . ' ' . $token;

        return true;
    }

    public function Parse(): void
    {
        $this->http->setDefaultHeader('authorization', $this->State['authorization']);
        $this->http->RetryCount = 0;
        $query = '[{"operationName":"GetMe","variables":{"numUniquePurchasedItems":10,"customInput":{"shouldUniqueByModifiers":true}},"query":"query GetMe($numUniquePurchasedItems: Int, $customInput: MeInput) {\n  me(numUniquePurchasedItems: $numUniquePurchasedItems, customInput: $customInput) {\n    thLegacyCognitoId\n    cognitoId\n    details {\n      ...DetailsFragment\n      __typename\n    }\n    loyaltyId\n    uniquePurchasedItems {\n      name\n      image\n      isExtra\n      isDonation\n      lineId\n      sanityId\n      quantity\n      type\n      price\n      url\n      pickerSelections\n      isInMenu\n      vendorConfigs {\n        ...VendorConfigsFragment\n        __typename\n      }\n      children {\n        ...CartEntryFragment\n        isInMenu\n        children {\n          ...CartEntryFragment\n          isInMenu\n          children {\n            ...CartEntryFragment\n            isInMenu\n            children {\n              ...CartEntryFragment\n              isInMenu\n              children {\n                ...CartEntryFragment\n                isInMenu\n                children {\n                  ...CartEntryFragment\n                  isInMenu\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment DetailsFragment on UserDetails {\n  name\n  dob\n  phoneNumber\n  email\n  emailVerified\n  promotionalEmails\n  isoCountryCode\n  zipcode\n  defaultAccountIdentifier\n  defaultFdAccountId\n  defaultPaymentAccountId\n  defaultScanAndPayAccountIdentifier\n  defaultReloadAmt\n  autoReloadEnabled\n  autoReloadThreshold\n  deliveryAddresses {\n    addressLine1\n    addressLine2\n    city\n    country\n    latitude\n    longitude\n    phoneNumber\n    route\n    state\n    streetNumber\n    zip\n    __typename\n  }\n  communicationPreferences {\n    id\n    value\n    __typename\n  }\n  favoriteStores {\n    storeId\n    storeNumber\n    __typename\n  }\n  loyaltyTier\n  rutrPassedSkillsTestTimestamp\n  rutrFailedSkillsTestTimestamp\n  showThLoyaltyOnboarding\n  __typename\n}\n\nfragment VendorConfigsFragment on VendorConfigs {\n  carrols {\n    ...VendorConfigFragment\n    __typename\n  }\n  carrolsDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  ncr {\n    ...VendorConfigFragment\n    __typename\n  }\n  ncrDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  oheics {\n    ...VendorConfigFragment\n    __typename\n  }\n  oheicsDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  partner {\n    ...VendorConfigFragment\n    __typename\n  }\n  partnerDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  productNumber {\n    ...VendorConfigFragment\n    __typename\n  }\n  productNumberDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  sicom {\n    ...VendorConfigFragment\n    __typename\n  }\n  sicomDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  qdi {\n    ...VendorConfigFragment\n    __typename\n  }\n  qdiDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  qst {\n    ...VendorConfigFragment\n    __typename\n  }\n  qstDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  rpos {\n    ...VendorConfigFragment\n    __typename\n  }\n  rposDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  simplyDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  simplyDeliveryDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  tablet {\n    ...VendorConfigFragment\n    __typename\n  }\n  tabletDelivery {\n    ...VendorConfigFragment\n    __typename\n  }\n  __typename\n}\n\nfragment VendorConfigFragment on VendorConfig {\n  pluType\n  parentSanityId\n  pullUpLevels\n  constantPlu\n  quantityBasedPlu {\n    quantity\n    plu\n    qualifier\n    __typename\n  }\n  multiConstantPlus {\n    quantity\n    plu\n    qualifier\n    __typename\n  }\n  parentChildPlu {\n    plu\n    childPlu\n    __typename\n  }\n  sizeBasedPlu {\n    comboPlu\n    comboSize\n    __typename\n  }\n  __typename\n}\n\nfragment CartEntryFragment on CartEntries {\n  _id: lineId\n  lineId\n  image\n  isDonation\n  isExtra\n  name\n  pickerSelections\n  price\n  quantity\n  sanityId\n  type\n  url\n  redeemable\n  vendorConfigs {\n    ...VendorConfigsFragment\n    __typename\n  }\n  __typename\n}\n"},{"operationName":"GetLoyaltyCards","variables":{},"query":"query GetLoyaltyCards {\n  getLoyaltyCards {\n    count\n    cards {\n      barcode\n      cardFormat\n      cardId\n      cardType\n      name\n      __typename\n    }\n    __typename\n  }\n}\n"}]';
        $this->http->PostURL($this->State['api_url'], $query, $this->headers());
        $response = $this->http->JsonLog(null, 3, false, 'cardId');

        if (!is_array($response)) {
            return;
        }

        if (isset($response[0]->errors)) {
            return;
        }
        $this->SetProperty('Name', $response[0]->data->me->details->name ?? null);

        $currencyCodes = [
            'CAN' => 'CAD',
            'USA' => 'USD',
        ];
        $country = $response[0]->data->me->details->isoCountryCode ?? null;
        // Currency
        if (array_key_exists($country, $currencyCodes)) {
            $currency = $currencyCodes[$country];
        } else {
            $this->sendNotification('refs #9994 unsupported country // BS');
        }

        $cards = $response[1]->data->getLoyaltyCards->cards ?? [];

        if (count($cards) > 1) {
            $this->sendNotification('refs #9994 found more than 1 card // BS');
        }
        $card = $cards[0];
        $cardId = $card->cardId ?? null;
        $query = '[{"operationName":"GetLoyaltyCardDetails","variables":{"cardId":"' . $cardId . '"},"query":"query GetLoyaltyCardDetails($cardId: String!) {\n  getLoyaltyCardDetails(cardId: $cardId) {\n    availableRedemptions\n    barcode\n    canEarnVisit\n    canRedeemReward\n    cardType\n    discountActiveUntil\n    donationUpcharge\n    numberOfTransactionsInTimePeriod\n    numberOfTransactionsMadeTowardsNextReward\n    numberOfTransactionsNeeded\n    periodEndTimestamp\n    periodStartTimestamp\n    pointExpiry {\n      points\n      expirationDate\n      __typename\n    }\n    points\n    scanAndPay\n    __typename\n  }\n}\n"}]';
        $this->http->PostURL($this->State['api_url'], $query, $this->headers());
        $response = $this->http->JsonLog(null, 3, false, 'points')[0]->data->getLoyaltyCardDetails ?? null;
        $this->SetBalance($response->points ?? null);
        $exp = $response->pointExpiry ?? null;

        // Expiration date and expiring balance
        if (!empty($exp)
            && is_array($exp)
            && !empty($exp[0]->points)
            && strtotime($exp[0]->expirationDate ?? '')
        ) {
            $this->SetProperty('ExpiringBalance', $exp[0]->points);
            $this->SetExpirationDate(strtotime($exp[0]->expirationDate));
        }

        $barcode = [];

        if (isset($card->barcode)) {
            $barcode['BarCodeType'] = BAR_CODE_QR;
            $barcode['BarCode'] = $card->barcode;
        }

        $query = '{"operationName":"UserAccounts","variables":{"feCountryCode":"' . $country . '"},"query":"query UserAccounts($feCountryCode: IsoCountryCode!) {\n  userAccounts(feCountryCode: $feCountryCode) {\n    count\n    accounts {\n      accountIdentifier\n      fdAccountId\n      chaseProfileId\n      credit {\n        alias\n        cardType\n        expiryYear\n        expiryMonth\n        panToken\n        __typename\n      }\n      prepaid {\n        alias\n        currentBalance\n        cardNumber\n        expiryMonth\n        expiryYear\n        feFormattedCurrentBalance\n        shuffledCardNumber\n        __typename\n      }\n      paypal\n      paypalIdentifier\n      __typename\n    }\n    paymentProcessor\n    __typename\n  }\n}\n"}';
        $this->http->PostURL($this->State['api_url'], $query, $this->headers());
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'currentBalance');

        if (!is_array($response->data->userAccounts->accounts ?? null)) {
            return;
        }

        $giftCard = $response->data->userAccounts->accounts[0]->prepaid ?? null;

        if (!is_numeric($giftCard->currentBalance ?? null)
            || !is_numeric($giftCard->cardNumber ?? null)
            || !is_numeric($giftCard->alias ?? null)
        ) {
            return;
        }

        $this->AddSubAccount([
            'Code'        => 'hortonsGiftCard' . $giftCard->cardNumber,
            'DisplayName' => "Tim Card ($giftCard->alias)",
            'Balance'     => $giftCard->currentBalance / 100,
            'Currency'    => $currency ?? null,
        ] + $barcode
        );
    }

    private function generate_uuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function headers(): array
    {
        return [
            'Content-Type'    => 'application/json',
            'Origin'          => 'https://www.timhortons.com',
            'Referer'         => 'https://www.timhortons.com',
            'x-ui-platform'   => 'web',
            'x-ui-language'   => 'en',
            'x-ui-region'     => 'US',
            'x-user-datetime' => date_format(new DateTime(), "Y-m-d\TH:i:sP"),
        ];
    }

    private function parseQuestion(): bool
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);

        if (is_array($response)
            || (empty($response->data->createOTP)
            && empty($response->data->resendCurrentLoginOTP))
        ) {
            return $this->checkErrors();
        }

        $this->Question = 'Verify with code. We sent an email with login instructions to ' . $this->AccountFields['Login'];
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }

    private function checkErrors(): bool
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
