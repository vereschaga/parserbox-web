<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerIberia extends TAccountChecker
{
    use ProxyList;
    use PriceTools;
    use SeleniumCheckerHelper;

    public const SWITCH_TO_ENGLISH_URL = 'https://www.iberia.com/es/en/login/?referralURL=https%3A%2F%2Fwww.iberia.com%2Fes%2Fiberiaplus%2Fmy-iberia-plus%2F%23!%2FIBMIBP';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $iberiaSsoAccessToken;
    private $loyaltyCard;

    private $seleniumURL = null;
    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");

        $this->setProxyNetNut();

        $this->http->setUserAgent("Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Safari/605.1.15");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.iberia.com/us/iberiaplus/my-avios/', [], 10);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (strlen($this->AccountFields['Pass']) < 6 || $this->AccountFields['Pass'] == '❼❺❾❶❸❸') {
            throw new CheckException("The pin should have 6 characters", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL(self::SWITCH_TO_ENGLISH_URL);
        $this->checkErrors();

        // provider error -> Page Unavailable
        if ($this->http->currentUrl() == 'http://www.iberia.com/notfound9.html') {
            sleep(7);
            $this->logger->notice("Delay");
            $this->http->GetURL(self::SWITCH_TO_ENGLISH_URL);
        }

        // refs #20463
        if (!strstr($this->AccountFields['Login'], '@')) {
            $this->AccountFields['Login'] = trim(preg_replace("/(?:^IB\s*|[^[:print:]\r\n])/ims", "", $this->AccountFields['Login']));
        }
        $this->AccountFields['Pass'] = preg_replace("/^[^[:print:]]*/ims", "", $this->AccountFields['Pass']); // AccountID: 4185733

        $this->selenium();
//        if (empty($xKeys))
//            return false;

        return true;

        $this->http->Form = $form;
        $this->http->Inputs = [
            'password' => [
                'maxlength' => 6,
            ],
        ];
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("checkRemember", "true");
        // fix for next form
        $this->AccountFields['Login'] = $this->http->Form['username'];

        foreach ($xKeys as $xKey) {
            if (isset($xKey['name'], $xKey['value'])) {
                $this->http->SetInputValue($xKey['name'], $xKey['value']);
            }
        }

        return true;
    }

    public function Login()
    {
        if ($this->http->FindPreg("/DoSubmit\(document\.main\,\'https:\/\/www\.iberia\.com\/web\/changePinLogin\.do'/ims")) {
            $this->logger->notice(">>> Redirect");

            if ($this->http->ParseForm("main")) {
                sleep(1);
                $this->http->FormURL = 'https://www.iberia.com/web/changePinLogin.do';
                $this->http->PostForm();
            }// if ($this->http->ParseForm("main"))
        }// if ($this->http->FindPreg("/DoSubmit\(document\.main\,\'https:\/\/www\.iberia\.com\/web\/changePinLogin\.do'/ims"))

        if ($this->http->FindSingleNode("
                //title[contains(text(), 'Change PIN')]
                | //p[contains(text(), 'An error has occurred in CHANGE OF PIN.')]
            ")
        ) {
            throw new CheckException("Iberia Plus website is asking you to change your pin, until you do so we would not be able to retrieve your account information.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'PIN, should contain 6 characters')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'The combination of the number of Iberia Plus card and the password that you have entered is not correct')
                or contains(text(), 'La combinación de número de tarjeta Iberia Plus y PIN que has introducido no es correcta.')
            ]", null, true, '/\)?([^\(]+)/ims')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The email and password combination you have entered is incorrect.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The email and password combination you have entered is incorrect.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//input[@id = "iberia-plus"]/following-sibling::p[not(contains(@class, "hidden")) and contains(text(), "Invalid format")]')) {
            $this->DebugInfo = 'wrong login';

            throw new CheckException("The email and password combination you have entered is incorrect.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//p[@class='txtAlerta']", null, true, "/Access to the Iberia Plus personal area is currently blocked/ims")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'Access to the Iberia Plus personal area is currently blocked for the indicated number.')]
                | //h2[contains(text(), 'En estos momentos, el acceso al área personal de Iberia Plus para este usuario está bloqueado')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // Change PIN number
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Cambio de PIN') or contains(text(), 'Change PIN number')]")) {
            throw new CheckException("Iberia Plus website is asking you to change your pin, until you do so we would not be able to retrieve your account information.", ACCOUNT_INVALID_PASSWORD);
        }
        // Reset password
        $this->logger->debug("[Selenium CurrentURL]: {$this->seleniumURL}");

        if ($this->http->FindPreg('/https:\/\/www\.iberia\.com\/\w{2}\/iberiaplus\/reset-password\/\#\!\/IBCAPS/', false, $this->seleniumURL)) {
            throw new CheckException("Iberia Plus website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_INVALID_PASSWORD);
        }

        // Maintenance
        if ($message = $this->http->FindPreg("/(Debido a la incorporación de nuevas mejoras, los servicios Iberia Plus se encuentran inactivos en estos momentos[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (strstr($this->http->currentUrl(), 'https://www.iberia.com/web/postloginhome.do') || $this->http->ParseForm("registroForm")
            || $this->http->FindSingleNode("//a[contains(text(), 'Salir')]")
            || $this->http->currentUrl() == 'https://www.iberia.com/us/?language=en&channel=COM'
            || $this->http->currentUrl() == 'https://www.iberia.com/us/?language=es&channel=COM'
        ) {
            $this->logger->notice("Force switch to English");
            $this->http->GetURL(self::SWITCH_TO_ENGLISH_URL);

            $this->forceSwitchToEnglish();
        }
        //# Access is allowed
        $avios = $this->http->FindSingleNode('//span[@id = "loggedUserAvios" and normalize-space(text()) != ""]', null, false);

        if (
            $this->http->FindSingleNode("//a[contains(@href, 'logoff')]/@href")
            || $this->http->FindSingleNode("//p[contains(text(), 'Member since:')]")
            || $this->http->FindSingleNode("//span[contains(@class, 'inte-loggedUsernameZero') and contains(@class, 'be-hea')]")
            || (isset($avios) && $avios !== '')
        ) {
            // refs #13486
            /*
            $this->http->SetProxy(null);
            $this->logger->notice("Current proxy -> " . $this->http->GetProxy());
            */

            // AccountID: 6168636
            if ($this->http->FindSingleNode('//div[@id = "loggedUser-Zero" and not(contains(@class, "hidden"))]//a[contains(text(), "Register with Iberia Plus")]')) {
                $this->SetWarning(self::NOT_MEMBER_MSG);

                return true;
            }

            return $this->loginSuccessful();
        }

        if ($message = $this->http->FindSingleNode('
                //p[contains(@class, "paragraph__regular--modal-claim")]
                | //div[contains(@class, "errorDiv") and not(contains(@class, "hide")) and not(contains(@style, "display: none"))]/label
                | //div[@id = "userErrorController" and not(@style="display: none")]/label
            ')
            ?? $this->http->FindSingleNode('//label[@id = "userErrorLabel"]')
        ) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'The Iberia Plus card number and password combination you have entered are incorrect.')
                || strstr($message, 'The Iberia plus number and password combination you entered is not correct.')
                || strstr($message, 'Sorry, you can\'t use the email entered. Please try again with your Iberia Plus number.')
                || strstr($message, 'Oops. You can\'t sign in to your Iberia Plus account. Please contact Customer Services.')
                || strstr($message, 'La combinación de número de Iberia Plus y contraseña que has introducido no es correcta.')
                || strstr($message, 'La combinación de correo electrónico y contraseña que has introducido no es correcta.')
                || strstr($message, 'Alguno de los datos no es válido. Por favor, comprueba que los hayas introducido correctamente y')
                || strstr($message, 'No hemos podido conectarte. Es posible')
                || strstr($message, 'Login has failed. Some of the details you entered may be incorrect, or the email might not be registered. Please try to log in using your Iberia Plus number or create a new password. If the problem persists, you can try signing up')
                || $message == 'El formato no es válido'
                || $message == 'Invalid format'
                || $message == 'Formato incorrecto'
                || $message == 'Tu contraseña ha caducado. Debes crear una nueva.'
                || $message == 'Your password has expired. You must create a new one.'
                || $message == 'Este número de Iberia Plus no se corresponde con ningún usuario. Por favor, inténtalo con tu e-mail de acceso.'
                || $message == 'This Iberia Plus number does not correspond to any user. Please, try it with your login email.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Sorry, an error has occurred. Please try again later.')) {
                $retryCount = 3;

                if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                    $retryCount = 2;
                }

                throw new CheckRetryNeededException($retryCount, 3, $message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Access to the Iberia Plus personal area is currently blocked for this user.")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // Incorrect pass
        if (
            $this->http->FindSingleNode("//input[@id='pin-number' and @class='form-text required error']/@name")
            || $this->http->FindSingleNode('//p[contains(text(), "If you\'ve forgotten your password, we\'ll have to assign you a new one.")]')
        ) {
            throw new CheckException('The Iberia Plus card number and password combination you have entered are incorrect. Please check the Iberia Plus number indicated and enter the correct password again.', ACCOUNT_INVALID_PASSWORD);
        }

        // Restricted access
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Restricted access')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An error has occurred in the Login.
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'An error has occurred in the Login.')
                or contains(text(), 'Se ha producido un error en el Login.')
                or (normalize-space(text()) = 'An error has occurred')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[CurrentURL]: {$currentUrl}");

        if (
            $this->http->FindSingleNode("//p[contains(text(), 'Se ha producido un intento de acceso no permitido a la')]")
            || $this->http->FindSingleNode("//strong[contains(text(), 'Expired session')]")
            || $currentUrl == 'https://www.iberia.com/'
            || $this->seleniumURL == 'https://www.iberia.com/web/obsmenu.do?language=en&country=US&quadrigam=IBMITA&quickaccess=true'
        ) {
            throw new CheckRetryNeededException(3, 7);
        }

        // AccountID: 5837014
        if ($this->AccountFields['Login'] === '3081031792133853') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->ErrorCode == ACCOUNT_WARNING) {
            return;
        }

        $response = $this->http->JsonLog(null, 0);

        if (!isset($response->memberIdentifier->identifier)) {
            return;
        }
        // Name
        $this->SetProperty("Name", beautifulName(($response->person->personName->firstName ?? null) . " " . ($response->person->personName->familyName ?? null)));
        // Card Number
        $this->loyaltyCard = $response->memberIdentifier->identifier;
        $this->SetProperty("Number", ltrim($this->loyaltyCard, '0'));

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://ibisservices.iberia.com/api/agl/v1/members/{$this->loyaltyCard}/programmes/IBP/schemes/accounts", [
            'Authorization' => 'Bearer ' . $this->iberiaSsoAccessToken,
        ]);
        $this->http->RetryCount = 2;

        // provider bug fix
        if (strstr($this->http->Error, 'Network error 28 - Operation timed out after')) {
            throw new CheckRetryNeededException();
        }

        $response = $this->http->JsonLog();
        $accounts = $response->accounts;

        foreach ($accounts as $account) {
            /*
            // Elite Points since you signed up to the programme
            if ($account->accountType == 'LIFETIME_RECOGNITION') {
                $this->SetProperty('LifetimeElitePoints', number_format($account->balance->amount));
                break;
            }
            */
            // Balance - Avios balance
            if ($account->accountType == 'TOTAL_REWARD') {
                $this->SetBalance($account->balance->amount);

                break;
            }
        }// foreach ($accounts as $account)

        $this->http->GetURL("https://ibisservices.iberia.com/api/agl/v2/members/{$this->loyaltyCard}/programmes/IBP/schemes/BASIC/progress-trackers", [
            'Authorization' => 'Bearer ' . $this->iberiaSsoAccessToken,
        ]);
        $response = $this->http->JsonLog();
        $progressTrackers = $response->progressTrackers ?? [];

        foreach ($progressTrackers as $progressTracker) {
            switch ($progressTracker->name) {
                // Elite Points since you signed up to the programme
                case 'Lifetime tier points':
                    $this->SetProperty('LifetimeElitePoints', number_format($this->http->FindPreg("/:\s*(.+)/", false, $progressTracker->trackerAttributes->trackerCurrent[0]->text)));

                    break;
                // Elite Points
                case "Tier points in current qualification period":
                    $this->SetProperty('ElitePoints', number_format($this->http->FindPreg("/:\s*(.+)/", false, $progressTracker->trackerAttributes->trackerCurrent[0]->text)));

                    break;
                // Flights
                case "Flights in current qualification period":
                    $this->SetProperty('Flights', number_format($this->http->FindPreg("/:\s*(.+)/", false, $progressTracker->trackerAttributes->trackerCurrent[0]->text)));

                    break;
            }
        }

        // Level, CardExpiry
        $this->http->GetURL("https://ibisservices.iberia.com/api/agl/v2/members/{$this->loyaltyCard}/programmes/IBP/schemes/recognition-levels", [
            'Authorization' => 'Bearer ' . $this->iberiaSsoAccessToken,
        ]);
        $response = $this->http->JsonLog();

        if (isset($response->schemes)) {
            if (count($response->schemes) > 1) {
                $this->sendNotification('check schemes > 1 // MI', 'awardwallet');
            }

            foreach ($response->schemes as $scheme) {
                if (isset($scheme->recognitionLevel->expiryDate)) {
                    break;
                }
            }
        }

        // Card level
        $this->SetProperty("Level", beautifulName($scheme->recognitionLevel->recognitionLevelIdentifier->identifier ?? null));
        // Expiry date
        if (isset($scheme->recognitionLevel->expiryDate)) {
            $this->SetProperty("CardExpiry", date('d/m/Y', strtotime($scheme->recognitionLevel->expiryDate)));
        }

        // Since
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://ibisservices.iberia.com/api/agl/v4/programmes/IBP/members/{$this->loyaltyCard}", [
            'Authorization' => 'Bearer ' . $this->iberiaSsoAccessToken,
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Date of joining - 2014-09-28
        if (isset($response->enrolmentDate)) {
            $this->SetProperty("Since", date('Y-m-d', strtotime($response->enrolmentDate)));
        }

        // Expiration date, refs #12865
        /*
        $this->getExpDate();
        */
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        // now all itins are parsed on Spanish site
        $result = [];

        // watchdog workaround
        $this->increaseTimeLimit();

        $this->http->GetURL('https://www.iberia.com/us/manage-my-booking/');

        /*if ($asset = $this->http->FindPreg('# src="([^\"]+)"></script></body>#')) {
            $sensorPostUrl = "https://www.iberia.com{$asset}";
            $this->http->NormalizeURL($sensorPostUrl);
            sleep(1);
            $this->sendStaticSensorDataNew($sensorPostUrl);
            sleep(1);
        }
        $this->http->setCookie('_abck', '6C2EE31B1116B96724599EC2EC0F4AD7~0~YAAQyEA2F0w5wj2LAQAAmT3zRgoiAlrXbCqvKttqdCalvBqqM7I1i2DVpLxOEXML/E6FufdSjQVMSafwYBe+FtODWK7hl8WVdD9166oQmUF3DGCpzoaT/1NEBgWDu4kooVQ3PI4Ae+UNmviu2cmnGyik6LbXjl7jxGb9gdTVRLB9HxccGmSFZmPlSahVj6xj1Wz6EGFIJEJb7ultwrtRhZPpdmXjzvaAmTwzKhQgvIHhxtwOr0IpShKVZ1BZUHz3SO+l4a9V8RjT/p4awodA09ITrF6QActYWIHPUStJG9uljU4o5ZqqDFtBYAL/gMQ4QQRmHQfS/ATgAQ+Hj7MfTvaTlbYECeG65IhtUSznxUT6T1WwcQ6UW6MSligiQlMeZ9CSwXdMQS+1iY1BKfZy+KIWG07TLXI=~-1~||-1||~-1', '.iberia.com');*/

        $headers = [
            'Authorization' => 'Bearer ' . $this->iberiaSsoAccessToken,
        ];
        $this->http->GetURL('https://ibisservices.iberia.com/api/sse-orm/rs/v2/order/bookings', $headers);
        $itineraries = $this->http->JsonLog() ?? [];

        $error = $itineraries->errors[0]->reason ?? null;
        $this->logger->error($error);

        if (
            in_array($error, [
                "Bookings not found with IberiaPlus user",
                "No se han encontrado reservas asociadas al usuario IberiaPlus",
                "Il n'existe aucune réservation associée à votre carte",
            ])
        ) {
            return $this->noItinerariesArr();
        }

        $this->logger->info(sprintf('Total %s itineraries were found', (is_array($itineraries) || ($itineraries instanceof Countable) ? count($itineraries) : 0)));

        foreach ($itineraries as $itinerary) {
            $conf = $itinerary->locator ?? null;
            $this->http->RetryCount = 1;

            if (empty($conf)) {
                $this->logger->error('Skip: empty ConfNo');

                continue;
            }
            // from CheckConfirmationNumberInternal
            $arFields = [
                'LastName' => $itinerary->surname,
                'ConfNo'   => $conf,
            ];
            $browser = clone $this;
            $browser->http->setHttp2(true);
            $itin = [];

            sleep(1);
            $error = $browser->CheckConfirmationNumberInternalGet($arFields, $itin, $this->iberiaSsoAccessToken);

            if ($error) {
                $this->logger->error("Skipping itinerary: {$error}");
            } elseif (!empty($itin)) {
                if ($this->currentItin >= 5) {
                    $this->sendNotification('success it // MI');
                }
                $result[] = $itin;
            }
        }// foreach ($itineraries as $itinerary)

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.iberia.com/us/manage-my-booking/?language=en&market=us&channel=COM#!/mytrps";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->setRandomUserAgent();
        $accesstoken = $this->getCookiesFromSelenium($arFields);

//        $headers = [
//            'Accept'        => 'application/json, text/plain, */*',
//            'Content-Type'  => 'application/x-www-form-urlencoded; charset=UTF-8',
//            'Authorization' => 'Basic aWJlcmlhX3dlYjo5ZGM4NzZjYi0xMDVkLTQ4MWItODM4Yy01NGUyNGQ3NDEwYzk=',
//            'Referer'       => 'https://www.iberia.com/',
//            'Origin'        => 'https://www.iberia.com',
//        ];
//        $this->http->setHttp2(true);
//        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
//        $this->http->RetryCount = 0;
//        $this->http->PostURL('https://ibisauth.iberia.com/api/auth/realms/commercial_platform/protocol/openid-connect/token',
//            ['grant_type' => 'client_credentials'], $headers, 30);
//        $response = $this->http->JsonLog();
//        $this->http->RetryCount = 2;

        if (empty($accesstoken)) {
            return null;
        }

        return $this->CheckConfirmationNumberInternalGet($arFields, $it, $accesstoken);

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->http->setCookie('_abck', '', '.iberia.com');

        if ($asset = $this->http->FindPreg('# src="([^\"]+)"></script></body>#')) {
            $sensorPostUrl = "https://www.iberia.com{$asset}";
            $this->http->NormalizeURL($sensorPostUrl);
//            $this->sendStaticSensorDataNew($sensorPostUrl);

            $sensorData = [
                // 0
                '',
                // 2
            ];
            $sensorData2 = [
                // 0
                '',
                // 1
                // 2
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
            $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
            $this->http->JsonLog();
            sleep(1);
            $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData2[$key]]), $sensorDataHeaders);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();
            sleep(1);

//            $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => "7a74G7m23Vrp0o5c9295131.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:93.0) Gecko/20100101 Firefox/93.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,402412,8944312,1536,871,1536,960,1536,436,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6008,0.753613589376,817754472155.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,0,0,0,0,-1,-1,0;0,-1,0,0,967,-1,0;0,-1,0,1,2315,447,0;1,-1,0,1,2089,327,0;0,-1,0,1,2314,763,0;0,-1,0,1,2015,336,0;0,-1,0,1,1582,447,0;1,-1,0,1,1356,327,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,25;-1,2,-94,-112,https://www.iberia.com/us/manage-my-booking/?language=en&market=us&channel=COM#!/chktrp-1,2,-94,-115,1,32,32,0,0,0,0,1691,0,1635508944311,5,17496,0,0,2916,0,0,1691,0,0,E109C5ED1221F9CA3A5D19ACC803F4E1~-1~YAAQJ9cwFynpm8p8AQAANv/tywbFy7ADeKbpP4FLYNzA7zg5as7aP1pEvno85n6ge9Er7xTtf9jr6lKSAGQG+7paVcEIDksd0ejS+AJdxFqhY4xy5HKTEvpl2pRRu2LJ3SYV8pumQ37qfNSmHfl1398hKXVIEdX5ipkCvT6BPLBmTO2jrHQyIkMeBy71Uvc3MKW/q0xUrtfx7RlXI5G3jyZgYOd8P2qhBlUuHpwzEufQlmC3o2JMvU4VZxOiWkYOrkJ9od+kqe/T3k6aoOkWGSOkhxxLbXbuKZV3WnkVfzifcnCXJuDFLlUPF9uSucTn4Wk/X5OJ9kD5b9nL8S1F48NyV6oCckmKX+n/LxhrE16xpJ5x/xwurN9d0vVb84zeVidc99tTG//xeTs=~-1~||1-dRRHxysMqp-1-10-1000-2||~-1,39381,923,-488543131,26067385,PiZtE,107953,42,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,0,0,0,0,0,200,0,200,0,200,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.c45ab3abbecb78,0.e7e303c69276c8,0.3d255c0476559,0.62010a91fe8d18,0.dcae7356752f88,0.fdfdc51d50253,0.d667723b94c8e8,0.431d19774090f,0.9fb2ebd44a35d8,0.23759b474e1098;0,0,0,0,1,1,0,1,0,0;0,0,4,1,11,3,1,11,3,12;E109C5ED1221F9CA3A5D19ACC803F4E1,1635508944311,dRRHxysMqp,E109C5ED1221F9CA3A5D19ACC803F4E11635508944311dRRHxysMqp,1,1,0.c45ab3abbecb78,E109C5ED1221F9CA3A5D19ACC803F4E11635508944311dRRHxysMqp10.c45ab3abbecb78,138,45,175,90,13,61,85,107,61,42,170,161,116,244,25,176,188,134,154,182,250,233,101,117,87,219,135,47,101,8,85,185,1488,0,1635508946002;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,670823025-1,2,-94,-118,132644-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) HD Graphics 400,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;2;5;0"]), $sensorDataHeaders);
//            $this->http->RetryCount = 2;
//            $this->http->JsonLog();
//            sleep(1);
//            $selenium = clone $this;
//            $this->http->brotherBrowser($selenium->http);
//
//            try {
//                $this->logger->notice("Running Selenium...");
//                $selenium->UseSelenium();
//                $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
//                $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
//                $selenium->disableImages();
//                $selenium->http->start();
//                $selenium->Start();
//                $selenium->http->GetURL($this->ConfirmationNumberURL($arFields));
//                $selenium->waitForElement(WebDriverBy::id('mmb-search-anonymous-login-form'), 5);
//
//                sleep(3);
//
//                $name = $selenium->waitForElement(WebDriverBy::id("ANONYMOUS_LOGIN_INPUT_SURNAME"), 10);
//                $this->savePageToLogs($selenium);
//                $confNo = $selenium->waitForElement(WebDriverBy::id("ANONYMOUS_LOGIN_INPUT_PNR"), 0);
//                $signInButton = $selenium->waitForElement(WebDriverBy::id("ANONYMOUS_LOGIN_BOTON"), 0);
//
//                if (!$confNo || !$name || !$signInButton) {
//                    return $this->checkErrors();
//                }
//
//                $confNo->sendKeys($arFields['ConfNo']);
//                $name->sendKeys($arFields['LastName']);
//
//                $selenium->driver->executeScript('
//                    const constantMock = window.fetch;
//                    window.fetch = function() {
//                        console.log(arguments);
//                        return new Promise((resolve, reject) => {
//                            constantMock.apply(this, arguments)
//                                .then((response) => {
//                                    if (response.url.indexOf("v2/order/import") > -1) {
//                                        response
//                                        .clone()
//                                        .json()
//                                        .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
//                                    }
//                                    resolve(response);
//                                })
//                                .catch((error) => {
//                                    reject(response);
//                                })
//                        });
//                    }
//                ');
//
//                $signInButton->click();
//
//                $selenium->waitForElement(WebDriverBy::xpath("
//                    //h1[contains(text(), 'Your trip to')]
//                "), 20);
//                $this->savePageToLogs($selenium);
//
//                $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
//                $this->logger->info("[Form responseData]: '" . $responseData . "'");
//
//                $this->http->JsonLog($responseData);
//
////                $cookies = $selenium->driver->manage()->getCookies();
////
////                foreach ($cookies as $cookie) {
////                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
////                }
//
//                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
//                $this->http->SaveResponse();
//
//                return false;
//            } finally {
//                // close Selenium browser
//                $selenium->http->cleanup();
//            }
        }
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Authorization' => 'Basic aWJlcmlhX3dlYjo5ZGM4NzZjYi0xMDVkLTQ4MWItODM4Yy01NGUyNGQ3NDEwYzk=',
        ];
        $this->http->PostURL('https://ibisauth.iberia.com/api/auth/realms/commercial_platform/protocol/openid-connect/token',
            ['grant_type' => 'client_credentials'], $headers);
        $response = $this->http->JsonLog();

        return $this->CheckConfirmationNumberInternalGet($arFields, $it, $response->access_token);
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation code",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Passenger surname",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    // ==== History ====

    public function GetHistoryColumns()
    {
        return [
            'Date'         => 'PostingDate',
            'Description'  => 'Description',
            'Sector'       => 'Info',
            'Avios'        => 'Miles',
            'Elite Points' => 'Info',
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $startTimer = $this->getTime();
        $result = [];

        $this->logger->debug('[History start date: ' . (isset($startDate) ? date('Y-m-d H:i:s', $startDate) : 'all') . ']');
        $this->http->RetryCount = 0;

        $this->http->GetURL("https://ibisservices.iberia.com/api/agl/v2/members/{$this->loyaltyCard}/programmes/IBP/schemes/accounts/transactions", [
            'Authorization' => 'Bearer ' . $this->iberiaSsoAccessToken,
        ]);
        $this->http->RetryCount = 2;
        $rows = $this->http->JsonLog(null, 3);

        if (!isset($rows->transactions)) {
            return $result;
        }

        $page = 0;
        $this->logger->debug("[Page: {$page}]");
        $result = array_merge($result, $this->ParsePageHistory($startDate, $rows->transactions));

        // Sort by date
        usort($result, function ($a, $b) {
            $key = 'Date';

            return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
        });

        $this->getTime($startTimer);

        return $result;
    }

    private function getCookiesFromSelenium($arFields)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromePuppeteer();
            $selenium->seleniumOptions->userAgent = null;

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 5;
            $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if (isset($fingerprint)) {
                $selenium->http->setUserAgent($fingerprint->getUseragent());
                $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            }

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL($this->ConfirmationNumberURL($arFields));
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            sleep(3);
            $pnr = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'ANONYMOUS_LOGIN_INPUT_PNR']"), 10);
            $pnrLabel = $selenium->waitForElement(WebDriverBy::xpath("//label[@for = 'ANONYMOUS_LOGIN_INPUT_PNR']"), 0);
            $suName = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'ANONYMOUS_LOGIN_INPUT_SURNAME']"), 0);
            $suNameLabel = $selenium->waitForElement(WebDriverBy::xpath("//label[@for = 'ANONYMOUS_LOGIN_INPUT_SURNAME']"), 0);

            $this->savePageToLogs($selenium);

            if (!$pnr || !$suName) {
                $this->logger->error('Failed to find login button');

                return false;
            }
            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->duration = rand(300, 1000);
            $mover->steps = rand(200, 300);

            $this->logger->debug("set login");

            $pnrLabel->click();
            $mover->click();
            $mover->sendKeys($pnr, $arFields['ConfNo'], 7);
            $suNameLabel->click();
            $mover->click();
            $mover->sendKeys($suName, $arFields['LastName'], 7);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            $anonymousToken = $selenium->driver->executeScript("return sessionStorage.getItem('ib-MmB-app.anonymousToken');");
            $this->logger->info("[Form responseData]: '" . $anonymousToken . "'");

            return str_replace('"', '', $anonymousToken);
        } catch (
        NoSuchDriverException
        | Facebook\WebDriver\Exception\InvalidSessionIdException
        | Facebook\WebDriver\Exception\UnrecognizedExceptionException
        | Facebook\WebDriver\Exception\WebDriverCurlException
        $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }

    private function CheckConfirmationNumberInternalGet($arFields, &$it, $accessToken): ?string
    {
        $headers = [
            'Accept'                      => 'application/json, text/plain, */*',
            'Accept-Encoding'             => 'gzip, deflate, br',
            'Accept-Language'             => 'en-US',
            'Authorization'               => "Bearer {$accessToken}",
            'Content-Type'                => 'application/json;charset=UTF-8',
            'Origin'                      => 'https://www.iberia.com',
            'Referer'                     => 'https://www.iberia.com/',
            'X-Observations-Current-Page' => 'null',
            'X-Observations-Origin-Page'  => 'null',
            'X-Request-Appversion'        => '10.33.1',
            'X-Request-Device'            => 'unknown|chrome|122.0.0.0',
            'X-Request-Osversion'         => 'linux|unknown',
        ];
        $data = json_encode([
            'locator' => $arFields['ConfNo'],
            'surname' => $arFields['LastName'],
        ]);
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://ibisservices.iberia.com/api/sse-orm/rs/v2/order/import', $data, $headers);
        $this->http->RetryCount = 2;

        // it helps
        if ($this->http->Response['code'] == 503) {
            sleep(5);
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://ibisservices.iberia.com/api/sse-orm/rs/v2/order/import', $data, $headers);
            $this->http->RetryCount = 2;
        }

        if ($this->http->Response['code'] == 403) {
            return null;
        }
        $data = $this->http->JsonLog();

        if (isset($data->errors[0]->reason)) {
            return $data->errors[0]->reason;
        }
        $this->ParseItineraryJson($data);

        return null;
    }

    private function getExpDate()
    {
        $this->logger->notice(__METHOD__);

        if ($this->Balance <= 0) {
            return;
        }

        $this->logger->info('Expiration date', ['Header' => 3]);

        $history = $this->ParseHistory();

        foreach ($history as $historyItem) {
            if ($historyItem['Date'] > strtotime("+1 day")) {
                $this->sendNotification("Future transaction: there is bug! ({$historyItem['Date']} / " . time() . ") - refs #12865 // Info for PT");
            }

            if (
                $historyItem['Avios'] == 0
                || stristr($historyItem['Description'], 'CADUCIDAD AVIOS POR INACTIVIDAD')
                || stristr($historyItem['Description'], 'Combinar mis Avios')
                || stristr($historyItem['Description'], 'Combine my Avios')
                || stristr($historyItem['Description'], 'Anulación reserva de vuelo con Avios')
                || stristr($historyItem['Description'], 'Anulación reserva con Avios del vuelo') // https://redmine.awardwallet.com/issues/12865#note-41
                || stristr($historyItem['Description'], 'ANULADO')
                || stristr($historyItem['Description'], 'TRANSFERS')
                || stristr($historyItem['Description'], 'TRANSFERENCIA')
            ) {
                $this->logger->notice("skip transaction:");
                $this->logger->notice(var_export($historyItem, true), ['pre' => true]);

                continue;
            }

            if (
                stristr($historyItem['Description'], 'combine')
                || (stristr($historyItem['Description'], 'cancel') && !stristr($historyItem['Description'], 'CANCELACIÓN BONUS TARJETA ELITE'))
                || stristr($historyItem['Description'], 'inactivity')
            ) {
                $this->sendNotification("[{$historyItem['Description']}] - refs #12865 // Info for PT");
            }

            $this->SetProperty("LastActivity", date("d/m/Y", $historyItem['Date']));

            $this->SetExpirationDate(strtotime("+36 month", $historyItem['Date']));

            break;
        }// foreach ($this->history as $historyItem)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->iberiaSsoAccessToken = $this->http->getCookieByName('IBERIACOM_SSO_ACCESS');

        foreach (explode('.', $this->iberiaSsoAccessToken) as $str) {
            $str = base64_decode($str);
            $this->logger->debug($str);

            if ($sub = $this->http->FindPreg('/"sub":"(.+?)"/', false, $str)) {
                break;
            }
        }

        if (!isset($sub)) {
            $this->logger->error("userId not found");

            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://ibisservices.iberia.com/api/agl/v4/programmes/IBP/members/{$sub}", [
            'Authorization' => 'Bearer ' . $this->iberiaSsoAccessToken,
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $this->logger->debug("[login]: {$this->AccountFields['Login']}");
        $ibPlusNumber = $response->memberIdentifier->identifier ?? null;
        $this->logger->debug("[number]: {$ibPlusNumber}");
        $this->logger->debug("[number filtered]: " . intval($ibPlusNumber));
        $contactEmail = $response->person->emailAddress->email ?? null;
        $this->logger->debug("[email]: {$contactEmail}");

        if (
            isset($ibPlusNumber)
            && (
                ($ibPlusNumber == $this->AccountFields['Login'] || intval($ibPlusNumber) == $this->AccountFields['Login'])
                || (strtolower($contactEmail) == strtolower($this->AccountFields['Login']))
                || in_array($this->AccountFields['Login'], [
                    'jpleao30@gmail.com',
                    'ale_ked@hotmail.com',
                    'felix2004felix@hotmail.com',
                    'werdamcelso@gmail.com',
                    'gducrey@gmail.com',
                    'belfam4@roadrunner.com',
                    'tharps22@gmail.com',
                ])
            )
        ) {
            return true;
        }

        return false;
    }

    private function forceSwitchToEnglish()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//li[contains(text(), 'Hola')]/span")
            || $this->http->FindSingleNode("//a[contains(text(), 'Salir')]")) {
            sleep(3);
            $this->logger->notice("Force switch to English");
            $this->http->GetURL(self::SWITCH_TO_ENGLISH_URL, [], 10);
        }
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->usePacFile(false);

            /*
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
            */
            $selenium->useChromePuppeteer();

            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            try {
                $selenium->http->GetURL("https://www.iberia.com/?language=en");
                sleep(random_int(1, 5));
                $selenium->http->GetURL("https://www.iberia.com/integration/ibplus/login/");
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("Facebook\WebDriver\Exception\UnknownErrorException: " . $e->getMessage(), ['pre' => true]);
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage(), ['pre' => true]);
                sleep(5);
                $this->savePageToLogs($selenium);
            }

            $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "loginPage:theForm:loginEmailInput"] | //input[@id = "iberia-plus"] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "The connection was interrupted due to an error,")] | //span[contains(text(), "This site can") or contains(text(), "t be reached")] | //button[@id = "onetrust-accept-btn-handler"]'), 10);

            if ($accept = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "onetrust-accept-btn-handler"] | //button[@name = "acceptCookie"]'), 5)) {
                $accept->click();

                $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "loginPage:theForm:loginEmailInput"] | //input[@id = "iberia-plus"] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "The connection was interrupted due to an error,")] | //span[contains(text(), "This site can") or contains(text(), "t be reached")]'), 10);
                $this->savePageToLogs($selenium);
            }

            // todo: debug
            if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), "The connection was interrupted due to an error,")] | //h1[contains(text(), "Access Denied")] | //span[contains(text(), "This site can") or contains(text(), "t be reached")]'), 0)) {
                $this->savePageToLogs($selenium);
                $selenium->http->GetURL("https://www.iberia.com/integration/ibplus/login/");
                $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "loginPage:theForm:loginEmailInput"] | //input[@id = "iberia-plus"] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "The connection was interrupted due to an error,")] | //span[contains(text(), "This site can") or contains(text(), "t be reached")]'), 10);
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "iberia-plus"]'), 0);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "iberia-plus-pass"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Go")]'), 0, false);

            if (!$loginInput || !$passwordInput || !$button) {
                $form = '//form[@id = "loginPage:theForm"]';
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath($form . '//input[@id = "loginPage:theForm:loginEmailInput"]'), 0);
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath($form . '//input[@id = "loginPage:theForm:loginPasswordInput"]'), 0);
                $button = $selenium->waitForElement(WebDriverBy::xpath($form . '//input[@id = "loginPage:theForm:loginSubmit"]'), 0);
            }

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("something went wrong");
                // save page to logs
                $this->savePageToLogs($selenium);

                if ($this->http->FindSingleNode('
                    //h1[contains(text(), "Access Denied")]
                    | //div[contains(text(), "The connection was interrupted due to an error, but we can take you to the homepage if you want to carry on browsing.")]
                    | //span[contains(text(), "This site can") or contains(text(), "t be reached")]
                ')) {
                    $this->markProxyAsInvalid();

                    $retry = true;
                }

                return $this->checkErrors();
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->duration = rand(300, 1000);
            $mover->steps = rand(10, 20);

            $mover->moveToElement($loginInput);
            $mover->click();
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 7);

            $mover->moveToElement($passwordInput);
            $mover->click();
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 7);
//            $mover->moveToElement($button);
//            $mover->click();
            sleep(random_int(0, 3));
            $selenium->driver->executeScript("
                if (document.getElementById('loginFormLayout')) {
                    $('#loginFormLayout button.inte-submitIBP:contains(\"Go\")').click();
                }
                else
                    document.querySelector('input[id = \"loginPage:theForm:loginSubmit\"]').click();
            ");

            $this->logger->debug("wait result");
            $result = $selenium->waitForElement(WebDriverBy::xpath('
                //h2[contains(text(), "Your Iberia Plus card - Iberia") or contains(text(), "Tu tarjeta Iberia Plus - Iberia")]
                | //div[contains(@class, "alert-with-error")]
                | //p[contains(@class, "paragraph__regular--modal-claim")]
                | //p[contains(text(), "If you\'ve forgotten your password, we\'ll have to assign you a new one.")]
                | //input[@id = "iberia-plus"]/following-sibling::p[not(contains(@class, "hidden")) and contains(text(), "Invalid format")]
                | //div[contains(@class, "errorDiv") and not(contains(@class, "hide")) and not(contains(@style, "display: none"))]/label
                | //div[contains(text(), "The connection was interrupted due to an error,")]
                | //span[contains(text(), "This site can") or contains(text(), "t be reached")]
                | //span[@id = "loggedUserAvios"]
                | //h1[contains(text(), "Access Denied")]
                | //div[@id = "userErrorController"]/label
                | //label[@id = "userErrorLabel"]
                | //a[@title="Mi Iberia"]
            '), 55);

            if ($selenium->waitForElement(WebDriverBy::xpath('//div[starts-with(@class, "ibe-loading__spinner")]'), 0)) {
                $this->DebugInfo = 'Infinite loading, retry';
                $retry = true;
            }

            // todo: debug
            if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), "The connection was interrupted due to an error,")]
             | //h1[contains(text(), "Access Denied")] | //span[contains(text(), "This site can") or contains(text(), "t be reached")]'), 0)) {
                $this->savePageToLogs($selenium);
                $selenium->http->GetURL("https://www.iberia.com/integration/ibplus/login/");

                $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "loginPage:theForm:loginEmailInput"] | //input[@id = "iberia-plus"] | //h1[contains(text(), "Access Denied")]'), 10);

                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "iberia-plus"]'), 0);
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "iberia-plus-pass"]'), 0);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Go")]'), 0, false);

                if (!$loginInput || !$passwordInput || !$button) {
                    $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "loginPage:theForm:loginEmailInput"]'), 0);
                    $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "loginPage:theForm:loginPasswordInput"]'), 0);
                    $button = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "loginPage:theForm:loginSubmit"]'), 0);
                }

                if (!$loginInput || !$passwordInput || !$button) {
                    $this->logger->error("something went wrong");
                    // save page to logs
                    $this->savePageToLogs($selenium);

                    if ($this->http->FindSingleNode('
                        //h1[contains(text(), "Access Denied")]
                        | //div[contains(text(), "The connection was interrupted due to an error, but we can take you to the homepage if you want to carry on browsing.")]
                        | //span[contains(text(), "This site can") or contains(text(), "t be reached")]
                    ')) {
                        $this->markProxyAsInvalid();
                    }

                    $retry = true;

                    return $this->checkErrors();
                }
                // save page to logs
                $this->savePageToLogs($selenium);

                $mover = new MouseMover($selenium->driver);
                $mover->logger = $this->logger;
                $mover->duration = rand(300, 1000);
                $mover->steps = rand(10, 20);

                $mover->moveToElement($loginInput);
                $mover->click();
                $mover->sendKeys($loginInput, $this->AccountFields['Login'], 7);

                $mover->moveToElement($passwordInput);
                $mover->click();
                $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 7);

                $selenium->driver->executeScript("
                    if (document.getElementById('loginFormLayout')) {
                        $('#loginFormLayout button.inte-submitIBP:contains(\"Go\")').click();
                    }
                    else
                        document.querySelector('input[id = \"loginPage:theForm:loginSubmit\"]').click();
                ");

                $this->logger->debug("wait result");
                $result = $selenium->waitForElement(WebDriverBy::xpath('
                    //h2[contains(text(), "Your Iberia Plus card - Iberia") or contains(text(), "Tu tarjeta Iberia Plus - Iberia")]
                    | //div[contains(@class, "alert-with-error")]
                    | //p[contains(@class, "paragraph__regular--modal-claim")]
                    | //p[contains(text(), "If you\'ve forgotten your password, we\'ll have to assign you a new one.")]
                    | //input[@id = "iberia-plus"]/following-sibling::p[not(contains(@class, "hidden")) and contains(text(), "Invalid format")]
                    | //div[contains(@class, "errorDiv") and not(contains(@class, "hide")) and not(contains(@style, "display: none"))]/label
                    | //div[contains(text(), "The connection was interrupted due to an error,")]
                    | //span[contains(text(), "This site can") or contains(text(), "t be reached")]
                    | //span[@id = "loggedUserAvios"]
                    | //div[@id = "userErrorController"]/label
                    | //h1[contains(text(), "Access Denied")]
                '), 55);
            }

            if ($result && $result->getText() == 'Access Denied') {
                $retry = true;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode('//span[@id = "loggedUserAvios"]')) {
                $this->markProxySuccessful();
            }

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->seleniumURL}");
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage(), ['pre' => true]);
            $this->DebugInfo = "TimeOutException";
            $retry = true;
        }// catch (ScriptTimeoutException $e)
        catch (SessionNotCreatedException | NoSuchDriverException | NoSuchWindowException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['pre' => true]);
            $this->DebugInfo = "Exception";
            $retry = true;
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return isset($result);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/In order to continue to improve our product we are currently making some modifications in iberia\.com/ims")) {
            throw new CheckException('In order to continue to improve our product we are currently making some modifications in iberia.com. We will be available in a few hours. We apologise for the inconvenience caused.', ACCOUNT_PROVIDER_ERROR);
        }
        // Online services are not available
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'At this moment, our online services are not available')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Online services are not available
        if ($message = $this->http->FindPreg("/At this moment, our online services are not available as result of the high number of accesses. Please try again in a few minutes/ims")) {
            throw new CheckException('Iberia Plus website had a hiccup, please try to check your balance at a later time.', ACCOUNT_PROVIDER_ERROR);
        }
        //# Online services are not available
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'our online services are not available')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Due to a problem with our systems we can not offer online services temporarily
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to a problem with our systems we can not offer online services temporarily')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The page you have requested is not available
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The page you have requested is not available')]")) {
            throw new CheckRetryNeededException(3, 3, $message);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // At this moment, our online services are not available
        if ($this->http->currentUrl() == self::SWITCH_TO_ENGLISH_URL && $this->http->Response['code'] == 503) {
            throw new CheckException('At this moment, our online services are not available as result of the high number of accesses. Please try again in a few minutes. We apologize for the inconvenience, thank you very much.', ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // An error occurred while processing your request.
            ($this->http->FindPreg("/An error occurred while processing your request./") && $this->http->Response['code'] == 504)
            || $this->http->FindSingleNode("//h1[contains(text(), 'Gateway Timeout')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function ParseItineraryJson($data)
    {
        $this->logger->notice(__METHOD__);

        $f = $this->itinerariesMaster->add()->flight();
        $conf = $data->order->bookingReferences[0]->reference;
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        $f->general()->confirmation($conf);

        foreach ($data->passengers as $passenger) {
            $f->general()->traveller(beautifulName("{$passenger->personalInfo->name} {$passenger->personalInfo->surname}"));
        }

        if (isset($data->tickets)) {
            $ticketsArr = [];

            foreach ($data->tickets as $tickets) {
                foreach ($tickets->ticketNumbers as $ticket) {
                    $this->logger->debug(var_export($ticket, true));
                    $ticketsArr[] = $ticket;
                }
            }
            $this->logger->debug(var_export($ticketsArr, true));

            $f->issued()->tickets(array_unique($ticketsArr), false);
        }

        foreach ($data->order->slices as $slice) {
            foreach ($slice->segments as $seg) {
                $s = $f->addSegment();
                $s->airline()->name(!empty($seg->flight->operationalCarrier->code) ? $seg->flight->operationalCarrier->code : $seg->flight->marketingCarrier->code);
                $s->airline()->number(!empty($seg->flight->operationalFlightNumber) ? $seg->flight->operationalFlightNumber : $seg->flight->marketingFlightNumber);
                $s->airline()->operator($seg->flight->operationalCarrier->name);

                $s->departure()->name($seg->departure->name);
                $s->departure()->code($seg->departure->code);
                $s->departure()->date2($seg->departureDateTime);
                $s->arrival()->name($seg->arrival->name);
                $s->arrival()->code($seg->arrival->code);
                $s->arrival()->date2($seg->arrivalDateTime);
                $s->extra()->cabin($seg->cabin->type, false, true);
                $s->extra()->bookingCode($seg->cabin->code, false, true);
                $s->extra()->aircraft($seg->flight->aircraft->description ?? null, false, true);
                $s->extra()->duration($this->convertMinsToHrsMins($seg->duration));

                $seats = [];

                foreach ($data->order->orderItems as $seat) {
                    if ($seat->type === 'seat' && $seg->id === $seat->segmentId) {
                        $seats[] = $seat->row . $seat->column;
                    }
                }
                $s->extra()->seats($seats);
            }
        }

        if (isset($data->order->price->total) && !empty($data->order->price->currency)) {
            $f->price()->total($data->order->price->total);
            $f->price()->currency($data->order->price->currency);
            $f->price()->tax($data->order->price->fare);
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function convertMinsToHrsMins($mins)
    {
        $h = floor($mins / 60);
        $m = round($mins % 60);
        $h = ($h < 10) ? ('0' . $h) : ($h);
        $m = ($m < 10) ? ('0' . $m) : ($m);

        return "{$h}:{$m}";
    }

    private function sendStaticSensorDataNew($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            "7a74G7m23Vrp0o5c9294571.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.71 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,402354,2490296,1920,1050,1920,1080,1920,572,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7566,0.16889714684,817636245148,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,0,0,0,0,-1,-1,0;0,-1,0,0,967,-1,0;0,-1,0,1,2315,447,0;1,-1,0,1,2089,327,0;0,-1,0,1,2314,763,0;0,-1,0,1,2015,336,0;0,-1,0,1,1582,447,0;1,-1,0,1,1356,327,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,42,-1,-1,-1;-1,2,-94,-109,0,41,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.iberia.com/us/manage-my-booking/?language=en&market=us&channel=COM#!/chktrp-1,2,-94,-115,1,32,32,42,41,0,83,1334,0,1635272490296,32,17493,0,0,2915,0,0,1335,83,0,A003836C2B92998B8F8C6DDE91A0FFDA~-1~YAAQL1oDF3SflK58AQAA7P3VvQbj5ory2ArhhJlLml/y6m5WCtoPXut/rmaJKiBAv6Wj40EArc3Upczyfk+shq70XQezah/9BXKcF0mDVcUUoFgv6h1WxDIvsrA9P+Xie0Z9bUHdnJBeNqGh/uMRaMEKU95tpSonX6ECn3U1eNvwRq08WWtICEtVNZFMjlnzuImf69frLNGglqSv6IWMHCSsIkBvioqxeNcF5bFv/A480uD3mBbjMV+SE2EczSihDElwNX0rVTgmDG4gmnYO1DGMu/Hli9g2sdbx1dTiKoTUq9+sACKcggi2DAUMqEYG4+ujAdQNMPa9w32kHox2iOSk+fpu2g4krVqpblLNRTgyj2fr7egJljAkIqMUs/AdHTyASFt0LUGp~-1~||1-LYvBPBicsZ-1-10-1000-2||~-1,38801,599,1450326266,30261689,PiZtE,59840,45,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,60,120,40,20,140,120,40,20,20,0,0,20,20,140,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11320044241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,7470918-1,2,-94,-118,102783-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,0,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;64;7;0",
        ];
        $sensorData2 = [
            "7a74G7m23Vrp0o5c9294571.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.71 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,402354,2490296,1920,1050,1920,1080,1920,572,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7566,0.969246392484,817636245148,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,0,0,0,0,-1,-1,0;0,-1,0,0,967,-1,0;0,-1,0,1,2315,447,0;1,-1,0,1,2089,327,0;0,-1,0,1,2314,763,0;0,-1,0,1,2015,336,0;0,-1,0,1,1582,447,0;1,-1,0,1,1356,327,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,42,-1,-1,-1;-1,2,-94,-109,0,41,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.iberia.com/us/manage-my-booking/?language=en&market=us&channel=COM#!/chktrp-1,2,-94,-115,1,32,32,42,41,0,83,1441,0,1635272490296,32,17493,0,0,2915,0,0,1443,83,0,A003836C2B92998B8F8C6DDE91A0FFDA~-1~YAAQL1oDF3SflK58AQAA7P3VvQbj5ory2ArhhJlLml/y6m5WCtoPXut/rmaJKiBAv6Wj40EArc3Upczyfk+shq70XQezah/9BXKcF0mDVcUUoFgv6h1WxDIvsrA9P+Xie0Z9bUHdnJBeNqGh/uMRaMEKU95tpSonX6ECn3U1eNvwRq08WWtICEtVNZFMjlnzuImf69frLNGglqSv6IWMHCSsIkBvioqxeNcF5bFv/A480uD3mBbjMV+SE2EczSihDElwNX0rVTgmDG4gmnYO1DGMu/Hli9g2sdbx1dTiKoTUq9+sACKcggi2DAUMqEYG4+ujAdQNMPa9w32kHox2iOSk+fpu2g4krVqpblLNRTgyj2fr7egJljAkIqMUs/AdHTyASFt0LUGp~-1~||1-LYvBPBicsZ-1-10-1000-2||~-1,38801,599,1450326266,30261689,PiZtE,38299,103,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,60,120,40,20,140,120,40,20,20,0,0,20,20,140,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.824fd2e5ab891,0.a894e074dbc56,0.7dccefaf21654,0.16b62a9c95743,0.67a67982b20e5,0.bea8f36f0348b,0.3c9abc3aac2e2,0.fee41964566fa,0.e3bec438bd109,0.df609b95e171c;5,0,1,3,6,2,3,1,1,2;0,0,1,4,1,6,7,6,4,4;A003836C2B92998B8F8C6DDE91A0FFDA,1635272490296,LYvBPBicsZ,A003836C2B92998B8F8C6DDE91A0FFDA1635272490296LYvBPBicsZ,1,1,0.824fd2e5ab891,A003836C2B92998B8F8C6DDE91A0FFDA1635272490296LYvBPBicsZ10.824fd2e5ab891,172,248,203,164,204,95,62,153,181,131,51,82,238,176,227,133,165,32,229,28,61,11,197,18,150,95,190,74,50,134,244,69,328,0,1635272491737;-1,2,-94,-126,-1,2,-94,-127,11320044241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,7470918-1,2,-94,-118,135215-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,0,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;4;7;0",
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
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData2[$key]]), $sensorDataHeaders);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function ParsePageHistory($startDate, $rows)
    {
        $result = [];
        $this->logger->debug("Total " . count($rows) . " transactions were found");

        foreach ($rows as $row) {
            if (!isset($row->dateMade, $row->sectorType)) {
                $this->sendNotification('Check the history');

                return $result;
            }

            $description = $row->description ?? '';
            $sector = $row->sectorType;
            $avios = $elitePoints = null;

            $transactionSummaries = $row->transactionSummaries ?? [];

            foreach ($transactionSummaries as $summary) {
                $currencyCode = $summary->monetaryAmount->currency->currencyCode;
                $amount = $summary->monetaryAmount->amount;

                if ($currencyCode == 'TIER_POINTS') {
                    $elitePoints = $amount;
                }
            }

            $currencyCode = $row->monetaryAmount->currency->currencyCode;

            if ($currencyCode == 'AVIOS') {
                $avios = $row->monetaryAmount->amount;
            }

            $dateTime = strtotime(str_replace(['T', '.000'], ' ', $row->dateMade), false);

            if (isset($startDate) && $dateTime < $startDate) {
                $this->logger->notice("break at date {$dateTime}");

                return $result;
            }

            $result[] = [
                'Date'         => $dateTime,
                'Description'  => $description,
                'Sector'       => $sector,
                'Avios'        => $avios,
                'Elite Points' => $elitePoints,
            ];
        }

        return $result;
    }
}
