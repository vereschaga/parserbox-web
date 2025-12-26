<?php

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common\Flight;
use Facebook\WebDriver\Exception\NoSuchCookieException;

class TAccountCheckerS7 extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    use PriceTools;

    private $currentItin = 0;
    private $tripsContent = null;
    private $noItins = false;
    private $userInfo = null;

    /** @var HttpBrowser */
    private $curl = null;

    private $pnrCache = [];

    private $profileId = null;

    private $endHistory = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();

        if ($this->attempt == 2) {
            $this->useChromium();
        } else {
            $this->useFirefox(\SeleniumFinderRequest::FIREFOX_100);
            //$this->setKeepProfile(true);
            $this->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);
        }
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            [1920, 1080],
        ];

        if (!isset($this->State['Resolution']) || $this->attempt > 1) {
            $this->logger->notice("set new resolution");
            $resolution = $resolutions[array_rand($resolutions)];
            $this->State['Resolution'] = $resolution;
        } else {
            $this->logger->notice("get resolution from State");
            $resolution = $this->State['Resolution'];
            $this->logger->notice("restored resolution: " . join('x', $resolution));
        }
        $this->setScreenResolution($resolution);

        $this->http->saveScreenshots = true;

        //$this->disableImages();
        //$this->useCache();

        if ($this->attempt == 0) {// lock workaround
            /*
            $this->http->SetProxy($this->proxyDOP());
            */
            $this->setProxyBrightData(null, 'static', 'de');
        } else {
            $this->http->SetProxy($this->proxyReCaptcha());
        }
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL("https://www.s7.ru/?language_id=1");
        } catch (TimeOutException | ScriptTimeoutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        try {
            $ssdcp = $this->driver->manage()->getCookieNamed('ssdcp')['value'] ?? null;
            $this->logger->info("ssdcp auth cookie: {$ssdcp}");
        } catch (NoSuchCookieException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }
        $this->setupCurl();

        $this->curl->RetryCount = 0;
        $this->curl->GetURL("https://www.s7.ru/dotCMS/priority/ajaxProfileService?dispatch=getUserInfo&_=" . date("UB"), [], 20);
        $this->curl->RetryCount = 2;
        $data = $this->curl->JsonLog(null, 3);

        if (isset($data->c->cardNumber)) {
            $this->userInfo = $data;

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $retry = false;

        if (empty($this->AccountFields['Login'])) {
            throw new CheckException('An incorrect login or password.', ACCOUNT_INVALID_PASSWORD);
        }

        try {
            $this->http->GetURL("https://www.s7.ru/?language_id=1");

            $loginBtnXpath = '//a[contains(text(), "Log in")]
                | //div[contains(text(), "Log in")]
                | //div[p[contains(text(), "Log in")]]
                | //div[contains(@class, "DS__HeaderCanary__login")]';
            $badProxyXpath = '//h1[contains(text(), "Access Denied")]
                | //h1[contains(text(), "404 Not Found")]
                | //iframe[contains(text(), "Request unsuccessful. Incapsula incident ID")]';
            $securityChallengeXpath = '//iframe[@id = "sec-cpt-if"]';

            $el = $this->waitForElement(WebDriverBy::xpath("$loginBtnXpath\r\n\r\n| $badProxyXpath\r\n\r\n| $securityChallengeXpath"), 10);

            if (isset($el) && $el->getAttribute('id') === 'sec-cpt-if') {
                $secSuccess = $this->waitFor(function () {
                    return is_null($this->waitForElement(WebDriverBy::xpath('//iframe[@id = "sec-cpt-if"]'), 0));
                }, 120);
                $this->saveToLogs();

                if (!$secSuccess) {
                    $this->logger->error($this->DebugInfo = 'Sec challenge went wrong');

                    if ($this->http->FindSingleNode('//title[contains(text(), "Challenge Validation")]')) {
                        throw new CheckRetryNeededException();
                    }

                    return $this->checkErrors();
                }
            }

            $this->saveToLogs();
            $menu = $this->waitForElement(WebDriverBy::xpath($loginBtnXpath), 11);

            $this->logger->debug("close Google popup");
            $this->driver->executeScript("var popup = document.querySelector('[title=\"Sign in with Google Dialog\"]'); if (popup) popup.style = \"display: none;\";");

            $noSubscribe = $this->waitForElement(WebDriverBy::xpath('//div[@id = "onesignal-slidedown-container"]//button[@id = "onesignal-slidedown-cancel-button"]'), 2);
            $this->saveToLogs();

            if (!$menu && !$noSubscribe) { // todo
                $this->logger->error("something went wrong");
                // 404 Not Found
                if (
                    $this->http->FindSingleNode($badProxyXpath)
                ) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                if (
                    $this->http->FindSingleNode("
                        //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                        | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                    ")
                ) {
                    $retry = true;
                }

                $this->saveToLogs();

                if (!$menu && !$this->waitForElement(WebDriverBy::xpath('//input[@name = "login"]'), 0)) {
                    return false;
                }
            } else {
                if ($noSubscribe) {
                    $this->logger->debug("click by no Subscribe");
                    $noSubscribe->click();
                    sleep(1);
                    $this->saveToLogs();
                    $menu = $this->waitForElement(WebDriverBy::xpath($loginBtnXpath), 10);

                    if (!$menu) {
                        return false;
                    }
                }
                $this->logger->debug("click by menu");

                try {
                    $menu->click();
                } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                    $this->logger->error("ElementClickInterceptedException: "/* . $e->getMessage()*/);
                    $this->logger->debug("click by js injection");
                    $this->driver->executeScript("var btn = document.querySelector('.DS__HeaderCanary__user_info'); if (btn) btn.click();");
                }
            }

            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "usernameToSiteHeader"] | //div[contains(text(), "Email/Phone number")]/preceding-sibling::input[1] | //input[@name = "login"]'), 10);
            $this->saveToLogs();
            // password
            $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "passwordToSiteHeader"] | //div[contains(text(), "Password/PIN")]/preceding-sibling::input[1] | //input[@name = "password"]'), 0);
            // Sign In
            $button = $this->waitForElement(WebDriverBy::xpath('//button[@type = "submit" and contains(@class, "DS__Button__block")]'), 0, false);
            $this->saveToLogs();

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("something went wrong");

                return false;
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->logger->debug("click");
            $this->saveToLogs();

//            $this->driver->executeScript('
//                let oldXHROpen = window.XMLHttpRequest.prototype.open;
//                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
//                    this.addEventListener("load", function() {
//                        if (url == "https://myprofile.s7.ru/auth/auth/api/profiles/tickets") {
//                            localStorage.setItem("responseData", this.responseText);
//                        }
//                    });
//                    return oldXHROpen.apply(this, arguments);
//                };
//            ');

            $button->click();

            sleep(5);
            $userProfileBtnXpath = '
                //div[contains(@class, "profile-dropdown")]
                | //div[contains(@class, "DS__HeaderCanary__user_info")]          
                | //p[contains(text(), "Отправили письмо с кодом на почту") or contains(text(), "Отправили СМС с кодом на номер")]          
            ';
            $onProfile = $this->waitForElement(WebDriverBy::xpath($userProfileBtnXpath), 10, false);
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            $this->saveToLogs();

            if (!$onProfile) {
                $message = $this->waitForElement(WebDriverBy::xpath('
                    //div[contains(@class, "Notification__icon-error")]/following-sibling::span/div
                    | //div[contains(@class, "DS__StatusMessage__view_error")]//div[contains(@class, "DS__Text__display_block") or contains(@class, "DS__TextBase__size_m")]
                    | //div[contains(@class, "DS__FieldTooltip__invalid")]//p[contains(@class, "DS__Text__display_block")]
                    | //div[contains(@class, "DS__Tooltip__invalid")]
                    | //div[contains(text(), "Access to profile blocked temporary due to many failed attempts")]
                '), 0);

//                $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
//                $this->logger->info("[Form responseData]: " . $responseData);

                if (
                    strpos($this->http->currentUrl(), 'https://myprofile.s7.ru/login?redirect=https://www.s7.ru') !== false
                    || (
                        $message
                        && $message->getText() === ''
                        && $this->http->currentUrl() == 'https://myprofile.s7.ru/login?redirect=https%3A%2F%2Fwww.s7.ru'
                    )
                    || (
                        !$message
                        && in_array($this->http->currentUrl(), ['https://www.s7.ru/?language_id=1', 'https://www.s7.ru/?language_id=1&utm_referrer='])
                    )
                ) {
                    if (!$this->waitForElement(WebDriverBy::xpath('//input[@name = "login"]'), 0) || !$message) {
                        $this->http->removeCookies();
                        $this->http->GetURL("https://myprofile.s7.ru/login?redirect=https%3A%2F%2Fwww.s7.ru%2F");
                    }

                    $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "login"]'), 7);
                    $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
                    // Sign In
                    $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "DS__Button__theme_b2c")]'), 0);
                    $this->saveToLogs();

                    if ($loginInput && $passwordInput && $button) {
                        $loginInput->sendKeys($this->AccountFields['Login']);
                        $passwordInput->sendKeys($this->AccountFields['Pass']);
                        $button->click();
                    } elseif ($this->http->FindPreg('/Request unsuccessful. Incapsula incident ID/')) {
                        if ($iframe = $this->waitForElement(WebDriverBy::xpath("//iframe[@id = 'main-iframe']"), 0)) {
                            $this->driver->switchTo()->frame($iframe);
                            $this->saveToLogs();
                        }

                        throw new CheckRetryNeededException(3, 1);
                    }
                    $onProfile = $this->waitForElement(WebDriverBy::xpath($userProfileBtnXpath), 5, false);
                    $this->saveToLogs();
                }

                $message = $this->waitForElement(WebDriverBy::xpath('
                    //div[contains(@class, "Notification__icon-error")]/following-sibling::span/div
                    | //div[contains(@class, "DS__Banner__icon_error")]//div[contains(@class, "DS__Text__noIndent")]
                    | //div[contains(@class, "DS__Banner__Banner_icon_error")]
                    | //div[contains(@class, "DS__Textfield__Tooltip DS__Textfield__Tooltip_invalid")]
                    | //div[contains(@class, "DS__StatusMessage__root DS__StatusMessage__view_error")]
                    | //div[contains(@class, "DS__Tooltip__invalid")]
                    | //div[contains(@class, "DS__FieldTooltip__invalid")]
                    | //p[contains(text(), "Your profile is locked")] | //p[contains(text(), "Your account is temporarily locked")]
                    | //div[contains(text(), "Something went wrong. Please, log-in one more time.")]
                    | //div[contains(text(), "Access to profile blocked temporary due to many failed attempts")]
                    | //div[contains(text(), "Доступ в личный кабинет временно заблокирован ")]
                '), 0);

                if (!$onProfile && $message) {
                    $error = $message->getText();
                    $this->logger->error("[Error]: {$error}");

                    if (
                        strstr($error, 'Неправильный логин или пароль.')
                        || strstr($error, 'Wrong credentials.')
                        || strstr($error, 'An incorrect login or password. You have ')
                        || strstr($error, 'You entered your S7 Priority card PIN as a password, but it doesn\'t work like that. Enter password or recover it.')
                        || strstr($error, 'Вы указали PIN от карты S7 Priority в качестве пароля. Но так не работает. Введите пароль или восстановите его.')
                        // Похоже, ваш пароль устарел. Чтобы сменить пароль, воспользуйтесь формой восстановления.
                        || strstr($error, 'Похоже, ваш пароль устарел. Чтобы сменить пароль,')
                        || strstr($error, 'Looks like your password has expired. To change password, please use the recovery form')
                        || strstr($error, 'Номер карты участника S7 Priority не существует или заблокирован. Подробную информацию вы можете получить в Контактном Центре.')
                        || strstr($error, 'Профиль ' . $this->AccountFields['Login'] . ' закрыт. Подробную информацию вы можете получить в Контактном центре')
                        || $error == 'Некорректный формат электронной почты'
                        || $error == 'Invalid email format'
                        || $error == 'The minimum amount of digits in the number is 9'
                        || $error == 'Минимальное количество цифр в номере — 9'
                    ) {
                        throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
                    }

                    // Something went wrong. Please, log-in one more time.
                    if (
                        strstr($error, 'Something went wrong. Please, log-in one more time.')
                        || strstr($error, 'Ваш контакт заблокирован, так как вы или кто-то другой много раз пытался отправить сообщение')
                    ) {
                        throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
                    }
                    // Профиль заблокирован, так как вы или кто-то другой много раз пытался восстановить пароль. Повторите попытку позже или позвоните в Контактный центр.
                    // Access to profvile blocked temporary due to many failed attempts. Profile will be automatically unblocked in 1 minutes.'
                    if (
                        strstr($error, 'Профиль заблокирован, ')
                        || strstr($error, 'Access to profile blocked temporary due to many failed attempts.')
                        || strstr($error, 'Доступ в личный кабинет временно заблокирован в связи с большим количеством неуспешных попыток авторизации')
                        || strstr($error, 'S7 Priority number is blocked or doesn\'t exist. For more information please contact our Call Centre.')
                        || strstr($error, ' is temporarily locked. For more information please contact our Call Centre.')
                    ) {
                        throw new CheckException($error, ACCOUNT_LOCKOUT);
                    }

                    $this->DebugInfo = $error;

                    return false;
                }// if (!$onProfile && $message)
            }// if (!$onProfile)
            $this->saveToLogs();

            if (
                strstr($onProfile->getText(), 'Отправили письмо с кодом на почту')
                || strstr($onProfile->getText(), 'Отправили СМС с кодом на номер')
            ) {
                $this->holdSession();
                $this->AskQuestion($onProfile->getText(), null, "Question2fa");

                return false;
            }

            if (!$this->driver->manage()->getCookieNamed('ssdcp')['value'] ?? null) {
                if ($this->attempt == 0) {
                    $this->DebugInfo = self::ERROR_REASON_BLOCK;

                    throw new CheckRetryNeededException(2, 3);
                }

                return false;
            }
            $cookies = $this->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->setupCurl();
        } catch (WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $retry = true;
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "TimeoutException";
            // retries
            if (
                strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')
                || strstr($e->getMessage(), 'Timeout loading page after')
                || $e->getMessage() === 'timeout'
            ) {
                $retry = true;
            }
        } finally {
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());
        $this->saveResponse();

        $q = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Отправили письмо с кодом на почту') or contains(text(), \"Отправили СМС с кодом на номер\")]"), 0);
        $securityAnswerInput = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Код безопасности')]/preceding-sibling::input"), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Подтвердить")]'), 0);
        $this->saveResponse();

        if (!$q || !$securityAnswerInput || !$btn) {
            return false;
        }

        $question = $q->getText();

        if ($question && !isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, 'Question2fa');

            return false;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $this->logger->debug("entering answer");
        $securityAnswerInput->click();
        $securityAnswerInput->clear();
        $this->driver->executeScript("
            function triggerInput(selector, enteredValue) {
                let input = document.querySelector(selector);
                input.dispatchEvent(new Event('focus'));
                input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
                let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                nativeInputValueSetter.call(input, enteredValue);
                let inputEvent = new Event(\"input\", { bubbles: true });
                input.dispatchEvent(inputEvent);
            }
            triggerInput('input.DS__FieldInput__input', '{$answer}');
        ");
//        $securityAnswerInput->sendKeys($answer);
        $this->saveResponse();
        $this->logger->debug("submit code");

        try {
            $btn->click();
        } catch (
            Facebook\WebDriver\Exception\StaleElementReferenceException
            | StaleElementReferenceException
            | Facebook\WebDriver\Exception\ElementClickInterceptedException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        // OTP entered is incorrect
        sleep(5);
        $error = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(),'Пожалуйста, проверьте введённые данные')]"), 0);
        $this->saveResponse();

        if ($error) {
            $this->logger->error("resetting answers");
            $this->holdSession();
            $this->AskQuestion($question, $error->getText(), 'Question2fa');

            return false;
        }

        $this->logger->debug("success");

        if (!$this->driver->manage()->getCookieNamed('ssdcp')['value'] ?? null) {
            if ($this->attempt == 0) {
                $this->DebugInfo = self::ERROR_REASON_BLOCK;

                throw new CheckRetryNeededException(2, 3);
            }

            return false;
        }
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
        $this->setupCurl();

        return true;
    }

    public function Login()
    {
        if ($this->http->FindSingleNode("//p[contains(text(), 'Looks like your password has expired.')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if (
            (
                $this->http->FindNodes("//a[contains(@class, 'logoutLink')] | //div[@class = 'UserInfo__card']")
                && !$this->http->FindSingleNode('//div[@class="error_block" and not(contains(@style, "none"))]')
            )
            || ($this->driver->manage()->getCookieNamed("isAuth")['value'] ?? null) == '1'
        ) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(@class, "exceptionPendingTxt") and contains(., "Too many log-in attempts was made from your ip")]')) {
            $this->logger->error($message);

            return false;
        }

        if ($message = $this->http->FindSingleNode('
                //div[@class="error_block" and not(contains(@style, "none"))]
                | //div[contains(@class, "DS__Tooltip__invalid")]')
        ) {
            switch ($message) {
                case 'Wrong login/password':
                case 'Неверный логин/пароль':
                case 'The minimum amount of digits in the number is 9':
                case 'Минимальное количество цифр в номере — 9':
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

                default:
                    $this->logger->error($message);

                    return false;
            }
        }
        // The number of authorization attempts exceeds the limit. Your profile is temporarily blocked.
        if ($message = $this->http->FindPreg("/(The number of authorization attempts exceeds the limit\.\s*Your profile is temporarily blocked\.)/ims")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // This S7 Priority account is inactive.
        if ($message = $this->http->FindPreg("/(This S7 Priority account is inactive\.[^\"]+)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // No card with this number exists. Please, check the data entered.
        if ($message = $this->http->FindPreg("/(No card with this number exists\.\s*Please, check the data entered\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Looks like your password has expired
        if ($message = $this->http->FindPreg("/\"errors\":\[\"(Looks like your password has expired\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // No profile matching these parameters found.
        if ($message = $this->http->FindPreg("/(?:No profile matching these parameters found\.|Specify correct PIN|Error accessing the profile\. Please, try logging in again\.|\"errors\":\[\"invalid\.credentials\"\]|Wrong login\/password)/ims")) {
            throw new CheckException("Wrong login/password", ACCOUNT_INVALID_PASSWORD);
        }
        // Service unavailable. Please try again later.
        if ($message = $this->http->FindPreg("/Service unavailable\.\s*Please try again later\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Registration is not complete
        if ($message = $this->http->FindPreg("/\"errors\":\[\"pending status\"\]/ims")) {
            throw new CheckException("S7 Priority website is asking you complete your registration, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*review*/
        // Invalid credentials
        if ($redirect = $this->http->FindPreg("/redirectUrl\":\"([^\"]+)/ims")) {
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }
        // Wrong login/password
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Wrong login/password')]", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Confirm your registration
        if (
            ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Confirm your registration')]"))
            && strstr($this->http->currentUrl(), 'serviceNotAvailable')
        ) {
            throw new CheckException("S7 Priority website is asking you to confirm your registration, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        if ($this->AccountFields['Login'] == '717143085') {
            throw new CheckException("Wrong login/password", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        try {
            $response = $this->userInfo ?: $this->getUserInfo();
        } catch (UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 1);
        }

        if (!$response) {
            return;
        }
        $this->profileId = $response->c->id ?? null;

        if (!$this->profileId) {
            $this->sendNotification('check profile id // MI');
        }

        // Name
        if (isset($response->c->firstName, $response->c->lastName)) {
            $this->SetProperty("Name", beautifulName($response->c->firstName . ' ' . $response->c->lastName));
        } elseif (isset($response->c->firstNameEn, $response->c->lastNameEn)) {
            $this->SetProperty("Name", beautifulName($response->c->firstNameEn . ' ' . $response->c->lastNameEn));
        } else {
            $this->logger->notice("Name is not found");
        }

        // Status
//        if (typecard === 'Classic' && authData.travelLevelStatus && authData.travelLevelStatus.toLowerCase() !== 'classic') {
//            trevellevel = authData.travelLevelStatus;
//            $('.js-priority-type-card').html(typecard + ' ' + trevellevel);
//        } else{$('.js-priority-type-card').html(typecard);}

        if (isset($response->c->cardLevel, $response->c->travelLevelStatus)) {
            $cardLevel = beautifulName($response->c->cardLevel);
            $travelLevelStatus = strtolower($response->c->travelLevelStatus);

            if ($cardLevel == 'Classic' && $travelLevelStatus != 'classic') {
                $this->SetProperty("Status", $cardLevel . ' ' . beautifulName($travelLevelStatus));
            } else {
                $this->SetProperty("Status", $cardLevel);
            }
        } else {
            $this->logger->notice("Status is not found");
        }

        // Date of creation
        if (isset($response->c->creationDate)) {
            $this->SetProperty("CreationDate", $response->c->creationDate);
        } else {
            $this->logger->notice("CreationDate is not found");
        }
        // Status Flights
        if (isset($response->c->qFlights)) {
            $this->SetProperty("StatusFlights", $response->c->qFlights);
        } else {
            $this->logger->notice("StatusFlights is not found");
        }
        // Status Miles
        if (isset($response->c->qMiles)) {
            $this->SetProperty("StatusMiles", $response->c->qMiles);
        } else {
            $this->logger->notice("StatusFlights is not found");
        }
        // Number
        if (isset($response->c->cardNumber)) {
            $this->SetProperty("Number", $response->c->cardNumber);
        } else {
            $this->logger->notice("Number is not found");
        }
        // Balance
        if (isset($response->c->milesBalance)) {
            $this->SetBalance($response->c->milesBalance);
        } else {
            $this->logger->notice("Balance is not found");
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // not a member
            if ((!empty($this->Properties['Name']) || !empty($response->c->email))
                && (
                    (isset($this->Properties['StatusMiles'], $this->Properties['StatusFlights'])
                        && $this->Properties['StatusMiles'] == 0 && $this->Properties['StatusFlights'] == 0)
                    || ($response->c->customerValue == 'SHORT' && $response->c->loyaltyRulesAccepted == false)
                )
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);

                return;
            }

            if ($this->http->Response['body'] == '{"stc":300,"std":"No results"}') {
                throw new CheckRetryNeededException(2);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        try {
            $this->parseCertificates();
        } catch (NoSuchWindowException | UnknownServerException | WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 1);
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "http://www.s7.ru/home/priority/ffpMyMiles.dot";

        return $arg;
    }

    public function ParseItineraries(): array
    {
        $this->logger->notice(__METHOD__);

        if ($this->tripsContent === null) {
            $this->saveTripsContent();
        }

        if ($this->noItins) {
            return $this->noItinerariesArr();
        }
        $startTimer = $this->getTime();
        /*
        $ssdcp = $this->curl->getCookieByName('ssdcp', '.s7.ru');
        $headers = [
            'Accept' => 'application/json, text/plain, * / *',
            'X-Token' => $ssdcp,
        ];
        $this->curl->RetryCount = 0;
        $this->curl->GetURL("https://myprofile.s7.ru/api/service/trips/api/profiles/{$this->profileId}/search-trips?page=0&size=100", $headers);
        $this->http->JsonLog(null, 3);
        $this->curl->RetryCount = 2;
        if (!$this->curl->FindPreg('/^\{"trips"/')) {
            $this->sendNotification('check parse itineraries without selenium // MI');
        }
        */
        $this->increaseTimeLimit(120);

        foreach ($this->tripsContent as $cont) {
            $status = ArrayVal($cont, 'status');

            if ($status === 'HISTORICAL' && !$this->ParsePastIts) {
                $this->logger->notice('Skipping itinerary in the past');

                continue;
            }

            if (!in_array($status, ['ACTIVE', 'HISTORICAL'])) {
                $this->sendNotification('check unknown itin status // MI');

                continue;
            }
            $orders = ArrayVal($cont, 'orders', []);

            foreach ($orders as $order) {
                $orderNumber = ArrayVal($order, 'number', 'null');
                $airs = ArrayVal($order, 'airs', []);

                foreach ($airs as $air) {
                    $pnr = $air['pnr'] ?? null;
                    $lastName = $air['passengers'][0]['names'][0]['lastName'] ?? null;
                    $future = $status === 'ACTIVE';
                    $cancelled = false;

                    if ($pnr && $lastName && $future) {
                        $error = null;
                        $manageAir = $this->getManageOrderAir($pnr, $orderNumber, $lastName, $error);

                        if ($manageAir) {
                            //$this->sendNotification('success getManageOrderAir // MI');
                            $air = $manageAir;
                        } elseif ($error) {
                            $this->logger->error("Skipping manage order air: {$error}");
                        } else {
                            $this->logger->error('Skipping manage order air: unknown error');
                        }
                        $cancelled = strstr($error, 'Order is cancelled');
                    }
                    $this->parseAir($air, $orderNumber, $future, $cancelled);
                }
            }

            $railways = $this->arrayVal($cont, ['orders', 'railways'], []);
            $hotels = $this->arrayVal($cont, ['orders', 'hotels'], []);
            $cars = $this->arrayVal($cont, ['orders', 'cars'], []);

            if ($railways || $hotels || $cars) {
                $this->sendNotification('check new itinerary types // MI');
            }
        }

        $this->getTime($startTimer);

        return [];
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Description" => "Description",
            "Miles"       => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        try {
            $transactions = $this->getTransactions();
        } catch (NoSuchWindowException | UnknownServerException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Exception: {$e->getMessage()}");
            $transactions = null;
        }

        if (!$transactions) {
            return [];
        }

        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');

        foreach ($transactions as $item) {
            $row = [];
            $dateStr = $item['date'] ?? '';
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                break;
            }
            $row['Date'] = $postDate;
            $typeDescription = $item['typeDescription'] ?? null;
            $partnerName = $item['partnerName'] ?? null;

            if ($partnerName && $typeDescription) {
                $row['Description'] = "{$partnerName}, {$typeDescription}";
            } else {
                $row['Description'] = $typeDescription;
            }
            $row['Miles'] = $item['totalValue'] ?? null;

            if (!$row['Description'] || !isset($row['Miles'])) {
                $this->sendNotification("[check history]: {$row['Description']} / {$row['Miles']}");

                continue;
            }
            $result[] = $row;
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            'ConfNo'  => [
                'Caption'  => 'Order ID, booking ID or eTicket number',
                'Type'     => 'string',
                'Size'     => 13,
                'Required' => true,
            ],
            'LastName' => [
                'Caption'  => 'Surname or email',
                'Type'     => 'string',
                'Size'     => 50,
                'Value'    => $this->GetUserField('LastName'),
                'Required' => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://www.s7.ru/';
    }

    public function CheckConfirmationNumberInternalOld($arFields, &$it)
    {
        $result = $this->parseItineraryV4($arFields['ConfNo'], $arFields['LastName']);

        if (is_string($result)) {
            return $result;
        }

        if (is_array($result)) {
            $it = $result;
        }

        return null;
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->curl = new HttpBrowser('none', new CurlDriver());
        $this->http->brotherBrowser($this->curl);
        $this->curl->setHttp2(true);
        // $this->curl->SetProxy($this->proxyReCaptcha());

        //$this->curl->GetURL('https://www.s7.ru/');
        $error = null;
        $air = $this->getManageOrderAir($arFields['ConfNo'], null, $arFields['LastName'], $error);

        if (empty($air) && $error) {
            return $error;
        }

        if (!$air) {
            return null;
        }
        $this->parseAir($air, null, true);

        return null;
    }

    private function setupCurl()
    {
        $this->logger->notice(__METHOD__);
        $this->curl = new HttpBrowser('none', new CurlDriver());
        $this->http->brotherBrowser($this->curl);
        $this->curl->setHttp2(true);
        $this->curl->SetProxy($this->http->GetProxy());
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->curl->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
    }

    private function getUserInfo(): ?object
    {
        $this->logger->notice(__METHOD__);
        $response = $this->ajaxRequest("https://www.s7.ru/dotCMS/priority/ajaxProfileService?dispatch=getUserInfo&_=" . date("UB"), 5);

        if (!$response) {
            return null;
        }
        $data = $this->http->JsonLog($response, 3);

        return is_string($data) ? null : $data;
    }

    private function getTransactions(): ?array
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->http->GetURL('https://myprofile.s7.ru/miles');
        } catch (SessionNotCreatedException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }

        sleep(2);

        try {
            $this->waitForElement(WebDriverBy::xpath('//h5[
                contains(text(), "Мильные транзакции")
                or contains(text(), "Mile transactions")
            ]'), 3);
        } catch (\Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
            $this->logger->error("UnknownServerException: {$e->getMessage()}");
        }

        $dateFrom = strtotime('-1 year', strtotime('now'));
        $dateFromStr = date('Y-m-d', $dateFrom);

        if (!$this->profileId) {
            return null;
        }

        try {
            $response = $this->ajaxRequest("https://myprofile.s7.ru/api/service/loyalty/api/loyalties/{$this->profileId}/transactions?from={$dateFromStr}&to=", 5);
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: {$e->getMessage()}");
            $response = '';
        }

        if (!stristr($response, '"error":null')) {
            $this->logger->info("[transactions]: {$response}");
        }

        if (!$response) {
            return null;
        }
        $data = $this->http->JsonLog($response, 0, true);
        $transactions = $data['transactions'] ?? [];
        $this->logger->info(sprintf('Found %s historical transactions', count($transactions)));

        return $transactions;
    }

    private function saveTripsContent(): void
    {
        $this->logger->notice(__METHOD__);
        $startTimer = $this->getTime();
        $noItins = null;

        try {
            $this->http->GetURL('https://myprofile.s7.ru/travels');
            $noItins = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Нет запланированных путешествий?") or contains(text(), "No planned trips?")]'), 5);
            $this->saveToLogs();
        } catch (NoSuchWindowException | UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 1);
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        if ($noItins) {
            $this->noItins = true;
            $this->tripsContent = [];

            return;
        }

        if (!$this->profileId) {
            $this->tripsContent = [];

            return;
        }

        $tripsContent = [];
        $size = $this->ParsePastIts ? 200 : 100;

        try {
            $response = $this->ajaxRequest("https://myprofile.s7.ru/api/service/trips/api/profiles/{$this->profileId}/search-trips?page=0&size={$size}");
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            return;
        }

        if (!$response) {
            $this->logger->error('Failed to parse future itins');
            sleep(5);
        } else {
            $data = $this->http->JsonLog($response, 3, true) ?: [];
            $tripsContent = $this->arrayVal($data, ['trips', 'content'], []);
            /*
            $totalPage = $this->arrayVal($data, ['trips', 'totalPage']);
            $validContent = $totalPage && (intval($totalPage) == 1 || count($tripsContent) == $size);
            if (!$validContent) {
                $this->sendNotification('check save trips content // MI');
            } else {
                $this->sendNotification('check save trips content valid // MI');
            }
            */
            $this->logger->info(sprintf('Found %s itineraries (future and past)', count($tripsContent)));
        }

        $this->getTime($startTimer);
        $this->tripsContent = $tripsContent;
    }

    private function ajaxRequest(string $url, $timeout = 2, $log = true): string
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("[parsing] {$url}");
        $ssdcp = $this->driver->manage()->getCookieNamed('ssdcp')['value'] ?? '';

        if (!$ssdcp) {
            $this->logger->error('no ssdcp cookie');

            return '';
        }
        $script = "
            var jq = document.createElement('script');
            jq.src = 'https://ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.js';
            document.getElementsByTagName('head')[0].appendChild(jq);
            setTimeout(function() {
                $.ajax({
                    url: '{$url}',
                    type: 'GET',
                    headers: {
                        'Accept': 'application/json, text/plain, */*',
                        'X-Token': '{$ssdcp}'
                    },
                    async: false,
                    success: function (data) {
                        console.log('Success: ' + JSON.stringify(data));
                        localStorage.setItem('response', JSON.stringify(data));
                    },
                    error: function (data, textStatus, error) {
                        console.log('Error: ' + JSON.stringify(error));
                        localStorage.setItem('responseError', JSON.stringify(error));
                        console.log('Error: ' + JSON.stringify(data));
                        localStorage.setItem('response', JSON.stringify(data));
                    }
                });
            }, 1000);
        ";
        $this->driver->executeScript($script);
        sleep($timeout);

        try {
            $response = $this->driver->executeScript("return localStorage.getItem('response');");
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 1);
        }

        $response = $response ?? null;

        if ($log) {
            $this->logger->debug("[response #0]: {$response}");
        }

        if (!$response || stristr($response, 'A network error occurred.')) {
            $this->logger->info('retry #1 ajax request');
            $this->driver->executeScript($script);
            sleep($timeout * 2);
            $response = $this->driver->executeScript("return localStorage.getItem('response');");

            if ($log) {
                $this->logger->debug("[response #1]: {$response}");
            }
        }

        if (!$response
            || stristr($response, 'A network error occurred.')
            || stristr($response, 'Request unsuccessful. Incapsula incident ID')
        ) {
            $this->logger->info('retry #2 ajax request');
            $this->driver->executeScript($script);
            sleep($timeout * 4);
            $response = $this->driver->executeScript("return localStorage.getItem('response');");

            if ($log) {
                $this->logger->debug("[response #2]: {$response}");
            }
        }

        if (!$response || stristr($response, 'A network error occurred.')) {
            $this->logger->debug("[response] $response");
        }

        return $response ?: '';
    }

    private function saveToLogs()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->saveResponse();
        } catch (UnexpectedAlertOpenException $e) {
            try {
                $this->logger->error($e->getMessage());
                $this->logger->debug($this->driver->switchTo()->alert()->getText());
            } catch (NoAlertOpenException $e) {
                $this->logger->debug("[1] no alert, skip");
            }
        }// catch (UnexpectedAlertOpenException $e)
        catch (NoAlertOpenException $e) {
            $this->logger->debug("no alert, skip");
        } catch (UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Service is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Service is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service unavailable. Please try again later.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Service unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# There was an error trying to complete your request
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'There was an error trying to complete your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * ;(
         * К сожалению, сайт временно недоступен.
         * Мы знаем об этом и приносим свои извинения.
         * */
        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'К сожалению, сайт временно недоступен.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(Сервис временно недоступен. Приносим извинения!|Сервис временно недоступен\.\s*Пожалуйста, воспользуйтесь сервисом немного позже\.)/ims")) {
            throw new CheckException("The service is temporarily unavailable. We apologize for the inconvenience. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        // Страницы с этим адресом не существует, или она была удалена.
        if ($this->http->FindPreg("/(Страницы с этим адресом не существует\,\s*или она была удалена\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseCertificates(): void
    {
        $this->logger->notice(__METHOD__);

        if ($this->tripsContent === null) {
            $this->saveTripsContent();
        }

        $certCount = 0;

        foreach ($this->tripsContent as $cont) {
            $orders = $cont['orders'] ?? [];

            foreach ($orders as $order) {
                $certificates = $order['certificates'] ?? [];

                foreach ($certificates as $index => $cert) {
                    $type = $cert['type'] ?? null;

                    if ($type !== 'Certificate') {
                        $this->sendNotification('check certificate type // MI');

                        continue;
                    }
                    $number = $cert['number'] ?? null;

                    if (!$number) {
                        $this->sendNotification('check certificate number // MI');

                        continue;
                    }

                    if (count($certificates) > 1) {
                        $code = sprintf('s7Certificate%s-%s', $number, $index + 1);
                    } else {
                        $code = "s7Certificate{$number}";
                    }
                    $subAcc = [
                        'Code'           => $code,
                        'DisplayName'    => "Certificate #{$number}",
                        'Balance'        => $this->arrayVal($cert, ['price', 'amount']),
                        'Currency'       => $this->arrayVal($cert, ['price', 'currency']),
                        "ExpirationDate" => strtotime($cert['endDate']),
                    ];
                    $this->AddSubAccount($subAcc);
                    $certCount++;
                }
            }
        }
        $this->logger->info("Parsed {$certCount} certificates");
    }

    private function parseAir(array $air, ?string $orderNumber, bool $future = true, bool $cancelled = false): void
    {
        $this->logger->notice(__METHOD__);
        $pnr = $air['pnr'] ?? null;

        if ($pnr && isset($this->pnrCache[$pnr])) {
            $this->logger->error('Skipping duplicate itinerary');

            return;
        }
        // status
        $status = $air['status'] ?? null;

        if ($status === 'HISTORIC') {
            $this->logger->error("Skipping itinerary: archived");

            return;
        }
        $flight = $this->itinerariesMaster->createFlight();

        if ($status === 'CANCELLED') {
            $flight->setCancelled(true);
        }

        if (!$flight->getCancelled() && $cancelled) {
            $flight->setCancelled(true);
        }
        // confirmation number
        $this->pnrCache[$pnr] = true;

        if (!$pnr) {
            $this->logger->error('[air without pnr]:');
            $this->logger->debug(var_export($air, true));

            if (!$future) {
                $flight->setNoConfirmationNumber(true);
            }
        } else {
            $flight->addConfirmationNumber($pnr, 'Booking', true);
        }

        if ($orderNumber) {
            $flight->addConfirmationNumber($orderNumber, 'Order');
            $this->logger->info("[{$this->currentItin}] Parse Flight #{$pnr} (Order #{$orderNumber})", ['Header' => 3]);
        } else {
            $this->logger->info("[{$this->currentItin}] Parse Flight #{$pnr}", ['Header' => 3]);
        }
        $this->currentItin++;
        $passengers = ArrayVal($air, 'passengers', []);
        // travellers and ticket numbers
        foreach ($passengers as $passenger) {
            $title = $passenger['names'][0]['title'] ?? '';
            $firstName = $passenger['names'][0]['firstName'] ?? $passenger['name']['firstName'] ?? '';
            $lastName = $passenger['names'][0]['lastName'] ?? $passenger['name']['lastName'] ?? '';
            $name = trim(beautifulName("{$title} {$firstName} {$lastName}"));
            $flight->addTraveller($name);
            $ticket = $this->arrayVal($passenger, ['tickets', 0, 'number']);

            if ($ticket) {
                $flight->addTicketNumber($ticket, false);
            }
        }
        // seats
        $seatsBySegment = [];
        $seats = $air['seats'] ?? [];

        foreach ($seats as $seat) {
            $segmentId = $seat['segmentId'] ?? '';
            $rowNumber = $seat['rowNumber'] ?? '';
            $seatNumber = $seat['seatNumber'] ?? '';
            $fullNumber = trim("$seatNumber$rowNumber");

            if (!$fullNumber) {
                continue;
            }

            if (!isset($seatsBySegment[$segmentId])) {
                $seatsBySegment[$segmentId] = [$fullNumber];
            } else {
                $seatsBySegment[$segmentId][] = $fullNumber;
            }
        }
        $pricing = $air['pricing'] ?? null;

        if (!$flight->getCancelled() && $pricing) {
            // total
            $flight->price()->total($pricing['total']['price']['amount'] ?? null, true, false);
            // cost
            $flight->price()->cost($pricing['base']['price']['amount'] ?? null, true, false);
            // tax
            $flight->price()->tax($pricing['taxes']['price']['amount'] ?? null, true, false);
            // currency
            $flight->price()->currency($pricing['total']['price']['currency'] ?? null, true, false);
            // spent awards
            $redemptionCurrency = $pricing['total']['redemption']['currency'] ?? null;

            if ($redemptionCurrency === 'MILES') {
                $flight->price()->spentAwards($pricing['total']['redemption']['amount'] ?? null, true, false);
            } elseif ($redemptionCurrency) {
                $this->sendNotification('check redemption currency // MI');
            }
            // earned miles
            $allEarnedMiles = [];

            foreach ($air['passengerBreakdowns'] ?? [] as $passengerBreakdown) {
                foreach ($passengerBreakdown['segmentBreakdowns'] ?? [] as $segmentBreakdown) {
                    $earnedMiles = $segmentBreakdown['earnMiles'] ?? null;

                    if ($earnedMiles) {
                        $allEarnedMiles[] = $earnedMiles;
                    }
                }
            }

            if ($allEarnedMiles) {
                $flight->setEarnedAwards(array_sum($allEarnedMiles));
            }
        }
        // segments
        $routes = $air['routes'] ?? [];

        foreach ($routes as $route) {
            $segments = $route['segments'] ?? [];

            foreach ($segments as $segment) {
                $segmentId = $segment['id'] ?? null;
                // airline name
                $airlineCode = $this->arrayVal($segment, ['operatingAirline', 'code']);
                // flight number
                $flightNumber = $this->arrayVal($segment, ['operatingAirline', 'flightNumber']);
                // status
                $status = $segment['status'] ?? null;
                $legs = $segment['legs'] ?? [];

                foreach ($legs as $leg) {
                    $seg = $flight->addSegment();
                    $seg->setAirlineName($airlineCode);
                    $seg->setFlightNumber($flightNumber);
                    // dep code
                    $seg->setDepCode($this->arrayVal($leg, ['departureAirport', 'code']));
                    // dep terminal
                    $seg->setDepTerminal($this->arrayVal($leg, ['departureAirport', 'terminal']), false, true);
                    // arr code
                    $seg->setArrCode($this->arrayVal($leg, ['arrivalAirport', 'code']));
                    // arr terminal
                    $seg->setArrTerminal($this->arrayVal($leg, ['arrivalAirport', 'terminal']), false, true);
                    // dep date
                    $depDate = $leg['departureDate'] ?? null;
                    $seg->setDepDate(strtotime($depDate));
                    // arr date
                    $arrDate = $leg['arrivalDate'] ?? null;
                    $seg->setArrDate(strtotime($arrDate));
                    // duration
                    $minutes = $leg['duration']['amountInMinutes'] ?? null;

                    if ($minutes === null) {
                        $unit = $leg['duration']['unit'] ?? null;

                        if ($unit === 'MINUTES') {
                            $minutes = $leg['duration']['amount'] ?? null;
                        }
                    }

                    if ($minutes) {
                        $seg->setDuration(sprintf('%dh %dm', $minutes / 60, $minutes % 60));
                    }
                    // distance
                    $miles = $leg['distance']['amountInMiles'] ?? null;

                    if ($miles === null) {
                        $unit = $leg['distance']['unit'] ?? null;

                        if ($unit === 'MI') {
                            $miles = $leg['distance']['amount'] ?? null;
                        }
                    }

                    if ($miles) {
                        $seg->setMiles($miles);
                    }
                    // status
                    $seg->setStatus($status, false, true);

                    if ($status === 'CANCELLED') {
                        $seg->setCancelled(true);
                    }
                    // seats
                    $segSeats = $seatsBySegment[$segmentId] ?? null;

                    if ($segSeats) {
                        $segSeats = array_values(array_unique($segSeats));
                        $seg->setSeats($segSeats);
                    }
                    // aircraft
                    $seg->setAircraft($segment['aircraft']['name'] ?? null, false, true);
                }
            }
        }

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);
    }

    private function getManageOrderAir(string $pnr, ?string $orderNumber, string $lastName, ?string &$retError): ?array
    {
        $this->logger->notice(__METHOD__);
        // regexp from the site
        if (!$this->curl->FindPreg('/^([a-z- ]{2,35})$/i', false, $lastName)) {
            $retError = 'Surname can contain latin letters only';

            return null;
        }
        $this->curl->GetURL("https://myb.s7.ru/myb.action?request_locale=en&myb_medium=s7PortalBot&surname={$lastName}&superPnrId={$pnr}", []);

        if (strstr($this->curl->Error, 'Network error 56 - Received HTTP code 407 from proxy after CONNECT')) {
            sleep(5);
            $this->curl->GetURL("https://myb.s7.ru/myb.action?request_locale=en&myb_medium=s7PortalBot&surname={$lastName}&superPnrId={$orderNumber}", []);
        }

        if (
            $this->curl->FindPreg('/find.order.description": "Manage your booking, track the status and more"/')
            && $orderNumber
        ) {
            $this->logger->error("wrong pnr in link");
            $this->currentItin--;
            $this->logger->info("[{$this->currentItin}] Parse Flight #{$orderNumber}, Order #{$orderNumber}", ['Header' => 3]);
            $this->currentItin++;
            $this->curl->GetURL("https://myb.s7.ru/myb.action?request_locale=en&myb_medium=s7PortalBot&surname={$lastName}&superPnrId={$orderNumber}");
        }

        if ($urlError = $this->curl->FindPreg('/ErrorPages\/503\.html/', false, $this->curl->currentUrl())) {
            $retError = $urlError;

            return null;
        }
//        if ($this->curl->FindSingleNode("//form[@data-validator-id='subscriptionForm']//span[contains(@class,'error-msg') and contains(@class,'js_popup_not_found_message')]")) {
        if (($txt = $this->curl->FindPreg("/<form[^>]+\bdata\-validator\-id=\"subscriptionForm\">(.+?)<\/form>/s"))
            && $this->curl->FindPreg("/\bjs_popup_not_found_message\b/", false, $txt)
        ) {
            $retError = 'Booking not found';

            return null;
        }
        $error = (
            $this->curl->FindPreg('/(Booking is archived)/')
            ?: $this->curl->FindPreg('/(Order is cancelled)/')
        );

        if ($error) {
            $retError = $error;

            return null;
        }
        $airId = (
            $this->curl->FindSingleNode('(//input[@name = "documentRequest.airId"]/@value)[1]')
            ?: $this->curl->FindSingleNode('(//input[@name = "contactsRequest.airId"]/@value)[1]')
        );
        $this->logger->debug("airId={$airId}");
        $ibe = $this->curl->FindPreg('/ibe_conversation=[\'+\s]*"([\w.:-]+)";/');
        $this->logger->debug("ibe_conversation={$ibe}");

        if (empty($airId)) {
            // The site always goes this request in it you can find out there is no reservation.
            $headers = [
                'Accept'          => 'text/html;type=ajax',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection'      => 'keep-alive',
                'Content-Type'    => 'application/x-www-form-urlencoded;charset=UTF-8',
                'Host'            => 'myb.s7.ru',
                'Origin'          => 'https://myb.s7.ru',
                'X-Wrap-Response' => 'true',
            ];
            $payload = [
                '_eventId'                 => 'find-new',
                'searchParams.passengerId' => $lastName,
                'searchParams.number'      => $pnr,
                'clientId'                 => 'myb',
                'execution'                => 'e1s1',
                'ibe_conversation'         => $ibe,
            ];
            $this->curl->RetryCount = 0;
            $this->curl->PostURL('https://myb.s7.ru/manage-order', $payload, $headers);
            $data = $this->curl->JsonLog(null, 3, true);

            if (isset($data['error'], $data['errorCode'])) {
                if (in_array($data['errorCode'], ['order.not.found', 'validation.failed'])) {
                    $retError = 'Booking not found';

                    return null;
                }
            }
        }

        $this->requestManageOrder($airId, $ibe);
        $data = $this->curl->JsonLog(null, 3, true);
        $air = $data['air'] ?? null;

        if (!$air) {
            $error = $data['error'] ?? null;
            $errorCode = $data['errorCode'] ?? null;

            if ($error === false) {
                $retError = 'Booking not found';

                return null;
            } else {
                if ($errorCode == 'unsuccessfull.uncheckin') {
                    $retError = 'Past itinerary';

                    return $air;
                }

                //$this->sendNotification('check getManageOrderAir // MI');
            }

            return null;
        }

        return $air;
    }

    private function requestManageOrder(?string $airId, ?string $ibe): void
    {
        $this->logger->notice(__METHOD__);

        if (empty($airId)) {
            return;
        }
        $headers = [
            'Accept'          => 'text/html;type=ajax',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection'      => 'keep-alive',
            'Content-Type'    => 'application/x-www-form-urlencoded;charset=UTF-8',
            'Host'            => 'myb.s7.ru',
            'Origin'          => 'https://myb.s7.ru',
            'X-Wrap-Response' => 'true',
        ];
        $payload = [
            '_eventId'         => 'getAirData',
            'airId'            => $airId,
            'clientId'         => 'myb',
            'execution'        => 'e1s1',
            'ibe_conversation' => $ibe,
        ];
        $this->curl->RetryCount = 0;
        $this->curl->PostURL('https://myb.s7.ru/manage-order', $payload, $headers);

        if (
            $this->curl->FindPreg('/empty body/', false, $this->http->Error)
            || $this->curl->FindSingleNode('//h4[contains(text(), "К сожалению, сайт временно недоступен.")]')
        ) {
            sleep(2);
            $this->curl->PostURL('https://myb.s7.ru/manage-order', $payload, $headers);
        }
        $this->curl->RetryCount = 2;
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        if (!is_array($indices)) {
            $indices = [$indices];
        }
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    private function parseItineraryV4($confNo, $lastName): ?string
    {
        $this->logger->notice(__METHOD__);
        $itinUrl = "https://myb.s7.ru/myb.action?request_locale=en&myb_medium=s7PortalBot&surname={$lastName}&superPnrId={$confNo}&manage-booking-submit=";
        $this->http->GetURL($itinUrl);

        if ($this->http->Response['code'] == 404) {
            sleep(5);
            $this->sendNotification('404 workaround');
            $this->http->GetURL($itinUrl);
        }

        $itinError = (
            $this->http->FindPreg('/(Unfortunately, your booking could not be found)/')
            ?: $this->http->FindPreg('/(Booking is archived)/')
            ?: $this->http->FindPreg('/(К сожалению, сайт временно недоступен\.)/')
            ?: $this->http->FindPreg('/(Some products are being processed)/')
        );

        if ($itinError) {
            $this->logger->error("Skipping itinerary: {$itinError}");

            return $itinError;
        }

        $segmentNodes = $this->xpathQuery('//div[contains(@class, "flight-box-item")][./preceding::text()[normalize-space()!=""][1][contains(.,\'Travel time:\')]]'); // when flights with stops

        if ($segmentNodes->length == 0) {
            $segmentNodes = $this->xpathQuery('//div[contains(@class, "flight-box-item")]');
        }

        if ($segmentNodes->length == 0) {
            $segmentNodes = $this->xpathQuery('//div[starts-with(@class,\'SegmentWrapper__block\')]');
            $verSeg = 2;
        }
        $this->logger->debug("Total {$segmentNodes->length} segments were found");

        if ($segmentNodes->length === 0) {
            // wrong pnr in link
            if ($this->http->FindPreg('/find.order.description": "Manage your booking, track the status and more"/')) {
                $this->logger->error("Skipping itinerary: wrong pnr in link");

                return null;
            }
            // Unable to manage the booking
            if ($this->http->FindPreg('/(Unable to manage the booking)/') && $segmentNodes->length == 0) {
                $this->logger->error('Skipping itinerary: Unable to manage the booking');

                return null;
            }
        }

        $flight = $this->itinerariesMaster->createFlight();
        // RecordLocator
        $conf = $this->http->FindSingleNode('//span[contains(@class, "js_booking_number")]/@data-pnr') ?: $confNo;
        $order = $this->http->FindSingleNode("//text()[contains(.,'Booking') and  contains(.,'{$conf}')]/preceding::text()[normalize-space()][1][contains(.,'Order')]", null, true, "/Order:\s*(\w+)/");
        $flight->addConfirmationNumber($conf, 'Booking', true);

        if (!empty($order)) {
            $flight->addConfirmationNumber($order, 'Order');
        }

        if (
            $this->http->FindPreg('/(Order is cancelled|Your flight is canceled|The flight has been cancelled)/')
            || (
                !empty($order)
                && !empty($this->http->FindSingleNode('//text()[contains(.,"Refund request for order")]/following::text()[normalize-space()][1][contains(.,"' . $order . '")]'))
            )
        ) {
            $flight->setCancelled(true);
            $this->logger->debug('Parsed Flight:');
            $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);

            return null;
        }
        // TotalCharge
        $totalText = $this->http->FindSingleNode('//div[@data-qa = "main_itemPaymentDetails"]/div[@data-qa = "cost"]');
        $total = $this->http->FindPreg('/([\d.,]+)/', false, $totalText);
        $flight->price()->total(PriceHelper::cost($total), false, true);
        // Currency
        $currency = $this->currency($totalText);
        $flight->price()->currency($currency, false, true);
        // Tax
        $taxText = $this->http->FindSingleNode('//div[@data-qa = "taxFlight_itemPaymentDetails"]/div[@data-qa = "cost"]');
        $tax = $this->http->FindPreg('/([\d.,]+)/', false, $taxText);

        if ($tax) {
            $flight->price()->tax(PriceHelper::cost($tax), false, true);
        }
        // Passengers
        $passengers = $this->http->FindNodes('//div[@data-qa = "passengerName_passengerItem"]/span[contains(@class, "name_field")]');

        if ($passengers) {
            $flight->setTravellers($passengers);
        }
        // TicketNumbers
        $ticketNumbers = $this->http->FindNodes('//div[@data-qa = "ticketNumber_passengerItem"]/span[1]', null, '/Ticket number (\w+)/');
        $flight->setTicketNumbers($ticketNumbers, false);
        // TripSegments
        $yearText = $this->http->FindSingleNode('//input[@id = "firstFlightDate"]/@value');
        $year = $this->http->FindPreg('/\b(\d{4})$/', false, $yearText);
        $dt2 = strtotime("1 Jan {$year}");

        if (isset($verSeg) && $verSeg === 2) {
            $this->parseSegmentsV4_2($segmentNodes, $flight, $dt2);
        } else {
            $this->parseSegmentsV4_1($segmentNodes, $flight, $dt2);
        }

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);

        return null;
    }

    private function parseSegmentsV4_1(DOMNodeList $segmentNodes, Flight $flight, int $dt2)
    {
        $this->logger->notice(__METHOD__);

        foreach ($segmentNodes as $node) {
            $seg = $flight->addSegment();
            // FlightNumber
            $flightText = $this->http->FindSingleNode('.//div[contains(@class, "flight-number")]', $node);
            $seg->setFlightNumber($this->http->FindPreg('/\s+(\d+)$/', false, $flightText));
            // AirlineName
            $seg->setAirlineName($this->http->FindPreg('/^(\w{2})\s+/', false, $flightText));
            // DepCode
            $depText = $this->http->FindSingleNode('.//div[contains(@class, "flight-description")]/div[1]//div[contains(@data-content, "Departure from")]', $node);
            $depCode = $this->http->FindPreg('/\b([A-Z]{3})\b/', false, $depText);
            $seg->setDepCode($depCode);
            // ArrCode
            $arrText = $this->http->FindSingleNode('.//div[contains(@class, "flight-description")]/div[2]//div[contains(@data-content, "Arrival at")]', $node);
            $arrCode = $this->http->FindPreg('/\b([A-Z]{3})\b/', false, $arrText);
            $seg->setArrCode($arrCode);
            // DepDate
            $date1 = $this->http->FindSingleNode('.//span[contains(@class, "departure-time")]', $node);
            $time1 = $this->http->FindSingleNode('.//div[contains(@class, "flight-description")]/div[1]//div[contains(@class, "date")]', $node);
            $dt1 = EmailDateHelper::parseDateRelative("{$time1} {$date1}", $dt2);
            $seg->setDepDate($dt1);
            // ArrDate
            $date2 = $this->http->FindSingleNode('.//span[contains(@class, "arrival-time")]', $node);
            $time2 = $this->http->FindSingleNode('.//div[contains(@class, "flight-description")]/div[2]//div[contains(@class, "date")]', $node);
            $dt2 = EmailDateHelper::parseDateRelative("{$time2} {$date2}", $dt2);

            if ($dt2 > strtotime('+6 months', $dt1)) {
                $dt2 = strtotime('-1 year', $dt2);
            }
            $seg->setArrDate($dt2);
            // Aircraft
            $aircraft = $this->http->FindSingleNode('.//div[contains(@class, "flight-plain")]/span[1]/@data-content', $node);
            $seg->setAircraft($aircraft, false, true);
            // Seats
            $seats = $this->http->FindNodes('.//div[@data-qa = "addSeat_passengerExtrasItem"]', $node, '/\b([A-Z\d]+)\b/');
            $seg->setSeats($seats);
            // Meal
            $meal = $this->http->FindSingleNode('(.//div[@data-qa = "meal_passengerExtrasItem"])[1]', $node) ?: null;
            $seg->addMeal($meal, false, true);
            // Cabin
            $cabin = $this->http->FindSingleNode('(./ancestor::div[contains(@class, "wrap-flight-section")])[1]//a[@data-content = "Fare information"]', $node);
            $seg->setCabin($cabin, false, true);
            // Duration
            $durText = $this->http->FindSingleNode('.//div[contains(@class, "flight-duration")]', $node);
            $dur = $this->http->FindPreg('/Travel time:\s+(.+)/', false, $durText);
            $seg->setDuration($dur, false, true);
        }
    }

    private function parseSegmentsV4_2(DOMNodeList $segmentNodes, Flight $flight, int $dt2)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("check parsing // ZM");

        foreach ($segmentNodes as $node) {
            $seg = $flight->addSegment();
            // airline
            $flightText = $this->http->FindSingleNode('./descendant::div[contains(@class, "Airlines__number")][1]', $node);
            $seg->airline()
                ->name($this->http->FindPreg('/^(\w{2})\s+/', false, $flightText))
                ->number($this->http->FindPreg('/\s+(\d+)$/', false, $flightText));
            // departure
            $depName = $this->http->FindSingleNode('./descendant::span[contains(@class, "FlightInfo__city-name")][1]', $node) . ', ' .
                $this->http->FindSingleNode('./descendant::span[contains(@class, "FlightInfo__city-airport")][1]', $node);
            $date1 = $this->http->FindSingleNode('./descendant::span[contains(@class, "FlightInfo__date")][1]', $node);
            $time1 = $this->http->FindSingleNode('./descendant::span[contains(@class, "FlightInfo__time")][1]', $node);
            $dt1 = EmailDateHelper::parseDateRelative("{$time1} {$date1}", $dt2);
            $seg->departure()
                ->noCode()
                ->name($depName)
                ->date($dt1);
            // arrival
            $arrName = $this->http->FindSingleNode('./descendant::span[contains(@class, "FlightInfo__city-name")][2]', $node) . ', ' .
                $this->http->FindSingleNode('./descendant::span[contains(@class, "FlightInfo__city-airport")][2]', $node);
            $date2 = $this->http->FindSingleNode('./descendant::span[contains(@class, "FlightInfo__date")][2]', $node);
            $time2 = $this->http->FindSingleNode('./descendant::span[contains(@class, "FlightInfo__time")][2]', $node);
            $dt2 = EmailDateHelper::parseDateRelative("{$time2} {$date2}", $dt2);

            if ($dt2 > strtotime('+6 months', $dt1)) {
                $dt2 = strtotime('-1 year', $dt2);
            }
            $seg->arrival()
                ->noCode()
                ->name($arrName)
                ->date($dt2);
            // Aircraft
//            $aircraft = $this->http->FindSingleNode('.//div[contains(@class, "flight-plain")]/span[1]/@data-content', $node);
//            $seg->setAircraft($aircraft, false, true);
            // Seats
//            $seats = $this->http->FindNodes('.//div[@data-qa = "addSeat_passengerExtrasItem"]', $node, '/\b([A-Z\d]+)\b/');
//            $seg->setSeats($seats);
            if (!$this->http->FindSingleNode('./descendant::div[contains(@class, "DS__Tooltip__Content")][contains(.,"No seats selected")]',
                $node)
            ) {
                $this->sendNotification('check seats // ZM');
            }
            // Meal
            $meal = $this->http->FindSingleNode('./descendant::div[contains(@data-qa, "meal")][1]', $node) ?: null;
            $seg->addMeal($meal, false, true);
            // Cabin
//            $cabin = $this->http->FindSingleNode('(./ancestor::div[contains(@class, "wrap-flight-section")])[1]//a[@data-content = "Fare information"]', $node);
//            $seg->setCabin($cabin, false, true);
            // Duration
            $durText = $this->http->FindSingleNode('./descendant::span[contains(@class, "FlightInfo__duration-time")]', $node);
            $dur = $this->http->FindPreg('/(.+) travel time/', false, $durText);
            $seg->setDuration($dur, false, true);
            // Miles
            $seg->setMiles($this->http->FindSingleNode('./descendant::span[contains(@class, "FlightInfo__duration-distance")]', $node), false, true);
        }
    }

    private function xpathQuery($query, ?DomNode $parent = null): DomNodeList
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent) ?: new DOMNodeList();
        $this->logger->info("found {$res->length} nodes: {$query}");

        return $res;
    }
}
