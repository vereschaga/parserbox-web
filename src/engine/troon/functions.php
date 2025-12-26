<?php

class TAccountCheckerTroon extends TAccountChecker
{
    private const REWARDS_PAGE_URL = "https://troon.com/account";

    private $headers = [
        "Content-Type"            => "application/json",
        "x-troon-client-version"  => "54b771d7c796870fd6db2b3132b0c043b87dcad3",
        "x-troon-client-platform" => "web-client",
        "x-trace-id"              => "dd5f14a4-38cf-4e7d-a10f-e32ac5898bd2",
        "x-session-id"            => "57b89333-770b-4112-970c-a7e33d93ed37",
        "x-tzoffset"              => "300",
        "Origin"                  => "https://troon.com",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
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

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        $this->headers += [
            "x-datetime" => date("U"),
        ];
        $data = '{"operationName":"loginV2","query":"mutation loginV2($email: String!, $password: String!) {\n  login: loginV2(\n    input: {email: $email, password: $password, syncInBackground: true}\n  ) {\n    user {\n      email\n    }\n  }\n}","variables":{"email":"'.$this->AccountFields["Login"].'","password":"'.$this->AccountFields["Pass"].'"}}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.troon.com/graphql", $data, $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $reponse = $this->http->JsonLog(null, 5);

        if (isset($reponse->data->login->user->email) && $this->loginSuccessful()) {
            return true;
        }

        if ($message = $reponse->errors[0]->extensions->displayMessage ?? null) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, "Your email or password is incorrect.")
                || strstr($message, "Your email, rewards number, or password is incorrect.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $reponse = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", $reponse->data->me->firstName . " ". $reponse->data->me->lastName);
        // Current Reward Status
        $this->SetProperty("Status", $reponse->data->userRewards->level);
        // Reward ID
        $this->SetProperty("RewardID", $reponse->data->me->troonRewardsId);
        // Balance - My Redeemable Points
        $this->SetBalance($reponse->data->userRewards->availablePoints);

        $data = '{"operationName":"activity","query":"query activity {\n  transactions: userRewardTransactions {\n    points\n    facilityName\n    dayCreated {\n      year\n      month\n      day\n    }\n    transactionType\n  }\n}","variables":{}}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.troon.com/graphql", $data, $this->headers);
        $this->http->RetryCount = 2;
        $reponse = $this->http->JsonLog();
        $activities = $reponse->data->transactions ?? [];
        $this->logger->debug("Total " . count($activities). " transactions were found");
        $now = 0;

        foreach ($activities as $activity) {
            // 5/15/2023
            $lastActivityStr = "{$activity->dayCreated->month}/{$activity->dayCreated->day}/{$activity->dayCreated->year}";
            $this->logger->debug("Last Activity: $lastActivityStr");
            $lastActivity = strtotime($lastActivityStr);

            if ($now < $lastActivity) {
                $now = $lastActivity;
                // LastActivity
                $this->SetProperty('LastActivity', $lastActivityStr);
                // Expiration Date
                if ($exp = strtotime('+18 month', $lastActivity)) {
                    $this->SetExpirationDate($exp);
                }
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $data = '{"operationName":"loggedInUser","query":"query loggedInUser {\n  me {\n    id\n    email\n    firstName\n    lastName\n    troonRewardsId\n    troonCardId\n    card: oldTroonCardGroup {\n      id\n      name\n    }\n    role\n    zipcode\n  }\n  userRewards {\n    availablePoints\n    level\n  }\n  activeTroonCardSubscription {\n    status\n    name: subscriptionName\n    nextInvoiceDate {\n      ...DayTime\n    }\n  }\n}\nfragment DayTime on CalendarDayTime {\n  day {\n    year\n    month\n    day\n  }\n  time {\n    hour\n    minute\n  }\n}","variables":{}}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.troon.com/graphql", $data, $this->headers);
        $this->http->RetryCount = 2;
        $reponse = $this->http->JsonLog();
        $email = $reponse->data->me->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }
}
