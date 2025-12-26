<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPaybackgerman extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public $regionOptions = [
        ""           => "Select region",
        //        "Germany"    => "Germany (Bisherige Methoden)",// deprecated
        "GermanyNew" => "Germany",
        //        "India"      => "India",// It seems that the Indian region for the PAYBACK program is currently supported via a different website.
        "Italy"      => "Italy",
        "Poland"     => "Poland",
        //'Mexico'     => 'Mexico', // timeout
    ];

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $xpathLoginBtnItaly = "(//form[@name = 'Login']//input[contains(@name, 'login-button-')]/@name)[1]";

    private $timeout = 10;

    /*
    public static function isClientCheck(AwardWallet\MainBundle\Entity\Account $account, $isMobile)
    {
        if (!in_array($account->getLogin3(), ['GermanyNew', 'Germany', '', null])) {
            return false;
        }

        return null;
    }
    */

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if (in_array($this->AccountFields['Login3'], ['GermanyNew', 'Germany']) || is_null($this->AccountFields['Login3'])) {
            $this->UseSelenium();
//            $this->setProxyBrightData(null, 'static', 'de');
            $this->setProxyGoProxies(null, 'de');
            $this->http->saveScreenshots = true;
            $this->useFirefox();
            $this->keepCookies(false);
            // It breaks everything
            $this->usePacFile(false);
            $this->http->setRandomUserAgent();
        }

        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        // Add field "Region"
        ArrayInsert($fields, "Login", true, ["Login3" => [
            "Type"      => "string",
            "InputType" => "select",
            "Required"  => true,
            "Caption"   => "Region",
            "Options"   => $this->regionOptions,
        ]]);
        $fields['Login2']['Required'] = false;
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        switch ($this->AccountFields['Login3']) {
            // Region "Mexico"
            case 'Mexico':
                $arg["RedirectURL"] = 'https://www.payback.mx/mi-monedero';

                break;
            // Region "India"
            case "India":
                $arg["RedirectURL"] = "https://www.payback.in/home/login.html";

                break;
            // Region "Italy"
            case "Italy":
                $arg["RedirectURL"] = "https://www.payback.it/";

                break;
            // Region "Poland"
            case "Poland":
                $arg["RedirectURL"] = "https://www.payback.pl/logowanie";

                break;
            // Region "Germany"
            default:
                $arg["RedirectURL"] = "https://www.payback.de/pb/id/312142/";
        }
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login3'] == 'Italy') {
            $this->http->GetURL("https://www.payback.it/il-mio-profilo");
            // Access is allowed
            if ($this->http->FindSingleNode("//span[contains(text(), 'Esci')]")) {
                return true;
            }
        }// if ($this->AccountFields['Login3'] == 'Italy')
//        elseif (in_array($this->AccountFields['Login3'], ['GermanyNew', 'Germany', '', null])) {
//            $this->http->GetURL("https://www.payback.de");
//            $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')]"), 3);
//            $this->saveResponse();
//            if ($logout)
//                return true;
//        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (!in_array($this->AccountFields['Login3'], ['GermanyNew', 'Germany', '', null])) {
            $this->http->removeCookies();
        }

        if ($this->AccountFields['Login3'] == 'Germany' && !preg_match('/^\d+$/', $this->AccountFields['Pass'])) {
            $this->AccountFields['Login3'] = 'GermanyNew';
        }

        $this->logger->notice('Region => ' . $this->AccountFields['Login3']);

        $this->DebugInfo = !empty($this->AccountFields['Login3']) ? $this->AccountFields['Login3'] : "Germany";

        switch ($this->AccountFields['Login3']) {
            // Region "Mexico"
            case 'Mexico':
                if (!is_numeric($this->AccountFields['Login']) && strlen($this->AccountFields['Login']) < 10) {
                    throw new CheckException("Por favor ingresa los 10 ó 16 dígitos de tu Monedero PAYBACK", ACCOUNT_INVALID_PASSWORD);
                }
                $this->http->GetURL('https://www.payback.mx/mi-monedero');

                if (!$this->http->ParseForm("Login")) {
                    return $this->checkErrors();
                }
                $this->http->SetInputValue("alias", $this->AccountFields['Login']);
                $this->http->SetInputValue("secret", $this->AccountFields['Pass']);
                $this->http->SetInputValue("permanentLogin", "on");
                $btnSubmit = $this->http->FindNodes('(//form[@name = "Login"])[1]//div[@class="pb-authentication__submit-button"]/input/@name');

                if (!empty($btnSubmit)) {
                    for ($i = -1, $iCount = count($btnSubmit); ++$i < $iCount;) {
                        $this->http->SetInputValue($btnSubmit[$i], 'Ingresa');
                    }
                }

                $captcha = $this->parseReCaptchaV2();

                if ($captcha) {
                    $this->http->SetInputValue('g-recaptcha-response', $captcha);
                }

                break;
            // Region "India"
            case "India":
                throw new CheckException("It seems that the Indian region for the PAYBACK program is currently supported via a <a href='https://zillionrewards.in/'>different website.</a>", ACCOUNT_PROVIDER_ERROR);
            // Region "Italy"
            case "Italy":
                // refs #14348
                $this->http->SetProxy($this->proxyReCaptcha());

                $this->http->GetURL("https://www.payback.it/");

                if (!$this->http->ParseForm("Login")) {
                    return $this->checkErrors();
                }
                $this->http->SetInputValue("alias", $this->AccountFields['Login']);
                $this->http->SetInputValue("permanentLogin", "on");
                $this->http->SetInputValue($this->http->FindSingleNode($this->xpathLoginBtnItaly), 'Accedi');

                $captcha = $this->parseReCaptchaV2();

                if ($captcha) {
                    $this->http->SetInputValue('g-recaptcha-response', $captcha);
                }

                break;
            // Region "Poland"
            case 'Poland':
                $this->http->GetURL("https://www.payback.pl/logowanie");
                $this->checkErrors();

                if (empty($this->AccountFields['Login2'])) {
                    throw new CheckException("Please enter a Birth Date in a valid format", ACCOUNT_INVALID_PASSWORD);
                }/*review*/

                $date = preg_split("/[\/\.\-]/ims", $this->AccountFields['Login2']);
                $pass = str_replace(["-", " "], "", $this->AccountFields['Pass']);

                if (count($date) > 2 && strlen($pass) == 5) {
                    $btn = $this->http->FindSingleNode('(//form[@name = "Login"]//input[@value = "Zaloguj się"]/@name)[1]');

                    if (!$this->http->ParseForm("Login") || !$btn) {
                        return false;
                    }
                    $this->http->SetInputValue("alias", $this->AccountFields['Login']);
                    $this->http->SetInputValue("dob", "{$date[0]}.{$date[1]}.{$date[2]}");
                    $this->http->SetInputValue("zip", "{$pass[0]}{$pass[1]}-{$pass[2]}{$pass[3]}{$pass[4]}");
                    $this->http->SetInputValue("permanentLogin", "on");
                    $this->http->SetInputValue($btn, "Zaloguj się");

                    $captcha = $this->parseReCaptchaV2();

                    if ($captcha) {
                        $this->http->SetInputValue('g-recaptcha-response', $captcha);
                    }
                } else {
                    $this->logger->error("something wrong");

                    if (strlen($pass) > 5) {
                        throw new CheckException("Invalid credentials", ACCOUNT_INVALID_PASSWORD);
                    }

                    if (count($date) != 3) {
                        throw new CheckException("Please enter a Birth Date in a valid format", ACCOUNT_INVALID_PASSWORD);
                    }/*review*/
                }

                break;
            // Region => "Germany - New Form"
            case 'GermanyNew':
            case 'Germany':
            default:
                $this->http->GetURL("https://www.payback.de/login");

                $this->waitForElement(WebDriverBy::xpath('//input[@name = "identification"] | //input[@id = "aliasInputSecure"] | //iframe[contains(@src, "/_Incapsula_Resource?")] | //div[@class = \'g-recaptcha\'] | //button[@id = "onetrust-pc-btn-handler" or @id = "accept-recommended-btn-handler"]'), $this->timeout);

                $this->driver->executeScript("
                    try {
                        document.querySelector('#onetrust-banner-sdk').style.display = \"none\";
                    } catch (e) {}
                    
                    try {
                        document.querySelector('#onetrust-consent-sdk').style.display = \"none\";
                    } catch (e) {}
                ");

                $elem = $this->waitForElement(WebDriverBy::xpath('//input[@name = "identification"] | //input[@id = "aliasInputSecure"]'), 0);
                // Incapusla defence workaround
                if (!$elem) {
                    $this->loginIncapsula();
                    $this->waitForElement(WebDriverBy::xpath('//input[@name = "identification"] | //input[@id = "aliasInputSecure"] | //iframe[contains(@src, "/_Incapsula_Resource?")]  | //div[@class = \'g-recaptcha\']'), $this->timeout);
                    $elem = $this->waitForElement(WebDriverBy::xpath('//input[@name = "identification"] | //input[@id = "aliasInputSecure"]'), 0);

                    if (!$elem) {
                        $this->loginIncapsula(2);
                    }
                    $this->waitForElement(WebDriverBy::xpath('//input[@name = "identification"] | //input[@id = "aliasInputSecure"] | //iframe[contains(@src, "/_Incapsula_Resource?")]  | //div[@class = \'g-recaptcha\']'), $this->timeout);
                    $elem = $this->waitForElement(WebDriverBy::xpath('//input[@name = "identification"] | //input[@id = "aliasInputSecure"] | //input[@name="identification"]'), 0);
                }

                if (!$elem) {
                    $this->saveResponse();

                    $this->logger->debug("enter login");
                    $this->driver->executeScript("
                        document.querySelector('pbc-login').shadowRoot.querySelector('pbc-login-identification').shadowRoot.querySelector('#identificationInput').querySelector('input[name=\"identification\"]').value = \"{$this->AccountFields['Login']}\";
                        document.querySelector('pbc-login').shadowRoot.querySelector('pbc-login-identification').shadowRoot.querySelector('#buttonElement').shadowRoot.querySelector('button').click();
                    ");
                    sleep(7);

                    try {
                        $this->logger->debug("find errors...");
                        $this->saveResponse();
                        $error = $this->driver->executeScript("
                            return document.querySelector('pbc-login').shadowRoot.querySelector('pbc-login-identification').shadowRoot.querySelector('pbc-closable').shadowRoot.querySelector('.closable__title').innerText;
                        ");
                        $this->logger->error("[Error 1]: {$error}");

                        if (empty($error)) {
                            $error = $this->driver->executeScript("
                                return document.querySelector(\"pbc-login\").shadowRoot.querySelector(\"pbc-login-identification\").shadowRoot.querySelector(\"#identificationInput\").shadowRoot.querySelector(\"pbc-form-field-error .pbc-input__error-text\").innerText;
                            ");
                        }
                    } catch (Facebook\WebDriver\Exception\JavascriptErrorException $e) {
                        $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                        $error = null;
                    }

                    $this->logger->error("[Error]: {$error}");

                    if ($error) {
                        if ($error == 'Ein Fehler ist aufgetreten') {
                            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
                        }

                        if (
                            $error == 'Die Login-Daten sind ungültig. Bitte Eingabe überprüfen.'
                            || $error == 'Ungültige Eingabe'
                        ) {
                            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
                        }

                        if ($error == 'Unexpected Recaptcha Error.') {
                            throw new CheckRetryNeededException(3, 0);
                        }

                        $this->DebugInfo = "[Login]: " . $error;

                        return false;
                    }

                    $this->logger->debug("enter password");
                    $this->saveResponse();
                    $this->driver->executeScript("
                        document.querySelector('pbc-login').shadowRoot.querySelector('pbc-login-password').shadowRoot.querySelector('#passwordInput').querySelector('input[name=\"password\"]').value = \"" . str_replace(["\\", '"'], ["\\\\", '\"'], $this->AccountFields['Pass']) . "\";
                        document.querySelector('pbc-login').shadowRoot.querySelector('pbc-login-password').shadowRoot.querySelector('#buttonElement').shadowRoot.querySelector('button').click();
                    ");

                    sleep(5);

                    try {
                        $this->logger->debug("find errors...");
                        $this->saveResponse();
                        $error = $this->driver->executeScript("
                            return document.querySelector('pbc-login').shadowRoot.querySelector('pbc-login-password').shadowRoot.querySelector('pbc-closable').shadowRoot.querySelector('.closable__title').innerText;
                        ");
                    } catch (Facebook\WebDriver\Exception\JavascriptErrorException $e) {
                        $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                        $error = null;
                    }

                    $this->logger->error("[Error]: {$error}");

                    if ($error) {
                        if (
                            $error == 'Ein Fehler ist aufgetreten'
                            || $error == 'Die Login-Daten sind ungültig. Bitte Eingabe überprüfen.'
                        ) {
                            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
                        }

                        if (strstr($error, 'Das Konto wurde aufgrund mehrmaliger fehlerhafter Eingaben zur Sicherheit für 24 Stunden gesperrt')) {
                            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
                        }

                        $this->DebugInfo = "[Password]: " . $error;

                        return false;
                    }

                    $this->removePopup();

                    try {
                        $this->logger->debug('checking for the need to update profile');
                        $profileUpdateNeeded = $this->driver->executeScript("return document.querySelector('pbc-security-check').shadowRoot.querySelector('pbc-double-image-background').querySelector('pbc-security-check-overview').shadowRoot.querySelector('pbc-teaser').shadowRoot.querySelector('h1').innerText;");
                    } catch (Facebook\WebDriver\Exception\JavascriptErrorException $e) {
                        $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                        $profileUpdateNeeded = null;
                    }

                    if ($profileUpdateNeeded && strstr($profileUpdateNeeded, 'Mach deinen Account noch sicherer')) {
                        $this->throwProfileUpdateMessageException();
                    }

                    $this->waitFor(function () {
                        return is_null($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "2-Schritt-Verifizierung")]'), 0));
                    }, 120);

                    $this->saveResponse();

                    if ($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "2-Schritt-Verifizierung")]'), 0)) {
                        throw new CheckException("To complete the sign-in, you should respond to the notification that was sent to you", ACCOUNT_PROVIDER_ERROR);
                    }

                    $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')]"), $this->timeout);
                    $this->saveResponse();

                    if ($logout) {
                        return true;
                    }

                    try {
                        $this->logger->debug("find errors...");
                        $this->saveResponse();
                        $error = $this->driver->executeScript("
                            return document.querySelector('pbc-login').shadowRoot.querySelector('pbc-login-password').shadowRoot.querySelector('#passwordInput').shadowRoot.querySelector('.pbc-input__error-text').innerText;
                        ");
                    } catch (Facebook\WebDriver\Exception\JavascriptErrorException $e) {
                        $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                        $error = null;
                    }

                    $this->logger->error("[Error]: {$error}");

                    if ($error) {
                        if ($error == "Das Passwort hat mindestens 8 Zeichen") {
                            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
                        }

                        $this->DebugInfo = "[Password]: " . $error;

                        return false;
                    }

                    return $this->checkErrors();
                }

                $this->newGermanyLoginForm($elem);

                break;
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Due to the strong current of hits on our systems, it comes to short-term overloads
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Auf Grund der momentan starken Zugriffe auf unsere Systeme kommt es kurzzeitig zu Überlastungen')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Leider stehen Ihnen die PAYBACK Services aktuell nicht zur Verfügung')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindPreg("/(Momentan verbessern und erweitern wir unsere Services)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindPreg("/(We apologise for the inconvenience\.\s*The PAYBACK page you\'ve requested is not available at this time\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Leider stehen Ihnen die PAYBACK Services aktuell nicht zur Verfügung')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Unsere neue Wartungsseite wartung.payback.de
        if ($this->http->currentUrl() == 'http://wartung.payback.de/') {
            throw new CheckException("Momentan verbessern und erweitern wir unsere Services für Sie. Daher sind viele Bereiche unserer Website derzeit leider nicht erreichbar. Vielen Dank für Ihr Verständnis.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->currentUrl() == 'http://maintenance.payback.it/') {
            throw new CheckException("Purtroppo in questo momento il sito non è attivo per attività di manutenzione.", ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->AccountFields['Login3'] == 'India'
            && $this->http->FindSingleNode("
                //h1[
                        contains(text(), 'Service Unavailable')
                        or contains(text(), 'Internal Server Error - Read')
                    ]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function LoginOfPoland()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode('//a[contains(@href, "Logout")]')) {
            return true;
        }
        // Ups, nie udało się! Sprawdź, czy formularz jest poprawnie wypełniony i spróbuj ponownie.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Ups, nie udało się! Sprawdź, czy formularz jest poprawnie wypełniony i spróbuj ponownie.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Login()
    {
        $this->logger->debug("[Login3]: '{$this->AccountFields['Login3']}'");

        if ($this->AccountFields['Login3'] == 'Poland') {
            return call_user_func([$this, "LoginOf" . $this->AccountFields['Login3']]);
        }

        if (!(in_array($this->AccountFields['Login3'], ['GermanyNew', 'Germany', 'India']) || is_null($this->AccountFields['Login3']))) {
            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
        }

        switch ($this->AccountFields['Login3']) {
            // Region "Mexico"
            case 'Mexico':
                // Por favor ingresa tu NIP o contraseña
                if (
                    $this->http->ParseForm("Login")
                    && ($this->http->FindSingleNode('
                            //p[normalize-space() = "Por favor ingresa tu NIP o contraseña"] 
                            | //p[normalize-space() = "Por favor ingresa tu contraseña"]
                            | //p[normalize-space() = "Tus datos son incorrectos. La contraseña tiene que ser 8-20 caracteres alfanuméricos."]
                        ')
                    )
                ) {
                    $this->http->SetInputValue("secret", $this->AccountFields['Pass']);
                    $btnSubmit = $this->http->FindNodes('(//form[@name = "Login"])[1]//div[@class="pb-authentication__submit-button"]/input/@name');

                    if (!empty($btnSubmit)) {
                        for ($i = -1, $iCount = count($btnSubmit); ++$i < $iCount;) {
                            $this->http->SetInputValue($btnSubmit[$i], 'Ingresa');
                        }
                    }
                    $captcha = $this->parseReCaptchaV2();

                    if ($captcha) {
                        $this->http->SetInputValue('g-recaptcha-response', $captcha);
                    }

                    if (!$this->http->PostForm()) {
                        return $this->checkErrors();
                    }
                }

                if ($this->http->FindSingleNode('//span[contains(text(), "Cerrar Sesión")]')) {  // Logout button
                    return true;
                }

                if ($message = $this->http->FindSingleNode('
                        //p[contains(text(), "Tus datos son incorrectos. Por favor verifica tu información")]
                        | //p[normalize-space() = "Tus datos son incorrectos. La contraseña tiene que ser 8-20 caracteres alfanuméricos."]
                        | //p[normalize-space() = "La información es incorrecta. Revisa los campos marcados en rojo."]
                ')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Tu información es incorrecta. Por favor verifícala
                if ($message = $this->http->FindSingleNode('//p[contains(text(), "Tu información es incorrecta. Por favor verifícala")]')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Por favor verifica tu información
                if ($message = $this->http->FindSingleNode('(//p[contains(text(), "Por favor verifica tu información")])[1]')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // ¡Ups! El número de dígitos que ingresaste es incorrecto, ingresa los 10 dígitos que están al reverso de tu Monedero
                if ($message = $this->http->FindSingleNode('(//p[contains(text(), "¡Ups! El número de dígitos que ingresaste es incorrecto, ingresa los 10 dígitos que están al reverso de tu Monedero")])[1]')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindSingleNode('//p[contains(text(), "Por seguridad, tu cuenta fue bloqueada")] | //i[contains(text(), "Por seguridad, tu cuenta fue bloqueada.")]')) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if ($message = $this->http->FindSingleNode('//strong[contains(text(), "Por seguridad, tu cuenta fue bloqueada. Contacta el Centro de Servicio a Clientes PAYBACK:")]')) {
                    throw new CheckException("Por seguridad, tu cuenta fue bloqueada.", ACCOUNT_LOCKOUT);
                }

                break;
            // Region "India"
            case "India":
                $response = $this->http->JsonLog();
                // Access is allowed
                if ($response->data->tokenData ?? null) {
                    return true;
                }
                $message = $response->message ?? $response->error->message ?? null;

                if ($message) {
                    $this->logger->error("[Error]: " . $message);

                    switch ($message) {
                        case 'PIN entered by you in incorrect. Please try again.':
                        case 'PIN entered by you is incorrect. Please try again.':
                        case strstr($message, 'PIN entered by you in incorrect. Only '):
                        case 'PIN expired! Regenerate your PIN instantly using forgot PIN link and use your linked mobile number':
                        case 'Try with correct password.':
                        case 'Member has no DoB':
                        case 'The Card Number / Mobile Number entered is incorrect.':
                        case 'Member is Deleted':
                        case 'Please login with your registered mobile number.':
                            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

                            break;

                        case 'Something went wrong. Please try again.':
                            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

                            break;

                        case 'Invalid Captcha':
                            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);

                            break;

                        default:
                            $this->DebugInfo = $message;
                    }// switch ($message)
                }// if ($message)

                // AccountID: 6174352
                if ($this->http->Response['body'] == '{"error":{"code":"IND-00001","message":""}}') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                break;
            // Region "Italy"
            case "Italy":
                // Access is allowed
                if ($this->http->FindSingleNode("//span[contains(text(), 'Esci')]")) {
                    return true;
                }
                // Ti preghiamo di verificare che i campi inseriti siano corretti
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Ti preghiamo di verificare che i campi inseriti siano corretti')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Il numero di carta o indirizzo e-mail inserito non esiste. Ti preghiamo di verificare.
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Il numero di carta o indirizzo e-mail inserito non esiste. Ti preghiamo di verificare.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Il numero di carta o il tuo indirizzo e-mail non sono stati riconosciuti.
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Il numero di carta o il tuo indirizzo e-mail non sono stati riconosciuti.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // La tua carta non risulta registrata. Per accedere a tutte le offerte del programma e ai coupon,registrala qui.
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'La tua carta non risulta registrata. Per accedere a tutte le offerte del programma e ai coupon,')]")) {
                    throw new CheckException("La tua carta non risulta registrata.", ACCOUNT_INVALID_PASSWORD);
                }
                /*
                 * Per ragioni di sicurezza il tuo conto è stato bloccato.
                 * Ti preghiamo di contattare il nostro Servizio Clienti al numero verde 800 93 00 93 da rete fissa o al numero
                 * 099 2320880 da rete mobile (costi e tariffe in funzione dell'operatore utilizzato).
                 */
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Per ragioni di sicurezza il tuo conto è stato bloccato.')]")) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                break;
            // Region => "Germany - New Form"
            //case 'Germany':
            // Region => "Germany"
            case 'GermanyNew':
            default:
                $this->waitForElement(WebDriverBy::xpath("//p[contains(normalize-space(text()), 'Bitte Login-Daten prüfen. Schutz mit Google reCAPTCHA ist aktiviert.')] | //iframe[contains(@src, '/_Incapsula_Resource?')] | //div[@class = 'g-recaptcha'] | //div[contains(@class, 'pb-notification--error')] | //a[contains(@href, 'logout')]"), $this->timeout);
                $this->saveResponse();

                if ($this->waitForElement(WebDriverBy::xpath("//p[contains(normalize-space(text()), 'Bitte Login-Daten prüfen. Schutz mit Google reCAPTCHA ist aktiviert.')]"), 0)) {
                    $this->logger->notice("Captcha");
                    $elem = $this->waitForElement(WebDriverBy::xpath('//input[@name = "identification"] | //input[@id = "aliasInputSecure"]'), 0);
                    $this->newGermanyLoginForm($elem);
                }

                $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')]"), 0);
                $this->saveResponse();

                if ($logout) {
                    return true;
                }
                $this->germanyInvalidCredentials();
                $this->loginIncapsula(2);

                $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')]"), $this->timeout);
                $this->saveResponse();

                // second attempt
                if (
                    ($elem = $this->waitForElement(WebDriverBy::xpath('//input[@name = "identification"] | //input[@id = "aliasInputSecure"]'), 0))
                    && $this->newGermanyLoginForm($elem)
                ) {
                    if ($this->waitForElement(WebDriverBy::xpath("//p[contains(normalize-space(text()), 'Bitte Login-Daten prüfen. Schutz mit Google reCAPTCHA ist aktiviert.')]"), 0)) {
                        $this->logger->notice("Captcha");
                        $elem = $this->waitForElement(WebDriverBy::xpath('//input[@name = "identification"] | //input[@id = "aliasInputSecure"]'), 0);
                        $this->newGermanyLoginForm($elem);
                    }
                    $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')]"), $this->timeout);
                    $this->saveResponse();
                }

                if ($logout) {
                    return true;
                }
                $this->germanyInvalidCredentials();
                // Die eingegebene Kundennummer ist ungültig. Sie darf nur Ziffern enthalten und muss 10-stellig sein.
                if ($this->http->FindPreg('/<strong>Die eingegebene Kundennummer ist ung/ims')
                    && $this->http->FindPreg('/Sie darf nur Ziffern enthalten und muss 10-stellig sein\./ims')) {
                    throw new CheckException("Die eingegebene Kundennummer ist ungültig. Sie darf nur Ziffern enthalten und muss 10-stellig sein.", ACCOUNT_INVALID_PASSWORD);
                }

                // no errors, no auth
                if ($this->AccountFields['Login'] == 'fha@wvs-direct.de') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                break;
        }

        return $this->checkErrors();
    }

    public function germanyInvalidCredentials()
    {
        $this->logger->notice(__METHOD__);
        // Die Login-Daten sind ungültig. Bitte Eingabe überprüfen.
        if ($message = $this->waitForElement(WebDriverBy::xpath("
                //p[contains(text(), 'Die Login-Daten sind ungültig. Bitte die markierte Eingabe überprüfen')]
                | //p[contains(text(), 'Die Login-Daten sind ungültig. Bitte Eingabe überprüfen.')]
                | //p[contains(text(), 'Da ist etwas schief gegangen! Haben Sie vielleicht schon auf das neue PAYBACK Login umgestellt?')]
                | //p[contains(text(), 'Die eingegebenen Zugangsdaten sind ungültig.')]
                | //p[contains(text(), 'Um einem Missbrauch vorzubeugen')]
                | //p[strong[contains(text(), 'Wir konnten Sie nicht einloggen.')]]
                | //p[strong[span[contains(text(), 'Wir konnten Sie nicht einloggen.')]]]
                | //p[strong[contains(text(), 'Das Passwort ist ungültig')]]
                | //p[strong[contains(text(), 'Die Postleitzahl ist ungültig')]]
                | //p[strong[contains(text(), 'Die eingegebene Kundennummer ist ungültig')]]
                | //p[strong[contains(text(), 'Das eingegebene Geburtsdatum ist ungültig')]]
            "), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }
        // Ihr Konto wurde zu Ihrer Sicherheit gesperrt!
        if ($message = $this->waitForElement(WebDriverBy::xpath('
                //strong[contains(text(), "Ihr Konto wurde zu Ihrer Sicherheit gesperrt!")]
                | //p[contains(normalize-space(text()), "Ihr Konto wurde deaktiviert. Ein Login auf")]
                | //p[contains(text(), "Ihr Konto wurde deaktiviert.")]
                | //p[contains(text(), "Das Konto ist deaktiviert. Ein Login auf PAYBACK.de ist nicht möglich.")]
                | //p[contains(text(), "Das Konto wurde aufgrund mehrmaliger fehlerhafter")]
        '), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
        }
        // Das Passwort ist ungültig
        if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Das Passwort ist ungültig')]"), 0)) {
            throw new CheckException("Das Passwort ist ungültig", ACCOUNT_INVALID_PASSWORD);
        }
        // Die eingegebenen Login-Daten sind ungültig
        if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Die eingegebenen Login-Daten sind ungültig')]"), 0)) {
            throw new CheckException("Die eingegebenen Login-Daten sind ungültig. Bitte überprüfen Sie Ihre Eingabe. Sie können sich alternativ auch mit Ihrer Kundennummer und Ihrem Passwort einloggen.", ACCOUNT_INVALID_PASSWORD);
        }
        // Leider stehen Ihnen die PAYBACK Services aktuell nicht zur Verfügung
        if ($message = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Leider stehen Ihnen die PAYBACK Services aktuell nicht zur Verfügung')]"), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        $this->saveResponse();
    }

    public function parseCaptcha($formID, $item = null)
    {
        $this->logger->notice(__METHOD__);
        $url = $this->waitForElement(WebDriverBy::xpath("//form[@id = '{$formID}']//img[@id = 'captchaImage']"), $this->timeout);

        if (!$url) {
            return false;
        }

        if ($item) {
            $img = "document.getElementsByTagName('img')[{$item}]";
        } else {
            $img = "document.getElementById('captchaImage')";
        }

        $captcha = $this->driver->executeScript("

		var captchaDiv = document.createElement('div');
		captchaDiv.id = 'captchaDiv';
		document.body.appendChild(captchaDiv);

		var canvas = document.createElement('CANVAS'),
			ctx = canvas.getContext('2d'),
			img = {$img};

		canvas.height = img.height;
		canvas.width = img.width;
		ctx.drawImage(img, 0, 0);
        dataURL = canvas.toDataURL('image/png');

		return dataURL;

		");

        $this->logger->debug("captcha: " . $captcha);
        $marker = "data:image/png;base64,";

        if (strpos($captcha, $marker) !== 0) {
            $this->logger->error("no marker");

            return false;
        }
        $captcha = substr($captcha, strlen($marker));
        $file = tempnam(sys_get_temp_dir(), "captcha") . ".png";
        $this->logger->debug("captcha file: " . $file);
        file_put_contents($file, base64_decode($captcha));

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $this->logger->debug("Captcha URL -> " . $url->getAttribute('src'));

        $captcha = $this->recognizeCaptcha($recognizer, $file, []);
        unlink($file);

        return $captcha;
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login3']) {
            // Region "Mexico"
            case 'Mexico':
                // additional security step
                if ($this->http->FindSingleNode("//p[contains(text(), 'Por favor ingresa tu NIP.')]") && $this->http->ParseForm("Login")) {
                    $this->http->SetInputValue("secret", $this->AccountFields['Pass']);
                    $btnSubmit = $this->http->FindNodes('(//form[@name = "Login"])[1]//div[@class="pb-authentication__submit-button"]/input/@name');

                    if (!empty($btnSubmit)) {
                        for ($i = -1, $iCount = count($btnSubmit); ++$i < $iCount;) {
                            $this->http->SetInputValue($btnSubmit[$i], 'Ingresa');
                        }
                    }
                    $this->http->PostForm();

                    /* temporarily may by
                    // ¡Ups! El número de dígitos que ingresaste es incorrecto, ingresa los 10 dígitos que están al reverso de tu Monedero
                    if ($message = $this->http->FindSingleNode('//p[contains(text(), "¡Ups! El número de dígitos que ingresaste es incorrecto, ingresa los 10 dígitos que están al reverso de tu Monedero")]')) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }
                    */
                }
                // Titular de la cuenta
                $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//li[@class="pb-account-details__card-holder-item"][2]//span[@class="pb-account-details__card-holder-name"][1]')));
                // Total de Puntos
                $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "pb-points-explanation_layer")]//div[contains(@class, "pb-points-explanation__item") and @data-locator="redeemable"]//div[@class="pb-points-explanation__points-tile-points"]'));
                // Puntos Bloqueados
                $this->SetProperty('BlockedPoints', $this->http->FindSingleNode('//div[contains(@class, "pb-points-explanation_layer")]//div[contains(@class, "pb-points-explanation__item") and @data-locator="blocked"]//div[@class="pb-points-explanation__points-tile-points"]'));

                $this->http->GetURL('https://www.payback.mx/mis-datos');
                // Núm. de Monedero PAYBACK
                $this->SetProperty('Number', $this->http->FindSingleNode('//span[@data-locator = "cardNumberBig"]'));

                break;
            // Region "Poland"
            case 'Poland':
                $this->http->GetURL("https://www.payback.pl/moje-punkty");

                if ($this->http->FindPreg("/Zaloguj się, aby zobaczyć swoje zebrane punkty/ims") && $this->http->ParseForm("Login")) {
                    $this->http->SetInputValue("secret", $this->AccountFields['Pass']);
                    $this->http->SetInputValue($this->http->FindSingleNode($this->xpathLoginBtnItaly), 'Accedi');

                    $captcha = $this->parseReCaptchaV2();

                    if ($captcha) {
                        $this->http->SetInputValue('g-recaptcha-response', $captcha);
                    }

                    $this->http->PostForm();
                }

                // Balance - masz już ... °P
                $this->SetBalance($this->http->FindSingleNode('//div[@class = "pb-account-details__points-area-value"]/text()[1]'));
                // Available points
                $this->SetProperty("AvailablePoints", $this->http->FindSingleNode('//div[contains(@class, "js__points-explanation-layer")]//div[contains(@class, "points-redeemable")]/following-sibling::div[@class = "pb-points-explanation__points-tile-points"]'));
                // Blocked points
                $this->SetProperty("BlockedPoints", $this->http->FindSingleNode('//div[contains(@class, "js__points-explanation-layer")]//div[contains(@class, "points-blocked")]/following-sibling::div[@class = "pb-points-explanation__points-tile-points"]'));

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    $this->SetBalance($this->http->FindSingleNode('//span[contains(@class, "pb-navigation__member-text")]'));
                    // Name
                    $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[contains(@class, "pb-member__header")]/p', null, true, "/Witaj\s*(.+)!/")));

                    // Ti preghiamo di verificare che i campi inseriti siano corretti.
                    // AccountID: 2026513
                    if (
                        $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                        && (strlen($this->AccountFields['Pass']) < 5 || strstr($this->AccountFields['Pass'], '-'))
                        && ($message = $this->http->FindSingleNode("//p[contains(text(), 'Ups, nie udało się! Sprawdź, czy formularz jest poprawnie wypełniony i spróbuj ponownie.')]"))
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    return;
                }

                $this->http->GetURL("https://www.payback.pl/ustawienia-konta");
                // PAYBACK number
                $this->SetProperty("Number", $this->http->FindSingleNode('//span[@data-locator="cardNumberBig"]'));
                // Name
                $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@data-locator = "personalFirstName"]') . " " . $this->http->FindSingleNode('//div[@data-locator = "personalLastName"]')));

                break;
            // Region "India"
            case "India":
                $response = $this->http->JsonLog(null, 0, true);
                $data = ArrayVal($response, 'data');
                // PAYBACK number
                $this->SetProperty("Number", ArrayVal($data, 'cardNumber'));

                // Balance - PAYBACK Points
                $accountBalance = ArrayVal($data, 'extintAccountBalance');
                $this->SetBalance(ArrayVal($accountBalance, 'typesAvailablePointsAmount'));
                // Points redeemable
                $this->SetProperty("BlockedPoints", ArrayVal($accountBalance, 'typesBlockedPointsAmount'));
                // Points expiring
                $expiryAnnouncement = ArrayVal($accountBalance, 'typesExpiryAnnouncement');
                $pointsExpiring = ArrayVal($expiryAnnouncement, 'typesPointsToExpireAmount');
                $this->SetProperty("PointsExpiring", $pointsExpiring);

                if ($pointsExpiring > 0) {
                    $exp = preg_replace('/T.+/', '', ArrayVal($expiryAnnouncement, 'typesNextExpiryDate'));

                    if (strtotime($exp)) {
                        $this->SetExpirationDate(strtotime($exp));
                    }
                }
                // Name
                $masterInfo = ArrayVal($data, 'masterInfo');
                $this->SetProperty("Name", beautifulName(ArrayVal($masterInfo, 'typesFirstName') . ' ' . ArrayVal($masterInfo, 'typesLastName')));

                break;
            // Region "Italy"
            case "Italy":
                // Balance - Punti utilizzabili
                $this->SetBalance($this->http->FindSingleNode("//span[@class = 'pb-member-info-points']", null, true, "/[\-\d\,\.\s]+/"));

                $this->http->GetURL("https://www.payback.it/il-mio-profilo");

                if ($this->http->FindPreg("/(?:Accedi all\&\#39;area personale PAYBACK|Accedi all'area personale PAYBACK)/ims") && $this->http->ParseForm("Login")) {
                    $this->http->SetInputValue("secret", $this->AccountFields['Pass']);
                    $this->http->SetInputValue($this->http->FindSingleNode($this->xpathLoginBtnItaly), 'Accedi');

                    $captcha = $this->parseReCaptchaV2();

                    if ($captcha) {
                        $this->http->SetInputValue('g-recaptcha-response', $captcha);
                    }

                    $this->http->PostForm();
                }

                // Balance - Punti utilizzabili
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    $this->SetBalance($this->http->FindSingleNode("//span[@class = 'pb-member-info-points']", null, true, "/[\-\d\,\.\s]+/"));

                    // Ti preghiamo di verificare che i campi inseriti siano corretti.
                    // AccountID: 4311778, 4233532, 3634622
                    if (
                        $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                        && ($this->AccountFields['Pass'] == '****' || in_array($this->AccountFields['Login'], ['6371524704486155', '4011134407', '4401972838']))
                        && ($message = $this->http->FindSingleNode("//p[contains(text(), 'Ti preghiamo di verificare che i campi inseriti siano corretti.') or contains(text(), 'Per ragioni di sicurezza il tuo conto è stato bloccato.') or contains(text(), 'Ti preghiamo di verificare che i campi inseriti siano corretti.')]"))
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }
                }
                // Numero di carta PAYBACK®
                $this->SetProperty("Number", $this->http->FindSingleNode("//span[@data-locator = 'cardNumberBig']"));
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@data-locator = 'personalFirstName']") . " " . $this->http->FindSingleNode("//div[@data-locator = 'personalLastName']")));

                break;
            // Region => "Germany"
            default:
                //$this->waitForElement(WebDriverBy::xpath("//p[@class = 'welcome-msg']/a"), 5);
                //$this->saveResponse();
                //# Balance header-element--welcome-msg pull-left hidden-xs hidden-sm visible-md visible-md visible-lg
                $this->SetBalance(preg_replace('/\D/', '', $this->http->FindSingleNode("//div[contains(@class, 'header-element--welcome-msg')]/a")));
                //# Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(@class, 'header-element--welcome-msg')]/strong")));
        }// switch ($this->AccountFields['Login3'])
    }

    protected function parseReCaptchaV2($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("(//form[@name = 'Login']//div[@class = 'g-recaptcha']/@data-sitekey)[1]");
        }

        if (!$key) {
            $key = $this->http->FindSingleNode("//form[@id = 'pb-login_form']//div[@class = 'g-recaptcha']/@data-sitekey");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $this->logger->notice("currentUrl: " . $this->http->currentUrl());
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }

    private function newGermanyLoginForm($elem)
    {
        $this->logger->notice(__METHOD__);

        $elem->sendKeys($this->AccountFields['Login']);
        $this->saveResponse();

        if ($btn = $this->waitForElement(WebDriverBy::id("buttonElement"), 0)) {
            $btn->click();
        }

        sleep(1);

        if ($pass = $this->waitForElement(WebDriverBy::id("passwordInput"), 5)) {
            $pass->sendKeys(htmlspecialchars_decode($this->AccountFields['Pass']));
        }

        sleep(1);
        $this->saveResponse();

        // Recaptcha
        if (
            $this->http->FindSingleNode('//p[contains(normalize-space(.), "Bitte Login-Daten prüfen. Schutz mit Google reCAPTCHA ist aktiviert")]')
        ) {
            $this->logger->notice("Captcha");
            $key = $this->waitForElement(WebDriverBy::xpath("//button[@data-sitekey] | //div[@class = 'js-recaptcha']"), 10);

            if (!$key) {
                $this->logger->error("data-sitekey not found");

                return false;
            }// if (!$key)
            $key = $key->getAttribute('data-sitekey');
            $captcha = $this->parseReCaptchaV2($key);

            if ($captcha === false) {
                return false;
            }
            $this->waitForElement(WebDriverBy::id("g-recaptcha-response"), 5);
            $this->driver->executeScript(
                'document.getElementById("g-recaptcha-response").value = "' . $captcha . '";');

            if ($pass = $this->waitForElement(WebDriverBy::id("passwordInput"), 0)) {
                $pass->sendKeys(htmlspecialchars_decode($this->AccountFields['Pass']));
            }
        }

        $this->removePopup();

        if ($btn = $this->waitForElement(WebDriverBy::id("loginSubmitButtonSecure"), 0)) {
            $btn->click();
        }

        return true;
    }

    private function removePopup()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $this->driver->executeScript('document.getElementById(\'onetrust-consent-sdk\').style.display = \'none\';');
        $this->saveResponse();

        return true;
    }

    private function loginIncapsula($version = 1)
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = $this->http->currentUrl();
        $this->waitForElement(WebDriverBy::xpath("//iframe[contains(@src, '/_Incapsula_Resource?')] | //div[@class = 'g-recaptcha']"), 5);

        if ($iframe = $this->waitForElement(WebDriverBy::xpath("//iframe[contains(@src, '/_Incapsula_Resource?')]"), 0)) {
            $this->driver->switchTo()->frame($iframe);
        }
        $this->saveResponse();
        $sitekey = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");

        if (!$sitekey) {
            return false;
        }
        $captcha = $this->parseReCaptchaV2($sitekey);

        if ($captcha) {
            if ($this->http->FindSingleNode("//h1[contains(text(),'www.payback.de Additional security check is required')]")) {
                $this->logger->notice("version 1");
                $this->driver->executeScript('document.getElementById("g-recaptcha-response").value = "' . $captcha . '";onCaptchaFinished("' . $captcha . '");');
                sleep(4);
                $this->saveResponse();
                $this->http->GetURL('https://www.payback.de/login');
                $this->saveResponse();
            } else {
                $this->logger->notice("version 2");
                $this->driver->executeScript('document.getElementById("g-recaptcha-response").value = "' . $captcha . '";handleCaptcha("' . $captcha . '");');
                /*
                $this->http->GetURL($this->http->currentUrl());
                */
                sleep(4);
                $this->saveResponse();
                $this->http->GetURL("https://www.payback.de/login");
            }

            return true;
        }

        return false;
    }
}
