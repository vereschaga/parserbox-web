<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;

class TAccountCheckerNordwind extends TAccountChecker
{
    use OtcHelper;

    private const REWARDS_PAGE_URL = "https://n4.websky.aero/graphql/query/nemo";

    private $headers = [
        "Accept"        => "*/*",
        "content-type"  => "application/json",
        "X-Currency"    => "RUB",
        "Origin"        => "https://nordwindairlines.ru",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->RetryCount = 0;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['authorization'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        unset($this->State['authorization']);

        $this->http->removeCookies();
        $this->http->GetURL('https://nordwindairlines.ru/ru/account#/account');

        $data = [
            "operationName" => "GetAccountInfo",
            "variables"     => [],
            "query"         => "query GetAccountInfo {\n  CurrentUser {\n    isFfpAuthorizationNeeded\n    authMethods {\n      id\n      value\n      confirmed\n      loginType\n      __typename\n    }\n    userProfile {\n      values {\n        name\n        value\n        __typename\n      }\n      __typename\n    }\n    ordersList {\n      ...AccountOrdersList\n      __typename\n    }\n    userFfpInfo {\n      ...UserFfpInfo\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment AccountOrdersList on AviaOrder {\n  id\n  timelimit\n  accessCode\n  canAddServices\n  canRemoveServices\n  servicesFallbackURL\n  locator\n  paymentStatus\n  status\n  customer {\n    values {\n      type\n      value\n      name\n      validationRules {\n        ...ValidationRule\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  travellers {\n    values {\n      type\n      value\n      name\n      validationRules {\n        ...ValidationRule\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  flight {\n    id\n    segmentGroups {\n      segments {\n        ...AccountSegment\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  price {\n    total {\n      ...Money\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment UserFfpInfo on UserFfpInfo {\n  cardNumber\n  numberOfMiles\n  numberOfQualifyingMiles\n  numberOfFlownSegments\n  milesForNextLevel\n  currentLevel\n  availableLevels\n  currentLevelMaxMiles\n  currentLevelMaxSegments\n  segmentsForNextLevel\n  __typename\n}\n\nfragment ValidationRule on ValidationRule {\n  with {\n    name\n    value\n    __typename\n  }\n  required\n  hidden\n  label\n  hint\n  placeholder\n  minDate\n  maxDate\n  maxLength\n  regExp {\n    pattern\n    error\n    __typename\n  }\n  options {\n    label\n    value\n    __typename\n  }\n  __typename\n}\n\nfragment AccountSegment on FlightSegment {\n  id\n  arrival {\n    airport {\n      id\n      iata\n      city {\n        name\n        images {\n          panorama {\n            url\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    date\n    __typename\n  }\n  departure {\n    airport {\n      id\n      iata\n      city {\n        name\n        images {\n          panorama {\n            url\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    date\n    __typename\n  }\n  __typename\n}\n\nfragment Money on Money {\n  amount\n  currency\n  __typename\n}\n",
        ];
        $this->http->PostURL(self::REWARDS_PAGE_URL, json_encode($data), $this->headers);
        $this->http->JsonLog();

        $authorization = $this->http->getCookieByName("hashed_value");

        if (!$authorization) {
            return $this->checkErrors();
        }

        $this->headers["authorization"] = "Bearer {$authorization}";

        $data = [
            "operationName" => "AccountExistence",
            "variables"     => [
                "params" => [
                    "login"     => $this->AccountFields['Login'],
                    "loginType" => "Email",
                ],
            ],
            "query"         => "query AccountExistence(\$params: SendSecureCodeParameters!) {\n  AccountExistence(parameters: \$params) {\n    message\n    result\n    __typename\n  }\n}\n",
        ];
        $this->http->PostURL(self::REWARDS_PAGE_URL, json_encode($data), $this->headers);

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $result = $response->data->AccountExistence->result ?? null;
        $message = $response->data->AccountExistence->message ?? null;

        if ($result === true && $this->parseQuestion()) {
            return false;
        }

        if ($result === false && $message == "Not found") {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $data = [
            "operationName" => "Authenticate",
            "variables"     => [
                "params" => [
                    "login"      => $this->AccountFields['Login'],
                    "loginType"  => "Email",
                    "secureCode" => $this->Answers[$this->Question],
                ],
            ],
            "query"         => "mutation Authenticate(\$params: AuthCredentials!) {\n  Authenticate(parameters: \$params) {\n    type\n    token\n    expiresIn\n    __typename\n  }\n}\n",
        ];
        unset($this->Answers[$this->Question]);
        $this->http->PostURL(self::REWARDS_PAGE_URL, json_encode($data), $this->State['headers']);
        $response = $this->http->JsonLog();

//        $error = $this->http->FindSingleNode('//error');
//
//        if ($error) {
//            $this->AskQuestion($this->Question, $error);
//
//            return false;
//        }// if ($error)

        $token = $response->data->Authenticate->token ?? null;

        if ($token) {
            $this->State['authorization'] = "Bearer {$response->data->Authenticate->token}";

            return $this->loginSuccessful();
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $firstName = "";
        $lastName = "";

        foreach ($response->data->CurrentUser->userProfile->values as $value) {
            if ($value->name == "lastName") {
                $lastName = $value->value;
            }

            if ($value->name == "firstName") {
                $firstName = $value->value;
            }
        }
        // Name
        $this->SetProperty('Name', beautifulName("{$firstName} {$lastName}"));

        // AccountID: 6070239
        if ($response->data->CurrentUser->userFfpInfo === null) {
            $this->SetWarning(self::NOT_MEMBER_MSG);

            return;
        }

        // Balance - Всего: ... миль.
        $this->SetBalance($response->data->CurrentUser->userFfpInfo->numberOfMiles);
        // Number
        $this->SetProperty('Number', $response->data->CurrentUser->userFfpInfo->cardNumber);
        // Level
        $this->SetProperty('Level', $response->data->CurrentUser->userFfpInfo->currentLevel);
        // Qualifying Miles
        $this->SetProperty('QualifyingMiles', $response->data->CurrentUser->userFfpInfo->numberOfQualifyingMiles);
        // Flown Segments
        $this->SetProperty('FlownSegments', $response->data->CurrentUser->userFfpInfo->numberOfFlownSegments);
        // Miles for next level
        $this->SetProperty('MilesForNextLevel', $response->data->CurrentUser->userFfpInfo->milesForNextLevel);
        // Segments for next level
        $this->SetProperty('SegmentsForNextLevel', $response->data->CurrentUser->userFfpInfo->segmentsForNextLevel);
    }

    public function ParseItineraries()
    {
        $data = [
            "operationName" => "GetOrdersList",
            "variables"     => [],
            "query"         => "query GetOrdersList {\n  CurrentUser {\n    ordersList {\n      ...AccountOrdersList\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment AccountOrdersList on AviaOrder {\n  id\n  timelimit\n  accessCode\n  canAddServices\n  canRemoveServices\n  servicesFallbackURL\n  locator\n  paymentStatus\n  status\n  customer {\n    values {\n      type\n      value\n      name\n      validationRules {\n        ...ValidationRule\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  travellers {\n    values {\n      type\n      value\n      name\n      validationRules {\n        ...ValidationRule\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  flight {\n    id\n    segmentGroups {\n      segments {\n        ...AccountSegment\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  price {\n    total {\n      ...Money\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment ValidationRule on ValidationRule {\n  with {\n    name\n    value\n    __typename\n  }\n  required\n  hidden\n  label\n  hint\n  placeholder\n  minDate\n  maxDate\n  maxLength\n  regExp {\n    pattern\n    error\n    __typename\n  }\n  options {\n    label\n    value\n    __typename\n  }\n  __typename\n}\n\nfragment AccountSegment on FlightSegment {\n  id\n  arrival {\n    airport {\n      id\n      iata\n      city {\n        name\n        images {\n          panorama {\n            url\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    date\n    __typename\n  }\n  departure {\n    airport {\n      id\n      iata\n      city {\n        name\n        images {\n          panorama {\n            url\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    date\n    __typename\n  }\n  __typename\n}\n\nfragment Money on Money {\n  amount\n  currency\n  __typename\n}\n",
        ];
        $this->http->PostURL(self::REWARDS_PAGE_URL, json_encode($data));
        $response = $this->http->JsonLog();
        $ordersList = $response->data->CurrentUser->ordersList ?? null;

        if ($ordersList === [] || $this->http->FindPreg("/^\s*\[\s*\{\s*\"data\": null\s*\},\s*\{\s*\"data\": null\s*\}\s*\]$/")) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        $itineraries = count($ordersList);
        $this->logger->debug("Total {$itineraries} itineraries were found");

        $this->sendNotification("refs # itineraries were found");

        foreach ($itineraries as $itinerary) {
//            $this->http->GetURL($itinerary->nodeValue);
//            $it = $this->parseItinerary();
//            $this->logger->debug('Parsed itinerary:');
//            $this->logger->debug(var_export($it, true), ['pre' => true]);
        }

        return [];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $data = [
            "operationName" => "GetAccountInfo",
            "variables"     => [],
            "query"         => "query GetAccountInfo {\n  CurrentUser {\n    isFfpAuthorizationNeeded\n    authMethods {\n      id\n      value\n      confirmed\n      loginType\n      __typename\n    }\n    userProfile {\n      values {\n        name\n        value\n        __typename\n      }\n      __typename\n    }\n    ordersList {\n      ...AccountOrdersList\n      __typename\n    }\n    userFfpInfo {\n      ...UserFfpInfo\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment AccountOrdersList on AviaOrder {\n  id\n  timelimit\n  accessCode\n  canAddServices\n  canRemoveServices\n  servicesFallbackURL\n  locator\n  paymentStatus\n  status\n  customer {\n    values {\n      type\n      value\n      name\n      validationRules {\n        ...ValidationRule\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  travellers {\n    values {\n      type\n      value\n      name\n      validationRules {\n        ...ValidationRule\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  flight {\n    id\n    segmentGroups {\n      segments {\n        ...AccountSegment\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  price {\n    total {\n      ...Money\n      __typename\n    }\n    __typename\n  }\n  splittedOrdersIds\n  parentOrderId\n  __typename\n}\n\nfragment UserFfpInfo on UserFfpInfo {\n  cardNumber\n  numberOfMiles\n  numberOfQualifyingMiles\n  numberOfFlownSegments\n  milesForNextLevel\n  currentLevel\n  availableLevels\n  currentLevelMaxMiles\n  currentLevelMaxSegments\n  segmentsForNextLevel\n  __typename\n}\n\nfragment ValidationRule on ValidationRule {\n  with {\n    name\n    value\n    type\n    __typename\n  }\n  required\n  hidden\n  label\n  hint\n  placeholder\n  minDate\n  maxDate\n  maxLength\n  regExp {\n    pattern\n    error\n    __typename\n  }\n  options {\n    label\n    value\n    __typename\n  }\n  __typename\n}\n\nfragment AccountSegment on FlightSegment {\n  id\n  arrival {\n    airport {\n      id\n      iata\n      city {\n        name\n        images {\n          panorama {\n            url\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    date\n    __typename\n  }\n  departure {\n    airport {\n      id\n      iata\n      city {\n        name\n        images {\n          panorama {\n            url\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    date\n    __typename\n  }\n  __typename\n}\n\nfragment Money on Money {\n  amount\n  currency\n  __typename\n}\n",
        ];
        $this->http->PostURL(self::REWARDS_PAGE_URL, json_encode($data), $this->headers + ["authorization" => $this->State['authorization']]);
        $this->http->JsonLog(null, 3, false, 'numberOfMiles');

        if (strstr($this->http->Response['body'], $this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $data = [
            "operationName" => "SendSecureCode",
            "variables"     => [
                "params" => [
                    "login"     => $this->AccountFields['Login'],
                    "loginType" => "Email",
                ],
            ],
            "query"         => "mutation SendSecureCode(\$params: SendSecureCodeParameters!) {\n  SendSecureCode(parameters: \$params) {\n    result\n    message\n    __typename\n  }\n}\n",
        ];
        $this->http->PostURL(self::REWARDS_PAGE_URL, json_encode($data), $this->headers);
        $response = $this->http->JsonLog();
        $message = $response->data->SendSecureCode->message ?? null;

        if ($message !== 'Code was sent') {
            return false;
        }

        $this->State['headers'] = $this->headers;

        $this->Question = "Мы отправили код подтверждения на {$this->AccountFields['Login']}";
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }

    private function parseItinerary()
    {
        $result = [];
        $bookNumber = '';
        $this->logger->info("Parse itinerary #{$bookNumber}", ['Header' => 3]);

        return $result;
    }
}
