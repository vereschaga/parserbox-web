<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSephora extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public $regionOptions = [
        ""      => "Select your region",
        "Spain" => "Spain",
        "Italy" => "Italy",
        "USA"   => "USA",
    ];

    private $domain = 'it';

    public static function isClientCheck(AwardWallet\MainBundle\Entity\Account $account, $isMobile)
    {
        if (in_array($account->getLogin2(), ['Spain', 'Italy'])) {
            return false;
        }

        return null;
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields['Login2']['Options'] = $this->regionOptions;
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        $redirectURL = "https://www.sephora.com/profile/login/login.jsp";

        switch ($this->AccountFields['Login2']) {
            case 'Spain':
                $redirectURL = "https://www.sephora.es/iniciar-sesion";

                break;

            case 'Italy':
                $redirectURL = "https://www.sephora.it/accedi";

                break;
        }
        $arg["RedirectURL"] = $redirectURL;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        $this->http->setHttp2(true);

        if ($this->AccountFields['Login2'] == 'Italy') {
            $this->http->setHttp2(true);
            $this->http->SetProxy($this->proxyReCaptcha());
        }

        if ($this->AccountFields['Login2'] == 'USA') {
            $this->UseSelenium();

//            $this->useFirefoxPlaywright();// TODO: not woring now
            $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
            $this->setProxyMount();
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumOptions->userAgent = null;

            $this->usePacFile(false);

            $this->http->saveScreenshots = true;
        }
    }

    public function LoadLoginForm()
    {
        return call_user_func([$this, "LoadLoginForm" . $this->AccountFields['Login2']]);
    }

    public function LoadLoginFormSpain()
    {
        $this->logger->notice(__METHOD__);

        return $this->LoadLoginFormItaly();
    }

    public function LoadLoginFormItaly()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $url = "https://www.sephora.it/accedi/";

        if ($this->AccountFields['Login2'] == 'Spain') {
            $this->domain = 'es';
            $url = 'https://www.sephora.es/iniciar-sesion';
        }

        return $this->seleniumSendSensorData($url);

        $this->http->GetURL($url);
        $login = $this->http->FindSingleNode("//form[@id = 'dwfrm_login']//input[contains(@name, 'dwfrm_login_username_')]/@name");
        $pass = $this->http->FindSingleNode("//form[@id = 'dwfrm_login']//input[contains(@name, 'dwfrm_login_password_')]/@name");

        if (!$this->http->ParseForm('dwfrm_login') || !$login || !$pass) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue($login, $this->AccountFields['Login']);
        $this->http->SetInputValue($pass, $this->AccountFields['Pass']);
        $this->http->SetInputValue("dwfrm_login_login", "Log In");
        $this->http->SetInputValue("dwfrm_login_rememberme", "true");

        $this->sendSensorData();

        return true;
    }

    public function LoadLoginFormUSA()
    {
        $this->logger->notice(__METHOD__);
        // Please enter an e-mail address in the format username@domain.com.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter an e-mail address in the format username@domain.com.", ACCOUNT_INVALID_PASSWORD);
        }
        $this->http->removeCookies();

        try {
            $this->http->GetURL("https://www.sephora.com/profile/me");
        } catch (
            WebDriverCurlException
            | NoSuchWindowException
            | NoSuchDriverException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 3);
        } catch (ScriptTimeoutException | TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("TimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        }
        $formXpath = "//div[@id = 'modalDialog' or @id = 'modal0Dialog' or @id = 'modal1Dialog' or @id = 'modal2Dialog']";
        $login = $this->waitForElement(WebDriverBy::xpath($formXpath . "//input[@type='email']"), 10);

        if (!$login && ($signInBtn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'sign in')]"), 0))) {
            $this->saveResponse();
            $signInBtn->click();
            $login = $this->waitForElement(WebDriverBy::xpath($formXpath . "//input[@type='email']"), 10);
        }

        $pass = $this->waitForElement(WebDriverBy::xpath($formXpath . "//input[@type='password']"), 0);
        $button = $this->waitForElement(WebDriverBy::xpath($formXpath . "//button[@type='submit']"), 0);
        $this->saveResponse();

        try {
            if (!$login || !$pass || !$button) {
                $this->logger->error("something went wrong");

                if ($this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Sign Out') or contains(text(), 'Se déconnecter')]"), 0, false)) {
                    return true;
                }

                $this->saveResponse();

                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    $this->markProxyAsInvalid();

                    throw new CheckRetryNeededException(3, 5);
                }

                try {
                    $this->checkErrors();
                } catch (NoSuchDriverException $e) {
                    $this->logger->error("[Exception]: {$e->getMessage()}");

                    if (
                        strstr($e->getMessage(), 'Tried to run command without establishing a connection Build info: version')
                    ) {
                        throw new CheckRetryNeededException(2, 0);
                    }

                    throw $e;
                }

                if ($this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Start a Conversation')]"), 0)) {
                    throw new CheckRetryNeededException(3, 3);
                }

                $login = $this->waitForElement(WebDriverBy::xpath($formXpath . "//input[@type='email']"), 1);
                $pass = $this->waitForElement(WebDriverBy::xpath($formXpath . "//input[@type='password']"), 0);
                $button = $this->waitForElement(WebDriverBy::xpath($formXpath . "//button[@type='submit']"), 0);
                $this->saveResponse();

                if (!$login || !$pass || !$button) {
                    return false;
                }
            }
            $this->logger->debug("set login");
            $login->click();
            $mover = new MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($login, $this->AccountFields['Login'], 5);
//            $login->sendKeys($this->AccountFields['Login']);
            $this->logger->debug("set pass");
            $pass->click();
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();
            $this->logger->debug("click btn");
            $button->click();
        } catch (
            WebDriverCurlException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | NoSuchDriverException
            $e
        ) {
            $this->logger->error("Exception: {$e->getMessage()}");

            throw new CheckRetryNeededException(1, 3);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sephora.com is currently unavailable
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Due to a high volume of traffic, Sephora.com is currently unavailable')]", null, true, "/(.*)As always, our Beauty Advisors are available/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Touch-ups in progress
        if ($this->http->FindSingleNode("//p[contains(text(), 'Please be patient while we update our site')]")
            || $this->http->FindSingleNode("//img[contains(@src, 'maintenance')]/@src")) {
            throw new CheckException('Please be patient while we update our site', ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // HTTP Status 404
            $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status')]")
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Sephora online is experiencing high volume traffic. PLease check back soon
        if ($this->http->FindSingleNode("//img[contains(@src, '/akfailover/images/hd-so-sorry')]/@src")
            || $this->http->FindSingleNode("//img[contains(@src, '/spawaitingroom/images/vib1.jpg')]/@src")
            || $this->http->FindSingleNode("//img[contains(@src, '/spawaitingroom/images/BLACK_FRIDAY_V2.jpg')]/@src")
            || $this->http->FindSingleNode("//img[contains(@src, '/spawaitingroom/images/Holiday-2014-Desktop-SPA-page-Holiday-Hours-asset.jpg')]/@src")
            || $this->http->FindSingleNode("//img[contains(@src, '/spawaitingroom/images/black-friday.jpg')]/@src")) {
            throw new CheckException('Sephora online is experiencing high volume traffic. Please check back soon', ACCOUNT_PROVIDER_ERROR);
        }

        // Message in site
        try {
            $this->http->GetURL('http://www.sephora.com/profile/login/findUser.jsp?user_name=' . urlencode($this->AccountFields['Login']));
        } catch (ScriptTimeoutException | TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->debug("TimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $result = json_decode($this->http->Response['body']);
        // AccountID: 333296
        if (isset($result->security_question, $result->has_security_answer) && empty($result->security_question) && !$result->has_security_answer) {
            throw new CheckException("Action Required. Please login to Sephora (Beauty Insider) and respond to a message that you will see after your login.", ACCOUNT_PROVIDER_ERROR);
        }
        // AccountID: 786362
        if (isset($result->first_name, $result->last_name, $result->is_pos_member, $result->is_store_bi_member)
            && $result->is_pos_member && $result->is_store_bi_member
        ) {
            $this->throwProfileUpdateMessageException();
        }

        return false;
    }

    public function Login()
    {
        if ($this->AccountFields['Login2'] != 'USA') {
//            $form = $this->http->Form;
//            $formURL = $this->http->FormURL;
//
//            if (!$this->http->PostForm()) {
//                return $this->checkErrors();
//            }

            // provider bug fix
//            if (/*$this->AccountFields['Login2'] == 'USA' &&*/
//                ($this->http->Response['body'] == '{"errorCode":347, "errorMessages":["profile.restricted.for.environment"]}'
//                    || $this->http->Response['body'] == '{"errorCode":347, "errorMessages":["profile.exist.on.opposite.environment"]}')){
//                $this->logger->notice("provider bug fix");
//                $this->http->Form = $form;
//                $this->http->FormURL = $formURL;
//                $this->http->PostForm();
//            }
        }

        return call_user_func([$this, "Login" . $this->AccountFields['Login2']]);
    }

    public function LoginSpain()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(text(), 'Cerrar sesión') or contains(text(), 'CERRAR SESI')])[1]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[
                contains(text(), "Combinación de e-mail/contraseña incorrecta.")
                or contains(text(), "Contraseña incorrecta")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function LoginItaly()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(text(), 'Disconnetti')])[1]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('
                //div[contains(text(), "Combinazione e-mail/password errata, verifica i tuoi dati o")]
                | //div[contains(text(), "Password dimenticata")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function LoginUSA()
    {
        $this->logger->notice(__METHOD__);

        sleep(4);

        try {
            $logout = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Sign Out') or contains(text(), 'Se déconnecter')]"), 6, false);
            $error = $this->waitForElement(WebDriverBy::xpath('//p[@data-at="sign_in_error"]'), 0);

            // provider error workaround
            if ($error && strstr($error->getText(), 'Token expired or random number not match. Please try again.')) {
                $this->saveResponse();

                if ($this->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0)) {
                    $this->waitFor(function () {
                        return !$this->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                    }, 120);
                }

                $this->saveResponse();

                $formXpath = "//div[@id = 'modalDialog' or @id = 'modal0Dialog' or @id = 'modal1Dialog']";

                if ($btn = $this->waitForElement(WebDriverBy::xpath($formXpath . "//button[@type='submit']"), 0)) {
                    $this->logger->error("click Log-in one more time");
                    $btn->click();
                    $this->saveResponse();
                    $logout = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Sign Out') or contains(text(), 'Se déconnecter')]"), 6, false);
                    $this->saveResponse();
                }
            }// if ($res && strstr($res->getText(), 'Your request has not been processed successfully.'))

            // provider bug fix, page not loaded completely
            if (!$logout) {
                $cookies = $this->driver->manage()->getCookies();
                $k = 0;

                foreach ($cookies as $cookie) {
//                    $this->logger->debug(var_export($cookie, true), ['pre' => true]);
//                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], isset($cookie['expiry']) ? $cookie['expiry'] : null);
                    if ($cookie['name'] == 'DYN_USER_ID' || $cookie['name'] == 'DYN_USER_CONFIRM') {
                        $k++;

                        if ($k == 2) {
                            $logout = true;

                            break;
                        }
                    }// if ($cookie['name'] == 'DYN_USER_ID' || $cookie['name'] == 'DYN_USER_CONFIRM')
                }// foreach ($cookies as $cookie)
            }// if (!$logout)

            $this->saveResponse();

            if ($logout) {
                return true;
            }

            if ($error = $this->waitForElement(WebDriverBy::xpath('//p[@data-at="sign_in_error"]'), 0)) {
                $message = $error->getText();
                $this->logger->error("[Error]: {$message}");

                if (
                    strstr($message, 'User Id or password does not match for user')
                    || strstr($message, 'We\'re sorry, there is an error with your email and/or password.')
                    || strstr($message, 'Passwords must be 6 to 12 characters (letters or numbers) long')
                    || $message === 'Email not present'
                    || strstr($message, 'Were sorry, there is an error with your email and/or password.')
                    || strstr($message, 'The email was associated with account that has been deactivated or deleted.')
                    || strstr($message, 'There is an error with your email and/or password. ')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message === 'Oops! Something went wrong. Please try again later.') {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // Sorry, your account has been locked for security reasons. Please call Customer
                if (
                    strstr($message, 'Sorry, your account has been locked for security reasons. Please call Customer ')
                    || strstr($message, 'We have locked your account due to security concerns.')
                ) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                $this->DebugInfo = $message;

                if ($message == 'Token expired or random number not match. Please try again.') {
                    throw new CheckRetryNeededException(2, 10, $message);
                }

                return false;
            }

            // Register with Sephora
            // think you already registered for Beauty Insider in a Sephora store because we recognize your email address ... . Please fill out the information below to complete your profile.
            if ($this->waitForElement(WebDriverBy::xpath("
                    //h4[contains(text(), 'Register with Sephora')]
                    | //p[contains(text(), 'We think you already registered for Beauty Insider in a Sephora store because we recognize your email address')]
                "), 0)
            ) {
                $this->throwProfileUpdateMessageException();
            }

//        $result = $this->http->JsonLog();
//        ## Invalid credentials
//        if (isset($result->errors->global)) {
//            if (is_array($result->errors->global))
//                $error = implode(' ', $result->errors->global);
//            else
//                $error = $result->errors->global;
//            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
//        }
//        // We think you already registered for Beauty Insider in a Sephora store because we recognize your email address ... . Please fill out the information below to complete your profile.
//        if (isset($result->user->is_pos_member) && $result->user->is_pos_member == 'true'
//            && isset($result->user->is_store_bi_member) && $result->user->is_store_bi_member == 'true')
//            $this->throwProfileUpdateMessageException();

            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            $this->saveResponse();

            /*
            if ($this->http->currentUrl() == 'https://www.sephora.com/profile/me') {
                throw new CheckRetryNeededException(3, 3);
            }
            */
        } catch (NoSuchDriverException | UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        // Access is allowed
        //if ($this->http->FindPreg("/\"login_status\":\"loggedin\"/"))
//            return true;

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);

        return call_user_func([$this, "parse" . $this->AccountFields['Login2']]);
    }

    public function parseSpain()
    {
        $this->logger->notice(__METHOD__);
        $this->parseItaly();
    }

    public function parseItaly()
    {
        $this->logger->notice(__METHOD__);
        // Balance - ¡Has conseguido
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "card-status-label")]/span', null, true, '/([\-\d\.\,]+)\s*punt/i'));
        // Numero Carta Sephora / Tu número de Tarjeta Sephora
        $this->SetProperty("BeautyInsiderNumber", $this->http->FindSingleNode('//div[contains(@class, "card-number-label")]', null, true, "/\s+([\d\s]+)\s*$/"));
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode('//div[contains(@class, "card-status-label")]/span', null, true, "/Sephora\s*([^\d]+)\s+\d/"));

        // not a member
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->FindSingleNode(
                "//p[contains(text(), 'Vantaggi esclusivi ti aspettano con il Programma Fedeltà Sephora!')] 
                | //text()[starts-with(normalize-space(), 'Entra subito in un modo di consigli beauty e offerte personalizzate. Iscriviti al programma fedeltà Sephora!')]
                ")) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }

        // Name
        if ($this->AccountFields['Login2'] == 'Spain') {
            $this->http->GetURL("https://www.sephora.es/datos-personales");
        } else {
            $this->http->GetURL("https://www.sephora.it/dettagli-account/");
        }
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[contains(@id, '_firstname')]/@value") . ' ' . $this->http->FindSingleNode("//input[contains(@id, '_lastname') and not(contains(@id, '_lastname2'))]/@value"));
        $this->SetProperty("Name", beautifulName($name));
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'USA';
        }

        return $region;
    }

    private function parseUSAStatus($status)
    {
        $this->logger->notice(__METHOD__);

        if ($status === 'BI') {
            $status = 'Insider';
        } else {
            if ($status === 'ROUGE') {
                $status = 'Rouge';
            }
        }

        return $status;
    }

    private function parseUSA()
    {
        $this->logger->notice(__METHOD__);

        $browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($browser);
        $browser->setHttp2(true);
        // crocked server workaround
        $browser->setProxyParams($this->http->getProxyParams());
        $browser->setUserAgent($this->http->userAgent);

        try {
            $cookies = $this->driver->manage()->getCookies();
        } catch (UnknownServerException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        foreach ($cookies as $cookie) {
            $browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $userId = $browser->getCookieByName('DYN_USER_ID', 'www.sephora.com');

        if (!isset($userId)) {
            return;
        }

        $browser->RetryCount = 0;
        $browser->GetURL("https://www.sephora.com/api/users/profiles/current/full?includeTargeters=%2Fatg%2Fregistry%2FRepositoryTargeters%2FSephora%2FCCDynamicMessagingTargeter&includeApis=profile,basket,loves,shoppingList,targetersResult,targetedPromotion&cb=" . time(), [], 20);
        $browser->RetryCount = 2;

        if (strstr($browser->Error, 'Network error 28 - Operation timed out after')) {
            return;
        }

        $response = $browser->JsonLog(null, 3, true);
        $profile = ArrayVal($response, 'profile');
        // Full Name
        $this->SetProperty('Name', beautifulName(ArrayVal($profile, 'firstName') . ' ' . ArrayVal($profile, 'lastName')));

        $browser->GetURL('https://www.sephora.com/api/bi/profiles/' . $userId . '/points?source=profile');
        $response = $browser->JsonLog(null, 3, true);
        // Status - BI (INSIDER), VIB
        if ($status = ArrayVal($response, 'biStatus')) {
            $this->SetProperty('Status', $this->parseUSAStatus($status));
        }
        // Balance - New Balance
        $this->SetBalance(ArrayVal($response, 'beautyBankPoints'));
        // spend $... to reach VIB status.
        if ($amountToNextSegment = ArrayVal($response, 'amountToNextSegment')) {
            $this->SetProperty('ToNextLevel', '$' . ArrayVal($response, 'amountToNextSegment'));
        }
        // Status valid until
        if ($vibEndYear = ArrayVal($response, 'vibEndYear')) {
            $this->SetProperty('StatusValidUntil', '12/31/' . $vibEndYear);
        }
        // Next elite level
        if ($nextSegment = ArrayVal($response, 'nextSegment')) {
            $this->SetProperty('NextEliteLevel', $this->parseUSAStatus($nextSegment));
        }
        // Miles/Points to retain status - Spend $350 to keep your VIB status through 2018.
        if ($retainStatus = $browser->FindPreg('/Spend <span data\-price>(.+?)<\/span> to keep your.+?status through/smi')) {
            $this->SetProperty('PointsRetainStatus', $retainStatus);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Sorry, Beauty Insider is currently unavailable.
            if ($message = $browser->FindSingleNode("(//p[contains(text(), 'Sorry, Beauty Insider is currently unavailable')])[1]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Sorry, BI is temporarily unreachable.
            if ($message = $browser->FindSingleNode("(//p[contains(text(), 'Sorry, BI is temporarily unreachable.')])[1]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    private function sendSensorData()
    {
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $referer = $this->http->currentUrl();

        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return;
        }

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
        ];
        $sensorData = [
            'sensor_data' => "7a74G7m23Vrp0o5c9269131.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400110,1004940,1536,871,1536,960,1536,441,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.225307038112,813075502470,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,113,113,0;0,-1,0,0,1687,-1,0;0,2,0,0,3449,3449,0;1,0,0,0,3510,3510,0;-1,2,-94,-102,0,-1,0,0,113,113,0;0,-1,0,0,1687,-1,0;0,2,0,0,3449,3449,0;1,0,0,0,3510,3510,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.sephora.it/accedi/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1626151004940,-999999,17396,0,0,2899,0,0,2,0,0,55DCCC6A45BE720B9A0C12AB35045768~-1~YAAQBWAZuMdgy4R6AQAAjisnngaX257YiZBTBdY+i2CZsBVXdBlzNQShv8irr4BlzwDeRO3DEY7kiXDeL/HBgSjCox61Op/8LVZIpwoTeZHQtNzxGJ+aM4DGhrMNJvO5jy+G9ZBjOFDIF5bCE2R83fZea0G8+3B5ByZuSqFLnAAR+fsCnrPM9R5k2kfNqdJJX686EC6O/72AzI5nSUGZF604+qq9bzA+LTXZpUbnUGdoE0DX2pFzpQ6JoUarT3Jp1sKROevw1BE2eYpnY0stNgG3V7FYZkg5LkT4FjEB97k1wgMP9WtE54195cMXLBqy6qpje+wGchWyiTQVTIL3gW8g8z9vlwLP1GdOa0+YH3BIV/Z0VArhrnZmGZz4KpVUiVQ9RmJ3/tv2wQ==~-1~-1~-1,35846,-1,-1,30261693,PiZtE,59568,18,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,75370650-1,2,-94,-118,90060-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
        ];
        $this->http->NormalizeURL($sensorPostUrl);
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => "7a74G7m23Vrp0o5c9269131.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400110,1004940,1536,871,1536,960,1536,441,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.631063023315,813075502470,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,113,113,0;0,-1,0,0,1687,-1,0;0,2,0,0,3449,3449,0;1,0,0,0,3510,3510,0;-1,2,-94,-102,0,0,0,0,113,113,0;0,-1,0,0,1687,-1,0;0,2,0,0,3449,3449,0;1,0,0,0,3510,3510,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.sephora.it/accedi/-1,2,-94,-115,1,32,32,0,0,0,0,637,0,1626151004940,10,17396,0,0,2899,0,0,638,0,0,55DCCC6A45BE720B9A0C12AB35045768~-1~YAAQBWAZuM9gy4R6AQAALi0nngZJpdOQXcQs43VFD7K/SZ8iVSYGmKbAyKo1UwCXA4u1Si2z/Rh/qLzH6jDc7tQ8Aya8q+vnkZf2ob3vTedMvHGHrWr1zSaN/AT3orqshBNQIk0PkJXCb8GA5aNEFfA3S8kviSKxym3ziivxhX39DG8NnXcMLSNRBL2JnP0G90C2szo4OtITmltKJ5EDsu55/66+VeVpoo8jWV2+WfRvxLzYztcB2YZFw5Ri0e/p6himYognfV3mlhbS36wv3IBqqCo4khK9qqDp3vvMZ1H73MsUq2rK4NZDKya/gAj1mh7SCtnIv8D/mvpBu8x7jmD8dMJ8Y0/si1ZlB5xOh/7NaIlR27HZFUtv35mNyhWKL4+oWWjafL+BXQ==~-1~||1-uBabUAvphU-1-10-1000-2||~-1,38419,660,-994064974,30261693,PiZtE,82994,107,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,40,40,40,40,60,40,40,0,0,0,0,0,0,180,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,75370650-1,2,-94,-118,95700-1,2,-94,-129,15a370dacd74a1608e95b1ad35460ec0eb6b480515ce6f1db86ef72cfc16d856,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;16;6;0",
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);

        $this->http->RetryCount = 2;
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);
    }

    private function seleniumSendSensorData($currentUrl)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();

            switch (rand(0, 2)) {
                case 0:
                    $selenium->useFirefox();

                    break;

                case 1:
                    $selenium->useChromium();

                    break;

                default:
                    $selenium->useGoogleChrome();
            }

            $selenium->disableImages();
            $selenium->useCache();
//            $selenium->usePacFile(false);
            $selenium->keepCookies(false);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL($currentUrl);

            /*
            if ($this->AccountFields['Login2'] == 'Spain') {
                $login = $selenium->waitForElement(WebDriverBy::xpath("//form[@id = 'dwfrm_login']//input[contains(@name, 'dwfrm_login_username_')]"), 5);
                $pass = $selenium->waitForElement(WebDriverBy::xpath("//form[@id = 'dwfrm_login']//input[contains(@name, 'dwfrm_login_password_')]"), 0);
                $loginSubmit = $selenium->waitForElement(WebDriverBy::id("loginSubmit"), 0);
                // save page to logs
                $this->savePageToLogs($selenium);

                $selenium->driver->executeScript("
                    try {
                        $('#tc-privacy-wrapper').remove();
                        $('#tc-privacy-overlay-banner').remove();
                    } catch (e) {}
                ");

                if (!$login || !$pass || !$loginSubmit) {
                    return false;
                }

                $login->sendKeys($this->AccountFields['Login']);
                $pass->sendKeys($this->AccountFields['Pass']);
                $loginSubmit->click();
            } else {
            */
            $acceptCookiesBtn = $selenium->waitForElement(WebDriverBy::id('footer_tc_privacy_button_2'), 5);

            if (isset($acceptCookiesBtn)) {
                $acceptCookiesBtn->click();
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@name, 'dwfrm_crmsephoracard_email')]"), 5);
            $loginSubmit = $selenium->waitForElement(WebDriverBy::xpath("//button[@name = 'dwfrm_crmsephoracard_confirm']"), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$login || !$loginSubmit) {
                return false;
            }

            $selenium->driver->executeScript("
                try {
                    $('#privacy-overlay').remove();
                    $('#privacy-container').remove();
                    $('#tc-privacy-wrapper').remove();
                    $('#tc-privacy-overlay-banner').remove();
                } catch (e) {}
            ");
            $this->savePageToLogs($selenium);

            $this->logger->debug("set login");

            try {
                $mover = new MouseMover($selenium->driver);
                $mover->logger = $this->logger;
                $mover->sendKeys($login, $this->AccountFields['Login'], 5);
//                $login->sendKeys($this->AccountFields['Login']);
            } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }

            sleep(1);
            $this->logger->debug("click submit");
//            $loginSubmit->click();
            $selenium->driver->executeScript('document.querySelector("button[name=dwfrm_crmsephoracard_confirm]").click();');

            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@name, 'dwfrm_login_password_')]"), 5);

            if (!$pass) {
                $selenium->driver->executeScript("
                    try {
                        $('#privacy-overlay').remove();
                        $('#privacy-container').remove();
                        $('#tc-privacy-wrapper').remove();
                        $('#tc-privacy-overlay-banner').remove();
                    } catch (e) {}
                ");
                $this->savePageToLogs($selenium);
                $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@name, 'dwfrm_login_password_')]"), 5);
            }

            $passSubmit = $selenium->waitForElement(WebDriverBy::xpath("//button[@name = 'dwfrm_login_login']"), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$passSubmit) {
                if ($this->http->FindSingleNode('//strong[
                            contains(text(), "Sei già iscritt* al programma fedeltà Sephora?")
                            or contains(text(), "¿Ya eres miembro de nuestro programa de fidelidad?")
                        ]')
                    ) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindSingleNode('//input[contains(@name, "dwfrm_crmsephoracard_email") and contains(@data-country-error-message, "Tu cuenta es portuguesa, accede a")]/@data-country-error-message')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                return false;
            }

            $pass->sendKeys($this->AccountFields['Pass']);
            $passSubmit->click();
            /*
            }
            */

            $selenium->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "card-number")]/strong
                | //div[contains(text(), "Combinazione e-mail/password errata, verifica i tuoi dati o")]
                | //div[contains(text(), "Password dimenticata")]
                | //div[contains(text(), "Contraseña incorrecta")]
                | //div[contains(text(), "Combinación de e-mail/contraseña incorrecta.")]
                | //strong[contains(text(), "Sei già iscritt* al programma fedeltà Sephora?")]
            '), 10);
            $this->savePageToLogs($selenium);

            try {
                $cookies = $selenium->driver->manage()->getCookies();
            } catch (InvalidArgumentException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                // "InvalidArgumentException: Cookie name should be non-empty trace" workaround
                $cookies = $selenium->http->driver->browserCommunicator->getCookies();
            }

            foreach ($cookies as $cookie) {
//                if (!in_array($cookie['name'], [
//                    'bm_sz',
//                    '_abck',
//                ])) {
//                    continue;
//                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            $result = true;
        } catch (TimeOutException | NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $result;
    }
}
