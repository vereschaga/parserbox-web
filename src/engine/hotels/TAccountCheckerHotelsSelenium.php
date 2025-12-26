<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
require_once __DIR__ . '/../expedia/TAccountCheckerExpediaSelenium.php';

class TAccountCheckerHotelsSelenium extends TAccountCheckerExpediaSelenium
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    protected $hotels;
    public string $provider = 'hotels';

    private const REWARDS_PAGE_URL_V1 = 'https://www.hotels.com/account/hotelscomrewards.html';
    private const REWARDS_PAGE_URL_V2 = 'https://www.hotels.com/account/rewards';

    public function InitBrowser()
    {
        try {
            TAccountChecker::InitBrowser();
            $this->KeepState = true;
            $this->UseSelenium();
            $this->http->saveScreenshots = true;

            if ($this->attempt == 0) {
                if ($this->AccountFields['Login2'] == 'OTHER') {
                    $this->setProxyGoProxies(null, 'de');
                } else {
                    $this->setProxyGoProxies(null, 'uk');
                }
            } elseif ($this->attempt == 1) {
                $this->setProxyGoProxies();
            } elseif ($this->attempt == 2) {
                $this->setProxyNetNut();
            }

            // refs #23984
            $this->setHost($this->AccountFields['Login2']);


            if ($this->attempt == 0) {
                $this->useChromePuppeteer();
            } else {
                $this->useFirefoxPlaywright();
            }

            $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
//        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->seleniumOptions->userAgent = null;
            $this->seleniumOptions->recordRequests = true;

            $this->http->setHttp2(true);
        } catch (ThrottledException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            throw new CheckRetryNeededException(3, 0);
        }
    }

    /*public function LoadLoginForm()
    {
        $this->http->removeCookies();
        return parent::LoadLoginForm();
    }*/

    protected function getHotels()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->hotels)) {
            $this->hotels = new TAccountCheckerHotels();
            $this->hotels->http = new HttpBrowser("none", new CurlDriver());
            //$this->hotels->http->setProxyParams($this->http->getProxyParams());
            $this->hotels->http->SetProxy($this->proxyUK());
            $this->http->brotherBrowser($this->hotels->http);
            $this->hotels->http->setUserAgent($this->http->userAgent);
            $this->hotels->AccountFields = $this->AccountFields;
            $this->hotels->itinerariesMaster = $this->itinerariesMaster;
            $this->hotels->HistoryStartDate = $this->HistoryStartDate;
            $this->hotels->historyStartDates = $this->historyStartDates;
            $this->hotels->http->LogHeaders = $this->http->LogHeaders;
            $this->hotels->ParseIts = $this->ParseIts;
            $this->hotels->ParsePastIts = $this->ParsePastIts;
            $this->hotels->WantHistory = $this->WantHistory;
            $this->hotels->WantFiles = $this->WantFiles;
            $this->hotels->strictHistoryStartDate = $this->strictHistoryStartDate;
            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->hotels->http->setDefaultHeader($header, $value);
            }

            $this->hotels->globalLogger = $this->globalLogger;
            $this->hotels->logger = $this->logger;
            $this->hotels->onTimeLimitIncreased = $this->onTimeLimitIncreased;
            $this->hotels->host = $this->host;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->hotels->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->hotels;
    }

    /*public function Parse()
    {
        $hotels = $this->getHotels();
        $hotels->Parse();
        $this->SetBalance($hotels->Balance);
        $this->Properties = $hotels->Properties;
        $this->ErrorCode = $hotels->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $hotels->ErrorMessage;
            $this->DebugInfo = $hotels->DebugInfo;
        }
    }*/
    public function parseWithCurl($currentUrl = null)
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->browser)) {
            $this->browser = new HttpBrowser("none", new CurlDriver());
            $this->browser->setProxyParams($this->http->getProxyParams());
            $this->http->brotherBrowser($this->browser);

            $this->browser->setUserAgent($this->http->userAgent);
            $cookies = $this->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                /*if ($this->AccountFields['Login2'] != 'OTHER') {
                    if ($cookie['name'] == 'currency') {
                        $cookie['value'] = 'USD';
                    }
                    if ($cookie['name'] == 'CRQS') {
                        $cookie['value'] = 't|3001`s|300000001`l|en_US`c|USD';
                    }
                    if ($cookie['name'] == 'CRQSS') {
                        $cookie['value'] = 'e|1';
                    }
                } else {
                    if ($cookie['name'] == 'currency') {
                        $cookie['value'] = 'EUR';
                    }
                    if ($cookie['name'] == 'CRQS') {
                        $cookie['value'] = 't|3102`s|300000752`l|de_DE`c|EUR';
                    }
                    if ($cookie['name'] == 'CRQSS') {
                        $cookie['value'] = 'e|752';
                    }
                }*/

                $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            if ($currentUrl) {
                $this->browser->RetryCount = 0;
                $this->browser->GetURL($currentUrl);
                $this->browser->RetryCount = 2;
            }
        }
    }


    public function Parse()
    {
        if ($this->AccountFields['Login2'] != 'OTHER') {
            $this->http->GetURL("https://hotels.com/?locale=en_US&pos=HCOM_US&siteid=300000001");
        } else {
            $this->http->GetURL("https://de.hotels.com?locale=en_DE&pos=HCOM_DE&siteid=300000752");
        }
        sleep(5);
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $this->saveResponse();
        /*$oldDesign = false;
        if ($this->http->FindSingleNode("//a[contains(@href,'/account/hotelscomrewards.html')]")) {
            $oldDesign = true;
        }*/
        $duaid = $this->http->FindPreg('#\\\\"duaid\\\\":\\\\"([\w\-]+)\\\\",\\\\"#');
        $tpid = $this->http->FindPreg('#\\\\"tpid\\\\":(\d+),\\\\"#');
        $expUserId = $this->http->FindPreg('#\\\\"expUserId\\\\":(\d+),\\\\"#');
        $clientInfo = $this->http->FindPreg('#\\\\"clientInfo\\\\":\\\\"([.,\-\w]+)\\\\"#');
        $pageId = $this->http->FindPreg('#\\\\"pageId\\\\":\\\\"([.,\-\w]+)\\\\"#');
        $viewId = $this->http->FindPreg('#\\\\"viewId\\\\":\\\\"([.,\-\w]+)\\\\"#');

        $this->parseWithCurl();
        $headers = [
            'Accept' => '*/*',
            'Content-Type' => 'application/json',
            'client-info' => $clientInfo,
        ];
        if ($this->AccountFields['Login2'] != 'OTHER') {
            $siteId = '300000001' ?? $this->http->FindPreg('#\\\\"siteId\\\\":(\d+),#');
            $locale = 'en_US' ?? $this->http->FindPreg('#\\\\"locale\\\\":\\\\"([.,\-\w]+)\\\\"#');
            $eapid = '1' ?? $this->http->FindPreg('#\\\\"eapid\\\\":(\d+),#');
            $currency = 'USD' ?? $this->http->FindPreg('#\\\\"currency\\\\":\\\\"([A-Z]+)\\\\"#');
            $host = 'https://www.hotels.com';

            $data = '[{"operationName":"MemberWalletQuery","variables":{"context":{"siteId":' . $siteId . ',"locale":"' . $locale . '","eapid":' . $eapid . ',"tpid":' . $tpid . ',"currency":"' . $currency . '","device":{"type":"DESKTOP"},"identity":{"duaid":"' . $duaid . '","authState":"AUTHENTICATED"},"privacyTrackingState":"CAN_TRACK","debugContext":{"abacusOverrides":[]}}},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"b2208829d173c3a467dd4add915c268dd2fdfa94c77bc2c028ebccd7beb7c541"}}}]';
            /*$this->browser->RetryCount = 0;
            $this->browser->PostURL("$host/graphql", $data, $headers);
            $this->browser->RetryCount = 2;
            $response = $this->browser->JsonLog();*/
        } else {
            $siteId = '300000752' ?? $this->http->FindPreg('#\\\\"siteId\\\\":(\d+),#');
            $locale = 'de_DE' ?? $this->http->FindPreg('#\\\\"locale\\\\":\\\\"([.,\-\w]+)\\\\"#');
            $eapid = '752' ?? $this->http->FindPreg('#\\\\"eapid\\\\":(\d+),#');
            $currency = 'EUR' ?? $this->http->FindPreg('#\\\\"currency\\\\":\\\\"([A-Z]+)\\\\"#');
            $host = 'https://de.hotels.com';
            $data = '[{"operationName":"MemberWalletQuery","variables":{"context":{"siteId":' . $siteId . ',"locale":"' . $locale . '","eapid":' . $eapid . ',"currency":"' . $currency . '","device":{"type":"DESKTOP"},"identity":{"duaid":"' . $duaid . '","authState":"AUTHENTICATED"},"privacyTrackingState":"CAN_NOT_TRACK","debugContext":{"abacusOverrides":[]}}},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"b2208829d173c3a467dd4add915c268dd2fdfa94c77bc2c028ebccd7beb7c541"}}}]';

            /*if ($menuBtn = $this->waitForElement(WebDriverBy::xpath('//button[@data-testid="header-menu-button"]/div/div/div'), 0)) {
                $menuBtn->click();
                $this->saveResponse();
                sleep(3);
            }
            $requests = $this->http->driver->browserCommunicator->getRecordedRequests();
            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));

                if (stripos($xhr->request->getUri(), '/graphql') !== false ) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    if (stripos(json_encode($xhr->response->getBody()), '"operationName":"MemberWalletQuery",') !== false) {
                        $response = json_encode($xhr->response->getBody());
                    }
                }
            }*/

        }
        $response = $this->sendApi("$host/graphql", $clientInfo, $data);
        $response = $this->browser->JsonLog($response);
        if (empty($response)) {
            $this->logger->error('Something went wrong');
            return;
        }

        // One Key
        if (isset($response[0]->data->memberWallet->oneKeyUserEnabled) && $response[0]->data->memberWallet->oneKeyUserEnabled === true) {
            $headers['x-page-id'] = 'page.User.Rewards';
            $data = '[{"operationName":"LoyaltyAccountSummary","variables":{"context":{"siteId":' . $siteId . ',"locale":"' . $locale . '","eapid":' . $eapid . ',"currency":"' . $currency . '","device":{"type":"DESKTOP"},"identity":{"duaid":"' . $duaid . '","authState":"AUTHENTICATED"},"privacyTrackingState":"CAN_TRACK","debugContext":{"abacusOverrides":[]}},"viewId":null,"strategy":"SHOW_TRAVELER_INFO_AND_REWARDS_LINK"},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"3b6ab5528680bc469361f69a2e20589fc69343bd5d62addc700c5f0a4365e4a8"}}}]';
//            $this->browser->RetryCount = 0;
//            $this->browser->PostURL("$host/graphql", $data, $headers);
//            $this->browser->RetryCount = 2;
             $this->sendApi("$host/graphql", $clientInfo, $data);

            // Balance - OneKeyCash TM
            $this->SetBalance($this->browser->FindPreg('/"rewardsAmount":"(.+?)","/'));
            // Currency
            $this->SetProperty('Currency', $this->browser->getCookieByName('currency'));
            // Name
            $this->SetProperty('Name', $this->browser->FindPreg('/"LoyaltyAccountTraveler","title":"Hi, (.+?)",/'));
            // Status
            $this->SetProperty('Status', $this->browser->FindPreg('/"theme":"global-lowtier","size":"large","text":"([\w\s]+)"},/'));

            $data = '[{"operationName":"LoyaltyTierProgressionQuery","variables":{"context":{"siteId":' . $siteId . ',"locale":"' . $locale . '","eapid":' . $eapid . ',"tpid":' . $tpid . ',"currency":"' . $currency . '","device":{"type":"DESKTOP"},"identity":{"duaid":"'.$duaid.'","authState":"AUTHENTICATED"},"privacyTrackingState":"CAN_TRACK","debugContext":{"abacusOverrides":[]}}},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"8e05e7ada985950ec80a4616cd616709d255e6589efb33fac6077ee289fcd852"}}}]';
            //$this->browser->PostURL("$host/graphql", $data, $headers);
            $this->sendApi("$host/graphql", $clientInfo, $data);

            // Trips collected to next status - "0 of 5 trip elements collected to reach Silver"
            $tripToNextStatus = $this->browser->FindPreg('#"(\d+) of \d+ trip elements collected to reach \w+"#');
            $this->SetProperty('TripToNextStatus', $tripToNextStatus);
            // Trip elements reset date - "Your trip elements reset to 0 on December 31, 2023."
            $tripResetDate = $this->browser->FindPreg('#"Your trip elements reset to \d+ on (\w+ \d+, \d{4})\."#');
            $this->SetProperty('TripResetDate', $tripResetDate);

        }
        elseif (isset($response[0]->data->memberWallet->oneKeyUserEnabled) && $response[0]->data->memberWallet->oneKeyUserEnabled === false) {
            foreach ($response[0]->data->memberWallet->details->items as $item) {
                if (in_array($item->label, ['Collected stamps', 'Gesammelte Stempel', 'Selos juntados'])) {
                    // Selos juntados
                    $this->SetBalance($item->text);
                } elseif (in_array($item->label, ['Reward nights', 'Prämiennächte', 'Noites de recompensa'])) {
                    // Noites de recompensa
                    $this->SetProperty('RewardsNights', $item->text);
                }
            }
            foreach ($response[0]->data->memberWallet->info->items as $item) {
                if (isset($item->theme) && $item->theme == 'LOYALTY_HIGH_TIER') {
                    switch ($item->text) {
                        case "Associado Gold":
                        case "Gold Member":
                            $this->SetProperty('Status', 'Gold');

                            break;
                        default:
                            $this->sendNotification("new status: {$item->text} // MI");
                    }
                }
            }
            $this->ParseV1();
        }
    }

    public function ParseV1()
    {
        $this->http->FilterHTML = false;
        $this->logger->notice(__METHOD__);
        $this->http->GetURL(self::REWARDS_PAGE_URL_V1);
        $this->waitForElement(WebDriverBy::xpath('//span[contains(text()," selos juntados")]/em | //span[@class = "membership-name"]'), 10);
        $this->saveResponse();
        //$this->SetProperty('Name', $this->http->FindSingleNode("//div[@class='banner-content']/p"));
        // 11 selos juntados
        $this->SetProperty('StampsCollected',
            $this->http->FindSingleNode("//span[contains(text(),' selos juntados')]/em"));
        // Junte mais 19 selos até 12 jan 2024 para manter o seu status Gold no próximo ano.
        $this->SetProperty('StampsToMaintainCurrentTier',
            $this->http->FindSingleNode("//div[@class='progress-message' and contains(.,'Junte mais') and contains(.,'selos até')]/strong[1]",
                null, false, '/^(\d+)$/'));

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'membership-name']")));

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@class = 'name']")));
        }

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id='item-member']/div[@title]/@title")));
        }

        // Membership number
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(text(), 'Membership Number')]", null, true, "/Number:\s*([^<]+)/ims"));

        if (!isset($this->Properties['Number'])) {
            $this->SetProperty("Number", $this->http->FindSingleNode("//span[@id = 'membership-number-value']"));
        }

        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode("//span[@class = 'membership-tier']", null, true, '/Hotels.com(?:®|™)\s*Rewards\s*([^<]+)/ims'));

        if (!isset($this->Properties['Status'])) {
            if ($status =
                $this->http->FindSingleNode("//div[@class = 'banner-content']//img[@alt = 'Hotels.com® Rewards']/@src")
                ?? $this->http->FindSingleNode("//div[@class = 'loyalty-lockup']/img[@alt = 'Hotels.com® Rewards']/@src")
            ) {
                $status = basename($status);
                $this->logger->debug(">>> Status " . $status);

                switch ($status) {
                    case strstr($status, 'rewards-logo-white-no-moon-en_'):
                    case 'rewards-logo-purple-moon-en_US.png':
                        $this->SetProperty("Status", "Member");

                        break;

                    case strstr($status, 'rewards-logo-white-silver-no-moon-en_'):
                    case 'rewards-logo-silver-moon-en_US.png':
                        $this->SetProperty("Status", "Silver");

                        break;

                    case strstr($status, 'rewards-logo-white-gold-no-moon-en_'):
                    case 'rewards-logo-gold-moon-en_US.png':
                        $this->SetProperty("Status", "Gold");

                        break;

                    default:
                        if (!empty($status) && $this->ErrorCode === ACCOUNT_CHECKED) {
                            $this->sendNotification("Unknown status: $status");
                        }
                }// switch ($status)
            }// if ($status = $this->browser->FindSingleNode("//img[@alt = 'Hotels.com® Rewards']/@src"))
        }// if (empty($this->Properties['Status']))

        // Nights needed to next level
        $this->SetProperty('NightsNeededToNextLevel',
            $this->http->FindPreg("/Stay (\d+) more (?:night|stamp)s? by\s*(?:\d\d\/\d\d\/\d\d|[\w\d\,\s]+)?\s*to reach Hotels.com® Rewards/ims")
            ?? $this->http->FindPreg("/Collect\s*<strong>(\d+)<\/strong>\s*(?:more\s*|)(?:night|stamp)s?\s*by\s*<strong>[^<]+<\/strong>\s*to\s*become\s*a/ims")
        );
        // Nights needed to maintain level
        $this->SetProperty('NightsNeededToMaintainLevel', $this->http->FindPreg("/Stay (\d+) more (?:night|stamp)s? by\s*(?:\d\d\/\d\d\/\d\d|[\w\d\,\s]+)?\s*to maintain your Hotels.com® Rewards/ims"));
        // nights collected (Nights during current membership year)
        $this->SetProperty("NightsDuringCurrentMembershipYear",
            $this->http->FindPreg("/You have stayed (\d+) nights? during your current membership year/ims")
            ?? $this->http->FindSingleNode("//div[@class = 'collected-night-count']/span[contains(text(), 'collected')]/em")
        );

        // Balance - You’ve collected ... nights / stamps towards your free night. Keep going!
        if (!$this->SetBalance($this->http->FindPreg("/<span class=\"squircle\"><\/span>(\d+) (?:night|stamp)s? collected</"))) {
            // Start collecting towards your next free night\
            if (
                $this->http->FindPreg("/(?:Start collecting towards your next free (?:night|stamp)\.|(?:Night|Stamp)s will appear in your account up to 72 hours after you check out\.)/ims")
                || $this->http->FindSingleNode('//div[contains(@class, "aside")]//div[contains(@class, "punchcard-container")]//p[@class = "explanation"]', null, true, "/Collect 10 (?:more\s*|\s*)(?:night|stamp)s?(?:,|\s*to) get (?:another|1) reward\* night/")
                || $this->http->FindSingleNode("//p[contains(text(),'Collect 10 stamps, get 1 reward* night')]")
            ) {
                $this->SetBalance(0);
            } elseif (
                $this->http->FindSingleNode('//div[contains(@class, "aside")]//li[@class = "night-icon earned"]')
                && count($this->http->FindSingleNode('//div[contains(@class, "aside")]//li[@class = "night-icon earned"]')) > 0
            ) {
                $this->SetBalance(count($this->http->FindSingleNode('//div[contains(@class, "aside")]//li[@class = "night-icon earned"]')));
            } else {
                $this->notAMember();
            }
        }

        // Last activity
        $lastActivity = $this->http->FindSingleNode("//div[contains(text(), 'Most recent activity')]", null, true, '/activity\s*([a-z]{3}\s*\d+\,\s*\d{4})/ims');

        if (!$lastActivity) {
            $lastActivity = $this->http->FindSingleNode("//span[@id = 'membership-last-activity-value']");
        }
        $this->SetProperty("LastActivity", $lastActivity);
        // Expiration Date  // refs #4738
        if (isset($lastActivity) && strtotime($lastActivity)) {
            $exp = strtotime("+12 month", strtotime($lastActivity));
            $this->SetExpirationDate($exp);
        }

        if ($se = $this->http->FindPreg("/You can enjoy your membership benefits until ([^\.]+)./ims")) {
            $this->SetProperty('StatusExpiration', $se);
        }

        if ($se = $this->http->FindPreg("/membership until <strong>([^<]+)<\/strong>\.\s*Collect/ims")) {
            $this->SetProperty('StatusExpiration', $se);
        }

        $this->logger->info('Free nights', ['Header' => 3]);
        // Number of Free Night
        $freeNights =
            $this->http->FindSingleNode("//h3[contains(@class,'with-icon') and contains(., 'reward*') and contains(., 'night')]", null, false, "/(\d+)\s*reward\*\s*night/")
            ?? $this->http->FindSingleNode("//h3[contains(@class,'with-icon') and contains(., ' collected') and contains(., 'night')]", null, false, "/(\d+)\s*night/");
        // Next Free Nights
        $this->SetProperty("UntilNextFreeNight", $this->http->FindSingleNode('//div[contains(@class, "aside")]//div[contains(@class, "punchcard-container")]//p[@class = "explanation"]', null, true, "/Collect (\d+) (?:more\s*|\s*)(?:night|stamp)s?(?:,|\s*to) get (?:another|1) reward\* night/"));

        $this->logger->debug("Free nights: {$freeNights}");

        if (isset($freeNights) && $freeNights > 0) {
            $this->SetProperty("CombineSubAccounts", false);
            // SubAccounts Properties
            // Expiration Date  // refs #18483
            $expDate =
                $this->http->FindSingleNode('//div[@id = "collected-nights"]//div[contains(@class, "expiry-info")]/strong')
                ?? $this->http->FindSingleNode('//div[@id = "collected-nights"]//div[contains(@class, "expiry-info")]//strong', null, true, "/extended until\s*(.+)/")
            ;
            $expDate = strtotime($expDate);
            $freeNightList = $this->http->FindNodes('//div[contains(@class, "free-night-details")]//p[contains(@class, "price")]');
            $subAccounts = [];

            foreach ($freeNightList as $worth) {
                $subAccount = [
                    'Code'        => 'hotelsFreeNight' . md5($worth),
                    'DisplayName' => sprintf('Free Night Up To %s', $worth),
                    'Balance'     => null,
                    // Free Night Up To     // refs #14472
                    'FreeNightUpTo'  => $worth,
                    'ExpirationDate' => $expDate ?: $exp ?? false,
                ];

                // refs #21579
                if (isset($subAccounts[$subAccount['Code'] . '-' . $subAccount['ExpirationDate']])) {
                    $this->logger->debug("such subAcc already exist: +1");

                    if ($subAccounts[$subAccount['Code'] . '-' . $subAccount['ExpirationDate']]['Balance'] == null) {
                        $subAccounts[$subAccount['Code'] . '-' . $subAccount['ExpirationDate']]['Balance'] = 1;
                    }

                    ++$subAccounts[$subAccount['Code'] . '-' . $subAccount['ExpirationDate']]['Balance'];

                    continue;
                }

                $subAccounts[$subAccount['Code'] . '-' . $subAccount['ExpirationDate']] = $subAccount;
            }
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }

        // Retries
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // You are not a member of this loyalty program
            if ($this->http->FindSingleNode("//h2[contains(text(), 'We are having technical issues.')]")) {
                if ($link = $this->http->FindSingleNode("//a[contains(text(), 'Welcome Rewards®')]/@href")) {
                    $this->http->GetURL($link);
                }

                if ($this->http->FindSingleNode("//a[contains(@href, 'https://www.hotels.com/account/welcomerewards.html?enroll=true')]/@href")
                    || $this->http->FindSingleNode("(//a[contains(@href, 'https://www.hotels.com/profile/signup.html?wrEnrollment=true')]/@href)[1]")
                ) {
                    $this->SetWarning(self::NOT_MEMBER_MSG);
                }
            }// if ($this->browser->FindSingleNode("//h2[contains(text(), 'We are having technical issues.')]"))
            // AccountID: 1559035, 894961, 2611724 and other
            elseif ($this->http->Response['code'] == 500) {
                $this->logger->notice("Provider bug, try to parse properties from profile page");
                $this->http->GetURL("https://www.hotels.com/profile/summary.html");
                // Balance - You’ve collected ... nights towards your free night. Keep going!
                $this->SetBalance(count($this->http->FindNodes("//div[@class = 'card']/ul/li[@class = 'earned']")));
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'membership-name']")));
                // Account number
                $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(text(), 'Membership Number')]", null, true, "/Number:\s*([^<]+)/ims"));
                //# Status
                $this->SetProperty('Status', $this->http->FindSingleNode("//span[@class = 'membership-tier']", null, true, '/Hotels.com(?:®|™)\s*Rewards\s*([^<]+)/ims'));

                $rewardsNights = $this->http->FindSingleNode("//p[@class = 'redeem']/span");
                $subAccounts = [];

                if (isset($rewardsNights) && $rewardsNights > 0) {
                    $this->sendNotification("hotels - refs #13880. New subacc logic");
                    $this->SetProperty("CombineSubAccounts", false);
                    // SubAccounts Properties
                    $subAccounts[] = [
                        'Code'        => 'hotelsRewardsNights',
                        'DisplayName' => 'Rewards Nights',
                        'Balance'     => $rewardsNights,
                    ];
                    // Set SubAccounts Properties
                    $this->SetProperty("SubAccounts", [$subAccounts]);
                }// if (isset($rewardsNights) && $rewardsNights > 0)
            }// elseif ($this->browser->Response['code'] == 500)
            else {
                $currentUrl = $this->http->currentUrl();
                $this->logger->debug($currentUrl);

                if ($currentUrl == 'https://www.hotels.com/profile/landing.html') {
                    if ($this->http->FindPreg("/Sorry, we weren’t able to show your Hotels\.com® Rewards activities due to a technical issue/is")) {
                        $this->SetBalanceNA();
                    }
                }

                $this->http->GetURL("https://www.hotels.com/hotel-rewards-pillar/hotelscomrewards.html?intlid=ACCOUNT_SUMMARY+%3A%3A+header_main_section");
                // Your Welcome Rewards® account has been deactivated.
                if ($message = $this->http->FindPreg("/(Your (?:Welcome Rewards®|Hotels.com® Rewards) account has been deactivated\.)/ims")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->notAMember();

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    $this->http->GetURL('https://www.hotels.com/');
                    $this->SetBalance($this->http->FindSingleNode("//div[contains(text(),'Collected stamps')]/../preceding-sibling::div/div", null, false, '/^\d+$/'));
                }
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function notAMember()
    {
        $this->logger->notice(__METHOD__);
        // We are having technical issues
        if (
            $this->http->FindSingleNode('//h2[contains(text(), "Unlock Secret Prices")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "Instant savings. Reward* nights. And more")]/following-sibling::div[a[contains(text(), "Join Now")]]')
            || ($this->http->FindPreg('/By enrolling, I agree to the full <a href="http:\/\/www\.hotels\.com\/customer_care\/terms_conditions.html" rel="nofollow" class="hcomPopup" target="_blank">\s*Terms and Conditions<\/a> of the program./ims')
                && $this->http->FindSingleNode('(//p[contains(text(), "Sorry, we weren\'t able to show your Welcome Rewards")])[1]'))
            || ($this->http->FindPreg("/Our loyalty program is now called Hotels.com® Rewards\. <a href=\"\/hotel-rewards-pillar\/hotelscomrewards\.html\">Enjoy free\* nights and Secret Prices<\/a> that are so low/ims")
                && $this->http->FindSingleNode('//p[contains(text(), "Sorry, we weren’t able to show your Hotels.com® Rewards")]'))
            || $this->http->FindPreg('/(By enrolling I agree to the full \&lt;a href=.+&gt;Terms and Conditions\&lt;\/a&gt; of the program.)/ims')
            || $this->http->FindPreg('/(By enrolling, I agree to the full <a href=."\/customer_care\/terms_conditions\.html.">Terms and Conditions<\/a> of the program\.)/ims')
            || $this->http->FindPreg('/(By enrolling, I agree to the full \&lt;a href=."\/customer_care\/terms_conditions\.html."\&gt;Terms and Conditions\&lt;\/a\&gt; of the program\.)/ims')
            // AccountID: 5593514
            /*
            || $this->http->FindSingleNode("//a[contains(text(), 'Start earning today')]")
            */
        ) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }
    }

    public function ParseItineraries($providerHost = 'www.hotels.com', $ParsePastIts = false)
    {
        parent::ParseItineraries($providerHost, $ParsePastIts);

        return [];
    }

    private function sendApi($url, $clientInfo, $data)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[URL]: $url -> $data");
        try {
            $this->driver->executeScript("
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '$url', true);
                xhr.withCredentials = true;
                xhr.setRequestHeader('Accept', '*/*');
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.setRequestHeader('client-info', '$clientInfo');

                xhr.onreadystatechange = function() {
                    if (this.readyState != 4) {
                        return;
                    }
                    /*if (this.status != 200) {
                        localStorage.setItem('statusText', this.statusText);
                        localStorage.setItem('responseText', this.responseText);
                        return;
                    }*/
                    localStorage.setItem('responseText', this.responseText);
                }
                xhr.send('$data');     
            ");
            sleep(3);
            $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
            if (empty($response)) {
                sleep(3);
                $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
                if (empty($response)) {
                    sleep(3);
                    $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
                    if (empty($response)) {
                        sleep(3);
                        $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
                    }
                }
            }
            $this->driver->executeScript("localStorage.removeItem('responseText')");
            $this->logger->info("[Form response]: $response");
        } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $response = null;
        }
        catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            throw new CheckRetryNeededException(3, 0);
        }
        $this->browser->SetBody($response);
        return $response;
    }
}
