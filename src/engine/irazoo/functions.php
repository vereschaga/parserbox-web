<?php

class TAccountCheckerIrazoo extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private $headers = [
        "Accept"       => "application/json, text/plain, */*",
        "Content-Type" => "application/json",
        "Origin"       => "https://app.irazoo.com",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

//        $this->UseSelenium();
//        $this->disableImages();
//        $this->useChromium();
//        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://app.irazoo.com/#/auth/login");

        $data = [
            "email"    => $this->AccountFields["Login"],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://backend.sidemoneyapps.com/api/v1/login/", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;

        $loginField = $this->waitForElement(WebDriverBy::xpath('//input[@id = "email"]'), 10);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
        $loginButton = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Log in to my account")]'), 0);

        if (!$loginField || !$passwordInput || !$loginButton) {
            $this->logger->error('something went wrong');

            return false;
        }
        $loginField->sendKeys($this->AccountFields["Login"]);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $loginButton->click();

        return true;
    }

    public function Login()
    {
        // login successful
        $response = $this->http->JsonLog();

        if (isset($response->token)) {
            $this->State['Authorization'] = "Token {$response->token}";

            return $this->loginSuccessful();
        }

        $message = $response->non_field_errors[0] ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (in_array($message, [
                'We have upgraded iRazoo! You can signup with the email',
            ])) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (in_array($message, [
                'Email is invalid.',
                'Password invalid.',
                'The provided details are incorrect. Check your answers and try again.',
            ])) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->http->Response['body'] == '"User not found"') {
            throw new CheckException("User not found", ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $this->http->Response['code'] == 500
            && $this->http->FindPreg("/^page error$/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        /*
        $logout = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "profile-dropdown")]/span'), 7);
        $this->saveResponse();

        if ($logout || $this->waitForElement(WebDriverBy::xpath('//span[@class = "progresslabel"]'), 0)) {
            return true;
        }
        // Upgraded
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We have upgraded iRazoo! You can signup with the email')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Email is invalid.
        if ($message = $this->http->FindSingleNode("//div[
                contains(text(), 'Email is invalid.')
                or contains(text(), 'The provided details are incorrect. Check your answers and try again.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Password invalid.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Password invalid.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        */

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - Points Goal
        $this->SetBalance($response->total_points);
        // Name
        $this->SetProperty("Name", beautifulName($response->first_name . " " . $response->last_name));
        // Expiration date  // refs #14771
        if ($this->Balance && isset($response->ref_id)) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            $this->http->GetURL("https://backend.sidemoneyapps.com/api/v1/users/profiles/{$response->ref_id}/points-history/?limit=20", $this->headers);
            $response = $this->http->JsonLog();
            $nodes = $response->results ?? [];
            $this->logger->debug("Total " . count($nodes) . " history rows were found");

            foreach ($nodes as $node) {
                $date = $node->add_date;
                $description = $node->description;
                $points = $node->points;
                $this->logger->debug("[{$date}]: {$description} / {$points}");

                if ($points > 0 && !strstr($description, 'Refer Friends')) {
                    $this->SetProperty("LastActivity", date("M d, Y", strtotime($date)));

                    if ($exp = strtotime($date)) {
                        $this->SetExpirationDate(strtotime("+60 day", $exp));
                    }

                    break;
                }// if ($points > 0 && !strstr($description, 'Refer Friends'))
            }// foreach ($nodes as $node)
        }// if ($this->Balance)

        return;

        // Balance
        $this->SetBalance($this->http->FindSingleNode('//span[@class = "progresslabel"]'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//button[contains(@class, "profile-dropdown")]/span')));
        // Expiration date  // refs #14771
        if ($this->Balance) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            $this->http->GetURL("https://app.irazoo.com/dashboard/points-history");
            $this->waitForElement(WebDriverBy::xpath('//thead[tr[th[contains(text(), \'Offer Name\')]]]/following-sibling::tbody/tr'), 10);
            $this->saveResponse();
            $nodes = $this->http->XPath->query("//thead[tr[th[contains(text(), 'Offer Name')]]]/following-sibling::tbody/tr");
            $this->logger->debug("Total {$nodes->length} history rows were found");

            foreach ($nodes as $node) {
                $date = $this->http->FindSingleNode('td[1]', $node);
                $description = $this->http->FindSingleNode('td[2]', $node);
                $points = $this->http->FindSingleNode('td[3]', $node, true, self::BALANCE_REGEXP_EXTENDED);
                $this->logger->debug("[{$date}]: {$description} / {$points}");

                if ($points > 0 && !strstr($description, 'Refer Friends')) {
                    $this->SetProperty("LastActivity", $date);

                    if ($exp = strtotime($date)) {
                        $this->SetExpirationDate(strtotime("+60 day", $exp));
                    }

                    break;
                }// if ($points > 0 && !strstr($description, 'Refer Friends'))
            }// foreach ($nodes as $node)
        }// if ($this->Balance)
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://backend.sidemoneyapps.com/api/v1/users/profiles/me/", $this->headers + ['Authorization' => $this->State['Authorization']]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (
            isset($response->email)
//            && strtolower($response->email) == strtolower($this->AccountFields['Login'])
        ) {
            $this->headers['Authorization'] = $this->State['Authorization'];

            return true;
        }

        return false;
    }
}
