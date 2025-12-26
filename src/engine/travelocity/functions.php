<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerTravelocity extends TAccountChecker
{
    /* travelocity looks like expedia  */

    use PriceTools;
    use ProxyList;
    /** @var CaptchaRecognizer */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->RetryCount = 0;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.travelocity.com/user/account", [], 20);

        // crocked server workaround
        if ($this->http->Response['code'] == 403) {
            $this->http->SetProxy($this->proxyDOP());
            $this->http->GetURL("https://www.travelocity.com/user/account", [], 20);
        }

        $this->http->RetryCount = 2;
        $this->delay();

        if ($this->http->Response['code'] == 200 && $this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.travelocity.com/login");

        $pointOfSaleId = $this->http->FindPreg("/\"pointOfSaleId.\":.\"([^\\\]+)/");
        $uiBrand = $this->http->FindPreg("/\"uiBrand.\":.\"([^\\\]+)/");
        $deviceId = $this->http->FindPreg("/\"deviceId.\":.\"([^\\\]+)/");
        $remoteAddress = $this->http->FindPreg("/\"remoteAddress.\":.\"([^\\\]+)/");
        $guid = $this->http->FindPreg("/\"guid.\":.\"([^\\\]+)/");
        $traceId = $this->http->FindPreg("/\"traceId.\":.\"([^\\\]+)/");
        $tpid = $this->http->FindPreg("/.\"tpid.\":(\d+)/");
        $site_id = $this->http->FindPreg("/_site_id.\":(\d+)/");
        $eapid = $this->http->FindPreg("/.\"eapid.\":(\d+)/");
        $csrf = $this->http->FindPreg("/\,.\"login.\":.\"([^\\\]+).\",.\"mf/");

        if (!$this->http->ParseForm("loginForm") || !$csrf || !$deviceId || !$uiBrand || !$pointOfSaleId) {
            return $this->checkErrors();
        }

        $captcha = $this->parseFunCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "username"      => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "rememberMe"    => true,
            "channelType"   => "WEB",
            "devices"       => [
                [
                    "payload" => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"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;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","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1777,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":314227,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"80001,www.travelocity.com,Travelocity,ULX","requestURL":"https://www.travelocity.com/login","userAgent":"' . $this->http->userAgent . '","placement":"LOGIN","placementPage":"80001","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                    "type"    => "TRUST_WIDGET",
                ],
            ],
            "atoShieldData" => [
                "atoTokens" => [
                    "fc-token"   => $captcha,
                    "rememberMe" => "",
                    "email"      => $this->AccountFields['Login'],
                ],
                "placement" => "login",
            ],
            "csrfData"      => [
                "csrfToken" => $csrf,
                "placement" => "login",
            ],
        ];

        $headers = [
            "Accept"               => "application/json",
            "accept-encoding"      => "gzip, deflate, br",
            "brand"                => $uiBrand,
            "content-type"         => "application/json",
            "device-type"          => "DESKTOP",
            "device-user-agent-id" => $deviceId,
            "eapid"                => $eapid,
            "pointofsaleid"        => $pointOfSaleId,
            "siteid"               => $site_id,
            "tpid"                 => $tpid,
            "trace-id"             => $traceId,
            "x-mc1-guid"           => $guid,
            "x-remote-addr"        => $remoteAddress,
            "X-USER-AGENT"         => $this->http->userAgent,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.travelocity.com/eg-auth-svcs/authenticate/password", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status === true) {
            $this->http->GetURL("https://www.travelocity.com/user/account");

            return $this->loginSuccessful();
        }

        $failure = $response->failure ?? null;
        $requestId = $response->requestId ?? null;
        $message = $response->message ?? null;

        if ($status === false && $failure === null && $requestId === null) {
            throw new CheckException("Email and password don't match. Try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message === 'Email constraint not met') {
            throw new CheckException("Enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $name = $this->http->FindSingleNode("//li[@id = 'fullname']");

        if (!isset($name)) {
            $name = $this->http->FindPreg('/>([^\'>]*)\'s information/ims');
            $name = preg_replace('/&nbsp;/ims', ' ', $name);
        }

        if (empty($name)) {
            if ($prop11 = $this->http->FindPreg("/\"prop11\":\"([^\"]+)/ims")) {
                $this->http->GetURL("https://www.travelocity.com/users/{$prop11}/profile?_=" . time() . date("B"));
            }
            $response = $this->http->JsonLog();

            if (isset($response->firstname, $response->middlename, $response->lastname)) {
                $name = CleanXMLValue($response->firstname . " " . $response->middlename . " " . $response->lastname);
            }
        }
        $this->SetProperty("Name", beautifulName($name));

        if (!empty($this->Properties['Name']) || isset($response->id)) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->http->FilterHTML = false;
        $this->http->GetURL('https://www.travelocity.com/trips');
        $this->delay();
        $expedia = $this->getExpedia();

        return $expedia->ParseItineraries('www.travelocity.com', $this->ParsePastIts);
    }

    public function GetConfirmationFields()
    {
        return [
            "Email" => [
                "Caption"  => "Email Address",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
            "ConfNo" => [
                "Caption"  => "Itinerary Number",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.travelocity.com/trips/booking-search?view=SEARCH_BY_ITINERARY_NUMBER_AND_EMAIL";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $this->sendNotification('check confirmation // MI');
        $duaid = $this->http->FindPreg('#\\\\"duaid\\\\":\\\\"([\w\-]+)\\\\",\\\\"#');

        if (!isset($duaid)) {
            return [];
        }

        $headers = [
            'Accept'               => '*/*',
            'Content-Type'         => 'application/json',
            'client-info'          => 'trips-pwa,4484d3137c7eb9c7e64b788b606f32fdb276b64b,us-west-2',
            'Origin'               => 'https://www.travelocity.com',
        ];
        $data = '[{"operationName":"TripSearchBookingQuery","variables":{"viewType":"SEARCH_RESULT","context":{"siteId":69,"locale":"en_US","eapid":0,"currency":"BRL","device":{"type":"DESKTOP"},"identity":{"duaid":"' . $duaid . '","expUserId":"-1","tuid":"-1","authState":"ANONYMOUS"},"privacyTrackingState":"CAN_NOT_TRACK","debugContext":{"abacusOverrides":[],"alterMode":"RELEASED"}},"searchInput":[{"key":"EMAIL_ADDRESS","value":"' . $arFields['Email'] . '"},{"key":"ITINERARY_NUMBER","value":"' . $arFields['ConfNo'] . '"}]},"query":"query TripSearchBookingQuery($context: ContextInput!, $searchInput: [GraphQLPairInput!], $viewType: TripsSearchBookingView!) {\n  trips(context: $context) {\n    searchBooking(searchInput: $searchInput, viewType: $viewType) {\n      ...TripsViewFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsViewFragment on TripsView {\n  __typename\n  ...TripsViewContentFragment\n  floatingActionButton {\n    ...TripsFloatingActionButtonFragment\n    __typename\n  }\n  ...TripsDynamicMapFragment\n  pageTitle\n  contentType\n  tripsSideEffects {\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n}\n\nfragment TripsViewContentFragment on TripsView {\n  __typename\n  header {\n    ...ViewHeaderFragment\n    __typename\n  }\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsSectionContainerFragment\n    ...TripsFormContainerFragment\n    ...TripsListFlexContainerFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsSlimCardFragment\n    ...TripsMapCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsPageBreakFragment\n    ...TripsContainerDividerFragment\n    ...TripsLodgingUpgradesPrimerFragment\n    ...TripItemContextualCardsPrimerFragment\n    __typename\n  }\n  notifications: customerNotifications {\n    ...TripsCustomerNotificationsFragment\n    __typename\n  }\n  toast {\n    ...TripsToastFragment\n    __typename\n  }\n  contentType\n}\n\nfragment ViewHeaderFragment on TripsViewHeader {\n  __typename\n  primary\n  secondaries\n  toolbar {\n    ...ToolbarFragment\n    __typename\n  }\n  signal {\n    type\n    reference\n    __typename\n  }\n}\n\nfragment TripsTertiaryButtonFragment on TripsTertiaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsLinkActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    __typename\n  }\n}\n\nfragment TripsLinkActionFragment on TripsLinkAction {\n  __typename\n  resource {\n    value\n    __typename\n  }\n  target\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  clickstreamAnalytics {\n    ...ClickstreamAnalyticsFragment\n    __typename\n  }\n}\n\nfragment ClickstreamAnalyticsFragment on ClickstreamAnalytics {\n  event {\n    clickstreamTraceId\n    eventCategory\n    eventName\n    eventType\n    eventVersion\n    __typename\n  }\n  payload {\n    ... on TripRecommendationModule {\n      title\n      responseId\n      recommendations {\n        id\n        position\n        priceDisplayed\n        currencyCode\n        name\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment MapDirectionsActionFragment on TripsMapDirectionsAction {\n  __typename\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  url\n}\n\nfragment TripsWriteToClipboardActionFragment on CopyToClipboardAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  value\n}\n\nfragment TripsVirtualAgentInitActionFragment on TripsVirtualAgentInitAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  applicationName\n  pageName\n  clientOverrides {\n    enableAutoOpenChatWidget\n    enableProactiveConversation\n    subscribedEvents\n    conversationProperties {\n      launchPoint\n      pageName\n      skipWelcome\n      __typename\n    }\n    intentMessage {\n      ... on VirtualAgentCancelIntentMessage {\n        action\n        intent\n        emailAddress\n        orderLineId\n        orderNumber\n        product\n        __typename\n      }\n      __typename\n    }\n    intentArguments {\n      id\n      value\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsOpenDialogActionFragment on TripsOpenDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  modalDialog {\n    ...TripsModalDialogFragment\n    __typename\n  }\n}\n\nfragment TripsModalDialogFragment on TripsModalDialog {\n  __typename\n  heading\n  buttonLayout\n  buttons {\n    ...TripsDialogPrimaryButtonFragment\n    ...TripsDialogSecondaryButtonFragment\n    ...TripsDialogTertiaryButtonFragment\n    __typename\n  }\n  content {\n    ...TripsEmbeddedContentListFragment\n    __typename\n  }\n}\n\nfragment TripsDialogPrimaryButtonFragment on TripsPrimaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    ...TripsDeleteTripActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    ...TripsLinkActionFragment\n    __typename\n  }\n}\n\nfragment TripsDialogTertiaryButtonFragment on TripsTertiaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    __typename\n  }\n}\n\nfragment TripsDialogSecondaryButtonFragment on TripsSecondaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    __typename\n  }\n}\n\nfragment TripsCloseDialogActionFragment on TripsCloseDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsDeleteTripActionFragment on TripsDeleteTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  overview {\n    tripViewId\n    filter\n    __typename\n  }\n}\n\nfragment TripsCancelCarActionFragment on TripsCancelCarAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  orderLineNumbers\n}\n\nfragment TripsCancelInsuranceActionFragment on TripsCancelInsuranceAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  orderLineNumber\n}\n\nfragment TripsCancelActivityActionFragment on TripsCancelActivityAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  activityOrderLineNumbers: orderLineNumbers\n  orderNumber\n}\n\nfragment TripsCancellationActionFragment on TripsCancellationAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  itemToCancel: item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  cancellationType\n  cancellationAttributes {\n    orderNumber\n    orderLineNumbers\n    refundAmount\n    penaltyAmount\n    __typename\n  }\n}\n\nfragment TripsUnsaveItemFromTripActionFragment on TripsUnsaveItemFromTripAction {\n  __typename\n  tripEntity\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  tripItem {\n    tripItemId\n    tripViewId\n    filter\n    __typename\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsEmitSignalFragment on TripsEmitSignal {\n  signal {\n    type\n    reference\n    __typename\n  }\n  values {\n    key\n    value {\n      ...TripsSignalFieldIdValueFragment\n      ...TripsSignalFieldIdExistingValuesFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSignalFieldIdExistingValuesFragment on TripsSignalFieldIdExistingValues {\n  ids\n  prefixes\n  __typename\n}\n\nfragment TripsSignalFieldIdValueFragment on TripsSignalFieldIdValue {\n  id\n  __typename\n}\n\nfragment TripsEmbeddedContentListFragment on TripsEmbeddedContentList {\n  __typename\n  primary\n  secondaries\n  listTheme: theme\n  items {\n    ...TripsEmbeddedContentLineItemFragment\n    __typename\n  }\n}\n\nfragment TripsEmbeddedContentLineItemFragment on TripsEmbeddedContentLineItem {\n  __typename\n  items {\n    __typename\n    ...TripsEmbeddedContentItemFragment\n  }\n}\n\nfragment TripsEmbeddedContentItemFragment on TripsEmbeddedContentItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    __typename\n  }\n}\n\nfragment TripsOpenFullScreenDialogActionFragment on TripsOpenFullScreenDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  dialog {\n    ...TripsFullScreenDialogFragment\n    __typename\n  }\n}\n\nfragment TripsFullScreenDialogFragment on TripsFullScreenDialog {\n  __typename\n  heading\n  closeButton {\n    ...TripsCloseDialogButtonFragment\n    __typename\n  }\n  content {\n    ...TripsEmbeddedContentCardFragment\n    __typename\n  }\n}\n\nfragment TripsCloseDialogButtonFragment on TripsCloseDialogButton {\n  __typename\n  primary\n  icon {\n    __typename\n    id\n    description\n    title\n  }\n  action {\n    __typename\n    analytics {\n      __typename\n      referrerId\n      linkName\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n    }\n  }\n}\n\nfragment TripsEmbeddedContentCardFragment on TripsEmbeddedContentCard {\n  __typename\n  primary\n  items {\n    ...TripsEmbeddedContentListFragment\n    __typename\n  }\n}\n\nfragment TripsOpenMenuActionFragment on TripsOpenMenuAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  floatingMenu {\n    ...TripsFloatingMenuFragment\n    __typename\n  }\n}\n\nfragment TripsFloatingMenuFragment on TripsFloatingMenu {\n  items {\n    ...TripsMenuTitleFragment\n    ...TripsMenuListItemFragment\n    ...TripsMenuListTitleFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMenuListItemFragment on TripsMenuListItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenChangeDatesDatePickerActionFragment\n    ...TripsOpenEmailDrawerActionFragment\n    ...TripsOpenEditTripDrawerActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsOpenSaveToTripDrawerActionFragment\n    ...TripsCustomerNotificationOpenInAppActionFragment\n    __typename\n  }\n}\n\nfragment TripsNavigateToViewActionFragment on TripsNavigateToViewAction {\n  __typename\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  tripItemId\n  tripViewId\n  viewFilter {\n    filter\n    __typename\n  }\n  viewType\n  viewUrl\n}\n\nfragment TripsOpenChangeDatesDatePickerActionFragment on TripsOpenChangeDatesDatePickerAction {\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  attributes {\n    ...TripsListDatePickerAttributesFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListDatePickerAttributesFragment on TripsDatePickerAttributes {\n  analytics {\n    closeAnalytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  buttonText\n  changeDatesAction {\n    analytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    item {\n      filter\n      tripItemId\n      tripViewId\n      __typename\n    }\n    tripEntity\n    __typename\n  }\n  maxDateRange\n  maxDateRangeMessage\n  calendarSelectionType\n  daysBookableInAdvance\n  itemDates {\n    end {\n      ...TripsListDateFragment\n      __typename\n    }\n    start {\n      ...TripsListDateFragment\n      __typename\n    }\n    __typename\n  }\n  productId\n  __typename\n}\n\nfragment TripsListDateFragment on Date {\n  day\n  month\n  year\n  __typename\n}\n\nfragment TripsOpenEmailDrawerActionFragment on TripsOpenEmailDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    __typename\n    filter\n    tripItemId\n    tripViewId\n  }\n}\n\nfragment TripsOpenEditTripDrawerActionFragment on TripsOpenEditTripDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n}\n\nfragment TripsOpenMoveTripItemDrawerActionFragment on TripsOpenMoveTripItemDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  item {\n    filter\n    tripEntity\n    tripItemId\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsOpenSaveToTripDrawerActionFragment on TripsOpenSaveToTripDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  input {\n    itemId\n    source\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsListSaveItemAttributesFragment on TripsSaveItemAttributes {\n  ...TripsListSaveStayAttributesFragment\n  ...TripsListSaveActivityAttributesFragment\n  ...TripsSaveFlightSearchAttributesFragment\n  __typename\n}\n\nfragment TripsListSaveActivityAttributesFragment on TripsSaveActivityAttributes {\n  regionId\n  dateRange {\n    start {\n      ...TripsListDateFragment\n      __typename\n    }\n    end {\n      ...TripsListDateFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListSaveStayAttributesFragment on TripsSaveStayAttributes {\n  checkInDate {\n    ...TripsListDateFragment\n    __typename\n  }\n  checkoutDate {\n    ...TripsListDateFragment\n    __typename\n  }\n  regionId\n  roomConfiguration {\n    numberOfAdults\n    childAges\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSaveFlightSearchAttributesFragment on TripsSaveFlightSearchAttributes {\n  searchCriteria {\n    primary {\n      journeyCriterias {\n        arrivalDate {\n          ...TripsListDateFragment\n          __typename\n        }\n        departureDate {\n          ...TripsListDateFragment\n          __typename\n        }\n        destination\n        destinationAirportLocationType\n        origin\n        originAirportLocationType\n        __typename\n      }\n      searchPreferences {\n        advancedFilters\n        airline\n        cabinClass\n        __typename\n      }\n      travelers {\n        age\n        type\n        __typename\n      }\n      tripType\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsCustomerNotificationOpenInAppActionFragment on TripsCustomerNotificationOpenInAppAction {\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  notificationAttributes {\n    notificationLocation\n    xPageID\n    optionalContext {\n      tripItemId\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMenuListTitleFragment on TripsMenuListTitle {\n  __typename\n  primary\n}\n\nfragment TripsMenuTitleFragment on TripsMenuTitle {\n  __typename\n  primary\n}\n\nfragment ClientSideImpressionAnalyticsFragment on ClientSideImpressionAnalytics {\n  uisPrimeAnalytics {\n    ...ClientSideAnalyticsFragment\n    __typename\n  }\n  clickstreamAnalytics {\n    ...ClickstreamAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment ClientSideAnalyticsFragment on ClientSideAnalytics {\n  eventType\n  linkName\n  referrerId\n  uisPrimeMessages {\n    messageContent\n    schemaName\n    __typename\n  }\n  __typename\n}\n\nfragment TripsOpenInviteDrawerActionFragment on TripsOpenInviteDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n}\n\nfragment TripsOpenCreateNewTripDrawerActionFragment on TripsOpenCreateNewTripDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsOpenCreateNewTripDrawerForItemActionFragment on TripsOpenCreateNewTripDrawerForItemAction {\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  createTripMetadata {\n    moveItem {\n      filter\n      tripEntity\n      tripItemId\n      tripViewId\n      __typename\n    }\n    saveItemInput {\n      itemId\n      pageLocation\n      attributes {\n        ...TripsListSaveItemAttributesFragment\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInviteActionFragment on TripsInviteAction {\n  __typename\n  inputIds\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsSaveNewTripActionFragment on TripsSaveNewTripAction {\n  __typename\n  inputIds\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsFormActionFragment on TripsFormAction {\n  __typename\n  validatedInputIds\n  type\n  formData {\n    ...TripsFormDataFragment\n    __typename\n  }\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsFormDataFragment on TripsFormData {\n  __typename\n  ...TripsCreateTripFromMovedItemFragment\n  ...TripsInviteFragment\n  ...TripsSendItineraryEmailFragment\n  ...TripsUpdateTripFragment\n  ...TripsCreateTripFromItemFragment\n}\n\nfragment TripsCreateTripFromMovedItemFragment on TripsCreateTripFromMovedItem {\n  __typename\n  item {\n    filter\n    tripEntity\n    tripItemId\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsInviteFragment on TripsInvite {\n  __typename\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsSendItineraryEmailFragment on TripsSendItineraryEmail {\n  __typename\n  item {\n    tripViewId\n    tripItemId\n    filter\n    __typename\n  }\n}\n\nfragment TripsUpdateTripFragment on TripsUpdateTrip {\n  __typename\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsCreateTripFromItemFragment on TripsCreateTripFromItem {\n  __typename\n  input {\n    itemId\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    pageLocation\n    __typename\n  }\n}\n\nfragment ToolbarFragment on TripsToolbar {\n  __typename\n  primary\n  secondaries\n  accessibility {\n    label\n    __typename\n  }\n  actions {\n    primary {\n      ...TripsTertiaryButtonFragment\n      __typename\n    }\n    secondaries {\n      ...TripsTertiaryButtonFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsCarouselContainerFragment on TripsCarouselContainer {\n  __typename\n  heading\n  subheading {\n    ...TripsCarouselSubHeaderFragment\n    __typename\n  }\n  elements {\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsSlimCardFragment\n    __typename\n  }\n  accessibility {\n    ... on TripsCarouselAccessibilityData {\n      nextButton\n      prevButton\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsContentCardFragment on TripsContentCard {\n  __typename\n  primary\n  secondaries\n  rows {\n    __typename\n    ...ContentColumnsFragment\n    ...TripsViewContentListFragment\n    ...EmblemsInlineContentFragment\n  }\n}\n\nfragment ContentColumnsFragment on TripsContentColumns {\n  __typename\n  primary\n  columns {\n    __typename\n    ...TripsViewContentListFragment\n  }\n}\n\nfragment TripsViewContentListFragment on TripsContentList {\n  __typename\n  primary\n  secondaries\n  listTheme: theme\n  items {\n    ...TripsViewContentLineItemFragment\n    __typename\n  }\n}\n\nfragment TripsViewContentLineItemFragment on TripsViewContentLineItem {\n  __typename\n  items {\n    __typename\n    ...TripsViewContentItemFragment\n  }\n}\n\nfragment TripsViewContentItemFragment on TripsViewContentItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    __typename\n  }\n}\n\nfragment EmblemsInlineContentFragment on TripsEmblemsInlineContent {\n  __typename\n  primary\n  secondaries\n  emblems {\n    ...TripsEmblemFragment\n    __typename\n  }\n}\n\nfragment TripsEmblemFragment on TripsEmblem {\n  ...BadgeFragment\n  ...EGDSMarkFragment\n  ...EGDSStandardBadgeFragment\n  ...EGDSLoyaltyBadgeFragment\n  ...EGDSProgramBadgeFragment\n  __typename\n}\n\nfragment BadgeFragment on TripsBadge {\n  accessibility\n  text\n  tripsBadgeTheme: theme\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  __typename\n}\n\nfragment UIGraphicFragment on UIGraphic {\n  ...EGDSIconFragment\n  ...EGDSMarkFragment\n  ...EGDSIllustrationFragment\n  __typename\n}\n\nfragment EGDSIconFragment on Icon {\n  description\n  id\n  size\n  theme\n  title\n  withBackground\n  __typename\n}\n\nfragment EGDSMarkFragment on Mark {\n  description\n  id\n  markSize: size\n  url {\n    ... on HttpURI {\n      __typename\n      relativePath\n      value\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSIllustrationFragment on Illustration {\n  id\n  description\n  link: url\n  __typename\n}\n\nfragment EGDSStandardBadgeFragment on EGDSStandardBadge {\n  accessibility\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  text\n  size\n  theme\n  __typename\n}\n\nfragment EGDSLoyaltyBadgeFragment on EGDSLoyaltyBadge {\n  accessibility\n  text\n  size\n  theme\n  __typename\n}\n\nfragment EGDSProgramBadgeFragment on EGDSProgramBadge {\n  accessibility\n  text\n  theme\n  __typename\n}\n\nfragment TripsFullBleedImageCardFragment on TripsFullBleedImageCard {\n  primary\n  secondaries\n  background {\n    url\n    description\n    __typename\n  }\n  badgeList {\n    ...EGDSBadgeFragment\n    __typename\n  }\n  icons {\n    id\n    description\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSBadgeFragment on EGDSBadge {\n  ...EGDSStandardBadgeFragment\n  ...EGDSLoyaltyBadgeFragment\n  ...EGDSProgramBadgeFragment\n  __typename\n}\n\nfragment TripsImageTopCardFragment on TripsImageTopCard {\n  primary\n  secondaries\n  background {\n    url\n    description\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...MapDirectionsActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSlimCardFragment on TripsSlimCard {\n  graphic {\n    ...EGDSIconFragment\n    ...EGDSMarkFragment\n    ...EGDSIllustrationFragment\n    __typename\n  }\n  primary\n  secondaries\n  subTexts {\n    ...TripsTextFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsTextFragment on TripsText {\n  ...EGDSGraphicTextFragment\n  ...EGDSPlainTextFragment\n  __typename\n}\n\nfragment EGDSGraphicTextFragment on EGDSGraphicText {\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  text\n  __typename\n}\n\nfragment EGDSPlainTextFragment on EGDSPlainText {\n  text\n  __typename\n}\n\nfragment NavigateToManageBookingActionFragment on TripsNavigateToManageBookingAction {\n  __typename\n  item {\n    __typename\n    tripItemId\n    tripViewId\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  url\n}\n\nfragment TripsCarouselSubHeaderFragment on TripsCarouselSubHeader {\n  ...EGDSGraphicTextFragment\n  ...EGDSPlainTextFragment\n  __typename\n}\n\nfragment TripsSectionContainerFragment on TripsSectionContainer {\n  ...TripsInternalSectionContainerFragment\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsMediaGalleryFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsFittedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsMapCardFragment\n    ...TripsImageSlimCardFragment\n    ...TripsSlimCardFragment\n    ...TripsMapCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsFlightMapCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsPricePresentationFragment\n    ...TripsSubSectionContainerFragment\n    ...TripsListFlexContainerFragment\n    ...TripsServiceRequestsButtonPrimerFragment\n    ...TripsSlimCardContainerFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFittedImageCardFragment on TripsFittedImageCard {\n  primary\n  secondaries\n  img: image {\n    url\n    description\n    aspectRatio\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  imageType\n  __typename\n}\n\nfragment TripsMapCardFragment on TripsMapCard {\n  primary\n  secondaries\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  image {\n    url\n    description\n    __typename\n  }\n  action {\n    ...MapActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment MapActionFragment on TripsMapAction {\n  __typename\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsImageSlimCardFragment on TripsImageSlimCard {\n  ...TripsInternalImageSlimCardFragment\n  signal {\n    type\n    reference\n    __typename\n  }\n  cardIcon {\n    ...TripsIconFragment\n    __typename\n  }\n  primaryAction {\n    ...TripsLinkActionFragment\n    ...TripsMoveItemToTripActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsSaveItemToTripActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    __typename\n  }\n  itemPricePrimer {\n    ... on TripsSavedItemPricePrimer {\n      ...TripsSavedItemPricePrimerFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInternalImageSlimCardFragment on TripsImageSlimCard {\n  primary\n  secondaries\n  badgeList {\n    ...EGDSBadgeFragment\n    __typename\n  }\n  thumbnail {\n    aspectRatio\n    description\n    url\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    hint\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsIconFragment on TripsIcon {\n  action {\n    ...TripsOpenMenuActionFragment\n    ...TripsSaveItemToTripActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    title\n    theme\n    __typename\n  }\n  label\n  __typename\n}\n\nfragment TripsSaveItemToTripActionFragment on TripsSaveItemToTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  saveItemInput {\n    itemId\n    source\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n  tripId\n}\n\nfragment TripsSavedItemPricePrimerFragment on TripsSavedItemPricePrimer {\n  tripItem {\n    ...TripItemFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripItemFragment on TripItem {\n  filter\n  tripItemId\n  tripViewId\n  __typename\n}\n\nfragment TripsMoveItemToTripActionFragment on TripsMoveItemToTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  data {\n    item {\n      filter\n      tripEntity\n      tripItemId\n      tripViewId\n      __typename\n    }\n    toTripId\n    toTripName\n    __typename\n  }\n}\n\nfragment TripsIllustrationCardFragment on TripsIllustrationCard {\n  primary\n  secondaries\n  illustration {\n    url\n    description\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsPrimaryButtonFragment on TripsPrimaryButton {\n  ...TripsInternalPrimaryButtonFragment\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsSendItineraryEmailActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsUpdateTripActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsDeleteTripActionFragment\n    ...TripsInviteAcceptActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsAcceptInviteAndNavigateToOverviewActionFragment\n    ...TripsCreateTripFromItemActionFragment\n    __typename\n  }\n  width\n  __typename\n}\n\nfragment TripsInternalPrimaryButtonFragment on TripsPrimaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n}\n\nfragment TripsSendItineraryEmailActionFragment on TripsSendItineraryEmailAction {\n  __typename\n  inputIds\n  item {\n    __typename\n    tripViewId\n    tripItemId\n    filter\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsUpdateTripActionFragment on TripsUpdateTripAction {\n  __typename\n  inputIds\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsInviteAcceptActionFragment on TripsInviteAcceptAction {\n  __typename\n  inviteId\n  analytics {\n    __typename\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsAcceptInviteAndNavigateToOverviewActionFragment on TripsAcceptInviteAndNavigateToOverviewAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    tripViewId\n    filter\n    inviteId\n    __typename\n  }\n  overviewUrl\n}\n\nfragment TripsCreateTripFromItemActionFragment on TripsCreateTripFromItemAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  saveItemInput {\n    itemId\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n  inputIds\n}\n\nfragment TripsSecondaryButtonFragment on TripsSecondaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    __typename\n  }\n}\n\nfragment TripsFlightMapCardFragment on TripsFlightPathMapCard {\n  primary\n  secondaries\n  image {\n    url\n    description\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsPricePresentationFragment on TripsPricePresentation {\n  __typename\n  pricePresentation {\n    __typename\n    ...PricePresentationFragment\n  }\n}\n\nfragment PricePresentationFragment on PricePresentation {\n  title {\n    primary\n    __typename\n  }\n  sections {\n    ...PricePresentationSectionFragment\n    __typename\n  }\n  footer {\n    header\n    messages {\n      ...PriceLineElementFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationSectionFragment on PricePresentationSection {\n  header {\n    name {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    enrichedValue {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    __typename\n  }\n  subSections {\n    ...PricePresentationSubSectionFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationSubSectionFragment on PricePresentationSubSection {\n  header {\n    name {\n      primaryMessage {\n        __typename\n        ... on PriceLineText {\n          primary\n          __typename\n        }\n        ... on PriceLineHeading {\n          primary\n          __typename\n        }\n      }\n      __typename\n    }\n    enrichedValue {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    __typename\n  }\n  items {\n    ...PricePresentationLineItemFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationLineItemFragment on PricePresentationLineItem {\n  enrichedValue {\n    ...PricePresentationLineItemEntryFragment\n    __typename\n  }\n  name {\n    ...PricePresentationLineItemEntryFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationLineItemEntryFragment on PricePresentationLineItemEntry {\n  primaryMessage {\n    ...PriceLineElementFragment\n    __typename\n  }\n  secondaryMessages {\n    ...PriceLineElementFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PriceLineElementFragment on PricePresentationLineItemMessage {\n  __typename\n  ...PriceLineTextFragment\n  ...PriceLineHeadingFragment\n  ...PriceLineBadgeFragment\n  ...InlinePriceLineTextFragment\n}\n\nfragment PriceLineTextFragment on PriceLineText {\n  __typename\n  theme\n  primary\n  weight\n  additionalInfo {\n    ...AdditionalInformationPopoverFragment\n    __typename\n  }\n  additionalInformation {\n    ...PricePresentationAdditionalInformationFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n}\n\nfragment PricePresentationAdditionalInformationFragment on PricePresentationAdditionalInformation {\n  ...PricePresentationAdditionalInformationDialogFragment\n  ...PricePresentationAdditionalInformationPopoverFragment\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFragment on PricePresentationAdditionalInformationDialog {\n  closeAnalytics {\n    linkName\n    referrerId\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  footer {\n    ...PricePresentationAdditionalInformationDialogFooterFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  openAnalytics {\n    linkName\n    referrerId\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFooterFragment on EGDSDialogFooter {\n  ... on EGDSInlineDialogFooter {\n    buttons {\n      ...PricePresentationAdditionalInformationDialogFooterButtonsFragment\n      __typename\n    }\n    __typename\n  }\n  ... on EGDSStackedDialogFooter {\n    buttons {\n      ...PricePresentationAdditionalInformationDialogFooterButtonsFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFooterButtonsFragment on EGDSButton {\n  accessibility\n  disabled\n  primary\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationPopoverFragment on PricePresentationAdditionalInformationPopover {\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverFragment on AdditionalInformationPopover {\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverSectionFragment on AdditionalInformationPopoverSection {\n  __typename\n  ... on AdditionalInformationPopoverTextSection {\n    ...AdditionalInformationPopoverTextSectionFragment\n    __typename\n  }\n  ... on AdditionalInformationPopoverListSection {\n    ...AdditionalInformationPopoverListSectionFragment\n    __typename\n  }\n  ... on AdditionalInformationPopoverGridSection {\n    ...AdditionalInformationPopoverGridSectionFragment\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverTextSectionFragment on AdditionalInformationPopoverTextSection {\n  __typename\n  text {\n    text\n    ...EGDSStandardLinkFragment\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverListSectionFragment on AdditionalInformationPopoverListSection {\n  __typename\n  content {\n    __typename\n    items {\n      text\n      __typename\n    }\n  }\n}\n\nfragment AdditionalInformationPopoverGridSectionFragment on AdditionalInformationPopoverGridSection {\n  __typename\n  subSections {\n    header {\n      name {\n        primaryMessage {\n          ...AdditionalInformationPopoverGridLineItemMessageFragment\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    items {\n      name {\n        ...AdditionalInformationPopoverGridLineItemEntryFragment\n        __typename\n      }\n      enrichedValue {\n        ...AdditionalInformationPopoverGridLineItemEntryFragment\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverGridLineItemEntryFragment on PricePresentationLineItemEntry {\n  primaryMessage {\n    ...AdditionalInformationPopoverGridLineItemMessageFragment\n    __typename\n  }\n  secondaryMessages {\n    ...AdditionalInformationPopoverGridLineItemMessageFragment\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverGridLineItemMessageFragment on PricePresentationLineItemMessage {\n  ... on PriceLineText {\n    __typename\n    primary\n  }\n  ... on PriceLineHeading {\n    __typename\n    tag\n    size\n    primary\n  }\n  __typename\n}\n\nfragment PriceLineHeadingFragment on PriceLineHeading {\n  __typename\n  primary\n  tag\n  size\n  additionalInfo {\n    ...AdditionalInformationPopoverFragment\n    __typename\n  }\n  additionalInformation {\n    ...PricePresentationAdditionalInformationFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n}\n\nfragment PriceLineBadgeFragment on PriceLineBadge {\n  __typename\n  badge {\n    accessibility\n    text\n    theme\n    __typename\n  }\n}\n\nfragment InlinePriceLineTextFragment on InlinePriceLineText {\n  __typename\n  inlineItems {\n    ...PriceLineTextFragment\n    __typename\n  }\n}\n\nfragment EGDSStandardLinkFragment on EGDSStandardLink {\n  action {\n    ...ActionFragment\n    __typename\n  }\n  standardLinkIcon: icon {\n    ...EGDSIconFragment\n    __typename\n  }\n  iconPosition\n  size\n  text\n  __typename\n}\n\nfragment ActionFragment on UILinkAction {\n  accessibility\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  resource {\n    value\n    __typename\n  }\n  target\n  __typename\n}\n\nfragment TripsSubSectionContainerFragment on TripsSectionContainer {\n  __typename\n  heading\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsFittedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsMapCardFragment\n    ...TripsSlimCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    __typename\n  }\n}\n\nfragment TripsInternalSectionContainerFragment on TripsSectionContainer {\n  __typename\n  heading\n  subheadings\n  tripsListSubTexts: subTexts\n  theme\n}\n\nfragment TripsSlimCardContainerFragment on TripsSlimCardContainer {\n  heading\n  subHeaders {\n    ...TripsTextFragment\n    __typename\n  }\n  slimCards {\n    ...TripsSlimCardFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListFlexContainerFragment on TripsFlexContainer {\n  ...TripsInternalFlexContainerFragment\n  elements {\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsAvatarGroupFragment\n    ...TripsEmbeddedContentCardFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInternalFlexContainerFragment on TripsFlexContainer {\n  __typename\n  alignItems\n  direction\n  justifyContent\n  wrap\n  elements {\n    ...TripsInternalFlexContainerItemFragment\n    __typename\n  }\n}\n\nfragment TripsInternalFlexContainerItemFragment on TripsFlexContainerItem {\n  grow\n  __typename\n}\n\nfragment TripsAvatarGroupFragment on TripsAvatarGroup {\n  avatars {\n    ...TripsAvatarFragment\n    __typename\n  }\n  avatarSize\n  showBorder\n  action {\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsAvatarFragment on TripsAvatar {\n  name\n  url\n  __typename\n}\n\nfragment TripsServiceRequestsButtonPrimerFragment on TripsServiceRequestsButtonPrimer {\n  buttonStyle\n  itineraryNumber\n  lineOfBusiness\n  orderLineId\n  __typename\n}\n\nfragment TripsMediaGalleryFragment on TripsMediaGallery {\n  __typename\n  accessibilityHeadingText\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  nextButtonText\n  previousButtonText\n  mediaGalleryId: egdsElementId\n  media {\n    ...TripsMediaFragment\n    __typename\n  }\n  mediaGalleryDialogToolbar {\n    ...TripsMediaGalleryDialogFragment\n    __typename\n  }\n}\n\nfragment TripsMediaGalleryDialogFragment on TripsToolbar {\n  primary\n  secondaries\n  actions {\n    primary {\n      icon {\n        description\n        id\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMediaFragment on TripsMedia {\n  media {\n    ... on Image {\n      url\n      description\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFormContainerFragment on TripsFormContainer {\n  __typename\n  formTheme\n  elements {\n    ...TripsPrimaryButtonFragment\n    ...TripsContentCardFragment\n    ...TripsValidatedInputFragment\n    __typename\n  }\n}\n\nfragment TripsValidatedInputFragment on TripsValidatedInput {\n  egdsElementId\n  instructions\n  label\n  placeholder\n  required\n  value\n  inputType\n  leftIcon {\n    __typename\n    leftIconId: id\n    title\n    description\n  }\n  rightIcon {\n    __typename\n    rightIconId: id\n    title\n    description\n  }\n  validations {\n    ...EGDSMaxLengthInputValidationFragment\n    ...EGDSMinLengthInputValidationFragment\n    ...EGDSRegexInputValidationFragment\n    ...EGDSRequiredInputValidationFragment\n    ...EGDSTravelersInputValidationFragment\n    ...MultiEmailValidationFragment\n    ...SingleEmailValidationFragment\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSMaxLengthInputValidationFragment on EGDSMaxLengthInputValidation {\n  __typename\n  errorMessage\n  maxLength\n}\n\nfragment EGDSMinLengthInputValidationFragment on EGDSMinLengthInputValidation {\n  __typename\n  errorMessage\n  minLength\n}\n\nfragment EGDSRegexInputValidationFragment on EGDSRegexInputValidation {\n  __typename\n  errorMessage\n  pattern\n}\n\nfragment EGDSRequiredInputValidationFragment on EGDSRequiredInputValidation {\n  __typename\n  errorMessage\n}\n\nfragment EGDSTravelersInputValidationFragment on EGDSTravelersInputValidation {\n  __typename\n  errorMessage\n}\n\nfragment MultiEmailValidationFragment on MultiEmailValidation {\n  __typename\n  errorMessage\n}\n\nfragment SingleEmailValidationFragment on SingleEmailValidation {\n  __typename\n  errorMessage\n}\n\nfragment TripsPageBreakFragment on TripsPageBreak {\n  __typename\n  _empty\n}\n\nfragment TripsContainerDividerFragment on TripsContainerDivider {\n  divider\n  __typename\n}\n\nfragment TripsLodgingUpgradesPrimerFragment on TripsLodgingUpgradesPrimer {\n  itineraryNumber\n  __typename\n}\n\nfragment TripItemContextualCardsPrimerFragment on TripItemContextualCardsPrimer {\n  tripViewId\n  tripItemId\n  placeHolder {\n    url\n    description\n    __typename\n  }\n  __typename\n}\n\nfragment TripsCustomerNotificationsFragment on TripsCustomerNotificationQueryParameters {\n  funnelLocation\n  notificationLocation\n  optionalContext {\n    itineraryNumber\n    journeyCriterias {\n      dateRange {\n        start {\n          day\n          month\n          year\n          __typename\n        }\n        end {\n          day\n          month\n          year\n          __typename\n        }\n        __typename\n      }\n      destination {\n        airportTLA\n        propertyId\n        regionId\n        __typename\n      }\n      origin {\n        airportTLA\n        propertyId\n        regionId\n        __typename\n      }\n      tripScheduleChangeStatus\n      __typename\n    }\n    tripId\n    tripItemId\n    __typename\n  }\n  xPageID\n  __typename\n}\n\nfragment TripsToastFragment on TripsToast {\n  ...TripsInfoToastFragment\n  ...TripsInlineActionToastFragment\n  ...TripsStackedActionToastFragment\n  __typename\n}\n\nfragment TripsInfoToastFragment on TripsInfoToast {\n  primary\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInlineActionToastFragment on TripsInlineActionToast {\n  primary\n  button {\n    ...TripsToastButtonFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsToastButtonFragment on TripsButton {\n  __typename\n  primary\n  action {\n    ...TripsNavigateToViewActionFragment\n    ...TripsDismissActionFragment\n    __typename\n  }\n}\n\nfragment TripsDismissActionFragment on TripsDismissAction {\n  __typename\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsStackedActionToastFragment on TripsStackedActionToast {\n  primary\n  button {\n    ...TripsToastButtonFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFloatingActionButtonFragment on TripsFloatingActionButton {\n  __typename\n  action {\n    ...TripsVirtualAgentInitActionFragment\n    __typename\n  }\n}\n\nfragment TripsDynamicMapFragment on TripsView {\n  egTripsMap {\n    ...DynamicMapFragment\n    __typename\n  }\n  egTripsCards {\n    ...TripsDynamicMapCardContentFragment\n    __typename\n  }\n  __typename\n}\n\nfragment DynamicMapFragment on EGDSBasicMap {\n  label\n  initialViewport\n  center {\n    latitude\n    longitude\n    __typename\n  }\n  zoom\n  bounds {\n    northeast {\n      latitude\n      longitude\n      __typename\n    }\n    southwest {\n      latitude\n      longitude\n      __typename\n    }\n    __typename\n  }\n  computedBoundsOptions {\n    coordinates {\n      latitude\n      longitude\n      __typename\n    }\n    gaiaId\n    lowerQuantile\n    upperQuantile\n    marginMultiplier\n    minMargin\n    minimumPins\n    interpolationRatio\n    __typename\n  }\n  config {\n    ... on EGDSDynamicMapConfig {\n      accessToken\n      egdsMapProvider\n      externalConfigEndpoint {\n        value\n        __typename\n      }\n      mapId\n      __typename\n    }\n    __typename\n  }\n  markers {\n    ... on EGDSMapFeature {\n      id\n      description\n      markerPosition {\n        latitude\n        longitude\n        __typename\n      }\n      type\n      markerStatus\n      qualifiers\n      text\n      clientSideAnalytics {\n        linkName\n        referrerId\n        __typename\n      }\n      onSelectAccessibilityMessage\n      onEnterAccessibilityMessage\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsDynamicMapCardContentFragment on EGDSImageCard {\n  id\n  description\n  image {\n    aspectRatio\n    description\n    thumbnailClickAnalytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    url\n    __typename\n  }\n  title\n  __typename\n}\n"}]';

        // Check auth
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.travelocity.com/graphql", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        foreach ($response[0]->data->trips->searchBooking->elements as $elements) {
            foreach ($elements->elements as $element) {
                if (isset($element->action->viewType) && $element->action->viewType == 'OVERVIEW') {
                    $viewUrl = $element->action->viewUrl;

                    break 2;
                }
            }
        }

        if (!isset($viewUrl)) {
            return null;
        }
        $this->http->GetURL($viewUrl);
        $providerHost = "https://www.travelocity.com";
        $this->delay();
        $its = $this->http->FindNodes('//div[@role="main"]//a[contains(@href, "/trips/egti-") and contains(.,"View booking")]/@href');
        $expedia = $this->getExpedia();

        foreach ($its as $it) {
            $this->http->GetURL($it);

            if ($expedia->ParseItineraryDetectType($providerHost, $arFields) === false) {
                //$this->delay();
                $this->http->GetURL($it);
                $this->increaseTimeLimit();
                $expedia->ParseItineraryDetectType($providerHost, $arFields);
            }
        }

        $this->logger->debug("Parsed data: " . var_export($this->itinerariesMaster->toArray(), true));

        return null;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        if (
            !strstr($this->http->currentUrl(), '://www.travelocity.com/user/signin')
            && !strstr($this->http->currentUrl(), '://www.travelocity.com/login')
        ) {
            return true;
        }

        return false;
    }

    private function delay()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->verify()) {
            $delay = rand(1, 10);
            $this->logger->debug("Delay -> {$delay}");
            sleep($delay);

            return true;
        }

        return false;
    }

    private function verify()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] != 429 || !$this->http->ParseForm("verifyButton")) {
            return false;
        }
        $currentUrl = $this->http->currentUrl();
        // captcha
        $key = $this->http->FindSingleNode("//form[@id = 'verifyButton']//div[@class = 'g-recaptcha']/@data-sitekey");
        $captcha = $this->parseCaptcha($key);

        if ($captcha === false) {
            return false;
        }
        $this->http->GetURL("https://www.travelocity.com/botOrNot/verify?g-recaptcha-response={$captcha}&destination={$this->http->Form['destination']}");

        if ($this->http->Response['code'] == 302) {
            $this->http->GetURL($currentUrl);
        }

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'It looks like we have an issue with the site.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our service is temporarily down and it appears we’ve been delayed for take off.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our service is temporarily down and it appears we’ve been delayed for take off.')]")) {
            throw new CheckException("We’re Sorry! Our service is temporarily down and it appears we’ve been delayed for take off.", ACCOUNT_PROVIDER_ERROR);
        }
        // 502 Bad Gateway
        if ($notwork = $this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindPreg("/recaptchaSiteKey = \"([^\"]+)/");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode('//iframe[contains(@src, "-api.arkoselabs.com")]/@src', null, true, "/pkey=([^&]+)/")
            ?? $this->http->FindPreg('/pkey=([^&\\\]+)/')
        ;

        if (!$key) {
            return false;
        }

        if ($this->attempt == 2) {
            $postData = array_merge(
                [
                    "type"                     => "FunCaptchaTaskProxyless",
                    "websiteURL"               => $this->http->currentUrl(),
                    "funcaptchaApiJSSubdomain" => 'expedia-api.arkoselabs.com',
                    "websitePublicKey"         => $key,
                ],
                []
//            $this->getCaptchaProxy()
            );
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($recognizer, $postData, $retry);
        }

        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    private function SecondLogin($formUrl)
    {
        $this->http->FormURL = $formUrl;
        $this->http->SetInputValue("signin-loginid", $this->AccountFields["Login"]);
        $this->http->SetInputValue("signin-password", $this->AccountFields["Pass"]);
        $res = $this->http->PostForm();

        if (!$res) {
            $this->logger->error('Failed to send second login form');

            return false;
        }

        if ($msg = $this->http->FindSingleNode("//div[@id = 'wrong-credentials-error-div']")) {
            $this->logger->error($msg);

            return false;
        }

        return true;
    }

    /** @return TAccountCheckerExpedia */
    private function getExpedia()
    {
        if (!isset($this->expedia)) {
            $this->expedia = new TAccountCheckerExpedia();
            $this->expedia->AccountFields = $this->AccountFields;
            $this->expedia->http = $this->http;
            $this->expedia->itinerariesMaster = $this->itinerariesMaster;
        }

        return $this->expedia;
    }
}
