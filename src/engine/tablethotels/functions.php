<?php

class TAccountCheckerTablethotels extends TAccountChecker
{
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
        $this->http->GetURL("https://www.tablethotels.com/signin?next=account");

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }
        $data = [
            "email"    => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "content-type"    => "application/x-www-form-urlencoded",
        ];

        $this->http->PostURL("https://www.tablethotels.com/auth_api/v1/auth?grant_type=password", $data, $headers);

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $access_token = $response->access_token ?? null;

        if (!$access_token) {
            $detail = $response->detail ?? null;
            $title = $response->title ?? null;
            // Your email or password was incorrect, please try again.
            if (strstr($detail, "The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn't understand how to supply the credentials required")) {
                throw new CheckException("Your email or password was incorrect, please try again.", ACCOUNT_INVALID_PASSWORD);
            }
            // Bad Request
            // u'scottrell@groupiad.com' is not a 'email'
            if ($this->http->FindPreg("/is not a 'email'\s+Failed validating 'format' in schema:.+?On instance:/s", false, $detail)) {
                throw new CheckException($title, ACCOUNT_INVALID_PASSWORD);
            }

            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $response = $this->http->JsonLog(null, 0);
        $fname = $response->response->customer->first_name ?? null;
        $lname = $response->response->customer->last_name ?? null;

        $this->SetProperty("Name", beautifulName("$fname $lname"));
        $customer_id = $response->response->customer->customer_id ?? null;

        if (
            !empty($this->Properties['Name'])
            && !empty($customer_id)
        ) {
            $this->SetBalanceNA();
        }

        $tablet_plus = $response->response->customer->tablet_plus ?? null;
        $tablet_pro = $response->response->customer->tablet_pro ?? null;

        if ($tablet_pro) {
            $this->sendNotification("tablet_pro is true, check");
        }

        if (empty($tablet_plus)) {
            $this->SetProperty('Status', 'Member');

            return;
        }
        $this->http->getURL("https://www.tablethotels.com/bear/v2/account/{$customer_id}/tablet-plus?locale=en");
        $response = $this->http->JsonLog();
        $status = $response->data->status ?? null;
        $expiration_date = $response->data->expiration_date ?? null;

        if (!isset($status) || !isset($expiration_date)) {
            $this->sendNotification("Something is wrong with the status");

            return;
        }
        $this->SetProperty('Status', 'Tablet Plus');
        $this->SetProperty('StatusExpire', $expiration_date);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.tablethotels.com/bear/account/profile", [], 20);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $email = $response->response->customer->email ?? null;

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "You caught us just when we are upgrading the site.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
