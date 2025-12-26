<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerOstohyvitys extends TAccountChecker
{
    use ProxyList;

    private $headers = [
        "Accept"                 => "application/json, text/plain, */*",
        "Accept-Language"        => "fi",
        "Content-Type"           => "application/json",
        "X-Bonusway-Locale"      => "fi",
        "X-Bonusway-Web-Version" => "4.0",
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['userID']) || !isset($this->State['token'])) {
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
        $this->http->GetURL("https://www.bonusway.fi/auth/login");

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.bonusway.com/sessions", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->user)) {
            $this->State['userID'] = $response->user->id;
            $this->State['token'] = $response->token;

            return $this->loginSuccessful();
        }
        // Invalid credentials
        if ($this->http->Response['code'] == 401 && stripos($this->http->Response['body'], 'Unauthorized') !== false) {
            throw new CheckException('Salasanasi on väärä.', ACCOUNT_INVALID_PASSWORD);
        }
        // Find error message
        if (isset($response->error) && strstr($response->error, 'Virheellinen k&auml;ytt&auml;j&auml;tunnus tai salasana')) {
            throw new CheckException('Virheellinen käyttäjätunnus tai salasana', ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($response->error) && strstr($response->error, 'Virheellinen käyttäjätunnus tai salasana')) {
            throw new CheckException($response->error, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        if (isset($response->forename, $response->surname)) {
            $this->SetProperty("Name", beautifulName("{$response->forename} {$response->surname}"));
        }
        // Tilityskelpoinen bonus
        if (isset($response->withdrawals->available)) {
            $this->SetBalance(number_format((float) $response->withdrawals->available, 2, '.', ''));
        }
        // Arvioitavana
        if (isset($response->cashback->accepted) && isset($response->cashback->evaluating)) {
            $this->SetProperty('Pending', '€' . number_format((float) $response->cashback->accepted + $response->cashback->evaluating, 2, '.', ''));
        }
        // Tilitetty bonus
        if (isset($response->withdrawals->total)) {
            $this->SetProperty('Redeemed', '€' . number_format((float) $response->withdrawals->total, 2, '.', ''));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $headers = array_merge($this->headers, ["Authorization" => "Bonusway version=\"1.0\" token=\"{$this->State['token']}\" verification=\"\""]);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.bonusway.com/users/{$this->State['userID']}", $headers, 20);
        $response = $this->http->JsonLog();
        $this->http->RetryCount = 2;

        if (isset($response->id)) {
            return true;
        }

        return false;
    }
}
