<?php

/**
 * Class TAccountCheckerPepsipoints
 * Display name: Pepsi Pulse (Experience Points)
 * Database ID: 900
 * Author: AKolomiytsev
 * Created: 14.10.2014 9:55.
 */
class TAccountCheckerPepsipoints extends TAccountChecker
{
    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerPepsipointsSelenium.php";

        return new TAccountCheckerPepsipointsSelenium();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://pass.pepsi.com/log-in?__locale__=en");

        if ($this->http->FindPreg("/\"model_data\":/")) {
            return true;
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://points.pepsi.com/";

        return $arg;
    }

    public function Login()
    {
        $data = '{"password":"' . $this->AccountFields['Pass'] . '","remember_me":false,"ct_rpc_action":"login","username":"' . $this->AccountFields['Login'] . '"}';

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://points.pepsi.com/user/rpc", $data, ['Content-Type' => 'application/json; charset=UTF-8']);
        $response = json_decode($this->http->Response['body']);

        if (isset($response->redirect_url)) {
            return true;
        }

        if (isset($response->exception->model)) {
            throw new CheckException($response->exception->model, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $response = json_decode($this->http->Response['body']);
        $this->http->GetURL($response->redirect_url);
        $this->http->RetryCount = 2;

        // Balance
        $balance = $this->http->FindPreg("/\"redeemable_points\": ([0-9]+),/");

        $this->SetBalance($balance);

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/\"full_name\": \"(.+)\",/")));

        // Status - Fan level & Points needed to next status
        $status = '';
        $toNextStatus = 0;

        $levels = [
            0     => 'Newbie',
            2500  => 'Rookie',
            5000  => 'Up and Comer',
            10000 => 'Pro',
            20000 => 'Icon',
            50000 => 'Legend',
        ];

        foreach ($levels as $key => $value) {
            if ($balance >= $key) {
                $status = $value;
            } else {
                $toNextStatus = $key - $balance;

                break;
            }
        }

        $this->SetProperty("Status", $status);
        $this->SetProperty("ToNextStatus", $toNextStatus);
    }
}
