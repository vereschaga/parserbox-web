<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerTrenitalia extends TAccountChecker
{
    use PriceTools;
    use ProxyList;

    private $currentItin = 0;
    private $pnrSet = [];
    private $pnrSegSet = [];
    private $retrievePNR;

    private $travelIds = [];

    private $headers = [
        "Accept"           => "application/json, application/pdf, text/calendar",
        "Accept-Encoding"  => "gzip, deflate, br",
        "Content-Type"     => "application/json",
        "X-Requested-With" => "Fetch",
        "accept-language"  => "en-GB",
    ];

    private $cardNumber;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (strstr($this->AccountFields['Login'], '<img onerror=alert(1)')) {
            throw new CheckException('Invalid credentials', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.lefrecce.it/B2CWeb/search.do?parameter=searchAdvInputViewer");

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'auth/handoff?action=login')]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("j_username", $this->AccountFields['Login']);
        $this->http->SetInputValue("j_password", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            // AccountID: 2393805
            if ($this->http->Response['code'] == 302 && $this->AccountFields['Login'] == 136380498) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        $data = [
            "userName" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.lefrecce.it/PicoAuth/api/auth/login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->access_token)) {
            $this->State['Authorization'] = "{$response->token_type} {$response->access_token}";

            return $this->loginSuccessful();
        }

        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == "CREDENZIALI NON VALIDE per l'utente {$this->AccountFields['Login']}") {
                throw new CheckException('Nome Utente o password non corretti.', ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "PASSWORD SCADUTA per l'utente ")) {
                throw new CheckException("La tua password è scaduta", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        } elseif ($this->http->Response['code'] == 500/* && $this->AccountFields['Login'] == 'paranoide'*/) {
            throw new CheckException('Nome Utente o password non corretti.', ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty('Name', beautifulName($response->customer->firstName . " " . $response->customer->lastName));
        // Status
        $status = $this->http->FindPreg('/CARTAFRECCIA_(\w+)/', false, $response->customer->card->loyaltyProfile->name ?? null);
        $this->SetProperty('Status', beautifulName($status));


        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.lefrecce.it/Channels.Website.BFF.WEB/website/loyalty/balance/userArea", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        // Please complete your profile by entering the missing details and get 200 points
        if (
            isset($response->message)
            && in_array($response->message, [
                'label.loyaltyreward.message.incomplete.data',
                "label.loyaltyreward.message.not.enabled.card",
            ])
            && $this->http->Response['code'] == 400
        ) {
            $this->SetProperty('Account', $this->cardNumber ?? null);

            $this->SetBalanceNA();

            return;
        }

        if (isset($response->loyaltyBalanceCFUserArea->available->amount)) {
            // Balance - Your account balance is of ___ available points.
            $this->SetBalance($response->loyaltyBalanceCFUserArea->available->amount);
            // Account number - Staff code
            $this->SetProperty('Account', $response->loyaltyBalanceCFUserArea->loyaltyCode ?? null);
            // Qualifying points
            $this->SetProperty('QualifyingPoints', $response->loyaltyBalanceCFUserArea->qualifying->amount ?? null);
        } elseif ($this->http->FindPreg('/,"loyaltyBalanceCFUserArea":null/')) {
            $this->SetBalanceNA();
        }

        if (isset($response->loyaltyBalanceLRUserArea->available->amount, $response->loyaltyBalanceLRUserArea->loyaltyProgramView->name)) {
            $cardsXgo = [
                'Code' => 'trenitalia' . str_replace('-', '',
                        $response->loyaltyBalanceLRUserArea->loyaltyProgramView->name),
                'DisplayName' => "X-Go Point Balance",
                'Balance'     => $response->loyaltyBalanceLRUserArea->available->amount,
                'Account'      => $response->loyaltyBalanceLRUserArea->loyaltyCode ?? null,
                'TerminationDate' => date("d/m/Y", strtotime($response->loyaltyBalanceLRUserArea->loyaltyProgramView->endDate))
            ];
            $this->AddSubAccount($cardsXgo, true);
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.lefrecce.it/Channels.Website.BFF.WEB/website/loyalty/balance", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'description');
        if ($response->nextCartaFrecciaMaxPoint ?? null) {
            // Points to next level
            $this->SetProperty('PointsNextLvl', $response->nextCartaFrecciaMaxPoint ?? null);
            // Previous Year Balance
            $this->SetProperty('PreviousYearBalance', $response->previousYearQualifyingPoints ?? null);
            // Program Termination Date
            $this->SetProperty('TerminationDate', date("d/m/Y", strtotime($response->loyaltyProgramView->endDate ?? null)));
            // Status
//        $this->SetProperty('Status', str_replace(date("Y"), '', $response->loyaltyProgramView->name));
        }

    }

    public function ParseItineraries()
    {
        // {"travelGroup":"TICKET","searchType":"DEPARTURE_DATE","fromDate":"30/06/2021","toDate":"01/07/2032","code":"","limit":10,"offset":0}
        $data = [
            'travelGroup' => 'TICKET',
            'searchType'  => 'DEPARTURE_DATE',
            'fromDate'    => date('d/m/Y', strtotime('-1 year')),
            'toDate'      => date('d/m/Y', strtotime('+10 year')),
            'code'        => '',
            'limit'       => 10,
            'offset'      => 0,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.lefrecce.it/Channels.Website.BFF.WEB/website/travel/solutions",
            json_encode($data), $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('/^\{"solutions":\[\],"favourites":\[\]\}$/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        foreach ($response->solutions as $item) {
            if (strtotime($item->arrivalDate) < strtotime('now') && !$this->ParsePastIts) {
                $this->logger->notice('Past parking, skip it');

                continue;
            }

            $param = [
                'resourceId'    => $item->resourceId,
                'silentWarning' => 'false',
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.lefrecce.it/Channels.Website.BFF.WEB/website/travel/reopen?" . http_build_query($param),
                $this->headers);
            $this->http->RetryCount = 2;
            $data = $this->http->JsonLog();
            $this->parseItinerary($item, $data);
        }

        return [];
    }

    public function ConfirmationNumberURL($arFields)
    {
//        return 'http://www.trenitalia.com/tcom-en/Purchase/Manage-your-ticket';
        return 'https://www.trenitalia.com/en/purchase/manage_your_ticket.html';
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation code (PNR)",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
            "Email" => [
                "Type"     => "string",
                "Caption"  => "Email",
                "Size"     => 40,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
        ];
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->FindSingleNode('(//form[contains(@action, "Channels.Website.WEB/website/auth/handoff?action=searchEmailPnr")])[2]')) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }
        $this->http->RetryCount = 0;
        $headers = [
            'Accept'           => 'application/json, application/pdf, text/calendar',
            'Content-Type'     => 'application/json',
            'x-requested-with' => 'Fetch',
        ];
        $this->http->PostURL('https://www.lefrecce.it/Channels.Website.BFF.WEB/website/whitelist/enabled',
            json_encode([]), $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->token)) {
            return null;
        }

        $headers['x-csrf-token'] = $response->token;
        $data = [
            'recoverType' => 'PNR_EMAIL',
            'pnr'         => $arFields['ConfNo'],
            'email'       => $arFields['Email'],
        ];
        $this->http->PostURL('https://www.lefrecce.it/Channels.Website.BFF.WEB/website/travel/recover',
            json_encode($data), $headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if (isset($response->message)) {
            return $response->message;
        }

        if (!empty($response->solutions)) {
            $this->parseItineraryRetrieve($response);
        }

        return null;
    }

    private function parseItinerary($item, $data): bool
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("[{$this->currentItin}] Parse Itinerary ({$data->purchaseDate})", ['Header' => 3]);
        $this->currentItin++;

        $r = $this->itinerariesMaster->add()->train();
        $r->general()->noConfirmation();
        $r->general()->date(strtotime($this->http->FindPreg('/^(.+?)T/', false, $data->purchaseDate)));
        $tickets = $travellers = [];

        foreach ($data->solutions as $solution) {
            foreach ($solution->solutionContainer->nodeSummaries as $nodeSumm) {
                $s = $r->addSegment();

                foreach ($nodeSumm->offerContainerSummaryViews as $offerContainer) {
                    foreach ($offerContainer->offerSummaryViews as $offer) {
                        if (isset($offer->traveller->firstName)) {
                            $travellers[] = beautifulName($offer->traveller->firstName . " " . $offer->traveller->lastName);
                        }
                        $tickets[] = $offer->entitlementId;
                        $s->extra()->cabin($offer->serviceName);
                    }
                }
                $s->setNumber($nodeSumm->nodeView->train->name);
                $s->departure()
                    ->name($nodeSumm->nodeView->origin)
                    ->date(strtotime($nodeSumm->nodeView->departureTime));
                $s->arrival()
                    ->name($nodeSumm->nodeView->destination)
                    ->date(strtotime($nodeSumm->nodeView->arrivalTime));
            }
        }

        if (!empty($travellers)) {
            $r->general()->travellers(array_unique($travellers));
        }

        if (!empty($tickets)) {
            $r->setTicketNumbers(array_unique($tickets), false);
        }

        if (!empty($data->solutions)) {
            $r->price()->total($data->solutions[0]->solutionContainer->solutionSummary->totalPrice->amount);
            $r->price()->currency($this->totalCurrency($data->solutions[0]->solutionContainer->solutionSummary->totalPrice->currency));
        }

        $this->logger->debug('Parsed Train:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return true;
    }

    private function totalCurrency($node)
    {
        return str_replace(['€', '£', '₹'], ['EUR', 'GBP', 'INR'], $node);
    }

    private function parseItineraryRetrieve(object $data)
    {
        $this->logger->notice(__METHOD__);
        $t = $this->itinerariesMaster->createTrain();
        //$t->general()->noConfirmation();

        $pnrs = $travellers = $accounts = [];

        foreach ($data->solutions as $solution) {
            $segments = [];
            $segments[] = $solution->solutionContainer;

            if (isset($solution->solutionContainer->returnSolutionContainer)) {
                $segments[] = $solution->solutionContainer->returnSolutionContainer;
            }

            foreach ($segments as $segment) {
                $s = $t->addSegment();
                $s->departure()->name($segment->solutionSummary->origin);
                $s->departure()->date2(str_replace('T', ', ',
                    $this->http->FindPreg('/^(.+?T\d+:\d+)/', false, $segment->solutionSummary->departureTime)));

                $s->arrival()->name($segment->solutionSummary->destination);
                $s->arrival()->date2(str_replace('T', ', ',
                    $this->http->FindPreg('/^(.+?T\d+:\d+)/', false, $segment->solutionSummary->arrivalTime)));

                foreach ($segment->nodeSummaries as $sum) {
                    if (isset($sum->pnr)) {
                        $pnrs[] = $sum->pnr;
                    }
                    $s->extra()->type($sum->nodeView->train->trainCategory);
                    $s->extra()->number($sum->nodeView->train->name);

                    foreach ($sum->offerContainerSummaryViews as $container) {
                        foreach ($container->offerSummaryViews as $view) {
                            if (isset($view->cpCode)) {
                                $t->general()->confirmation($view->cpCode, 'CP');
                            }

                            if (isset($view->traveller->firstName, $view->traveller->lastName)) {
                                $travellers[] = beautifulName("{$view->traveller->firstName} {$view->traveller->lastName}");
                            }

                            if (!empty($view->traveller->loyaltyCode)) {
                                $accounts[] = $view->traveller->loyaltyCode;
                            }

                            foreach ($view->seatInfo as $seat) {
                                $s->extra()->seat($seat->seat);
                                $s->extra()->car($seat->wagon);
                            }
                        }
                    }
                }
            }

            $t->price()->currency($solution->solutionContainer->totalPrice->currency);
            $t->price()->total($solution->solutionContainer->totalPrice->amount);
        }

        foreach (array_unique(array_filter($pnrs)) as $val) {
            $t->general()->confirmation($val, 'PNR');
        }

        foreach (array_unique(array_filter($travellers)) as $val) {
            $t->general()->traveller($val);
        }

        foreach (array_unique(array_filter($accounts)) as $val) {
            $t->program()->account($val, false);
        }

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($t->toArray(), true), ['pre' => true]);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/Gentile\s*Cliente\,\s*siamo\s*spiacenti\s*ma,\s*per\s*un\s*malfunzionamento\s*temporaneo,\s*non\s*\&egrave;\s*possibile\s*accedere\s*al\s*sistema\./")) {
            throw new CheckException($message . " Si prega di provare più tardi.", ACCOUNT_PROVIDER_ERROR);
        }
        /**
         * Gentile Cliente, siamo spiacenti ma non è possibile accedere al sistema per manutenzione programmata.
         * Si prega di provare più tardi.
         */
        if ($message = $this->http->FindSingleNode('(//div[contains(., "siamo spiacenti ma non è possibile accedere al sistema per manutenzione programmata.")])[1]')) {
            throw new CheckException($message . " Si prega di provare più tardi.", ACCOUNT_PROVIDER_ERROR);
        }
        // Gentile Cliente, siamo spiacenti ma non è possibile accedere al sistema a causa di un traffico troppo elevato. Si prega di provare più tardi.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "siamo spiacenti ma non è possibile accedere al sistema a causa di un traffico troppo elevato.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.lefrecce.it/Channels.Website.BFF.WEB/website/user/profile",
            '{"userName":"' . $this->AccountFields['Login'] . '","password":null}',
            $this->headers + ["Authorization" => $this->State['Authorization']]);
        $response = $this->http->JsonLog(null, 3, false, 'pointsLimit');
        $this->http->RetryCount = 2;

        $userName = $response->user->userName ?? null;
        $this->logger->debug("[userName]: {$userName}");
        $email = $response->user->email ?? null;
        $this->logger->debug("[email]: {$email}");
        $this->cardNumber = $response->customer->card->code ?? null;
        $this->logger->debug("[cardNumber]: {$this->cardNumber}");

        if (in_array(strtolower($this->AccountFields['Login']),
            [strtolower($userName), strtolower($email), $this->cardNumber])) {
            $this->http->setDefaultHeader("Authorization", $this->State['Authorization']);

            return true;
        }

        return false;
    }
}
