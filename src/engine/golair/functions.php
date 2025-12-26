<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\LuminatiProxyManager\Port;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common;

class TAccountCheckerGolair extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $currentItin = 0;

    private $domain = 'com.br';
    private $headers = [
        'Accept'   => 'application/json, text/plain, */*',
        'Channel'  => 'Web',
        'Language' => 'es-ES',
        'Region'   => 'ARGENTINA',
    ];

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $this->setDomain();
        $arg['SuccessURL'] = "https://www.smiles.{$this->domain}/web/guest/minha-conta";

        return $arg;
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields, $values);
        $fields['Login2']['Options'] = [
            ""          => "Select your country",
            'Argentina' => 'Argentina',
            'Brazil'    => 'Brazil',
        ];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        if (
            isset($this->AccountFields['Login2']) && $this->AccountFields['Login2'] == 'Brazil'
        ) {
            if ($this->attempt == 1) {
                $array = ['us', 'ca', 'uk'];
                $targeting = $array[array_rand($array)];
                $this->setProxyMount();
            } else {
                $array = ['us', 'ca', 'uk'];
                $targeting = $array[array_rand($array)];
                $this->setProxyGoProxies(null, $targeting);
            }
            /*
            $this->http->setRandomUserAgent();
            */
        }

//        if ($this->AccountFields['UserID'] == 2110) {
            $this->logger->debug("testing lpm");
            $this->setLpmProxy((new Port)
                ->setExternalProxy([$this->http->getProxyUrl()])
                ->cacheUrlByRegexp('\.(js|mp4|jpeg|jpg|webp|svg|png|css|woff2|ttf)($|\?)')
                ->setBanUrlContent('^' . preg_quote('https://optimizationguide-pa.googleapis.com/downloads')) // Google Chrome downloads it when opening new tab
            );
//        }
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] == 'Brazil') {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.smiles.{$this->domain}/group/guest/minha-conta?p_p_id=smileswidgetmydataportlet_WAR_smileswidgetmyaccountportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=doWidgetMyData&p_p_cacheability=cacheLevelPage&p_p_col_id=column-2&p_p_col_count=2", [], 20);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if ($response->userInfoHeaderVO->memberVO ?? false) {
                return true;
            }
        } elseif (isset($this->State['accessToken'])) {
            $headers = $this->headers + [
                'Content-Type'  => 'application/json;charset=UTF-8',
                'Authorization' => 'Bearer ' . $this->State['accessToken'],
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL('https://api.smiles.com.br/api/members/tier', $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (isset($response->tierUpgradeInfo)) {
                return true;
            }
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->setDomain();

        if ($this->AccountFields['Login2'] == 'Brazil') {
//            if ($this->attempt > 0) {
            $currentUrl = $this->selenium();

            if (!is_string($currentUrl)) {
                return false;
            }
            $this->logger->debug("[Current URL]: {$currentUrl}");

//            }

            /*$this->http->FilterHTML = false;
            $this->http->GetURL("https://www.smiles.{$this->domain}/login");

            if ($this->attempt == 0 && $this->http->Response['code'] == 403) {
                $this->http->removeCookies();
                $this->selenium();

                $this->http->GetURL("https://www.smiles.{$this->domain}/login");
            }*/

            $clientId = $this->http->FindPreg('/client=(.+?)&/', false, $currentUrl);
            $state = $this->http->FindPreg('/state=(.+?)&/', false, $currentUrl);
            $redirect_uri = $this->http->FindPreg('/redirect_uri=(.+?)&/', false, $currentUrl);
            //$_csrf = $this->http->getCookieByName('_csrf', null, '/usernamepassword/login');

            if (!isset($clientId, $state)) {
                if ($this->http->FindPreg("/iframe id=\"main-iframe\" src=\"\/_Incapsula_Resource/")
                    || $this->http->FindPreg("/script src=\"\/_Incapsula_Resource/")) {
                    $this->markProxyAsInvalid();

                    throw new CheckRetryNeededException(2, 0);
                }

                if (
                    strstr($this->http->Error, 'Network error 28 - Operation timed out after')
                    || strstr($this->http->Error, 'Network error 28 - Connection timed out afterk')
                ) {
                    throw new CheckRetryNeededException(2, 0);
                }

                return $this->checkErrors();
            }

            $captcha = $this->parseReCaptcha('6LcSG6oaAAAAAGooY4PRTvcb63uNDaBPvZ0FUaRk', $currentUrl);

            if ($captcha === false) {
                return false;
            }

            $data = [
                "client_id"      => $clientId,
                "redirect_uri"   => "https://www.smiles.com.br/logincb?dest=",
                "tenant"         => "smiles-prod",
                "response_type"  => "code",
                "scope"          => "openid profile email",
                "audience"       => "https://smiles.api",
                "_csrf"          => $_csrf ?? 'BihmKuoP-ojoIoL1pTd9WQr8hS1Tv7C89bBg',
                "state"          => $state,
                "_intstate"      => "deprecated",
                "username"       => $this->AccountFields['Login'],
                "password"       => $this->AccountFields['Pass'],
                "captcha_smiles" => $captcha,
                "connection"     => "smilesdb",
            ];
            $headers = [
                'Content-Type' => 'application/json',
                'Auth0-Client' => 'eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTQuMyJ9',
                'x-dtpc'       => '6$30068387_356h13vUSWVSPWUGVMSTTRTUGRHTLQPEQAGSOHC-0e5',
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://login.smiles.com.br/usernamepassword/login', json_encode($data), $headers);
            $this->http->RetryCount = 2;

            if (strstr($this->http->Error, 'Network error 28 - Operation timed out after')) {
                throw new CheckRetryNeededException(2, 0);
            }
        } else {
            $this->http->GetURL("https://www.smiles.com.ar/login");

            if ($this->http->Response['code'] != 200) {
                return $this->checkErrors();
            }
            // Logo estaremos de volta!
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "Logo estaremos de volta!")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->http->GetURL("https://ldrelay.smiles.com.ar/sdk/evalx/5ca361f5f90780081712ca46/users/eyJrZXkiOiJodHRwczovL3d3dy5zbWlsZXMuY29tLmFyIn0");
            $response = $this->http->JsonLog();
            $apiUrl = $response->{'api-data'}->value->{'api-url'} ?? null;
            $recaptchaKey = $response->{'api-data'}->value->recaptchaKey ?? null;

            if (!isset($apiUrl) || !isset($recaptchaKey)) {
                return false;
            }

            $captcha = $this->parseReCaptcha($recaptchaKey);
            /*
            $captcha = $this->parseReCaptcha('6LcklGscAAAAANA7t80LWsSFj0xSrLFzVsONvI5k');
            */

            if ($captcha === false) {
                return false;
            }

            $this->http->RetryCount = 0;
            $data = [
                'grant_type'    => 'client_credentials',
                'client_id'     => '418c0e79-51e7-4f6d-9434-58086e6d12b5', //$response->audience,
                'client_secret' => '2675e6f0-b0de-4f23-961a-e93330ecdec5',
                'memberNumber'  => $this->AccountFields['Login'],
                'password'      => $this->AccountFields['Pass'],
                'format'        => 'json',
            ];
            $headers = [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'recaptcha'    => $captcha,
            ];
//            $this->http->PostURL("https://api-blue.smiles.com.ar/api/login", $data, $this->headers + $headers);
//            $this->http->PostURL("https://api-green.smiles.com.ar/api/login", $data, $this->headers + $headers);
            $this->http->PostURL($apiUrl . "/api/login", $data, $this->headers + $headers);
            $this->http->RetryCount = 2;
        }

        return true;
    }

    public function Login()
    {
        if ($this->AccountFields['Login2'] == 'Argentina') {
            return $this->LoginArgentina();
        }

        $this->http->RetryCount = 0;

        if ($this->http->ParseForm("hiddenform")) {
            sleep(rand(3, 9));
            $this->http->PostForm();

            if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode('//iframe[@id = "main-iframe" and contains(@src, "/_Incapsula_Resource")]/@src')) {
                $this->markProxyAsInvalid();
                $this->DebugInfo = "Incapsula";

                throw new CheckRetryNeededException(3, 3);
            }
        }
        $this->http->RetryCount = 2;

        if (strpos($this->http->currentUrl(), "https://www.smiles.{$this->domain}/validar") !== false) {
            $this->captchaReporting($this->recognizer);
            $token = $this->http->getCookieByName("session-token", "www.smiles.com.br", "/", true);

            if (!$token) {
                $this->logger->error("session-token not found");

                return false;
            }

            $this->http->GetURL("https://www.smiles.com.br/web/guest/login?p_p_id=smilesloginportlet_WAR_smilesloginportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=createSessionAuth&token={$token}", [], 80);
            $response = $this->http->JsonLog();

            if (isset($response->status) && $response->status === 500) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return true;
        }

        $response = $this->http->JsonLog();
        $message = $response->description
            ?? $response->message
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: " . $message);
            $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'Usuário não encontrado ou Senha está inválida.')
                || $message == '[1] Invalid user.'
                || $message == '[4] Invalid user (200): user data does not match.'
                || (strstr($message, '[3] Invalid user (452):') && strstr($message, 'Membro com status Merged'))
                || (strstr($message, '[3] Invalid user (495): undefined'))
            ) {
                throw new CheckException("Os dados de acesso não estão corretos", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Ocorreu um erro inesperado')
            ) {
                throw new CheckException("Ocorreu um erro inesperado", ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == "[3] Invalid user (503): {\"message\":\"Service Unavailable\"}"
                || $message == "{\"message\": \"Internal server error\"}"
                || $message == 'ESOCKETTIMEDOUT'
                || $message == 'ETIMEDOUT'
                || $message == 'Client request error: socket hang up'
                || strstr($message, 'Request throttled by auth0-sandbox due to fatal errors caused by previous requests.')
                || $message == 'Invalid response code from the auth0-sandbox: HTTP 502.'
                || $message == 'Request to Webtask got ESOCKETTIMEDOUT'
                || $message == 'Internal Server Error'
                || $message == 'Unauthorized: Erro Get Params'
            ) {
                throw new CheckRetryNeededException(3, 5, self::PROVIDER_ERROR_MSG);
            }
            // Atenção: seu e-mail ainda não está validado!
            if (
                $message == 'Email not verified.'
            ) {
                $this->throwProfileUpdateMessageException();
            }

            if (strstr($message, 'Request unsuccessful. Incapsula incident ID:')) {
                $this->DebugInfo = 'Incapsula';

                throw new CheckRetryNeededException(3, 5);
            }

            if ($message = $this->http->FindPreg('/An error occurred while processing your request/')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if (strstr($this->http->currentUrl(), 'error_description=Access%20denied.%20Captcha%20check%20invalid.')) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
        }

        // retries
        if (
            strstr($this->http->Error, 'Network error 28 - Unexpected EOF')
            || strstr($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT')
            || strstr($this->http->Error, 'Network error 56 - Received HTTP code 406 from proxy after CONNECT')
            || ($this->http->currentUrl() == 'https://www.smiles.com.br/logincb?dest=&error=access_denied&error_description=ETIMEDOUT' && $this->http->Response['code'] == 401)
            || ($this->http->currentUrl() == 'https://www.smiles.com.br/logincb?dest=&error=access_denied&error_description=ESOCKETTIMEDOUT' && $this->http->Response['code'] == 401)
            || (isset($response->message) && $response->message == 'ESOCKETTIMEDOUT' && $this->http->Response['code'] == 401)
        ) {
            throw new CheckRetryNeededException(3, 5);
        }

        if ($message = $this->http->FindSingleNode('//h3[contains(text(), "Looks like something went wrong!")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->processQuestion()) {
            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $data = [
            'state'           => $this->State['state'],
            'code'            => $answer,
            'rememberBrowser' => 'true',
            'action'          => 'default',
        ];

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://login.smiles.com.br/u/mfa-email-challenge?state=' . $this->State['state'], $data, $headers);
        $this->http->RetryCount = 2;

        if (
            $message = $this->http->FindSingleNode('//span[@id="error-element-code"]')
        ) {
            if (strstr($message, "O código inserido está incorreto.")) {
                $this->AskQuestion($this->Question, $message, 'Question');

                return false;
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->Login();
    }

    public function Parse()
    {
        if ($this->AccountFields['Login2'] == 'Argentina') {
            $this->ParseArgentina();

            return;
        }

        if (!strstr($this->http->currentUrl(), "https://www.smiles.{$this->domain}/group/guest/minha-conta?")) {
            $this->http->GetURL("https://www.smiles.{$this->domain}/group/guest/minha-conta?p_p_id=smileswidgetmydataportlet_WAR_smileswidgetmyaccountportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=doWidgetMyData&p_p_cacheability=cacheLevelPage&p_p_col_id=column-2&p_p_col_count=2");
        }
        $response = $this->http->JsonLog();
        $memberInfo = $response->userInfoHeaderVO->memberVO ?? null;
        // Balance - Saldo
        $this->SetBalance($response->userInfoHeaderVO->widgetMyDataVO->availableMiles ?? null);
        // Name
        if (isset($memberInfo->firstName, $memberInfo->lastName)) {
            $this->SetProperty("Name", beautifulName($memberInfo->firstName . " " . $memberInfo->lastName));
        }
        // Número Smiles / Número Smiles
        $this->SetProperty("AccountNumber", $memberInfo->memberNumber ?? null);
        // Membro desde / Cliente desde
        $memberSince = $memberInfo->memberSince ?? null;

        if ($memberSince) {
            $this->SetProperty("MemberSince", date("d/m/Y", strtotime($memberSince)));
        }
        // Category / Categoría
        $this->SetProperty("Category", $response->userInfoHeaderVO->widgetMyCategoryVO->currentTier ?? null);
        // Categoria / Categoría ... Válido até / Válida hasta
        $this->SetProperty("StatusExpiration", $response->userInfoHeaderVO->widgetMyCategoryVO->endDateCard ?? null);
        // Para conquistar a categoria Prata    // refs #10704
        $milesClubTierUpgrade = $response->userInfoHeaderVO->tierUpgradeInfoVO->milesClubTierUpgrade ?? null;

        if ($milesClubTierUpgrade && $milesClubTierUpgrade > 0) {
            $this->SetProperty("MilesToNextLevel", $milesClubTierUpgrade);
        }
        // Para conquistar a categoria Prata    // refs #10704
        // milhas qualificáveis / millas calificables
        $this->SetProperty("QualifyingMiles", $response->userInfoHeaderVO->widgetMyCategoryVO->totalMiles ?? null);
        // trecho / tramos
        $this->SetProperty("Segments", $response->userInfoHeaderVO->widgetMyCategoryVO->totalSegments ?? null);

        // if exist error on the balance page then get info from header (provider bugfix)
        // AccountID: 4644834
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->GetURL("https://www.smiles.{$this->domain}/group/guest/minha-conta?p_p_id=smilesloginportlet_WAR_smilesloginportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=renderLogin&p_p_cacheability=cacheLevelPage");
            // Balance - Você possui ... Milhas
            $this->SetBalance($this->http->FindSingleNode("(//div[contains(@class, 'dropdown-toggle')]//p[@class = 'miles'])[1]", null, false, self::BALANCE_REGEXP_EXTENDED));
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//div[contains(@class, 'dropdown-toggle')]//div[contains(@class, 'name')]/span)[1]")));

            // provider bug fix for broken accounts or failed previous request
            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && $this->http->FindPreg("/LoginPortletController.member/")
            ) {
                // Balance - Saldo
                $this->SetBalance($this->http->FindPreg("/'availableMiles':\s*'([^\']+)/"));
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindPreg("/'name':\s*'([^\']+)/") . " " . $this->http->FindPreg("/'lastName':\s*'([^\']+)/")));
                // Número Smiles / Número Smiles
                $this->SetProperty("AccountNumber", $this->http->FindPreg("/'memberNumber':\s*'([^\']+)/"));
                // Category / Categoría
                $this->SetProperty("Category", $this->http->FindPreg("/'category':\s*'([^\']+)/"));
                // Membro desde / Cliente desde
                $memberSince = $this->http->FindPreg("/'memberSince':\s*'([^\']+)/");

                if ($memberSince) {
                    $this->SetProperty("MemberSince", date("d/m/Y", strtotime($memberSince)));
                }
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $this->http->PostURL("https://www.smiles.{$this->domain}/group/guest/minha-conta?p_p_id=smileswidgetmilestoexpireportlet_WAR_smileswidgetmyaccountportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=doWidgetMilesToExpire&p_p_cacheability=cacheLevelPage&p_p_col_id=column-3&p_p_col_pos=1&p_p_col_count=14", []);
        // Expiration Date
        $response = $this->http->JsonLog();
        $accrualList = $response->milesToExpireVO->widgetMilesToExpireList ?? [];

        foreach ($accrualList as $item) {
            if (isset($item->expiration, $item->points)) {
                $exp = $this->ModifyDateFormat($item->expiration);

                if ($exp = strtotime($exp, false)) {
                    //# Expiration Date
                    $this->SetExpirationDate($exp);
                    //# Miles to expire
                    $this->SetProperty("MilesToExpire", $item->points);

                    break;
                }
            }
        }
    }

    public function ParseItineraries(): array
    {
        if ($this->AccountFields['Login2'] == 'Argentina') {
            return $this->ParseItinerariesArgentina();
        }

        return $this->ParseItinerariesBrazil();
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Locator",
                "Type"     => "string",
                "Size"     => 12,
                "Cols"     => 12,
                "Required" => true,
            ],
            "DepCode" => [
                "Caption"  => "Departure",
                "Type"     => "string",
                "Size"     => 3,
                "Cols"     => 10,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "LastName",
                "Type"     => "string",
                "Size"     => 40,
                "Cols"     => 40,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://b2c.voegol.com.br/minhas-viagens/encontrar-viagem";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        if ($this->attempt == 0) {
            $this->setProxyMount();
        } else {
            $this->setProxyGoProxies();
        }

        $this->http->setRandomUserAgent();

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        /*if (!$this->http->FindSingleNode("//title[normalize-space()='VoeGOL']")) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }*/
        $headers = [
            'Accept'                         => '*/*',
            'Access-Control-Request-Headers' => 'x-aat',
            'Access-Control-Request-Method'  => 'GET',
            'Referer'                        => 'https://b2c.voegol.com.br/',
            'Origin'                         => 'https://b2c.voegol.com.br',
            'Content-Encoding'               => 'gzip, deflate, br',
            'X-AAT'                          => 'FSmZaVOMa1/7DuFYspsOLwifxnjAWdYRGjJRTBRjGa3ubM1rEytti5G1jS/PRNj3UulQk8alJiBDsi36UZ8MBQ==',
        ];
        $this->http->GetURL('https://gol-auth-api.voegol.com.br/api/authentication/create-token', $headers);
        $responseToken = $this->http->JsonLog();

        if (empty($responseToken->response->token)) {
            if (strpos($this->http->Error, 'Network error ') !== false) {
                throw new CheckRetryNeededException(2);
            }

            return null;
        }

        $headers = [
            'Accept'                 => 'application/json',
            'Accept-Encoding'        => 'gzip',
            'Authorization'          => "Bearer {$responseToken->response->token}",
            'Referer'                => 'https://b2c.voegol.com.br/',
            'Origin'                 => 'https://b2c.voegol.com.br',
            'Content-Encoding'       => 'gzip, deflate, br',
        ];
        $params = http_build_query([
            'context'   => 'b2c',
            'flow'      => 'consult',
            'pnr'       => strtoupper($arFields['ConfNo']),
            'origin'    => strtoupper($arFields['DepCode']),
            'lastName'  => strtoupper($arFields['LastName']),
        ]);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://booking-api.voegol.com.br/api/pnrBnpl/pnr-bnpl-validation?{$params}", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (empty($response)) {
            return null;
        }

        if (!empty($response->notifications[0]) && $response->success === false) {
            if ($response->notifications[0] == 'ERROR: Problem occurred while retrievong a pnr. Pnr not existing or verification information not match') {
                return 'Houve um erro. Tente novamente mais tarde.';
            }
        }
        // Retry recaptcha fail
        if (isset($response->error->message) && $response->error->message == 'Error recaptcha: score recaptcha is fail') {
            $captcha = $this->parseReCaptchaV3('6Lcy4iYeAAAAAArHVKXPrTzq_wuhER5Qvg7LhzGe');

            if ($captcha === false) {
                return false;
            }
            $headers = [
                'Accept'        => 'application/json',
                'Authorization' => "Bearer {$responseToken->response->token}",
            ];

            $params = http_build_query([
                'context'   => 'b2c',
                'flow'      => 'MTO',
                'pnr'       => strtoupper($arFields['ConfNo']),
                'origin'    => strtoupper($arFields['DepCode']),
                'lastName'  => strtoupper($arFields['LastName']),
                'recaptcha' => $captcha,
                'canal'     => '1',
            ]);
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://booking-api.voegol.com.br/api/pnrBnpl/pnr-bnpl-validation-v2?{$params}", $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (empty($response)) {
                return null;
            }

            if (!empty($response->notifications[0]) && $response->success === false) {
                if ($response->notifications[0] == 'ERROR: Problem occurred while retrievong a pnr. Pnr not existing or verification information not match') {
                    return 'Houve um erro. Tente novamente mais tarde.';
                }
            }
        }

        $this->parseFlight($response);

        return null;
    }

    private function processQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->FindPreg('/mfa-login-options/', false, $this->http->currentUrl())) {
            return false;
        }

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $this->State['state'] = $this->http->FindPreg('/state=(.*)/', false, $this->http->currentUrl());

        $data = [
            'state'  => $this->State['state'],
            'action' => "email::1",
        ];

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://login.smiles.com.br/u/mfa-login-options?state=' . $this->State['state'], $data, $headers);
        $this->http->RetryCount = 2;

        $question = $this->http->FindSingleNode('//p[@id="aria-description-text"]');
        $email = $this->http->FindSingleNode('//span[@class="ulp-authenticator-selector-text"]');

        if (!$question || !$email) {
            return $this->checkErrors();
        }

        $fullQuestion = $question . ' ' . $email;
        $this->logger->debug('[FULL QUESTION]: ' . $fullQuestion);
        $this->AskQuestion($fullQuestion, null, 'Question');

        return true;
    }

    private function parseFlight($data)
    {
        $this->logger->notice(__METHOD__);
        $recordLocator = $data->response->pnrRetrieveResponse->pnr->reloc ?? null;

        if (empty($recordLocator)) {
            $this->sendNotification("something went wrong, need to check ConfNo // MI");

            return;
        }

        $this->logger->info("Parse Itinerary #{$recordLocator}", ['Header' => 3]);

        $f = $this->itinerariesMaster->add()->flight();
        $f->general()
            ->confirmation($recordLocator);

        $passengers = $data->response->pnrRetrieveResponse->pnr->passengers;

        foreach ($passengers as $passenger) {
            $f->general()->traveller(join(' ', (array) $passenger->passengerDetails));
        }

        $itineraryParts = $data->response->pnrRetrieveResponse->pnr->itinerary->itineraryParts;

        foreach ($itineraryParts as $itineraryPart) {
            foreach ($itineraryPart->segments as $segment) {
                $s = $f->addSegment();
                $s->airline()->name($segment->flight->airlineCode)->number($segment->flight->flightNumber);

                $s->departure()
                    ->code($segment->origin)
                    ->date2($segment->departure);
                $s->arrival()
                    ->code($segment->destination)
                    ->date2($segment->arrival);

                $s->extra()
                    ->aircraft($segment->equipment)
                    ->cabin($segment->cabinClass)
                    ->bookingCode($segment->bookingClass);
                //->duration($segment->duration);
            }
        }
    }

    private function setDomain()
    {
        if ($this->AccountFields['Login2'] == 'Argentina') {
            $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
            $this->domain = 'com.ar';
        } else {
            $this->http->setHttp2(true);
            $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
            /*
            if ($this->attempt == 2) {
                $this->http->SetProxy($this->proxyReCaptchaIt7());
            } else {
                $this->http->SetProxy($this->proxyReCaptcha());
            }
            */
        }
    }

    private function parseReCaptcha($key = '6LcSG6oaAAAAAGooY4PRTvcb63uNDaBPvZ0FUaRk', $currentURL = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $currentURL ?? $this->http->currentUrl(),
            "proxy"   => $this->getCaptchaProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseReCaptchaV3($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }
        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => "https://b2c.voegol.com.br/minhas-viagens/encontrar-viagem",
            "websiteKey"   => $key,
            "minScore"     => 0.3,
            "pageAction"   => "LOGIN",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => 'https://b2c.voegol.com.br/minhas-viagens/encontrar-viagem',
            "proxy"     => $this->http->GetProxy(),
            "version"   => "enterprise",
            "action"    => "LOGIN",
            "min_score" => 0.6,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // 503 Service Temporarily Unavailable
        if ($this->http->FindSingleNode("//h3[contains(text(), 'We are sorry, an error occurred. Please retry after a few minutes.')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '503 Service Temporarily Unavailable')]")
            || (isset($this->http->Response['code']) && $this->http->Response['code'] == 500 && $this->http->FindPreg('#<title></title>#'))
            || (isset($this->http->Response['code']) && $this->http->Response['code'] == 504 && $this->http->FindSingleNode('//title[contains(text(), "ERROR: The request could not be satisfied")]'))
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'ESTAMOS EM MANUTENÇÃO.')]/parent::div")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         *  Smiles
         * Logo estaremos de volta!
         *
         * Nós estamos atualizando o site da Smiles para você. Por favor volte em breve.
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Nós estamos atualizando o site da')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//font[contains(text(), 'An unexpected system error occurred.')]")
            || $this->http->FindSingleNode("//title[contains(text(), '504 Gateway Time-out')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'The request could not be satisfied.')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/You don't have permission to access/")) {
            $this->DebugInfo = $message;
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException();
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Access To Website Blocked")]')) {
            $this->DebugInfo = $message;
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException();
        }

        return false;
    }

    private function LoginArgentina()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if (isset($response->access_token)) {
            $this->State['accessToken'] = $response->access_token;

            return true;
        }

        $message = $response->errorMessage ?? null;

        if ($message) {
            $this->logger->error($message);
            // Usuario y/o contraseña inválidos. Verificá tus datos y probá nuevamente.
            if (
                $message == "Usuario no encontrado, o contraseña inválida."
                || $message == 'DNI o número Smiles inválido. Tu número Smiles consta de 9 (nueve) caracteres numéricos y tu DNI de 8 o 7 (ocho o siete).'
            ) {
                throw new CheckException('Usuario y/o contraseña inválidos. Verificá tus datos y probá nuevamente.', ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == "No fue posible acceder a tu cuenta. Para más información, contacte con el Centro de atención Smiles."
                || $message == 'Hubo un problema al confirmar esta cancelación. Por favor, contactate con nuestro Centro de atención para finalizar este proceso.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // Usted ha excedido el número de intentos para iniciar sesión. Por favor, restablezca la contraseña y vuelva a intentarlo.
            if (
                $message == "Excediste la cantidad de oportunidades para entrar a tu cuenta. Por favor, reestablecé tu contraseña e intentalo de nuevo."
            ) {
                throw new CheckException('Usted ha excedido el número de intentos para iniciar sesión. Por favor, restablezca la contraseña y vuelva a intentarlo.', ACCOUNT_LOCKOUT);
            }

            if (
                $message == "Hubo un error técnico - AuthenticateMemberV1."
            ) {
                throw new CheckRetryNeededException(2, 10, $message);
            }

            return false;
        }// if ($message)

        if ($this->http->FindSingleNode('//h1[
                contains(text(), "502 Bad Gateway")
                or contains(text(), "503 Service Temporarily Unavailable")
            ]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retry
        if (
            ($message = $this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]'))
            && $this->http->Response['code'] == 400
        ) {
            throw new CheckRetryNeededException(2, 10, $message);
        }

        // Estamos actualizando el sitio de Smiles con las últimas novedades. Por favor, volvé a entrar en un ratito.
        $header503 = $this->http->Response['headers']['http/1.1 503 service unavailable'] ?? null; //todo: not verified

        if (
            $this->http->FindPreg('/Back-end server is at capacity/', false, $header503)
            && $this->http->Response['code'] == 503
            && empty($this->http->Response['body'])
        ) {
            throw new CheckException("Estamos actualizando el sitio de Smiles con las últimas novedades. Por favor, volvé a entrar en un ratito.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function ParseArgentina()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        $token = $response->access_token ?? $this->State['accessToken'];
        $data = [
            'memberNumber' => $response->memberNumber ?? $response->tierUpgradeInfo->memberNumber,
            'token'        => $token,
        ];
        $this->headers['Authorization'] = 'Bearer ' . $token;
        $headers = $this->headers + [
            'Content-Type' => 'application/json;charset=UTF-8',
        ];
        $this->http->PostURL('https://api.smiles.com.br/smiles-bus/MemberRESTV1/GetMember', json_encode($data), $headers);
        $response = $this->http->JsonLog();

        // Balance - Saldo
        $this->SetBalance($response->member->availableMiles ?? null);
        // Name
        if (isset($response->member->firstName, $response->member->lastName)) {
            $this->SetProperty("Name", beautifulName("{$response->member->firstName} {$response->member->lastName}"));
        }
        // AccountNumber - Número Smiles
        $this->SetProperty("AccountNumber", $response->member->memberNumber ?? null);
        // MemberSince - Cliente desde
        $memberSince = $response->member->memberSince ?? null;

        if ($memberSince) {
            $this->SetProperty("MemberSince", date("d/m/Y", strtotime($memberSince)));
        }

        // Expiration Date
        if (isset($response->member->milesNextExpirationDate, $response->member->milesToExpire) && $response->member->milesToExpire > 0) {
            if ($exp = $this->http->FindPreg('/(\d{4}-\d+-\d+)T/', false, $response->member->milesNextExpirationDate)) {
                $this->SetExpirationDate(strtotime($exp, false));
                // Miles to expire
                $this->SetProperty("MilesToExpire", number_format($response->member->milesToExpire, 0, ',', '.'));
            }
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://api.smiles.com.br/api/members/tier', $headers + ['Accept' => 'application/json, text/plain, */*']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!$response) {
            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Access To Website Blocked")]')) {
                $this->DebugInfo = $message;

                if ($this->attempt === 2) {
                    $this->sendNotification('check argentina tier // MI');
                }

                throw new CheckRetryNeededException(2, 10);
            }
        }

        // QualifyingMiles - millas calificables*
        $this->SetProperty("QualifyingMiles", number_format($response->tierUpgradeInfo->totalMilesClub ?? null, 0, ',', '.'));
        // Segments - tramos volados*
        $this->SetProperty("Segments", $response->tierUpgradeInfo->totalSegments ?? null);

        // Category - Categoría
        if (isset($response->tierUpgradeInfo->currentTier)) {
            $tier = strtolower($response->tierUpgradeInfo->currentTier);

            if ($tier == 'smiles') {
                $tier = 'Member';
            }

            if ($tier == 'prata') {
                $tier = 'Silver';
            }

            if ($tier == 'ouro') {
                $tier = 'Gold';
            }

            if ($tier == 'diamante') {
                $tier = 'Diamond';
            }
            $this->SetProperty("Category", $tier);
        }
        // MilesToNextLevel
        if (isset($response->tierUpgradeInfo->milesClubTierUpgrade) && $response->tierUpgradeInfo->milesClubTierUpgrade > 0) {
            $this->SetProperty("MilesToNextLevel", number_format($response->tierUpgradeInfo->milesClubTierUpgrade, 0, ',', '.'));
        }

        $this->http->PostURL('https://api.smiles.com.br/smiles-bus/MemberRESTV1/SearchTier', json_encode($data + ['status' => 'ALL']), $headers);
        $response = $this->http->JsonLog();

        if (!$response && $this->ErrorCode === ACCOUNT_CHECKED) {
            $this->sendNotification("Argentina. Exp date not found");
        }

        if (!isset($response->tierList)) {
            return;
        }
        // StatusExpiration - Válida hasta
        $minDate = strtotime('01/01/3018');

        foreach ($response->tierList->tier as $tier) {
            $expStatus = strtotime($tier->endDate, false);

            if ($expStatus && $expStatus < $minDate && $expStatus > strtotime('now')) {
                $this->SetProperty("StatusExpiration", date('d/m/Y', $expStatus));

                break;
            }
        }
    }

    private function xpathQuery(string $query, ?DomNode $context = null): DomNodeList
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $context);
        $this->logger->info("found {$res->length} nodes: {$query}");

        return $res;
    }

    private function isConnectionProblem(): bool
    {
        $this->logger->notice(__METHOD__);
        $isErrorCode = in_array($this->http->Response['code'], [500, 503, 502, 504]);
        $isErrorMessage = (
            $this->http->FindPreg('/"message"\s*:\s*null/')
            ?: $this->http->FindPreg('/"message"\s*:\s*"Internal server error"/')
            ?: $this->http->FindPreg('/"message"\s*:\s*"Ocorreu erro técnico"/')
            ?: $this->http->FindPreg('/"errorMessage"\s*:\s*"Ocorreu erro técnico\."/')
            ?: $this->http->FindPreg('/"message"\s*:\s*"Endpoint request timed out"/')
        );

        if ($isErrorCode && $isErrorMessage) {
            return true;
        }
        $isTimeout = (
            $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)
            ?: $this->http->FindPreg('/Connection timed out after/', false, $this->http->Error)
        );

        if ($isTimeout) {
            return true;
        }
        $isNetworkError = (
            $this->http->FindPreg('/Network error 0/', false, $this->http->Error)
            ?: $this->http->FindPreg('/HTTP Code 401/', false, $this->http->Error)
            ?: $this->http->FindPreg('/Network error 35 - OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection/', false, $this->http->Error)
            ?: $this->http->FindPreg('/Network error 56 - Received HTTP code 503 from proxy after CONNECT/', false, $this->http->Error)
            ?: $this->http->FindPreg('/Network error 56 - Proxy CONNECT aborted/', false, $this->http->Error)
            ?: $this->http->FindPreg('/Network error 7 - Failed to connect to \d+\.\d+\.\d+\.\d+ port \d+: Connection refused/', false, $this->http->Error)
            ?: (empty($this->http->Response['body']))
        );

        if ($isNetworkError) {
            return true;
        }

        return false;
    }

    private function ParseItinerariesBrazil(): array
    {
        $this->logger->notice(__METHOD__);
        $this->increaseTimeLimit();
        $this->http->GetURL('https://www.smiles.com.br/group/guest/minha-conta');
//        $this->incapsula();
        $token = $this->http->FindPreg("/MyFlightsController.token = '(\w+)';/");

        if (empty($token)) {
            $token = $this->http->FindPreg("/EasyTravelController.token = '(\w+)';/");
        }

        if (empty($token)) {
            $this->http->GetURL('https://www.smiles.com.br/group/guest/minha-conta');
            $token = $this->http->FindPreg("/MyFlightsController.token = '(\w+)';/");

            if (empty($token)) {
                $token = $this->http->FindPreg("/EasyTravelController.token = '(\w+)';/");
            }

            if (empty($token)) {
                throw new CheckRetryNeededException(3, 1);

                return [];
            }
        }
        $headers = [
            'Accept'          => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Authorization'   => "Bearer {$token}",
            'Channel'         => 'Web',
            'Referer'         => 'https://www.smiles.com.br/',
            'Origin'          => 'https://www.smiles.com.br',
        ];

        $this->logger->info('Parse Future', ['Header' => 3]);
        $futureFlights = $this->getFlightsBrazil('https://member-flight-blue.smiles.com.br/member/flights?flightType=future&limit=8&offset=0', $headers);

        if ($futureFlights === []) {
            $futureFlights = $this->getFlightsBrazil('https://member-flight-green.smiles.com.br/member/flights?flightType=future&limit=8&offset=0', $headers);
        }

        if ($futureFlights === [] && !$this->ParsePastIts) {
            return $this->noItinerariesArr();
        }

        if (is_array($futureFlights)) {
            $this->logger->debug(sprintf('Total %s future reservations found', count($futureFlights)));

            if (count($futureFlights) > 20) {
                $this->sendNotification('check futureFlights > 20 // MI');
            }

            foreach ($futureFlights as $key => $item) {
                $this->increaseTimeLimit();
                $this->ParseItineraryArgentina($item);
            }
        }

        if (!$this->ParsePastIts) {
            return [];
        }

        $this->logger->info('Parse Past', ['Header' => 3]);
        $pastFlights = $this->getFlightsBrazil('https://member-flight-blue.smiles.com.br/member/flights?flightType=past&limit=20&offset=0', $headers);

        if ($futureFlights === [] && $pastFlights === [] && ($futureFlights === [] || $this->http->FindPreg('/"errorMessage":"Ocorreu erro técnico."/u'))) {
            return $this->noItinerariesArr();
        }

        if (is_array($pastFlights)) {
            $this->logger->debug(sprintf('Total %s past reservations found', count($pastFlights)));

            foreach ($pastFlights as $item) {
                $this->ParseItineraryArgentina($item);
            }
        }

        return [];
    }

    private function incapsula()
    {
        $this->logger->notice(__METHOD__);
        $incapsula = $this->http->FindSingleNode("//script[contains(@src, '/_Incapsula_Resource?')]/@src");

        if (!$incapsula) {
            return false;
        }
//        $this->sendNotification("check incapsula // ZM");
        // get cookies from curl
        $allCookies = array_merge($this->http->GetCookies(".smiles.com.br"), $this->http->GetCookies(".smiles.com.br", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("www.smiles.com.br"), $this->http->GetCookies("www.smiles.com.br", "/", true));

        /** @var TAccountCheckerOman $selenium */
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.smiles.{$this->domain}/home?p_p_id=smilesloginportlet_WAR_smilesloginportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=renderLogin&p_p_cacheability=cacheLevelPage");

            $this->logger->debug("set cookies...");

            foreach ($allCookies as $key => $value) {
                $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".smiles.com.br"]);
            }

            $selenium->http->GetURL("https://www.smiles.com.br/group/guest/minha-conta");
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strpos($e->getMessage(), 'timeout: Timed out receiving message from renderer') !== false) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function getFlightsBrazil(string $url, array $headers): ?array
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL($url, $headers, 20);
        $response = $this->http->JsonLog(null, 3);

        if ($this->isConnectionProblem()) {
            $this->logger->error('Retrying to get flights: connection problem');
            $this->http->SetProxy($this->proxyReCaptcha());
            sleep(5);
            $url = preg_replace('/\bblue\b/', 'green', $url);
            $this->http->GetURL($url, $headers, 20);
            $response = $this->http->JsonLog(null, 0);

            if ($this->isConnectionProblem()) {
                $this->logger->error('Failed to get flights: connection problem');

                return null;
            }
        }
        $this->http->RetryCount = 2;

        if (!isset($response->flightList) || !is_array($response->flightList)) {
            $this->sendNotification('check getFlightsBrazil // MI');
        }

        return $response->flightList ?? null;
    }

    private function ParseItinerariesArgentina(): array
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->Properties['AccountNumber'])) {
            return [];
        }
        $this->http->GetURL("https://api.smiles.com.br/api/v2/members/flights?memberNumber={$this->Properties['AccountNumber']}&getFutureFlights=true&getPastFlights=true&getLoanFlights=true&getReservationFlights=true", $this->headers);
        $response = $this->http->JsonLog(null, 2);

        if (!empty($response->reservationFlightList)) {
            $this->sendNotification('golair - refs #17986. reservationFlightList itineraries were found // MI');
        }
        $notUpcoming = $this->http->FindPreg('/,"futureFlightList":\[\],/') && $this->http->FindPreg('/,"loanFlightList":\[\],/');

        if (!empty($response->futureFlightList)) {
            $this->logger->debug("Total " . count($response->futureFlightList) . " future reservations found");

            foreach ($response->futureFlightList as $item) {
                $this->ParseItineraryArgentina($item);
            }
        }

        if (!empty($response->loanFlightList)) {
            $this->logger->debug("Total " . count($response->loanFlightList) . " loan reservations found");

            foreach ($response->loanFlightList as $item) {
                $this->ParseItineraryArgentina($item);
            }
        }

        if ($this->ParsePastIts) {
            $this->logger->info("Past Itineraries", ['Header' => 2]);

            if (!empty($response->pastFlightList)) {
                $this->logger->debug("Total " . count($response->pastFlightList) . " past reservations found");

                foreach ($response->pastFlightList as $item) {
                    $this->ParseItineraryArgentina($item);
                }
            } elseif ($notUpcoming && $this->http->FindPreg('/,"pastFlightList":\[\],/')) {
                return $this->noItinerariesArr();
            }
        } else {
            if ($notUpcoming) {
                return $this->noItinerariesArr();
            }
        }

        return [];
    }

    private function ParseItineraryArgentina($item): void
    {
        $this->logger->notice(__METHOD__);

        if ($this->currentItin == 18) {
            $this->logger->notice('increaseTimeLimit 300');
            $this->increaseTimeLimit(300);
        }

        if (empty($item->flight->chosenFlightSegmentList)) {
            return;
        }
        $flight = $this->itinerariesMaster->createFlight();

        if (isset($item->bookingStatus)) {
            $bookingStatus = $item->bookingStatus;

            if (in_array($bookingStatus, ['CONFIRMED', 'SEGMENT_CONFIRMED_AFTER_SCHEDULE_CHANGE', 'UNABLE_FLIGHT_DOES_NOT_OPERATE'])) {
                $flight->setStatus('Confirmed');
            } elseif (in_array($bookingStatus, ['CANCELLED'])) {
                $flight->setStatus('Cancelled');
                $flight->setCancelled(true);
            }
        }

        $conf = $ticketNumbers = [];
        $segments = $item->flight->chosenFlightSegmentList;
        $this->logger->debug("Total " . count($segments) . " segments found");

        foreach ($segments as $segment) {
            // Travellers ticketNumber
            if (isset($segment->passengerList)) {
                $ticketNumbers = array_merge($ticketNumbers, array_column($segment->passengerList, 'ticketNumber'));
            }
            $conf[] = $segment->recordLocator;
            $this->logger->debug("Total " . count($segment->chosenFlight->legList) . " stops found");

            foreach ($segment->chosenFlight->legList as $leg) {
                if (isset($leg->flightNumber) && $leg->flightNumber == "") {
                    $this->logger->notice('Skip segment: not flightNumber');

                    continue;
                }
                $seg = $flight->addSegment();
                // AirlineName
                $seg->setAirlineName($leg->marketingAirline->code ?? null);
                $seg->setFlightNumber($leg->flightNumber);

                if (isset($leg->operationAirline->code)) {
                    $seg->setOperatedBy($leg->operationAirline->code);
                }

                if (isset($leg->equipment)) {
                    $seg->setAircraft($leg->equipment, true);
                }

                if (isset($leg->cabin)) {
                    $seg->setCabin(beautifulName($leg->cabin));
                }

                // Departure
                $seg->setDepCode($leg->departure->airport->code);

                if (isset($leg->arrival->departure->name, $leg->arrival->departure->city)) {
                    $seg->setDepName($leg->departure->airport->name . ', ' . $leg->departure->airport->city);
                }

                $seg->setDepDate(strtotime(str_replace('T', ' ', $leg->departure->date), false));
                // Arrival
                $seg->setArrCode($leg->arrival->airport->code);

                if (isset($leg->arrival->airport->name, $leg->arrival->airport->city)) {
                    $seg->setArrName($leg->arrival->airport->name . ', ' . $leg->arrival->airport->city);
                }

                if (isset($leg->departure->date) && !isset($leg->arrival->date)) {
                    $seg->setNoArrDate(true);
                } else {
                    $seg->setArrDate(strtotime(str_replace('T', ' ', $leg->arrival->date), false));
                }
            }

            if (isset($seg) && count($segment->chosenFlight->legList) == 1) {
                // Duration
                if (isset($segment->chosenFlight->duration->hours, $segment->chosenFlight->duration->minutes)) {
                    $duration = $segment->chosenFlight->duration->hours . 'h' . $segment->chosenFlight->duration->minutes;
                } elseif (!isset($segment->chosenFlight->duration->hours) && isset($segment->chosenFlight->duration->minutes)) {
                    $duration = $segment->chosenFlight->duration->minutes . 'm';
                }

                if (isset($duration)) {
                    $seg->setDuration($duration);
                }
            }
        }

        if (empty($flight->getSegments())) {
            $this->logger->error('Skip it: not flightNumber');
            $this->itinerariesMaster->removeItinerary($flight);

            return;
        }
        // TicketNumbers
        $flight->setTicketNumbers(array_unique($ticketNumbers), false);
        // Travellers
        $travellers = [];

        if (isset($item->flight->passengerList)) {
            foreach ($item->flight->passengerList as $traveller) {
                if (isset($traveller->firstName)) {
                    $travellers[] = beautifulName("{$traveller->firstName} {$traveller->lastName}");
                }
            }
        }

        if ($travellers) {
            $flight->setTravellers($travellers);
        }
        // ConfirmationNumber
        if (!empty($conf)) {
            $conf = array_unique($conf);
            $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$conf[0]}", ['Header' => 3]);
            $this->currentItin++;

            foreach ($conf as $locator) {
                $flight->addConfirmationNumber($locator, 'Localizador');
            }
        }

        if ($this->AccountFields['Login2'] === 'Brazil') {
            $this->parsePriceBrazil($flight);
        } elseif ($this->AccountFields['Login2'] === 'Argentina') {
            $moneyCost = $item->moneyCost ?? null;

            if ($moneyCost) {
                $moneyCost = (float) str_replace(" ", '', $moneyCost);

                if ($moneyCost) {
                    $flight->price()->total($moneyCost);
                    $flight->price()->currency('ARS');
                }
            }
            $flight->price()->spentAwards($item->loanedMiles ?? null, false, true);
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($flight->toArray(), true), ['pre' => true]);
    }

    private function parsePriceBrazil(Common\Flight $flight): bool
    {
        $this->logger->notice(__METHOD__);
        $confNumbers = $flight->getConfirmationNumbers();
        $conf = $confNumbers[0][0] ?? null;

        if (!$conf) {
            return false;
        }

        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $http2->RetryCount = 0;
        $this->increaseTimeLimit();
        $http2->GetURL("https://www.smiles.{$this->domain}/group/guest/minha-conta/meus-voos?p_p_id=smilesmyflightsportlet_WAR_smilesbookingportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=detailPayment&p_p_cacheability=cacheLevelPage&p_p_col_id=column-3&p_p_col_count=1&_smilesmyflightsportlet_WAR_smilesbookingportlet_currentURL=%2Fgroup%2Fguest%2Fminha-conta%2Fmeus-voos%3FurlCallback%3D%2Fgroup%2Fguest%2Fminha-conta%2Fmeus-voos&_smilesmyflightsportlet_WAR_smilesbookingportlet_recordLocator={$conf}", [], 20);
        $http2->RetryCount = 2;

        if ($this->http->Response['code'] !== 200) {
            $this->sendNotification('check price request // MI');

            return false;
        }

        $totalStr = $http2->FindSingleNode('//td[contains(text(), "TOTAL") or contains(text(), "Total")]/ancestor::tr[1]');

        if ($totalStr) {
            // spent awards
            $miles = $http2->FindPreg('/(Mil.as\s*[\d.,]+)/', false, $totalStr);
            $flight->price()->spentAwards($miles, false, true);
            // total
            $total = $http2->FindPreg('/([\d.,]+)\s*$/', false, $totalStr) ?: '';
            $flight->price()->total(PriceHelper::cost($total, '.', ','), false, true);
            // currency
            $currency = $http2->FindPreg('/\s+([^\s]+)\s+[\d.,]+\s*$/', false, $totalStr) ?: '';
            $flight->price()->currency($this->currency($currency), false, true);
        }
        // tax
        $taxStr = $http2->FindSingleNode('//td[contains(text(), "Taxa de Embarque") or contains(text(), "Tasas")]/ancestor::tr[1]/td[3]');

        if ($taxStr) {
            $tax = $http2->FindPreg('/([\d.,]+)\s*$/', false, $taxStr) ?: '';

            if ($tax) {
                $flight->price()->tax(PriceHelper::cost($tax, '.', ','), false, true);
            }
        }
        // cost
        $costStr = $http2->FindSingleNode('//td[contains(text(), "Bilhetes")]/ancestor::tr[1]/td[3]');

        if ($costStr) {
            $cost = $http2->FindPreg('/([\d.,]+)\s*$/', false, $costStr);
            $flight->price()->cost(PriceHelper::cost($cost, '.', ','), false, true);
        }

        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        /** @var TAccountCheckerIchotelsgroup $selenium */
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_100);

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.smiles.com.br/group/guest/minha-conta?p_p_id=smileswidgetmydataportlet_WAR_smileswidgetmyaccountportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=doWidgetMyData&p_p_cacheability=cacheLevelPage&p_p_col_id=column-2&p_p_col_count=2");
            } catch (
                ScriptTimeoutException
                | TimeOutException
                | Facebook\WebDriver\Exception\TimeoutException
                | UnexpectedJavascriptException $e
            ) {
                $this->logger->error("TimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            } catch (WebDriverCurlException $e) {
                $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $retry = true;
            }

            $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'identifier'] | //iframe[contains(text(), \"Request unsuccessful. Incapsula incident ID\")]"));

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'identifier']"), 0);
            $this->savePageToLogs($selenium);

            if (empty($login)) {
                $this->logger->error('something went wrong');
                $this->savePageToLogs($selenium);

                if ($this->http->FindSingleNode('//iframe[contains(text(), "Request unsuccessful. Incapsula incident ID")]')) {
                    $this->logger->notice('retry');
                    $retry = true;
                }

                return $this->checkErrors();
            }
            $login->sendKeys($this->AccountFields['Login']);
            sleep(1);

            $main = $selenium->waitForElement(WebDriverBy::xpath('//main/div[@class="main-content"]'), 3);

            if ($main) {
                $main->click();
            }

            sleep(5);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            return $selenium->http->currentUrl();
        } catch (
            UnknownServerException
            | NoSuchWindowException
            | NoSuchDriverException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\WebDriverException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            $selenium->http->cleanup(); //todo:

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 7);
            }
        }

        return true;
    }
}
