<?php

use AwardWallet\Common\Parser\Util\PriceHelper;

class TAccountCheckerPiu extends TAccountChecker
{
    use PriceTools;
    private const REWARDS_PAGE_URL = 'https://biglietti.italotreno.it/Customer_Account_Dashboard.aspx?Culture=en-US';

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.italotreno.it/en/';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://biglietti.italotreno.it/Customer_Account_Login.aspx?Culture=en-US');

        if (!$this->http->ParseForm('SkySales')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('RestylingOverrideCustomerAccountLoginViewRestylingOverrideCustomerAccountLoginView$TextBoxUserID', $this->AccountFields['Login']);
        $this->http->SetInputValue('RestylingOverrideCustomerAccountLoginViewRestylingOverrideCustomerAccountLoginView$PasswordFieldPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('RestylingOverrideCustomerAccountLoginViewRestylingOverrideCustomerAccountLoginView$ButtonLogIn', 'Log in');
        $this->http->SetInputValue('RestylingOverrideCustomerAccountLoginViewRestylingOverrideCustomerAccountLoginView$CheckBoxRemainLogged', 'on');
        $this->http->SetInputValue('__VIEWSTATE', $this->http->Form['viewState'] ?? $this->http->Form['__VIEWSTATE'] ?? "");
        $this->http->SetInputValue('__EVENTTARGET', $this->http->Form['eventTarget'] ?? $this->http->Form['__EVENTTARGET'] ?? "");
        $this->http->SetInputValue('__EVENTARGUMENT', $this->http->Form['eventArgument'] ?? $this->http->Form['__EVENTARGUMENT'] ?? "");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode('//span[contains(text(), "You\'re trying to access to a protected")]')) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[@class="inBaloonErr"]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Login failed, please try again')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Account locked. Please call Italo Assistenza that is available every day')
                || strstr($message, 'Your account has been blocked for security reasons, since you have exceeded login attempts limit.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[contains(text(), "Hi ")]', null, true, "/Hi (.+)/")));
        // Customer code
        $this->SetProperty('MembershipNumber', $this->http->FindSingleNode('//div[contains(text(), "Customer code")]/following-sibling::div'));
        // Points to Next Level
        $this->SetProperty('PointsToNextLevel', $this->http->FindSingleNode('//h4[contains(@class, "_inl-completedText")]/text()[1]'));

        $this->http->GetURL("https://biglietti.italotreno.it/Customer_Account_Loyalty_Livelli.aspx");
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode('//div[@id = "pageContainer"]', null, true, "/([\d\,\.]+)falsefalse$/"));
        // Member Type
        $this->SetProperty('MemberType', $this->http->FindSingleNode('//div[@data-loyalty]/@data-loyalty'));

        // ITALO PIÙ POINTS
        $this->SetProperty('MorePoints', $this->http->FindSingleNode('(//span[@class="ada-punti"])[1]', null, false, self::BALANCE_REGEXP));
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://biglietti.italotreno.it/Customer_Account_MieiAcquisti_MieiViaggi.aspx');

        if ($this->http->FindSingleNode('//div[contains(text(), "There is no journey planned yet!")]')/* && !$this->ParsePastIts*/) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }
        $this->sendNotification('upcoming it // MI');

        $nodes = $this->http->XPath->query("//div[@class='ada-sheet']");
        $viewState = $this->http->FindSingleNode("//input[contains(@id,'viewState')]/@value");

        $dataForms = [];

        foreach ($nodes as $node) {
            $href = $this->http->FindSingleNode(".//a[contains(text(),'VIEW JOURNEY')]/@href", $node);
            // __doPostBack('CustomerAccountDashb...','View:U9FQTC')
            $data = [];
            $data['__EVENTTARGET'] = 'CustomerAccountDashboardMasterCustomerAccountMieiAcquistiMieiViaggiNewView$ControlGroupCustomerAccountMieiAcquistiMieiViaggiNewView$NtvExtendedBookingListCustomerAccountMieiAcquistiMieiViaggiNewView';
            $data['__EVENTARGUMENT'] = "Edit:" . $this->http->FindSingleNode('.//h3[contains(@class,"ada-ticket-code")]', $node);
            $data['__VIEWSTATE'] = $viewState;
            $data['pageToken'] = '';
            $data['CustomerAccountDashboardMasterCustomerAccountMieiAcquistiMieiViaggiNewView$ControlGroupCustomerAccountMieiAcquistiMieiViaggiNewView$NtvExtendedBookingListCustomerAccountMieiAcquistiMieiViaggiNewView$TextBoxDomecPassword'] = '';
            $dataForms[] = $data;
        }

        foreach ($dataForms as $data) {
            $this->http->PostURL('https://biglietti.italotreno.com/Customer_Account_MieiAcquisti_MieiViaggi.aspx', $data);
            $dataLayers = $this->http->JsonLog($this->http->FindPreg('/dataLayer = (\[.+?\]);/s'));

            if (count($dataLayers) > 1) {
                $this->sendNotification('dataLayers > 1 // MI');
            }
            $this->parseItinerary('', $dataLayers[0]);
        }

        /*if ($this->ParsePastIts) {
            $dataForms = [];
            $this->http->GetURL('https://biglietti.italotreno.it/Customer_Account_MieiAcquisti_MieiViaggiStorico.aspx?Culture=en-US');

            $nodes = $this->http->XPath->query("//div[@class='ada-sheet']");
            $this->logger->debug('Found ' . count($nodes) . ' its');
            $viewState = $this->http->FindSingleNode("//input[contains(@id,'viewState')]/@value");

            foreach ($nodes as $node) {
                $href = $this->http->FindSingleNode(".//a[contains(text(),'VIEW JOURNEY')]/@href", $node);
                // __doPostBack('CustomerAccountDashb...','View:U9FQTC')
                $data = [];
                $data['__EVENTTARGET'] = $this->http->FindPreg("/__doPostBack\('(.+?)',/", false, $href);
                $data['__EVENTARGUMENT'] = $this->http->FindPreg("/__doPostBack\('.+?','(View:.+?)'/", false, $href);
                $data['__VIEWSTATE'] = $viewState;
                $data['pageToken'] = '';
                $data['CustomerAccountDashboardMasterCustomerAccountMieiAcquistiMieiViaggiStoricoNewView$ControlGroupCustomerAccountMieiAcquistiMieiViaggiStoricoNewView$NtvExtendedBookingListCustomerAccountMieiAcquistiMieiViaggiStoricoNewView$TextBoxDomecPassword'] = '';
                $dataForms[] = $data;
            }

            foreach ($dataForms as $data) {
                $this->http->PostURL('https://biglietti.italotreno.it/Customer_Account_MieiAcquisti_MieiViaggiStorico.aspx', $data);
                $dataLayers = $this->http->JsonLog($this->http->FindPreg('/dataLayer = (\[.+?\]);/s'));

                foreach ($dataLayers as $dataLayer) {
                    $this->parseItinerary('Past ', $dataLayer, $data);
                }
            }
        }*/

        return [];
    }

    private function parseItinerary($type, $dataLayer)
    {
        $result = [];
        $this->logger->info("{$type}Parse itinerary #{$dataLayer->codicePNR}", ['Header' => 3]);
        $t = $this->itinerariesMaster->add()->train();
        $t->general()->confirmation($dataLayer->codicePNR);
        $t->price()->total(PriceHelper::cost($dataLayer->totale));
        $t->price()->currency($this->http->FindPreg('/"symbol":".+?","displayCode":"([A-Z]{3})"/'));

        $trains = $this->http->XPath->query("//div[@class='payment-wrapper']//div[@class='table-container']");
        $this->logger->debug("Found {$trains->length} itinerary");

        /* $http2 = clone $this->http;
         $this->http->brotherBrowser($http2);
         $http2->RetryCount = 0;
         $http2->GetURL('https://biglietti.italotreno.it/Booking_Acquisto_RiepilogoStampa.aspx');
         $http2->RetryCount = 2;*/

        $travellers = [];
        //$t->general()->traveller($this->http->FindSingleNode('.//h4[contains(text(),"Passenger Details")]/following-sibling::p[@class="destinazione"]', $train));

        foreach ($trains as $train) {
            $s = $t->addSegment();
            $destinations = $this->http->FindSingleNode('.//h4[contains(text(),"itinerary")]/following-sibling::p[@class="destinazione"]', $train);
            $destination = preg_split('/\s+-\s+/', $destinations);

            if (count($destination) == 2) {
                $s->departure()->name($destination[0])
                    ->geoTip('eu');
                $s->arrival()->name($destination[1])
                    ->geoTip('eu');
            }

            $date = $this->http->FindSingleNode(".//h3[contains(text(),'Date Departures')]/following-sibling::p[@class='date']", $train);
            $this->logger->debug("Date: " . $date);
            $times = $this->http->FindSingleNode(".//h3[contains(text(),'Departures / arrivals timetable')]/following-sibling::p[@class='date']", $train);
            $time = preg_split('/>\s+/', $times);

            if (count($time) == 2) {
                $s->departure()
                    ->date(strtotime("$date $time[0]"));
                $s->arrival()
                    ->date(strtotime("$date $time[1]"));
            }

            $s->extra()
                ->number($dataLayer->numeroTrenoAndata)
                ->cabin($this->http->FindSingleNode('.//h4[contains(text(),"Offer Type:")]/following-sibling::p[@class="destinazione"]', $train));

            $seats = $this->http->FindNodes('.//p[contains(text(),"Coach")]', $train);

            // Coach  1  | Seat  25
            // Coach  1  | Seat  12
            foreach ($seats as $seat) {
                $s->extra()
                    ->car($this->http->FindPreg('/Coach\s*(\d+)/', false, $seat))
                    ->seat($this->http->FindPreg('/Seat\s*(\d+)/', false, $seat));
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($t->toArray(), true), ['pre' => true]);

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//*[@onclick="submitLogout()"]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
