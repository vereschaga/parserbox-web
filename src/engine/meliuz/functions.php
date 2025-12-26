<?php

use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerMeliuz extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://customer.meliuz.com.br/v2/me?include=indication_count,has_online_transaction,has_retail_transaction,has_online_transaction_only_purchase';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $responseProfileData = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->setProxyGoProxies(null, 'br');
        $this->setProxyNetNut(null, 'br');
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['headers'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, $this->State['headers'], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        /*$this->http->GetURL('https://www.meliuz.com.br/entrar');
        // it's Login page? verify...
        if (strpos($this->http->Response['body'], '<title>Méliuz - Entrar</title>') !== false) {
            return false;
        }*/

        $this->selenium();

        return true;

        $captcha = $this->parseReCaptcha();

        if ($captcha == false) {
            return false;
        }

        $data = [
            'client_id'          => 'meliuz-client-site-production',
            'client_secret'      => 'cWk5Z4GyAK1OgX1NDaBarLGFDmL9wk',
            'grant_data'         => $this->AccountFields['Pass'],
            'grant_type'         => 'password',
            'identifier_type'    => 'email',
            'identifier_value'   => $this->AccountFields['Login'],
            'recaptcha_response' => $captcha,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://customer.meliuz.com.br/v2/oauth/token', $data);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->data->accessToken)) {
            $this->captchaReporting($this->recognizer);
            $this->State['headers'] = [
                'authorization' => 'Bearer ' . $response->data->accessToken,
                'refreshToken'  => $response->data->refreshToken,
            ];

            return $this->loginSuccessful();
        }

        $message = $response->error->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message === "oauth.grant.recaptcha_response.invalid") {
                throw new CheckRetryNeededException(3, 3, self::CAPTCHA_ERROR_MSG);
            }

            if ($message === "oauth.grant.customer.not_found") {
                throw new CheckException("Usuário ou senha incorretos. Por favor, tente novamente.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message === "customer.password.expired") {
                throw new CheckException("Não foi possível fazer o login. Por favor, tente novamente.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message === "customer.password.blocked") {
                throw new CheckException("Você atingiu o limite de tentativas. Para sua segurança, redefina sua senha.", ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $solvingStatus =
            $this->http->FindSingleNode('//a[@title="AntiCaptcha: Captcha solving status"]')
            ?? $this->http->FindSingleNode('//a[@class = "status"]')
        ;

        if ($solvingStatus) {
            $this->logger->error("[AntiCaptcha]: {$solvingStatus}");

            if (
                strstr($solvingStatus, 'Proxy response is too slow,')
                || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection refused')
                || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection timeout')
                || strstr($solvingStatus, 'Captcha could not be solved by 5 different workers')
                || strstr($solvingStatus, 'Solving is in process...')
                || strstr($solvingStatus, 'Proxy IP is banned by target service')
                || strstr($solvingStatus, 'Recaptcha server reported that site key is invalid')
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 3, self::CAPTCHA_ERROR_MSG);
            }

            $this->DebugInfo = $solvingStatus;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $json = $this->http->JsonLog($this->responseProfileData, 0);
        // Balance - R$...
        $this->SetBalance($json->data->confirmed_balance ?? null);
        // Name
        $this->SetProperty("Name", beautifulName($json->data->name ?? null));
        // Pending
        $this->AddSubAccount([
            'Code'           => 'Pending',
            'DisplayName'    => "Pending",
            'Balance'        => $json->data->pending_balance,
        ]);
    }

    public function parseReCaptcha()
    {
        $this->http->RetryCount = 0;
        /*
         * secret_key  - it's "global secret key" for this site
         * Get from java-script on https://staticz.com.br/.next/production/67e451c702d2f6aa4a7c6b549707176052aa8975-42154/_next/static/chunks/3ad6cca2ee21cee566a33a852cf14bef8e30cd53.90302b6cf8b618710744.js
         * static,= cWk5Z4GyAK1OgX1NDaBarLGFDmL9wk
         */
        $key = '6Lfh9JgUAAAAAFKjdZEc33SmBBfqqc8hwfFC0X-y';

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            'invisible' => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;

        if (empty($this->responseProfileData)) {
            $this->http->GetURL(self::REWARDS_PAGE_URL, $this->State['headers'], 20);
        }

        $this->http->RetryCount = 2;

        $json = $this->http->JsonLog($this->responseProfileData, 3, true);
        $email = $json['data']['email'] ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (
            strtolower($email) == strtolower($this->AccountFields['Login'])
            || (
                in_array(strtolower($this->AccountFields['Login']), [
                    'tarcisiobezerra@hotmail.com',
                    'f.tadashi.meliuz@gmail.com',
                    'elheinzen@gmail.com',
                    'joao1298@hotmail.com', // joaop1298@gmail.com
                    'marina.mendes@outlook.com', // marina_mendes2@hotmail.com
                    'jonathanluis164@gmail.com', // jonathanluis387@gmail.com
                ])
                && !isset($json['errors'])
                && !empty($email)
            )
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Alguns serviços estão passando por instabilidades, mas você ainda pode comprar com cashback.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);

            $wrappedProxy = $this->services->get(WrappedProxyClient::class);
            $proxy = $wrappedProxy->createPort($this->http->getProxyParams());
            $selenium->seleniumOptions->antiCaptchaProxyParams = $proxy;

            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
//            $selenium->seleniumOptions->antiCaptchaProxyParams = $this->http->getProxyParams();

            $selenium->seleniumOptions->recordRequests = true;

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
            }

//            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
//            $selenium->setKeepProfile(true);
//            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                //$selenium->http->GetURL('https://www.meliuz.com.br');
                $selenium->http->GetURL('https://www.meliuz.com.br/entrar');
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            } catch (UnknownServerException $e) {
                $this->logger->error("UnknownServerException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'identifier']"), 10);
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$pass) {
                if ($this->http->FindPreg("/<iframe id=\"main-iframe\" src=\"\/_Incapsula_Resource\?CWUDNSAI/")) {
                    $retry = true;
                    $this->markProxyAsInvalid();
                }

                return $this->checkErrors();
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);

            if ($rememberMeCheckboxSignIn = $selenium->waitForElement(WebDriverBy::xpath("//label[input[@id = 'remember-device']]"), 0)) {
                $rememberMeCheckboxSignIn->click();
            }

            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "button-login-page-submit"]'), 0);

            if (!$button) {
                return $this->checkErrors();
            }

            $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/\/oauth\/token/g.exec( url )) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                        
                        if (/customer.meliuz.com.br\/v2\/me\?include=indication_count,has_online_transaction,/g.exec( url )) {
                            localStorage.setItem("responseProfileData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');

            $this->logger->debug("click by login btn");
            $this->savePageToLogs($selenium);

            if ($error = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Por favor, digite um e-mail ou número de telefone válido')]"), 0)) {
                throw new CheckException($error->getText(), ACCOUNT_INVALID_PASSWORD);
            }

//            $button->click();

            $selenium->waitFor(function () use ($selenium) {
                return is_null($selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
//                    || is_null($selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0, false));
            }, 180);

            $this->logger->debug("waiting results...");
            $selenium->waitForElement(WebDriverBy::xpath("
                //a[contains(text(), 'Indique e ganhe')]
                | //a[contains(text(), 'Quero ganhar')]
                | //img[@alt='Meu avatar']
                | //div[@id = 'error-login']
                | //button[contains(text(), 'Li e concordo com os termos')]
            "), 10);

            /** @var SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;

            try {
                $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
            } catch (AwardWallet\Common\Selenium\BrowserCommunicatorException $e) {
                $this->logger->error("BrowserCommunicatorException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $requests = [];
            }

            foreach ($requests as $n => $xhr) {
//                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if (strpos($xhr->request->getUri(), 'ne_transaction_only_purchase') !== false) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $this->responseProfileData = json_encode($xhr->response->getBody());
                }
            }
            // save page to logs
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: '" . $responseData . "'");

//            $this->logger->info("[Form responseProfileData]: '" . $responseProfileData . "'");

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);
            }
        } catch (Facebook\WebDriver\Exception\UnknownErrorException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $currentUrl;
    }
}
