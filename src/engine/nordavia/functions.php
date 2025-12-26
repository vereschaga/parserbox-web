<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerNordavia extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->disableOriginHeader();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://flysmartavia.com/avia/auth/login");

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        $data = [
            'email'    => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"        => "application/json",
            "Content-type"  => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.flysmartavia.com/api/auth/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        // Invalid credentials
        if ($error = $response->data->message ?? null) {
            $this->logger->error("[Error]: {$error}");

            if ($error === 'User not registered. Use your email to signup.') {
                throw new CheckException('Пользователь не зарегистрирован.', ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return false;
    }

    public function parseItem($number)
    {
        return $this->http->FindSingleNode(sprintf("
            (//div[contains(@class, 'card')]//div[contains(@class, 'number')])[%s]",
            $number
        ));
    }

    public function Parse()
    {
        // Balance - miles
        $milesText = $this->parseItem(3);
        $this->SetBalance($this->http->FindPreg('/^\s*(\d+)/', false, $milesText));
        // Name
        $name = beautifulName($this->parseItem(1));
        $this->SetProperty("Name", $name);
        // Card Number
        $this->SetProperty('CardNumber', $this->parseItem(2));
    }
}
