<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerRedroof extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->SetProxy($this->proxyReCaptcha());
//        $this->http->setDefaultHeader('Origin', 'https://www.redroof.com');
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
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.redroof.com/signin/login?ReturnUrl=%2fprofile");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $data = [
            "email"               => $this->AccountFields['Login'],
            "password"            => $this->AccountFields['Pass'],
            "rememberMe"          => "false",
            "includeCurrentStays" => true,
        ];
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br, zstd",
            "Content-Type"    => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://prd-e-gwredroofwebapi.redroof.com/api/v1/member/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We apologize but something has gone wrong and the web page is unable to load at this time.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We apologize but something has gone wrong and the web page is unable to load at this time.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error in \'/\' Application.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg('/\{ StatusCode = 500, Message = Internal Server Error. \}/')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(2, 7);
        }

        $response = $this->http->JsonLog(null, 3, true);
        // There was an error in completing your request.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'There was an error in completing your request.')]", null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Access is allowed
        $pmsLoginResponse = ArrayVal($response, 'pmsLoginResponse', null);
        $message = ArrayVal($pmsLoginResponse, 'message', null);

        if ($message == "Login Successful") {
            if (!$this->loginSuccessful()) {
                if ($this->http->Response["code"] == 500
                    && $this->http->FindSingleNode('//h1[contains(text(),"Server Error in")]')
                ) {
                    throw new CheckRetryNeededException(2, 1);
                }

                return false;
            }

            return true;
        }// if ($message == "Login Successful")

        if ($message) {
            $this->logger->error("Error: {$message}");

            if (strstr($message, 'The username or password entered is incorrect')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'We apologize. An error has occurred. Agents are standing by. Please give us a call @ 877-843-7663.')
                || $message == 'Login failure'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // WS Gateway Timeout- API response greater than 30 seconds. Http Status Code 504
            if (strstr($message, 'AWS Gateway Timeout- API response greater than 30 seconds.  Http Status Code 504')
                // Server Error- Http Status Code 500
                || strstr($message, 'Server Error- Http Status Code 500')
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $data = $this->http->JsonLog(null, 0, false, 'pmsLoginResponse');
        $profile = $data->pmsLoginResponse->profile ?? $data->data->memberProfile;
        // Balance - Total Balance
        $this->SetBalance($profile->pointsBalanceFormatted ?? null);
        // Name
        $this->SetProperty("Name", beautifulName(($profile->firstName ?? null) . " " . ($profile->lastName ?? null)));
        // Account Number
        $this->SetProperty("Number", $profile->LoyaltyAccountNbr ?? null);

        // Expiration date // refs #3837, https://redmine.awardwallet.com/issues/3837#note-9
        if ($this->Balance > 0) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            $memberRewardTransactions = $response->redicardActivity ?? [];
            $this->logger->debug("Total " . count($memberRewardTransactions) . " transactions were found");

            foreach ($memberRewardTransactions as $memberRewardTransaction) {
                $date = $memberRewardTransaction->date;
                $pointsAmount = $memberRewardTransaction->pointsAmount;
                $transactionType = $memberRewardTransaction->transactionType;

                if ($transactionType == 'Stay' && $pointsAmount > 0) {
                    // Last Activity
                    $lastActivity = $date;
                    $this->SetProperty("LastActivity", $lastActivity);
                    // Expiration Date - 14 months
                    if ($lastActivity = strtotime($lastActivity)) {
                        $exp = strtotime("+14 month", $lastActivity);
                        $this->SetExpirationDate($exp);
                    }// if ($lastActivity = strtotime($lastActivity))

                    break;
                }// if ($transactionType == 'Stay' && $pointsAmount > 0)
            }// foreach ($memberRewardTransactions as $memberRewardTransaction)
        }// if ($this->Balance > 0)

        // Certificates

        $this->logger->info('Certificates', ['Header' => 3]);
        $memberCertificates = $response->CertificateActivity ?? [];
        $this->logger->debug("Total " . count($memberCertificates) . " certificates were found");

        foreach ($memberCertificates as $certificate) {
            $certificateNumber = $certificate->CertificateNumber;
            $expirationDate = $certificate->ExpirationDate;
            $status = $certificate->Status;

            if (strtolower($status) == 'issued') {
                $this->AddSubAccount([
                    'Code'              => 'redroofCertificate' . $certificateNumber,
                    'DisplayName'       => "Cert #{$certificateNumber}",
                    'Balance'           => null,
                    'ExpirationDate'    => strtotime($expirationDate),
                    'IssuedTo'          => $certificate->issueDate,
                    'StatusCertificate' => $status,
                ], true);
            }
        }// foreach ($memberCertificates as $certificate)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br, zstd",
            "Content-Type"    => "application/json",
            "Priority"        => "u=1, i",
            "Origin"          => "https://www.redroof.com",
        ];
        $this->http->RetryCount = 0;
//        $this->http->GetURL('https://prd-e-gwredroofwebapi.redroof.com/api/v1/session/getasync/SessionId', $headers);
//        $this->http->JsonLog();
//        $this->http->GetURL('https://prd-e-gwredroofwebapi.redroof.com/api/v1/session/getasync/RegisterRediRewardsMemberResponse', $headers);
//        $this->http->JsonLog();
//        $this->http->GetURL('https://prd-e-gwredroofwebapi.redroof.com/api/v1/member/get-profile-page', $headers);
        $this->http->RetryCount = 2;
//        $response = $this->http->JsonLog(null, 3, false, 'data');
        $response = $this->http->JsonLog(null, 3, false, 'pmsLoginResponse');

        if (
            isset($response->pmsLoginResponse->profile->LoyaltyAccountNbr/*, $response->MemberProfile->Email*/)
            // not working for accounts with login username
            /*
            && (
                $response->MemberProfile->LoyaltyAccountNumber == $this->AccountFields['Login']
                || strtolower($response->MemberProfile->Email) == strtolower($this->AccountFields['Login'])
            )
            */
        ) {
            return true;
        }

        return false;
    }
}
