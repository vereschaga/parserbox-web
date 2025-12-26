<?php

use AwardWallet\Common\Parsing\LuminatiProxyManager\Port;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerIpirangaSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const WAIT_TIMEOUT = 10;
    private $ipiranga;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        if ($this->attempt == 0) {
            $this->setProxyNetNut();
        } else {
            $this->setProxyGoProxies(null, 'br');
        }

        if ($this->AccountFields['UserID'] == 2110) {
            $this->logger->debug("testing lpm");
            $this->setLpmProxy((new Port)
                ->setExternalProxy([$this->http->getProxyUrl()])
            );
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
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);

        if ($this->attempt == 0) {
            $this->useFirefoxPlaywright();
        } else {
            $this->useChromePuppeteer();
        }
        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        //$this->seleniumOptions->addHideSeleniumExtension = false;
        //$this->seleniumOptions->userAgent = null;

        $this->usePacFile(false);
    }

    public function LoadLoginForm()
    {
        // stupid users gap
        if (!is_numeric($this->AccountFields['Login'])) {
            throw new CheckException("CPF e/ou Senha Inválidos", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL('https://www.kmdevantagens.com.br/');
        $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Entre")]/../.. | //h2[contains(text(), "The request could not be satisfied.")]'), self::WAIT_TIMEOUT);
        $loginButton = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Entre")]/../..'), 0);
        $this->saveResponse();

        if (!$loginButton) {
            if (
                $this->http->FindSingleNode('//h2[contains(text(), "The request could not be satisfied.")]')
                || empty($this->http->Response['body'])
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        $loginButton->click();
        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="cpf"]'), self::WAIT_TIMEOUT);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id="password"]'), 0);
        $this->saveResponse();

        if (!$login || !$password) {
            if (empty($this->http->Response['body'])) {
                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);

        $this->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/api\/auth\/callback\/credentials/g.exec(url)) {
                        localStorage.setItem("responseData", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
        ');

        $this->driver->executeScript('
            const constantMock = window.fetch;
            window.fetch = function() {
                console.log(arguments);
                return new Promise((resolve, reject) => {
                    constantMock.apply(this, arguments)
                        .then((response) => {
                            if(response.url.indexOf("/api/auth/callback/credentials") > -1) {
                                response
                                    .clone()
                                    .json()
                                    .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                            }
                            resolve(response);
                        })
                        .catch((error) => {
                            reject(error);
                        })
                });
            }
        ');

        $submit = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "ENTRAR")]'), self::WAIT_TIMEOUT / 2);
        $this->saveResponse();

        if (!$submit) {
            if (empty($this->http->Response['body'])) {
                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        $submit->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(webdriverBy::xpath('
            //div[contains(@class, "MuiAlert-message")]/div
            | //p[@id="cpf-helper-text"]
            | //p[contains(@class, "Mui-error") and not(@id="cpf-helper-text")]
            | //p[contains(text(), "Olá,")]
        '), self::WAIT_TIMEOUT * 4);
        $this->saveResponse();

        try {
            $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
        } catch (UnexpectedJavascriptException $e) {
            throw new CheckRetryNeededException(3, 0);
        }

        $this->logger->info("[Form responseData]: " . $responseData);

        if (!empty($responseData)) {
            $this->http->SetBody($responseData);
        }

        $response = $this->http->JsonLog();
        $url = $response->url ?? null;

        if (in_array($url, ['https://www.kmdevantagens.com.br/', 'https://kmdevantagens.com.br'])) {
            return $this->getIpiranga()->loginSuccessful();
        }

        // CPF ou Senha incorreto(s)
        parse_str(parse_url($url, PHP_URL_QUERY), $output);
        $message =
            $output['error']
            ?? $this->http->FindSingleNode('//div[contains(@class, "MuiAlert-message")]/div')
            ?? $this->http->FindSingleNode('//p[contains(@class, "Mui-error") and not(@id="cpf-helper-text")]')
            ?? $this->http->FindSingleNode('//p[@id="cpf-helper-text"]')
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Ocorreu um erro, tente novamente em alguns instantes.')
                || strstr($message, 'Recaptcha não está pronto')
            ) {
                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($message, 'Login ou senha incorreto')
                || $message == 'A senha deve ter exatamente 6 dígitos'
                || $message == 'CPF inválido'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Não foi possível realizar o login. Tente novamente em alguns instantes.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $ipiranga = $this->getIpiranga();
        $ipiranga->Parse();
        $this->SetBalance($ipiranga->Balance ?? $this->Balance);
        $this->Properties = $ipiranga->Properties;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorCode = $ipiranga->ErrorCode;
            $this->ErrorMessage = $ipiranga->ErrorMessage;
            $this->DebugInfo = $ipiranga->DebugInfo;
        }
    }

    protected function getIpiranga()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->ipiranga)) {
            $this->ipiranga = new TAccountCheckerIpiranga();
            $this->ipiranga->http = new HttpBrowser("none", new CurlDriver());
            $this->ipiranga->http->setProxyParams($this->http->getProxyParams());
            $this->http->brotherBrowser($this->ipiranga->http);
            $this->ipiranga->State = $this->State;
            $this->ipiranga->AccountFields = $this->AccountFields;
            $this->ipiranga->itinerariesMaster = $this->itinerariesMaster;
            $this->ipiranga->HistoryStartDate = $this->HistoryStartDate;
            $this->ipiranga->historyStartDates = $this->historyStartDates;
            $this->ipiranga->http->LogHeaders = $this->http->LogHeaders;
            $this->ipiranga->ParseIts = $this->ParseIts;
            $this->ipiranga->ParsePastIts = $this->ParsePastIts;
            $this->ipiranga->WantHistory = $this->WantHistory;
            $this->ipiranga->WantFiles = $this->WantFiles;
            $this->ipiranga->strictHistoryStartDate = $this->strictHistoryStartDate;
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->ipiranga->http->setDefaultHeader($header, $value);
            }
            $this->ipiranga->globalLogger = $this->globalLogger;
            $this->ipiranga->logger = $this->logger;
            $this->ipiranga->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $this->ipiranga->http->removeCookies();
        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->ipiranga->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->ipiranga;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
