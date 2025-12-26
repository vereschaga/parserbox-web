<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPayless extends TAccountChecker
{
    // see perfectdrive, payless, avis (USA, Australia)

    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $customerInfo;
    private $response;

    /*
    function IsLoggedIn() {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.paylesscar.com/profile/update.do?action=view", [], 20);
        $this->http->RetryCount = 2;
        // Access is allowed
        if ($this->http->FindNodes('//a[contains(@href, "signout")]/@href'))
            return true;

        return false;
    }
    */

    private $typeForm = 'old';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyDOP());
        $this->setProxyGoProxies();
        $this->http->setUserAgent("AwardWallet Service. Contact us at awardwallet.com/contact");
//        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.paylesscar.com/profile/login.do');

        if ($this->http->ParseForm("profileForm")) {
            $this->http->SetInputValue('userName', $this->AccountFields['Login']);
            $this->http->SetInputValue('psw', $this->AccountFields['Pass']);
        } elseif ($this->http->ParseForm('loginForm')) {
            $this->typeForm = 'new';
        } else {
            return $this->checkErrors();
        }

        $this->selenium();

        return true;

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        if ($this->typeForm == 'old') {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->SetInputValue('recaptchaValue', $captcha);
        } else {
//            return $this->selenium($captcha);

            $this->http->setDefaultHeader('Content-Type', 'application/json');
            $this->http->setDefaultHeader('deviceType', 'bigbrowser');
            $this->http->setDefaultHeader('domain', 'us');
            $this->http->setDefaultHeader('Host', 'www.paylesscar.com');
            $this->http->setDefaultHeader('locale', 'en');
            $this->http->setDefaultHeader('userName', 'PAYLESSCOM');
            $this->http->setDefaultHeader('password', 'PAYLESSCOM');
            $this->http->setDefaultHeader('bookingType', 'car');
            $this->http->setDefaultHeader('channel', 'Digital');

            $this->http->RetryCount = 0;
            $this->http->GetURL('https://www.paylesscar.com/libs/granite/csrf/token.json');
            $this->http->RetryCount = 2;

            $this->http->GetURL('https://www.paylesscar.com/webapi/init');
            $digitalToken = $this->http->Response['headers']['digital_token'] ?? null;

            $this->http->setDefaultHeader('DIGITAL_TOKEN', $digitalToken);
//            $this->http->setDefaultHeader('AVIS_XSRF', $this->http->getCookieByName('AVIS_XSRF'));
//            $this->http->setDefaultHeader('CSRF-Token', 'undefined');

//            $this->seleniuNew();
//
//            return true;
            $this->http->setDefaultHeader('g-recaptcha-response', $captcha);

            $data = [
                "uName"          => $this->AccountFields['Login'],
                "password"       => $this->AccountFields['Pass'],
                "rememberMe"     => true,
                "displayControl" => [
                    "variation" => "Big",
                    "closeBtn"  => true,
                ],
            ];
            $this->http->PostURL('https://www.paylesscar.com/webapi/profile/login', json_encode($data), ["Accept" => "application/json, text/plain, */*"]);
        }

        $this->logger->debug('Type Form: ' . $this->typeForm);

        return true;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefoxPlaywright();
//            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['linux']];
//            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->http->GetURL("https://www.paylesscar.com/profile/login.do");

            $selenium->waitForElement(WebDriverBy::id('username'), 7, false);

            $this->logger->notice("angular.reloadWithDebugInfo();");

            $selenium->driver->executeScript("
                window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;
                window.location.reload();
            ");

            $this->logger->notice("delay");
            sleep(7);
            $selenium->waitForElement(WebDriverBy::id('username'), 7, false);
            // save page to logs
            $this->savePageToLogs($selenium);

            $selenium->driver->executeScript("
                var scope = angular.element(document.querySelector('.login form#loginForm')).scope();
                scope.vm.loginModel.uName = '{$this->AccountFields['Login']}';
                scope.vm.loginModel.password = '{$this->AccountFields['Pass']}';
                scope.vm.loginInProgress = true;
                $('button[name=\"button\"]').click();
            ");
//                scope.vm.recaptcha = '{$captcha}';
//                scope.vm.getLogin.successLogin(scope.vm);
            $this->savePageToLogs($selenium);
            /*
            $selenium->driver->executeScript("
                var form = $('.login form#loginForm');
                var login = $('input#username', form);
                login.val('{$this->AccountFields['Login']}');
                angular.element(login).trigger('change');
                var password = $('input#password', form);
                password.val('{$this->AccountFields['Pass']}');
                angular.element(password).trigger('change');
                var recaptcha = $('input#recaptcha', form);
                recaptcha.val('{$captcha}');
                angular.element(recaptcha).trigger('change');

                $('input[type=\"submit\"]', form).trigger('click');
            ");
            */

            if (!$selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "My Profile")]'), 7)) {
                // save page to logs
                $this->savePageToLogs($selenium);

                $selenium->driver->executeScript("
                    var scope = angular.element(document.querySelector('.login form#loginForm')).scope();
                    if (typeof(scope) != 'undefined' && typeof(scope.vm.prod) != 'undefined') {
                        localStorage.setItem('response', JSON.stringify(scope.vm.prod));
                    }
                    else
                        localStorage.setItem('response', null);
                ");
                $this->response = $selenium->driver->executeScript("return localStorage.getItem('response');");
                $this->logger->info("[Response]: " . $this->response);
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')
                || strstr($e->getMessage(), 'timeout ')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException exception: " . $e->getMessage());
            $this->logger->debug($selenium->driver->switchTo()->alert()->getText());
            // captcha problems workaround
            if (strstr($selenium->driver->switchTo()->alert()->getText(), 'Cannot contact reCAPTCHA')) {
                $retry = true;
            }
            $selenium->driver->switchTo()->alert()->accept();
            $this->logger->notice("alert, accept");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo:

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(4, 1);
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're upgrading PaylessCar.com.
        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "We\'re upgrading PaylessCar.com.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We are currently undergoing some maintenance
        if ($this->http->FindPreg('/(Service Unavailable)/ims')) {
            $this->http->GetURL("http://paylesscar.com/");

            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently undergoing some maintenance')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
        // An error has occurred while processing your request
        if (($message = $this->http->FindPreg("/<h2>(An error has occurred while processing your request\.)<\/h2>/ims")) && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            throw new CheckRetryNeededException(3, 7, $message . " Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if ($this->typeForm == 'old') {
            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
        } else {
            if ($this->http->FindSingleNode('//button[contains(text(), "Sign Out")]')) {
                $this->captchaReporting($this->recognizer);

                return true;
            }

            $response = $this->http->JsonLog($this->response ?? null);

            if (isset($response->customerInfo->wizardNumberMasked)
                && isset($response->customerInfo->firstName) && isset($response->customerInfo->enrollmentDate)) {
                $this->captchaReporting($this->recognizer);
                $this->customerInfo = $response->customerInfo;

                return true;
            }

            $type = $response->errorList[0]->type ?? $this->http->FindPreg('/"type":"(ERROR)","code":"/');
            $code = $response->errorList[0]->code ?? $this->http->FindPreg('/"type":"ERROR","code":"(\d+)"/');
            $message = $response->errorList[0]->message ?? $this->http->FindPreg('/"type":"ERROR","code":"\d+","message":"([^\"]+)"/');

            if ($type == 'ERROR') {
                switch ($code) {
                    // Username and Password provided do not match our records
                    case '31102':
                        $this->captchaReporting($this->recognizer);

                        throw new CheckException('Username and Password provided do not match our records', ACCOUNT_INVALID_PASSWORD);

                        break;
                    // We are sorry, the site has not properly responded to your request. If the problem persists, please contact Payless.
                    case '80010':
                        $this->captchaReporting($this->recognizer);

                        throw new CheckException('We are sorry, the site has not properly responded to your request. If the problem persists, please contact Payless.', ACCOUNT_PROVIDER_ERROR);

                        break;
                    // The information provided does not match our records. Please ensure that the information you have entered is correct and try again.
                    case '13036':
                    case '30034':
                        $this->captchaReporting($this->recognizer);

                        throw new CheckException('The information provided does not match our records. Please ensure that the information you have entered is correct and try again.', ACCOUNT_INVALID_PASSWORD);

                        break;
                    // For security reasons, please reset your password. A reset link has been sent to your email X******X@xxx.COM
                    case '30366':
                        $this->captchaReporting($this->recognizer);

                        if ($message) {
                            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                        }

                        break;

                    case '30033':
                        $this->captchaReporting($this->recognizer);
                        // "Your account has been disabled. You will be receiving an email shortly explaining how you can reset your profile information. For any assistance, please call customer service at 1-800-729-5377",
                        throw new CheckException($message, ACCOUNT_LOCKOUT);

                        break;

                    case '06016':
                        $this->captchaReporting($this->recognizer, false);

                        throw new CheckException('We are sorry, the site has not properly responded to your request. Please try again. If the problem persists, please Contact Us.', ACCOUNT_PROVIDER_ERROR);

                        break;
                }// switch ($code)
            }// if ($type == 'ERROR')
        }
        $this->http->RetryCount = 2;

        // Access is allowed
        if ($this->http->FindNodes('//a[contains(@href, "signout")]/@href')) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        // Username and Password provided do not match our records
        if (
            $message = $this->http->FindSingleNode("//li[contains(text(), 'Username and Password provided do not match our records')]")
            ?? $this->http->FindSingleNode("//strong[contains(text(), 'For security reasons, please reset your password.')]")
            ?? $this->http->FindSingleNode("//div[contains(@class, 'fade popup-fix in')]//strong[contains(text(), 'Your password has expired.')]")
            ?? $this->http->FindSingleNode("//span[contains(text(), 'Authorization error: Invalid username/password')]")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Something went wrong. Please try again later.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Something went wrong. Please try again later.')]")
            ?? $this->http->FindSingleNode("//p[contains(text(), 'We apologize, we cannot login your profile at this time.')]")
            ?? $this->http->FindSingleNode("//div[not(contains(@class, 'ng-hide'))]/span[contains(text(), 'We are Sorry, the site has not properly responded to your request. If the problem persists, please contact Payless')]")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->customerInfo->firstName ?? $this->http->FindSingleNode("//p[contains(text(), 'Member since')]/preceding-sibling::h4") ?? $this->http->FindSingleNode("(//a[contains(text(), 'Welcome,')])[1]", null, true, "/\,\s*([^!.]+)/")));
        // Perks ID
        $this->SetProperty("PerksID", $this->customerInfo->wizardNumberMasked ?? $this->http->FindSingleNode("(//label[contains(text(), 'Perks ID')]/following-sibling::p)[1]") ?? $this->http->FindSingleNode("//p[span[contains(text(), 'Perks ID')]]/text()[last()]"));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//p[contains(text(), 'Member since')]", null, true, "/Member since\s*([^<]+)/"));

        if (isset($this->customerInfo->enrollmentDate)) {
            $this->SetProperty("MemberSince", date('F Y', strtotime($this->customerInfo->enrollmentDate, false)));
        }

        if (!empty($this->Properties['PerksID']) && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function GetConfirmationFields()
    {
        return [
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "ConfNo"   => [
                "Caption"  => "Confirmation number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.paylesscar.com/en/reservation/view-modify-cancel";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $this->sendNotification("failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}<br/>Email: {$arFields["Email"]}");

        if (!$this->http->ParseForm("res-viewModifyForm")) {
            $this->sendNotification("failed to retrieve itinerary by conf #", 'all', true,
                "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}<br/>Email: {$arFields["Email"]}");

            return null;
        }
        $this->http->setDefaultHeader('Content-Type', 'application/json');
        $this->http->setDefaultHeader('deviceType', 'bigbrowser');
        $this->http->setDefaultHeader('domain', 'us');
        $this->http->setDefaultHeader('Host', 'www.paylesscar.com');
        $this->http->setDefaultHeader('locale', 'en');
        $this->http->setDefaultHeader('userName', 'PAYLESSCOM');
        $this->http->setDefaultHeader('password', 'PAYLESSCOM');

        $this->http->GetURL('https://www.paylesscar.com/webapi/init');

        $this->http->setDefaultHeader('AVIS_XSRF', $this->http->getCookieByName('AVIS_XSRF'));
        $this->http->setDefaultHeader('CSRF-Token', 'undefined');
        $data = [
            "confirmationNumber" => $arFields["ConfNo"],
            "lastName"           => $arFields["LastName"],
        ];
        $this->http->PostURL("https://www.paylesscar.com/webapi/reservation/view", json_encode($data));
        $res = $this->ParseItinerary();

        if (empty($res)) {
            $this->sendNotification("Failed to retrieve itinerary by conf #", 'all', true,
                "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}<br/>Email: {$arFields["Email"]}");

            return null;
        } elseif (is_string($res)) {
            return $res;
        }

        $it = $res;

        return null;
    }

    public function ParseItinerary()
    {
        $response = $this->http->JsonLog();
        $reservationSummary = $response->reservationSummary ?? [];
        $error = $response->errorList[0]->message ?? null;

        if (empty($error) && ($response->fieldErrors[0]->classifier ?? null) == 'invalid') {
            $filedName = $response->fieldErrors[0]->fieldName ?? null;

            if ($filedName == 'confirmationNumber') {
                $error = 'Please enter a valid confirmation number.';
            }
        }

        if (empty($reservationSummary) || $error) {
            return $error;
        }

        $result = ['Kind' => 'L'];

        if (isset($reservationSummary->personalInfo->firstName->value, $reservationSummary->personalInfo->lastName->value)) {
            $result['RenterName'] = $reservationSummary->personalInfo->firstName->value . " " . $reservationSummary->personalInfo->lastName->value;
        }
        $result['Number'] = $reservationSummary->confirmationNumber ?? null;

        $result['CarType'] = $reservationSummary->vehicle->carGroup ?? null;
        $result['CarModel'] = $reservationSummary->vehicle->makeModel ?? null;
        $result['CarImageUrl'] =
            isset($reservationSummary->vehicle->image)
                ? "https://www.paylesscar.com/content/dam/cars/xl/{$reservationSummary->vehicle->makeYr}/{$reservationSummary->vehicle->makeNme}/{$reservationSummary->vehicle->image}" : null;

        $result['TotalCharge'] = $reservationSummary->rateSummary->estimatedTotal ?? null;
        $result['Currency'] = $reservationSummary->rateSummary->currencyCode ?? null;
        $result['BaseFare'] = $reservationSummary->rateSummary->baseRate ?? null;
        $result['TotalTaxAmount'] = $reservationSummary->rateSummary->totalTax ?? null;

        if (isset($reservationSummary->pickLoc)) {
            $result['PickupLocation'] = $reservationSummary->pickLoc->name . ", " . $reservationSummary->pickLoc->locationCode;
            $result['PickupDatetime'] = strtotime($reservationSummary->pickDate . " " . $reservationSummary->pickTime);
            $result['PickupHours'] = $reservationSummary->pickLoc->hoursOfOperation ?? null;
            $result['PickupPhone'] = $reservationSummary->pickLoc->phoneNumber ?? null;
        }

        if (isset($reservationSummary->dropLoc)) {
            $result['DropoffDatetime'] = strtotime($reservationSummary->dropDate . " " . $reservationSummary->dropTime);
            $result['DropoffLocation'] = $reservationSummary->dropLoc->name . ", " . $reservationSummary->dropLoc->locationCode;
            $result['DropoffHours'] = $reservationSummary->dropLoc->hoursOfOperation ?? null;
            $result['DropoffPhone'] = $reservationSummary->dropLoc->phoneNumber ?? null;
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@name = 'profileForm']//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
        $extParameters = [];

        if (!$key) {
            $key = $this->http->FindPreg('/var enterpriseCaptchaSiteKey = "(.+?)";/');

            if (!$key) {
                return false;
            }
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $this->recognizer->RecognizeTimeout = 120;
            $postData = [
                "type"         => "RecaptchaV3TaskProxyless",
                "websiteURL"   => $this->http->currentUrl(),
                "websiteKey"   => $key,
                "minScore"     => 0.9,
                //                "pageAction"   => "login",
                "isEnterprise" => true,
            ];

            return $this->recognizeAntiCaptcha($this->recognizer, $postData);
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
}
