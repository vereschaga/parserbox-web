<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerMsccruises extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public $regionOptions = [
        ""   => "Select your country",
        "au" => "Australia",
        "be" => "Belgium",
        "br" => "Brazil", // 19059#note-8
        "de" => "Germany", // refs #19059
        "it" => "Italy",
        "es" => "Spain",
        "us" => "USA",
        "uk" => "United Kingdom",
    ];

    private $transId = null;
    private $policy = null;
    private $itineraries = [];
    private $domain = 'https://www.msccruisesusa.com';
    private $tenant;
    private $firstName;
    private $lastName;
    private $lang = 'en';

    public static function GetAccountChecker($accountInfo)
    {
//        if ($accountInfo['Login2'] == 'au') {
//            return new TAccountCheckerMsccruises();
//        } else {
        require_once __DIR__ . '/TAccountCheckerMsccruisesSelenium.php';

        return new TAccountCheckerMsccruisesSelenium();
//        }
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    /*
    function GetRedirectParams($targetURL = null) {
        $arg = parent::GetRedirectParams();
        $arg['SuccessURL'] = "{$this->getLocale('domain')}/webapp/wcs/stores/servlet/OrderManagementView?{$this->getLocale('query')}";

        return $arg;
    }
    */

    public function UpdateGetRedirectParams(&$arg)
    {
        switch ($this->AccountFields['Login2']) {
            case 'au':
                $arg['RedirectURL'] = 'https://www.msccruises.com.au/en-au/Homepage.aspx';

                break;

            case 'be':
                $arg['RedirectURL'] = 'https://www.msccruises.be/nl-be/Homepage.aspx';

                break;

            case 'br':
                $arg['RedirectURL'] = 'https://www.msccruzeiros.com.br/Account/SignIn?ReturnUrl=%2fmanage-booking%2fmanage-your-booking&CancelUrl=%2fgerenciar-reserva%2fgerenciar-sua-reserva';

                break;

            case 'de':
                $arg['RedirectURL'] = 'https://www.msccruises.de/Account/SignIn?ReturnUrl=%2fmanage-booking%2fmanage-your-booking&CancelUrl=%2fbuchung-verwalten%2fbuchung-verwalten';

                break;

            case 'it':
                $arg['RedirectURL'] = 'https://www.msccrociere.it/my%20area/account%20settings';

                break;

            case 'uk':
                $arg['RedirectURL'] = 'https://www.msccruises.co.uk/my%20area/account%20settings';

                break;

            default:
                $arg['RedirectURL'] = 'https://www.msccruisesusa.com/Account/SignIn?ReturnUrl=%2fmanage-booking%2fmanage-your-booking&CancelUrl=%2fmanage-booking%2fmanage-your-booking';

                break;
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        switch ($this->AccountFields['Login2']) {
            case 'au':
                $this->domain = 'https://www.msccruises.com.au';

                break;

            case 'be':
                $this->domain = 'https://www.msccruises.be';

                break;

            case 'br':
                $this->domain = 'https://www.msccruzeiros.com.br';

                break;

            case 'de':
                $this->domain = 'https://www.msccruises.de';

                break;

            case 'it':
                $this->lang = 'it';
                $this->domain = 'https://www.msccrociere.it';

                break;

            case 'uk':
                $this->domain = 'https://www.msccruises.co.uk';

                break;
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        if (!in_array($this->AccountFields['Login2'], ['au', 'be'])) {
            $this->http->GetURL($this->domain . '/my-area/plan-my-cruise#/');
        } else {
            $this->http->PostURL("https://www.{$this->getLocale('domain')}/webapp/wcs/stores/servlet/DecryptCookie", $this->getLocale('query'), [], 20);
        }
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->debug('Region: ' . $this->AccountFields['Login2']);
        // Please enter a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            switch ($this->AccountFields['Login2']) {
                case 'it':
                    throw new CheckException("Si prega di inserire un indirizzo email valido.", ACCOUNT_INVALID_PASSWORD);

                    break;

                case 'de':
                    throw new CheckException("Geben Sie eine gültige E-Mail Adresse an", ACCOUNT_INVALID_PASSWORD);

                    break;

                default:
                    throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
            }
        }
        $this->http->removeCookies();

        if (!in_array($this->AccountFields['Login2'], ['be'])) {
            $this->http->GetURL("{$this->domain}/manage-booking/manage-your-booking");
//            if ($sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/\w+/.+?)'\]\);#")) {
//                $this->http->NormalizeURL($sensorPostUrl);
//                $this->sendStaticSensorData($sensorPostUrl);
//            }
            $this->http->GetURL($this->domain . "/Account/SignIn?ReturnUrl=%2fmy-area%2fplan-my-cruise%23%2f&CancelUrl=%2fmanage-booking%2fmanage-your-booking");
        } else {
            $this->http->GetURL("https://www.{$this->getLocale('domain')}/webapp/wcs/stores/servlet/AzureRedirect?{$this->getLocale('query')}");
        }
        $this->transId = $this->http->FindPreg("/\"transId\": \"([^\"]+)/");
        $this->tenant = $this->http->FindPreg("/\"tenant\": \"([^\"]+)/");
        $this->policy = $this->http->FindPreg("/\"policy\": \"([^\"]+)/");

        if (!$this->transId || !$this->tenant || !$this->policy) {
            return $this->checkErrors();
        }
        $this->http->FormURL = "https://mscb2cprod.b2clogin.com{$this->tenant}/SelfAsserted?tx={$this->transId}&p={$this->policy}";
        $this->http->SetInputValue('signInName', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('request_type', 'RESPONSE');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The site database appears to be down
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our apologies but we have encountered a problem.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 500 INTERNAL SERVER ERROR
        if ($message = $this->http->FindSingleNode("//div[contains(text(), '500 INTERNAL SERVER ERROR')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $csrf = $this->http->getCookieByName("x-ms-cpim-csrf", "mscb2cprod.b2clogin.com");
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();
        // Success
        if (isset($response->status) && $response->status == 200) {
            if (empty($this->getLocale('diags'))) {
                return false;
            }
            $this->http->GetURL("https://mscb2cprod.b2clogin.com{$this->tenant}/api/CombinedSigninAndSignup/confirmed?csrf_token={$csrf}&tx={$this->transId}&p={$this->policy}&diags={$this->getLocale('diags')}");

            if ($this->http->ParseForm("auto")) {
                $this->http->PostForm(['Origin' => 'https://mscb2cprod.b2clogin.com']);
            }

            /**
             * We have found your account, but it is registered at the MSC website of another country.
             * For further information, please contact us at 848 24 24 90.
             *
             * Il tuo account è registrato al sito web MSC di un altro paese.
             * Per accedere al tuo account, visita il sito del paese in cui hai effettuato la registrazione oppure utilizza
             * un altro indirizzo mail per creare un nuovo account su questo sito.
             */
            if ($message = $this->http->FindSingleNode("
                //p[
                    contains(text(), 'Il tuo account è registrato al sito web MSC di un altro paese. Per accedere al tuo account')
                    or contains(text(), 'We found your account, but on a different MSC Website.')
                    or contains(text(), 'You are not allowed to proceed on this site, please go to your country web site to proceed')
                ]")
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode("
                //p[
                    contains(text(), 'Your account has not been confirmed. Please check your email and confirm the account registration by clicking the confirmation link in the email.')
                ]")
            ) {
                throw new CheckException('Your account has not been confirmed. Please check your email and confirm the account registration by clicking the confirmation link in the email.', ACCOUNT_PROVIDER_ERROR);
            }

            // We apologize for the inconvenience, but we are having issues retrieving your account. Please contact us for further information at +1-877-665-4655.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We apologize for the inconvenience, but we are having issues retrieving your account. Please contact us for further information at')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (in_array($this->AccountFields['Login2'], ['au', 'be'])) {
                $this->http->PostURL("https://www.{$this->getLocale('domain')}/webapp/wcs/stores/servlet/DecryptCookie", []);
            } else {
                if ($this->http->FindSingleNode('//p[contains(text(), "Your account\'s email has not been validated yet.")]')) {
                    $this->logger->debug("account not activated message workaround");
//                    $this->http->GetURL($this->domain);
//                    $this->http->GetURL($this->domain."/my-area/plan-my-cruise#/");
//                    $this->http->GetURL($this->domain . "/my-msc/msc-voyager-club");
//                    throw new CheckRetryNeededException();
                }

                if ($this->http->FindSingleNode('//p[contains(text(), "Il profilo non è stato ancora attivato.")]')) {
                    $this->logger->debug("account not activated message workaround");
//                    $this->http->GetURL("https://www.msccrociere.it");
//                    $this->http->GetURL("https://www.msccrociere.it/my-area/plan-my-cruise#/");
//                    $this->http->GetURL("https://www.msccrociere.it/my-area/msc-voyager-club");
//                    throw new CheckRetryNeededException();
                }
            }

            if ($this->loginSuccessful()) {
                return true;
            }
        }// if (isset($response->status) && $response->status == 200)
        // catch errors
        if (isset($response->message)) {
            $message = $response->message;

            if (in_array($this->AccountFields['Login2'], ['uk', 'it'])) {
                // Details entered are not recognised, please check and try again. If your account was created more than 30 days ago, please sign-up again.
                if (strstr($message, 'Details entered are not recognised, please check and try again.')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
            }

            // You need to change your password. Please click on the link "Change/Reset your password" below
            if (strstr($message, 'Per favore cambia la password. Clicca sul link Cambia/Resetta la tua password per procedere')
                || strstr($message, 'You need to change your password. Please click on the link "Change/Reset your password" below')) {
                $this->throwProfileUpdateMessageException();
            }
            // We can't seem to find your account
            if (
                strstr($message, 'We can\'t seem to find your account')
                || strstr($message, 'Due to new data protection regulations, to continue please change your password by clicking on')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Due to new login requirements, you must change your password. Please click on FORGOT PASSWORD to make this change.
            if (
                strstr($message, 'Due to new login requirements, you must change your password. Please click on FORGOT PASSWORD to make this change.')
                || strstr($message, 'I dettagli inseriti non sono stati riconosciuti, per favore controlla e riprova. Se il tuo account è stato creato più di 30 giorni fa, ti preghiamo di registrarti di nuovo.')
                || strstr($message, 'De ingevoerde gegevens worden niet herkend. Controleer het en probeer het opnieuw. Als uw account meer dan 30 dagen geleden is aangemaakt, meld u dan opnieuw aan.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Per favore cambia la password. Clicca qui sotto per completare la richiesta.
            if (strstr($message, 'Per favore cambia la password. Clicca qui sotto per completare la richiesta.')) {
                throw new CheckException("Per favore cambia la password.", ACCOUNT_INVALID_PASSWORD);
            }
            // Your password is incorrect
            if (strstr($message, 'Your password is incorrect')) {
                throw new CheckException('Your password is incorrect', ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'La  password non è corretta, riprova o fai clic su Reimposta la password qui sotto')) {
                throw new CheckException('La  password non è corretta, riprova', ACCOUNT_INVALID_PASSWORD);
            }
            // La password non è corretta
            if (strstr($message, 'La password non è corretta')) {
                throw new CheckException('La password non è corretta', ACCOUNT_INVALID_PASSWORD);
            }
        }// if (isset($response->message))

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (in_array($this->AccountFields['Login2'], ['it', 'us'])) {
            $this->selenium();

            return;
        }
        $response = $this->http->JsonLog(null, 0) ?? $this->http->JsonLog(base64_decode($this->http->getCookieByName("user")));

        // Name
        $this->firstName = $response->firstName ?? $response->name;
        $this->lastName = $response->lastName ?? $response->surname;
        $this->SetProperty('Name', beautifulName($this->firstName . ' ' . $this->lastName));
        // Number
        $this->SetProperty('Number', $response->mscClub ?? null);
        // Card: Black
        $this->SetProperty('EliteLevel', $response->cardType ?? null);
        // Points
        $this->SetBalance($response->cardPoint ?? null);
        // refs# 18750 Expiration date
        if (isset($response->cardPoint) && $response->cardPoint > 0 && ($exp = strtotime($this->ModifyDateFormat($response->scadenza ?? $response->cardExpiredDate), false))) {
            $this->SetExpirationDate($exp);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name'])
                && isset($response->cardType, $response->mscClub)
                && empty($this->Properties['EliteLevel'])
                && empty($response->mscClub)
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }

            if (!empty($this->Properties['Name'])
//                && isset($response->cardType, $response->cardPoint, $response->cardNumber)
                && $response
                && isset($response->cardType)
                && $response->cardType === null
                && $response->cardPoint === null
                && $response->cardNumber === null
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            } else {
                // AccountID: 4232754
                $this->http->GetURL("https://www.{$this->getLocale('domain')}/webapp/wcs/stores/servlet/OrderManagementView?{$this->getLocale('query')}#/club-area");
                // data-msc-club-ext-data="Gold|6300"
                if ($data = $this->http->FindPreg('/data-msc-club-ext-data="(.+?)"\n/')) {
                    // Card: Black
                    $this->SetProperty('EliteLevel', $this->http->FindPreg('/^(\w+)\|/', false, $data));
                    // Balance - Points
                    $this->SetBalance($this->http->FindPreg('/\|([\d.,]+)$/', false, $data));
                } elseif ($this->http->FindPreg('/data-msc-club-ext-data=""\n/') && $this->http->FindPreg('/data-msc-club-number=""\n/')) {
                    $this->SetWarning(self::NOT_MEMBER_MSG);
                }
            }
        }
    }

    public function ParseItineraries()
    {
        if (in_array($this->AccountFields['Login2'], ['br', 'de', 'it', 'us', 'uk'])) {
            //$this->selenium();
            return $this->itineraries;
        }

        $result = [];
        $this->http->RetryCount = 1;

        if (!$this->http->GetURL("https://www.{$this->getLocale('domain')}/webapp/wcs/stores/servlet/GetBookingAdded")) {
            sleep(5);
            $this->http->GetURL("https://www.{$this->getLocale('domain')}/webapp/wcs/stores/servlet/GetBookingAdded");
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog($this->http->FindPreg('#/\*(.+?)\*/#s'));

        if (empty($response->listBooking) && isset($response->errorMessage)) {
            sleep(5);
            $this->http->GetURL("{$this->getLocale('domain')}/webapp/wcs/stores/servlet/GetBookingListCmd");
            $response = $this->http->JsonLog($this->http->FindPreg('#/\*(.+?)\*/#s'));
        }

        if (!isset($response->listBooking) && !$this->http->FindPreg('/"errorCode":/')) {
            $this->sendNotification('refs #11750 - msccruises: no itineraries');

            return $result;
        }

        if ($this->http->FindPreg('/\{\s*"listBooking":\s*\[\]\s*\}/')) {
            return $this->noItinerariesArr();
        }

        foreach ($response->listBooking as $booking) {
            $this->http->GetURL("https://www.{$this->getLocale('domain')}/webapp/wcs/stores/servlet/GetBookingDataCmd?addItemLongDescription=true&bookingNo={$booking->bookingNumber}&{$this->getLocale('query')}&firstName={$this->firstName}&lastName={$this->lastName}");

            if ($timetable = $this->http->JsonLog($this->http->FindPreg('#/\*(.+?)\*/#s'))) {
                $this->logger->info(sprintf('Parse Itinerary #%s', $booking->bookingNumber), ['Header' => 3]);

                if (isset($timetable->errorMessage) && $timetable->errorMessage == "ACCESS_DENIED"
                    && isset($timetable->bookingNo) && ($timetable->bookingNo == $booking->bookingNumber
                        || is_array($timetable->bookingNo) && in_array($booking->bookingNumber, $timetable->bookingNo))
                ) {
                    $this->logger->debug('Skip booking:');
                    $this->logger->debug('Unable to open a booking selected from the dropdown list');

                    continue;
                }

                if (empty($timetable->error)) {
                    // Filter Past Itineraries
                    if (!$this->ParsePastIts && isset($timetable->serverValidation->arrivalDate)
                        && strtotime($timetable->serverValidation->arrivalDate) < strtotime('now')) {
                        continue;
                    }

                    if ($res = $this->parseItinerary($booking, $timetable)) {
                        $result[] = $res;
                    }
                }// if (empty($timetable->errorMessage))
                else {
                    $this->logger->error($timetable->error == 'errore Exception richiamo OpenBookingDetailsCmdImpl.java' ? 'Unable to open a booking selected from the dropdown list' : $timetable->error);
                }
            }// if ($timetable = $this->http->JsonLog($this->http->FindPreg('#/\*(.+?)\*/#s')))
        }// foreach ($response->listBooking as $booking)

        return $result;
    }

    private function getLocale($param)
    {
        switch ($this->AccountFields['Login2']) {
            case 'au':
                $locale = [
                    'domain' => 'msccruises.com.au',
                    'query'  => 'storeId=715828381&langId=-1017&catalogId=10001',
                    'diags'  => '%7B%22pageViewId%22%3A%220ffb6f64-83f7-4384-aae9-51e16934c0ce%22%2C%22pageId%22%3A%22CombinedSigninAndSignup%22%2C%22trace%22%3A%5B%7B%22ac%22%3A%22T005%22%2C%22acST%22%3A1580139634%2C%22acD%22%3A1%7D%2C%7B%22ac%22%3A%22T021%20-%20URL%3Ahttps%3A%2F%2Fwww.mscpartner.com%2Fazureb2c.aspx%3Fui_locales%3Den-AU%22%2C%22acST%22%3A1580139634%2C%22acD%22%3A325%7D%2C%7B%22ac%22%3A%22T029%22%2C%22acST%22%3A1580139634%2C%22acD%22%3A21%7D%2C%7B%22ac%22%3A%22T004%22%2C%22acST%22%3A1580139634%2C%22acD%22%3A3%7D%2C%7B%22ac%22%3A%22T019%22%2C%22acST%22%3A1580139634%2C%22acD%22%3A34%7D%2C%7B%22ac%22%3A%22T003%22%2C%22acST%22%3A1580139634%2C%22acD%22%3A47%7D%2C%7B%22ac%22%3A%22T002%22%2C%22acST%22%3A0%2C%22acD%22%3A0%7D%5D%7D',
                ];

                break;

            case 'be':
                $locale = [
                    'domain' => 'msccruises.be',
                    'query'  => 'storeId=13273&langId=-1015&catalogId=10001',
                    'diags'  => urlencode('{"pageViewId":"1a18220d-aa8d-46b0-88ae-c239ed198532","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1589801094,"acD":1},{"ac":"T021 - URL:https://www.mscpartner.com/azureb2c.aspx?ui_locales=nl-BE","acST":1589801094,"acD":50},{"ac":"T029","acST":1589801095,"acD":6},{"ac":"T004","acST":1589801095,"acD":3},{"ac":"T019","acST":1589801095,"acD":14},{"ac":"T003","acST":1589801096,"acD":67},{"ac":"T002","acST":0,"acD":0}]}'),
                ];

                break;

            case 'br':
                $locale = [
                    'domain' => 'msccruzeiros.com.br',
                    //                    'query'  => '',
                    'diags'  => urlencode('{"pageViewId":"89cc790f-eb71-490a-a5f6-2a3216ef7505","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1589270300,"acD":1},{"ac":"T021 - URL:https://account.msccruises.com/Azure-UI/AzureB2CUI-SignIn?ui_locales=pt-BR&context=AAIAAAAJUmV0dXJuVXJsIy9tYW5hZ2UtYm9va2luZy9tYW5hZ2UteW91ci1ib29raW5nCUNhbmNlbFVybCgvZ2VyZW5jaWFyLXJlc2VydmEvZ2VyZW5jaWFyLXN1YS1yZXNlcnZh&origin=https%3a%2f%2fwww.msccruzeiros.com.br","acST":1589270300,"acD":1026},{"ac":"T029","acST":1589270301,"acD":13},{"ac":"T004","acST":1589270301,"acD":2},{"ac":"T019","acST":1589270301,"acD":22},{"ac":"T003","acST":1589270301,"acD":6},{"ac":"T002","acST":0,"acD":0}]}'),
                ];

                break;

            case 'de':
                $locale = [
                    'domain' => 'msccruises.de',
                    //                    'query'  => '',
                    'diags'  => urlencode('{"pageViewId":"879ab8b6-7cf3-4cc0-bf9a-a6f4b30d1a66","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1589374601,"acD":1},{"ac":"T021 - URL:https://account.msccruises.com/Azure-UI/AzureB2CUI-SignIn?ui_locales=de-DE&context=AAIAAAAJUmV0dXJuVXJsIy9tYW5hZ2UtYm9va2luZy9tYW5hZ2UteW91ci1ib29raW5nCUNhbmNlbFVybCQvYnVjaHVuZy12ZXJ3YWx0ZW4vYnVjaHVuZy12ZXJ3YWx0ZW4%3d&origin=https%3a%2f%2fwww.msccruises.de","acST":1589374601,"acD":449},{"ac":"T029","acST":1589374601,"acD":24},{"ac":"T004","acST":1589374601,"acD":3},{"ac":"T019","acST":1589374601,"acD":35},{"ac":"T003","acST":1589374601,"acD":3},{"ac":"T002","acST":0,"acD":0}]}'),
                ];

                break;

            case 'it':
                $locale = [
                    'domain' => 'msccrociere.it',
                    //'query' => '',
                    'diags'  => '%7B%22pageViewId%22%3A%224d898801-986f-4fce-9f8c-f3f28c2a8ad1%22%2C%22pageId%22%3A%22CombinedSigninAndSignup%22%2C%22trace%22%3A%5B%7B%22ac%22%3A%22T005%22%2C%22acST%22%3A1543298630%2C%22acD%22%3A2%7D%2C%7B%22ac%22%3A%22T021%20-%20URL%3Ahttps%3A%2F%2Fwww.mscpartner.com%2Fazureb2c.aspx%3Fui_locales%3Dit-IT%22%2C%22acST%22%3A1543298630%2C%22acD%22%3A442%7D%2C%7B%22ac%22%3A%22T029%22%2C%22acST%22%3A1543298630%2C%22acD%22%3A13%7D%2C%7B%22ac%22%3A%22T004%22%2C%22acST%22%3A1543298630%2C%22acD%22%3A3%7D%2C%7B%22ac%22%3A%22T019%22%2C%22acST%22%3A1543298630%2C%22acD%22%3A35%7D%2C%7B%22ac%22%3A%22T003%22%2C%22acST%22%3A1543298630%2C%22acD%22%3A5%7D%2C%7B%22ac%22%3A%22T002%22%2C%22acST%22%3A0%2C%22acD%22%3A0%7D%5D%7D',
                ];

                break;

            case 'uk':
                $locale = [
                    'domain' => 'msccruises.co.uk',
                    //'query' => '',
                    'diags'  => '%7B%22pageViewId%22%3A%2242328174-0317-4412-a1d4-98a2796131a7%22%2C%22pageId%22%3A%22CombinedSigninAndSignup%22%2C%22trace%22%3A%5B%7B%22ac%22%3A%22T005%22%2C%22acST%22%3A1583909111%2C%22acD%22%3A2%7D%2C%7B%22ac%22%3A%22T021%20-%20URL%3Ahttps%3A%2F%2Faccount.msccruises.com%2FAzure-UI%2FAzureB2CUI-SignIn%3Fui_locales%3Den%26context%3DAAIAAAAJUmV0dXJuVXJsIy9tYW5hZ2UtYm9va2luZy9tYW5hZ2UteW91ci1ib29raW5nCUNhbmNlbFVybCMvbWFuYWdlLWJvb2tpbmcvbWFuYWdlLXlvdXItYm9va2luZw%253d%253d%26origin%3Dhttps%253a%252f%252fwww.msccruises.co.uk%22%2C%22acST%22%3A1583909111%2C%22acD%22%3A307%7D%2C%7B%22ac%22%3A%22T029%22%2C%22acST%22%3A1583909112%2C%22acD%22%3A11%7D%2C%7B%22ac%22%3A%22T004%22%2C%22acST%22%3A1583909112%2C%22acD%22%3A4%7D%2C%7B%22ac%22%3A%22T019%22%2C%22acST%22%3A1583909112%2C%22acD%22%3A24%7D%2C%7B%22ac%22%3A%22T003%22%2C%22acST%22%3A1583909112%2C%22acD%22%3A12%7D%2C%7B%22ac%22%3A%22T002%22%2C%22acST%22%3A0%2C%22acD%22%3A0%7D%5D%7D',
                ];

                break;

            default:
                $locale = [
                    'domain' => 'msccruisesusa.com',
                    'query'  => 'storeId=12264&langId=-1004&catalogId=10001',
                    'diags'  => urlencode('{"pageViewId":"4db16ee3-3e76-4223-a607-0591afbda751","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1589089146,"acD":1},{"ac":"T021 - URL:https://account.msccruises.com/Azure-UI/AzureB2CUI-SignIn?ui_locales=en-US&context=AAIAAAAJUmV0dXJuVXJsIy9tYW5hZ2UtYm9va2luZy9tYW5hZ2UteW91ci1ib29raW5nCUNhbmNlbFVybCMvbWFuYWdlLWJvb2tpbmcvbWFuYWdlLXlvdXItYm9va2luZw%3d%3d&origin=https%3a%2f%2fwww.msccruisesusa.com","acST":' . time() . ',"acD":945},{"ac":"T029","acST":' . time() . ',"acD":14},{"ac":"T004","acST":' . time() . ',"acD":2},{"ac":"T019","acST":' . time() . ',"acD":22},{"ac":"T003","acST":' . time() . ',"acD":4},{"ac":"T002","acST":0,"acD":0}]}'),
                ];

                break;
        }

        return $locale[$param] ?? null;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (in_array($this->AccountFields['Login2'], ['au', 'be'])) {
            $response = $this->http->JsonLog();

            if (isset($response->firstName, $response->lastName)) {
                return true;
            }
            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            if ($currentUrl == "{$this->domain}/account/messages/user-not-enabled") {
                $response = $this->http->JsonLog(base64_decode($this->http->getCookieByName("user")));

                if (isset($response->name, $response->surname)) {
                    return true;
                }
            }
        } else {
            if ($this->http->FindSingleNode("//a[@id = 'signoutUrl']")) {
                return true;
            }
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        //$this->http->GetURL('https://www.msccruisesusa.com/my-area/plan-my-cruise#/');
        $host = str_replace('https://www', '', $this->domain);
        $allCookies = [
            $host => array_merge(
                $this->http->GetCookies($host),
                $this->http->GetCookies($host, "/", true)),
            "www{$host}" => array_merge(
                $this->http->GetCookies("www{$host}"),
                $this->http->GetCookies("www{$host}", "/", true)),
        ];

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
            //$selenium->http->setUserAgent($this->http->getDefaultHeader('User-Agent'));
            $selenium->usePacFile(false);
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            //$this->logger->debug("ProxyAddress: ". $checker2->http->getProxyAddress());

            // Page Not Found
            $selenium->http->GetURL("{$this->domain}/my-msc/mscvoyagerclub");
            //$selenium->http->GetURL("http://www.xhaus.com/headers");

            foreach ($allCookies as $host => $values) {
                foreach ($values as $name => $value) {
                    $this->logger->debug("{$name} = {$value}, {$host}");

                    if (!empty($name) && !empty($value)) {
                        $selenium->driver->manage()->addCookie(['name' => $name, 'value' => $value, 'domain' => $host]);
                    }
                }
            }

            if ($selenium->http->FindSingleNode("//h1[starts-with(normalize-space(),'Oops, looks like this page is cruising right now')]")) {
                $this->logger->error('Oops, looks like this page is cruising right now');
                $selenium->http->GetURL($this->domain . "/manage-booking/manage-your-booking");
            }
            $this->parseSelenium($selenium);

            if ($this->ParseIts) {
                $urlCruises = $this->domain . "/my-area/plan-my-cruise#/";

                $selenium->http->GetURL($urlCruises);
                $signInName = $selenium->waitForElement(WebDriverBy::id('signInName'), 7);
                $password = $selenium->waitForElement(WebDriverBy::id('password'), 0);

                if ($signInName && $password) {
//                $retry = true;
//                return false;
                    sleep(2);
                    $urlCruises = $this->domain . "/my-area/plan-my-cruise#/";
                    $selenium->http->GetURL($urlCruises);
                }

                if ($message = $selenium->waitForElement(WebDriverBy::xpath("//div[starts-with(normalize-space(),'Dear Guest, for this reservation we kindly invite you to call')]"),
                    0)
                ) {
                    $this->logger->error($message->getText());
                } else {
                    $this->itineraries = $this->parseItinerariesSelenium($selenium, $urlCruises);
                }
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        } finally {
            // close Selenium browser
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
            $selenium->http->cleanup(); //todo

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function parseSelenium($selenium)
    {
        $this->logger->notice(__METHOD__);

        if ($this->AccountFields['Login2'] == 'it') {
            $selenium->http->GetURL("https://www.msccrociere.it/my-area/plan-my-cruise#/");
            $selenium->http->GetURL("https://www.msccrociere.it/my-area/msc-voyager-club");

            if ($selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Il profilo non è stato ancora attivato.")]'), 0)) {
                $this->logger->debug("account not activated message workaround");
                $selenium->http->GetURL("https://www.msccrociere.it/my-area/msc-voyager-club");
            }
        } else {
            $selenium->http->GetURL($this->domain . "/my-msc/msc-voyager-club");

            if ($selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Your account\'s email has not been validated yet.")]'), 0)) {
                $this->logger->debug("account not activated message workaround");
                $selenium->http->GetURL($this->domain . "/my-msc/msc-voyager-club");
            }
        }
        // Balance - points
        $balance = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'points' and (contains(text(), 'points') or contains(text(), 'punti'))]"), 10);
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();

        if ($balance) {
            $this->SetBalance($this->http->FindPreg('/([\d.,]+)\s+/', false, $balance->getText()));
        }
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[@class = 'nameMember']")));
        // Card #
        $this->SetProperty('Number', $this->http->FindSingleNode("//span[@class = 'card-number']"));
        // MEMBERSHIP
        $this->SetProperty('EliteLevel', $this->http->FindSingleNode("//div[@class = 'typeMember']/span[1]"));
        // refs# 18750 Expiration date
        $exp = $this->http->FindSingleNode("//div[@class = 'date-points']/div[@class = 'date']");

        if (isset($balance, $exp) && $this->Balance > 0 && ($exp = strtotime($exp, false))) {
            $this->SetExpirationDate($exp);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode('//label[contains(text(), "Are you already a MSC Voyager Club Member?") or contains(text(), "Sei già socio MSC Voyager Club?")]')) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }
    }

    private function parseItinerariesSelenium($selenium, $urlCruises)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
//        $urlCruises = $this->domain . "/my-area/plan-my-cruise#/";
//        $selenium->http->GetURL($urlCruises);

        $selenium->waitForElement(WebDriverBy::xpath("//h1[
                    contains(normalize-space(.), 'non sono al momento disponibili. Se hai già una crociera prenotata, associa il numero di prenotazione al tuo account')
                    or contains(normalize-space(.), 'Gentile Cliente, potrebbero essere necessarie fino a 24 ore dalla prenotazione per rendere disponibili i dettagli della tua crociera')
                    or contains(text(), 'please if you have booked a cruise link your Booking ID to your account')
                ]
                | (//*[@class='tile--mymsc-cruises__details'])[1]
        "), 10);
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();

        // Accept All Cookies
        if ($next = $selenium->waitForElement(WebDriverBy::id("onetrust-accept-btn-handler"), 0)) {
            $next->click();
        }

        if ($selenium->waitForElement(WebDriverBy::xpath("//h1[
                    contains(normalize-space(.), 'non sono al momento disponibili. Se hai già una crociera prenotata, associa il numero di prenotazione al tuo account')
                    or contains(normalize-space(.), 'Gentile Cliente, potrebbero essere necessarie fino a 24 ore dalla prenotazione per rendere disponibili i dettagli della tua crociera')
                    or contains(text(), 'please if you have booked a cruise link your Booking ID to your account')
                ]"), 0)
        ) {
            return $this->noItinerariesArr();
        }
        // title: Tutte le mie crociere | All my cruises
        if (!$this->http->FindSingleNode("(//*[@class='tile--mymsc-cruises__details'])[1]")) {
            return [];
        }

        if (!in_array($this->AccountFields['Login2'], ['uk', 'us'])) {
            $this->sendNotification($this->AccountFields['Login2'] . ' - new itineraries // MI');
        }

        $elements = $this->http->FindNodes("//section[@class='tile-container--mymsc']//button[normalize-space(text())='See details' or normalize-space(text())='Vedi dettagli']");

        if (count($elements) === 0 && (!empty($this->http->FindNodes("//section[@class='mycruise--all-cruises']")))) {
            $selenium->driver->executeScript("$('section.mycruise--all-cruises span a').get(0).click()");
            $selenium->waitForElement(WebDriverBy::xpath("//section[@class='tile-container--mymsc']//button[normalize-space(text())='See details' or normalize-space(text())='Vedi dettagli']"), 1);
            $urlCruises = $selenium->http->currentUrl();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $elements = $this->http->FindNodes("//section[@class='tile-container--mymsc']//button[normalize-space(text())='See details' or normalize-space(text())='Vedi dettagli']");
        }
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();

        if (count($elements) > 0) {
            $this->logger->debug("Total " . count($elements) . " itineraries were found");

            for ($i = 0; $i < count($elements); $i++) {
                $j = $i + 1;
                // Dear Guest, for this reservation we kindly invite you to call our contact center at 1-877-665-4655 specifying your booking ID in order
                // to reserve another cruise or fill in this form to be called back. We look forward to welcoming you on board again.
                $section = $selenium->waitForElement(WebDriverBy::xpath("(//section[@class='tile-container--mymsc'])[{$j}]//div[@class='tile--mymsc-cruises__title']"), 1);

                if (!$section) {
                    continue;
                }
                $selenium->driver->executeScript("$('section.tile-container--mymsc button.button').eq({$i}).get(0).click()");

                $this->increaseTimeLimit(); // was 360
                $this->parseItinerarySelenium($selenium);

                // $selenium->driver->executeScript("window.history.go(-1)");
                $this->logger->notice("Back URL: " . $urlCruises);
                $selenium->http->GetURL($urlCruises);
            }
        }

        return $result;
    }

    private function parseItinerarySelenium($selenium)
    {
        $this->logger->notice(__METHOD__);
        $c = $this->itinerariesMaster->add()->cruise();
        $text = $selenium->waitForElement(WebDriverBy::xpath("//*[@class='tile--mymsc-cruises__details']/span[contains(text(), 'Booking ID:')]"), 15);
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();

        if (!$text) {
            $text = $this->http->FindSingleNode("//*[@class='tile--mymsc-cruises__details']/span[1]");
        } else {
            $text = $text->getText();
        }

        if ($text) {
            $confNo = $this->http->FindPreg('/:\s*(\w+)/', false, $text);
            $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);
            $c->general()->confirmation($confNo);
        }

        // Close Popup
        $closePopup = $selenium->waitForElement(WebDriverBy::xpath("//*[@class='modal-header']/div[2]/*[1]"), 0);

        if ($closePopup) {
            $closePopup->click();
        }

        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();

        if ($dates = $this->http->FindSingleNode("//span[@class='tile--mymsc-cruises__from-port']")) {
            // 7 Dec 2019 - 21 Dec 2019
            // 28 dic 2019 - 4 gen 2020
            if (empty($dateStartTxt = $this->http->FindPreg('/\s*(\d+ \w+ \d{4})\s*-/', false, $dates))) {
                // Jan 2 2021 - Jan 9 2021
                $dateStartTxt = preg_replace("/(\w+) (\d+) (\d{4})/", '$2 $1 $3',
                    $this->http->FindPreg('/\s*(\w+ \d+ \d{4})\s*-/', false, $dates));
            }
            $dateStart = $this->dateStringToEnglish($dateStartTxt, $this->lang);
            $dateStart = strtotime($dateStart);
        } else {
            $this->logger->notice('Skip item: not date');

            return;
        }

        $selenium->driver->executeScript("scroll(0, 500)");
        $days = 0;

        if ($duration = $this->http->FindSingleNode("//span[contains(@class,'tile--mymsc-cruises__duration')]")) {
            $days = $this->http->FindPreg('/(\d+) n/', false, $duration) + 1;
        }
        $this->logger->debug("Found {$days} days");
        $date = null;

        for ($i = 0; $i <= $days; $i++) {
            $box = implode("\n", $this->http->FindNodes("//ul[@class='my-itinerary__list']/li[contains(@class,'my-itinerary__box') and contains(@class,'active')]/descendant::text()[normalize-space()!='']"));

            if (!$box) {
                $this->logger->debug('Skip item');

                continue;
            }
            /*DAY 11 - 17 DEC
            Charlotte Amalie
            Arrival 07:00 Departure 17:00*/
            /*GIORNO 3 - 23 MAR
            Road Town
            Arrivo 10:00 Partenza 19:00*/
            $this->logger->debug($box);

            if (preg_match('/(?:DAY|GIORNO) \d+ - (?<date>\d+ \w+|\w+ \d+)\s+(?<port>.+?)\s+(?:Arrival|Departure|Arrivo|Partenza)/s', $box, $matches)) {
                $this->logger->debug(var_export($matches, true));

                if (isset($date)) {
                    $prevDate = $date;
                }
                $matches['date'] = preg_replace("/(\w+) (\d+)/", '$2 $1', $matches['date']);
                $date = $this->dateStringToEnglish($matches['date'], $this->lang);
                $date = strtotime($date, $dateStart);

                if (isset($prevDate)) {
                    if ($prevDate > $date) {
                        $date = strtotime('+1 year', $date);
                    }
                }
                $s = $c->addSegment();
                $s->setName($matches['port']);
            } else {
                $this->logger->notice('Skip item: not segment');
            }

            if (isset($s) && ($time = $this->http->FindPreg('/(?:Arrival|Arrivo) (\d+:\d+(?:\s*[AP]M)?)/', false, $box))) {
                $s->setAshore(strtotime($time, $date));
            }

            if (isset($s) && ($time = $this->http->FindPreg('/(?:Departure|Partenza) (\d+:\d+(?:\s*[AP]M)?)/', false, $box))) {
                $s->setAboard(strtotime($time, $date));
            }

            if ($next = $selenium->waitForElement(WebDriverBy::xpath("//ul[@class='my-itinerary__list']/li[contains(@class,'my-itinerary__box')]/following-sibling::li[contains(@class,'my-itinerary__arrow') and not(contains(@class,'disabled'))][last()]"), 0)) {
                $next->click();
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
            } else {
                break;
            }
        }

        $urlCruises = $this->domain . "/my-msc/cruise-details#/cabins";

        if ($this->AccountFields['Login2'] == 'it') {
            $urlCruises = "https://www.msccrociere.it/my-area/cruise-details#/cabins";
        }
        $selenium->http->GetURL($urlCruises);

        if ($deck = $selenium->waitForElement(WebDriverBy::xpath("//li/strong[contains(text(),'Deck:') or contains(text(),'Ponte:')]/following-sibling::span"), 1)) {
            $c->details()->deck($deck->getText(), true, false);
        }

        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();

        if ($roomNumber = $this->http->FindSingleNode("//li/strong[contains(text(),'Cabin:') or contains(text(),'Cabina:')]/following-sibling::span")) {
            $c->details()->room($roomNumber);
        }

        $passengers = $this->http->FindNodes("//li[h3[contains(text(),'Passengers:') or contains(text(),'Passeggeri:')]]/following-sibling::li");

        if (count($passengers) > 0) {
            $c->general()->travellers(array_map("beautifulName", $passengers));
        }

        if ($class = $this->http->FindSingleNode("//li/strong[contains(text(),'Type:') or contains(text(),'Tipologia:')]/following-sibling::span")) {
            $c->details()->roomClass($class);
        }

        $urlCruises = $this->domain . "/my-msc/cruise-details#/price-details-payment";

        if ($this->AccountFields['Login2'] == 'it') {
            return;
            //$urlCruises = "https://www.msccrociere.it/my-msc/cruise-details#/price-details-payment";
        }
        $selenium->http->GetURL($urlCruises);
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();

        if ($total = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(),'Total booking value:')]/following-sibling::p"))) {
            $total = $this->getTotalCurrency($total->getText());
            $this->logger->debug(var_export($total, true));
            $c->price()->total($total['TotalCharge']);
            $c->price()->currency($total['Currency']);
        }
    }

    private function parseItinerary($booking, $timetable)
    {
        $this->logger->notice(__METHOD__);
        $c = $this->itinerariesMaster->add()->cruise();
        $c->general()->confirmation($booking->bookingNumber);

        if (isset($timetable->book)) {
            $book = $this->http->JsonLog($timetable->book, false);

            if (isset($book->paxes)) {
                foreach ($book->paxes as $i) {
                    $c->general()->traveller(beautifulName($i->addressData->firstName . ' ' . $i->addressData->lastName));
                }
            }

            if (isset($book->items)) {
                foreach ($book->items as $i) {
                    if (isset($i->cabinData)) {
                        if (!empty($i->cabinData->deckNumber)) {
                            $c->details()->deck($i->cabinData->deckNumber);
                        }

                        if (!empty($i->cabinData->chosenCabinNum)) {
                            $c->details()->room($i->cabinData->chosenCabinNum);
                        }

                        if (!empty($i->cabinData->categoryDesc)) {
                            $c->details()->roomClass($i->cabinData->categoryDesc);
                        }

                        break;
                    }
                }
            }
        }

        if (isset($timetable->cruise)) {
            $itinerary = $this->http->JsonLog($timetable->cruise);

            if (isset($itinerary->ship->code)) {
                $c->details()->shipCode($itinerary->ship->code);
            }

            if (isset($itinerary->ship->description)) {
                $c->details()->ship($itinerary->ship->description);
            }

            foreach ($itinerary->itinerary->segments as $i) {
                if (empty($i->city) || empty($i->code)) {
                    $this->logger->debug('Skip item');

                    continue;
                }
                $s = $c->addSegment();
                $s->setName($i->city);
                $i->date = strtotime($i->date, false);

                if (isset($i->arrivalTime)) {
                    $s->setAshore(strtotime($i->arrivalTime, $i->date));
                }

                if (isset($i->departureTime)) {
                    $s->setAboard(strtotime($i->departureTime, $i->date));
                }
            }
        }
    }

    private function dateStringToEnglish($date, $lang = 'en')
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['TotalCharge' => $tot, 'Currency' => $cur];
    }

    private function sendStaticSensorData($sensorPostUrl, $isOne = false)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            //null,
            //"",
            "7a74G7m23Vrp0o5c9166941.54-1,2,-94,-100,%user_agent%,uaend,12147,20030107,ru,Gecko,3,0,0,0,391058,3143767,1920,1050,1920,1080,1920,386,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7618,0.257801180128,794681571883,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.msccruisesusa.com/manage-booking/manage-your-booking-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1589363143766,-999999,17002,0,0,2833,0,0,5,0,0,65F7FBD78828AB5701EEBA444F97E19E~-1~YAAQul4OF3X3zMFxAQAAe/BsDQP00q581OkZws5DEXvuYP6qa0hmphq7ZbiSmdXXNcvTAl3sq2a6TW/TdyriKo1hO6lzM5vGm8KZ9k0Zi++j3wr3tpwHzIAgkuso12lz3VZ3gPtV/ZRAlA8/1BJycGTexSuNTvc91Z4TfuMOoL+NN/UoiZavFBcrwYakZ72BV7y3AL+m8qTmfItpQEvqaaCywrE1hjEH5uh2DCDlChcrTsoYUdBuJBL2QMLUugbwjaEYKj+gu9nc0E9C0lQvwUJlB12UDrt98zyBjXi4cZofDlDIGqMjTg79d1AucMiPPw==~-1~-1~-1,30661,-1,-1,30261689-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,15718700-1,2,-94,-118,78091-1,2,-94,-121,;8;-1;0",
        ];
        $sensorData2 = [
            //null,
            //"",
            "7a74G7m23Vrp0o5c9166941.54-1,2,-94,-100,%user_agent%,uaend,12147,20030107,ru,Gecko,3,0,0,0,391058,3143767,1920,1050,1920,1080,1920,386,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7618,0.867516991433,794681571883,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,0,-1,0,1,1439,1439,0;0,-1,0,1,1759,1727,0;0,-1,0,1,1643,1611,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,257,-1,-1,-1;-1,2,-94,-109,0,257,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.msccruisesusa.com/manage-booking/manage-your-booking-1,2,-94,-115,1,32,32,257,257,0,514,545,0,1589363143766,21,17002,0,0,2833,0,0,550,514,0,65F7FBD78828AB5701EEBA444F97E19E~-1~YAAQul4OF3j3zMFxAQAAyfRsDQMB+pua+1yktNh7MKD2vGPpq3ZYdnvCHdXUVQoB2S3e0xrVzpv3jP/P7LfEtvZ8feTv3zGJg/54PjNL2F4iEJAe+p+zWy1wwuji2V+dUwKR5B3uLj7DXY5zsJDNSVQtyAq+mFQvnwc12Xh59+RaNQ11RWn/OJea4maASKd1HRWGpD3QG3aSSKt+/RtChlvEBb0uUdBplG1cm7It0EvfDkfw9NJdz1jDZedK8ufiV+i+rIesh4hTh98b5ZLfIphJp60aTxNSJIgW8Sr0Vzc+cwhJWgiQQcHpD4iNAIrKzAcZVNqRHw9cc2MH+1Hd~-1~-1~-1,31507,273,1428148863,30261689-1,2,-94,-106,9,1-1,2,-94,-119,81,42,39,41,61,65,48,38,8,7,7,8,12,166,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5585-1,2,-94,-116,15718700-1,2,-94,-118,88011-1,2,-94,-121,;7;10;0",
        ];

        if (count($sensorData) != count($sensorData2)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }
        $key = array_rand($sensorData);

        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode([
            'sensor_data' => str_replace('%user_agent%', $this->http->getDefaultHeader('User-Agent'), $sensorData[$key]),
        ]), $sensorDataHeaders);

        if (!$isOne && $sensorData2[$key] !== "") {
            $this->http->PostURL($sensorPostUrl, json_encode([
                'sensor_data' => str_replace('%user_agent%', $this->http->getDefaultHeader('User-Agent'), $sensorData2[$key]),
            ]), $sensorDataHeaders);
        }
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }
}
