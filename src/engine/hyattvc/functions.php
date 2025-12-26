<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerHyattvc extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $headers = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->SetProxy($this->proxyDOP());
        $this->http->setRandomUserAgent();
        $this->http->setDefaultHeader("User-Agent", $this->http->userAgent);
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://thelounge.hyattvacationclub.com/login/login.html");

        // maintenance
        if ($this->http->currentUrl() == 'https://www.hyattvacationclub.com/bumper.html'
            && ($message = $this->http->FindSingleNode("//div[contains(text(), 'The Hyatt Residence Club website is temporarily unavailable.')]"))) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $data = [
            'username' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $this->http->PostURL('https://thelounge.hyattvacationclub.com/api/authentications', json_encode($data), $headers);

        if ($msg = $this->http->FindPreg('/The username and\/or password does not match our records\./')) {
            throw new CheckException($msg, ACCOUNT_INVALID_PASSWORD);
        }

        if ($msg = $this->http->FindPreg('/Connection Error\. Please try again later/')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        $response = $this->http->JsonLog(null, 3, true);

        $this->headers = [
            'Accept'          => '*/*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US,en;q=0.9,ru;q=0.8,de;q=0.7',
            'Authorization'   => sprintf('Bearer %s', ArrayVal($response, 'token')),
            // 'Content-Type' => 'application/json',
            //'DNT' => '1',
            'Host'       => 'www.hyattvacationclub.com',
            'Referer'    => 'https://thelounge.hyattvacationclub.com/',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.108 Safari/537.36',
        ];
        $this->http->GetURL('https://thelounge.hyattvacationclub.com/api/profile', $this->headers);

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 3, true);
        // Reset Password
        if (ArrayVal($response, 'password_change_required') == true) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindPreg("/\"member_number\":\d+/")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//font[@color='#D60000']/text()")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // AccountID: 4587343
        if (ArrayVal($response, 'message') == 'Member is not current') {
            $this->SetBalanceNA();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0, true);
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($response, 'first_name') . " " . ArrayVal($response, 'last_name')));
        // Member #
        $this->SetProperty("HVCMember", ArrayVal($response, 'member_number'));

        $this->http->GetURL('https://thelounge.hyattvacationclub.com/api/contracts', $this->headers);
        $all_contracts = $this->http->JsonLog(null, 3, true);

        foreach ($all_contracts as $contract) {
            // Total HRPP
            $totalHRPP = ArrayVal($contract, 'hrpp_fixed_balance');
            // Total CUP
            $totalCUP = ArrayVal($contract, 'fixed_cup_balance');
            // Total LCUP
            $totalLCUP = ArrayVal($contract, 'fixed_limited_cup_balance');

            $contract_points = ArrayVal($contract, 'contract_points', []);

            foreach ($contract_points as $contract_point) {
                $contract_id = ArrayVal($contract_point, 'display_contract_id');
                $code = ArrayVal($contract_point, 'category');
                $balance = ArrayVal($contract_point, 'points_balance');

                if ($balance > 0 && $balance != '') {
                    $subAccount = [
                        'Code' 		     => $code . 'PointsContract' . $contract_id,
                        'DisplayName' => $code . ' (Contract ' . $contract_id . ')',
                        'Balance'	    => $balance,
                        'Contract'    => $contract_id,
                        'Unit'        => ArrayVal($contract_point, 'unit_number'),
                        'Week'        => ArrayVal($contract_point, 'week_number'),
                        // Total HRPP
                        "TotalHRPP" => $totalHRPP,
                        // Total CUP
                        "TotalCUP" => $totalCUP,
                        // Total LCUP
                        "TotalLCUP" => $totalLCUP,
                    ];
                    // Expire Date
                    $expiration = ArrayVal($contract_point, 'expire_date');
                    $expiration = strtotime($expiration);

                    if ($expiration !== false) {
                        $subAccount['ExpirationDate'] = $expiration;
                    }
                    $this->AddSubAccount($subAccount, true);
                }// if ($balance > 0 || $balance != '')
            }// foreach ($contract_points as $contract_point)
        }// foreach ($all_contracts as $contract)

        if (isset($this->Properties['SubAccounts']) || (isset($totalHRPP, $totalCUP, $totalLCUP) && $this->http->FindPreg("/\"additional_members\":\[\],\"contract_points\":\[\]/"))) {
            $this->SetProperty("CombineSubAccounts", false);
            $this->SetBalanceNA();
        }// if (isset($this->Properties['SubAccounts']))
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.hyatt.com/vacations/sign_in1.jsp';
        $arg['SuccessURL'] = 'https://www.hyatt.com/vacations/clubhouse/member_statement.jsp';

        return $arg;
    }

    protected function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'currently down for maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Our Website is down for planned maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An unexpected error has occurred while attempting to process your request
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'An unexpected error has occurred while attempting to process your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Connection Error. Please try again later.
        if ($this->http->FindSingleNode('//li[contains(text(), "Connection Error. Please try again later.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // this message is not true, may be it's blocking or strange bug
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The page you are trying to access is currently down for maintenance.')]")) {
            $this->DebugInfo = "Maintenance: it's lie";
        }
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        if (isset($this->http->Response['code']) && $this->http->Response['code'] == 403
            || $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            $this->DebugInfo = 403;
        }

        return false;
    }
}
