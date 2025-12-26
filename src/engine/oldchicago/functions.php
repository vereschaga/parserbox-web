<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerOldchicago extends TAccountChecker
{
    private $access_token = null;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://oldchicago.com/account/rewards");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $data = [
            "email"    => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $header = [
            "Accept"       => "*/*",
            "Content-Type" => "application/json",
        ];
        $this->http->PostURL("https://rewards.oldchicago.com/backend/index.php?path=/login", json_encode($data), $header);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, but the World Beer Tour pages are temporarilly dow")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'World Beer Tour pages are temporarilly down')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->jwt, $response->access_token)) {
            $this->access_token = $response->access_token;
            $this->http->setDefaultHeader("Accept", "*/*");
            $this->http->setDefaultHeader("Content-Type", "application/json");
            $this->http->setDefaultHeader("Authorization", "Bearer {$response->jwt}");

            $this->http->GetURL("https://rewards.oldchicago.com/backend/index.php?path=/user&username={$this->AccountFields['Login']}&access_token={$this->access_token}");

            return true;
        }

        $message = $response->errorMessage ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");
            // Your login information was not recognized.
            if ($message == "Could not Authenticate") {
                throw new CheckException("The password you entered is incorrect or out-of-date.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message)

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();
        // Name
        if (isset($response->fields->firstName, $response->fields->lastName)) {
            $this->SetProperty("Name", Html::cleanXMLValue(beautifulName($response->fields->firstName . " " . $response->fields->lastName)));
        }
        // Card Number
        $printedCardNumber = $response->primaryCardNumbers[0] ?? null;
        $this->SetProperty("CardNumber", $printedCardNumber);

        if (!isset($printedCardNumber)) {
            return;
        }

        $this->http->GetURL("https://rewards.oldchicago.com/backend/index.php?path=/account&printedCardNumber={$printedCardNumber}&access_token={$this->access_token}");
        $response = $this->http->JsonLog();
        $pointBalances = $response->pointBalances ?? [];

        foreach ($pointBalances as $pointBalance) {
            switch ($pointBalance->name) {
                case "Reward Points":
                    // Balance - points
                    $this->SetBalance($pointBalance->balance);
                    // Points to your next OC Bucks
                    $this->SetProperty("OCReward", 75 - $pointBalance->balance);
                    break;
                // Current Tour
                case "World Beer Tour Progress":
                    $this->AddSubAccount([
                        'Code'        => 'oldchicagoCurrentTour',
                        'DisplayName' => "Current Tour",
                        'Balance'     => $pointBalance->balance,
                    ]);
                    break;
                // Completed Tours
                case "World Beer Tours Completed":
                    $this->SetProperty("CompletedTours", $pointBalance->balance);
                    break;
            }
        }
        // Status
        $this->SetProperty("Status", $response->tierLabel);
    }
}
