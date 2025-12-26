<?php

class TAccountCheckerRegalhotels extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        // Please enter the correct email format
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter the correct email format', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://sso-regalclub.regalhotel.com/login?redirect_url=https://www.booking.regalhotel.com/RwbeHandler.ashx&source=BookingEngine");

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        $data = [
            "AuthFlow"       => "USER_PASSWORD_AUTH",
            "AuthParameters" => [
                "USERNAME"  => $this->AccountFields['Login'],
                "PASSWORD"  => $this->AccountFields['Pass'],
                "DOMAIN"    => "sso-regalclub.regalhotel.com",
                "ExpiresIn" => null,
            ],
            "ClientId"       => "3idejuptc3bb8ua157bgh3kdqv",
        ];
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json",
            "X-Amz-Target" => "AWSCognitoIdentityProviderService.InitiateAuth",
            "Origin"       => "https://sso-regalclub.regalhotel.com",
            "Referer"      => "https://sso-regalclub.regalhotel.com/",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://cmsapi-regalclub.regalhotel.com/mobile/cognito/global-login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $reponse = $this->http->JsonLog();

        if (isset($reponse->encryptValue)) {
            // TODO:
            $this->http->GetURL("https://sso-regalclub.regalhotel.com/?redirect_url=https://www.booking.regalhotel.com/RwbeHandler.ashx&source=BookingEngine");
            $this->http->GetURL("https://www.booking.regalhotel.com/RwbeHandler.ashx?code={$reponse->encryptValue}");
            $this->http->RetryCount = 0;

            $headers = [
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "Content-Type"     => "application/json",
                "X-Requested-With" => "XMLHttpRequest",
            ];

            $this->http->PostURL("https://www.booking.regalhotel.com/RwbeHandler.ashx?log=landCode", '{"name":"https://www.booking.regalhotel.com","message":"https://www.booking.regalhotel.com"}', $headers);
            $this->http->PostURL("https://www.booking.regalhotel.com/RwbeHandler.ashx?log=rc_login_m1", "{}", $headers);
            $this->http->PostURL("https://www.booking.regalhotel.com/RwbeHandler.ashx?log=receiveMessage", '{"name":"https://www.booking.regalhotel.com","message":"1246b {\"message\":\"member\",\"code\":\"' . $reponse->encryptValue . '\",\"langCode\":\"yes\"}"}', $headers);
//            $this->http->GetURL("https://messages.guest-experience.triptease.io/01DFTVYXE8K53N7B26R/messages?language=en-US");
            $this->http->RetryCount = 2;
            $this->http->GetURL("https://www.booking.regalhotel.com/");
        }

        if ($this->http->FindSingleNode("//a[contains(text(), 'Log Out')]/@href")) {
            return true;
        }

        $message = $reponse->message ?? null;

        if ($message) {
            $this->logger->notice("[Error]: {$message}");

            if (
                $message == 'login error, please try again!'
                || $message == 'Incorrect username or password.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//a[@id = 'ctl00_hlUserInfo']"));
        // Card Number
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//span[@id = 'ctl00_lblCardNo']"));
        // Membership Class
        $this->SetProperty("MembershipClass", $this->http->FindSingleNode("//span[@id = 'ctl00_lblCardType']"));
        // Membership Expiry Date
        $this->SetProperty("MembershipExpiryDate", $this->http->FindSingleNode("//span[@id = 'ctl00_lblCardExpiry']"));
        // Points Balance
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'ctl00_lblMemberPoints']"));

        $this->http->GetURL("https://www.loyalty.regalhotel.com/Membership/PointsIndex.aspx");
        $expNodes = $this->http->XPath->query('//td[span[contains(text(), "Points to be expired on")]]');
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");

        foreach ($expNodes as $expNode) {
            $points = $this->http->FindSingleNode('preceding-sibling::td[1]', $expNode);
            $date = $this->http->FindSingleNode('following-sibling::td[1]', $expNode);

            if (
                $points > 0
                && (
                    !isset($exp)
                    || strtotime($this->ModifyDateFormat($date)) < $exp
                )
            ) {
                $this->SetProperty("ExpiringBalance", $points);
                $exp = strtotime($this->ModifyDateFormat($date));
                $this->SetExpirationDate($exp);
            }// if (!isset($exp) || strtotime($date) < $exp)
        }// foreach ($expNodes as $expNode)
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
