<?php

use AwardWallet\Engine\langham\QuestionAnalyzer;

class TAccountCheckerLangham extends TAccountChecker
{
    private $headers = [
        "Accept"          => "application/json, text/plain, */*",
        "Accept-Language" => "en-US,en;q=0.5",
        "Accept-Encoding" => "gzip, deflate, br",
        "x-api-key"       => "jAOOqSiGNI6O9zsAamr2q8xQ8BeE6ZWA4mBJvv7U",
        "Origin"          => "https://www.brilliantbylangham.com",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.brilliantbylangham.com/en/login");

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        $data = [
            "username"             => $this->AccountFields['Login'],
            "password"             => $this->AccountFields['Pass'],
            "is_username_memberid" => filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false,
        ];
        $headers = [
            "Content-Type" => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://apiprod.gravty.cn/v1/lhg/gis/two-factor/members/login', json_encode($data), $this->headers + $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $message = $response->error->message ?? null;
        $service = $response->service ?? null;

        if ($service == "SIGN_IN_OTP") {
            $this->State['member_id'] = $response->member_id;

            $question = "Check your inbox now. We've sent a verification code to you.";

            if (!QuestionAnalyzer::isOtcQuestion($question)) {
                $this->sendNotification("need to fix QuestionAnalyzer");
            }

            $this->AskQuestion($question, null, "Question");

            return false;
        }

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Invalid Credentials') {
                throw new CheckException('Oops...It looks like you\'ve entered the wrong credentials. Please check carefully and have another go. We\'ll wait…', ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $data = [
            "action"  => "VALIDATE",
            "otp"     => $this->Answers[$this->Question],
            "service" => "SIGN_IN_OTP",
        ];
        unset($this->Answers[$this->Question]);
        $this->http->RetryCount = 0;
        $headers = [
            "Content-Type" => "application/json",
        ];
        $this->http->PostURL("https://apiprod.gravty.cn/v1/lhg/gis/two-factor/members/{$this->State['member_id']}/otp", json_encode($data), $this->headers + $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

//        if (
//            isset($response->ErrorMessages)
//            && in_array($response->ErrorMessages, [
//            ])
//        ) {
//            $this->AskQuestion($this->Question, $response->ErrorMessages, 'Question');
//
//            return false;
//        }

        if (!isset($response->jwt_token)) {
            return false;
        }

        $this->http->setDefaultHeader("Authorization", "JWT {$response->jwt_token}");

        return true;
    }

    public function Parse()
    {
        $this->http->GetURL("https://apiprod.gravty.cn/v1/members/data/{$this->State['member_id']}", $this->headers);
        $response = $this->http->JsonLog();
        $data = $response->data;

        // Name
        $this->SetProperty("Name", beautifulName($data->user->first_name . " " . $data->user->last_name));
        // Membership No.
        $this->SetProperty("Number", $data->member_id);
        // Tier
        switch ($data->tier_data[0]->tier_code_id) {
            case 'Tier_T1':
                $this->SetProperty("Tier", "ONYX");

                break;

            case 'Tier_T2':
                $this->SetProperty("Tier", "TOPAZ");

                break;

            case 'Tier_T3':
                $this->SetProperty("Tier", "DIAMOND");

                break;

            case 'Tier_T4':
                $this->SetProperty("Tier", "Sapphire");

                break;

            case 'Tier_T5':
                $this->SetProperty("Tier", "RUBY");

                break;

            default:
                $this->sendNotification("refs #23681 - new status was found: {$data->tier_data[0]->tier_code_id} // RR");

                break;
        }
        // VALID UNTIL
        $this->SetProperty("StatusExpiration", date("d M Y", strtotime($data->tier_data[0]->tier_end_date)));
        // Member Since
        $this->SetProperty("MemberSince", $data->date_of_joining);

        // Balance - BRILLIANT POINTS
        if (isset($data->balances) && $data->balances == []) {
            $this->SetBalance(0);
        } else {
            foreach ($data->balances as $balance) {
                switch ($balance->loyalty_account) {
                    case 'Award Points':
                        $this->SetBalance($balance->balance);

                        break;

                    case 'Status Points':
                        $this->SetProperty("StatusPoints", $balance->balance);

                        break;

                    default:
                        $this->sendNotification("refs #23681 - Unknow balance was found: {$balance->loyalty_account} // RR");

                        break;
                }// switch ($balance->loyalty_account)
            }// foreach ($data->balances as $balance)

            // STATUS POINTS
            if (
                !isset($this->Properties["StatusPoints"])
                && $this->ErrorCode == ACCOUNT_CHECKED
                && count($data->balances) == 1
            ) {
                $this->SetProperty("StatusPoints", 0);
            }
        }

        if (!empty($data->points_expiration)) {
            foreach ($data->points_expiration as $points_expiration) {
                switch ($points_expiration->loyalty_account) {
                    case 'Award Points':
                        $this->SetExpirationDate(strtotime($points_expiration->expiration_date));
                        $this->SetProperty("ExpiringBalance", $points_expiration->points);

                        break;

                    default:
                        $this->sendNotification("refs #23681 - Unknow balance was found: {$points_expiration->loyalty_account} // RR");

                        break;
                }// switch ($balance->loyalty_account)
            }// foreach ($data->balances as $balance)
        }// if (!empty($data->points_expiration))

        $this->http->GetURL("https://apiprod.gravty.cn/v1/entity-data/members/booking_memberbookings/?filter%7Bmember_id%7D={$this->State['member_id']}", $this->headers);
        $response = $this->http->JsonLog();

        if (!empty($response->data)) {
            $this->sendNotification("refs #23681 its were found");
        }

        // STATUS POINTS
        $this->SetProperty("MemberSince", $data->date_of_joining);
        // POINTS NEEDED TO UPGRADE
//        $this->SetProperty("", );
    }
}
