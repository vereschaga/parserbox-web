<?php

class TAccountCheckerKenyaair extends TAccountChecker
{
    use AwardWallet\Engine\ProxyList;

    private const REWARDS_PAGE_URL = 'https://asante.kenya-airways.com/';

    private $id = null;
    private $nextStatus = null;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://asante.kenya-airways.com/';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (
            !$this->http->FindSingleNode('//title[contains(text(), "Asante Rewards Member Portal")]')
            || $this->http->Response['code'] != 200
        ) {
            return $this->checkErrors();
        }

        $postData = [
            "channel"      => "W",
            'username'     => $this->AccountFields['Login'],
            'password'     => $this->AccountFields['Pass'],
            "program_code" => "ASANTE_PROGRAM",
        ];

        $this->http->PostURL("https://asante.kenya-airways.com/b2c/login", $postData, [
            'Accept'             => 'application/json, text/plain, */*',
            'Content-Type'       => 'application/x-www-form-urlencoded',
            'X-Clm-Program-Code' => 'ASANTE_PROGRAM',
        ]);

        return true;
    }

    public function ProcessStep($step)
    {
        $postData = [
            "channel"       => "W",
            'refresh_token' => $this->State['refresh_token'],
            "program_code"  => "ASANTE_PROGRAM",
        ];

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->http->PostURL("https://asante.kenya-airways.com/b2c/refresh-token", $postData, [
            'Accept'             => 'application/json, text/plain, */*',
            'Content-Type'       => 'application/x-www-form-urlencoded',
            'X-CLM-OTP-Token'    => $answer,
            'X-Clm-Program-Code' => 'ASANTE_PROGRAM',
        ]);

        $data = $this->http->JsonLog(null, 2, true);
        $access_token = $data['access_token'] ?? null;

        if ($this->http->Response['code'] != 200 || !$access_token) {
            $message = $data['message'] ?? null;

            $this->DebugInfo = $message;

            return false;
        }

        $this->http->setCookie('Bearer', $access_token, 'asante.kenya-airways.com');
        $this->http->setCookie('cmptoken', json_encode(['access_token' => $access_token, 'expires_in' => $data['expires_in'], 'scope' => $data['scope'], 'jti' => $data['jti']]), 'asante.kenya-airways.com');

        return $this->loginSuccessful();
    }

    public function Login()
    {
        $data = $this->http->JsonLog(null, 2, true);
        $access_token = $data['access_token'] ?? null;

        if ($this->http->Response['code'] != 200 || !$access_token) {
            $message = $data['message'] ?? null;

            if ($message == 'Wrong login or password') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $this->http->setCookie('Bearer', $access_token, 'asante.kenya-airways.com');
        $this->http->setCookie('cmptoken', json_encode(['access_token' => $access_token, 'expires_in' => $data['expires_in'], 'scope' => $data['scope'], 'jti' => $data['jti']]), 'asante.kenya-airways.com');

        $this->State['refresh_token'] = $data['refresh_token'];

        // We have sent you a one-time password
        $this->AskQuestion("We have sent you a one-time password", null, "Question");

        return false;

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $data = $this->http->JsonLog(null, 3, true);
        $this->id = $data['id'];

        // Balance - Reward points
        $balance = $data['mainPointsBalance'];
        $this->SetBalance($balance);
        // Name
        $this->SetProperty('Name', beautifulName(($data['firstName'] ?? null) . ' ' . ($data['lastName'] ?? null)));
        // Number
        $this->SetProperty('Number', $data['mainIdentifier'] ?? null);
        // Member since
        $this->SetProperty('MemberSince', strtotime($data['enrolmentDate'] ?? null) ?? null);

        $data = $this->getData('https://asante.kenya-airways.com/b2c/me/recognition-schemas/KQ_Asante/tiers', 3, true);

        if (is_iterable($data)) {
            uasort($data, function ($a, $b) {
                if ($a['tier']['priority'] == $b['tier']['priority']) {
                    return 0;
                }

                return ($a['tier']['priority'] < $b['tier']['priority']) ? -1 : 1;
            });
        }

        // Status
        $this->SetProperty('Status', current($data)['tier']['name'] ?? null);
        // Status Expiration Date
        $this->SetProperty('StatusExpirationDate', strtotime(current($data)['endDate'] ?? null) ?? null);

        $data = $this->getData('https://asante.kenya-airways.com/b2c/me/recognition-schemas/KQ_Asante/available-tiers', 3, true);

        if (is_iterable($data)) {
            uasort($data, function ($a, $b) {
                if ($a['priority'] == $b['priority']) {
                    return 0;
                }

                return ($a['priority'] > $b['priority']) ? -1 : 1;
            });
        }

        $this->nextStatus = current($data)['name'] ?? null;
        $data = $this->getData('https://asante.kenya-airways.com/b2c/me/progress-trackers', 3, true);

        if (is_iterable($data)) {
            foreach ($data as $item) {
                if ($item['name'] == 'Flight Counter') {
                    // Flights completed
                    $this->SetProperty('FlightsCompleted', $item['currentValue'] ?? null);
                }

                if ($item['name'] == 'Tier Points') {
                    // Points to next status
                    $this->SetProperty('PointsToNextStatus', $item['maxValue'] ?? null);
                    // Status points
                    $this->SetProperty('StatusPoints', $item['currentValue'] ?? null);
                }
            }
        }

        $data = $this->getAuxiliaryData('https://asante.kenya-airways.com/ccms-api//pages/alias/progress-to-tier-description', 3, true);
        $flightsData = $data['content'] ?? null;

        if ($flightsData) {
            $this->http->setBody($flightsData);
            $tierDescriptions = $this->http->FindNodes('//strong');

            foreach ($tierDescriptions as $key => $value) {
                if (strtolower($this->nextStatus) == strtolower($value) && ($tierDescriptions[$key + 2] ?? null) && is_numeric($tierDescriptions[$key + 2])) {
                    // Flights to next status
                    $this->SetProperty('FlightsToNextStatus', intval($tierDescriptions[$key + 2] ?? null));
                }
            }
        }

        /*
        $history = $this->getData('https://asante.kenya-airways.com/b2c/me/account/customers/' . $this->id . '/transactions?orderField=date%3Adesc&firstResult=0&withQCnt=false', 3, false);
        $needToNotify = false;

        if (is_iterable($history)) {
            foreach ($history as $transaction) {
                if ($transaction->status !== 'B') {
                    continue;
                }

                foreach ($transaction->pointsBalances as $operation) {
                    if ($operation->status !== 'B') {
                        continue;
                    }

                    if ($operation->spentPoints == 0) {
                        $balance = $balance - $operation->points;
                    }

                    if ($balance <= 0) {
                        // old code for parse exp date
                        // Earning Date
                        // $this->SetProperty("EarningDate", strtotime($transaction->date));
                        // Expiration Date
                        // $this->SetExpirationDate(strtotime($operation->expirationDate));
                        // Expiring Balance
                        // $this->SetProperty("ExpiringBalance", $operation->pointsToExpire + $balance);

                        $earningDate = strtotime($transaction->date);
                        $expDate = strtotime($operation->expirationDate);
                        $expBalance = $operation->pointsToExpire + $balance;

                        $this->logger->debug('EARNING DATE: ' . $earningDate);
                        $this->logger->debug('EXP DATE: ' . $expDate);
                        $this->logger->debug('EXP BALANCE: ' . $expBalance);

                        $transactionDateTime = new Datetime();
                        $transactionDateTime->setTimestamp(strtotime($transaction->date));
                        $transactionDateTime->setTimestamp(strtotime($transactionDateTime->format("Y-m-d")));
                        $expDateTime = new Datetime();
                        $expDateTime->setTimestamp(strtotime($operation->expirationDate));
                        $interval = $expDateTime->diff($transactionDateTime);
                        $this->logger->debug('INTERVAL BETWEEN EXP DATE AND TRANSACTION DATE: ' . $interval->format('%Y years %m months %d days %H hours %i minutes %s seconds'));

                        if ($interval->y == 3) {
                            $needToNotify = true;
                        }
                    }
                }
            }
        }

        if ($needToNotify == true) {
            $this->sendNotification('refs #22740 - need to check exp date // IZ');
        }
        */
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $data = $this->getData('https://asante.kenya-airways.com/b2c/me', 3, true);
        $email = $data['address']['email'] ?? null;

        if ($email != null && strtolower($email) === strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function getAuxiliaryData(string $url, $logs = 0, $convertToArray = false)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL($url, [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Basic ' . base64_encode('ccms:ccmsApiKey'),
        ]);

        return $this->http->JsonLog(null, $logs, $convertToArray);
    }

    private function getData(string $url, $logs = 0, $convertToArray = false)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL($url, [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Bearer ' . $this->http->getCookieByName('Bearer', 'asante.kenya-airways.com'),
        ]);

        return $this->http->JsonLog(null, $logs, $convertToArray);
    }

    private function postData(string $url, $params = [], $logs = 0, $convertToArray = false)
    {
        $this->logger->notice(__METHOD__);
        $this->http->PostURL($url, $params, [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Bearer ' . $this->http->getCookieByName('Bearer', 'asante.kenya-airways.com'),
            'Content-Type'  => 'application/json',
        ]);

        return $this->http->JsonLog(null, $logs, $convertToArray);
    }
}
