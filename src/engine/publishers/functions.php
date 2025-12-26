<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPublishers extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
//        $this->http->setHttp2(true);
        $this->http->setUserAgent(\HttpBrowser::PROXY_USER_AGENT);
        $this->http->SetProxy($this->proxyReCaptchaIt7());
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid Email.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://accounts.pch.com/login");

        $csrf = $this->http->FindSingleNode('//meta[@name = "csrf-token"]/@content');

        if (!$csrf) {
            return false;
        }

        $this->sendSensorData();

        $data = [
            "email"    => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
            "persist"  => true,
        ];
        $headers = [
            "Accept"       => "application/json, text/javascript, */*; q=0.01",
            "Content-Type" => "application/json; charset=utf-8",
            "X-CSRF-TOKEN" => $csrf,
            "X-XSRF-TOKEN" => $this->http->getCookieByName("XSRF-TOKEN"),
        ];
        $this->http->PostURL("https://accounts.pch.com/login", json_encode($data), $headers);

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog($this->http->FindPreg("/^cor\((.+)\)$/"));
        // Access is allowed
        if ($this->http->FindPreg("/Login Successful/")) {
            return true;
        }

        $message =
            $response->ValidationResponses->FieldOrDatabaseValidationResponse->Responses[0]->Message
            ?? $response->data->fields->email[0]->message
            ?? $response->data->fields->password[0]->message
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Sorry, the email you provided is not recognized. Please \'Register\' to create your own account today')
                || strstr($message, 'Sorry, the email you provided is not recognized. Please "Register" to create your own account today')
                || strstr($message, 'The password you entered is invalid or not found. Please try again or click forgot password')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 've exceeded the maximum number of incorrect sign in attempts')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.pch.com/");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@class, 'profile-name')]")));
        // Status
        $this->SetProperty("Status", beautifulName($this->http->FindSingleNode("//li[contains(@class, 'level-chart-item--current')]/@data-level")));

        $this->http->GetURL("https://accounts.pch.com/api/token-history/1");
        $response = $this->http->JsonLog();
        // Balance - Token Balance
        $this->SetBalance($response->data->balance->balance ?? null);
        // All Time Tokens
        $this->SetProperty("Tokens", $response->data->balance->creditTotal ?? null);
        // Total Tokens Used
        $this->SetProperty("TokensUsed", $response->data->balance->debitTotal ?? null);
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            return false;
        }
        $this->http->NormalizeURL($sensorDataUrl);
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];

        $sensorData = [
            "7a74G7m23Vrp0o5c9223591.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,404925,581884,1536,871,1536,960,1536,373,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8981,0.411681424205,822860290942,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,146,146,0;1,-1,0,0,167,167,0;-1,2,-94,-102,0,-1,0,0,146,146,0;1,-1,0,0,167,167,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://accounts.pch.com/login-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1645720581884,-999999,17605,0,0,2934,0,0,2,0,0,D13625F107AEE900BCD8774716D52A85~-1~YAAQXA3eF9P0Lip/AQAA1BeXLAfe+JKZ7h0LGmh6TF55IZ06chAzPbn1a6gctTaylriK78uHlqedwLx4CCkUlZoP+ZK1GjRJW1FaRthOOWVPNidomon5UMWAU9We1+m0AbE4ZuioolVCMxXhy7XVW+4FFKVvD7oiTYSFAEe0x6LpHNpf7ENXthYlmfgK5o4B3fIp7z3Y6WXfaFuAvnu96P+6eG8dzC1VxKxyBaznzsB8PJ3cld5wXI/7+DNGI3ZUox0SYVFudGceFGHfz5NxxlK4jVuayFQKzk+HtYPtq9dMCOFaDFlVFT0D2rRQhxCRbmuTs7PO6AiuUbKARpluE71JyDayBrfxPoZgrO9whqpXDH9tucS71ZPnbWLGJNAgl7qp3/+oEA==~-1~-1~-1,36338,-1,-1,30261693,PiZtE,48232,68,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,6362901594-1,2,-94,-118,86781-1,2,-94,-129,-1,2,-94,-121,;3;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9223591.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,404925,581884,1536,871,1536,960,1536,373,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8981,0.962999933481,822860290942,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,146,146,0;1,-1,0,0,167,167,0;-1,2,-94,-102,0,-1,0,0,146,146,0;1,-1,0,0,167,167,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,0,451;-1,2,-94,-112,https://accounts.pch.com/login-1,2,-94,-115,1,32,32,0,0,0,0,795,0,1645720581884,10,17605,0,0,2934,0,0,796,0,0,D13625F107AEE900BCD8774716D52A85~-1~YAAQXA3eF9P0Lip/AQAA1BeXLAfe+JKZ7h0LGmh6TF55IZ06chAzPbn1a6gctTaylriK78uHlqedwLx4CCkUlZoP+ZK1GjRJW1FaRthOOWVPNidomon5UMWAU9We1+m0AbE4ZuioolVCMxXhy7XVW+4FFKVvD7oiTYSFAEe0x6LpHNpf7ENXthYlmfgK5o4B3fIp7z3Y6WXfaFuAvnu96P+6eG8dzC1VxKxyBaznzsB8PJ3cld5wXI/7+DNGI3ZUox0SYVFudGceFGHfz5NxxlK4jVuayFQKzk+HtYPtq9dMCOFaDFlVFT0D2rRQhxCRbmuTs7PO6AiuUbKARpluE71JyDayBrfxPoZgrO9whqpXDH9tucS71ZPnbWLGJNAgl7qp3/+oEA==~-1~-1~-1,36338,920,-1225394234,30261693,PiZtE,48156,58,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,40,40,20,40,60,60,20,0,0,0,0,0,20,100,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5475-1,2,-94,-116,6362901594-1,2,-94,-118,90209-1,2,-94,-129,38f825eefa08fe20ea32d48bf22a815f52f60e337278131653ac853ff4333750,2,0,Google Inc. (Intel Inc.),ANGLE (Intel Inc., Intel(R) UHD Graphics 630, OpenGL 4.1 INTEL-18.4.6),5d46e782775916804ffc6ea60cfa44fb08a21a9cddbc005eb9996c50f34ea1a4,32-1,2,-94,-121,;22;5;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = 0; // array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $data = [
            "sensor_data" => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        return true;
    }
}
