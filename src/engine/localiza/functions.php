<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerLocaliza extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    /**
     * @var string[]
     */
    private array $headers = [
        "Accept"          => "application/json, text/plain, */*",
        "Accept-Language" => "pt-br",
        "Accept-Encoding" => "gzip, deflate, br, zstd",
        "Client_id"       => "2ff8a437-2e1e-30dc-bc88-5589f7dda98a",
        "Content-Type"    => "application/json",
        "Origin"          => "https://www.localiza.com",
        "x-platform-id"   => "desktop",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.localiza.com/brasil/pt-br/fidelidade/meus-pontos");
        $data = json_encode([
            'deviceId' => '59177add-ccd9-4bdf-88b3-0a674c697979',
            'deviceName' => '59177add-ccd9-4bdf-88b3-0a674c697979',
            'redirectUri' => 'https://www.localiza.com/autenticacao',
        ]);
        $this->http->PostURL("https://canaisdigitais-api.localiza.com/al-canaisdigitais-site/v1/OAuth/grant-code", $data, $this->headers);
        $response = $this->http->JsonLog();
        if (!isset($response->authUrl))
            return false;

        $this->http->GetURL($response->authUrl);
        $authrequest = $this->http->FindPreg("/Authrequest=([^&]+)/ims", false, $this->http->currentUrl());

        if (!$authrequest) {
            return $this->checkErrors();
        }

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "Login"      => $this->AccountFields['Login'],
            "Password"   => $this->AccountFields['Pass'],
            "RememberMe" => "true",
        ];
        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Language" => "en",
            "Accept-Encoding" => "gzip, deflate, br",
            "authrequest"     => $authrequest,
            "recaptchatoken"  => $captcha,
            "Origin"          => "https://id.localiza.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://api-labs.localiza.com/uniid-uniid-login-backend-netcore/api/login/password', $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->data->userInfo->codePurpose) && $response->data->userInfo->codePurpose == 'RESETTINGPASSWORDANDLOGIN') {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }
        elseif (isset($response->data->finalRedirectUri)) {
            $this->captchaReporting($this->recognizer);
            // https://www.localiza.com/autenticacao?code=9B19A86F8B0450A06379641CA4DB3A066FDA40DA23AA860CEB2B145D520A90DE&state=c827e690-648d-42ea-bff9-a82811cc3c41
            $this->http->GetURL($response->data->finalRedirectUri);
            $data = [
                "code"        => $this->http->FindPreg("/code=([^&]+)/", false, $response->data->finalRedirectUri),
                "sessionId"   => $this->http->FindPreg("/state=([^&]+)/", false, $response->data->finalRedirectUri),
                "redirectUri" => "https://www.localiza.com/autenticacao",
            ];
            $this->headers += [
                "x-dispositivo-id"   => "59177add-ccd9-4bdf-88b3-0a674c697979",
                "x-dispositivo-nome" => "59177add-ccd9-4bdf-88b3-0a674c697979",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://canaisdigitais-api.localiza.com/al-canaisdigitais-site/v1/OAuth/access-token", json_encode($data), $this->headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (isset($response->accessToken)) {
                $this->State['Authorization'] = "Bearer {$response->accessToken}";
                $this->headers['Authorization'] = "Bearer {$response->accessToken}";

                return $this->loginSuccessful();
            }

            return false;
        }

        $message = $response->data->result ?? null;

        if ($message) {
            $this->captchaReporting($this->recognizer);
            $this->logger->error("[Error]: {$message}");

            if (in_array($message, [
                3,
                4,
            ])) {
                throw new CheckException("Invalid user and/or password!", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 7) {
                throw new CheckException("Há uma inconsistência nos seus dados. Entre em contato conosco. Código: UIF0275", ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 9) {
                throw new CheckException("You already have a registration with us, but this is your first digital access. You should activate your registration.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 16) {
                throw new CheckException("No momento, o seu perfil não foi aprovado para alugar com a gente.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 17) {
                $this->throwProfileUpdateMessageException();
            }

            if (in_array($message, [
                11,
                12,
            ])) {
                throw new CheckException("There is an inconsistency in your data. Contact us. Code: UIF0278", ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }// if (isset($message))

        $meesage = $response->ModelErrors[0]->Errors[0]->DefaultMessage
            ?? $response->Data
            ?? null
        ;

        if ($meesage) {
            $this->captchaReporting($this->recognizer);
            $this->logger->error("[ModelErrors -> Error]: {$message}");

            if (
                strstr($meesage, 'Score calculado [0.1] está abaixo do limite configurado [0.3]')
                || strstr($meesage, 'Score calculado [0.2] está abaixo do limite configurado [0.3]')
                || strstr($meesage, 'Score calculado [0] está abaixo do limite configurado [0.3]')
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            if ($meesage == "CreateAssessmentAsync falhou por motivo de [Unspecified]") {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $meesage;

            return false;
        }

        if ($this->http->Response['code'] == 504 && $this->http->FindPreg("/^stream timeout$/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['body'] == '{"success":true,"modelErrors":[],"data":{"result":0}}') {
            throw new CheckException("System error has occurred. UIF0272", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg('/^(.+?)\s+\*\*\*/', false, $response->nome)));
        // Cartão fidelidade número
        $this->SetProperty("CardNumber", $response->clienteId ?? null);

        $this->http->GetURL("https://canaisdigitais-api.localiza.com/al-canaisdigitais-site/v1/Fidelidade/meus-dados", $this->headers);
        $response = $this->http->JsonLog();
        // Balance - Pontos disponíveis
        $this->SetBalance($response->pontuacao->disponivel ?? null);
        // Status
        $this->SetProperty("Status", $response->categoria->nome ?? null);
        // Loyalty Points
        $this->SetProperty("LoyaltyPoints", round($response->pontuacao->categorizavel ?? null));
        // Loyalty Points
        $this->SetProperty("LoyaltyPointsToNextLevel", round($response->proximaCategoria->pontosParaAtingir ?? null));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $key = "6LeM9LYnAAAAAJGl2kiP5pEPM1eAhKeGGrs_SIeg";

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV2TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "isInvisible" => true
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            "Authorization"   => $this->State['Authorization'],
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://canaisdigitais-api.localiza.com/al-canaisdigitais-site/v1/Clientes/resumo-dados-pessoais", $this->headers + $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->clienteId)) {
            $this->headers['Authorization'] = $this->State['Authorization'];

            return true;
        }

        if (
            isset($response->codigo, $response->mensagensUsuario[0])
            && $response->mensagensUsuario[0] == "Valide sua conta abrindo uma solicitação no Portal da Privacidade."
        ) {
            throw new CheckException($response->mensagensUsuario[0], ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
