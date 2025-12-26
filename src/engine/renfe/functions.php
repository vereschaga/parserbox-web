<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerRenfe extends TAccountChecker
{
    use ProxyList;
    use DateTimeTools;
    use OtcHelper;

    /**
     * @var CaptchaRecognizer
     */
    protected $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://venta.renfe.com/vol/masRenfe.do", [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//p[@id='cardTarjetaPuntos']", null, false)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://venta.renfe.com/vol/loginCEX.do?fallo=No&controller=plusRenfeController&to=masRenfe.do");
        /*
        if (strstr($this->http->Response["errorMessage"], '52 - Empty reply from server'))
            throw new CheckRetryNeededException(3, 10);
        */
        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }

        // $captcha3 = $this->parseReCaptcha('6Leb45wUAAAAAGdzbcxr6k9Nc0pVCKz75Jc-HyZJ', true);
        $captcha2 = $this->parseReCaptcha('6LedkpUUAAAAAP-sCphwwZaZOPElfnkiJUY8xc1C', false);
        // c0-e4=string:{$captcha3}
        if ($captcha2 !== false) {
            $data
                = "callCount=1
windowName=
c0-scriptName=userManager
c0-methodName=login
c0-id=0
c0-e1=string:{$this->AccountFields['Login']}
c0-e2=string:" . urlencode($this->AccountFields['Pass']) . "
c0-e3=boolean:false
c0-e5=string:{$captcha2}
c0-e4=Object_Object:{v2:reference:c0-e5}
c0-e6=null:null
c0-e7=string:f75d29636b67c463ddae2a3f8f5669ff
c0-param0=Object_Object:{userId:reference:c0-e1, password:reference:c0-e2, consolidation:reference:c0-e3, captchaResponses:reference:c0-e4, cdgoVerification:reference:c0-e6, idDevice:reference:c0-e7}
c0-param1=string:P
batchId=7
instanceId=0
page=%2Fvol%2FloginCEX.do%3Ffallo%3DNo%26controller%3DplusRenfeController%26to%3DmasRenfe.do
scriptSessionId=XrBfPrTT6oFdz7GBZ8x0n500\$Qo/Ny60\$Qo-wglbPdYj8";
            $this->http->PostURL('https://venta.renfe.com/vol/dwr/call/plaincall/userManager.login.dwr', $data, [
                'Accept'       => '*/*',
                'Content-Type' => 'text/plain',
            ]);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Servicio temporalmente no disponible. Vuelva a intentarlo pasados unos  minutos.
        if ($message = $this->http->FindSingleNode("//div[contains(text(),'Servicio temporalmente no disponible. Vuelva a intentarlo pasados unos  minutos.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(text(),'Servicio temporalmente no disponible. Vuelva a intentarlo pasados unos')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseQuestion($response)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($response->email)) {
            return true;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $this->State['captchaResponses'] = $response->captchaResponses;

        $question = "Hemos enviado un código de verificación a {$response->email}";
        $this->AskQuestion($question, null, 'Question');

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->http->RetryCount = 0;
        $data =
            "callCount=1
windowName=
c0-scriptName=userManager
c0-methodName=login
c0-id=0
c0-e1=string:{$this->AccountFields['Login']}
c0-e2=string:" . urlencode($this->AccountFields['Pass']) . "
c0-e3=string:$answer
c0-e4=string:b9d68f70f6e469d2d3d8170b404be222
c0-param0=Object_Object:{userId:reference:c0-e1, password:reference:c0-e2, cdgoVerification:reference:c0-e3, idDevice:reference:c0-e4}
c0-param1=string:P
batchId=3
instanceId=0
page=%2Fvol%2FloginCEX.do%3Ffallo%3DNo%26controller%3DplusRenfeController%26to%3DmasRenfe.do
scriptSessionId=uktLhtsCoYZnKSJygVLwA0GiMQo/J\$XNMQo-C1d4rELFq";
        $this->http->PostURL('https://venta.renfe.com/vol/dwr/call/plaincall/userManager.login.dwr', $data, [
            'Accept'       => '*/*',
            'Content-Type' => 'text/plain',
        ]);
        $this->http->RetryCount = 2;

        $response = $this->http->FindPreg('/r\.handleCallback\("\d+","\d+",(\{.+?\})\);/');
        $response = $this->http->JsonLog(preg_replace("/(\w+):/", '"$1":', $response));

        if (isset($response->responseCode) && $response->responseCode == 'KO_VALIDA') {
            $this->AskQuestion($this->Question, "El código que ha introducido no es válido. Inténtelo de nuevo.", $step);

            return false;
        }

        if (isset($response->responseCode) && !in_array($response->responseCode, [
                'OK',
                'DOC',
                'CONVERSION',
        ])) {
            return false;
        }

        $this->http->GetURL('https://venta.renfe.com/vol/masRenfe.do');

        return true;
    }

    public function Login()
    {
        $response = $this->http->FindPreg('/r\.handleCallback\("\d+","\d+",(\{.+?\})\);/');
        $response = $this->http->JsonLog(preg_replace("/(\w+):/", '"$1":', $response));

        if (isset($response->responseCode) && $response->responseCode == 'MAIL_COD_VERIFICACION') {
            return $this->parseQuestion($response);
        }

        // Para iniciar sesión, debes indicar el email y contraseña con la que habitualmente accedes a renfe.com o si tienes acceso a la Zona Privada +Renfe, también puedes indicar tu número de tarjeta y la contraseña.
        if ($message = $this->http->FindPreg('/errorMessages:\["(Para iniciar sesi.+?debes indicar el email y contrase.+?)"\],/')
            /*|| $this->http->FindPreg('/,landlinePhone:\{countryCode:null,nationalNumber:null\},/')*/) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Para iniciar sesión, debes indicar el email y contraseña con la que habitualmente accedes a renfe.com o si tienes tarjeta +Renfe, también puedes indicar tu número de tarjeta y la contraseña.
        if ($message = $this->http->FindPreg('/errorMessages:\["(Para iniciar sesi.+?o si tienes tarjeta.+?)"\],/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // No se ha podido completar el inicio de sesi&oacute;n, por favor vuelve a intentarlo de nuevo
        if ($message = $this->http->FindPreg('/errorMessages:\["(No se ha podido completar el inicio de sesi.+?por favor vuelve a intentarlo de nuevo)"\],/')) {
            throw new CheckRetryNeededException(2, 7);
        }

        if ($message = $this->http->FindPreg('/errorMessages:\["(Captcha incorrecto)"\],/')) {
            throw new CheckRetryNeededException(2, 7);
        }

        $isProfileUpdate = $this->http->FindPreg('/(,landlinePhone:\{.+?responseCode:"EMAIL-DOC-MOBILE",.+?,userId:"\d+",|,responseCode:"DOC")/');
        $this->http->GetURL('https://venta.renfe.com/vol/masRenfe.do');

        if ($this->http->FindSingleNode("//input[@id='numTarjetaJovenHidden']/@value")) {
            if (isset($this->recognizer)) {
                $this->recognizer->reportGoodCaptcha();
            }

            return true;
        } elseif (
            $isProfileUpdate
            && (
                $this->http->currentUrl() == 'https://venta.renfe.com/vol/loginCEX.do?fallo=No&controller=plusRenfeController&to=masRenfe.do'
                || $this->http->currentUrl() == 'https://venta.renfe.com/vol/loginCEX.do?to=masRenfe.do&fallo=No&controller=plusRenfeController&mrcaInfPost=true'
                || $this->http->currentUrl() == 'https://venta.renfe.com/vol/loginCEX.do?to=masRenfe.do&activoNuevosBeneficios=false&fallo=No&controller=plusRenfeController&mrcaInfPost=true'
                || ($this->http->currentUrl() == 'https://venta.renfe.com/vol/masRenfe.do' && $this->http->Response['code'] == 500)
            )
        ) {
            $this->throwProfileUpdateMessageException();
        }
        // not a member
        if ($this->http->FindSingleNode('//button[contains(text(), "Unirme al Programa Más Renfe")]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Ha ocurrido un error al acceder la ")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "En estos momentos no podemos atenderle")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Saldo de Puntos
        $this->SetBalance($this->http->FindSingleNode("//p[@id='cardTarjetaPuntos']"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//input[@id='nombre_tarjeta']/@value")));
        // Mi tarjeta +Renfe
        $this->SetProperty('Status', $this->http->FindSingleNode("//div[contains(@class,'panel-side-content')]//h2[@class='title title-sm aColor1 uppercase']/span/following-sibling::text()"));
        // Nº NÚMERO TARJETA:
        $this->SetProperty('CardNumber', $this->http->FindSingleNode("//input[@id='numTarjetaJovenHidden']/@value"));
        // Consumo mes actual:
        $this->SetProperty('SpentThisMonth',
            $this->http->FindSingleNode("//p[contains(text(),'Consumo mes actual:')]", null, false, '/[\d,.€]+/'));
        // Consumo acumulado:
        $this->SetProperty('YTDSpent',
            $this->http->FindSingleNode("//p[contains(text(),'Consumo acumulado:')]", null, false, '/[\d,.€]+/'));

        // ExpirationDate - refs #18018
        if ($expBalance = $this->http->FindSingleNode("//span[contains(text(),' próximos a caducar')]", null, false, '/([\d.,]+) Puntos/')) {
            if ($expDate = $this->http->FindNodes("//span[contains(text(),' próximos a caducar')]/following-sibling::text()", null, '/el (\d+ de .+? de \d+)/iu')) {
                $expDate = str_replace(' de ', ' ', join(' ', $expDate));
                $this->logger->debug("Expiration Date: {$expDate}");

                if ($originalMonth = $this->http->FindPreg('#\b([[:alpha:]]+)\b#u', false, $expDate)) {
                    if ($translatedMonth = $this->translateMonth($originalMonth, 'es')) {
                        $translatedDate = preg_replace("#{$originalMonth}#i", $translatedMonth, $expDate);
                        $this->logger->debug("Translated Expiration Date: {$translatedDate}");

                        if ($expDate = strtotime($translatedDate, false)) {
                            $this->SetProperty('ExpiringBalance', $expBalance);
                            $this->SetExpirationDate($expDate);
                        }
                    }
                }
            }
        }

        $this->http->GetURL('https://venta.renfe.com/vol/myJourneysCEX.do?c=_u1ls');

        if (!$this->http->FindSingleNode("//*[@id='msgNoJourneys' and contains(text(),'No existen viajes pendientes')]")
            || $this->http->FindSingleNode("//tbody[@id='listaMisViajes']", null, false)
        ) {
            $this->sendNotification('refs #17986. Itineraries were found //MI');
        }
    }

    protected function parseReCaptcha($key, $isV3 = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => 'https://venta.renfe.com/vol/loginCEX.do?fallo=No&controller=plusRenfeController&to=masRenfe.do',
            "proxy"   => $this->http->GetProxy(),
        ];

        if ($isV3) {
            $parameters += [
                "version"   => "v3",
                "action"    => "loginCEX_do",
                "min_score" => 0.3,
            ];
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
