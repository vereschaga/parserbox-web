<?php

class TAccountCheckerGloriapartner extends TAccountChecker
{
    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.gloria.com.tr/en/glorian-member-club/profile-main-page/', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.gloria.com.tr/en/glorian-member-club/profile-main-page/");

        if (!$this->http->FindSingleNode('//form[@id="form-member-club-login"]')) {
            return false;
        }

        $data = [
            'UserName' => $this->AccountFields['Login'],
            'Password' => $this->AccountFields['Pass'],
        ];

        $headers = [
            'Content-Type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.gloria.com.tr/umbraco/api/MemberClub/Login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->successfull) && $response->successfull === true && strstr($response->message, "profile-main-page")) {
            $this->http->GetURL('https://www.gloria.com.tr/en/glorian-member-club/profile-main-page/');

            return $this->loginSuccessful();
        }

        $message = $response->message ?? null;

        if (isset($message)) {
            if (strstr($message, 'Kullanıcı bulunamadı')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Kullanıcı adı veya şifreniz yanlıştır.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // My Point
        $this->SetBalance($this->http->FindSingleNode('//span[contains(@class, "count")]', null, true, "/My Point: (.*)/"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[contains(@class, "nav-member-clup-container")]//h5')));

        // // Card Number
        // $this->SetProperty("CardNumber", $this->http->FindSingleNode('//div[contains(@class, "nav-member-clup-container")]//span[contains(text(), "Card Number:")]/strong'));

        if ($this->http->FindSingleNode('//div[contains(@class, "nav-member-clup-container")]//span[contains(text(), "Card Number:")]/strong') != '') {
            $this->sendNotification('refs #24244 gloriapartner: nedd to check card number');
        }

        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode('//div[contains(@class, "nav-member-clup-container")]//span[contains(text(), "Member")]'));

        // Points to Next Level
        $this->SetProperty('PointsToNextLevel', $this->http->FindSingleNode('//div[@class="member-clup-puan-container"]//p[contains(text(), "To reach the")]/b[2]'));
    }

    private function checkErrors(): bool
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@href, "LogOut")]')) {
            return true;
        }

        return false;
    }
}
