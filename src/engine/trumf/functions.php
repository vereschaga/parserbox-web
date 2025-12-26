<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerTrumf extends TAccountChecker
{
    use ProxyList;
    private $correlationId = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.trumf.no/profil/", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.trumf.no/profil/");

        $this->correlationId = $this->http->FindPreg("/correlationId=([^&]+)/", false, urldecode(urldecode($this->http->currentUrl())));
        $this->logger->debug('correlationId: ' . $this->correlationId);

        if ($this->correlationId == null) {
            return $this->checkErrors();
        }

        $this->State['correlationId'] = $this->correlationId;

        $data = [
            'phoneNumber' => $this->AccountFields['Login'],
        ];
        $headers = [
            'accept'       => '*/*',
            'content-type' => 'application/json',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://id.trumf.no/trumfid/login/validateUser?correlationId={$this->correlationId}&returnUrl=%2Fconnect%2Fauthorize%2Fcallback%3Fclient_id%3Dtrumf%26response_type%3Dcode%26scope%3Dopenid%2520profile%2520offline_access%2520http%253A%252F%252Fid.trumf.no%252Fscopes%252Fmedlem%2520api.rest%2520api.sylinder%2520api.trumfid%2520api.trumfid.biometri.administration%2520api.trumfid.biometri.administration.read%26redirect_uri%3Dhttps%253A%252F%252Fwww.trumf.no%252Fservices%252Floginservice%26state%3DskndN6j1ViSyTH2rd0_d%26nonce%3Dd26c7SaJ9Mm1jA79NhEa", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if (isset($response->redirect)) {
            $message = $this->http->FindPreg("/error=([^&]+)/", false, $response->redirect);

            if ($message == 'registration_required') {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $data = [
            'password' => $this->AccountFields['Pass'],
        ];
        $headers = [
            'accept'       => '*/*',
            'content-type' => 'application/json',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://id.trumf.no/trumfid/login/pwd?correlationId={$this->correlationId}&returnUrl=%2Fconnect%2Fauthorize%2Fcallback%3Fclient_id%3Dtrumf%26response_type%3Dcode%26scope%3Dopenid%2520profile%2520offline_access%2520http%253A%252F%252Fid.trumf.no%252Fscopes%252Fmedlem%2520api.rest%2520api.sylinder%2520api.trumfid%2520api.trumfid.biometri.administration%2520api.trumfid.biometri.administration.read%26redirect_uri%3Dhttps%253A%252F%252Fwww.trumf.no%252Fservices%252Floginservice%26state%3DskndN6j1ViSyTH2rd0_d%26nonce%3Dd26c7SaJ9Mm1jA79NhEa", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if (isset($response->type)) {
            $message = $response->type;

            if ($message == 'WrongPassword') {
                throw new CheckException('Passordet du har skrevet inn er feil. Prøv igjen.', ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Server Error in '/' Application
        if ($this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            || $this->http->FindPreg("/(Beklager\,\s*siden du leter etter finnes ikke)/ims")) {
            $this->http->GetURL("https://www.trumf.no/");
        }
//            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

        // Trumf.no er utilgjengelig grunnet vedlikehold. Velkommen tilbake senere.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Trumf.no er utilgjengelig grunnet vedlikehold. Velkommen tilbake senere.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Beklager, det har oppstått en feil
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Beklager, det har oppstått en feil') or contains(text(), 'Beklager, det oppstod en feil')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Beklager - trumf.no er for tiden ikke tilgjengelig
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Beklager - trumf.no er for tiden ikke tilgjengelig')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Beklager, men det er noe som ikke stemmer. Prøv igjen senere eller kontakt oss om du lurer på noe :)
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Uff da! Noe gikk galt :[')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Vi har for øyeblikket problemer med innloggede tjenester. Beklager ulempen dette måtte medføre.
        $this->http->GetURL("https://www.trumf.no/");

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Vi har for øyeblikket problemer med innloggede tjenester. Beklager ulempen dette måtte medføre.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // Logg inn feilet, logg deg inn med ditt kundenummer...
        // Login feilet, feil brukernavn eller passord.
        // Ugyldig passord
        if ($message = $this->http->FindSingleNode("//div[
                contains(text(), 'Ugyldig passord')
                or contains(text(), 'Innloggingen feilet på grunn av feil mobilnummer eller passord.')
                or contains(text(), 'Login feilet, feil brukernavn eller passord.')
                or contains(text(), 'Logg inn feilet, logg deg inn med ditt kundenummer')      
            ]")
            ?? $this->http->FindSingleNode("//p[
                    contains(text(), 'Innloggingen feilet på grunn av feil mobilnummer eller passord. Det er ikke lenger mulig å logge inn med kundenummer. Fyll ut ditt mobilnummer og passord.')
                    or contains(text(), 'Innloggingen feilet på grunn av feil mobilnummer eller passord.') 
                ]")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Kunne ikke sende SMS med engangskode fordi vi ikke har registrert mobilnummer. Ta kontakt med kundeservice på telefon 22 56 33 00 for å få lagt dette til.
        if ($message = $this->http->FindSingleNode("//div[
                contains(text(), 'Kunne ikke sende SMS med engangskode fordi vi ikke har registrert mobilnummer')
                or contains(text(), 'Vi har for tiden problemer med å sende SMS med engangskode. Vennligst forsøk igjen senere.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->parseQuestion()) {
            return false;
        }
        // Vi har for tiden noen feil med innlogging. Vennligst forsøk igjen senere.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Vi har for tiden noen feil med innlogging. Vennligst forsøk igjen senere.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);

        $response = $this->http->JsonLog();

        if (!isset($response->redirect)) {
            return false;
        }

        parse_str(parse_url($response->redirect, PHP_URL_QUERY), $output);
        $number = $output['Nr'] ?? null;
        $returnUrl = $output['ReturnUrl'] ?? null;

        if (!$number || !$returnUrl) {
            return false;
        }

        $question = "Skriv inn engangskoden som ble sendt til *** ** *{$number}.";
        $this->State['returnUrl'] = $returnUrl;

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $data = [
            "otp"           => $answer,
            "rememberMeSms" => true,
        ];
        $headers = [
            'accept'       => '*/*',
            'content-type' => 'application/json',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://id.trumf.no/trumfid/smsCode?correlationId={$this->State['correlationId']}&returnUrl=%2Fconnect%2Fauthorize%2Fcallback%3Fclient_id%3Dtrumf%26response_type%3Dcode%26scope%3Dopenid%2520profile%2520offline_access%2520http%253A%252F%252Fid.trumf.no%252Fscopes%252Fmedlem%2520api.rest%2520api.sylinder%2520api.trumfid%2520api.trumfid.biometri.administration%2520api.trumfid.biometri.administration.read%26redirect_uri%3Dhttps%253A%252F%252Fwww.trumf.no%252Fservices%252Floginservice%26state%3DskndN6j1ViSyTH2rd0_d%26nonce%3Dd26c7SaJ9Mm1jA79NhEa%26acr_values%3Dcas%253Acompleted", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->title) && $response->title == 'Wrong Code') {
            $this->AskQuestion($this->Question, "Feil kode ble skrevet inn. Du har flere forsøk igjen.", "Question");

            return false;
        }

        if ($message = $this->http->FindSingleNode("//body[contains(text(), 'Uff da, noe gikk galt. Lukk nettleseren eller appen, og prøv igjen senere.')]")) {//todo
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    public function Parse()
    {
        $this->sendNotification('refs #23685 trumf - auth completed');
        // Balance - Saldo
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Saldo:')]", null, true, self::BALANCE_REGEXP_EXTENDED));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[contains(@class, "user-info")]/span[1]')));

        /*
        $this->http->GetURL("https://www.trumf.no/api/trumf/memberinformation", [
            "Accept"                     => "application/json, text/plain, *
        /*",
            '__requestverificationtoken' => $this->http->FindSingleNode('//input[@name="__RequestVerificationToken"]/@value'),
        ]);
        $response = $this->http->JsonLog(null, 3, true);
        // Noe gikk feil, vennligst forsøk igjen senere.
        if ($this->http->Response['code'] == 500 && isset($response->message) && $response->message == 'Noe gikk feil, vennligst forsøk igjen senere.') {
            throw new CheckRetryNeededException(3, 10, $response->message);
        }
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($response, 'FirstName') . " " . ArrayVal($response, 'LastName')));
        // Trumf kundenummer
        $this->SetProperty("Number", ArrayVal($response, 'TrumfNumber'));
        */
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/Logg ut/ims")) {
            return true;
        }

        return false;
    }
}
