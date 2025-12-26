<?php

use AwardWallet\Common\Parser\Util\PriceHelper;

class TAccountCheckerHomeaway extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://www.homeaway.com/';

    public static function GetAccountChecker($accountInfo)
    {
//        if (in_array($accountInfo["Login"],
//            ['iomarkus@protonmail.com', 'iormark@yandex.by', 'iormark@yandex.ru', 'iormark@ya.ru'])) {
        require_once __DIR__ . "/TAccountCheckerHomeawaySelenium.php";

        return new TAccountCheckerHomeawaySelenium();
//        }

        return new static();
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.homeaway.com/';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $success = $this->loginSuccessful();
        $this->http->RetryCount = 2;

        if ($success) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.vrbo.com/user/login?ckoflag=0&uurl=qscr%253Dreds%2526rurl%253D%252Ftrips");

        $pointOfSaleId = $this->http->FindPreg("/\"pointOfSaleId.\":.\"([^\\\]+)/");
        $uiBrand = $this->http->FindPreg("/\"uiBrand.\":.\"([^\\\]+)/");
        $deviceId = $this->http->FindPreg("/\"deviceId.\":.\"([^\\\]+)/");
        $guid = $this->http->FindPreg("/\"guid.\":.\"([^\\\]+)/");
        $traceId = $this->http->FindPreg("/\"traceId.\":.\"([^\\\]+)/");
        $tpid = $this->http->FindPreg("/.\"tpid.\":(\d+)/");
        $site_id = $this->http->FindPreg("/_site_id.\":(\d+)/");
        $eapid = $this->http->FindPreg("/.\"eapid.\":(\d+)/");
        $csrfPassword = $this->http->FindPreg("/\,.\"login.\":.\"([^\\\]+).\",.\"mf/");

        $headers = [
            "Accept"               => "application/json",
            "accept-encoding"      => "gzip, deflate, br",
            "brand"                => $uiBrand,
            "content-type"         => "application/json",
            "device-type"          => "DESKTOP",
            "device-user-agent-id" => $deviceId,
            "eapid"                => $eapid,
            "pointofsaleid"        => $pointOfSaleId,
            "siteid"               => $site_id,
            "tpid"                 => $tpid,
            "trace-id"             => $traceId,
            "x-mc1-guid"           => $guid,
            "X-REMOTE-ADDR"        => "undefined",
            "X-USER-AGENT"         => "undefined",
        ];

        if (!$csrfPassword || !$deviceId || !$uiBrand || !$pointOfSaleId) {
            return $this->checkErrors();
        }

        $csrfEmail = $this->http->FindPreg("#.\"loginemail.\":.\"([^\\\"]+).\"#");
        $csrfOTP = $this->http->FindPreg("#.\"verifyotpsignin.\":.\"([^\\\"]+).\"#");

        $captcha = $this->parseFunCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            'channelType'   => 'WEB',
            'email'         => $this->AccountFields['Login'],
            'username'      => $this->AccountFields['Login'],
            'passCode'      => $this->AccountFields['Pass'],
            'password'      => $this->AccountFields['Pass'],
            'rememberMe'    => true,
            'scenario'      => 'SIGNIN',
            'atoShieldData' => [
                'atoTokens' => [
                    'email'      => $this->AccountFields['Login'],
                    'fc-token'   => $captcha,
                    'rememberMe' => '',
                ],
                'devices' => [
                    [
                        "payload" => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"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","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1423,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":26842,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"9001,www.vrbo.com,Vrbo,ULX","requestURL":"https://www.vrbo.com/login?enable_login=true&redirectTo=%2Ftraveler%2Fth%2Fbookings%3Fvgdc%3DHAUS%26preferlocale%3Dtrue","userAgent":"' . $this->http->userAgent . '","placement":"LOGIN","placementPage":"9001","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                        'type'    => 'TRUST_WIDGET',
                    ],
                ],
                'csrfToken' => $csrfPassword,
                'placement' => 'login',
            ],
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.vrbo.com/identity/user/password/verify", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        // You have reached max attempts to resend code
        if ($error = $this->http->FindPreg('/"failure":\{"field":"EmailOtp","message":"(You have reached max attempts to resend code)"/')) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }

        if ($csrfEmail && $csrfOTP) {
            if ($this->parseQuestion($headers, $csrfEmail, $csrfOTP)) {
                return false;
            }
        }

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (empty($response->failure->message) && $this->loginSuccessful()) {
            return true;
        }

        $field = $response->failure->field ?? null;

        if ($field) {
            $this->logger->error("[Error]: {$field}");

            if ($field == 'email') {
                throw new CheckException("Enter a valid email.", ACCOUNT_INVALID_PASSWORD);
            }
        }
        $message = $response->failure->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Invalid password credentials'
                || stristr($message, "Email={$this->AccountFields['Login']} is not in a valid format")
            ) {
                throw new CheckException("Email and password don't match. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->Response['code'] == 200 && $this->http->FindPreg('/","identifier":\{"flow":"BETA_CONSENT","variant":"BETA_CONSENT_\d+"\},"csrfData":null,"failure":null,"scenario":"/')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->Response['code'] == 401 && $this->http->FindPreg('/\{"cmsToken":"NotNeeded","identifier":null,"csrfData":{"csrfToken":"[^\"]+","placement":"login"},"failure":null,"scenario":"SIGNIN"\}/')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            throw new CheckRetryNeededException(3, 0);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindPreg("/\"firstName.\":.\"([^\\\]+)/") ." ". $this->http->FindPreg("/\"lastName.\":.\"([^\\\]+)/")));
        // Status
        $this->SetProperty("Tier", $this->http->FindPreg('/"One Key ([^\"]+) Tier."/'));

        if (!empty($this->Properties['Name']) || $this->http->FindPreg("/\"user_authentication_state.\":.\"AUTHENTICATED.\"/")) {
            $this->SetBalanceNA();
        }
        // Balance - OneKeyCash
        $this->SetBalance($this->http->FindSingleNode("//h2[contains(text(), ', you have $')]", null, true, "/, you have (.+) in OneKeyCash to use/"));
    }

    public function ParseItineraries(): array
    {
        $this->http->GetURL('https://www.vrbo.com/traveler/th/bookings');

        $upcomingNodes = $this->http->FindNodes("//section[contains(@class,'TripCards--upcoming')]//a[contains(@class,'Card__vertical') and contains(@href,'/conversation')]/@href");
        $cancelledNodes = $this->http->FindNodes("//section[contains(@class,'TripCards--cancelled')]//a[contains(@class,'Card__vertical') and contains(@href,'/conversation')]/@href");
        $pastNodes = $this->http->FindNodes("//section[contains(@class,'TripCards--past')]//a[contains(@class,'Card__vertical') and contains(@href,'/conversation')]/@href");

        // no itineraries
        $notItineraries = $this->http->FindSingleNode('//*[not(self::script)][contains(text(), "You don\'t have any past or upcoming trips.")]');

        if ($notItineraries && empty($upcomingNodes) && !$this->ParsePastIts) {
            return $this->noItinerariesArr();
        }

        foreach ($upcomingNodes as $node) {
            $this->logger->debug('Parsed itinerary:');
            $this->http->NormalizeURL($node);
            $this->http->GetURL($node);
            $this->parseItinerary();
        }

        $this->logger->info("Cancelled Itineraries", ['Header' => 2]);

        foreach ($cancelledNodes as $node) {
            $this->logger->debug('Parsed itinerary:');
            $this->http->NormalizeURL($node);
            $this->http->RetryCount = 0;
            $this->http->GetURL($node);

            if ($this->http->Response['code'] == 500) {
                sleep(5);
                $this->http->GetURL($node);
            }
            $this->http->RetryCount = 2;

            if ($this->http->Response['code'] == 500
                && ($error = $this->http->FindSingleNode("//h2[contains(.,'Something went wrong')]/following-sibling::p[contains(.,'Check back shortly')]"))) {
                $this->logger->error($error);

                continue;
            }
            $this->parseItinerary(true);
        }

        if ($this->ParsePastIts) {
            $this->logger->info("Past Itineraries", ['Header' => 2]);

            foreach ($pastNodes as $node) {
                $this->logger->debug('Parsed itinerary:');
                $this->http->NormalizeURL($node);
                $this->http->RetryCount = 0;
                $this->http->GetURL($node);

                if (!$this->http->FindSingleNode("//span[contains(text(), 'Reservation ID')]/following-sibling::span")
                    && !$this->http->FindSingleNode("//*[@class = 'trip-dates-section__arrival']/*[@class = 'trip-dates-section__date']/h3")
                    && $this->http->FindSingleNode("//span[contains(text(), 'Booking status')]/following-sibling::span")) {
                    sleep(5);
                    $this->http->GetURL($node);
                }
                $this->http->RetryCount = 2;

                if ($this->http->Response['code'] == '500' && ($error = $this->http->FindSingleNode("//h2[contains(.,'Something went wrong')]/following-sibling::p[contains(.,'Check back shortly')]"))) {
                    $this->logger->error($error);

                    continue;
                }
                $this->parseItinerary();
            }
        }

        return [];
    }

    public function ProcessStep($step): bool
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->sendNotification('2fa // MI');

        $pageurl = 'https://www.vrbo.com/verifyotp?enable_login=true&redirectTo=%2F%3Fvgdc%3DHAUS&scenario=SIGNIN&path=email';
        $captcha = $this->parseFunCaptcha("FB58AF3E-E7BE-4AF8-BB16-E7CDB94CADCF", false, $pageurl);

        if ($captcha === false) {
            return false;
        }

        $data = [
            'username'      => $this->AccountFields['Login'],
            'email'         => $this->AccountFields['Login'],
            'rememberMe'    => true,
            'channelType'   => 'WEB',
            'passCode'      => $answer,
            'scenario'      => 'SIGNIN',
            'atoShieldData' => [
                'atoTokens' => [
                    'fc-token'   => $captcha,
                    'rememberMe' => '',
                    'code'       => $answer,
                ],
                'placement' => 'verifyotpsignin',
                'csrfToken' => $this->State['csrf'],
                'devices'   => [
                    [
                        'payload' => 'eyJjb25maWd1cmVkRGVwZW5kZW5jaWVzIjpbeyJuYW1lIjoiaW92YXRpb25uYW1lIiwicGF5bG9hZCI6IjA0MDBZZ3dNSXNWYlJxa3BJZnEyTEx0TC9WOUJrekVxQml1TWZ1WkRNQlBONklMZTg5ak5sT0tnZDVWNXNwcTE4eU1nZk1nU0xDamwrK3U2MzNpaTVlVmU4dzFQV3hJUmVTUVh4TkRUYlZxM3lzQzAydVlIeGMwVEppQVJLQzYvVWlBWlpPWTZvc0RkS2RFckU4cXh2bXNIVnFVL2FOTVAxcTYyQUdQMHJSdFlHajZkcTdsSGhOaXl0MzhLQWs2N0tSR21GdElpRW5YZ1FvN3hWYWppalgvb2xBL01wTmorZkg1ZE9iWVdyQitUWkdmYi9wdXBUellqR3J6RjZtVWdEZ1RSYVVwOHkvcVc2bFk2cHpVd1hnWENjQkpFdmc3QnZMeW4vcHI3MW9ENTYxcThxZ0N1N0t4RVpYMmR0TWRZSVV1RmMwN3R4aDIva0lWT2ZkSngrc0Y5aEtLS3VYUm1mK2o0TWVqZnlEcy9sRlZ4QkFWNk9zUEdEeDJycVVCclBRRGo0TkVsWHBGMGNpSzVGU3dwam5pU1RlOVJKc240dTNQZEhmN1dVdGlvZGtRVTdqZCsveWhxcm1OcE0za0hRay9VelNPZWxmazFndXh2NDdtTittbFhiN0Z3TmY2WEplcVgvTWtxTlFQL0xEckZNNTRKUDlwenNGdW9PZHFiaXNxQVR0bWxiUkRRek5XSUh0N1RjTUZMWUpvU0JjdHBFa29lb2xqZGJleGJkYVNZbW9DditPTkkwZ1N2bG9lQnFYTlI0czlBUHNuWmg3dE8xQ0hlcnFZWm04d0VoY0JURUdBc0dJNTlQNVVGdndoYThaNnUxaFRGYWxiMWM0TGp2N0YrVVYwdEErRVFJSWlqL0JTRTRQOFRkUVZscldyQlNXZVFCZWlPVGhzWDNVLzJxZjdpaHdxRHVWWDY5K1BqTXptb1c3MTZmV2tqN3BLejREMm9YOHhkZEJ5ZlNaMlFOdTg5Q0lDMlFGOXQvN1RUdTR6NmFVeTV4V1c3bU93SzdXRGNXaGJEaU9JakNHUWQ3UmhLMTF2eDBOQzFVSmdBMUJRWkhqbDVXamNtKzRqZE9JT3R1eW5HcjJoQ09IdnI1WHgwTXlJeW5sa3BNcE1DQzhGbkNmZlNtckFXTFlUWDNOcDJXWlcwdXlZaFNMVFlSdFUrWWpaMCt3VUlUVmRGODM0ZG5iVVJQaXVGb2RqMWdMaG5KbzhQQk9peExYR0JJQ0ExSnBRWUFGVXJEbGNzcUh4TVJ5TUEzY2h1QUJ4RzFOeU1kZkZKUVRpMktIVTF6ZkhZUVJKWnVFQkhidGwyTDYrTzRMUUJvVWlrYzF5TDN0NlhsU0k3eWd5azRWVDlZUU1na0tyQitvVElHZmNkRWZiMFRZK1hyeTgzUmVsNFcxSEhGRkFONlExeDgwVWRDZEdkeXF3d2JUS2RlU2c0UCt1akhSSDI5RTJQbDY4dk4wWHBlRnRSeHhSUURla05jZk5GSFFuUm5jcXNNRzB5blhrb09EL3JveDBSOXZSTmo1ZXZMemRGNlhoYlVjY2dxSHh5UFdoNWlobGJJZFJtUm0vN0J3dVg0TUFnSktYTFE1TjJLWUZWbVdsSFZ5YmgzZW52MVlZSVR5elJ3di9qTmNRSDJSZG9tWVJ4aXZtRGJtVk9NNm04S1psbXZDTzJtM3g2emFrcFBBcDhGQythMkJFOHkvWkdpL09aYzFQV0QxRWs5NEUySjUzWnhmYUFUdEJML0hyeHdIazZJcXZkT0lPdHV5bkdyOGZ6MnRzd3RMbkhQWDAxY1h5N0RxV2VaWnV2cUZnaFJDRE1ud1Vsci83N3NrbmEvZXVXbVY2emlCQmtWYlpxTkY2YWFxYUUzVmwyeWFzeDRqeEczajRTQUt6eEdBaGxjRFNzcmtud2VFOEY4akxFMFFmOW1TQmZBSFlxdzBsUTRYaFE2Z0JUbnZsWWRkblhxODR3QSt6VTduNEMrTkJTOWREU1pnT0o4SU8wNDJEV1d5K3lTR3B3SGszVnRVWHJBQUlENk9XY2dsK2hENnprSnNIZlVZWUJna0dpWkYxT3h2UXh2MldEUVVPUW1leDRIUkxseFNyYjhSeldkelpLNFJwTkdmUWJVMEpsMm9YeHpWRjlvNStBbWVOSEg2K3c2cHpya1JRWUs3OUhrRXlBR204bnJIMVF1VnRPaFkvMUgzT1RwcTl3NktHbkpJbFIybGx3NWNRdk11NjFFZkV3Mm0wOFRFQzVXVWpSMmhuOUZSZjJJUFZMM3dMU0c2c0E4UW9jTDZNZTNoemM5a1U5KzdTV2NGQ1VHU3dQLzBoTmpCVFpHMDZpdnZNM3VBbDRHU0tIT0lpKzlXVHczRUdEK2hzcFlseDFRUk9wTTMydUI5dGtwU3JMNWFNVDVhZHRBNVN6dVYxZFQvSGpqd2NMNXNqR1VMczlrUXdFbTBXTmNnSWVQci9wZnBrNWdJdHVGZlZBYThPWVZZbUZwTDZjN2NBaGdmUjQxQ3dlQVNYamlBVEpHTW1nck43dzB2dFVscE93VDVPMnp5cEs2bmVSNzVMbFUwU2hPRjRSemt5OUp0ejBITmNmaGc5M1N0L25sKzdlRTBzWTNTaWJRbkxtcGpERWRXM3JveVZPSXBUWTlHMTQxNGNaOEI4TzBnVU8xR0tzSUlyQnpMVkVqMUZPWVNXbjRvWEVyS1VGcXgzc0dVaTgrYm1YR2VPVDdNdUxVQUtzNXpGbVJjSWdzVEplRE82N2xKakVmUktoR0c4Wm51QWZlSTRvR1psTGtkLzhNQS9pciszcUtZeXZLTk92OHdSN2ZTU2pyeUE4aU1oS05haW93akRvTE9tZFBtK1pKL3Q2Y1lDaXhYZk9SMVIvT0kwMXFUdHhrckV5c0FzNVpqQXpYVkdvVktLVWNzMlJhcXBtOEhScDBZaVhtZVZGS0dMYkgvd0thbFU3RWxBTVpCQVFZeG9rTDdmOVM3RjRQTzJtTjlpamhKY0g2VlNjKzBxekZXb01raXljYUhONW1BVHZMdU5vMWkrak9GczJEUVVkZnI0eDBqZm55eitscjl3Q25CeXRqa2Y3QWlaZmIxSXEwcGVkeVhEd0tNd21pNkxPLzloeHRCWHRLbTZ1RWlJcDl6OFRUVlZBUjFCUlZFamtsb2VoQ29xYlRWbElyWENvSVVPaFA0bkwyYUNLQXh5ck9hM05jL3AvMXk5UEhraENUSGcrTXMwUTBGRUQrT0pWOEp1dEVwQWV5ZE1qVHJyNFpKb09aTFFnWklXVDZNV1NmVVA5bDExVWxvdndhNHQ0STJOMG00enBwNkNuUWJDWmdrZGFqSHBUVnFQZ0ZON0JCaHQ0ajdJYmo0VWN6b2J3ckl3ZEFTQS9ib0RpT3cvTUpKWjg4ZnZkd3RWaEF5aGtwdlExLzlTMmpoTzNuOGlYVmpObUk3dDRLYWxxWjJzdkZua0pPWDhiSzA0a1ZHTnRHeWZYbHJHSGRvVVVPTUx3VkRZenRpV3Jsd1NFV2ZqNWNneXEwVmJaUFFTMHNwTy9kNHN0Z0pleFM3UTdHUT09Iiwic3RhdHVzIjoiQ09NUExFVEUifV0sImRpYWdub3N0aWNzIjp7InRpbWVUb1Byb2R1Y2VQYXlsb2FkIjoxMjA4LCJlcnJvcnMiOlsiQzExMDQiLCJDMTAwMCIsIkMxMDA0Il0sImRlcGVuZGVuY2llcyI6W3sidGltZVRvUHJvZHVjZVBheWxvYWQiOjI3MDU5LCJlcnJvcnMiOltdLCJuYW1lIjoiaW92YXRpb25uYW1lIn1dfSwiZXhlY3V0aW9uQ29udGV4dCI6eyJyZXBvcnRpbmdTZWdtZW50IjoiOTAwMSx3d3cudnJiby5jb20sVnJibyxVTFgiLCJyZXF1ZXN0VVJMIjoiaHR0cHM6Ly93d3cudnJiby5jb20vbG9naW4',
                        'type'    => 'TRUST_WIDGET',
                    ],
                ],
            ],
        ];
        unset($this->Answers[$this->Question]);

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.vrbo.com/identity/email/otp/verify', json_encode($data), $this->State['headers']);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if ($this->http->Response['code'] == 200 && $this->http->FindPreg('/","identifier":\{"flow":"BETA_CONSENT","variant":"BETA_CONSENT_\d+"\},"csrfData":null,"failure":null,"scenario":"/')) {
            $this->throwProfileUpdateMessageException();
        }

        if (!property_exists($response, 'failure')
            || $response->failure !== null
            || (property_exists($response, 'status') && $response->status !== true)
        ) {
            $message = $response->failure->message ?? null;

            if (stripos($message, 'OTP verification failed, provided OTP is invalid') !== false) {
                $this->AskQuestion($this->Question, 'Invalid code, please try again', "2fa");

                return false;
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindPreg('/"identifier":\{"flow":"PASSWORD","variant":"PASSWORD_3"\},"csrfData":/')
            && $this->http->FindPreg('/"placement":"verifyotpsignin"\},"failure":null,"scenario":"SIGNUP"/')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindPreg('/"identifier":\{"flow":"PROFILE_DETAIL","variant":"PROFILE_DETAIL_\d+"\}/')
            || $this->http->FindPreg('/"identifier":\{"flow":"ONE_IDENTITY","variant":"ONE_IDENTITY_\d+"\}/')) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->loginSuccessful();
    }

    private function parseQuestion($headers, $csrfEmail, $csrfOTP): bool
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg('/\{"cmsToken":"NotGenerated","identifier":null,"csrfData"/')
            || $this->http->FindPreg('/"cmsToken":"NotNeeded","identifier":null,"csrfData":.+?"placement":"login"\},"failure":null,"scenario":"SIGNIN"/')
            || $this->http->FindPreg('/"identifier":\{"flow":"BETA_CONSENT","variant":"BETA_CONSENT_\d+"\}/')
            || $this->http->FindPreg('/"identifier":\{"flow":"ONE_IDENTITY","variant":"ONE_IDENTITY_\d+"\}/')
        ) {
            $data = [
                'username'      => $this->AccountFields['Login'],
                'email'         => $this->AccountFields['Login'],
                'resend'        => false,
                'channelType'   => 'WEB',
                'scenario'      => 'SIGNIN',
                'atoShieldData' => [
                    'atoTokens' => new stdClass(),
                    'placement' => 'loginemail',
                    'csrfToken' => $csrfEmail,
                    'devices'   => [
                        [
                            'payload' => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"0400rFK0xCscs7eVebKatfMjIL0DlP25BqM+S9xeYVb8G8IQ0V5eJDnpvNFu1N6XayHZ5fGW5ZlOMYcpP+U/ow6B55RvFCMSbl5xsqD6MTN95fQNl17v7KIbJt7z2M2U4qB34yvRhXnDkIvOI6x43qvV/C/xvCwL50uNa0jdtCKsSKCMd+lYGc5Lt0/teK6wzGX440JwBIixsUHmx2vNwg2Qepnlac9gMrtHSCcEPP13kFSu6O+mu6rD44frKTZ440c9hqhbA4T/1Lh3m7ebYcyPRqcdvOaikN2qKULPxBs5mS8mLgsfSImYQydp/My5xsOpfvLxvyQ/zn9rRjLkmcdik84jrHjeq9X8L/G8LAvnS41rSN20IqxIoIx36VgZzku3T+14rrDMZfjjQnAEiLGxQcCq9DH/qIFm7l7z/4PvaMJxKa5ScHurDbx4kBPDhN1CIKwR+cb8pJlDyrXEs0yPFpIFqT+kaBgd5v+L5TKZh1JIkDv9euBifL4sP/rDpQqRf7qhSLHn4X668UW/DpoFFJAV+ZZLc8a70ZuZJFR0zYFfzl5qiHYuOrROpPzOzPf13ZV8YJVNIUPZCB2vuiEt/RZZ7hquPMFUrd58uV3EFRWaDcRyWlsPIYgvdKFIFOfTOALO/86gorFme/hJzHwuC2sHjQubcKhD+MTw3KUeezT3Hg+2Oq3BTYDB5mrABueSbZWp/5NFsh1iTDOguRPLm+1sRoFJwLN+kuN7pHlNT+U9GC17jlnhc+rwxyG0LqgC6Yf0/pTJUUwDVymmPVngsgydKgnOIDKXblUrdgWkJJT58QAB3NffQB3+Dd6SKNck5jxeFvm7v6P4K/PpO4bsaKV46r0SrTSJLuz+HM3F1yn9LhP5/51Z3C8MWvX7mP09LD4mV3TQqnyTnmPj+ncLdRKtWJRlWp63P8qFSAZ42orAdp/1/wwkJ7XbUlKdexkPRh35hSsrB0AKSs4KVYKB4qkb952p5qwSRw6NL2GAwt+X8yQ9pt4DpHSw7PtSQtURum/NXiqfFU1FefNUJkIj/kQwVr+xZSPZ3BOJ2d5nlZ52+ncQ4qJLHu4cY6Mt9WpRZpQRyqKemuJqTUnBSvUYsNwTidneZ5Wedvp3EOKiSx7uHGOjLfVqUWaUEcqinpriak1JwUr1GLDcE4nZ3meVnnb6dxDiokseTnOtpbEdaovEpkWTlnMi5g4mmYKxVbRrv0w6dFMy2EuOjA2+wLjlHAw1htwGoheBiE8nFfkKa53huQiJuHfIakuHuH9BhO0tZ218bK28BP5uVSt2BaQklKpKlT1PGOcgfobiOhcONJPNnBg89k56rF9ki3gZNYvqJ8XiEIyU0CF0sOz7UkLVEbpvzV4qnxVNT59yZMLEen9ZEBlF3CwJErGAqHKF92sv0O/E9ABp/9da+8GxQj+MNvyY8psLEkPL0PTwnfvCcYkQ6yIhr3QJEs5uK/niql9HJP61tHnSuc1CZw0scim3OeZFb4hcqt0yyzR7++NeG1r9cUc3lnMihGKAq2q0AOWxfkTz8aGQSxxOeUevoHxdjU59qpox3l1XkuyS99GcE2Ns1TwRpHpCSz78XZLVcp6e2n1AV9TjYwbz3wafI/WYHEsTCs2TKRSUB860/YtAXmzCSuwj+Acg2f2vFnup4QVZOEUGoVSakIq0bl8O++WORSiRlB4zzByYV5BlioOZde35LQ9wqMOHFD9mO1u0CUWvlxo53Jon/8IagwKYJeDhJQJthhlo0Dnn6RsAk1w41Ytz+jhoGvOy/cJaz3Upn4g9mZpHcBN21gaQy1sVBwxanS0FSbWlqzvNglTyVsX8cQTx8A39jYLu1XCHMlMlcepPk+2jS6qDfw2ku6OWNJfIoVhByCYxc22l+o0tCDnp9AlFhJKyMgZypm8czElJjtdFmVQI1ynhkIgWVqwLuGICv0N0VSgA3aSxDrC2Fj1gMxpNqa+tFdNBTrrE7eVz75WJSdYogTGekWE/1vPmh5kD1HOEJCBIIpiOhVPBsZRZ8SDBiYwWbjaXLvgIYSLTxG4sojveBLVAY5CttZhmo2xLdgZ+lD6aW6RMCRJZ+F8jVelKJUkBQo/UPv5x003OckU+zy5RNgqag+8FwYbaw3RkcEKalSp0adrBv9eOvsyvTBVPcHli7fPqCzj0nEo3ZjoPD1AfxKR/T6D7pLYBr1iG8/boT2Wl9J3REUCAAqfQFq+MsMdzyk02gDTXisny5oICTaCdaJ7Urfqa75l2MDM0+bvF+DkbGquKCu9M15MH2pytc3FhJ749epSkOINbAWYgf914BhbIwHzzK+fh9iZrke8BIDpDtF0jyT+s2Q2VWMjUiDeywT+wYJztM+y19kf6z2yujmp243GvtsbrgkRJ1WdIKoJK+zyXuS0usXJnXX2hJj1Fbxib32Uyd5Veuz5ggz26Fbwq67aYSp6eFvnj7c2QHdOpA+3iPJOwLeF4txyCGMoXbD+tOGEEZXTBxvvehI+2MFJX/g+3kFf91J67WrVwsgPYUnpfdYeqeGGlCbC7Wiv3gWKhvMRwyOa22rf1stqbaZ+Ngmsbn9cm51fOgvLvBoY0HZS3L4RA6xWCQselygYFmIwy9ud0sE5ae3Cm15362ubh5w+Nn0RPTzA/kPjsiVuwYN+kc6UreJ62NQhMCoLOUbQOUATcawACKVix","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1916,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":7551,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"9001,www.vrbo.com,Vrbo,ULX","requestURL":"https://www.vrbo.com/login?enable_login=true&redirectTo=%2Ftraveler%2Fth%2Fbookings%3Fvgdc%3DHAUS%26preferlocale%3Dtrue","userAgent":"Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/112.0","placement":"LOGIN","placementPage":"9001","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                            'type'    => 'TRUST_WIDGET',
                        ],
                    ],
                ],
            ];

            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.vrbo.com/identity/email/otp/send', json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            // todo: shoild be added 2fa support in the future
            // see refs #22167
            if (isset($response->identifier->variant) && $response->identifier->flow == 'OTP') {
                $this->State['headers'] = $headers;
                $this->State['csrf'] = $csrfOTP;
                $this->AskQuestion("Enter the secure code we sent to your email. Check junk mail if it’s not in your inbox.", null, "2fa");

                return true;
            }
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.homeaway.com/traveler/profile/edit', [], 20);

        if (
            $this->http->FindPreg("/\"authState.\":.\"AUTHENTICATED/")
            // {"cmsToken":"NotNeeded","identifier":{"flow":"ORIGIN","variant":"ORIGIN"},"csrfData":null,"failure":null,"scenario":"SIGNIN"}
            || $this->http->FindPreg('/"identifier":\{"flow":"ORIGIN","variant":"ORIGIN"\},"csrfData":null,"failure":null/')
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseItinerary($cancelled = false): void
    {
        $this->logger->notice(__METHOD__);
        $bookNumber = $this->http->FindSingleNode("//span[contains(text(), 'Reservation ID')]/following-sibling::span");
        $this->logger->info("Parse Itinerary #{$bookNumber}", ['Header' => 3]);
        $hotel = $this->itinerariesMaster->createHotel();
        $hotel->general()->confirmation($bookNumber, 'Reservation ID');

        if ($status = $this->http->FindSingleNode("//span[contains(text(), 'Booking status')]/following-sibling::span")) {
            $hotel->general()->status($status);

            if ($status == 'Cancelled' || $status == 'Withdrawn') {
                $hotel->general()->cancelled();
            }
        } elseif ($cancelled) {
            $hotel->general()->cancelled();
        }

        $hotel->hotel()->name($this->http->FindSingleNode("//h1[contains(@class, 'text-inverse')]"));

        if ($address = $this->http->FindNodes("//*[contains(@class, 'PropertyMap__address__line')]")) {
            $hotel->hotel()->address(join(', ', $address));
        } elseif ($this->http->FindSingleNode("//*[contains(@class, 'PropertyMap__address__line')]", null, false,
                '/Available in \d+/')
            || in_array($hotel->getStatus(), ['Pending', 'Booking request'])) {
            $hotel->hotel()->noAddress();
        }
        // .rental.address":{"city":"Todos Santos","cityRegionPostal":"Todos Santos, Baja California Sur 23300","street":"Las Liebres","__typename":"RentalAddress"},
        elseif ($address = $this->http->FindPreg('/\.rental.address":(.+?"RentalAddress"}),/')) {
            $address = $this->http->JsonLog($address);
            $hotel->hotel()->address("{$address->street}, {$address->cityRegionPostal}");
        } elseif ($hotel->getCancelled() || $this->http->FindSingleNode("//*[contains(text(),'Your booking no longer qualifies for a refund.')]")) {
            $hotel->hotel()->noAddress();
        } else {
            // TODO: hard code
            $propId = $this->http->FindSingleNode("//span[contains(text(),'Property ID:')]/following-sibling::span");

            if (in_array($propId, ['560734', '303320', ['7592957']])) {
                $hotel->hotel()->noAddress();
            }
        }

        if ($phone = $this->http->FindSingleNode("//div[contains(@class, 'PropertyContact__name-container')]/following-sibling::h4")) {
            $hotel->hotel()->phone($phone);
        }

        $date = $this->http->FindSingleNode("//*[@class = 'trip-dates-section__arrival']/*[@class = 'trip-dates-section__date']/h3");
        $time = $this->http->FindSingleNode("//*[@class = 'trip-dates-section__arrival']/*[@class = 'trip-dates-section__time']/h3");
        $hotel->booked()->checkIn2("{$date}, {$time}");

        $date = $this->http->FindSingleNode("//*[@class = 'trip-dates-section__departure']/*[@class = 'trip-dates-section__date']/h3");
        $time = $this->http->FindSingleNode("//*[@class = 'trip-dates-section__departure']/*[@class = 'trip-dates-section__time']/h3");
        $hotel->booked()->checkOut2("{$date}, {$time}");

        if ($guests = $this->http->FindSingleNode("//span[contains(text(), 'Guests')]/following-sibling::span")) {
            $hotel->booked()->guests($this->http->FindPreg('/(\d+) adult/i', false, $guests));

            if ($kids = $this->http->FindPreg('/(\d+) (?:child|kid)/i', false, $guests)) {
                $hotel->booked()->kids($kids);
            }
        }

        if ($cancellation = $this->http->FindSingleNode("//ul[@class = 'policy-list']")) {
            $hotel->setCancellation($cancellation);
            // 100% refund for cancellations requested by 10/16/2020 at 11:59 PM (property's local time).
            if ($m = $this->http->FindPregAll('#100% refund for cancellations requested by (\d+/\d+/\d+) at (\d+:\d+(?:\s*[AP]M)?)#', $cancellation, PREG_SET_ORDER)) {
                $hotel->booked()->deadline2("{$m[0][1]},{$m[0][2]}");
            } // 100% refund if canceled at least 14 days before arrival date.
            elseif ($m = $this->http->FindPreg('/100% refund if canceled at least (\d+ days?) before arrival date/', false, $cancellation)) {
                $hotel->booked()->deadlineRelative($m);
            }// Bookings canceled at least 60 days before the start of the stay will receive a 100% refund
            elseif ($m = $this->http->FindPreg('/Bookings canceled at least (\d+ days?) before the start of the stay will receive a 100% refund/', false, $cancellation)) {
                $hotel->booked()->deadlineRelative($m);
            } // 100% refund if you cancel by Dec 15, 2020.
            elseif ($m = $this->http->FindPreg('/100% refund if you cancel by (\w+ \d+, \d{4})\./', false, $cancellation)) {
                $hotel->booked()->deadline2($m);
            }

            // No Refund
            elseif ($cancellation == 'No Refund'
                || $this->http->FindPreg('/Bookings canceled at least \d+ days? before the start of the stay will receive a 50% refund/', false, $cancellation)
                || $this->http->FindPreg('/50% refund if canceled at least \d+ days? before arrival date/', false, $cancellation)
                || $this->http->FindPreg('/Your booking no longer qualifies for a refund/', false, $cancellation)
                || $this->http->FindPreg('/Bookings at this property are non-refundable/', false, $cancellation)) {
                $hotel->booked()->nonRefundable();
            }
        }

        if ($price = $this->http->FindSingleNode("//div[@class = 'booking-section__line']/*[contains(text(), 'Total')]/following-sibling::strong")) {
            // $8,249.00
            if ($m = $this->http->FindPregAll('/^\s*(.+?)\s*(\d+.+)/', $price, PREG_SET_ORDER)) {
                $hotel->price()->currency($this->normalizeCurrency($m[0][1]));
                $hotel->price()->total(PriceHelper::cost($m[0][2]));
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'BRL' => ['R$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function parseFunCaptcha($key = null, $retry = true, $pageurl = null)
    {
        $this->logger->notice(__METHOD__);
        $key =
            $key
            ?? $this->http->FindSingleNode('//iframe[contains(@src, "-api.arkoselabs.com")]/@src', null, true, "/pkey=([^&]+)/")
            ?? $this->http->FindPreg('/\?pkey=([^\\\]+)/')
        ;

        if (!$key) {
            return false;
        }

        if ($this->attempt == 2) {
            $postData = array_merge(
                [
                    "type"                     => "FunCaptchaTaskProxyless",
                    "websiteURL"               => $pageurl ?? $this->http->currentUrl(),
                    "funcaptchaApiJSSubdomain" => 'expedia-api.arkoselabs.com',
                    "websitePublicKey"         => $key,
                ],
                []
//            $this->getCaptchaProxy()
            );
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($recognizer, $postData, $retry);
        }

        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $pageurl ?? $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }
}
