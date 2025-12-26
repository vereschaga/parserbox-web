<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerLanpassOld extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    use DateTimeTools;

    private const CONFIGURATION_URL = 'https://bff.latam.com/ws/application/common/configuration/1.1/rest/search_configuration';

    /** @var HttpBrowser */
    public $browser;
    private $auth_token;
    private $configurationHeaders = [
        'Content-Type'     => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
        'Origin'           => 'https://www.latam.com',
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyDOP());
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.latam.com/en_us/';

        return $arg;
    }

    public function IsLoggedIn()
    {
        /*$this->http->GetURL('https://www.latam.com/en_us/');
        $latam_user_data = $this->http->getCookieByName('latam_user_data', '.latam.com');
        if ($latam_user_data)
            return true;*/

        //$this->logger->debug("latam_user_data: <pre>".var_export($latam_user_data, true)."</pre>");
        $data = '{"applicationName":"customerportal","language":"EN","country":"US","portal":"personas","step":"1"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL(self::CONFIGURATION_URL, $data, $this->configurationHeaders, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        $lan_session = $this->http->getCookieByName("lan_session");
        $this->logger->debug("lan_session: <pre>" . var_export($lan_session, true) . "</pre>");
        // for tam users
        $this->auth_token = urldecode($this->http->getCookieByName("auth_token", ".lan.com"));
        $this->logger->debug("auth_token: <pre>" . var_export($this->auth_token, true) . "</pre>");

        if (!empty($response->data->flowId) && (!empty($this->auth_token) || !empty($lan_session))) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        /*
        $this->http->GetURL("https://www.latam.com/en_us/");

        /*
        $authorizeUrl = $this->http->FindSingleNode('(//a[contains(@href, "login.latam.com/authorize")])[1]/@href');
        if (!$authorizeUrl) {
            return $this->checkErrors();
        }
        $this->http->GetURL($authorizeUrl);
        */
        $this->http->GetURL("https://www.latam.com/cgi-bin/site_login.cgi?page=https://www.latam.com/en_us/");

        /*
        if (!$this->http->ParseForm("box-white")) {
            if ($this->http->currentUrl() == 'https://www.latam.com/cgi-bin/site_login.cgi' && $this->http->Response['code'] == 500)
                throw new CheckException("We are working to improve your experience. This service won't be available while we make a few adjustments. Thank you for your understanding.", ACCOUNT_PROVIDER_ERROR);
            return $this->checkErrors();
        }
        */

        $currentUrl = $this->http->currentUrl();
        $client_id = $this->http->FindPreg("/client=([^&]+)/", false, $currentUrl);
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $currentUrl);
        $scope = $this->http->FindPreg("/scope=([^&]+)/", false, $currentUrl);
        /*
        $csrf = $this->http->getCookieByName("_csrf", null, "/usernamepassword/login", true);
        */
        $auth0Config = $this->http->JsonLog(base64_decode($this->http->FindPreg("/escape\(window\.atob\('([^\']+)/")));
        $csrf = $auth0Config->extraParams->_csrf ?? null;

        if (!$client_id || !$state || !$scope || !$csrf) {
            if ($this->AccountFields['Login'] == '65852222100') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
//        $captcha = $this->parseCaptcha(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2, '6LdkGssZAAAAABcqg7ForMsyhT6v5BUXVk8hcK7X', $currentUrl, true, true);
        $captcha = $this->parseCaptcha(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2, '6LeMru0aAAAAAEy8qRwQ6SEwex-fwoTqmSwrxkdD', $currentUrl, true, true, "LOGIN");
        /*

         grecaptcha.enterprise.ready((function() {
                                rn(!0),
                                grecaptcha.enterprise.execute(M, {
                                    action: "LOGIN"
                                }).then((function(n) {
                                    if (null == n || "" === n.trim())
                                        return rn(!1),
                                        void wn({
                                            code: "GOOGLE_CAPTCHA_IS_NULL"
                                        });
                                    xn(n)
        https://bff.latam.com/ws/api/auth0-login/v1/index.js

        */
//        $captcha = $this->parseCaptcha(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2, '6LeMru0aAAAAAEy8qRwQ6SEwex-fwoTqmSwrxkdD', $currentUrl, true, true, "LOGIN");
        $data = [
            "client_id"     => $auth0Config->clientID ?? $client_id,
            "redirect_uri"  => $auth0Config->callbackURL ?? "https://bff.latam.com/ws/api/auth0-legacy-cookies/v1/continue?callback=https://www.latam.com/en_us/",
            "tenant"        => "latam-cim-prod",
            "response_type" => "code",
            "scope"         => $auth0Config->extraParams->scope ?? $scope,
            "_csrf"         => $csrf,
            "state"         => $auth0Config->extraParams->state ?? $state,
            "_intstate"     => "deprecated",
            "username"      => json_encode([
                "user"    => $this->AccountFields['Login'],
                "home"    => "us",
                "captcha" => $captcha,
            ]),
            "password"      => $this->AccountFields['Pass'],
            "connection"    => "latam-customer-database",
        ];
        $headers = [
            "Auth0-Client" => "eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTIuMiJ9",
            "Content-Type" => "application/json",
            "Accept"       => "*/*",
        ];
        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(10);
        $this->http->PostURL("https://login.latam.com/usernamepassword/login", json_encode($data, JSON_UNESCAPED_SLASHES), $headers);
        $this->http->setMaxRedirects(5);
        $this->http->RetryCount = 2;

//        $this->http->SetInputValue("login", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
//
//        $captcha = $this->parseCaptcha();
//        if ($captcha !== false)
//            $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode("//h1[normalize-space(text()) = 'This service is temporarily unavailable']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are working to improve your experience
        if ($message = $this->http->FindSingleNode("//h1[normalize-space(text()) = 'We are working to improve your experience']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Estamos trabajando para mejorar tu experiencia
        if ($message = $this->http->FindSingleNode("//p[contains(text(),'Mientras terminamos los últimos detalles, este servicio estará temporalmente deshabilitado.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 500 Internal Server Error
        if (
            $this->http->FindSingleNode("//h1[normalize-space(text()) = '500 Internal Server Error']")
            || $this->http->FindSingleNode("//h1[normalize-space(text()) = 'Service Unavailable - DNS failure']")
            || $this->http->FindSingleNode("//h1[normalize-space(text()) = 'Service Unavailable - DNS failure']")
            || $this->http->FindSingleNode("//pre[normalize-space(text()) = 'Internal Server Error']")
            || $this->http->FindPreg("/An error occurred while processing your request\./")
            || $this->http->FindPreg("/No server is available to handle this request\./")
            || $this->http->FindPreg("/504 Gateway Time-out<\/h1>\s*The server didn't respond in time\./")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $message = $response->message ?? null;
        $name = $response->name ?? null;

        if ($message && $name) {
            if ($name == 'ValidationError' && $message == '001') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("The information you entered is incorrect. Please fill out both fields on the form.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($name == 'ValidationError' && $message == '401') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("The information you entered is incorrect. Please fill out both fields on the form.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($name == 'ValidationError' && $message == '006') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("You have two accounts associated with your user ID.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($name == 'ValidationError' && $message == '002') {
                $this->captchaReporting($this->recognizer);

                throw new CheckRetryNeededException(2, 10, "We are sorry but at the moment we cannot process your request, we are working on reestablishing the service. Please try again later or get in touch with our Contact Center regarding your query.", ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * From now on you will earn and redeem at LATAM Pass
             * Create your new access passwords and enjoy your benefits.
             */
            if ($name == 'ValidationError' && $message == '005') {
                $this->captchaReporting($this->recognizer);
                $this->throwProfileUpdateMessageException();
            }

            if ($name == 'Error' && strstr($message, 'Unexpected error in bff. FlowId: ')) {
                /*
                throw new CheckException("We apologize, there was an issue while processing your data. Please try again later.", ACCOUNT_PROVIDER_ERROR);
                */
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        } elseif ($message) {
            if ($message == 'Request to Webtask exceeded allowed execution time') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Lo sentimos, en este momento no podemos procesar tu solicitud, estamos trabajando para restablecer el servicio. Por favor inténtalo más tarde.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'parsedUserData.captcha.trim is not a function') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Origin'       => 'https://login.latam.com',
        ];
        $this->http->setMaxRedirects(10);
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $this->http->setMaxRedirects(7);

        if (($redirect = $this->http->FindSingleNode("//object[contains(@data, 'sso_login')]/@data | //iframe[contains(@src, 'sso_login')]/@src"))
            || ($redirect = $this->http->FindPreg("/executeRedirect\(\)\s*\{\s*window\.location\.href\s*=\s*'([^\']+)/"))) {
            $this->logger->debug("Redirect: {$redirect}");
            $this->http->GetURL($redirect);

            $this->captchaReporting($this->recognizer);

//            $this->sendNotification("success auth - refs #20629 // RR");

            return true;
        }

        if (
            strstr($this->http->currentUrl(), "https://bff.latam.com/ws/api/auth0-legacy-cookies/v1/cookieCallback?flowId=")
            && $this->http->Response['code'] == 403
        ) {
            // https://redmine.awardwallet.com/issues/20629
//            $this->http->GetURL('https://www.latamairlines.com/us/en');
//            $this->http->GetURL('https://www.latamairlines.com/en-us/login?returnTo=https%3A%2F%2Fwww.latamairlines.com%2Fus%2Fen');
//            $this->pontosmultiplus(true);

            return false;

            throw new CheckException("We apologize, there was an issue while processing your data. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.latam.com/en_us/apps/personas/customerportal#dashboard");

        $lan_session = explode(';', urldecode($this->http->getCookieByName("lan_session")));
        $this->logger->debug("lan_session: <pre>" . var_export($lan_session, true) . "</pre>");
        // for tam users
        $this->auth_token = urldecode($this->http->getCookieByName("auth_token", ".lan.com"));
        $this->logger->debug("auth_token: <pre>" . var_export($this->auth_token, true) . "</pre>");

        $this->http->setDefaultHeader("Content-Type", "application/json");
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $data = '{"applicationName":"customerportal","language":"EN","country":"US","portal":"personas","step":"1"}';
        $this->http->PostURL(self::CONFIGURATION_URL, $data, $this->configurationHeaders);
        $response = $this->http->JsonLog();

        if (isset($response->data->flowId)) {
            $this->http->setDefaultHeader("flowId", $response->data->flowId);
        }

        if (isset($lan_session[3])) {
            $this->http->setDefaultHeader("sessionInfoUid", $lan_session[0]);
            $this->http->setDefaultHeader("XsessionInfoUid", $lan_session[0]);
            $this->http->setDefaultHeader("sessionInfoUserType", $lan_session[1]);
            $this->http->setDefaultHeader("XsessionInfoUserType", $lan_session[1]);
            $this->http->setDefaultHeader("sessionInfoSid", $lan_session[2]);
            $this->http->setDefaultHeader("XsessionInfoSid", $lan_session[2]);
            $this->http->setDefaultHeader("sessionInfoToken", $lan_session[3]);
            $this->http->setDefaultHeader("XsessionInfoToken", $lan_session[3]);
        }// if (isset($lan_session[3]))
        elseif ($this->auth_token) {
            // for tam users
            $this->http->setDefaultHeader("XtamSessionInfoEnc", $this->auth_token);
        }
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.latam.com/ws/api/customerportal/v1/rest/proxy/customerProfile/profiles/summary", $headers);
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');

        // provider bug fix
        if (
            ($this->http->Response['code'] == 404 && $this->http->FindSingleNode('//title[contains(text(), "404 Not Found")]'))
            || (
                in_array($this->http->Response['code'], [504, 500, 503])
                && $this->http->FindPreg('/Unable to invoke request|No server is available to handle this request/')
            )
        ) {
            if (!empty($this->auth_token)) {
                $this->http->GetURL("https://www.latam.com/bin/external/customer/summary.json?" . date("UB") . $this->auth_token, $headers + ["auth_token" => $this->auth_token]);
                $data = $this->http->JsonLog(null, 3, true);

                $attempt = 0;

                while (
                    !$data
                    && $this->http->FindPreg('/<h1>HTTP Status 500 - java.lang.NoClassDefFoundError: Could not initialize class java.net.PlainDatagramSocketImpl<\/h1>/')
                    && $attempt < 3
                ) {
                    $attempt++;
                    $this->logger->notice("[attempt #{$attempt}]: provider bug fix");
                    sleep(5);
                    $this->http->GetURL("https://www.latam.com/bin/external/customer/summary.json?" . date("UB") . $this->auth_token, $headers + ["auth_token" => $this->auth_token]);
                    $data = $this->http->JsonLog(null, 3, true);
                }

                if (ArrayVal($data, 'message') == 'Invalid token') {
                    throw new CheckRetryNeededException();
                }
            } else {
                $this->logger->debug(var_export(urldecode($this->http->getCookieByName("latam_user_data", ".latam.com")), true), ['pre' => true]); // todo: if we decided grab info from cookies then IsLoggedIn should be disabled for these accounts

                $stopParse = true;

                if ($this->http->Response['code'] == 404 && $this->http->FindSingleNode('//title[contains(text(), "404 Not Found")]')) {
                    $this->logger->debug("auth_token: <pre>" . var_export($this->auth_token, true) . "</pre>");
                    $this->logger->debug("latam_user_data: <pre>" . var_export(urldecode($this->http->getCookieByName("latam_user_data", ".lan.com")), true) . "</pre>");
                    $this->logger->debug("lan_session: <pre>" . var_export(urldecode($this->http->getCookieByName("lan_session", ".lan.com")), true) . "</pre>");

                    if (
                        $this->attempt == 1
                        && $this->auth_token == ''
                        && !empty($this->http->getCookieByName("lan_session", ".lan.com"))
                        && urldecode($this->http->getCookieByName("latam_user_data", ".lan.com")) == ';;;;;;'
                    ) {
                        throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    if (!empty($this->http->getCookieByName("latam_user_data", ".lan.com")) && !empty($this->http->getCookieByName("lan_session", ".lan.com"))) {
                        $stopParse = false;
                    }

                    if ($stopParse == true) {
                        throw new CheckRetryNeededException(2, 3);
                    }
                }

                if ($stopParse == true) {
                    return;
                }
            }
        }

        $this->http->RetryCount = 2;
        // Sesion LAN expirada
        if ($this->http->Response['code'] == 401) {
            $message = $response['status']['message'] ?? null;
            $this->sessionExpirada($response);

            if ($message == 'Request invalida') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }

        if (isset($this->http->Response['headers']['trackid'])) {
            $this->http->setDefaultHeader("X-Track-Id", $this->http->Response['headers']['trackid']);
        }

        // Balance - Total miles
        $this->SetBalance(ArrayVal($data, 'balance'));
        // Member No
        $this->SetProperty("Number", ArrayVal($data, 'memberNumber'));
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($data, 'firstName') . " " . ArrayVal($data, 'lastName')));
        // Category
        $this->SetProperty("Category", str_replace(' SIGNATURE', '', ArrayVal($data, 'category')));
        // Status expiration
        $categoryExpirationDate = ArrayVal($data, 'categoryExpirationDate');
        $categoryExp = strtotime($categoryExpirationDate);

        if ($categoryExp && $categoryExp < strtotime("+ 10 year")) {
            $this->SetProperty("StatusExpiration", $categoryExpirationDate);
        }

        $parseBalanceMultiplus = false;

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Unable to retrieve information of a customer
            if (ArrayVal($data, 'message') == 'Unable to retrieve information of a customer.'
                || (ArrayVal($data, 'userType') == 'LAN' && ArrayVal($data, 'profileStatus') == 'ACTIVO') && !empty($this->Properties['Name'])
                || ($this->http->currentUrl() == 'https://www.latam.com/ws/api/customerportal/v1/rest/proxy/customerProfile/profiles/summary' && $this->http->Response['code'] == 404)
                || ($this->http->currentUrl() == 'https://www.latam.com/ws/api/customerportal/v1/rest/proxy/customerProfile/profiles/summary' && $this->http->Response['code'] == 504)
            ) {
                $latam_user_data = explode(';', urldecode($this->http->getCookieByName("latam_user_data")));
                $this->logger->debug(var_export($latam_user_data, true), ['pre' => true]);

                if (isset($latam_user_data[5])) {
                    $parseBalanceMultiplus = true;

                    // Balance - Total miles
                    $this->SetBalance(str_replace("'", "", $latam_user_data[3]));
                    // Name
                    $this->SetProperty("Name", beautifulName(str_replace("'", "", $latam_user_data[0]) . " " . str_replace("'", "", $latam_user_data[1])));
                    // Category
                    $this->SetProperty("Category", str_replace(' SIGNATURE', '', str_replace("'", "", $latam_user_data[5])));
                }// if (isset($lan_session[3]))
            }// if (ArrayVal($data, 'message') == 'Unable to retrieve information of a customer.')
            // isLoggedIn workaround
            $this->sessionExpirada($response);
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && ArrayVal($data, 'message') == 'Unable to retrieve information of a customer.')

        $this->http->setCookie('homeInfo', 'en_us', '.lan.com');
        $this->http->setCookie('pcom', 'en%2Fus', '.lan.com');
//        $this->http->GetURL("https://www.latam.com/ws/api/ffp-services-bff-web/v2/cartola/summary");
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.latam.com/ws/api/ffp-services-bff-web/v2/cartola/summary?year=" . date("Y", strtotime("-1 year")));
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 0);

        // refs #16718
        $rules = $response->qualifiers->upgrade->rules ?? [];

        foreach ($rules as $rule) {
            if ($rule->ruledescription[0]->value != 'REGLA_META_KMS_MAS_SEGMENTOS_MAS_REVENUE_NO_KMS') {
                $this->logger->debug("skip {$rule->ruledescription[0]->value}");

                continue;
            }

            foreach ($rule->qualifiers as $qualifier) {
                $value = $qualifier->current[0]->value;
                $caption = $qualifier->current[2]->value;

                switch ($caption) {
                    // Elite Miles
                    case 'CARTOLA_LABEL_MILLAS_ELITE':
                        $this->SetProperty('EliteMiles', $value);

                        break;
                    // Premium Segments
                    case 'CARTOLA_LABEL_SEGMENTOS_PREMIUM':
                        $this->SetProperty('Segments', $value);

                        break;
                    // Elite Dollars
                    case 'CARTOLA_LABEL_DOLARES_ELITE':
                        $this->SetProperty('EliteDollars', $value);

                        break;
                }// switch ($caption)
            }// foreach ($rule->qualifiers as $qualifier)
        }// foreach ($rules as $rule)

        $toExpire = $response->summary->toExpire ?? [];

        foreach ($toExpire as $expire) {
            $expDate = $expire->date;
            $expBalance = $expire->total;

            if (!isset($exp) || strtotime($expDate) < $exp) {
                // Expiration date
                $exp = strtotime($expDate);
                $this->SetExpirationDate($exp);
                // Expiring Balance
                $this->SetProperty('ExpiringBalance', $expBalance);
            }// if (!isset($exp) || strtotime($expDate) < $exp)
        }// foreach ($toExpire as $expire)

        // Status expiration
        if (!isset($this->Properties['StatusExpiration'])) {
            $categoryExpirationDate = $response->summary->tierExpiration ?? null;
            $categoryExp = strtotime($categoryExpirationDate);

            if ($categoryExpirationDate && $categoryExp < strtotime("+ 10 year")) {
                $this->SetProperty("StatusExpiration", $categoryExpirationDate);
            }
        }// if (!isset($this->Properties['StatusExpiration']))

        if (
            $this->http->currentUrl() == 'https://www.latam.com/ws/api/ffp-services-bff-web/v2/cartola/summary?year=2020'
            && $this->http->Response['code'] == 504
            && $parseBalanceMultiplus === true
        ) {
            $this->http->GetURL('https://www.latamairlines.com/us/en');
            $this->http->GetURL('https://www.latamairlines.com/en-us/login?returnTo=https%3A%2F%2Fwww.latamairlines.com%2Fus%2Fen');
//            $this->http->GetURL('https://www.pontosmultiplus.com.br/myaccount/login.jsp?urlOrigin=L2hvbWU=&_DARGS=/cartridges/LatamHeader/LatamHeader.jsp_A&_DAV=/home&_dynSessConf=7564827652428911123&_ga=2.96109852.1465267298.1631874523-1604155782.1631874522');
        }

        $this->pontosmultiplus($parseBalanceMultiplus);
    }

    public function pontosmultiplus($parseBalance = false)
    {
        $this->logger->notice(__METHOD__);
        // refs #18378, 2692
        // todo
//        if (!strstr($this->http->currentUrl(), 'https://www.pontosmultiplus.com.br') && !strstr($this->http->currentUrl(), 'https://pontosmultiplus.com.br')) {
//            return;
//        }
        $this->logger->info('Expiration date', ['Header' => 3]);

        if ($parseBalance == false) {
            unset($this->Properties['Category']);
        }

        $browser = clone $this;
        $this->http->brotherBrowser($browser->http);

        $browser->http->setHttp2(true);

        if (in_array($this->http->Response['code'], [404, 403]) || $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)) {
            $browser->setProxyBrightData(null, 'static', 'br');
//            $browser->http->GetURL('https://www.latamairlines.com/us/en');
            $browser->http->GetURL('https://www.latamairlines.com/en-us/login?returnTo=https%3A%2F%2Fwww.latamairlines.com%2Fus%2Fen');
//            $browser->http->GetURL($this->http->currentUrl());
        }

        $client_id = $this->http->FindPreg("/client=([^&]+)/", false, $browser->http->currentUrl());
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $browser->http->currentUrl());
        $nonce = $this->http->FindPreg("/nonce=([^&]+)/", false, $browser->http->currentUrl());
        $_csrf =
            $browser->http->getCookieByName("_csrf", "accounts.latamairlines.com", "/usernamepassword/login", true)
//            ?? $browser->http->getCookieByName("_csrf", "latam-xp-prod.auth0.com", "/usernamepassword/login", true)
        ;
        $this->logger->debug("_csrf: " . $_csrf);

        if ((!$client_id || !$_csrf || !$nonce) && !$browser->http->FindSingleNode('//div[contains(@class, "user-data--tier")]/strong[1]')) {
            if (
                $parseBalance === true
                && $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
                && $this->http->Response['code'] == 403
            ) {
                throw new CheckRetryNeededException(3, 0);
            }

            if (
                $parseBalance === true
                && $browser->http->FindSingleNode('//h1[contains(text(), "Service Unavailable - DNS failure")]')
            ) {
                throw new CheckRetryNeededException(3, 0);
            }

            if (
                $parseBalance === true
                && $browser->http->FindSingleNode('//h1[contains(text(), "Site em manutenção")]')
            ) {
                throw new CheckException("Site em manutenção. Logo mais estaremos de volta", ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }
        // for isLoggedIn
        elseif (!$browser->http->FindSingleNode('//div[contains(@class, "user-data--tier")]/strong[1]')) {
//            $key = $browser->http->FindSingleNode("//div[@id = 'recap-login']/@data-sitekey");
//            $captcha = $browser->parseCaptcha(self::CAPTCHA_RECOGNIZER_RUCAPTCHA, $key);
//
//            if (
//                $this->attempt == 2
//                && ($sensorPostUrl = $this->http->FindPreg('# src="([^\"]+)"></script></body>#'))
//            ) {
//                $this->http->NormalizeURL($sensorPostUrl);
//                $this->sendStaticSensorDataMultiplus($sensorPostUrl);
//                sleep(1);
//            } else {
//                $this->seleniumMultiplus($browser->http->currentUrl(), $browser);
//            }
//            $this->seleniumMultiplus($browser->http->currentUrl(), $browser);

//            if ($captcha == null) {
//                return;
//            }

            $data = [
                "client_id"     => $client_id,
                "redirect_uri"  => "https://www.latamairlines.com/callback",
                "tenant"        => "latam-xp-prod",
                "response_type" => "code",
                "scope"         => "openid email profile",
                "_csrf"         => $_csrf,
                "state"         => $state,
                "_intstate"     => "deprecated",
                "nonce"         => $nonce,
                "password"      => base64_encode($this->AccountFields['Pass']),
                "connection"    => "latamxp-prod-db",
                "username"      => "{\"alias\":\"{$this->AccountFields['Login']}\",\"lang\":\"en\",\"country\":\"us\",\"antiFraudHeader\":{\"X-latam-Application-Af\":\"FE-20211102095835910-3699|2|{$this->http->currentUrl()}|agent_desktop|{$this->http->getIpAddress()}|5189\"}}",
            ];
            $headers = [
                "Accept"       => "*/*",
                "Content-Type" => "application/json",
                "Origin"       => "https://accounts.latamairlines.com",
                "auth0-client" => "eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTQuMiJ9",
            ];
            $browser->http->RetryCount = 0;
            $browser->http->PostURL("https://accounts.latamairlines.com/usernamepassword/login", json_encode($data), $headers);
            $browser->http->RetryCount = 2;

            if ($browser->http->ParseForm("hiddenform")) {
                $browser->http->PostForm();
            }

            $response = $browser->http->JsonLog();
            $success = $response->name ?? null;
            $responseCode = $response->code ?? null;

            if ($success == 'Error') {
                $description = $response->description ?? null;

                if ($description == '2FA Required') {
//                    $this->parseQuestion();

                    return;
                }

                if (
                    $parseBalance === true
                    && $description == "Error."
                    && $responseCode == 401
                ) {
                    throw new CheckException("Check the email or membership number and password entered.", ACCOUNT_INVALID_PASSWORD);
                }

                return;
            }

            $browser->captchaReporting($this->recognizer);
            $browser->http->RetryCount = 0;

            if (!isset($response->description->data->userId)) {
                $this->logger->error("userId not found");

                return;
            }

            $this->sendNotification("success // RR");

            $headers = [
                "accept"                      => "*/*",
                "content-type"                => "application/json",
                //                "x-latam-app-session-id"      => "c5cd5086-8c3c-4ed7-940d-1d0f122b9129",// todo
                "x-latam-application-country" => "us",
                "x-latam-application-lang"    => "en",
                "x-latam-application-name"    => "my-account",
                "x-latam-client-name"         => "my-account",
                "x-latam-country"             => "us",
                "x-latam-lang"                => "en",
                //                "x-latam-request-id"          => "20356bc8-d957-4f21-b4fa-ca1ed4acea41",
                //                "x-latam-track-id"            => "0ddce07e-8dfc-4d49-825e-9d23d5123f5e",
            ];
            $browser->http->RetryCount = 0;
            $browser->http->GetURL("https://www.latamairlines.com/bff/web-profile/v1/user/{$response->description->data->userId}/profile", $headers);
            $browser->http->RetryCount = 2;
            $browser->http->JsonLog();

            if (!$browser->http->GetURL("https://www.pontosmultiplus.com.br/portal/")) {
                $this->increaseTimeLimit();

                return;
            }
        }
        // Category
        $this->SetProperty("Category", $browser->http->FindSingleNode('//div[contains(@class, "user-data--tier")]/strong[1]'));
        // Qualificáveis ... pontos
        $this->SetProperty("EliteMiles", $browser->http->FindSingleNode('//div[@id = "info-box-qualifier"]/div[2]/span/strong'));

        if ($parseBalance === true) {
            // Balance - Total miles
            $this->SetBalance($browser->http->FindSingleNode('//div[p[
                    contains(text(), "Saldo total")
                    or contains(text(), "Member No.")
                    or contains(text(), "N° de membre")
                    or contains(text(), "Gesamtsumme")
                    or contains(text(), "Saldo toal")
                    or contains(text(), "Balance")
            ]]/following-sibling::div/span/strong'));
            // Member No
            $this->SetProperty("Number", $browser->http->FindSingleNode('//node()[
                    contains(., "Nº de membro")
                    or contains(., "N° de membre")
                    or contains(., "Member No.")
                    or contains(., "Mitgliedsnr.")
                    or contains(., "N.º de miembro")
            ]/following-sibling::strong'));
            // Name
            $this->SetProperty("Name", beautifulName($browser->http->FindSingleNode('//span[contains(@class, "participante")]')));
        }

        $browser->http->GetURL("https://www.pontosmultiplus.com.br/portal/pages/MeusPontosAVencer.html");
        $browser->http->RetryCount = 2;

        if (!$browser->http->ParseForm("formDataTable")) {
            return;
        }
        $browser->http->SetInputValue("javax.faces.partial.ajax", "true");
        $browser->http->SetInputValue("javax.faces.source", "formDataTable:selectPeriodo");
        $browser->http->SetInputValue("javax.faces.partial.execute", "formDataTable:selectPeriodo");
        $browser->http->SetInputValue("javax.faces.partial.render", "formDataTable:selectPeriodo formDataTable:panelTransacoes formDataTable:totalPersonalizado");
        $browser->http->SetInputValue("javax.faces.behavior.event", "valueChange");
        $browser->http->SetInputValue("javax.faces.partial.event", "change");
        $browser->http->SetInputValue("formDataTable", "formDataTable");
        $browser->http->SetInputValue("formDataTable:selectPeriodo_input", 1080);
        $browser->http->SetInputValue("formDataTable:selectPeriodo_focus", "");
        $browser->http->SetInputValue("formDataTable:txtDe_pv_input", date("d/m/Y"));
        $browser->http->SetInputValue("formDataTable:txtAte_pv_input", date("d/m/Y", strtotime("+3 year")));
        $browser->http->SetInputValue("formDataTable:id-periodo-max", 3);
        $headers = [
            "Accept"           => "application/xml, text/xml, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "Faces-Request"    => "partial/ajax",
            "Origin"           => "https://www.pontosmultiplus.com.br",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $browser->http->PostForm($headers);

        $transactions = $browser->http->XPath->query('//tbody[@id="formDataTable:tblTransacoes_data"]/tr');
        $this->logger->notice("Total {$transactions->length} transactions were found");

        foreach ($transactions as $transaction) {
            $expDate = $this->ModifyDateFormat($browser->http->FindSingleNode('td[1]', $transaction));
            $expBalance = $browser->http->FindSingleNode('td[2]', $transaction);

            if ($expDate && (!isset($exp) || strtotime($expDate) < $exp)) {
                // Expiration date
                $exp = strtotime($expDate);
                $this->SetExpirationDate($exp);
                // Expiring Balance
                $this->SetProperty('ExpiringBalance', $expBalance);
            }// if ($expDate && (!isset($exp) || strtotime($expDate) < $exp))
        }// foreach ($transactions as $transaction)
    }

    public function sessionExpirada($response)
    {
        $this->logger->notice(__METHOD__);
        $message = $response['status']['message'] ?? null;

        if (in_array($message, ['Sesion TAM expirada', 'Sesion LAN expirada'])) {
            throw new CheckRetryNeededException(2, 1);
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->unsetDefaultHeader('X-Requested-With');
        $this->http->unsetDefaultHeader('sessionInfoUid');
        $this->http->unsetDefaultHeader('sessionInfoUserType');
        $this->http->unsetDefaultHeader('sessionInfoSid');
        $this->http->unsetDefaultHeader('sessionInfoToken');
        $this->http->setDefaultHeader("application", "mybookings");
        $this->http->setDefaultHeader("channel", "WEB");
        $this->http->setDefaultHeader("country", "US");
        $this->http->setDefaultHeader("language", "EN");
        $this->http->setDefaultHeader("portal", "personas");
        $this->http->setDefaultHeader("Origin", "https://www.latam.com");

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json, text/javascript, */*; q=0.01',
            'Referer'      => 'https://www.latam.com/en_us/apps/personas/mybookings',
            'flowId'       => '0', // It is important that it is there
            'X-Flow-Id'    => '0', // It is important that it is there
            'trackId'      => '0', // It is important that it is there
            'X-Track-Id'   => '0', // It is important that it is there
        ];
        $this->http->GetURL("https://bff.latam.com/ws/api/mybookings/v3/rest/reservations", $headers);
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');
        $bookingItems = ArrayVal($data, 'bookingItems', []);

        // no Itineraries
        if ($this->http->FindPreg("/\"bookingItems\":\[\]/")) {
            return $this->noItinerariesArr();
        }

        if (count($bookingItems) > 1) {
            $this->logger->error('Skip if there is more than one reservation');

            return $result;
        }

        // Sesion LAN expirada
        // $this->sessionExpirada($response);

        // MyTrips-Web-Token
        $this->http->GetURL("https://www.latam.com/en_us/apps/personas/mybookings");
        $clientId = $this->http->FindPreg("/clientId':'([^\']+)/");

        foreach ($bookingItems as $bookingItem) {
            $arFields['ConfNo'] = ArrayVal($bookingItem, 'recordLocator');
            $arFields['LastName'] = ArrayVal($bookingItem, 'lastName');

            $this->logger->info("Parse Itinerary #{$arFields['ConfNo']}", ['Header' => 3]);

            $it = $this->parseItinerary($arFields['ConfNo'], $arFields['LastName'], $clientId);
            /*
            $resp = $this->getRecaptchaV3FromSelenium($arFields);
            if (!$resp)
                return null;

            $it = $this->parseItinerary2($arFields['ConfNo'], $arFields['LastName'], $clientId, $resp);
            */

            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($it, true), ['pre' => true]);

            if (is_string($it)) {
                continue;
            }

            $result[] = $it;
        }// foreach ($bookingItems as $bookingItem)

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
//        return "https://www.latam.com/en_us/apps/personas/mybookings";
        return "https://www.latamairlines.com/us/en/my-trips";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $clientId = $this->http->FindPreg("/clientId':'([^\']+)/");

        if (!$clientId) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        $resp = $this->getRecaptchaV3FromSelenium($arFields);

        if (!$resp) {
            return null;
        }

        $result = $this->parseItinerary2($arFields['ConfNo'], $arFields['LastName'], $clientId, $resp);

        if (is_string($result)) {
            return $result;
        }

        $it = $result;

        return null;

        if ($this->http->Response['code'] == 403) {
            sleep(3);
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));

            if ($this->http->Response['code'] == 403) {
                sleep(5);
                $this->http->GetURL($this->ConfirmationNumberURL($arFields));
            }
        }
        $clientId = $this->http->FindPreg("/clientId':'([^\']+)/");

        if (!$clientId) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }
//        $result = $this->parseItinerary2($arFields['ConfNo'], $arFields['LastName'], $clientId, $resp);
        $result = $this->parseItinerary($arFields['ConfNo'], $arFields['LastName'], $clientId);

        if (is_string($result)) {
            return $result;
        }

        $it = $result;

        return null;
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
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    protected function parseCaptcha($service, $key = null, $currentURL = null, $invisible = false, $isV3 = false, $action = "submit")
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindPreg("/\"sitekey\"\s*:\s*\"([^\"]+)/");
        }

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $currentURL ?? $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => $invisible ? '1' : '0',
        ];

        if ($isV3 === true) {
            if ($service == self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2) {
                $postData = [
                    "type"         => "RecaptchaV3TaskProxyless",
                    "websiteURL"   => $currentURL ?? $this->http->currentUrl(),
                    "websiteKey"   => $key,
                    "minScore"     => 0.9,
                    "pageAction"   => $action,
                ];

                if ($key === '6LeMru0aAAAAAEy8qRwQ6SEwex-fwoTqmSwrxkdD') {
                    $postData["isEnterprise"] = true;
                }

                if ($key === '6LegvyYbAAAAAMrG02u0u3RVralXEZ-cNrETN43F') {
                    $postData["isEnterprise"] = true;
                }

                $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
                $this->recognizer->RecognizeTimeout = 120;

                return $this->recognizeAntiCaptcha($this->recognizer, $postData);
            }
            $parameters += [
                "version"   => "v3",
                "action"    => $action,
                "min_score" => 0.9,
            ];
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseItinerary2($recordLocator, $lastName, $clientId, $resp)
    {
        /*
        $response = $this->http->JsonLog($resp);

        if (!isset($resp->url)) {
            return [];
        }
        $v8 = new V8Js();
        $generateId = "
           var e = (new Date).getTime();
           var r = \"xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx\".replace(/[xy]/g, function(t) {
                var n = (e + 16 * Math.random()) % 16 | 0;
                return e = Math.floor(e / 16),
                    (\"x\" == t ? n : 3 & n | 8).toString(16)
           }); r;
        ";
        $trackId = $v8->executeString($generateId, 'basic.js');
        $this->logger->notice($trackId);
        $flowId = $v8->executeString($generateId, 'basic.js');
        $this->logger->notice($flowId);
        $headers = [
            'Referer'            => 'https://www.latam.com/en_us/apps/personas/mybookings',
            'Origin'             => 'https://www.latam.com',
            'Content-Type'       => 'application/json',
            'Authorization'      => "Bearer " . str_replace('https://www.latam.com/en_us/apps/personas/mybookings#reservas?entry=', '', $resp->url),
            'Accept'             => 'application/json, text/javascript, * / *; q=0.01',
            'X-Application-Name' => 'mybookings',
            'X-Track-Id'         => $trackId,
            'trackid'            => $trackId,
            'X-Flow-Id'          => $flowId,
            'flowId'             => $flowId,
            'country'            => 'US',
            'channel'            => 'WEB',
            'language'           => 'EN',
            'portal'             => 'personas',
            'application'        => 'mybookings',
        ];
        // get reservation info
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://bff.latam.com/ws/api/mybookings/v3/rest/reservation/booking-info", $headers);
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] != 403) {
            $this->sendNotification('success booking-info // MI');
        }
        */
        $response = $this->http->JsonLog($resp, 3, true);
        $data = ArrayVal($response, 'data', []);
        $it = ["Kind" => "T"];

        if (empty($data)) {
            return $it;
        }

        if (empty(ArrayVal($data, 'recordLocator'))) {
            if ($this->http->FindPreg("/\"message\":\"Reservation not found for PNR\"/")) {
                if (strlen($recordLocator) > 6) {
                    return "The reservation information entered is not valid";
                }

                return "We do not have any reservations containing the information you entered. Please review them and try again.";
            }

            if ($this->http->FindPreg("/\"message\":\"Unauthorized Reservation\"/")) {
                return "It is not possible to show your reservation information at this time";
            }

            return $it;
        }

        $this->sendNotification('success parse it // MI');
        $it['RecordLocator'] = ArrayVal($data, 'recordLocator');

        // Segments
        $segments = array_merge(ArrayVal($data, 'outbound', []), ArrayVal($data, 'inbound', []), ArrayVal($data, 'multiCity', []));
        $this->logger->debug("Total " . count($segments) . " segments were found");

        foreach ($segments as $seg) {
            $segment = [];
            $segment['FlightNumber'] = ArrayVal($seg, 'flightNumber');

            $operatingAirline = ArrayVal($seg, 'operatingAirline');
            $segment['AirlineName'] = ArrayVal($operatingAirline, 'iataCode');
            $segment['Cabin'] = ArrayVal($seg, 'cabin');
            $segment['BookingClass'] = ArrayVal($seg, 'bookingClass');

            $legs = ArrayVal($seg, 'legs');
            $segment['DepName'] = ArrayVal($legs[0], 'departureCityName') . ", " . ArrayVal($legs[0], 'departureAirportName');
            $segment['DepCode'] = ArrayVal($legs[0], 'departureAirportCode');

            $segment['ArrName'] = ArrayVal($legs[0], 'arrivalCityName') . ", " . ArrayVal($legs[0], 'arrivalAirportName');
            $segment['ArrCode'] = ArrayVal($legs[0], 'arrivalAirportCode');

            $depDate = ArrayVal($legs[0], 'departureDateTime');

            if (strtotime($depDate)) {
                $segment['DepDate'] = strtotime($depDate);
            }

            $arrDate = ArrayVal($legs[0], 'arrivalDateTime');

            if (strtotime($arrDate)) {
                $segment['ArrDate'] = strtotime($arrDate);
            }

            $it['TripSegments'][] = $segment;
        }// foreach ($segments as $seg)

        // Passengers
        $passengers = ArrayVal($data, 'passengers', []);

        foreach ($passengers as $passenger) {
            $it['Passengers'][] = beautifulName(ArrayVal($passenger, 'firstName') . " " . ArrayVal($passenger, 'lastName'));
            $loyaltyInfo = ArrayVal($passenger, 'loyaltyInfo');
            $frequentFlyerId = ArrayVal($loyaltyInfo, 'frequentFlyerId');

            if ($frequentFlyerId) {
                $it['AccountNumbers'][] = $frequentFlyerId;
            }
        }

        if (!empty($it['AccountNumbers'])) {
            $it['AccountNumbers'] = implode(', ', $it['AccountNumbers']);
        }

        return $it;
    }

    private function parseItinerary($recordLocator, $lastName, $clientId)
    {
        $this->logger->notice(__METHOD__);

        // for success captcha recognizing
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $this->browser->LogHeaders = true;

        // set headers
        $this->browser->setDefaultHeader("application", "mybookings");
        $this->browser->setDefaultHeader("channel", "WEB");
        $this->browser->setDefaultHeader("country", "US");
        $this->browser->setDefaultHeader("language", "EN");
        $this->browser->setDefaultHeader("portal", "personas");
        $this->browser->setDefaultHeader("Origin", "https://www.latam.com");

        $flowId = $this->http->getDefaultHeader('flowId') ?? "0";
        $trackId = '0';

        $this->browser->GetURL('https://www.latam.com/ws/api/common/airports/1.0/rest/applicationName/mybookings/portal/personas/language/ES/country/CL?portal=personas&application=mybookings&country=CL&language=ES&clientid=flight_status&channel=WEB');
        $flowId = ArrayVal($this->browser->Response['headers'], 'flowid') ?: $flowId;
        $trackId = ArrayVal($this->browser->Response['headers'], 'trackid') ?: $trackId;

        if ($this->browser->Response['code'] != 200) {
            return ["Kind" => "T"];
        }

        $captcha = $this->parseCaptcha(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2, "6LcFoLUUAAAAAKGqMopj6jyAINLdYvC3ls74xozI", "https://www.latam.com/en_us/apps/personas/mybookings", true, true);
//        $captcha = $this->parseCaptcha(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2, "6LegvyYbAAAAAMrG02u0u3RVralXEZ-cNrETN43F", "https://www.latam.com/en_us/apps/personas/mybookings", true, true, "booking_search");

        if ($captcha == null) {
            return ["Kind" => "T"];
        }
        $headers = [
            'Referer'                   => 'https://www.latam.com/en_us/apps/personas/mybookings',
            'Origin'                    => 'https://www.latam.com',
            'Content-Type'              => 'application/json',
            'Connection'                => 'keep-alive',
            'flowId'                    => $flowId, // It is important that it is there
            'trackId'                   => "0", // It is important that it is there
            'Accept'                    => 'application/json, text/javascript, */*; q=0.01',
            'X-Application-Name'        => 'mybookings',
            'X-Channel'                 => 'WEB',
            "X-Client-Id"               => $clientId,
            'X-Country'                 => 'US',
            'X-Flow-Id'                 => $flowId,
            'X-Home'                    => 'en_us',
            'X-Language'                => 'EN',
            "X-MyTrips-Challenge-Token" => $captcha,
            "X-Portal"                  => "personas",
            "X-Track-Id"                => $trackId,
        ];
        $data = [
            "recordLocator" => $recordLocator,
            "lastName"      => $lastName,
        ];
        $this->browser->RetryCount = 0;
        $this->browser->PostURL("https://bff.latam.com/ws/api/rest/authorization-service/v1/access/token", json_encode($data), $headers);

        // it helps
        if ($this->browser->Response['code'] == 403) {
            $captcha = $this->parseCaptcha(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2, "6LcFoLUUAAAAAKGqMopj6jyAINLdYvC3ls74xozI", "https://www.latam.com/en_us/apps/personas/mybookings", true, true);
//            $captcha = $this->parseCaptcha(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2, "6LegvyYbAAAAAMrG02u0u3RVralXEZ-cNrETN43F", "https://www.latam.com/en_us/apps/personas/mybookings", true, true, "booking_search");

            if ($captcha == null) {
                return ["Kind" => "T"];
            }

            $headers["X-MyTrips-Challenge-Token"] = $captcha;
            $this->browser->PostURL("https://bff.latam.com/ws/api/rest/authorization-service/v1/access/token", json_encode($data), $headers);
        }

        $this->browser->RetryCount = 2;

        // flowId, trackId
        /*
        $v8 = new V8Js();
        $generateId = "
           var e = (new Date).getTime();
           var r = \"xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx\".replace(/[xy]/g, function(t) {
                var n = (e + 16 * Math.random()) % 16 | 0;
                return e = Math.floor(e / 16),
                    (\"x\" == t ? n : 3 & n | 8).toString(16)
           }); r;
        ";
        $trackId = $v8->executeString($generateId, 'basic.js');
        $this->logger->notice($trackId);
        $flowId = $v8->executeString($generateId, 'basic.js');
        $this->logger->notice($flowId);
        */

        $this->browser->RetryCount = 2;
        $response = $this->browser->JsonLog();

        if (!isset($response->url)) {
            $this->logger->error("token not found");

            return ["Kind" => "T"];
        }

        $headers = [
            'Referer'            => 'https://www.latam.com/en_us/apps/personas/mybookings',
            'Origin'             => 'https://www.latam.com',
            'Content-Type'       => 'application/json',
            //'xtamsessioninfoenc' => $this->auth_token,
            'Authorization'      => "Bearer " . str_replace('https://www.latam.com/en_us/apps/personas/mybookings#reservas?entry=', '', $response->url),
            'Accept'             => 'application/json, text/javascript, */*; q=0.01',
            'X-Application-Name' => 'mybookings',
            'X-Track-Id'         => $trackId,
            'trackid'            => $trackId,
            'X-Flow-Id'          => $flowId,
            'flowId'             => $flowId,
            'country'            => 'US',
            'channel'            => 'WEB',
            'language'           => 'EN',
            'portal'             => 'personas',
            'application'        => 'mybookings',
        ];
        // get reservation info
        $this->browser->RetryCount = 0;
        $this->browser->GetURL("https://bff.latam.com/ws/api/mybookings/v3/rest/reservation/booking-info", $headers);

        if ($this->browser->Response['code'] == 403) {
            sleep(5);
            $this->http->GetURL("https://bff.latam.com/ws/api/mybookings/v3/rest/reservation/booking-info", $headers);
        }

        $this->browser->RetryCount = 2;

        $response = $this->browser->JsonLog(null, 3, true);

        // todo: debug 500
        /*
        if ($this->http->Response['body'] == '{"status":{"code":500,"message":"Unknown error"},"data":null}') {
            sleep(10);
            $this->sendNotification("500 - Unknown error, workaround // RR");

            $headers = [
                "Accept"             => "*
        /*",
                "Accept-Encoding"    => "gzip, deflate, br",
                "Content-Type"       => "application/json",
                "Origin"             => "https://www.latam.com",
                "Referer"            => "https://www.latam.com/en_un/check-in-and-other-services/",
                "X-Application-Name" => "cms",
                "X-Channel"          => "web",
                "X-Client-Id"        => $clientId,
                "X-Country"          => "UN",
                "X-Flow-Id"          => $flowId,
                "X-Home"             => "en_us",
                "X-Language"         => "en",
                "X-Portal"           => "personas",
                "X-Track-Id"         => $trackId,
            ];
            $data = [
                "recordLocator" => $recordLocator,
                "lastName"      => $lastName,
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://bff.latam.com/ws/api/rest/authorization-service/v1/access/token", json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
            if (!isset($response->url)) {
                $this->logger->error("token not found");
                return ["Kind" => "T"];
            }

            $headers = [
                'Referer'            => 'https://www.latam.com/en_us/apps/personas/mybookings',
                'Origin'             => 'https://www.latam.com',
                'Content-Type'       => 'application/json',
                'country'            => 'US',
                'trackid'            => $trackId, // It is important that it is there
                'xtamsessioninfoenc' => $this->auth_token,
                'Authorization'      => "Bearer ".str_replace('https://www.latam.com/en_us/apps/personas/mybookings#reservas?entry=', '', $response->url),
                'Accept'             => 'application/json, text/javascript, *
        /*; q=0.01',
                'X-Application-Name' => 'mybookings',
                'X-Track-Id'         => $trackId,
                'X-Flow-Id'          => $flowId,
            ];
            // get reservation info
            $this->http->GetURL("https://bff.latam.com/ws/api/mybookings/v3/rest/reservation/booking-info", $headers);
            $response = $this->http->JsonLog(null, 3, true);
        }
        */

        $data = ArrayVal($response, 'data', []);
        $it = ["Kind" => "T"];

        if (empty(ArrayVal($data, 'recordLocator'))) {
            if ($this->http->FindPreg("/\"message\":\"Reservation not found for PNR\"/")) {
                if (strlen($recordLocator) > 6) {
                    return "The reservation information entered is not valid";
                }

                return "We do not have any reservations containing the information you entered. Please review them and try again.";
            }

            if ($this->http->FindPreg("/\"message\":\"Unauthorized Reservation\"/")) {
                return "It is not possible to show your reservation information at this time";
            }

            return $it;
        }

        $it['RecordLocator'] = ArrayVal($data, 'recordLocator');

        // Segments
        $segments = array_merge(ArrayVal($data, 'outbound', []), ArrayVal($data, 'inbound', []), ArrayVal($data, 'multiCity', []));
        $this->logger->debug("Total " . count($segments) . " segments were found");

        foreach ($segments as $seg) {
            $segment = [];
            $segment['FlightNumber'] = ArrayVal($seg, 'flightNumber');

            $operatingAirline = ArrayVal($seg, 'operatingAirline');
            $segment['AirlineName'] = ArrayVal($operatingAirline, 'iataCode');
            $segment['Cabin'] = ArrayVal($seg, 'cabin');
            $segment['BookingClass'] = ArrayVal($seg, 'bookingClass');

            $legs = ArrayVal($seg, 'legs');
            $segment['DepName'] = ArrayVal($legs[0], 'departureCityName') . ", " . ArrayVal($legs[0], 'departureAirportName');
            $segment['DepCode'] = ArrayVal($legs[0], 'departureAirportCode');

            $segment['ArrName'] = ArrayVal($legs[0], 'arrivalCityName') . ", " . ArrayVal($legs[0], 'arrivalAirportName');
            $segment['ArrCode'] = ArrayVal($legs[0], 'arrivalAirportCode');

            $depDate = ArrayVal($legs[0], 'departureDateTime');

            if (strtotime($depDate)) {
                $segment['DepDate'] = strtotime($depDate);
            }

            $arrDate = ArrayVal($legs[0], 'arrivalDateTime');

            if (strtotime($arrDate)) {
                $segment['ArrDate'] = strtotime($arrDate);
            }

            $it['TripSegments'][] = $segment;
        }// foreach ($segments as $seg)

        // Passengers
        $passengers = ArrayVal($data, 'passengers', []);

        foreach ($passengers as $passenger) {
            $it['Passengers'][] = beautifulName(ArrayVal($passenger, 'firstName') . " " . ArrayVal($passenger, 'lastName'));
            $loyaltyInfo = ArrayVal($passenger, 'loyaltyInfo');
            $frequentFlyerId = ArrayVal($loyaltyInfo, 'frequentFlyerId');

            if ($frequentFlyerId) {
                $it['AccountNumbers'][] = $frequentFlyerId;
            }
        }

        if (!empty($it['AccountNumbers'])) {
            $it['AccountNumbers'] = implode(', ', $it['AccountNumbers']);
        }

        return $it;
    }

    private function getRecaptchaV3FromSelenium($arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
            //$selenium->disableImages();
//            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->removeCookies();
            //$selenium->http->GetURL("https://www.latam.com/en_us/");
            $selenium->http->GetURL($this->ConfirmationNumberURL($arFields));
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(., 'Find your trip')]"), 5);
            $locator = $selenium->waitForElement(WebDriverBy::id("record-locator-input"), 0);
            $name = $selenium->waitForElement(WebDriverBy::id("last-name-input"), 0);
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if ($btn) {
                $this->logger->info("login form loaded");
                $locator->sendKeys($arFields['ConfNo']);
                $name->sendKeys($arFields['LastName']);
                /*$selenium->driver->executeScript("grecaptcha.ready(function() {
                    grecaptcha.execute('6LcFoLUUAAAAAKGqMopj6jyAINLdYvC3ls74xozI', {action: 'booking_search'}).then(function(token) {
                        console.log(token);
                        localStorage.setItem('responseData', token);
                    });
                });");*/
                /*$selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        console.log(this.responseText);
                        console.log("========================");
                        if (/\{"url":"https:/g.exec( this.responseText )) {
                            localStorage.setItem("responseData", this.responseText);
                            window.stop();
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
                ');*/
                $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        console.log(this);
                        console.log("========================");
                        if (url === "https://bff.latam.com/ws/api/rest/authorization-service/v1/access/token") {
                            localStorage.setItem("url", url);
                            localStorage.setItem("status", this.status);
                            localStorage.setItem("responseData", this.responseText);
                        }
                        if (url === "https://bff.latam.com/ws/api/mybookings/v3/rest/reservation/booking-info") {
                            localStorage.setItem("info_url", url);
                            localStorage.setItem("info_status", this.status);
                            localStorage.setItem("info_responseData", this.responseText);
                            window.stop();
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
                ');
                $btn->click();

                $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Reservation code:')]"), 5);
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                $resp = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
                $url = $selenium->driver->executeScript("return localStorage.getItem('url');");
//
//                if (empty($resp) || stripos($url, '/reservation/booking-info') === false) {
//                    $this->logger->info("load...");
//                    sleep(2);
//                    $resp = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
//                    $url = $selenium->driver->executeScript("return localStorage.getItem('url');");
//                }
//
//                if (empty($resp) || stripos($url, '/reservation/booking-info') === false) {
//                    $this->logger->info("load...");
//                    sleep(3);
//                    $resp = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
//                    $url = $selenium->driver->executeScript("return localStorage.getItem('url');");
//                }
                $this->logger->info("resp: " . $resp);
                $this->logger->info("url: " . $url);
                $status = $selenium->driver->executeScript("return localStorage.getItem('status');");
                $this->logger->info("status: " . $status);

                $resp = $selenium->driver->executeScript("return localStorage.getItem('info_responseData');");
                $this->logger->info("info_responseData: " . $resp);
                $this->logger->info("info_url: " . $selenium->driver->executeScript("return localStorage.getItem('info_url');"));
                $status = $selenium->driver->executeScript("return localStorage.getItem('info_status');");
                $this->logger->info("info_status: " . $status);

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
//                    if (!in_array($cookie['name'], [
//                        'bm_sz',
//                        '_abck',
//                    ])) {
//                        continue;
//                    }
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }

                if (!empty($resp)) {
                    return $resp;
                }
            }
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return null;
    }

    private function sendStaticSensorDataMultiplus($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            "7a74G7m23Vrp0o5c9285061.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:92.0) Gecko/20100101 Firefox/92.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,401600,8986366,1536,871,1536,960,1536,369,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6006,0.533597239266,816104493183,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,1,652,447,0;1,0,0,1,883,883,0;0,0,0,1,1578,1578,0;0,0,0,1,863,1731,0;0,0,0,1,2098,1202,0;0,0,0,1,1257,1257,0;0,0,0,1,770,770,0;0,0,0,1,520,520,0;-1,2,-94,-102,0,0,0,1,652,447,0;1,0,0,1,883,883,0;0,0,0,1,1578,1578,0;0,0,0,1,863,1731,0;0,0,0,1,2098,1202,0;0,0,0,1,1257,1257,0;0,0,0,1,770,770,0;0,0,0,1,520,520,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.pontosmultiplus.com.br/login/?TYPE=33554433&REALMOID=06-00095130-1a0d-1672-b794-10f20a98d0ed&GUID=&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-i4Nurek8vWjAtMJMO3Or1CJ1BltjzwkWicQ%2fXBwextP00uWVmDVgJ28mpyorKDcM&TARGET=-SM-HTTPS%3a%2f%2fwww%2epontosmultiplus%2ecom%2ebr%2fmyaccount%2flogin%2ejsp%3furlOrigin%3dL2hvbWU%3d%26_DARGS%3d%2fcartridges%2fLatamHeader%2fLatamHeader%2ejsp_A%26_DAV%3d%2fhome%26_dynSessConf%3d7564827652428911123%26_ga%3d2%2e96109852%2e1465267298%2e1631874523--1604155782%2e1631874522-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1632208986366,-999999,17460,0,0,2910,0,0,2,0,0,B9E5BEEF84285167E3CAE3C4EF3FF5DA~-1~YAAQEfOPqPAgFgd8AQAAmp08BwY9oaBMccOVud31kexT8sgfmZXLXeFTi1/RWNsKyr1f27ZnH6JsQBZQ2vF+GHkGeW1y/Z2e9K1wisIevZ7Jl4jbJEfXTdvLvlsFfIZu/WiIQATRKkt/rlmCcwBZTf+iuawf8TymVIh1E1RfW802eAZGv+aEp1AIadgtZJ4f4VErsZ9/PbwahrCzUXBrBH/29ac/IVZyS8uXUCCT+zpe4d0TN5GPAkDALOLnIlv5arAk8vNbViiQ1U6Y/xaRsTUArwxuW5r/GWRnyOXKOiIC6Gvjz80aOd9hBk6jeirqEEzgpk0EgdhcRS+44cCh6evRtMiareZccVkdZ/RrPsmwcATqKqmu8Zd9NPTpAkHWd0Ph++ptWhWz~-1~-1~-1,37009,-1,-1,26067385,PiZtE,94638,100,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,242631207-1,2,-94,-118,132682-1,2,-94,-129,-1,2,-94,-121,;9;-1;0",
        ];

        $sensorData2 = [
            "7a74G7m23Vrp0o5c9285061.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:92.0) Gecko/20100101 Firefox/92.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,401600,8986366,1536,871,1536,960,1536,369,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6006,0.845314976422,816104493183,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,1,652,447,0;1,0,0,1,883,883,0;0,0,0,1,1578,1578,0;0,0,0,1,863,1731,0;0,0,0,1,2098,1202,0;0,0,0,1,1257,1257,0;0,0,0,1,770,770,0;0,0,0,1,520,520,0;-1,2,-94,-102,0,0,0,1,652,447,0;1,0,0,1,883,883,0;0,0,0,1,1578,1578,0;0,0,0,1,863,1731,0;0,0,0,1,2098,1202,0;0,0,0,1,1257,1257,0;0,0,0,1,770,770,0;0,0,0,1,520,520,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.pontosmultiplus.com.br/login/?TYPE=33554433&REALMOID=06-00095130-1a0d-1672-b794-10f20a98d0ed&GUID=&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-i4Nurek8vWjAtMJMO3Or1CJ1BltjzwkWicQ%2fXBwextP00uWVmDVgJ28mpyorKDcM&TARGET=-SM-HTTPS%3a%2f%2fwww%2epontosmultiplus%2ecom%2ebr%2fmyaccount%2flogin%2ejsp%3furlOrigin%3dL2hvbWU%3d%26_DARGS%3d%2fcartridges%2fLatamHeader%2fLatamHeader%2ejsp_A%26_DAV%3d%2fhome%26_dynSessConf%3d7564827652428911123%26_ga%3d2%2e96109852%2e1465267298%2e1631874523--1604155782%2e1631874522-1,2,-94,-115,1,32,32,0,0,0,0,542,0,1632208986366,5,17460,0,0,2910,0,0,542,0,0,B9E5BEEF84285167E3CAE3C4EF3FF5DA~-1~YAAQEfOPqPMgFgd8AQAAWaI8BwZ4Fogrf8615LMnYmfCn3vf/Huc5KyouakR0A1IyXA4PmcawzbWKMhxTpKU6xSi7Jmjwu2qXWcuFZIpQYoSFhvohrfOVjTIEFJusCQwUNECu34iwLDo6EUGx94qw+uolp2UkG54rY7Wix04goQPXi7qTmM+Rl3i6bJ4lebP2mPbFsatlsQD6pIWJCVb2UB0sa7xRPTYuAv3UxUEKxCZxZVBr9UftzZ0kyPYstoI6t//e2S325u1OI1oeQiwIxJTd2vYeOTAZmXF0kwtEtlR1GzTSwBT4IYktV7PxhXFQiqjNaXDfGANFZsOwBZZna4XF+OCfvJ/qOYGKcVMg7jVwGUHq/gKrFk8tJvhJbK3L2rMOLhdpiIvKVhT0aKOLPuWf7qQrCk=~-1~||1-cHtChXqRQQ-1-10-1000-2||~-1,40946,820,-211898333,26067385,PiZtE,76216,92,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,200,0,0,200,0,0,200,0,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,242631207-1,2,-94,-118,139514-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) HD Graphics 400,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;11;10;0",
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

    private function seleniumMultiplus($currentUrl, &$browser)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
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
//            $selenium->useCache();
//            $selenium->usePacFile(false);
            $selenium->keepCookies(false);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.latamairlines.com/us/en"); //todo

            $selenium->http->GetURL($currentUrl);
            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'userid' or @id = 'form-input--alias']"), 5);
//            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'form-input--password']"), 0);
//            $button = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'form-button--submit']"), 0);
//
//            if ($login && $pass && $button) {
//                $login->sendKeys($this->AccountFields['Login']);
//                $pass->sendKeys($this->AccountFields['Pass']);
//                $button->click();
//
//                $button = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'submit']"), 10);//todo
//            }

            // save page to logs
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
//                if (!in_array($cookie['name'], [
//                    'bm_sz',
//                    '_abck',
//                ])) {
//                    continue;
//                }
                $browser->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            $result = true;
        } catch (TimeOutException | NoSuchWindowException | UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();
        }

        return $result;
    }
}
