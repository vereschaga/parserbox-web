<?php

namespace AwardWallet\Engine\skywards\RewardAvailability;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use Facebook\WebDriver\Exception\ElementClickInterceptedException;
use Facebook\WebDriver\Exception\InvalidElementStateException;
use Facebook\WebDriver\Exception\JavascriptErrorException;
use Facebook\WebDriver\Exception\TimeoutException as TOException;
use Facebook\WebDriver\Exception\WebDriverCurlException;
use Facebook\WebDriver\WebDriverKeys;

// EK662627571
// Banana12_
//remember=xIf83zRKv8f2MgnmII1TimX6vSVCvD8GwbdppPFu4pg
//SSOUser=43d2e56da4dd2193bc0546b8f1ba4a5eb9db1256
class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    // TODO partners = true - for emiratesky
    public $partners = false;
    // TODO
    public static $useMobile = false;
    public static $useNew = true;
    /** @var \HttpBrowser */
    public $browser;
    public $isRewardAvailability = true;
    // debugMode = true  =>  real account (for now)
    private $debugMode = true;

    private $isLoggedIn = false;
    // Valid/NotValid - only 100%, if we know
    private $routeValid;
    private $routeNotValid;

    private $checkUser;
    private $checkRemember;

    private $useMainParser = false;
    private $selenium = true;

    private $profilePage = 'https://www.emirates.com/account/us/english/manage-account/manage-account.aspx';

    private $isHot = false;

    public static function GetAccountChecker($accountInfo)
    {
        if (!self::$useMobile) {
            return new static();
        }

        require_once __DIR__ . "/ParserMobile.php";

        return new ParserMobile();
    }

    public static function getRASearchLinks(): array
    {
        return ['https://fly2.emirates.com/CAB/IBE/SearchAvailability.aspx' => 'search page'];
    }

    public function InitBrowser()
    {
        // parse using accounts
//        $this->debugMode = $this->AccountFields['DebugState'] ?? false;

        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();

        $this->logger->notice($this->partners);

        if ($this->partners) {
            $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);
        } else {
            $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);
        }
//        $this->useChromium(\SeleniumFinderRequest::CHROMIUM_80);
//        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);

//        $this->KeepState = $this->debugMode;
        $this->useCache();
        // for debug
        $this->http->saveScreenshots = true;
        $this->setProxyNetNut(null, 'ca');
        //$this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');
        $this->http->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:122.0) Gecko/20100101 Firefox/122.0');
        //$this->usePacFile(false);
        $this->seleniumRequest->setHotSessionPool(
            self::class,
            'skywards',
            $this->AccountFields['AccountKey'] ?? null
        );
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        if (!isset($this->AccountFields['AccountKey'])) {
            throw new \CheckException('no account', ACCOUNT_ENGINE_ERROR);
        }

        if (!$this->debugMode) {
            $this->logger->error('parser off');

            return false;
        }

        // debug
        $current = $this->http->currentUrl();

        if (isset($current)) {
            $this->logger->warning($current);

            if (strpos($current, 'emirates.com') !== false) {
                $this->isHot = true;
                $this->logger->debug('HOT');
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            }
        }

        if ($this->isHot && ($linkContinue = $this->waitForElement(\WebDriverBy::id('btnExtendSession'), 0))) {
            try {
                $linkContinue->click();
            } catch (\ElementNotVisibleException $e) {
                $this->logger->error('ElementNotVisibleException: ' . $e->getMessage());
            }

            if ($ns = $this->waitForElement(\WebDriverBy::xpath('//a[contains(@class,"ts-session-expire--link")]'),
                0)) {
                $ns->click();
                $this->isLoggedIn = $this->waitForElement(\WebDriverBy::id('ctl00_c_ctrlPayMethods_lblMiles'), 10);

                if ($this->isLoggedIn) {
                    return true;
                }
            }
        }

        try {
            $this->http->GetURL("https://fly2.emirates.com/CAB/ibe.aspx"); // seems this url reset previous data
            /*            if ($this->isHot) {
                            $this->http->GetURL("https://fly2.emirates.com/CAB/ibe.aspx"); // seems this url reset previous data
                        } else {
                            $this->http->GetURL("https://fly2.emirates.com/CAB/IBEauth.aspx");
                        }*/
            // retries
            if ($this->http->FindPreg("/(?:page isn’t working|There is no Internet connection|This site can’t be reached|You don't have permission to access)/ims")
                || $this->http->FindSingleNode("//p[contains(.,'The page you’re trying to access is restricted.')]") // seems like a block. was try to click - did not helped
            ) {
                $this->markProxyAsInvalid();

                throw new \CheckRetryNeededException(5, 0);
            }

            $this->waitFor(function () {
                return $this->waitForElement(\WebDriverBy::xpath("
                          //span[contains(@id,'ctl00_c_ctrlSkyMember_cufon_Label_For_Skyuser')]
                        |  //span[contains(@id,'ctl00_c_ctrlPayMethods_lblMiles')]
                        | //span[contains(.,'Book Classic Rewards Flight')] | //span[contains(.,'Redeem flights')]
                        | //p[contains(.,'Log in to your Emirates account') or contains(.,'Log in to Emirates Skywards')]"),
                    0);
            }, 20);

            $this->http->SaveResponse();

            $member = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@id,'SkyMember')]/span[normalize-space()='Member details']/following::span[1][normalize-space()!='']"), 20);

            $this->isLoggedIn = $this->isHot && ($this->waitForElement(\WebDriverBy::id('ctl00_c_ctrlPayMethods_lblMiles'),
                    10) && $member);

            $this->http->SaveResponse();

            if ($this->isLoggedIn) {
                return true;
            }

            if ($this->isHot) {
                $this->isHot = false;
                $this->http->removeCookies();
                $this->http->GetURL("https://fly2.emirates.com/CAB/IBEauth.aspx");
            }
        } catch (\ScriptTimeoutException | \TimeOutException | TOException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        } catch (\WebDriverCurlException $e) {
            throw new \CheckRetryNeededException(5, 0);
        } catch (\Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
            $this->logger->error("InvalidSessionIdException exception: " . $e->getMessage());
        }

        $this->hideOverlay();

        if ($link = $this->http->FindSingleNode("//a[contains(.,'New search')]/@href")) {
            $this->logger->error('New search');
            $this->saveResponse();
            $this->sendNotification('New search (on start with hack) // ZM');

            try {
                $this->http->GetURL("https://fly2.emirates.com/IBE.aspx");
                $this->saveResponse();
            } catch (\ScriptTimeoutException | \TimeOutException | TOException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            }

            if ($this->waitForElement(\WebDriverBy::xpath("//a[contains(.,'New search')]"), 10)) {
                $this->saveResponse();

                throw new \CheckRetryNeededException(5, 0);
            }
            $this->sendNotification('RA New search (go ahead) // ZM');
        }

        $login = $this->waitForElement(\WebDriverBy::id('btnLogin'), 0);

        if (!$login) {
            if ($this->waitForElement(\WebDriverBy::xpath("//span[contains(.,'Book Classic Rewards Flight')] | //span[contains(.,'Redeem flights')]"),
                10)) {
                $this->isLoggedIn = true;

                return true;
            }
            $this->saveResponse();
            $this->markProxyAsInvalid();
        }

        $this->hideOverlay();
        $this->saveResponse();

        $login = $this->waitForElement(\WebDriverBy::id('btnLogin'), 0);

        if (!$login) {
            $this->logger->error('page not load or something else');

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->waitForElement(\WebDriverBy::xpath("//span[contains(normalize-space(),'0 Skywards Miles')]"), 10)) {
            $this->isLoggedIn = true;

            return true;
        }

        $login->click();

        $frame = $this->waitForElement(\WebDriverBy::id('ctl00_c_SSOLogin_loginIFrame'), 5);

        if (!$frame) {
            $this->saveResponse();

            return false;
        }
        $this->saveResponse();

        return true;
    }

    public function Login()
    {
        if ($this->isLoggedIn) {
            return true;
        }

        if (isset($this->AccountFields['Login']) && $this->AccountFields['Login'] === 'fakelogin') {
            return false;
        }
        $frame = $this->waitForElement(\WebDriverBy::id('ctl00_c_SSOLogin_loginIFrame'), 15);
        $this->driver->switchTo()->frame($frame);

        $email = $this->waitForElement(\WebDriverBy::id('sso-email'));
        $pwd = $this->waitForElement(\WebDriverBy::id('sso-password'), 0);
        $btn = $this->waitForElement(\WebDriverBy::id('login-button'), 0);

        if (!$email || !$pwd || !$btn) {
            $this->saveResponse();
            $this->logger->error("form not load");
            $this->logger->error("check other form");
            $reAuthPwd = $this->waitForElement(\WebDriverBy::id('reauth-password'), 10);
            $btn = $this->waitForElement(\WebDriverBy::id('reauth-login-btn'), 0);

            if (!$reAuthPwd || !$btn) {
                $this->saveResponse();

                return false;
            }

            $reAuthPwd->sendKeys($this->AccountFields['Pass']);
            sleep(1);
            $btn->click();
        } else {
            $email->sendKeys($this->AccountFields['Login']);
            $pwd->sendKeys($this->AccountFields['Pass']);
            sleep(1);
            //$pwd->sendKeys(\WebDriverKeys::ENTER);
            $btn->click();
        }

        $span = $this->waitForElement(\WebDriverBy::xpath("//span[contains(.,'Book Classic Rewards Flight')] | //span[contains(.,'Redeem flights')]"),
            60);

        if (!$span) {
            $warning = $this->waitForElement(\WebDriverBy::xpath("//div[@id='mainContainer']//span[starts-with(normalize-space(),'Warning:')][1]/ancestor::div[1]"),
                0);
            $this->saveResponse();

            if ($warning) {
                $this->logger->error($msg = $warning->getText());

                if (stripos($msg, 'technical problem') !== false) {
                    throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                }
            }

            if ($msg = $this->http->FindSingleNode("//p[contains(.,'Sorry, we have a technical problem at the moment. Please try again later')]")) {
                $this->logger->error($msg);

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            if ($msg = $this->http->FindSingleNode("//p[
                    contains(.,'Your account has been proactively locked as a security precaution') or 
                    contains(.,'Your account has been locked due to the number of unsuccessful login attempts') or 
                    contains(.,'Sorry, your account has been locked. If you need urgent access to your account')
                 ]")
            ) {
                $this->logger->error($msg);
                $this->sendNotification('lock acc // ZM');

                throw new \CheckException($msg, ACCOUNT_LOCKOUT);
            }

            if ($this->http->FindSingleNode('//p[
                contains(text(), "An email with a 6-digit passcode has been sent to")
                or contains(text(), "Please choose how you want to receive your passcode.")
            ]')
            ) {
                return $this->twoStepVerificationBook();
            }
            $error = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'error')][contains(.,'number or password you entered is incorrect')]"),
                0);

            if ($error) {
                $this->logger->error($msg = $error->getText());

                throw new \CheckException($msg, ACCOUNT_ENGINE_ERROR);
            }

            if ($this->waitForElement(\WebDriverBy::id('ctl00_c_SSOLogin_loginIFrame'), 0)
                || $this->waitForElement(\WebDriverBy::id('sso-email'), 0)) {
                $this->http->removeCookies();
                $this->SaveResponse();
                $this->logger->error('has block. restart with other account');
//                $this->sendNotification("check retry after block // ZM");

                throw new \CheckRetryNeededException(5, 0);
            }

            return false;
        }
        $this->saveResponse();

        if ($this->waitForElement(\WebDriverBy::xpath("//p[normalize-space()='Log in to your Emirates account']/ancestor::div[@id='ctl00_c_LoginPopUpButton']"),
            0)) {
            if ($this->http->FindSingleNode("//div[@id='ctl00_c_ctrlSkyMember_dvSkyTier'][contains(normalize-space(),'Skywards Miles')][count(.//text()[normalize-space()!=''])!=2]")) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->sendNotification("check login // ZM");
        }
        $this->isLoggedIn = true;

        return true;
    }

    public function getRewardAvailabilitySettings()
    {
        $arrCurrencies = ['AED']; // по идее пров поддерживает и другие, но там конвертация и approx.

        return [
            'supportedCurrencies'      => $arrCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'AED',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        $this->hideOverlay();

        if ($fields['Currencies'][0] !== 'AED') {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['DepDate'] > strtotime('+328 day')) {
            $this->SetWarning('too late');

            if ($this->isLoggedIn) {
                $this->saveSession();
            }

            return [];
        }

        if ($fields['Adults'] > 9) {
            $this->SetWarning('you can check max 9 travellers');

            if ($this->isLoggedIn) {
                $this->saveSession();
            }

            return ['routes' => []];
        }

        try {
            $this->routeNotValid = !$this->validRoute($fields);
            /*if ($this->routeNotValid) {
                return [];
            }*/

            // parse from emirates - comment for now
            /*            if ($this->isHot && !$this->partners
                            && ($link = $this->waitForElement(\WebDriverBy::xpath("(//a[contains(.,'Emirates logo') and contains(@href,'/SessionHandler.aspx?')])[1]"),
                                0))
                        ) {
                            $link->click();

                            $res = $this->parseFromEmirates($fields);

                            if (is_array($res)) {
                                $this->saveSession();

                                return $res;
                            }
                            $this->http->GetURL("https://fly2.emirates.com/CAB/ibe.aspx");
                        }
                        $this->logCookie();*/

            try {
                $res = $this->enterRequest($fields);
            } catch (\UnknownServerException $e) {
                $this->logger->error("exception: " . $e->getMessage());
                $this->DebugInfo = "exception";
                $this->saveResponse();
                $res = false;
            } catch (ElementClickInterceptedException | WebDriverCurlException | \WebDriverCurlException $e) {
                $this->logger->error("exception: " . $e->getMessage());
                $this->saveResponse();

                throw new \CheckRetryNeededException(5, 0);
            }

            if (is_array($res)) {
                if ($this->isLoggedIn) {
                    $this->saveSession();
                }

                return $res;
            }

            if ($link = $this->http->FindSingleNode("//a[contains(.,'New search')]/@href")) {
                $this->logger->error('New search!!!');

                $newSearch = $this->waitForElement(\WebDriverBy::xpath("//a[contains(.,'New search')]"), 0);

                if ($newSearch) {
                    $newSearch->click();
                    $this->saveResponse();
                } else {
                    $this->http->GetURL("https://fly2.emirates.com/IBE.aspx");
                    $this->saveResponse();
                }
                $this->waitForElement(\WebDriverBy::xpath("(//a[contains(.,'Emirates logo') and contains(@href,'/SessionHandler.aspx?')])[1]"),
                    10);

//                sleep(5);
//                $this->checkRefreshCookie();

                if ($this->isLoggedIn) {
                    $this->saveSession();
                }

                if ($this->routeNotValid) {
                    $this->SetWarning("There are no flights from {$fields['DepCode']} to {$fields['ArrCode']}");

                    return [];
                }

                $this->logCookie();

                if (!$this->partners
                    && ($link = $this->waitForElement(\WebDriverBy::xpath("(//a[contains(.,'Emirates logo') and contains(@href,'/SessionHandler.aspx?')])[1]"),
                        0))
                ) {
                    $link->click();

                    $res = $this->parseFromEmirates($fields);

                    if (is_array($res)) {
                        $this->saveSession();

                        return $res;
                    }
                }

                // не будем терять время,сразу рестарт. не помогает ретраи(через клик, через урлы), домен fly10 etc
                throw new \CheckRetryNeededException(5, 0);
            }

            // TODO: tmp (debug)
            if ($this->checkNoFlights()) {
                if ($this->isLoggedIn) {
                    $this->saveSession();
                }

                return ['routes' => []];
            }

            if ($contBtn = $this->waitForElement(\WebDriverBy::id('ctl00_c_btnContinue'), 0)) {
                $contBtn->click();
                $this->saveResponse();
            }

            if ($this->checkNoFlights()) {
                if ($this->isLoggedIn) {
                    $this->saveSession();
                }

                return ['routes' => []];
            }

            try {
                $this->http->SaveResponse();
            } catch (\Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
                $this->logger->error("InvalidSessionIdException: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($this->http->FindSingleNode("//span[contains(.,'Book Classic Rewards Flight')] | //span[contains(.,'Redeem flights')]")
                && (time() - $this->requestDateTime) < 100
            ) {
                // не угадаешь что лучше... идти на эмираты, или по новой попробовать ввод. и то, и то иногда помогает.
                $res = $this->enterRequest($fields, true);

                if (is_array($res)) {
                    if ($this->isLoggedIn) {
                        $this->saveSession();
                    }

                    return $res;
                }

                if ($link = $this->http->FindSingleNode("//a[contains(.,'New search')]/@href")) {
                    $this->logger->error('New search');

                    if ($this->routeNotValid) {
                        $this->SetWarning("There are no flights from {$fields['DepCode']} to {$fields['ArrCode']}");

                        return [];
                    }

                    $this->logCookie();

                    if (!$this->partners
                        && ($link = $this->waitForElement(\WebDriverBy::xpath("(//a[contains(.,'Emirates logo') and contains(@href,'/SessionHandler.aspx?')])[1]"),
                            0))
                    ) {
                        $link->click();

                        $res = $this->parseFromEmirates($fields);

                        if (is_array($res)) {
                            $this->saveSession();

                            return $res;
                        }
                    }

                    throw new \CheckRetryNeededException(5, 0);
                }

                if ($this->checkNoFlights()) {
                    if ($this->isLoggedIn) {
                        $this->saveSession();
                    }

                    return ['routes' => []];
                }
            } elseif (
                $this->http->FindSingleNode("//span[contains(.,'Book Classic Rewards Flight')] | //span[contains(.,'Redeem flights')]")
                && (time() - $this->requestDateTime) > 100
            ) {
                throw new \CheckRetryNeededException(5, 0);
            }

            if (!$this->http->FindSingleNode("//p[normalize-space()='Lowest price for all passengers']")) {
                if ($this->http->FindSingleNode("//a[@id='ctl00_c_btnContinue']")) {
                    $this->driver->executeScript("document.querySelector('#ctl00_c_btnContinue').click();");
                    sleep(1);
                }
                $this->saveResponse();
            }

            if (!$this->http->FindSingleNode("//h2[normalize-space()='Redeem Skywards Miles']")
                || !$this->http->FindSingleNode("//p[normalize-space()='Lowest price for all passengers']")
            ) {
                $this->saveResponse();

                if ($this->checkNoFlights()) {
                    if ($this->isLoggedIn) {
                        $this->saveSession();
                    }

                    return ['routes' => []];
                }

                throw new \CheckException("something went wrong", ACCOUNT_ENGINE_ERROR);
            }
        } catch (\WebDriverCurlException | \WebDriverException $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\NoSuchDriverException $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());

            if (!empty($this->ErrorMessage) && $this->ErrorCode === ACCOUNT_WARNING) {
                return ['routes' => []];
            }

            throw new \CheckRetryNeededException(5, 0, 'NoSuchDriverException', ACCOUNT_ENGINE_ERROR);
        }
        $routes = $this->parseRewardFlights($fields);

        if (!empty($routes)) {
            if ($this->isLoggedIn) {
                $this->saveSession();
            }
        }

        return ['routes' => $routes];
    }

    public function twoStepVerificationBook()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Two-step verification', ['Header' => 3]);

        if (
            $this->http->FindSingleNode('//p[contains(text(), "Please choose how you want to receive your passcode.")]')
            && ($email = $this->waitForElement(\WebDriverBy::xpath("//div[label[@for = 'radio-button-email']]"), 0))
        ) {
            $email->click();
            $sendOTP = $this->waitForElement(\WebDriverBy::xpath("//button[@id = 'send-OTP-button']"), 0);
            $sendOTP->click();

            $this->waitForElement(\WebDriverBy::xpath('//p[contains(text(), "An email with a 6-digit passcode has been sent to")]'),
                5);
            $this->saveResponse();
            $this->holdSession();
        }

        $question = $this->http->FindSingleNode('//p[contains(text(), "An email with a 6-digit passcode has been sent to")]');

        if (!$question) {
            $this->logger->error("something went wrong");

            return false;
        }
        $answerInputs = $this->driver->findElements(\WebDriverBy::xpath("//input[contains(@class, 'otp-input-field__input')]"));
        $this->saveResponse();
        $this->logger->debug("count answer inputs: " . count($answerInputs));

        if (!$question || empty($answerInputs)) {
            $this->logger->error("something went wrong");

            return false;
        }
        $this->saveResponse();

        if (!isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, 'Question');

            return false;
        }
        $this->logger->debug("entering answer...");
        $answer = $this->Answers[$question];

        foreach ($answerInputs as $i => $answerInput) {
            if (!isset($answer[$i])) {
                $this->logger->error("wrong answer");

                break;
            }
            $answerInput->sendKeys($answer[$i]);
            $this->saveResponse();
        }// foreach ($elements as $key => $element)
        unset($this->Answers[$question]);
        $this->saveResponse();

        $this->logger->debug("wait errors...");
        $errorXpath = "//p[
                contains(text(), 'The one-time passcode you have entered is incorrect')
                or contains(text(), ' incorrect attempts to enter your passcode. You have ')
                or contains(text(), 'Sorry, you have exceeded the allowed number of attempts to enter your passcode. Your account is temporarily locked for 30 minutes.')
        ]";
        $error = $this->waitForElement(\WebDriverBy::xpath($errorXpath), 5);
        $this->saveResponse();

        if (!$error && $this->waitForElement(\WebDriverBy::xpath('//div[contains(text(), "Loading")]'), 0)) {
            $error = $this->waitForElement(\WebDriverBy::xpath($errorXpath), 10);
            $this->saveResponse();
        }

        if ($error) {
            $message = $error->getText();

            if (
                strpos($message, 'The one-time passcode you have entered is incorrect') !== false
                || strpos($message, ' incorrect attempts to enter your passcode. You have ') !== false
            ) {
                /*$this->logger->notice("resetting answers");
                $this->AskQuestion($question, $message, 'Question');*/
                $this->holdSession();

                return false;
            }

            if (strpos($message,
                    'Sorry, you have exceeded the allowed number of attempts to enter your passcode. Your account is temporarily locked for 30 minutes.') !== false
            ) {
                throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($error)
        $this->logger->debug("success");
        $this->browser = $this->http;

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if (
             $this->waitForElement(\WebDriverBy::xpath("//p[contains(text(), 'Your session has expired')]"), 0)
        ) {
            $this->logger->alert('new session detected');

            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step === "Question") {
            return $this->twoStepVerificationBook();
        }

        return true;
    }

    private function parseFromEmirates($fields)
    {
        $this->logger->notice(__METHOD__);

        $this->waitForElement(\WebDriverBy::xpath("(//a[./img[contains(@alt,'Emirates logo')]])[1]"), 20);
        $rewards = $this->waitForElement(\WebDriverBy::xpath("//label[normalize-space()='Classic rewards']"), 2);

        if ($rewards) {
            if (!$this->driver->executeScript("return document.querySelector('#search-flight__rewards_checkbox').checked;")) {
                $this->logger->debug('click [Classic rewards]');
                $rewards->click();
            }
        } else {
            $this->saveResponse();

            return false;
        }
        $this->saveResponse();

        if (!$this->validRouteEmiratesCom($fields)) {
            return ['routes' => []];
        }
        $this->http->saveScreenshots = false;

        try {
            $res = $this->enterRequestEmirates($fields);
        } catch (\StaleElementReferenceException | \UnknownServerException $e) {
            $this->logger->error($e->getMessage());
            $this->logger->notice('usually js failed. not load elements');
            $link = $this->waitForElement(\WebDriverBy::xpath("(//a[@data-link='Emirates'])[1]"), 0);
            $link->click();
            $res = $this->enterRequestEmirates($fields);
        }

        if (is_array($res)) {
            if ($this->isLoggedIn) {
                $this->saveSession();
            }

            return $res;
        }

        if ($this->checkNoFlights()) {
            if ($this->isLoggedIn) {
                $this->saveSession();
            }

            return ['routes' => []];
        }

        if ($contBtn = $this->waitForElement(\WebDriverBy::id('ctl00_c_btnContinue'), 0)) {
            $contBtn->click();
            $this->saveResponse();
        }

        if ($this->checkNoFlights()) {
            if ($this->isLoggedIn) {
                $this->saveSession();
            }

            return ['routes' => []];
        }

        if ($res === false) {
            throw new \CheckRetryNeededException(5, 0);
        }
        $routes = $this->parseRewardFlights($fields);

        if (!empty($routes)) {
            if ($this->isLoggedIn) {
                $this->saveSession();
            }
        }

        return ['routes' => $routes];
    }

    private function enterRequestEmirates($fields)
    {
        $this->logger->notice(__METHOD__);

        if (strpos($this->http->currentUrl(), 'fly2.emirates.com') === false) {
            $this->http->GetURL("https://fly2.emirates.com/CAB/IBEauth.aspx");
        }

        $xpath = "//input[@name='Departure Airport' or @name='Departure airport']";
        $dep = $this->waitForElement(\WebDriverBy::xpath($xpath), 15);

        if (!$dep) {
            return false;
        }
        $continue = $this->waitForElement(\WebDriverBy::xpath("//div[@id='panel0']//a[./span[normalize-space()='Continue'] and contains(@class,'search-flight')]"),
            0);

        if ($continue) {
            $continue->click();
        }

        $this->driver->executeScript("document.querySelector('input[name=\"Departure Airport\"], input[name=\"Departure airport\"]').scrollIntoView();");
        $this->saveResponse();

        $airport = $this->fillAirportEmirates('d', $fields['DepCode'], $xpath);

        $cnt = 0;

        while ($airport === 'no airport' && $cnt < 3) {
            $this->logger->debug('try enter again');
            $airport = $this->fillAirportEmirates('d', $fields['DepCode'], $xpath, true);
            $cnt++;
        }

        if ($airport === 'no airport' || trim($airport) !== $fields['DepCode']) {
            if ($airport === 'NYC' && in_array($fields['DepCode'], ['JFK', 'EWR'])) {
                $this->logger->warning('getting NYC');
            } else {
                $this->sendNotification('check: no DEP airport // ZM');

                $this->http->saveScreenshots = true;
                $this->saveResponse();

                return false;
            }
        }
        $xpath = "//input[@name='Arrival Airport' or @name='Arrival airport']";
        $arr = $this->waitForElement(\WebDriverBy::xpath($xpath), 4);

        if (!$arr) {
            return false;
        }

        $airport = $this->fillAirportEmirates('a', $fields['ArrCode'], $xpath);

        $cnt = 0;

        while ($airport === 'no airport' && $cnt < 3) {
            $this->logger->debug('try enter again');
            $airport = $this->fillAirportEmirates('a', $fields['ArrCode'], $xpath, true);
            $cnt++;
        }

        if ($airport === 'no airport' || trim($airport) !== $fields['ArrCode']) {
            if ($airport === 'NYC' && in_array($fields['ArrCode'], ['JFK', 'EWR'])) {
                $this->logger->warning('getting NYC');
            } else {
                $this->sendNotification('check: no ARR airport // ZM');
                $this->http->saveScreenshots = true;
                $this->saveResponse();

                return false;
            }
        }
        $this->saveResponse();

        $continue = $this->waitForElement(\WebDriverBy::xpath("//div[@id='panel0']//a[./span[normalize-space()='Continue'] and contains(@class,'search-flight')]"),
            0);

        if ($continue) {
            $continue->click();
        }

        if (!($oneWay = $this->waitForElement(\WebDriverBy::xpath("//label[contains(@class,'one-way')]"), 1))) {
            if ($dates = $this->waitForElement(\WebDriverBy::id('search-flight-date-picker--depart'), 0)) {
                $dates->click();
            } else {
                $this->saveResponse();

                return false;
            }
        }

        if (!$this->driver->executeScript("return document.querySelector('label.one-way input').checked;")) {
            $this->logger->debug("click one-way and date (for close calendar)");
            $this->waitForElement(\WebDriverBy::xpath("//label[contains(@class,'one-way')]"), 1)->click();
        }
        //select date
        $leftXpath = "//div[@id='panel0']//eol-calendar[contains(@title,'departure date')]//a[contains(@class,'icon-arrow-left')]/ancestor::div[1]";
        $rightXpath = $leftXpath . "/ancestor::div[1]/following-sibling::div[1]/div[1]";
        $leftDate = $this->getCalendarDate($leftXpath);
        $depDate = strtotime('01 ' . date("M y", $fields['DepDate']));
        $cnt = 0;

        if ($depDate < $leftDate) {
            while ($depDate !== $leftDate && $cnt < 11) {
                $this->waitForElement(\WebDriverBy::xpath($leftXpath . "/a"), 0)->click();
                $leftDate = $this->getCalendarDate($leftXpath);
                $cnt++;
            }
        } else {
            while ($depDate !== $leftDate && $cnt < 11) {
                $this->waitForElement(\WebDriverBy::xpath($rightXpath . "/a"), 0)->click();
                $leftDate = $this->getCalendarDate($leftXpath);
                $cnt++;
            }
        }
        $rightDate = $this->getCalendarDate($rightXpath);

        if ($depDate !== $leftDate && $depDate !== $rightDate) {
            $this->sendNotification('check date // ZM');

            throw new \CheckException('wrong date', ACCOUNT_ENGINE_ERROR);
        }
        $day = (int) date('d', $fields['DepDate']);
        $month = ((int) date('m', $fields['DepDate'])) - 1;
        $depDate = $day . $month . date('Y', $fields['DepDate']);

        $depDateXpath = "//div[@id='panel0']//eol-calendar[contains(@title,'departure date')]//td[not(contains(@class,'ek-datepicker__day--inactive')) and contains(@data-string,'{$depDate}')]";

        if (!($selDate = $this->waitForElement(\WebDriverBy::xpath($depDateXpath), 0))) {
            $this->SetWarning('inactive date');

            return [];
        }
        $selDate->click();

        // выбираем первую попавшуюся. далее скриптом
//            $this->waitForElement(\WebDriverBy::xpath("(//td/a[not(@aria-disabled)])[2]"), 3)->click();

        if (!$this->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Add Return']"), 2)) {
            throw new \CheckRetryNeededException(5, 0);
        }
        $this->saveResponse();

        // adults
        try {
            $this->waitForElement(\WebDriverBy::xpath("//label[contains(.,'Passengers')]/following-sibling::input/ancestor::div[1]"),
                0)->click();
            $this->waitForElement(\WebDriverBy::xpath("//input[@name='Passengers']"), 0)->click();
        } catch (\UnknownServerException $e) {
            $this->logger->error('UnknownServerException: ' . $e->getMessage());
            $this->sendNotification('check UnknownServerException (pax) // ZM');
            $this->saveResponse();
            $this->waitForElement(\WebDriverBy::xpath("//label[contains(.,'Passengers')]/following-sibling::input/ancestor::div[1]"),
                0)->click();
            $this->waitForElement(\WebDriverBy::xpath("//input[@name='Passengers']"), 0)->click();
        }
        $span = $this->waitForElement(\WebDriverBy::xpath("//div[@data-type='adults']//span[contains(.,'Ages 12+') or contains(.,'Ages 16+')]/preceding-sibling::label/span[1]"),
            2);

        if (!$span) {
            $this->sendNotification('check adults // ZM');
            $this->saveResponse();
            $this->waitForElement(\WebDriverBy::xpath("//label[contains(.,'Passengers')]/following-sibling::input/ancestor::div[1]"),
                0)->click();
            $this->waitForElement(\WebDriverBy::xpath("//input[@name='Passengers']"), 0)->click();
            $span = $this->waitForElement(\WebDriverBy::xpath("//div[@data-type='adults']//span[contains(.,'Ages 12+') or contains(.,'Ages 16+')]/preceding-sibling::label/span[1]"),
                2);

            if (!$span) {
                throw new \CheckRetryNeededException(5, 0);
            }
        }
        $cntAdults = $span->getText();

        if ($cntAdults != $fields['Adults']) {
            $cnt = 0;

            while (($minus = $this->waitForElement(\WebDriverBy::xpath("//div[@data-type='adults']//span[contains(.,'Ages 12+') or contains(.,'Ages 16+')]/ancestor::div[2]//button[contains(.,'Decrease')][@aria-disabled='false']"),
                    0)) && $cnt < 8) {
                $minus->click();
                $cnt++;
            }
            $cnt = 1;

            while (($plus = $this->waitForElement(\WebDriverBy::xpath("//div[@data-type='adults']//span[contains(.,'Ages 12+') or contains(.,'Ages 16+')]/ancestor::div[2]//button[contains(.,'Increase')][@aria-disabled='false']"),
                    0)) && $cnt != $fields["Adults"]) {
                $plus->click();
                $cnt++;
            }
            $cntAdults = $this->waitForElement(\WebDriverBy::xpath("//div[@data-type='adults']//span[contains(.,'Ages 12+') or contains(.,'Ages 16+')]/preceding-sibling::label/span[1]"),
                2)->getText();

            if ($cntAdults != $fields['Adults']) {
                $this->sendNotification('check adults // ZM');

                throw new \CheckException('wrong adults', ACCOUNT_ENGINE_ERROR);
            }
        }
        $this->waitForElement(\WebDriverBy::id('search-flight-class'), 0)->click();
        $cabinText = $this->getCabin($fields['Cabin'], true);
        $this->waitForElement(\WebDriverBy::xpath("//a/p[contains(.,'{$cabinText}')]"), 0)->click();
        $this->saveResponse();

        if (!$this->driver->executeScript("return document.querySelector('#search-flight__rewards_checkbox').checked;")) {
            $rewards = $this->waitForElement(\WebDriverBy::xpath("//label[normalize-space()='Classic rewards']"), 2);

            if ($rewards) {
                $rewards->click();
            }
        }

        if (!$this->driver->executeScript("return document.querySelector('#search-flight__rewards_checkbox').checked;")) {
            $this->logger->debug('not checked Classic rewards');

            throw new \CheckRetryNeededException(5, 0);
        }
        $airportTextDep = $this->driver->executeScript("
        return document.querySelector('form>input[name=\"seldcity1\"]').value
        ");
        $airportTextArr = $this->driver->executeScript("
        return document.querySelector('form>input[name=\"selacity1\"]').value
        ");

        if (trim($airportTextDep) !== $fields['DepCode'] || trim($airportTextArr) !== $fields['ArrCode']) {
            if ((trim($airportTextDep) === 'NYC'
                    && in_array($fields['DepCode'], ['JFK', 'EWR'])
                    && trim($airportTextArr) === $fields['ArrCode'])
                || (trim($airportTextArr) === 'NYC'
                    && in_array($fields['ArrCode'], ['JFK', 'EWR'])
                    && trim($airportTextDep) === $fields['DepCode'])
            ) {
                $this->logger->debug('go on with NYC');
            } else {
                $this->logger->debug('wrong airports');

                throw new \CheckRetryNeededException(5, 0);
            }
        }
        $search = $this->waitForElement(\WebDriverBy::xpath("//button[normalize-space()='Search flights']"), 3);

        if (!$search) {
            throw new \CheckException('can\'t find button', ACCOUNT_ENGINE_ERROR);
        }

        return $this->clickSearch($search, $fields);
    }

    private function getCalendarDate($leftXpath)
    {
        $leftMonth = $this->waitForElement(\WebDriverBy::xpath($leftXpath . "/div[1]"), 0)->getText();
        $leftYear = $this->waitForElement(\WebDriverBy::xpath($leftXpath . "/div[2]"), 0)->getText();

        return strtotime('01 ' . $leftMonth . ' ' . $leftYear);
    }

    private function logCookie()
    {
        try {
            $cookies = $this->driver->manage()->getCookies();
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('InvalidArgumentException: ' . $e->getMessage());
            $cookies = [];
        }

        foreach ($cookies as $cookie) {
            $this->logger->debug($cookie['name'] . '=' . $cookie['value']);
        }
    }

    private function saveSession()
    {
        $this->logger->notice(__METHOD__);
        $this->keepSession(true);
    }

    private function checkNoFlights(): bool
    {
        $this->logger->notice(__METHOD__);

        if ($msg = $this->http->FindSingleNode("//div[@id='mainContainer']//span[starts-with(normalize-space(),'Warning:')][1]/ancestor::div[1]")) {
            $this->logger->error($msg);

            if (stripos($msg, 'no flights') !== false
                || stripos($msg,
                    'We couldn’t find any availability in') !== false
                || stripos($msg,
                    'unable to price the selected itinerary. Please try different dates') !== false
                || stripos($msg,
                    'There are no seats available on the dates you requested. Please try different dates') !== false
                || stripos($msg,
                    'unable to find flight options to match your search. Please try a different search') !== false
                || stripos($msg,
                    'unable to get a price for your selected itinerary. Please try a different search') !== false
                || stripos($msg,
                    'Choose your departure date: Please choose your departure date: You may not select a date more than 328 days from today.') !== false
            ) {
                $this->SetWarning($msg);

                return true;
            }

            if (stripos($msg, 'Please select a price before continuing with your booking.') !== false
                && $this->http->FindSingleNode("//table[@id='ctl00_c_gridTableMain']//td[contains(@class,'search-date')][contains(.,'Not available') or contains(.,'No availability')]")
            ) {
                $this->SetWarning('Not available');

                return true;
            }

            if ($msg = $this->http->FindSingleNode("//p[contains(.,'Sorry, we have a technical problem at the moment. Please try again later')]")) {
                $this->logger->error($msg);

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }
        }

        if (($msg = $this->http->FindSingleNode("//p[contains(.,'We couldn’t find any availability in Business Class that matches your search. You can check the available fares below for the next higher class')]"))
            && $this->http->FindSingleNode("//table[@id='ctl00_c_gridTableMain']//td[contains(@class,'search-date')][contains(.,'Not available') or contains(.,'No availability')]")
        ) {
            // NB: на таком иногда continue стал давать марщруты без пересадок, но без миль
            $this->SetWarning('Not available');

            return true;
        }

        if ($msg = $this->http->FindSingleNode("//span[@id='sHLErrorsText']/ancestor::div[1]")) {
            $this->logger->error($msg);

            if (stripos($msg,
                    'The requested departure and destination cities are in the same country. Please try again using the Advanced search option') !== false
                || stripos($msg,
                    'Sorry, we were unable to price the selected itinerary. Please try different dates or fare combinations') !== false
                || stripos($msg,
                    'Sorry, we were unable to process this request. Please try again later, or contact your local Emirates office for help') !== false
            ) {
                $this->SetWarning($msg);

                return true;
            }

            if (stripos($msg, 'Sorry, we have a technical problem at the moment. Please try again later') !== false) {
                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    private function getCabin(string $str, bool $skywardCabinCode, $short = false, $num = false)
    {
//        if ($str === 'premiumEconomy') {// no premium
//            $str = 'economy';
//        }
        // TODO maybe it's better remake with params (short, num)
        $cabins = [
            'economy'        => 'Economy Class',
            'business'       => 'Business Class',
            'premiumEconomy' => 'Premium Economy',
            'firstClass'     => 'First Class',
        ];
        $shortCabins = [
            'economy'        => 'Economy',   // 0,
            'business'       => 'Business', // 1,
            'firstClass'     => 'First',  // 2,
            'premiumEconomy' => 'Premium Economy',
        ];
        $numCabins = [
            'economy'        => 0,
            'business'       => 1,
            'firstClass'     => 2,
            'premiumEconomy' => 7,
        ];

        switch (true) {
            case $short && !$num:
                $cabins = !$skywardCabinCode ? array_flip($shortCabins) : $shortCabins;

                break;

            case $short && $num:
                $this->logger->error('wrong request getCabin');
                $cabins = [];

                break;

            case !$short && $num:
                $cabins = !$skywardCabinCode ? array_flip($numCabins) : $numCabins;

                break;

            case !$short && !$num:
                if (!$skywardCabinCode) {
                    $cabins = array_flip($cabins);
                }

                break;
        }

        if (isset($cabins[$str])) {
            return $cabins[$str];
        }
        $this->sendNotification("check cabin {$str} (" . var_export($skywardCabinCode, true) . ", "
            . var_export($short, true) . '-' . var_export($num, true) . ") // ZM");

        throw new \CheckException("new cabin code", ACCOUNT_ENGINE_ERROR);
    }

    private function enterRequest($fields, $isRetry = false)
    {
        $this->logger->notice(__METHOD__);

        $this->hideOverlay();

        if (!$this->waitForElement(\WebDriverBy::xpath("//div[@id='dvFrom']/input[@type!='hidden']"), 0)) {
            try {
                $this->driver->navigate()->refresh();
            } catch (\Throwable $e) {
                $this->logger->error('Exception: ' . $e->getMessage());
                $this->sendNotification('navigate refresh // ZM');
            }
            $this->saveResponse();
        }

        $span = $this->waitForElement(\WebDriverBy::xpath("//span[contains(.,'Book Classic Rewards Flight')] | //span[contains(.,'Redeem flights')]"),
            0);

        if (!$span) {
            throw new \CheckRetryNeededException(5, 0);
        }

        /*$mover = new \MouseMover($this->driver);
        $mover->enableCursor();
        $mover->logger = $this->logger;
        //        $mover->duration = rand(100000, 120000);
        //        $mover->steps = rand(50, 70);
        $span = $this->waitForElement(\WebDriverBy::xpath("//span[contains(.,'Book Classic Rewards Flight')] | //span[contains(.,'Redeem flights')]/ancestor::div[1]"), 0);

        $this->logger->debug('move to dvPoMiles field');
        $mover->moveToElement($span);
        $mover->click();
        sleep(2);*/

        $this->logCookie();

        $this->driver->executeScript("document.querySelector('#dvPoMiles').click();");
        $this->driver->executeScript("document.querySelector('#ctl00_c_ctrlPayMethods_lblMiles').click();");

        if ($this->partners) {
            $this->driver->executeScript("document.querySelector('#dvPoPartnerMiles').click();");
        } else {
            $this->driver->executeScript("document.querySelector('#dvPoEKMiles').click();");
        }
        $this->driver->executeScript("document.querySelector('#dvRadioOneway').click();");

        // fill departure
        $from = $this->waitForElement(\WebDriverBy::xpath("//div[@id='dvFrom']/input[@type!='hidden']"), 3);

        if (!$from) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $partialId = 'ctl00_c_CtWNW_ddlFrom';
        $depText = $this->fillAirport($from, $partialId, $fields['DepCode']);

        if (null == $depText && $fields['DepCode'] === 'ORL') {
            $this->saveResponse();
            // bug (отдает что ORL валидный, но выбрать не дает)
            $this->SetWarning("There are no flights from {$fields['DepCode']} to {$fields['ArrCode']}. Try MCO instead {$fields['DepCode']}");

            return [];
        }

        if (null == $depText) {
            $this->saveResponse();

            if ($this->routeNotValid) {
                $this->SetWarning("There are no flights from {$fields['DepCode']} to {$fields['ArrCode']}");

                return [];
            }

            if ($this->routeValid) {
                $depText = $this->fillAirport2($from, $partialId, $fields['DepCode']);

                if (!$this->http->FindPreg("/\({$fields['DepCode']}\)$/", false, $depText)) {
                    $this->saveResponse();

                    throw new \CheckRetryNeededException(5, 0);
                }
                $departureEntered = true;
            } else {
                $this->SetWarning("no {$fields['DepCode']} airport");

                return [];
            }
        }

        if (!isset($departureEntered)) {
            $this->driver->executeScript("document.querySelector('#{$partialId}-suggest').value='{$depText}'");
            $this->driver->executeScript("document.querySelector('#{$partialId}').value='{$fields['DepCode']}'");
        }

        // fill arrival
        $to = $this->waitForElement(\WebDriverBy::xpath("//div[@id='dvTo']/input[@type!='hidden']"), 0);

        if (!$to) {
            $this->saveResponse();

            if (!$isRetry) {
                $this->http->GetURL('https://fly10.emirates.com/IBE.aspx');

                if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached)/ims')) {
                    throw new \CheckRetryNeededException(5, 0);
                }
                $this->waitForElement(\WebDriverBy::xpath("//span[contains(.,'Book Classic Rewards Flight')] | //span[contains(.,'Redeem flights')]"),
                    45);
                $this->saveResponse();

                return $this->enterRequest($fields, true);
            }

            throw new \CheckRetryNeededException(5, 0);
        }
        $partialId = 'ctl00_c_CtWNW_ddlTo';
        $arrText = $this->fillAirport($to, $partialId, $fields['ArrCode']);

        if (null == $arrText && $fields['ArrCode'] === 'ORL') {
            $this->saveResponse();
            // bug (отдает что ORL валидный, но выбрать не дает)
            $this->SetWarning("There are no flights from {$fields['DepCode']} to {$fields['ArrCode']}. Try MCO instead {$fields['ArrCode']}");

            return [];
        }

        if (null == $arrText) {
            $this->saveResponse();

            if ($this->routeNotValid) {
                $this->SetWarning("There are no flights from {$fields['DepCode']} to {$fields['ArrCode']}");

                return [];
            }

            if ($this->routeValid) {
                $arrText = $this->fillAirport2($to, $partialId, $fields['ArrCode']);

                if (!$this->http->FindPreg("/\({$fields['ArrCode']}\)$/", false, $arrText)) {
                    $this->SetWarning("Warning:Sorry, the selected itinerary is unavailable. Please check this page(the link opens in a new window) for flight details, restrictions and eligibility to travel");

                    return [];
                }
                $arrivalEntered = true;
            } else {
                $this->SetWarning("no {$fields['ArrCode']} airport");

                return [];
            }
        }

        if (!isset($arrivalEntered)) {
            $this->driver->executeScript("document.querySelector('#{$partialId}-suggest').value='{$arrText}'");
            $this->driver->executeScript("document.querySelector('#{$partialId}').value='{$fields['ArrCode']}'");
        }

        $cabin = $this->waitForElement(\WebDriverBy::id('ctl00_c_CtWNW_flightClass_chosen'));

        if ($cabin) {
            $cabin->click();
            $cabinText = $this->getCabin($fields['Cabin'], true, false);
            $check = $this->waitForElement(\WebDriverBy::xpath("//li[contains(.,'{$cabinText}')]"), 5);

            if (!$check) {
                $this->sendNotification('no cabin // ZM');
                $this->saveResponse();

                throw new \CheckRetryNeededException(5, 0);
            }

            try {
                $check->click();
            } catch (\UnknownServerException $e) {
                $this->logger->error('UnknownServerException: ' . $e->getMessage());
                $this->sendNotification('check click cabin // ZM');
            }
        }
        $depDate = date("d M y", $fields['DepDate']);
        $this->logger->debug('input date: ' . $depDate);

        $this->driver->executeScript("document.querySelector('#txtDepartDate').value='{$depDate}'");

        $checkAdults = $this->waitForElement(\WebDriverBy::id('ctl00_c_CtNoOfTr_numberAdults_chosen'));

        if (!$checkAdults) {
            throw new \CheckException('other page', ACCOUNT_ENGINE_ERROR);
        }

        try {
            $regExp = "/You\s+have\s+selected\s+{$fields['Adults']}\s+adults\.\s+{$fields['Adults']}(?:\s*Label)?$/";

            if (!$this->http->FindPreg($regExp, false, $checkAdults->getText())) {
                $this->logger->debug('checkAdults->click');

                try {
                    $checkAdults->click();
                    $this->logger->debug('checkAdults->clicked');
                } catch (\StaleElementReferenceException | \UnexpectedJavascriptException $e) {
                    $this->sendNotification("debug click // ZM");
                    $this->logger->error("Exception: " . $e->getMessage());
                }
                $this->logger->debug('checkAdults->go on');
                $element = $this->waitForElement(\WebDriverBy::xpath("//div[@id='ctl00_c_CtNoOfTr_numberAdults_chosen']//li[./span[normalize-space()='{$fields['Adults']}']]"),
                    0);

                if (!$element) {
                    $this->logger->debug('checkAdults->executeScript');
                    $this->driver->executeScript("document.querySelector('#ctl00_c_CtNoOfTr_numberAdults_chosen').querySelector('ul').scrollIntoView();");
                    $element = $this->waitForElement(\WebDriverBy::xpath("//div[@id='ctl00_c_CtNoOfTr_numberAdults_chosen']//li[./span[normalize-space()='{$fields['Adults']}']]"),
                        0);

                    if (!$element) {
                        $this->logger->debug('checkAdults->click');
                        $checkAdults->click();
                        $this->logger->debug('checkAdults->clicked');
                        $this->driver->executeScript("document.querySelector('#ctl00_c_CtNoOfTr_numberAdults_chosen').querySelector('ul').scrollIntoView();");
                        $this->logger->debug('checkAdults->executeScript done');
                    }
                    $script = /** @lang JavaScript */
                        "
                var lis = document.querySelector('#ctl00_c_CtNoOfTr_numberAdults_chosen').querySelector('ul').querySelectorAll('li');
                var res = [...lis].filter(e => e.innerText == '{$fields['Adults']}')[0].scrollIntoView(); ";

                    $element = $this->waitForElement(\WebDriverBy::xpath("//div[@id='ctl00_c_CtNoOfTr_numberAdults_chosen']//li[./span[normalize-space()='{$fields['Adults']}']]"),
                        0);

                    if (!$element) {
                        $this->logger->debug('checkAdults->executeScript');
                        $this->driver->executeScript($script);
                    }
                }

                try {
                    $this->saveResponse();
                } catch (\UnexpectedJavascriptException $e) {
                    $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage());
                    sleep(5);
                    $this->saveResponse();
                }
                $check = $this->waitForElement(\WebDriverBy::xpath("//div[@id='ctl00_c_CtNoOfTr_numberAdults_chosen']//li[@text='{$fields['Adults']}']"),
                    0);

                if (!$check) {
                    throw new \CheckException("can't check Adults", ACCOUNT_ENGINE_ERROR);
                }
                $this->logger->debug('research checkAdults & click');
                $check = $this->waitForElement(\WebDriverBy::xpath("//div[@id='ctl00_c_CtNoOfTr_numberAdults_chosen']//li[@text='{$fields['Adults']}']"),
                    0);
                $check->click();
            }
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->saveResponse();
            $this->logger->debug('re-enter adults');
            $element = $this->waitForElement(\WebDriverBy::xpath("//div[@id='ctl00_c_CtNoOfTr_numberAdults_chosen']//li[./span[normalize-space()='{$fields['Adults']}']]"),
                1);

            if ($element) {
                $check = $this->waitForElement(\WebDriverBy::xpath("//div[@id='ctl00_c_CtNoOfTr_numberAdults_chosen']//li[@text='{$fields['Adults']}']"),
                    0);

                if (!$check) {
                    throw new \CheckException("can't check Adults", ACCOUNT_ENGINE_ERROR);
                }
                $this->logger->debug('research checkAdults & click');

                try {
                    $check = $this->waitForElement(\WebDriverBy::xpath("//div[@id='ctl00_c_CtNoOfTr_numberAdults_chosen']//li[@text='{$fields['Adults']}']"),
                        0);
                    $check->click();
                } catch (\StaleElementReferenceException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                    $this->saveResponse();

                    throw new \CheckRetryNeededException(5, 0);
                }
            }
        }

//        $d = $this->waitForElement(\WebDriverBy::id('chkFlexSearch'), 0);
        /*  $d = $this->waitForElement(\WebDriverBy::xpath("//div[@id='dvFrom']/img"), 0);
          if ($d) {
              $mover = new \MouseMover($this->driver);
              $mover->enableCursor();
              $mover->logger = $this->logger;
              //        $mover->duration = rand(100000, 120000);
              //        $mover->steps = rand(50, 70);

              $this->logger->debug('move to Flax field');
              $mover->moveToElement($d);
//            $mover->click();
//            $this->driver->executeScript("if (document.querySelector('#chkFlexSearch').checked) document.querySelector('#chkFlexSearch').click();");
          }*/
        $checkDep = $this->driver->executeScript("return document.querySelector('#ctl00_c_CtWNW_ddlFrom').value;");
        $checkArr = $this->driver->executeScript("return document.querySelector('#ctl00_c_CtWNW_ddlTo').value;");
        $this->logger->warning('dep: ' . $checkDep);
        $this->logger->warning('arr: ' . $checkArr);

        // local re-enter (not full enterRequest)
        if (!empty($checkDep) && empty($checkArr)) {
            $arrText = $this->fillAirport2($to, 'ctl00_c_CtWNW_ddlTo', $fields['ArrCode']);

            if (!$this->http->FindPreg("/\({$fields['ArrCode']}\)$/", false, $arrText)) {
                $this->saveResponse();
                $this->SetWarning("no {$fields['ArrCode']} airport");

                return [];
            }
        }

        $checkDep = $this->driver->executeScript("return document.querySelector('#ctl00_c_CtWNW_ddlFrom').value;");
        $checkArr = $this->driver->executeScript("return document.querySelector('#ctl00_c_CtWNW_ddlTo').value;");
        $this->logger->warning('dep: ' . $checkDep);
        $this->logger->warning('arr: ' . $checkArr);

        if (empty($checkDep) || empty($checkArr)) {
            $this->saveResponse();
            $this->sendNotification("check enter airports // ZM");

            if (!$isRetry) {
                $this->logger->debug('reload and enterRequest again');
                $this->driver->executeScript("window.location.reload();");
                sleep(1);
                $this->saveResponse();
                $this->enterRequest($fields, true);
            } else {
                throw new \CheckException('something wrong with input airports', ACCOUNT_ENGINE_ERROR);
            }
        }
        $search = $this->waitForElement(\WebDriverBy::id("ctl00_c_IBE_PB_FF"), 0);

        if (!$search) {
            throw new \CheckException('can\'t find button', ACCOUNT_ENGINE_ERROR);
        }

        return $this->clickSearch($search, $fields);
    }

    private function clickSearch($search, $fields)
    {
        $this->logger->notice(__METHOD__);

        try {
            $search->click();
        } catch (\ScriptTimeoutException | \TimeOutException | TOException $e) {
            $this->logger->error($e->getMessage());
            /*
                        $this->sendNotification('check click search // ZM');

                        if (!$isRetry) {
                            $this->logger->debug('reload and enterRequest again');
                            $this->driver->executeScript("window.location.reload();");
                            sleep(1);
                            $this->saveResponse();

                            return $this->enterRequest($fields, true);
                        }
            */
            // throw new \CheckRetryNeededException(5, 0);
            // повтор кнопки поиск и клик вешает, как и попытка ввода с нуля
        } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\UnknownErrorException $e) {
            throw new \CheckRetryNeededException(5, 0);
        }
        $this->saveResponse();

        if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached)/ims')) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->http->FindSingleNode("//h1[normalize-space()='502 Bad Gateway'] | //h1[normalize-space()='Server Error']")) {
            try {
                $this->sendNotification("check reload 502 // ZM");
                $this->driver->executeScript("window.location.reload();");
                $this->saveResponse();
            } catch (\UnexpectedJavascriptException $e) {
                $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());
            }
        }

        if ($this->http->FindSingleNode("//body[./text()[normalize-space()='The service is unavailable.']]")) {
            throw new \CheckException('The service is unavailable', ACCOUNT_PROVIDER_ERROR);
        }

        if ($frame = $this->waitForElement(\WebDriverBy::id('sec-text-if'), 2)) {
            // "Processing your request. If this page doesn't refresh automatically, resubmit your request."
            $this->waitFor(function () {
                return !$this->waitForElement(\WebDriverBy::id('sec-text-if'), 0);
            }, 100);
            $this->saveResponse();
        }

        $this->waitFor(
            function () {
                return
                    $this->waitForElement(\WebDriverBy::xpath("
                          //span[contains(.,'Book Classic Rewards Flight')] | //span[contains(.,'Redeem flights')]
                        | //h2[normalize-space()='Redeem Skywards Miles']
                        | //a[contains(.,'New search')]
                        | //div[contains(.,'Confirm your flights and continue')]/a[normalize-space()='Continue']
                    "), 0);
            },
            15
        );
        $this->saveResponse();
        $this->waitFor(function () {
            return !$this->waitForElement(\WebDriverBy::id('sec-text-if'), 0);
        }, 100);
        $this->saveResponse();

        if ($link = $this->http->FindSingleNode("//a[contains(.,'New search')]/@href")) {
            $depDate = date("d-M-y", $fields['DepDate']);
            $numCabin = $this->getCabin($fields['Cabin'], true, false, true);

            $continueLink = $this->http->FindSingleNode("//h1[contains(.,'Invalid process')]/following-sibling::div//div[contains(@class,'results-row') and contains(.,'({$fields['DepCode']})') and contains(.,'({$fields['ArrCode']})')]//a[contains(@href,'TID=OW&seldcity1={$fields['DepCode']}&selacity1={$fields['ArrCode']}&selddate1={$depDate}&seladate1=&seladults={$fields['Adults']}&selchildren=0&selinfants=0&selcabinclass={$numCabin}&selcabinclass1={$numCabin}')]");

            if ($continueLink) {
                $this->logger->error('New search: continueLink');
                $this->http->NormalizeURL($continueLink);
                $this->http->GetURL($continueLink);
                $this->sendNotification("check continue link - new search // ZM");
            }
        }

        if ($this->http->FindSingleNode("//div[@id='ctl00_c_errorPnl'][contains(.,'provided Business Class fares instead')]")) {
            $continue = $this->waitForElement(\WebDriverBy::xpath("//div[contains(.,'Confirm your flights and continue')]/a[normalize-space()='Continue']"),
                0);

            if ($continue) {
                $continue->click();
                $this->saveResponse();
            }
        }

        return null;
    }

    private function fillAirport($input, $ctl00_c_CtWNW_ddl, $iataCode)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice($iataCode);

        try {
            try {
                $input->click();
            } catch (ElementClickInterceptedException $e) {
                $this->logger->error('ElementClickInterceptedException: ' . $e->getMessage());
            }

            try {
                $input->clear();
            } catch (\InvalidElementStateException | InvalidElementStateException $e) {
                $this->logger->error('InvalidElementStateException: ' . $e->getMessage());
                $this->saveResponse();
            }
//            $selectAll = WebDriverKeys::CONTROL . "a" . WebDriverKeys::CONTROL;
//            $input->sendKeys($selectAll);
//            $input->sendKeys(WebDriverKeys::DELETE);
        } catch (\UnknownServerException $e) {
            $this->logger->error('UnknownServerException: ' . $e->getMessage());
        } catch (\UnrecognizedExceptionException $e) {
            $this->logger->error('UnrecognizedExceptionException: ' . $e->getMessage());
            $this->saveResponse();
        }

        $input->sendKeys($iataCode);
        sleep(1);
        $this->saveResponse();
        // site has bug with LGA
        try {
            $airportText = $this->driver->executeScript("
        sRes = []; 
        document.querySelectorAll('#suggestions{$ctl00_c_CtWNW_ddl} > div[text]').forEach(
            function(el){
                var s=el.getAttribute('text'); 
                res=/\({$iataCode}\)/.exec(s); 
                if(null!==res && res.length>0) sRes.push(s);
            }
        ); 
        if (sRes.length!==1) {
            if (sRes.length===3 && '{$iataCode}'==='LGA')
                return sRes[0];  
            if (sRes.length===2 && '{$iataCode}'==='UVF')
                return sRes[0];  
            return null;
        } 
        return sRes[0];
        ");
        } catch (\UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $this->saveResponse();
            $airportText = null;
        }

        return $airportText;
    }

    private function fillAirport2($input, $ctl00_c_CtWNW_ddl, $iataCode)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice($iataCode);

        try {
            $input->click();
            $input->clear();
//            $selectAll = WebDriverKeys::CONTROL . "a" . WebDriverKeys::CONTROL;
//            $input->sendKeys($selectAll);
//            $input->sendKeys(WebDriverKeys::DELETE);
        } catch (\UnknownServerException $e) {
            $this->logger->error('UnknownServerException: ' . $e->getMessage());
        }

        $input->sendKeys($iataCode);
        sleep(1);
        $input->sendKeys(WebDriverKeys::DOWN);
        $input->sendKeys(WebDriverKeys::ENTER);

        $airportText = $this->driver->executeScript("
        return document.querySelector('#{$ctl00_c_CtWNW_ddl}-suggest').value
        ");

        return $airportText;
    }

    private function fillAirportEmirates($type, $iataCode, $xpath, $isRetry = false)
    {
//        $type = d|a
        $this->logger->notice(__METHOD__);
        $this->logger->notice($iataCode);
        $this->http->saveScreenshots = true; // for debug

        switch ($type) {
            case 'd':
                $loc = 'origins';
                $class = 'js-origin-dropdown';

                break;

            case 'a':
                $loc = 'destinations';
                $class = 'destination-dropdown';

                break;

            default:
                throw new \CheckException('wrong type airport', ACCOUNT_ENGINE_ERROR);
        }

        $this->saveResponse();
        $input = $this->waitForElement(\WebDriverBy::xpath($xpath), 0);

        try {
            if (!$this->waitForElement(\WebDriverBy::xpath("//div[@id='panel0']//div[@class='{$class}']//h3[normalize-space()='All locations']"),
                    0) || ($input && !$input->isSelected())) {
                $input->click();
                sleep(1);
            }
            $input->clear();

            if ($isRetry) {
                $input->sendKeys(WebDriverKeys::ESCAPE); // почему-то открытый календарь иногда
                $input = $this->waitForElement(\WebDriverBy::xpath($xpath), 0);
                $input->click();
                $this->saveResponse();
            }

//            $selectAll = WebDriverKeys::CONTROL . "a" . WebDriverKeys::CONTROL;
//            $input->sendKeys($selectAll);
//            $input->sendKeys(WebDriverKeys::DELETE);
//            $input->sendKeys(WebDriverKeys::SPACE);
        } catch (\UnknownServerException $e) {
            $this->logger->error('UnknownServerException: ' . $e->getMessage());
            $this->saveResponse();

            try {
                $input = $this->waitForElement(\WebDriverBy::xpath($xpath), 0);
                $input->clear();
            } catch (\UnknownServerException $e) {
                $this->logger->error('UnknownServerException: ' . $e->getMessage());
            }
        }

        if ($isRetry) {
            usleep(500000);
            $input->clear();
            usleep(500000);
            $input->sendKeys(WebDriverKeys::SPACE);
            sleep(1);
            $this->saveResponse();
        }

        for ($i = 0; $i < 3; $i++) {
            usleep(random_int(300000, 700000));
            $this->logger->info("Sending '$iataCode[$i]' to input ...");
            $input->sendKeys($iataCode[$i]);
        }

//        if ($isRetry) {
        $this->saveResponse();
//        }

        try {
            $ch = $this->waitForElement(\WebDriverBy::xpath("//div[@data-data-type='{$loc}']//li[@data-dropdown-id='{$iataCode}']//p[normalize-space()='{$iataCode}']"),
                3);
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
            $this->sendNotification('check StaleElementReferenceException // ZM');
            $ch = $this->waitForElement(\WebDriverBy::xpath("//div[@data-data-type='{$loc}']//li[@data-dropdown-id='{$iataCode}']//p[normalize-space()='{$iataCode}']"),
                3);
        }

        if (!$ch) {
            $this->logger->debug('no airport');

            return 'no airport';
        }

        $this->driver->executeScript("
                if (document.querySelectorAll('div.{$class} li[data-dropdown-id=\"{$iataCode}\"]').length > 0
                && (document.querySelectorAll('div.{$class} li[data-dropdown-id=\"{$iataCode}\"]')[0].offsetWidth > 0
                    || document.querySelectorAll('div.{$class} li[data-dropdown-id=\"{$iataCode}\"]')[0].offsetHeight > 0
                    )
                )
                    document.querySelectorAll('div.{$class} li[data-dropdown-id=\"{$iataCode}\"]')[0].click();
            ");

        $airportText = $this->driver->executeScript("
        return document.querySelector('form>input[name=\"sel{$type}city1\"]').value
        ");

        return $airportText;
    }

    private function showAllOptions($rootPath)
    {
        while ($showAll = $this->waitForElement(\WebDriverBy::xpath("//a[contains(.,'more outbound option')]"), 0)) {
            $Roots = $this->http->XPath->query($rootPath);
            $i = $Roots->length + 1;
            $this->logger->debug("click show more outbounds");
            $showAll->click();
            $this->waitForElement(\WebDriverBy::xpath("({$rootPath})[{$i}]"), 10);
            $this->saveResponse(); //$('#ctl00_c_FlightResultOutBound_ancShowMore').click();
        }
    }

    private function isAllShows($rootPath, &$Roots)
    {
        $Roots = $this->http->XPath->query($rootPath);
        $cntRouts = (int) $this->http->FindSingleNode("//h2[./strong[contains(.,'Outbound')]]", null, false,
            "/\(\s*(\d+)\s+options?\s*\)/");
        $this->logger->debug($cntRouts . " option(s)");

        return empty($cntRouts) || $cntRouts === $Roots->length;
    }

    private function parseRewardFlights($fields): array
    {
        $this->logger->notice(__METHOD__);

        $rootPath = "//div[@class='ts-fbr-flight-list__body']/div";

        $this->showAllOptions($rootPath);

        $Roots = null;

        if (!$this->isAllShows($rootPath, $Roots)) {
            sleep(2);
            $this->showAllOptions($rootPath);
            $Roots = $this->http->XPath->query($rootPath);
            $this->sendNotification("check count parsed routes // ZM");
        }

        $routes = [];

        $this->logger->debug("Found {$Roots->length} routes");
        $this->logger->debug("path: " . $rootPath);

        if ($Roots->length === 0
            && (!$this->http->FindSingleNode("//h2[normalize-space()='Redeem Skywards Miles']")
                || !$this->http->FindSingleNode("//p[normalize-space()='Lowest price for all passengers']")
            )
        ) {
            throw new \CheckRetryNeededException(5, 0);
        }

        foreach ($Roots as $numRoot => $root) {
            $this->logger->debug("num route: " . $numRoot);

//            $totalDuration = $this->convertDuration($this->http->FindSingleNode(".//h3[contains(.,'Flight option')]/ancestor::div[1]/following-sibling::div[1]//time[starts-with(normalize-space(),'Duration')]",
//                $root, false, "/Duration\s*(.+)/"));

            $stop = $this->http->FindSingleNode(".//h3[contains(.,'Flight option') or contains(.,'Flight Option')]/ancestor::div[1]/following-sibling::div[1]/descendant::span[contains(.,'connection') or contains(.,'stop')][1]",
                $root);

            if (empty($stop)) {
                $nonStop = $this->http->FindSingleNode(".//h3[contains(.,'Flight option') or contains(.,'Flight Option')]/ancestor::div[1]/following-sibling::div[1]/descendant::span[contains(.,'Non-Stop')][1]",
                    $root);

                if (!$nonStop) {
                    $this->sendNotification("check stops // ZM");
                }
            }
            $numStops = $this->http->FindPreg("/\d+\s*stop/i", false, $stop);
            $numConnections = $this->http->FindPreg("/\d+\s*connection/i", false, $stop);

            if (null !== $numStops || null !== $numConnections) {
                $stop = (int) $numStops + (int) $numConnections;
            } else {
                $stop = 0;
            }
            $layovers = null;
            $duration = null;

            $segments = [];
            $rootSegments = $this->http->XPath->query(".//section", $root);
            $numSeg = 0;

            foreach ($rootSegments as $rootSegment) {
                $numSeg++;
                $dateDate = strtotime($this->http->FindSingleNode("./div/time", $rootSegment));
                $segStops = $this->http->FindSingleNode(".//time[starts-with(normalize-space(),'Duration')]/following-sibling::div[2]",
                    $rootSegment);

                if (null !== ($numStops = $this->http->FindPreg("/\d+\s*stop/i", false, $segStops))) {
                    $segStops = (int) $numStops;

                    if ($segStops > 1) {
                        $this->sendNotification("check route with {$segStops} stops // ZM");
                    }
                } else {
                    $segStops = 0;
                }
                $depDate = strtotime($this->http->FindSingleNode(".//span[starts-with(normalize-space(),'Departure') or starts-with(normalize-space(),'departure')]/ancestor::p/following-sibling::time",
                    $rootSegment), $dateDate);
                $arrDate = strtotime($this->http->FindSingleNode(".//span[starts-with(normalize-space(),'Arrival') or starts-with(normalize-space(),'arrival')]/ancestor::p/following-sibling::time",
                    $rootSegment), $dateDate); // 15:00 +1 day
                $segment = [
                    'num_stops' => $segStops,
                    'aircraft'  => $this->http->FindSingleNode("(.//h3[contains(.,'Flight option') or contains(.,'Flight Option')]/following-sibling::ul//li)[{$numSeg}]//p[@class='ts-fip__type']",
                        $root),
                    'flight' => [
                        $this->http->FindSingleNode("(.//h3[contains(.,'Flight option') or contains(.,'Flight Option')]/following-sibling::ul//li)[{$numSeg}]//p[@class='ts-fip__aircraft']",
                            $root),
                    ],
                    'airline' => $this->http->FindSingleNode("(.//h3[contains(.,'Flight option') or contains(.,'Flight Option')]/following-sibling::ul//li)[{$numSeg}]//p[@class='ts-fip__aircraft']",
                        $root, false, "/^(\w{2})\d+$/"),
                    'departure' => [
                        'date'     => date('Y-m-d H:i', $depDate),
                        'dateTime' => $depDate,
                        'airport'  => $this->http->FindSingleNode(".//span[starts-with(normalize-space(),'Departure') or starts-with(normalize-space(),'departure')]/ancestor::p//text()[string-length(normalize-space())=3]",
                            $rootSegment),
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', $arrDate),
                        'dateTime' => $arrDate,
                        'airport'  => $this->http->FindSingleNode(".//span[starts-with(normalize-space(),'Arrival') or starts-with(normalize-space(),'arrival')]/ancestor::p//text()[string-length(normalize-space())=3]",
                            $rootSegment),
                    ],
                    'time' => [
                        'flight'  => $duration,
                        'layover' => $layovers,
                    ],
                ];
                $segments[] = $segment;
            }

            $offers = $this->http->XPath->query($offerPath = ".//div[starts-with(@data-target,'options-target')]/div//div[@class='ts-fbr-option__price-detail'][not(.//strong[normalize-space()='Sold out']) and .//strong[contains(normalize-space(),'Sold out')]/following-sibling::p[1][normalize-space()]]",
                $root);
            $cabinXpath = "./preceding-sibling::strong[contains(@class,'option__class')][1]";
            $milesXpath = ".//p[@data-miles]/@data-miles";
            $taxesXpath = ".//p[@data-miles]/@data-price";
            $currencyXpath = ".//p[@data-miles]/@data-currency";

            if ($offers->length === 0) {
                $offers = $this->http->XPath->query(".//div[starts-with(@data-target,'options-target')]/div//div[@class='ts-fbr-option__price-detail']/div/p[starts-with(normalize-space(),'From')][normalize-space()]",
                    $root);
                $cabinXpath = "./ancestor::div[2]/preceding-sibling::strong[contains(@class,'option__class')][1]";
                $milesXpath = "./@data-miles";
                $taxesXpath = "./@data-price";
                $currencyXpath = "./@data-currency";
            }

            $this->logger->debug("Found {$offers->length} offers");
            $this->logger->debug("path: " . $offerPath);

            foreach ($offers as $offer) {
                $cabinText = $this->http->FindSingleNode($cabinXpath, $offer);
                $segments_ = $segments;

                foreach ($segments as $num => $segment) {
                    $segments_[$num]['cabin'] = $this->getCabin($cabinText, false, true);
                    $segments_[$num]['classOfService'] = $cabinText;
                }
                $miles = (int) str_replace(',', '',
                    $this->http->FindSingleNode($milesXpath, $offer));

                if ($miles < 3000) {
                    $this->SetWarning("Some routes are <3000 miles");

                    if ($miles < 1000) {
                        $this->logger->error('miles < 1000 (seems bug) - ' . $miles . '. skip offer');
                        $this->sendNotification("miles < 1000 // ZM");
                    }
                }
                $taxes = PriceHelper::cost($this->http->FindSingleNode($taxesXpath, $offer));
                $route = [
                    'num_stops' => $stop,
                    'times'     => [
                        //                        'flight'  => $this->subLayovers($totalDuration, $layovers),
                        //                        'layover' => $layovers,
                        'flight'  => null,
                        'layover' => null,
                    ],
                    'redemptions' => [
                        'miles'   => intdiv($miles, $fields['Adults']),
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $this->http->FindSingleNode($currencyXpath, $offer),
                        'taxes'    => round($taxes / $fields['Adults'], 2),
                        'fees'     => null,
                    ],
                    'connections' => $segments_,
                ];
                $routes[] = $route;
            }
        }
        $this->logger->debug('Parsed data:');
        $this->logger->debug(var_export($routes, true), ['pre' => true]);

//        $this->http->GetURL("https://fly2.emirates.com/CAB/System/aspx/logout.aspx");
//        $this->saveResponse();
        return $routes;
    }

    private function validRoute($fields)
    {
        // если не 100% невалидный, то идем работать на странице "ручками"
        // https://fly2.ekstatic.net/js/IBEAutoSuggest.min.js
//        var u = $("#DEXEnabled").val() == "true", f = $("#POORestrictedCountries").val() == "true", i, r;
//        isRedeem && isPartner ?
//            (i = sQueryDepPartner, r = sQueryArrPartner) :
//            u ? (i = sQueryDepNonInterline, r = sQueryArrNonInterline) :
//                f ? (i = sQueryDepNonInterline, r = sQueryArrNonInterline) :
//                    (i = sQueryDepRed, r = sQueryArrRed);

        $this->logger->debug($this->http->currentUrl());
        $this->http->SaveResponse();

        if ($this->partners) {
            $path = $this->driver->executeScript(
                'return sQueryDepPartner;
                '
            );
        } else {
            try {
                $path = $this->driver->executeScript(
                    '
                    var u = $("#DEXEnabled").val() == "true", f = $("#POORestrictedCountries").val() == "true", i;
                    u ? i = sQueryDepNonInterline :
                        f ? i = sQueryDepNonInterline :
                            i = sQueryDepRed;
                    return i;                
                '
                );
            } catch (JavascriptErrorException $e) {
                $this->logger->error($e->getMessage());
                $this->waitForElement(\WebDriverBy::xpath("//input[@id='DEXEnabled']"), 10);
                $this->waitForElement(\WebDriverBy::xpath("//input[@id='//input[@id='POORestrictedCountries']']"), 10);

                $path = $this->driver->executeScript(
                    '
                var u = document.querySelector("#DEXEnabled").value == "true", f = document.querySelector("#POORestrictedCountries").value == "true", i;
                u ? i = sQueryDepNonInterline :
                    f ? i = sQueryDepNonInterline :
                        i = sQueryDepRed;
                return i;                
                '
                );
            }
        }

        if (empty($path)) {
            return true;
        }
        $this->http->NormalizeURL($path);
        $this->logger->debug($path);
        $tt =
            '
            var xhttp = new XMLHttpRequest();
            xhttp.open("GET", "' . $path . '", false);
            xhttp.setRequestHeader("Accept", "*/*");
            xhttp.setRequestHeader("Accept-Encoding", "gzip, deflate, br");
            xhttp.setRequestHeader("Referer", "https://fly2.emirates.com/CAB/IBE/SearchAvailability.aspx");
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {

                    localStorage.setItem("retData",this.responseText);

                }
            };
            xhttp.send();
            v = localStorage.getItem("retData");
            return v;
';
        $this->logger->debug($tt);

        try {
            $returnData = $this->driver->executeScript($tt);

            if (strpos($returnData, "|") !== false) {
                $origins = array_filter(explode("|", $returnData));
            } else {
                $origins = null;
            }
        } catch (\WebDriverException $e) {
            $this->logger->error($e->getMessage());
            $origins = null;
        }

//        $this->logger->debug(var_export($origins, true));
        if (!isset($origins)) {
            return true;
        }

        if (!empty($origins) && is_array($origins) && !in_array($fields['DepCode'], $origins)) {
//            $this->SetWarning('no flights from ' . $fields['DepCode']);

            return false;
        }

        if ($this->partners) {
            $path = $this->driver->executeScript('return partnerAirlineQueryRed;');
        } else {
            $path = $this->driver->executeScript('return sFilterQueryRed;');
        }

        if (empty($path)) {
            return true;
        }
        $this->http->NormalizeURL($path);
        $this->logger->debug($path);

        $tt =
            '
            var xhttp = new XMLHttpRequest();
            xhttp.open("GET", "' . $path . $fields['DepCode'] . '", false);
            xhttp.setRequestHeader("Accept", "*/*");
            xhttp.setRequestHeader("Accept-Encoding", "gzip, deflate, br");
            xhttp.setRequestHeader("Referer", "https://fly2.emirates.com/CAB/IBE/SearchAvailability.aspx");
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {

                    localStorage.setItem("retData",this.responseText);

                }
            };
            xhttp.send();
            v = localStorage.getItem("retData");
            return v;
';
        $this->logger->debug($tt);

        try {
            $returnData = $this->driver->executeScript($tt);

            if (strpos($returnData, "|") !== false) {
                $destinations = array_filter(explode("|", $returnData));
            } else {
                $destinations = null;
            }
        } catch (\WebDriverException $e) {
            $this->logger->error($e->getMessage());
            $destinations = null;
        }
//        $this->logger->debug(var_export($destinations, true));
        if (!isset($destinations)) {
            return true;
        }

        if (!empty($destinations) && is_array($destinations) && !in_array($fields['ArrCode'], $destinations)) {
//            $this->SetWarning('no flights from ' . $fields['DepCode'] . ' to ' . $fields['ArrCode']);

            return false;
        }

        if (is_array($origins) && is_array($destinations)
            && in_array($fields['DepCode'], $origins) && in_array($fields['ArrCode'], $destinations)) {
            $this->routeValid = true;
        }

        return true;
    }

    private function validRouteEmiratesCom($fields)
    {
        $this->logger->notice(__METHOD__);
        $browser = new \HttpBrowser("none", new \CurlDriver());
        $this->http->brotherBrowser($browser);

        $validDep = \Cache::getInstance()->get('ra_emirates_locations');

        if (!$validDep || !is_array($validDep)) {
            $browser->GetURL('https://www.emirates.com/service/stations?module=ONLINE_BOOKING&requestedPoint=DESTINATION&emiratesRewards=true');
            $dataOrigin = $browser->JsonLog(null, 0, true);

            if (!is_array($dataOrigin) || !isset($dataOrigin['stationCodes']) || !is_array($dataOrigin['stationCodes'])) {
                // try to check in browser
                return true;
            }
            $validDep = $dataOrigin['stationCodes'];
            \Cache::getInstance()->set('ra_emirates_locations', $validDep, 60 * 60 * 24);
        }

        if (!in_array($fields['DepCode'], $validDep)) {
            $this->SetWarning("No flights from " . $fields['DepCode']);

            return false;
        }

        $validArr = \Cache::getInstance()->get('ra_emirates_locations_' . $fields['DepCode']);

        if (!$validArr || !is_array($validArr)) {
            $browser->GetURL('https://www.emirates.com/service/stations/' . $fields['DepCode'] . '?module=ONLINE_BOOKING&requestedPoint=DESTINATION&emiratesRewards=true');
            $dataDestination = $browser->JsonLog(null, 0, true);

            if (!is_array($dataDestination) || !isset($dataDestination['stationCodes']) || !is_array($dataDestination['stationCodes'])) {
                // try to check in browser
                return true;
            }
            $validArr = $dataDestination['stationCodes'];
            \Cache::getInstance()->set('ra_emirates_locations_' . $fields['DepCode'], $validArr, 60 * 60 * 24);
        }

        if (!in_array($fields['ArrCode'], $validArr)) {
            $this->SetWarning("No flights to " . $fields['ArrCode']);

            return false;
        }

        return true;
    }

    private function checkBadProxy()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("
                    //h1[contains(text(), 'This site can’t be reached')]
                    | //span[contains(text(), 'This site can’t be reached')]
                    | //h1[contains(text(), 'Access Denied')]
                ")
            || $this->http->FindSingleNode("
                    //p[contains(text(), 'Health check')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                    | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                ")
            || $this->http->FindPreg('/page isn’t working/ims')
            || $this->waitForElement(\WebDriverBy::xpath("
                | //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                | //p[contains(text(), 'Health check')]
            "), 0)
        ) {
            $this->markProxyAsInvalid();

            throw new \CheckRetryNeededException(3, 0);
        }
    }

    private function hideOverlay()
    {
        $this->logger->notice(__METHOD__);
        $this->driver->executeScript('var overlay = document.getElementById(\'onetrust-consent-sdk\'); if (overlay) overlay.style.display = "none";');
    }

    private function detectingLogoutLink($sleep = 10, $selenium = false)
    {
        $this->logger->notice(__METHOD__);
        $doNotSavePage = false;

        if ($this->selenium || $selenium) {
            $startTime = time();

            while ((time() - $startTime) < $sleep) {
                $currentTime = time() - $startTime;
                $this->logger->debug("(time() - \$startTime) = {$currentTime} < {$sleep}");
                $logout = $this->waitForElement(\WebDriverBy::xpath("
                        //div[contains(@class, 'membershipName')]
                        | //div[@class = 'membershipSkywardsMiles']/div[@class= 'milesCount']
                        | //span[contains(@class, \"icon-profile-account\")]/following-sibling::span[not(normalize-space(text()) = 'Log in')]
                        | //div[@class = 'welcome-message']/span
                        | //div[@class = 'form-container']//input[@value = 'Log in']
                        | //h1[contains(text(), 'Business Rewards Dashboard')]
                "), 0);

                if (!$logout) {
                    $logout = $this->waitForElement(\WebDriverBy::xpath("//span[@id = 'tlmembershipnumber']"), 0,
                        false);
                }
                $error = $this->waitForElement(\WebDriverBy::xpath('
                    //div[@id = "validationSummary"]
                    | //div[@id = "MainContent_SSLogin_pnlErrorMessage"]
                    | //div[contains(@class, "login-error")]
                    | //div[contains(@class, "focus-alert alert alert-danger")][count(./ancestor::div[contains(@class,"noshow")])=0]
                '), 0)
                    ?? $this->http->FindSingleNode('//div[@id="validationSummary" and @class="errorPanel"]/ul/li');

                if ($error) {
                    $this->logger->debug("error found");
                    $doNotSavePage = true;
                    $this->saveResponse();
                }

                if ($this->waitForElement(\WebDriverBy::xpath('//p[
                        contains(text(), "An email with a 6-digit passcode has been sent to")
                        or contains(text(), "Please choose how you want to receive your passcode.")
                    ]'), 0)
                ) {
                    $this->saveResponse();

                    return false;
                }

                try {
                    if ($logout || $doNotSavePage == false) {
                        $this->saveResponse();
                    }
                } catch (\WebDriverCurlException | \NoSuchDriverException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }

                if ($logout && !$error) {
                    try {
                        $this->logger->debug("[Current URl]: {$this->http->currentUrl()}");
                    } catch (\NoSuchDriverException $e) {
                        $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
                    }
                    $this->browser = $this->http;

                    return true;
                }

                if ($error && $currentTime > 10) {
                    return false;
                }

                try {
                    $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
                } catch (\NoSuchDriverException $e) {
                    $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());

                    throw new \CheckRetryNeededException(3, 0);
                }

                if ($msg = $this->http->FindPreg('/accessrestricted/', false, $this->http->currentUrl())) {
                    $this->DebugInfo = $msg;
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                    $this->markProxyAsInvalid();

                    throw new \CheckRetryNeededException(3, 0);
                }

                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    $this->DebugInfo = $msg;
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;

                    $this->checkBadProxy();
                } else {
                    $this->DebugInfo = null;
                }

                if (!$error && !$this->http->FindNodes('//div[@id="validationSummary" and @class="errorPanel"]/ul/li')) {
                    $this->checkBadProxy();
                }

                if ($this->http->currentUrl() === 'https://skywards.flydubai.com/en/login') {
                    throw new \CheckRetryNeededException(3, 0);
                }
            }// while ((time() - $startTime) < $sleep)
        } elseif ($this->browser->FindNodes("//a[contains(@href, 'logout')]")) {
            return true;
        }

        return false;
    }
}
