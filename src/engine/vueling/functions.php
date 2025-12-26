<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerVueling extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public const REWARDS_PAGE_URL = 'https://tickets.vueling.com/UpdateProfile.aspx?culture=en-GB';

    private $_successUrl = 'https://tickets.vueling.com/SearchPoints.aspx?culture=en-GB';

    private $currentItin = 0;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['URL'] = "http://tickets.vueling.com";
        $arg['SuccessURL'] = $this->_successUrl;

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        //$this->http->setRandomUserAgent();
        /*
        $this->http->setDefaultHeader("User-Agent", "curl/7.52.1");
        */
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setHttp2(true);

        $this->http->SetProxy($this->proxyReCaptchaIt7());
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
//        $this->http->GetURL("https://tickets.vueling.com/");
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if ($this->http->Response['code'] == 0 || $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            throw new CheckRetryNeededException(3, 5);
        }

        //	    $this->http->GetURL('http://tickets.vueling.com/?culture=en-GB');
        if ($this->http->ParseForm("formMyVueling") || $this->http->ParseForm("headerSignIn")) {
            $this->http->Inputs = [
                'passwd' => [
                    'maxlength' => 20,
                ],
            ];

            // enter the login and password
            $this->http->SetInputValue("user", $this->AccountFields['Login']);
            $this->http->SetInputValue("passwd", $this->AccountFields['Pass']);
            $this->http->SetInputValue("remember", "on");
        } else {
            if (!$this->http->ParseForm("SkySales")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('StaticHeaderViewSearchView$MemberLoginHeaderSearchView$TextBoxUserID', $this->AccountFields['Login']);
            $this->http->SetInputValue('StaticHeaderViewSearchView$MemberLoginHeaderSearchView$PasswordFieldPassword', $this->AccountFields['Pass']);
//            $this->http->SetInputValue('__EVENTTARGET', 'StaticHeaderViewSearchView$MemberLoginHeaderSearchView$ButtonLogIn');
        }

        return true;
    }

    public function Login()
    {
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        if ($sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")) {
            $this->http->NormalizeURL($sensorPostUrl);
//            $key = $this->sendSensorData($sensorPostUrl);
            $this->getSensorDataFromSelenium();
        } else {
            $this->logger->error("sensor_data URL not found");
        }

        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->RetryCount = 0;

        /*
        if (!$this->http->PostForm()) {
            // no auth, empty page
            if (in_array($this->AccountFields['Login'], [
                'canovas.pa@gmail.com',
                'eddieringleb@gmail.com',
                'hjara@live.com.ar',
                'ion.legazpi@gmail.com',
                'dpnbcn@gmail.com',
                'sanchisalfonso@gmail.com',
                'tededmunds88@gmail.com',
                'guymees@gmail.com',
                'dominiclongo@stthomas.edu',
                'bernardo.donato22@gmail.com',
                'cliffdhunt@googlemail.com',
                'ade.solarin@gmail.com',
                'sophie@littlemissnobody.com',
            ])
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (in_array($this->AccountFields['Login'], [
                '3081471046603626',
            ])
            ) {
                throw new CheckException("No recuerdo mi usuario o contraseña", ACCOUNT_INVALID_PASSWORD);
            }

            return $this->checkErrors();
        }
        */

        $this->http->RetryCount = 2;

        // check for invalid password
        $message = $this->http->FindSingleNode("//div[@id = 'validationErrorContainerReadAlongList']")
            ?? $this->http->FindSingleNode("//form[contains(@id,'SkySales')]//div[@class = 'alert__message']")
            ?? $this->http->FindSingleNode("//form[contains(@id,'SkySales')]//p[@role = 'alert']")
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");
            // Lo sentimos, en estos momentos no es posible conectarse a Vueling Club. Puedes volver a intentarlo pasados unos minutos.
            if (
                stristr($message, 'Lo sentimos, en estos momentos no es posible conectarse a Vueling Club. Puedes volver a intentarlo pasados unos minutos.')
                || stristr($message, 'Lo sentimos, en estos momentos no es posible conectarse a Vueling Club. Puedes volver a intentarlo pasados unos minutos.')
                || stristr($message, 'Lo sentimos, pero debido a un error en la conexión, en estos momentos no es posible acceder a Vueling Club.')
                || stristr($message, 'Estamos realizando un mantenimiento para ofrecerte un mejor servicio. Ahora mismo no es posible acceder a Vueling Club ni utilizar tus puntos Avios, pero aún puedes reservar y acumular puntos Avios. Sentimos los inconvenientes.')
                || $message == 'An error has occurred. Please try again later.'
                || $message == 'Se ha producido un error. Por favor, inténtalo de nuevo más tarde.'
                || $message == 'In order to log in to Vueling Club with this username, you need to identify yourself on the Vueling app first, and accept the terms and conditions of Vueling Club and Avios.'
                || $message == 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.'
                || $message == 'Произошла ошибка. Пожалуйста, повторите попытку позже.'
                || $message == 'Due to a connection error, you can\'t access your account right now but you can still complete the booking process.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'Vous devez accepter les mentions légales.') {
                $this->throwAcceptTermsMessageException();
            }

            if (
                stristr($message, 'Los datos de acceso no son correctos. Recuerda que tienes un total de 3 intentos para introducir la contraseña.')
                || stristr($message, 'Los datos de acceso no son correctos.')
                || stristr($message, 'Los datos introducidos no existen o no son correctos.')
                || stristr($message, 'The access details are not correct. Remember, you have three attempts to enter the password.')
                || stristr($message, 'I dati di accesso non sono corretti. Ricorda che hai a disposizione un massimo di 3 tentativi per inserire la password.')
                || stristr($message,
                    'The details entered do not exist or are incorrect. Remember, after three failed attempts your account will be locked for 30 minutes. If you prefer, you can reset your password.')
                || stristr($message, 'Ups, de momento no es posible iniciar sesión en Vueling con este email, pero puedes utilizar otro para registrarte.')
                || stristr($message, 'Password is not valid. The length must be')
                || $message == 'The email must include @ and dot. E.g. name@email.com'
                || $message == 'Sorry, it is not possible to log in to Vueling with this email at this time, but you can use a different one to register.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                stristr($message, 'Usuario es inválido. La longitud debe ser de al menos 1 caracter y no mayor de 50 caracteres.')
            ) {
                throw new CheckException("The details entered are incorrect", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                stristr($message, 'Tu cuenta ha sido bloqueada. Puedes intentar acceder de nuevo pasados 30 minutos')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindPreg('/document.getElementById\("imgtolog"\).style.display = "block"/')) {
            $this->http->GetURL($this->_successUrl);

            if ($sorry = $this->http->FindPreg('#;URL=(http://sorry.vueling.com)#')) {
                $this->http->GetURL($sorry);
            }
        }

        if ($this->http->FindSingleNode("//p[
                contains(normalize-space(text()), 'Our new programme, Vueling Club, also has a new currency: Avios')
                or contains(normalize-space(text()), 'Com o nosso novo programa Vueling Club, chega também uma nova moeda: Avios.')
                or contains(normalize-space(text()), 'Теперь ваши бонусные баллы Puntos стали милями Avios')
            ]")
        ) {
            $this->throwProfileUpdateMessageException();
        }

        // Para poder hacer efectivo el cambio de puntos a Avios necesitamos que, por motivos de seguridad, restablezcas tu contraseña y completes los siguientes datos.
        if ($this->http->FindSingleNode("//p[contains(text(),'Para poder hacer efectivo el cambio de puntos a Avios necesitamos que')]")
            || $this->http->FindSingleNode("//p[contains(text(),'Avant de pouvoir transformer vos points en Avios, nous avons besoin, pour des raisons de sécurité,')]")
            || $this->http->FindSingleNode("//p[contains(text(),'Perché il passaggio dei punti ad Avios sia effettivo, per motivi di sicurezza')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode("//p[normalize-space(text()) = '¡Date de alta en Vueling Club!']")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // login successful
//        if ($this->http->FindSingleNode("(//a[contains(@href, 'LogOut')]/@href)[1]"))
//        if ($this->http->getCookieByName("RememberLogin")) {
        if (
            $this->http->getCookieByName("SharedSession")
            || $this->http->getCookieByName("SharedSessionOrg")
        ) {
            return true;
        }
        // Need to change a password
        if ($this->http->FindSingleNode("//span[@id = 'linkTextPunto' and text() = 'Cambiar contraseña'] | //h3[contains(text(), 'Change of password')]")) {
            throw new CheckException("Vueling Airlines (Punto Punto) website is asking you to change your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*review*/

        // Por favor, introduce el email y/o contraseña.
        if (in_array($this->AccountFields['Login'], [
            'rick@r3builders.com',
            'cullmaan@aol.com',
            'belen.wagaw@gmail.com',
            'coffellfamily@cwgsy.net',
            'katieponomareva@gmail.com',
        ])) {
            throw new CheckException("Los datos de acceso no son correctos, revisa de nuevo tu usuario y tu contraseña. ¿Necesitas ayuda? Atención al cliente: 902 104 269", ACCOUNT_INVALID_PASSWORD);
        }

        if (in_array($this->AccountFields['Login'], [
            'samjenkins7@yahoo.co.uk',
            'cluaran@hotmail.com',
        ])
        ) {
            throw new CheckException("Ups, de momento no es posible iniciar sesión en Vueling con este email, pero puedes utilizar otro para registrarte.", ACCOUNT_INVALID_PASSWORD);
        }

        // AccountID: 4886283
        if (
            $this->http->currentUrl() == 'http://tickets.vueling.com/?culture=en-GB%5cErrorMessage.aspx%5cErrorMessage.aspx%5CErrorMessage.aspx'
            && $this->http->Response['code'] == 302
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->Response['code'] == 403
            && $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
        ) {
            throw new CheckRetryNeededException(3, 5);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        //# Full Name
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[contains(@id, 'FirstName')]/@value")
            . ' ' . $this->http->FindSingleNode("//input[contains(@id, 'LastName')]/@value"));

        if (strlen($name) > 3) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // Punto customer number
        $this->SetProperty("Number", $this->http->FindSingleNode("//strong[contains(text(), 'customer number')]/following-sibling::span"));

        $providerBug = false;

        if ($this->http->FindPreg("/<strong>Vueling Club customer number<\/strong>: <span class=\"tc_greySoft\"><\/span>/")) {
            $providerBug = true;
        }

        $this->http->GetURL("https://tickets.vueling.com/VuelingClubTransactions.aspx?culture=en-GB");
        // Balance - Your current balance is
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(),'Your current balance is')]/strong", null, false, '/(\d+)\s*Avios/'));

        // Sorry but, due to a connection error, we cannot show you your Avios balance at this time. You can try again in a few minutes.
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
            && ($message = $this->http->FindSingleNode("//p[
                    contains(text(), 'Sorry but, due to a connection error, we cannot show you your Avios balance at this time. You can try again in a few minutes.')
                    or contains(text(), 'Lo sentimos, pero debido a un error en la conexión, en estos momentos no es posible mostrar tu saldo de Avios. Puedes volver a intentarlo pasados unos minutos.')
                ]"))
        ) {
            if ($providerBug == true || $this->http->FindPreg("/\"PointsAvios\":\"\"/")) {
                $this->SetWarning($message);
            }
            // Balance - You have ... Avios
            $this->SetBalance($this->http->FindPreg('/"PointsAvios":"([^\"]+)/'));
        }

        // Expiration date  // refs #16048
        // the activity in the profile does not match the rules
        /*if ($this->Balance > 0) {
            $nodes = $this->http->XPath->query("//div[@id = 'punto_saldoArticle']//table[contains(@class, 'resultTable')]/tbody/tr");
            $this->logger->debug("Total {$nodes->length} nodes were found");

            foreach ($nodes as $node) {
                $transactionDate = $this->http->FindSingleNode("td[1]", $node);
                $description = $this->http->FindSingleNode("td[2]", $node, false, '/Combine\s+my\s+Avios/i');
                $points = $this->http->FindSingleNode("td[3]", $node);

                if (!empty($transactionDate) && $points > 0 && !$description) {
                    // Expiration Date
                    $modifyDate = $this->ModifyDateFormat($transactionDate);
                    $this->logger->debug("Expiration Date {$modifyDate} - " . strtotime($modifyDate) . " / {$points}");

                    if ($exp = strtotime($modifyDate, false)) {
                        $this->SetExpirationDate(strtotime('+36 month', $exp));
                        $this->SetProperty("LastActivity", $transactionDate);
                    }

                    break;
                }// if (!empty($transactionDate) && $points > 0)
            }// for ($i = 0; $i < $nodes->length; $i++)
        }*/// if ($this->Balance > 0)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->checkErrors();
        }
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://tickets.vueling.com/MemberBookingListAsPax.aspx');

        if ($this->http->FindSingleNode("//span[contains(text(), 'You currently have no flights pending with Vueling.')]")) {
            if ($this->ParsePastIts) {
                $pastItineraries = $this->parsePastItineraries();

                if (!empty($pastItineraries) && $this->http->FindSingleNode("//span[contains(text(), 'You currently have no used bookings.')]")) {
                    return $this->noItinerariesArr();
                }
            } else {
                return $this->noItinerariesArr();
            }
        }

        $page = 1;

        do {
            $this->logger->info("Future Itineraries Page: {$page}", ['Header' => 2]);
            $this->http->ParseForm('SkySales');

            if ($page > 1) {
                $this->http->SetInputValue('__EVENTTARGET', 'changePage');
                $this->http->SetInputValue('__EVENTARGUMENT', $page);
                $this->http->SetInputValue('MemberListAsPaxView$filterStates', 'pending');
                $this->http->PostForm();
            }
            $page++;

            $bookingNodes = $this->xpathQuery('//div[contains(@class, "sectionBorderTab")]');

            foreach ($bookingNodes as $node) {
                $this->parseItinerary($node);
            }
        } while (
            $this->http->FindNodes('//a[contains(@class, "prev") and contains(text(), "Next")]')
            && $page < 3
        );

        // $this->http->GetURL('https://tickets.vueling.com/MemberBookingListAsPax.aspx');
        if ($this->ParsePastIts) {
            $this->parsePastItineraries();
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking code",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "Email" => [
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://tickets.vueling.com/RetrieveBooking.aspx?event=change&culture=en-GB";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->LogHeaders = true;

        if ($this->attempt == 0) {
            $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, 'br');
        } elseif ($this->attempt == 1) {
            $this->setProxyGoProxies();
        }

        $this->http->setRandomUserAgent();
        $this->getSensorDataFromRetrieveSelenium();
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($error = $this->http->FindSingleNode("//h2[contains(@class,'landing-sorry')]/following-sibling::p[1][contains(.,'Estamos realizando tareas de mantenimiento en nuestra web')]")) {
            return $error;
        }

        if (!$this->http->ParseForm('SkySales')) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            if ($this->http->Response['code'] == 403) {
                throw new CheckRetryNeededException(3, 3);
            }

            return null;
        }
        $this->http->SetInputValue('ControlGroupRetrieveBookingView$BookingRetrieveInputRetrieveBookingView$CONFIRMATIONNUMBER1', $arFields['ConfNo']);
        $this->http->SetInputValue('ControlGroupRetrieveBookingView$BookingRetrieveInputRetrieveBookingView$CONTACTEMAIL1', $arFields['Email']);
        $this->http->SetInputValue('__VIEWSTATE', ArrayVal($this->http->Form, 'viewState'));
        $this->http->SetInputValue('__EVENTARGUMENT', '');
        $this->http->SetInputValue('__EVENTTARGET', 'ControlGroupRetrieveBookingView$BookingRetrieveInputRetrieveBookingView$LinkButtonRetrieve');
        unset($this->http->Form['viewState']);
        unset($this->http->Form['eventArgument']);
        unset($this->http->Form['eventTarget']);

        if (!$this->http->PostForm()) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        if ($error = $this->http->FindSingleNode('//div[@id = "validationErrorContainerReadAlongList"]')) {
            return $error;
        }

        if ($this->http->FindSingleNode('//h2[contains(text(), "The time of your flight has been changed. Here are your new flight details:")]')) {
            $this->parseChangedItinerary();
        } elseif ($this->http->FindSingleNode('//h3[contains(.,\'Booking code\')]')
            and $this->http->XPath->query('//div[contains(@class,\'flight-icon-plane\')]/ancestor::div[./preceding-sibling::div][1]')->length > 0
        ) {
            $this->parseChangedRetrieve();
        } else {
            $this->parseItineraryRetrieve();
        }

        return null;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//strong[contains(text(), 'customer number')]/following-sibling::span")
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're currently improving some of the features in our booking system to offer you a better service.
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We\'re currently improving some of the features in our booking system to offer you a better service.")]
                | //p[contains(text(), "Estamos realizando algunas mejoras en nuestro sistema de reservas para ofrecerte un mejor servicio.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/Se ha producido un problema/ims') && $this->http->FindPreg('/Por favor, vuelve a intentarlo .* tarde/ims')) {
            throw new CheckException('Vueling Airlines website had a hiccup, please try to check your balance at a later time.', ACCOUNT_PROVIDER_ERROR);
        }
        //# We are currently performing schedule maintenance on vueling.com
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'We are currently performing schedule maintenance on vueling.com')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently carrying out maintenance on our website.
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'We are currently carrying out maintenance on our website.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/We&rsquo;re currently updating the system./ims')) {
            throw new CheckException('Vueling Airlines website had a hiccup, please try to check your balance at a later time.', ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application.
        if ($this->http->FindPreg("/Server Error in '\/' Application\./ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred while processing your request.
        if ($message = $this->http->FindPreg("/An error occurred while processing your request\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 3589352, 4193145
        if (
            $this->http->FindSingleNode("//em[contains(text(), 'There was no XML start tag open.')]")
            || $this->http->Response['code'] == 500
            || $this->http->currentUrl() == 'https://tickets.vueling.com/ErrorMessage.aspx'
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($redirect = $this->http->FindPreg("/<META http-equiv=\"refresh\" content=\"0;URL=(http:\/\/sorry\.vueling\.com)\"/")) {
            $this->http->GetURL($redirect);
            // We are currently carrying out maintenance on our website.
            if ($message = $this->http->FindSingleNode('//strong[contains(text(), "We are currently carrying out maintenance on our website.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    private function xpathQuery($query, $parent = null): DOMNodeList
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent);
        $this->logger->info(sprintf('Total found %s nodes: %s', $res->length, $query));

        return $res;
    }

    private function parsePastItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Past Itineraries", ['Header' => 2]);
        $startTimer = $this->getTime();
        $pastLimit = 5;
        $bookingNodes = null;

        for ($i = 1; $i <= $pastLimit; $i++) {
            $this->logger->info("Past Itineraries Page: {$i}", ['Header' => 2]);
            $this->logger->info("Parsing page #$i of past itineraries");
            $this->http->ParseForm('SkySales');

            if ($i === 1) {
                $this->http->SetInputValue('__EVENTARGUMENT', 'flown');
                $this->http->SetInputValue('__EVENTTARGET', 'changeSubMode');
            } else {
                $nextPage = $this->http->FindNodes('//a[contains(@class, "prev") and contains(text(), "Next")]');

                if (!$nextPage) {
                    break;
                }
                $this->http->SetInputValue('__EVENTARGUMENT', "$i");
                $this->http->SetInputValue('__EVENTTARGET', 'changePage');
            }
            $this->http->SetInputValue('MemberListAsPaxView$filterStates', 'flown');
            $this->http->PostForm();
            $bookingNodes = $this->xpathQuery('//div[contains(@class, "sectionBorderTab")]');

            if (count($bookingNodes) === 0) {
                break;
            }

            foreach ($bookingNodes as $node) {
                $this->parseItinerary($node);
            }
        }

        $this->getTime($startTimer);

        return $bookingNodes;
    }

    private function parseItinerary($bookingNode)
    {
        $this->logger->notice(__METHOD__);
        $flight = $this->itinerariesMaster->createFlight();
        $conf = $this->http->FindSingleNode('.//span[contains(@class, "pnrPrice")]', $bookingNode);
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $conf), ['Header' => 3]);
        $flight->addConfirmationNumber($conf, 'Booking Code', true);

        // Total
        $totalInfo = $this->http->FindSingleNode('.//div[contains(@class, "paxPaymentTotal")]', $bookingNode);
        $totalStr = $this->http->FindPreg('/(\d[\d.,]+)/', false, $totalInfo);

        if (isset($totalStr)) {
            $total = PriceHelper::cost($totalStr);
            $flight->price()->total($total);
            // Currency
            $flight->price()->currency($this->http->FindPreg('/([A-Z]{3})/', false, $totalInfo));
        }
        // Status
        if ($status = $this->http->FindSingleNode('.//span[contains(@class, "paymentStatus")]', $bookingNode)) {
            $flight->setStatus($status);
        }
        // Passengers
        $names = $this->http->FindNodes('.//ul[contains(@class, "paxNameList")]/li/div[1]/strong[1]', $bookingNode);
        $flight->setTravellers($names);
        // TripSegments
        $segments = $this->xpathQuery('.//h6[contains(text(), "Flight:")]', $bookingNode);

        foreach ($segments as $i => $segment) {
            $seg = $flight->addSegment();
            // AirlineName
            $seg->setAirlineName($this->http->FindPreg('/Flight:\s*(\w{2})/', false, $segment->nodeValue));
            // FlightNumber
            $seg->setFlightNumber($this->http->FindPreg('/Flight:\s*\w{2}\s*(\d+)/', false, $segment->nodeValue));
            // DepCode
            $seg->setDepCode($this->http->FindSingleNode('(./following-sibling::table[1]//td[contains(@class, "detalleRuta")])[1]', $segment, true, '/\(([A-Z]{3})\)/'));
            // DepName
            $seg->setDepName($this->http->FindSingleNode('(./following-sibling::table[1]//td[contains(@class, "detalleRuta")])[1]', $segment, true, '/^(.+?)\s+\(/'));
            // DepDate
            $isOutward = $this->isOutward($segment);

            if ($isOutward) {
                $currentDate = $this->http->FindSingleNode('(.//ancestor::div[contains(@class, "contentSection")]//span[contains(@class, "tc_black")])[1]', $segment);
            } else {
                $currentDate = $this->http->FindSingleNode('(.//ancestor::div[contains(@class, "contentSection")]//span[contains(@class, "tc_black")])[2]', $segment);
            }
            $currentDate = preg_replace('/\//', '.', $currentDate);
            $currentDate = strtotime($currentDate);
            $time1 = $this->http->FindSingleNode('(./following-sibling::table[1]//td[contains(@class, "detalleHoraSinAncho")])[1]', $segment);
            $seg->setDepDate(strtotime($time1, $currentDate));
            // ArrCode
            $seg->setArrCode($this->http->FindSingleNode('(./following-sibling::table[1]//td[contains(@class, "detalleRuta")])[2]', $segment, true, '/\(([A-Z]{3})\)/'));
            // ArrName
            $seg->setArrName($this->http->FindSingleNode('(./following-sibling::table[1]//td[contains(@class, "detalleRuta")])[2]', $segment, true, '/^(.+?)\s+\(/'));
            // ArrDate
            $time2 = $this->http->FindSingleNode('(./following-sibling::table[1]//td[contains(@class, "detalleHoraSinAncho")])[2]', $segment);
            $seg->setArrDate(strtotime($time2, $currentDate));
            // DepartureTerminal
            $seg->setDepTerminal($this->http->FindSingleNode('(./following-sibling::table[1]//td[contains(@class, "detalleTerminal75")])[1]', $segment, true, '/Terminal: (\w+)/'), false, true);
            // ArrivalTerminal
            $seg->setArrTerminal($this->http->FindSingleNode('(./following-sibling::table[1]//td[contains(@class, "detalleTerminal75")])[2]', $segment, true, '/Terminal: (\w+)/'), false, true);
            // Seats
            $seats = $this->getSeats($bookingNode, $i, $isOutward);
            $seg->setSeats($seats);
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($flight->toArray(), true), ['pre' => true]);
    }

    private function parseChangedItinerary()
    {
        $this->logger->notice(__METHOD__);
        $flight = $this->itinerariesMaster->createFlight();
        $conf = $this->http->FindSingleNode('//span[contains(@id, "lblPNR")]');
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $conf), ['Header' => 3]);
        $flight->addConfirmationNumber($conf, 'Booking Code', true);

        // TripSegments
        $segments = $this->xpathQuery('//h6[contains(text(), "Flight ")]');

        foreach ($segments as $segment) {
            $seg = $flight->addSegment();
            // AirlineName
            $seg->setAirlineName($this->http->FindPreg('/Flight\s*(\w{2})/', false, $segment->nodeValue));
            // FlightNumber
            $seg->setFlightNumber($this->http->FindPreg('/Flight\s*\w{2}(\d+)/', false, $segment->nodeValue));
            // DepCode
            $seg->setDepCode($this->http->FindSingleNode('./following-sibling::table[1]/tbody/tr[1]/td[1]', $segment, true, '/\(([A-Z]{3})\)/'));
            // DepName
            $seg->setDepName($this->http->FindSingleNode('./following-sibling::table[1]/tbody/tr[1]/td[1]', $segment, true, '/^(.+?)\s+\(/'));
            // DepDate
            $currentDate = $this->http->FindSingleNode('.//ancestor::div[1]/h6[1]', $segment);
            $currentDate = strtotime($currentDate);
            $time1 = $this->http->FindSingleNode('(./following-sibling::table[1]//td[contains(@class, "detalleHoraSinAncho")])[1]', $segment);
            $seg->setDepDate(strtotime($time1, $currentDate));
            // ArrCode
            $seg->setArrCode($this->http->FindSingleNode('./following-sibling::table[1]/tbody/tr[2]/td[1]', $segment, true, '/\(([A-Z]{3})\)/'));
            // ArrName
            $seg->setArrName($this->http->FindSingleNode('./following-sibling::table[1]/tbody/tr[2]/td[1]', $segment, true, '/^(.+?)\s+\(/'));
            // ArrDate
            $time2 = $this->http->FindSingleNode('(./following-sibling::table[1]//td[contains(@class, "detalleHoraSinAncho")])[2]', $segment);
            $seg->setArrDate(strtotime($time2, $currentDate));
            // DepartureTerminal
            $seg->setDepTerminal($this->http->FindSingleNode('./following-sibling::table[1]/tbody/tr[1]/td[2]', $segment, true, '/Terminal: (\w+)/'), false, true);
            // ArrivalTerminal
            $seg->setArrTerminal($this->http->FindSingleNode('./following-sibling::table[1]/tbody/tr[2]/td[2]', $segment, true, '/Terminal: (\w+)/'), false, true);
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($flight->toArray(), true), ['pre' => true]);
    }

    private function getSeats($bookingNode, $segmentIndex, $isOutward): array
    {
        $this->logger->notice(__METHOD__);

        if ($isOutward) {
            $seatNodes = $this->xpathQuery('.//ul[contains(@class, "paxNameList")]/li[contains(@class, "colInRow")]/div[2]/strong[1]', $bookingNode);
        } else {
            $seatNodes = $this->xpathQuery('.//ul[contains(@class, "paxNameList")]/li[contains(@class, "colOutRow")]/div[2]/strong[1]', $bookingNode);
        }

        $seats = [];

        foreach ($seatNodes as $node) {
            $personSeats = explode('/', $node->nodeValue);

            if (isset($personSeats[$segmentIndex])) {
                $seat = trim($personSeats[$segmentIndex]);

                if ($seat == 'Not assigned') {
                    continue;
                }
                $seats[] = $this->http->FindPreg('/^(\w+)/', false, $seat);
            }
        }

        return array_values(array_filter($seats));
    }

    private function getSeatsRetrieve($segmentIndex): array
    {
        $this->logger->notice(__METHOD__);
        $seatNodes = $this->xpathQuery('//table[@id = "table-paxsListChangeItinerarySeats"]//tr[@data-passengername]/td[1]');

        $seats = [];

        foreach ($seatNodes as $node) {
            $personSeats = explode('/', $node->nodeValue);

            if (isset($personSeats[$segmentIndex])) {
                $seat = trim($personSeats[$segmentIndex]);

                if ($seat == 'Not assigned') {
                    continue;
                }

                if (strpos($seat, 'Extra seat') !== false) {
                    $seats[] = $this->http->FindPreg("/^(\d[A-z])\s*\S\s*\d[A-z]/", false, $seat);
                    $seats[] = $this->http->FindPreg("/^\d[A-z]\s*\S\s*(\d[A-z])/", false, $seat);
                } else {
                    $seats[] = $seat;
                }
            }
        }

        return array_values(array_filter($seats));
    }

    private function isOutward($segment): bool
    {
        $this->logger->notice(__METHOD__);
        $node = $this->xpathQuery('./ancestor::div[1]', $segment);

        if ($node->length !== 1) { // outward is the default
            return true;
        }
        $node = $node->item(0);
        $id = $node->attributes->getNamedItem('id');

        if (!$id) {
            return true;
        }

        return $this->http->FindPreg('/Ida$/', false, $id->value) ? true : false;
    }

    private function parseChangedRetrieve(): void
    {
        $this->logger->notice(__METHOD__);
        $flight = $this->itinerariesMaster->createFlight();
        $conf = $this->http->FindSingleNode("//h3[contains(.,'Booking code')]/span");
        $flight->addConfirmationNumber($conf, 'Booking Code', true);

        $countCancelledSegments = 0;
        // TripSegments
        $segments = $this->xpathQuery("//div[@id='content_info-actualizada']//div[contains(@class,'flight-icon-plane')]/ancestor::div[contains(@class,'wrap-item-flight ')][1]");

        if ($segments->length === 0) {
            $segments = $this->xpathQuery("//div[contains(@class,'flight-icon-plane')]/ancestor::div[./preceding-sibling::div][1]");
        }

        foreach ($segments as $segment) {
            $seg = $flight->addSegment();
            $status = $this->http->FindSingleNode(
                "./preceding-sibling::div[last() - 1]/div/descendant::text()[normalize-space()!=''][1]",
                $segment
            );

            if (null !== $status) {
                $seg->extra()->status($status);

                if (stripos($status, 'cancelled') !== false || stripos($status, 'Pending refund') !== false) {
                    $countCancelledSegments++;
                }
            }
            $seg->extra()
                ->cabin(
                    $this->http->FindSingleNode("./preceding-sibling::div[last()]/div/div", $segment),
                    false,
                    true
                );
            $date = $this->http->FindSingleNode('./preceding-sibling::div[1]/span[last()]', $segment);
            $date = strtotime($date);
            // AirlineName
            $seg->setAirlineName($this->http->FindSingleNode("./following-sibling::div/div/span[1]",
                $segment,
                false,
                "/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\d+$/"
            ));
            // FlightNumber
            $seg->setFlightNumber($this->http->FindSingleNode(
                "./following-sibling::div/div/span[1]",
                $segment,
                false,
                "/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)$/"
            ));
            // DepCode
            $seg->setDepCode($this->http->FindSingleNode(
                ".//div[contains(@class,'wrap-item-flight-info_item flight-outbound')]//*[contains(@class,'infoFlight__infoIata__iata')]",
                $segment,
                true,
                "/^([A-Z]{3})$/"
            ));
            // DepName
            $depName = $this->http->FindSingleNode(
                ".//div[contains(@class,'wrap-item-flight-info_item flight-outbound')]//*[contains(@class,'infoFlight__item__infoRoute')]", $segment);
            $seg->setDepName($this->http->FindPreg("/(.+?)(?:\s+T\w)?$/", false, $depName));
            // DepartureTerminal
            if ($seg->getDepName() !== $depName) {
                $seg->setDepTerminal($this->http->FindPreg("/.+?\s+T(\w)$/", false, $depName));
            }
            // DepDate
            $time1 = $this->http->FindSingleNode(
                ".//div[contains(@class,'wrap-item-flight-info_item flight-outbound')]//*[contains(@class,'info-hour-default') or contains(@class,'info-new-hour')]", $segment, false, "/^(\d+:\d+)h?$/");

//            if (!$time1) {
//                $time1 = $this->http->FindSingleNode("./div[1]/descendant::text()[normalize-space() != ''][2]", $segment, false, "/^(\d+:\d+)h?$/");
//            }
            $seg->setDepDate(strtotime($time1, $date));
            // ArrCode
            $seg->setArrCode($this->http->FindSingleNode(
                "(.//div[contains(@class,'wrap-item-flight-info_item flight-return')]//*[contains(@class,'infoFlight__infoIata__iata')])[1]",
                $segment,
                true,
                "/^([A-Z]{3})$/"
            ));
            // ArrName
            $arrName = $this->http->FindSingleNode(
                ".//div[contains(@class,'wrap-item-flight-info_item flight-return')]//*[contains(@class,'infoFlight__item__infoRoute')][1]", $segment);
            $seg->setArrName($this->http->FindPreg("/(.+?)(?:\s+T\w)?$/", false, $arrName));
            // ArrivalTerminal
            if ($seg->getArrName() !== $arrName) {
                $seg->setArrTerminal($this->http->FindPreg("/.+?\s+T(\w)$/", false, $arrName));
            }
            // ArrDate
            $time2 = $this->http->FindSingleNode(
                "(.//div[contains(@class,'wrap-item-flight-info_item flight-return')]//*[contains(@class,'info-hour-default') or contains(@class,'info-new-hour')])[1]", $segment, false, "/^(\d+:\d+)h?$/");

//            if (!$time2) {
//                $time2 = $this->http->FindSingleNode("./div[2]/descendant::text()[normalize-space() != ''][2]", $segment, false, "/^(\d+:\d+)h?$/");
//            }
            $seg->setArrDate(strtotime($time2, $date));
        }
        // Status
        $statuses = array_unique($this->http->FindNodes("//div[contains(@class,'flight-icon-plane')]/ancestor::div[./preceding-sibling::div][1]/ancestor::div[1]/preceding-sibling::div[last()-1]/div/descendant::text()[normalize-space() != ''][1]"));

        if (count($statuses) === 1) {
            $flight->setStatus(array_shift($statuses));
        }

        if ($countCancelledSegments === $segments->length) {
            $flight->general()->cancelled();
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($flight->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryRetrieve()
    {
        $this->logger->notice(__METHOD__);
        $flight = $this->itinerariesMaster->createFlight();
        $conf = $this->http->FindSingleNode('//input[@id = "recordLocator"]/@value');
        $flight->addConfirmationNumber($conf, 'Booking Code', true);

        // Total
        if ($totalInfo = $this->http->FindSingleNode('//div[contains(@class, "paxPaymentTotal")]')) {
            $totalStr = $this->http->FindPreg('/(\d[\d.,]+)/', false, $totalInfo);
            $total = PriceHelper::cost($totalStr);
            $flight->price()->total($total);
            // Currency
            $flight->price()->currency($this->http->FindPreg('/([A-Z]{3})/', false, $totalInfo));
        }
        // Status
        if ($status = $this->http->FindSingleNode('(//span[contains(@class, "paymentStatus")])[1]')) {
            $flight->setStatus($status);
        }
        // Passengers
        $names = $this->http->FindNodes('//tr[@class = "table-passengerDetail__name"]/td[@colspan]/p');
        $names = array_map(function ($name) {
            return beautifulName($name);
        }, $names);
        $flight->setTravellers(array_values(array_unique(array_filter($names))));
        // TripSegments
        $segments = $this->xpathQuery('//strong[contains(text(), "Flight Nº:")]/ancestor::div[contains(@class, "flightDetailsBox__infoFLight__sectionContent")]');

        foreach ($segments as $i => $segment) {
            $seg = $flight->addSegment();
            // AirlineName
            $seg->setAirlineName($this->http->FindPreg('/Flight Nº:\s*(\w{2})/', false, $segment->nodeValue));
            // FlightNumber
            $seg->setFlightNumber($this->http->FindPreg('/Flight Nº:\s*\w{2}(\d+)/', false, $segment->nodeValue));
            // DepCode
            $seg->setDepCode($this->http->FindSingleNode('(.//span[contains(@class, "flightDetailsBox__infoFLight__terminal")])[1]', $segment, true, '/\b([A-Z]{3})\b/'));
            // DepName
            $seg->setDepName($this->http->FindSingleNode('(.//strong[contains(@class, "detalleRuta")])[1]', $segment));
            // DepDate
            $date = $this->http->FindSingleNode('.//ancestor::div[contains(@class, "flightDetailsBox__infoFLight__block")]//p[contains(@class, "flightDetailsBox__date")]/span[2]', $segment);
            $date = strtotime($date);
            $time1 = $this->http->FindSingleNode('(.//span[contains(@class, "flightDetailsBox__infoFLight__time")])[1]', $segment);
            $seg->setDepDate(strtotime($time1, $date));
            // DepartureTerminal
            $seg->setDepTerminal($this->http->FindSingleNode('(.//span[contains(@class, "flightDetailsBox__infoFLight__terminal")])[1]', $segment, true, '/\(T(\w+)\)/'), false, true);
            // ArrCode
            $seg->setArrCode($this->http->FindSingleNode('(.//span[contains(@class, "flightDetailsBox__infoFLight__terminal")])[2]', $segment, true, '/\b([A-Z]{3})\b/'));
            // ArrName
            $seg->setArrName($this->http->FindSingleNode('(.//strong[contains(@class, "detalleRuta")])[2]', $segment));
            // ArrDate
            $time2 = $this->http->FindSingleNode('(.//span[contains(@class, "flightDetailsBox__infoFLight__time")])[2]', $segment);
            $seg->setArrDate(strtotime($time2, $date));
            // ArrivalTerminal
            $seg->setArrTerminal($this->http->FindSingleNode('(.//span[contains(@class, "flightDetailsBox__infoFLight__terminal")])[2]', $segment, true, '/\(T(\w+)\)/'), false, true);
            // Seats
            $seats = $this->getSeatsRetrieve($i);
            $seg->setSeats($seats);
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($flight->toArray(), true), ['pre' => true]);
    }

    private function sendSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9039821.45-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,388656,9525783,1536,880,1536,960,1536,448,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8973,0.06294983631,789799762891.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,888,-1,0;0,0,0,0,447,447,0;1,0,0,0,658,658,0;0,-1,0,0,5468,5409,0;0,-1,0,0,6006,5947,0;0,-1,0,0,1075,-1,1;0,-1,0,0,1076,-1,1;0,-1,0,0,5469,5410,0;0,-1,0,0,6007,5948,0;-1,2,-94,-102,0,-1,0,0,888,-1,0;0,0,0,0,447,447,0;1,0,0,0,658,658,0;0,-1,0,0,5468,5409,0;0,-1,0,0,6006,5947,0;0,-1,0,0,1075,-1,1;0,-1,0,0,1076,-1,1;0,-1,0,0,5469,5410,0;0,-1,0,0,6007,5948,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://tickets.vueling.com/?AspxAutoDetectCookieSupport=1-1,2,-94,-115,1,1,0,0,0,0,0,2,0,1579599525783,-999999,16898,0,0,2816,0,0,3,0,0,DE0619012E0D01F94E98819E5943BBB5~-1~YAAQQoQUAnLxLG1vAQAAlOd3xwNCcdk0OvmVL0f0XqHAA2n71zbgCicJEWrEiH8cVRKMec4vmyON2EMgGOLGufLE2SPNYDChTI0ni3ao9936eJIz+rKAFuCcklC251TUg+dpu0DOc4Rara4kUC7IPEaW1HKWTBJURx40+wOc78/p28K53oF2Hb5DzcTEIrAVxOTAlEXRORpcH7IybkapQq4nI18DXOzzqzL+I5Exekd29lLgdaKtynacMgOQB7agd3X5dBVO+CWlA7eNpr+RUUITqDmEcYCKbjTZOQPKy3efO3Xc0NmFXQ7GKw==~-1~-1~-1,28943,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-125,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,85732006-1,2,-94,-118,93725-1,2,-94,-121,;2;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9023351.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,387188,3133159,1440,829,1440,900,1440,331,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.365466110182,786816566579.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,888,-1,0;0,0,0,0,447,447,0;1,0,0,0,658,658,0;0,-1,0,0,5468,5409,0;0,-1,0,0,6006,5947,0;0,-1,0,0,1075,-1,1;0,-1,0,0,1076,-1,1;0,-1,0,0,5469,5410,0;0,-1,0,0,6007,5948,0;-1,2,-94,-102,0,-1,0,0,888,-1,0;0,0,0,0,447,447,0;1,0,0,0,658,658,0;0,-1,0,0,5468,5409,0;0,-1,0,0,6006,5947,0;0,-1,0,0,1075,-1,1;0,-1,0,0,1076,-1,1;0,-1,0,0,5469,5410,0;0,-1,0,0,6007,5948,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://tickets.vueling.com/-1,2,-94,-115,1,1,0,0,0,0,0,2,0,1573633133159,-999999,16834,0,0,2805,0,0,3,0,0,10DB410A78C505F189E88DC65A3E6234~-1~YAAQYZt7XH6ai0tuAQAAGQbYYwLDKo9bDFH0jbUzH3qcI58IDgcqqcb2Ok4PiohrzKnE0WCfckRMRERQELKDkH/oKVw87M0veXnw0BwlAJL95rDc6QH8JkRryxWRb1UnHOQvYzZKCQ3H9j7pZoYPGdjXFowS4yC/0z+rg2OJcvUOpWtElJ4NiZFd42mWXfGkLwQ7yPsTK9aiieHNg4obRFktK9naJqVL+/rvlHf+zOG5/3ynI/F3lZKgI4Jl68LQSE5jhekIPuth/a/CopdGoUKQOGB6ylzvHj2nkEjcrCJbH9TFcOZuS0atxw==~-1~-1~-1,29728,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,3133169-1,2,-94,-118,90771-1,2,-94,-121,;2;-1;0",
            // 2
            "",
            // 3
            "7a74G7m23Vrp0o5c9039821.45-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:72.0) Gecko/20100101 Firefox/72.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,388656,9124749,1536,880,1536,960,1536,424,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6002,0.302040830151,789799562374,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,888,-1,0;0,0,0,0,447,447,0;1,0,0,0,658,658,0;0,-1,0,0,5468,5409,0;0,-1,0,0,6006,5947,0;0,-1,0,0,1075,-1,1;0,-1,0,0,1076,-1,1;0,-1,0,0,5469,5410,0;0,-1,0,0,6007,5948,0;-1,2,-94,-102,0,-1,0,0,888,-1,0;0,0,0,0,447,447,0;1,0,0,0,658,658,0;0,-1,0,0,5468,5409,0;0,-1,0,0,6006,5947,0;0,-1,0,0,1075,-1,1;0,-1,0,0,1076,-1,1;0,-1,0,0,5469,5410,0;0,-1,0,0,6007,5948,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://tickets.vueling.com/-1,2,-94,-115,1,1,0,0,0,0,0,2,0,1579599124748,-999999,16898,0,0,2816,0,0,4,0,0,66B68859EEB25BCBF5E94514141D3872~-1~YAAQYoQUAg0gDFNvAQAA0MFxxwNZf3cjT5L3zRigy9Rd8iTjlOUOJYNzlinAdiJ71mkqLqclePl0AcwZahp6MtyH1ELp7dAdqDus7t1qOnPetHlWwjrZWnVR/R92r+n4xihrzlQmp3KASxf8CYSBMpIkrCoFh+qFvviteai3uR4yD9h79HaDr2/Cx9FWaoJZ3ILYsCU0AxecETY7J8m9xwlcukY3FW6bHQkp+Rh9l22SoMJcSTV+M+lC6wPW+TePKZ1WxRzHfVR6QW+jLJN25atAtXX4UTGHtE9DQ/j30O/J51Bqk/M3xXQXAlgSrfJxqawhxrGlHQtp0m2ipSFpVjN62fmg3Q==~-1~-1~-1,32957,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-125,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,228118932-1,2,-94,-118,91907-1,2,-94,-121,;3;-1;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9039821.45-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,388656,9525783,1536,880,1536,960,1536,448,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8973,0.625800789312,789799762891.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,888,-1,0;0,0,0,0,447,447,0;1,0,0,0,658,658,0;0,-1,0,0,5468,5409,0;0,-1,0,0,6006,5947,0;0,-1,0,0,1075,-1,1;0,-1,0,0,1076,-1,1;0,-1,0,0,5469,5410,0;0,-1,0,0,6007,5948,0;-1,2,-94,-102,0,-1,0,0,888,-1,0;0,0,0,0,447,447,0;1,0,0,0,658,658,0;0,0,1,0,5468,5409,0;0,0,1,0,6006,5947,0;0,-1,1,0,1075,-1,1;0,-1,1,0,1076,-1,1;0,0,1,0,5469,5410,0;0,0,1,0,6007,5948,0;-1,2,-94,-108,-1,2,-94,-110,0,2,159,-1,-1,6134;1,1,389,1252,78;2,1,390,1252,78;3,1,445,1252,78;4,1,446,1252,78;5,1,558,1252,79;6,1,581,1252,79;7,1,650,1252,79;8,1,740,1252,79;9,3,2099,1252,79,-1;10,4,2259,1252,79,-1;11,2,2260,1252,79,-1;12,1,2686,1252,80;13,1,2694,1252,82;14,1,2702,1252,85;15,1,2712,1252,89;16,1,2718,1251,93;17,1,2726,1251,97;18,1,2734,1249,106;19,1,2742,1249,115;20,1,2750,1248,124;21,1,2823,1242,212;22,1,2830,1241,215;23,1,2839,1241,220;24,1,2846,1241,224;25,1,2854,1241,227;26,1,2862,1240,229;27,1,2870,1240,230;28,1,2878,1240,230;29,1,2886,1240,231;30,1,2894,1240,231;31,1,2926,1240,231;32,1,2934,1240,231;33,1,2942,1240,230;34,1,2950,1240,230;35,1,2958,1240,229;36,1,2966,1240,229;37,1,2974,1239,228;38,1,2982,1239,228;39,1,2990,1239,227;40,1,3047,1239,226;41,1,3063,1238,226;42,1,3070,1238,226;43,1,3078,1238,226;44,1,3086,1237,225;45,1,3094,1237,225;46,1,3102,1236,225;47,1,3111,1234,225;48,1,3118,1234,224;49,1,3126,1232,224;50,1,3134,1231,223;51,1,3142,1230,223;52,1,3151,1229,223;53,1,3158,1228,223;54,1,3168,1227,223;55,1,3174,1226,223;56,1,3182,1226,223;57,1,3190,1225,223;58,1,3199,1225,223;59,1,3309,1217,233;60,1,3311,1216,234;61,1,3319,1215,235;62,1,3326,1215,236;63,1,3334,1214,237;64,1,3343,1213,238;65,1,3350,1212,238;66,1,3358,1211,239;67,3,3371,1211,239,658;68,1,3377,1211,239;69,4,3539,1211,239,658;70,2,3540,1211,239,658;71,1,4142,1210,239;72,1,4150,1200,241;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,248;3,248;3,323;3,323;3,324;3,324;-1,2,-94,-112,https://tickets.vueling.com/?AspxAutoDetectCookieSupport=1-1,2,-94,-115,1,304120,0,0,0,0,304119,4222,0,1579599525783,13,16898,0,73,2816,4,0,4224,198806,0,DE0619012E0D01F94E98819E5943BBB5~-1~YAAQtTV6XFQwletuAQAAgwV4xwPs5yBgD2s446PB9dD/SzqFSjlTNyEFxsF2mJbjH0jNhJheqWa3VeL5FlTY5xS/H5+mGFOj3L62wmy1Dxc2WtXzIzfDibmTedaE1Te5UW3n0O1KAm3X8e0LI3vf8vqIbZC96EvcMqIdTrb+nhpr/uFn99RLD6jZ+WZYiQnoapmBkrywUqzPIt+CXWzh3syczngM8H8SUKDOlx2+DkLrS4Z5sUfZz2B8s2jCJU59VJFIwEqsfTNHDDAhuoJskHYqPlctVbD5mlEW80rm8hIA2RcGSCsygHtD0V1+E9lBfYI43JjNeCtoK07lh9O6xsxmi2RB~-1~-1~-1,32453,368,1808462405,30261693-1,2,-94,-106,8,4-1,2,-94,-119,27,32,29,29,44,47,11,8,6,5,5,5,9,377,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.0f0d932c5f8b6,0.b6b44b3a52c79,0.088a3d05f0577,0.3c2d1cd68e617,0.5bbe6cbf1deed,0.7186544fb71b3,0.f84a4af865dd,0.5938278bddf24,0.4dd382e5baeb5,0.5d3084d8829c8;87,74,2,69,56,106,20,69,66,68;1803,7492,236,7189,6280,12129,2061,7540,6947,7807;DE0619012E0D01F94E98819E5943BBB5,1579599525783,BSMlUEVMwX,DE0619012E0D01F94E98819E5943BBB51579599525783BSMlUEVMwX,4500,4500,0.0f0d932c5f8b6,DE0619012E0D01F94E98819E5943BBB51579599525783BSMlUEVMwX45000.0f0d932c5f8b6,7,120,110,242,84,49,87,60,138,179,97,168,237,229,126,142,42,131,200,72;-1,2,-94,-125,-1,2,-94,-70,-36060876;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,4945-1,2,-94,-116,85732006-1,2,-94,-118,202210-1,2,-94,-121,;3;5;0",
            // 1
            "7a74G7m23Vrp0o5c9023351.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,387188,3133159,1440,829,1440,900,1440,331,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.860053607430,786816566579.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,888,-1,0;0,0,0,0,447,447,0;1,0,0,0,658,658,0;0,-1,0,0,5468,5409,0;0,-1,0,0,6006,5947,0;0,-1,0,0,1075,-1,1;0,-1,0,0,1076,-1,1;0,-1,0,0,5469,5410,0;0,-1,0,0,6007,5948,0;-1,2,-94,-102,0,-1,0,0,888,-1,0;0,0,0,0,447,447,0;1,0,0,0,658,658,0;0,0,1,0,5468,5409,0;0,0,1,0,6006,5947,0;0,-1,1,0,1075,-1,1;0,-1,1,0,1076,-1,1;0,0,1,0,5469,5410,0;0,0,1,0,6007,5948,0;-1,2,-94,-108,-1,2,-94,-110,0,2,137,-1,-1,6134;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,237;3,237;3,336;3,336;3,338;3,338;-1,2,-94,-112,https://tickets.vueling.com/-1,2,-94,-115,1,138,0,0,0,0,137,446,0,1573633133159,15,16834,0,1,2805,0,0,448,137,0,10DB410A78C505F189E88DC65A3E6234~-1~YAAQYZt7XJGai0tuAQAA1wrYYwLV+UXurDiCIXwswOOI++k80NFUBqmwPN0kea8edgC5dczWMHUQYFzbWmv4AY2h5wxOUZmmihiTkFbsqz4Ln8P6dVCgHznZt98/3V+gXZUSZ8MKerrfNXwS3ENSuPRUL96wgT/k7+YKsJDcuxuqUhyEnvkH9i2aKF+uuLOY9TbUd98YC4PLWOnG36amcMf38bAWjNvJSTGWLgiFsygzP1UAM1X0Grhg5wI8728VycUkDrGzcgNRfkOFxt2tBYTolNKvNfV3dkxQI2c46XwWuB+tEU8VGApSXxQYc5Z9eEIuEIRL7SLmhH+ePfFZBKWkmMpjnQ==~-1~-1~-1,32973,784,-840000784,30261693-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-434613739;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,4997-1,2,-94,-116,3133169-1,2,-94,-118,97370-1,2,-94,-121,;2;6;0",
            // 2
            "",
            // 3
            "7a74G7m23Vrp0o5c9039821.45-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:72.0) Gecko/20100101 Firefox/72.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,388656,9124749,1536,880,1536,960,1536,424,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6002,0.17399993386,789799562374,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,888,-1,0;0,0,0,0,447,447,0;1,0,0,0,658,658,0;0,-1,0,0,5468,5409,0;0,-1,0,0,6006,5947,0;0,-1,0,0,1075,-1,1;0,-1,0,0,1076,-1,1;0,-1,0,0,5469,5410,0;0,-1,0,0,6007,5948,0;-1,2,-94,-102,0,-1,0,0,888,-1,0;0,0,1,0,447,447,0;1,0,1,0,658,658,0;0,0,1,0,5468,5409,0;0,0,1,0,6006,5947,0;0,-1,1,0,1075,-1,1;0,-1,1,0,1076,-1,1;0,0,1,0,5469,5410,0;0,0,1,0,6007,5948,0;-1,2,-94,-108,0,1,14513,224,0,2,769;1,1,14767,86,0,2,769;2,2,14960,-2,0,0,769;3,1,16257,224,0,2,447;4,1,16432,86,0,2,447;5,2,16591,-2,0,0,447;6,1,16900,17,0,4,447;7,1,16915,16,0,12,447;8,2,19197,9,0,4,447;9,2,19278,17,0,0,447;10,1,21161,224,0,2,658;11,1,21433,86,0,2,658;12,2,21714,-2,0,0,658;-1,2,-94,-110,0,1,72,983,391;1,2,200,-1,-1,6134;2,1,389,1296,99;3,1,423,1296,101;4,1,444,1296,102;5,1,456,1296,102;6,1,472,1296,102;7,1,489,1296,102;8,1,539,1296,102;9,1,1928,1292,108;10,1,1944,1288,114;11,1,1961,1277,137;12,1,1978,1272,150;13,1,1994,1243,215;14,1,2012,1223,251;15,1,2027,1149,379;16,1,8830,924,415;17,1,8846,940,401;18,1,8862,947,395;19,1,8878,960,384;20,1,8896,1005,346;21,1,8913,1072,284;22,1,8929,1089,268;23,1,8946,1122,234;24,1,8996,1184,176;25,1,9011,1211,150;26,1,9029,1217,143;27,1,9045,1223,134;28,1,9062,1232,122;29,1,9095,1242,104;30,1,9129,1245,92;31,1,9146,1245,89;32,1,9163,1245,85;33,1,9195,1244,84;34,1,9246,1245,84;35,1,9262,1245,84;36,1,9278,1247,85;37,1,9295,1249,85;38,1,9312,1250,85;39,1,9328,1255,84;40,1,9345,1259,83;41,1,9362,1261,83;42,1,9379,1265,79;43,1,9395,1268,76;44,1,9413,1268,75;45,1,9429,1268,73;46,1,9444,1268,73;47,1,9462,1268,72;48,1,9479,1268,72;49,1,9512,1268,72;50,1,9579,1268,72;51,1,9613,1267,72;52,1,9629,1267,72;53,1,9645,1267,72;54,1,9663,1266,71;55,1,9680,1264,70;56,1,9696,1263,70;57,1,9712,1262,69;58,1,9728,1261,69;59,1,9745,1260,69;60,1,9764,1260,69;61,1,9795,1258,67;62,1,9812,1257,66;63,1,9829,1256,66;64,1,9845,1256,65;65,1,9862,1255,64;66,1,9878,1254,63;67,1,9912,1254,61;68,1,9928,1254,61;69,1,9944,1254,60;70,1,9995,1253,60;71,1,10129,1253,60;72,1,10145,1252,60;73,1,10163,1249,64;74,1,10179,1247,66;75,1,10196,1244,71;76,1,10212,1243,73;77,1,10229,1242,75;78,1,10245,1242,78;79,1,10313,1242,82;80,1,10328,1242,83;81,1,10344,1243,84;82,1,10361,1243,84;83,1,10379,1244,85;84,1,10422,1244,85;85,1,10659,1246,83;86,1,10695,1248,82;87,1,10712,1248,82;88,3,11836,1248,82,-1;89,4,11994,1248,82,-1;90,2,11996,1248,82,-1;91,1,12399,1248,82;92,1,12416,1247,83;93,1,12434,1247,83;94,1,12450,1246,84;95,1,12466,1244,85;96,1,12482,1244,86;97,1,12499,1242,89;98,1,12517,1241,90;99,1,12533,1239,93;100,1,12550,1233,104;101,1,12566,1226,116;102,1,12584,1203,148;103,1,12600,1185,168;163,3,13940,1033,235,-1;165,4,14074,1033,235,-1;166,2,14075,1033,235,-1;207,3,15978,1016,244,447;208,4,16080,1016,244,447;209,2,16081,1016,244,447;338,3,20499,1212,242,658;339,4,20645,1212,242,658;340,2,20646,1212,242,658;411,3,25021,1269,310,-1;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,292;3,292;3,373;3,373;3,375;3,375;3,11842;2,17572;0,17832;1,19078;3,19099;-1,2,-94,-112,https://tickets.vueling.com/-1,2,-94,-115,238640,1245736,0,0,0,0,1484374,25021,0,1579599124748,3,16898,13,412,2816,9,0,25023,1315684,0,66B68859EEB25BCBF5E94514141D3872~-1~YAAQYoQUAnwhDFNvAQAAnylyxwNdwZTfTPjSrWYXIzwdP7aydrZtH+tzAQ7g5X2tIKR+q+GsQNEgNS8KnMpIDiMJ+6m21uv+m9Hb3Q0YKDamZYfV5LqWq30kBK9AGZx0/lZTBxVeVkDws4LnTHSxc+jQG+HfIsW41/HHUiupYY3TFwXZ70+QhO9nBoTkApyWw5zEIPvTX1jttdLCc54QqS3J0C3fnJx2ubXyfmP263Zo6rMguZLMS0GkOjuo6OSMwo/8pCO/bvxMbNBibCg3wDnDoyO1C3VHzE92Wda3tdkHbISPi5nDAACtu6Ctt9qXjNWqvg6OUSUscp55oZs2ml3F9PLu~-1~-1~-1,32532,470,371711861,26067385-1,2,-94,-106,1,7-1,2,-94,-119,200,0,0,200,200,200,0,200,200,0,200,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.f125f319fec83,0.9ec0729f5423f,0.fbbb740db1a868,0.a59a02cc05118,0.96c673dfb8573,0.83d14eef6518a8,0.30e17123e3485,0.ba2b2858d06e98,0.b58c16bc6bf488,0.5b1e2b9ffaf588;160,28,27,18,237,16,5,13,14,19;16094,2779,2645,1677,25838,1450,402,1335,1447,1722;66B68859EEB25BCBF5E94514141D3872,1579599124748,uPwJyIrcEc,66B68859EEB25BCBF5E94514141D38721579599124748uPwJyIrcEc,4500,4500,0.f125f319fec83,66B68859EEB25BCBF5E94514141D38721579599124748uPwJyIrcEc45000.f125f319fec83,158,223,143,241,168,82,254,134,74,79,156,9,177,148,233,218,15,241,69,148;-1,2,-94,-125,-1,2,-94,-70,50988738;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,4668-1,2,-94,-116,228118932-1,2,-94,-118,254867-1,2,-94,-121,;3;6;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);
        $data = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);

        return $key;
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("vueling sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    private function getSensorDataFromRetrieveSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);
            $choice = rand(1, 2);

            if ($choice == 1) {
                $selenium->useGoogleChrome();
            } else {
                $selenium->useFirefox();
            }
            $selenium->disableImages();
            $selenium->keepCookies(false);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->removeCookies();

            try {
                $selenium->http->GetURL('https://tickets.vueling.com/RetrieveBooking.aspx?event=change&culture=en-GB');
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeOutException $e) {
                $this->logger->debug("TimeoutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $selenium->waitForElement(WebDriverBy::id('CONFIRMATIONNUMBER1Container'), 10);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (StaleElementReferenceException | NoSuchDriverException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $retry = true;
        } finally {
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }

    private function getSensorDataFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);
            $choice = rand(1, 2);

            if ($choice == 1) {
                $selenium->useGoogleChrome();
            } else {
                $selenium->useFirefox();
            }
            $selenium->disableImages();
            $selenium->keepCookies(false);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->removeCookies();

            try {
//                $selenium->http->GetURL('https://tickets.vueling.com/');
                $selenium->http->GetURL('https://tickets.vueling.com/UpdateProfile.aspx?culture=en-GB');
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeOutException $e) {
                $this->logger->debug("TimeoutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            if ($accpet = $selenium->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 5)) {
                $accpet->click();
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "ControlGroupLoginViewMyVueling$MemberLoginView2LoginViewMyVueling$TextBoxUserID"]'), 0);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "ControlGroupLoginViewMyVueling$MemberLoginView2LoginViewMyVueling$PasswordFieldPassword"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "ControlGroupLoginViewMyVueling_MemberLoginView2LoginViewMyVueling_LinkButtonLogIn"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);

            // canes
            if (empty($this->AccountFields['Pass'])) {
                throw new CheckException("The username could not be found or the password you entered was incorrect. Please try again.", ACCOUNT_PROVIDER_ERROR);
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $this->savePageToLogs($selenium);
            $this->logger->debug("click by btn");
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Personal details") or contains(text(), "Datos personales")] | //p[@role = "alert"]'), 10);
            $this->savePageToLogs($selenium);
            /*
            try {
                $selenium->http->GetURL('https://tickets.vueling.com/RetrieveBooking.aspx?event=change&culture=en-GB');
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeOutException $e) {
                $this->logger->debug("TimeoutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }
            $selenium->waitForElement(WebDriverBy::id('ControlGroupRetrieveBookingView_BookingRetrieveInputRetrieveBookingView_CONFIRMATIONNUMBER1'), 7);
            */

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (StaleElementReferenceException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        } catch (
            Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\ElementClickInterceptedException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
