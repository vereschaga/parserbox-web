<?php

class TAccountCheckerBwtravelcard extends TAccountChecker
{
    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://wwws-usa2.givex.com/cws4.0/bwiusd/my-account/manage-cards.html';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['mqpass'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        // Request to dc_948.rpc
        $data = [
            'id'     => 950,
            'params' => ['en', 950, 'mqid', 'mqpass', $this->State['mqpass'], ''],
        ];
        $headers = [
            'Content-Type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://wwws-usa2.givex.com/cws40_svc/bwiusd/consumer/dc_950.rpc', json_encode($data), $headers, 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://wwws-usa2.givex.com/cws4.0/bwiusd/login/');

        if (!$this->http->ParseForm('cws_frm_login')) {
            return $this->checkErrors();
        }

        $abck = [
            "A1E268A71F2562D9BDA4717EBA978851~0~YAAQW97aFxiRPk6UAQAAclFtXw1ZZ6hHaeF3hpXHuZ5MNCKk5azPMaQy4rBgL17Otep70ri3R0H4JwlZBYUo/h489eSi0LpR7mw14MVrzUWLtyvpG2mB8j2GC2o2id6Uwv+KDJyxhmiKhXH3KhwGx87jRMlsIqceX9S01KiLwEh8FmdmzEKkFwCXsYQygUFXUWsbAJ00cBHufmzU2mZQ3anPrTAJ8/3cpJChHwt4OQx1BJbWHpnivaOWqaIId2fPLFVaFxXZjGPQH1t4Ft9hy9cChINZpn34YNtHDI9gZcehopdzqUOXxlMRb5xLpSVMaWzscoT9L0PEY0qcvNZONendDdSbCKx8iFBoe7e4N1N4mshpSRNOwLhoxm/lbAIIvncwlGlylre93vTVRRy6TgklRI3MFZUTUc5VIUOyI3lZbFt3JyhPgQ2687KxVVPZ10Lu0lKN+u2u9IaliFnRRFlUtn62Gq/ilqINqDgDxbEygOlB2yzYxYg6ltgikXfaCFg21tV5~-1~||0||~-1",
        ];
        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
        $this->http->setCookie("_abck", $abck[$key]);

        return true;
    }

    public function Login()
    {
        // Request to dc_958.rpc
        $data = [
            'id'     => 958,
            'params' => [
                'en',
                958,
                'mqid',
                'mqpass',
                $this->AccountFields['Login'],
                $this->AccountFields['Pass'],
                't',
            ],
        ];
        $headers = [
            'Content-Type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://wwws-usa2.givex.com/cws40_svc/bwiusd/consumer/dc_958.rpc', json_encode($data), $headers);
        $result = $this->http->JsonLog(null, 3, true);

        if (empty($result['result']['I4'])) {
            if (isset($result['status']) && $result['status'] === 0) {
                throw new CheckException('Login/Password incorrect', ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        $this->State['mqpass'] = $result['result']['I4'];
        // Request to dc_948.rpc
        $data = [
            'id'     => 88,
            'params' => [
                'en',
                99,
                'mqid',
                'mqpass',
                $result['result']['I4'],
                '',
            ],
        ];
        $headers = [
            'Content-Type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $this->http->RetryCount = 0;

        if ($this->http->PostURL('https://wwws-usa2.givex.com/cws40_svc/bwiusd/consumer/dc_948.rpc', json_encode($data), $headers)) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $result = $this->http->JsonLog(null, 3, true);

        // Cards > 1
        if (count($result['result']['I5']) > 1) {
            $this->sendNotification("refs #5203: Need to check: Cards > 1");
        }

        // Name
        $this->SetProperty('Name', beautifulName($result['result']['I3'] . ' ' . $result['result']['I4']));
        // Balance and Cards
        if (empty($result['result']['I5'])) {
            // You currently do not have any active cards.
            if (!empty($this->Properties['Name']) && $this->http->FindPreg("/\"I5\": \[\]\,/")) {
                $this->SetBalanceNA();
            }

            return;
        }
        // Balance
        $this->SetBalance($result['result']['I5'][0][5]);
        // Card Number
        $this->SetProperty('Number', $result['result']['I5'][0][0] . '*****' . $result['result']['I5'][0][1] . '*');
        // Status
        if ($result['result']['I5'][0][2] == 1) {
            $this->SetProperty('Status', 'Active');
        } else {
            $this->sendNotification("refs #5203: Need to check inactive Card Status");
        }
        // Expiration Date & Balance
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $this->SetProperty("ExpiringBalance", '$' . $result['result']['I5'][0][5]);
        $expDate = strtotime($result['result']['I5'][0][8], false);

        if ($result['result']['I5'][0][5] > 0 && $expDate) {
            $this->logger->debug(date('>>> d M Y', $expDate));
            $this->SetExpirationDate($expDate);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $result = $this->http->JsonLog(null, 3, true);

        if (!empty($result['result']['I5'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
