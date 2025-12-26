<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerWalgreens extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
//    use OtcHelper;

    private const REWARDS_PAGE_URL = 'https://www.walgreens.com/youraccount/default.jsp';

    // TODO: if parser will broken, see case https://redmine.awardwallet.com/issues/18281

    private $json = false;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && (strstr($properties['SubAccountCode'], "walgreensBeautyEnthusiast"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
        */
        $this->http->setHttp2(true);
        /*
        $this->http->SetProxy($this->proxyReCaptcha());
        */
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.walgreens.com/login.jsp');
        $csrf = $this->http->FindSingleNode("//meta[@name = '_csrf']/@content");

        if (!$csrf) {
            return $this->checkErrors();
        }

        if ($this->attempt == 1) {
            $key = $this->getCookiesFromSelenium();
        } else {
            $key = $this->sendSensorData();
        }

        $this->http->setDefaultHeader('Content-Type', 'application/json; charset=utf-8');
        $data = [
            "username" => $this->AccountFields["Login"],
            "password" => $this->AccountFields["Pass"],
        ];
        $this->http->setDefaultHeader('X-XSRF-TOKEN', $csrf);
        $this->State['X-XSRF-TOKEN'] = $csrf;
        $headers = [
            'deviceInfo'   => '{"userAgent":"' . $this->http->userAgent . '","webdriver":"not available","deviceMemory":8,"hardwareConcurrency":16,"screenResolution":[960,1536],"availableScreenResolution":[880,1536],"timezone":"' . 'America/New_York' . '","addBehavior":false,"plugins":"5718c5b0e4804c02811d26cb3b867e9f","canvas":"b7e763a915c35efbb202091d17ef2cc3","webgl":"5256f6b1b473b6a768e370b70ca67c07","touchSupport":[0,false,false],"fonts":"a0a442e8c6459232f8a92c1c276348e6","audio":"124.04345808873768","edgescape":"georegion=288,country_code=US,region_code=VA,city=ASHBURN,dma=511,pmsa=8840,msa=8872,areacode=703,county=LOUDOUN,fips=51107,lat=39.0438,long=-77.4879,timezone=EST,zip=20146-20149,continent=NA,throughput=vhigh,bw=5000,network=aws,asnum=14618,network_type=hosted,location_id=0"}',
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept'       => 'application/json, text/plain, */*',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.walgreens.com/profile/v1/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        // PhoneNumber validation success. (AccountID: 3690452, 2453329)
        if ($this->http->FindPreg("/(?:\"code\":\"WAG_I_LOGIN_1028\",\"message\":\"PhoneNumber validation success.\"|\"code\":\"WAG_E_PASSWORDLESS_1013\",\"message\":\"Exceeded maximum attempts to generate verification code\.\")/")) {
            /*
            $this->http->JsonLog();
            $data = [
                "login"         => $this->AccountFields["Login"],
                "password"      => $this->AccountFields["Pass"],
                "isConsentFlow" => false,
            ];
            $this->http->PostURL("https://www.walgreens.com/svc/profiles/login", json_encode($data));
            */
        }// if ($this->http->FindPreg("/\"code\":\"WAG_I_LOGIN_1028\",\"message\":\"PhoneNumber validation success.\"/"))

        // Sorry, the Service You Requested is Temporarily Unavailable. We regret the inconvenience. Try your request again in a few moments.
        if ($this->http->FindPreg("/^\{\"messages\":\[\{\"code\":\"WAG_E_SVC_UNAVAILABLE_1403\",\"message\":\"403 Forbidden: Not allowed to access the service\",\"type\":\"ERROR\"\}\]\}$/")) {
            throw new CheckException("Sorry, the Service You Requested is Temporarily Unavailable. We regret the inconvenience. Try your request again in a few moments.", ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            if (!empty($this->DebugInfo)) {
                $this->DebugInfo .= ", {$key}";
            } else {
                $this->DebugInfo = "need to upd sensor_data / key: {$key}";
            }

            throw new CheckRetryNeededException(2, 5);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Updates in Progress
        if (in_array($this->http->Response['code'], [0, 503]) && $this->http->FindSingleNode("//h1/strong[text()='Updates in Progress']")) {
            throw new CheckException('The Walgreens.com you know and love will be unavailable tonight until tomorrow morning while we make some technical updates to the site. ', ACCOUNT_PROVIDER_ERROR);
        }
        //# Welcome to Walgreens.com! Because we're unusually busy at the moment, we're temporarily unable to take you to the page you requested
        if ($message = $this->http->FindSingleNode('//img[contains(@alt, "Welcome to Walgreens.com! Because we\'re unusually busy at the moment")]/@alt')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We were unable to complete your request
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'we were unable to complete your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The Walgreens.com you know and love will be unavailable temporarily
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The Walgreens.com you know and love will be unavailable temporarily')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We are currently in the process of upgrading Walgreens.com
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'We are currently in the process of upgrading Walgreens.com')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // You're seeing this page because there's a little too much traffic at the corner of happy and healthy.
        if ($message = $this->http->FindPreg("/You're seeing this page because there's a little too much traffic at the corner of happy and healthy\./ims")) {
            throw new CheckException("Walgreens (Balance Rewards) website is currently experiencing heavy traffic. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        // 500
        $this->http->GetURL("http://www.walgreens.com");

        if ($this->http->FindSingleNode('//p[contains(text(), "We\'ve reported it to the team.")]') && $this->http->Response['code'] == 500) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $response = $this->http->JsonLog(null, 0);

        if (isset($response->links[0]->href)) {
            $url = $response->links[0]->href;
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
            // "email":"i***ark@ya.ru",
            $email = $this->http->FindPreg('/"email":"([*\w@.]+)",/');
            // "phone":[{"number":"(***) ***-9118 (cell)"
            $phone = preg_replace('/\s+\(\w{3,}\)$/', '', $this->http->FindPreg('/"phone":\[\{"number":"([^\"]+)/'));
            $securityQuestions = $this->http->FindPreg('/"securityQuestions":(\[[^\}]+\])\}/');

            if (!empty($securityQuestions)) {
                $this->sendNotification("securityQuestions were found // MI");
            }
            // Email
            $refId = $this->http->FindPreg('/refId=(\w+)/', false, $response->links[0]->href);

            if (
                $refId
                && (
                    isset($email)
                    || isset($phone)
                )
            ) {
                $this->State['refId'] = $refId;
                $data = [
                    'priority' => '0',
                    'refId'    => $refId,
                    'to'       => isset($email) ? 'email' : (isset($phone) ? 'phone' : ''),
                ];
                $headers = [
                    'Content-Type' => 'application/json; charset=utf-8',
                ];
                $this->http->PostURL("https://www.walgreens.com/profile/v1/sendCode", json_encode($data), $headers);
                $response = $this->http->JsonLog();

                if (isset($response->messages)) {
                    if (strstr($response->messages[0]->message, '@')) {
                        $question = "Please enter Verification Code which was sent to the following email address: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                        $this->logger->debug("Send Verification Code to email: {$email}");
                    } else {
                        $this->logger->debug("Send Verification Code to phone: {$phone}");
                        $question = "Please enter Verification Code which was sent to the following phone number: $phone. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                    }
                } else {
                    return false;
                }
            }
        }

        if (!isset($question)) {
            if ($this->http->FindSingleNode('//div[contains(text(), "ve exceeded the limit of attempts to verify your identity")]')) {
                throw new CheckException("You have ve exceeded the limit of attempts to verify your identity", ACCOUNT_PROVIDER_ERROR);
            }

            // Keeping your account safe
            if ($this->http->FindSingleNode('//div[contains(text(), "To keep your information secure, we need to confirm your identity with an additional step.")]')) {
                $this->throwProfileUpdateMessageException();
            }

            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        if (!isset($this->State['refId'], $this->State['X-XSRF-TOKEN'])) {
            return true;
        }

        $data = [
            'code'  => $this->Answers[$this->Question],
            'refId' => $this->State['refId'],
            'type'  => 'Pin',
        ];
        unset($this->Answers[$this->Question]);
        $this->http->setDefaultHeader('X-XSRF-TOKEN', $this->State['X-XSRF-TOKEN']);
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.walgreens.com/profile/v1/authenticate', json_encode($data), [
            'deviceInfo'   => '{"userAgent":"' . $this->http->userAgent . '","webdriver":"not available","deviceMemory":8,"hardwareConcurrency":4,"screenResolution":[1920,1080],"availableScreenResolution":[1920,1050],"timezone":"America/New_York","addBehavior":false,"plugins":"fc88e85a76ab3eced9b94a1e30b5cd1b","canvas":"b0f6be7bf1c6f2f954ea1baa7d6d18b3","webgl":"6556a736b65f8c047e6182a790332188","touchSupport":[0,false,false],"fonts":"1c14d795eb1a76e9c60c626d61eb67ef","audio":"124.04345023652422","edgescape":"georegion=XX,country_code=US,region_code=WA,city=Seattle,dma=0,pmsa=XX,msa=XX,areacode=0,county=XX,fips=XX,lat=47.6092,long=-122.3314,timezone=PST,zip=98111,continent=NA,throughput=XX,bw=XX,network=XX,asnum=XX,location_id=XX"}',
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        // Invalid entry.
        if (!empty($response->messages[0]->message)) {
            $message = $response->messages[0]->message;
            $this->logger->error("[Error]: {$message}");

            if (
                stristr($message, 'Invalid entry. You have ')
                || stristr($message, 'Please enter the verification code we sent. You have ')
                || stristr($message, 'The code you entered doesn’t match the one we sent you.')
            ) {
                $this->AskQuestion($this->Question, $message, "Question");

                return false;
            }
        }

        $this->http->GetURL(self::REWARDS_PAGE_URL);

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // redirect
        if (isset($response->links[0]->rel, $response->links[0]->href)
            && $response->links[0]->rel == 'redirect') {
            if ($response->links[0]->href == '/youraccount/default.jsp') {
                $this->http->GetURL(self::REWARDS_PAGE_URL);

                return $this->loginSuccessful();
            }
            // Create Your Walgreens.com Account with Pharmacy Access
            if ($response->links[0]->href == '/pharmacy/hipaa/hipaa_disclaimer.jsp') {
                throw new CheckException("Walgreens (Balance Rewards) website is asking you to create Your Walgreens.com Account with Pharmacy Access, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
            // Select a Security Question
            if ($response->links[0]->href == '/password/create_securityquestion.jsp') {
                throw new CheckException("Walgreens (Balance Rewards) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($response->links[0]->href == "/youraccount/terms_conditions.jsp?ru=/youraccount/default.jsp") {
                $this->throwAcceptTermsMessageException();
            }

            // Please reset your password to login.
            if ($response->links[0]->href == '/password/password_reset.jsp?flow=ivd') {
                throw new CheckException("Walgreens (Balance Rewards) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }

            // Security questions
            if (isset($response->links[0]->href) && strstr($response->links[0]->href, "verify_identity.jsp")) {
                $this->parseQuestion();

                return false;
            }

            return true;
        }
        // Catch errors
        if (!empty($response->messages[0]->message)) {
            $message = $response->messages[0]->message;
            $this->logger->error("[Error]: {$message}");

            // Security questions
            if (
                $this->http->FindPreg("/\"code\":\"WAG_I_PASSWORDLESS_1024\",\"message\":\"PIN Sent, to phone \(XXX\) XXX-\d+ Cell\s*\.|\"code\":\"WAG_I_PASSWORDLESS_1024\",\"message\":\"PIN Sent, to phone \(XXX\) XXX-\d+ Home\s*\./")
            ) {
                $this->parseQuestion();

                return false;
            }

            if (
                // Invalid entry. Please enter a valid username and password
                strstr($message, 'Invalid entry. Please enter a valid username and password')
                || strstr($message, 'We didn’t recognize that username or password. You have 3 more attempt(s) to sign in.')
                || strstr($message, 'We didn’t recognize that username or password. Please try again.')
                || strstr($message, 'Phone login not supported. Please try again with a valid username and password.')
                // You entered an invalid username or password
                || strstr($message, 'You entered an invalid username or password')
                || strstr($message, 'Invalid username or password.')
                // The information you entered doesn’t match our records. Please double-check your info and try again.
                || strstr($message, 'The information you entered doesn’t match our records.')
                // The phone number you entered isn’t set up for PIN Code Sign In. Try entering your username.
                || strstr($message, 'The phone number you entered isn’t set up for PIN Code Sign In. Try entering your username.')
                // Multiple accounts match the phone number you entered. Not to worry, enter your username to continue.
                || strstr($message, 'Multiple accounts match the phone number you entered.')
                || strstr($message, 'Sign In with your username and password instead')
                || strstr($message, 'Invalid entry. Please enter a valid username and password')
                || strstr($message, 'Invalid entry. You have ')
                || strstr($message, 'Phone number login is not supported for this account. Please try again with a valid username and password.')
                || strstr($message, 'Phone login is not supported for this account. Please try again with a valid username and password.')
                || strstr($message, 'We didn’t recognize your email or password. Please try again.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Account locked for your security')
            ) {
                throw new CheckException("Account locked for your security.", ACCOUNT_LOCKOUT);
            }

            if (
                // This account has been deactivated for your security.
                strstr($message, 'This account has been deactivated for your security.')
                // This account has been locked for your security.
                || strstr($message, 'This account has been locked for your security.')
                // We're sorry, the account associated with this username is inactive or has been locked
                || strstr($message, 'We\'re sorry, the account associated with this username is inactive or has been locked for security purposes')
                || strstr($message, 'Account locked for 15 minutes.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            // Sorry! This service is temporarily unavailable. Please try again later.
            if (strstr($message, 'Sorry! This service is temporarily unavailable')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // We apologize for the inconvenience, but for security reasons you need to call 877-250-5823 to sign in.
            if (strstr($message, 'We apologize for the inconvenience, but for security reasons you need to call')) {
                throw new CheckException("We apologize for the inconvenience, but for security reasons you need to call 877-250-5823 to sign in.", ACCOUNT_PROVIDER_ERROR);
            }

            // AccountID: 2829051
            if (strstr($message, '500 Service Unavailable: Application exception; Database connectivity error; Server overloaded')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($response->messages[0]->message)

        // Sorry, the Service You Requested is Temporarily Unavailable
        if ($this->http->Response['code'] == 403 && $this->AccountFields["Login"] == 'parlen') {
            throw new CheckException("Sorry, the Service You Requested is Temporarily Unavailable", ACCOUNT_PROVIDER_ERROR);
        }

        // We are sorry for the inconvenience, but this service is currently unavailable. Please try again later.
        if ($this->http->Response['code'] == 0 && $this->AccountFields["Login"] == 'dmays13@comcast.net') {
            throw new CheckException("We are sorry for the inconvenience, but this service is currently unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Walgreens Cash rewards
        $this->SetBalance($this->http->FindSingleNode('//div[contains(text(), "Walgreens Cash rewards")]/preceding-sibling::div/strong'));
        // Membership #
        $this->SetProperty("AccountNumber", implode('', $this->http->FindNodes('//div[strong[contains(text(), "Membership #")]]/text()')));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Balance® Rewards has ended.
            if (
                $this->http->FindSingleNode('
                    //div[@id = "balancerewards"]/div[contains(normalize-space(), "Balance® Rewards has ended, but you can keep your rewards! Join myWalgreens™ by")]
                    | //div[contains(text(), "myWalgreens is more than a rewards program - it&#x27;s a personalized experience that makes saving, shopping and your well-being easier. For the one and only you.")]
                    | //a[@id = "JoinBtn"]/@id
                ')
                && empty($this->Properties['AccountNumber'])
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode('//div[@id = "success_msg" and contains(text(), "We’re sorry for the inconvenience, but this service is currently unavailable. Please try again later.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        // Name
        $this->http->GetURL("https://www.walgreens.com/youraccount/loyalty/loyalty_rewards_settings.jsp");
        $this->SetProperty("Name", beautifulName(implode('', $this->http->FindNodes('//div[p[strong[contains(text(), "Name")]]]/text()'))));
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $abck = [
            // 0
            "3293E042E5DCE0E6ECBDB176C0B5F014~0~YAAQNoVlX8tSydyUAQAAt0US3w3gy3+mdu84cXvX68lipRqrM8Gw74MprSZPsyoawQe6X6ASxg2uBPPdAtwKdQ1FomvbcEV4jpx8DwEcs4+FYUmhX1+wYvvy/RMA+WUkRMjtv/uwR3wsUEW80jLoUVzWmkuC8W8NrdRoCevqwYzJIIX2/EcXGiaf6hWgRDG+DHY8ejZsRaLtEA9Wa4Ti2bPVY0/PtHTtXSC9H2+E2iIkwgBHVHml3HHX5GqCQqhN5rBf3a01kSwlgI4XPepFdBGfVq7cJ5LF+DRL8eBO93CJJAQStX5vMY9ZQfLTq/Ey5vtmHLDpZjxRZqY5e4LH7F5+qKciO6tE/HjIRGTbeGNL1rzutjCn6mLm8pfC8b5IMAiufKwp2Q1SJs8nwu7NErYkDSl0QxcqX6vXDZ7v2LNMa2R+ldmKeq6RIM0/HijVhR4kSEZ5XDgF/ZWrpvEWTY8gs7Xyk7CrD9JLbaFO8SRbHC7zs6XocqXaD6iFUUABG2f9N7rPkfgmFQOl41T87x/CnXaV~-1~-1~-1",
            // 1
            "1F56B3E577638446DBA270AC3B8358A2~0~YAAQVJbvUFcjosOSAQAA13a20QxXJDUihMmO/aWriwOvyXzvbMXdB0F2N7DWdUbKt1l2uzNGKXTdtbQ5COogfezSlPPKOABC9MQDwdw86h5AXj10D+6NK3HDf3+j61veAUhJ/qXJAmtEP7OhnMR104hP1RVXZUvJaJ0UgH1ckc6ps7AW3QIb732RUugwX5inEjPqXXFosLBfKjcppdiI2dv5Ta/WX2QZbuAivuZavq2eEMXl/PU0rYSjZjjTJa22DtBG0ew7ywMqUKFL1VqpRZIq4KOK9DNPLuE3dRwn71sNlrs/p96f0KNiw68zVMUuxO2xusoU3LaOBwCTd5NLaOsbZ9Jg7syMKlg+/6ynGgH1ajezsoNStjoSq6cX9ZESfTnun4tGJttlJKekxg0ODETEny2KBVowBYbHbE87vJb0Aur4QWOxXfZ9HepQFAxi6mbdbho9hxnLMvkgDzBqMX8eLUPleqJ95Y+4D+7m6TfOzgc=~-1~||0||~-1",
            // 2
            "57417F4ABECF3E7A2B1BDC62EC504318~0~YAAQHroXAozzl92UAQAAHtr53g2hiFAVjpdviy6HS+9aPrICRdSzL13jEEe3gh0Wqf8zsN5Q0H2eUfdHTs6xvwdl/G6j7D7uq0It7w+ZG/3sFG4tzvArK3v+YObqBDnPAIRS6u9dKUiKs+ArFBJfS1BvQWt33RGmJttGLF50S5XXMa81YHDqF5RWQM0sD+dEVZCJr/gM25tQbCO0g7Jr++OQK8xnYnfFpeqsAPrAog0wYpYKiZWlA8IZWxOLpokJZtzbXUY/Dh6jVp7mhzv66uu1qF24QZpQX9l2rIlZOem+tktSD8invuhPwdbx42CiO02Bpe294Y8GRW53Dvyo9Ge4MaFA6jacoImQQONhix5aX1xyGfvMnO2EGGxrnz5vWD6kh00aWsG3bfVNeIKtAwEZQOZjLgFDU6T61iSTVl3rMqSlp4t5jkKpKlNTDs97CI/mU9rhRRajjfZfQcEVt4FXOiLr2c31evUtfUc/teiQ7S6xa8Ps/vWfG30YH7yW3Ygzbmx9UblnDgt/vOTt1En2ibRhG2M3kznCv9s5zkLSkg==~-1~-1~-1",
            // 3
            "FD7F60CFE341060030DBC6BD60672433~0~YAAQPpbvUKKaktGSAQAAaXWP0gxchhEyuh5/5a5STH1IH6AOB/iUqQhvnEJV4V1ecoZV1GKJizUq7lzTyPcIUmS9klOWqogNcR3NjPN0i5LMebLEWPNWFsj2Kebn+OAyowkGl2SHQAJNRAI6klnbC652MTXtO+0tRwtbq8zM5Mt4FhpIDLmESszhL0z1WTT28xL6wE7Of4oJTTiXOkGKLSZjA7ezGULcYQK3j9NQR/ZAAn73kHhPQa9jE1y/yG7NTy/P4TRhDFYgAhQfXbK0Bb90/yw3EfXIY5djWBbsJvKPXogkhpe3Y1fkfnH86Vlh5Vbj1uVGI+zRdxx5mQWV8/WZnfaa8prEukE3ooJLHHKI1KK1tbx7yPle1KCSnTmJwM+U0H/14BF5RaSxW0jfd01Yfz+eniDOTk/V2VZ57UYVA8KDuS6u3yYN4Vm1PlcTZiL37H7Ufkbm~-1~-1~-1",
            // 4
            "604629C52D44542D960A93BF03EEA3D3~0~YAAQ0gVJF+Ggn9KSAQAAVtLg6wy9BrejNPmyAdaUKzrSzZ1Va94Sq7dpOJovKgjnMqxbi46VHiRNzBuyUlq51Y6YLfgKhrtHHGjmQV3boh6UROXbd1v6PvlM7xZFqXtAV8trMm+ZS7cXmSsg6E1fR/gKDOz74owjmza7cSSm453mz2KiYHODVqBcxLKdZBHudy7ArZgE4t0yr+zjBEsXl3sU2Thqvbf0ymrGKBsQ9QBYOyW0uUwnBbpPk0uWh8xobtUeOz1L5WBnVwpG4KFAsEey2221ofYnnONsr1n+G7P6HAU8GU0Yh1RfP7x+dQht8G8KhrMQ39bB/yiNvqwBeAypBmnRaG1ZgTsrZD6kgU6O++BgEnjjTg9ZoBPdvNYPN5yPb58dXDw+SxVaeIbdOSJHW4fei+l5c4Qducb2DMUwbB6Um0cnMo2wsmTnB2JyYBwoaMEZBpBeA74heWeqBcyQ+giAvDQpIy/aL7U97wRBtiBrka1NUvKiuszVfeg1~-1~-1~-1",
            // 5
            "044C5F6D1C7BB7C45C8F550737ED96ED~0~YAAQt5hUaN+6M92UAQAAlIs54A3lRxxSgBf4uv12Ij9t4TsxJ2KrebXje25f3fLhHQ3Js0jQWk8SAxf01lPcaUlQyp77nx/pzHu0qlpYomQcBPVsjvrQX97AT8Vzoq4ruE8rhumfTYMPHhsQL7gDCBA1yTYf84TjVP3L55T3C4AOgu3NB39DJxM29g+R9Bv3Ok6WFl+TqgDoBDnnjp3QddVbtZ0aYD0+FSgDZ0IkvNKsNymbYunGCDKodLgMiq4kiCIlhUM1ZML6izWZgK94Fbjm31682ju8L5ZndvuIDwDBfuTUnu4Dhe7JbCPbLN8H4WIUCWzCBZZT9g/a7GfP5CWZfqSWE7hOb6CRZ+Jmyc6OpWjhd965KLX1JUyJsX0w6Kd2bsT3UBUJ79tDyALkF0UIxUGSrZnPC3/enTmFptcOA0B3Pga+4Zl+EVkARfMGhQ8K0U4cmNE3LqcPqhsMo4i/nxIt1P2EUBPsJksRd9lTeHIvL19qh6rV8mkc4Vq7MZZ0Tdo8VQST8kgRijZ8B+bx/yu/~-1~-1~-1",
            // 6
            "14FE6BB85DA6E97A4A9950B61B19458B~-1~YAAQt5hUaPG9M92UAQAAJfw54A0IjR9GRsRkEh84kkZQqRG2e+4nR2DW+lcpO2jJzARd8BlOEY0O8bSRQmU+jVthEP0XGDryxjzDwUsMi26AYwO0YB6W8P7auhi1AC+ZHRu1ImC8rdtTZEBRaPpC9YunuzmafIfC0pV8T0imqtGe9JeoYlP+cNv5Uv3TckP4G3Os5QN4Z+/5q8rKnuzA9oAFOiYJ8Uzu6qhUWgCDff/iD4pJ1PIvvvlU4RL7cxPDuyhEx1MwCrQrW3e+3Q67Y5uoc5Mf8r1fDh/S1T75/mLZyaUGEqiGzXSO9Y8n7odTAt2eYLfX/8TpAk3NWRI32ABaqwQTIMNs4/dn8mysCQVxeEXUI2iHbfaiqRi8E5YmjZdmEH7HHf4Gt0PhkJxXfX7J42KqYIjr5So72OLGEnv2Wq0BWuNdJCmYOMdMMGklsHmeXYHMXhnWWV59zSy+hHWuxMduepacQpv/TXdoTYUmHAFoDZ5QNoPpi4lhUiJeKh3NDNI87PPtUbnJ6ftSf4jjqtEJw1OkrAXMDn5qanT05HEkv8noTL/0sQJRnHKr1Ss2pGI5A9Ph9LL9qvv4wKJ9rnOTQrfa42Gju8mwI8xpMRKrziz4HF1aeAOp7NWhTLYhCD/4+H+F4fnPyQ0HS+qYhkHQ3h+2YXOHbuTvEcHfvhHCFEcm43LzziQBnJfQ5N8rG3SBMRiG/DyYV2YRZdxaihgAV+un1PfY/zqSURF+u+MOO6zcb2k6UTpuubtRsolT6aH/EibCtzlXb3S6T8pE~-1~-1~-1",
            // 7
            "B370A25D65EABE19E687DD88C0773416~-1~YAAQt5hUaLHAM92UAQAA5EY64A2PYyVNqiY26GUnaPHAgm/E7ImAcmjKY6UWua8V3eBkkrabJHuKtdlTMxBJVmOp44/jXjIEwZff1Qc2EDRU/L5zTWe3n8InwYrwcO1WAPcg10J8XksB6jYdDbSV9zyi6DFShbhM8E3ZdTVyImogvNoO6foYTstwqDhGW8xqgyWyyfHSajdvd/nsfNLe/9oQ4SXtyS/SGX60iTVOJNmsp7+3QJ8+IVElRaZpd8rG/R9y+GW0q0UBQ7DA4RCXLD/n1y6YVqGsAWExtd7cVJzaspcFZkUPhuEieKXMt/gMkJRAcHHdoNkyAXf0HLhB2x5kMR3ig3a8jybwduy461So/n8rdr/O0bOGNe4++OtY1X8Zl53ML3QZ8f3GDeYIOwPv6taIZunmi5AYB4h6e0KDYqUNXzOHqmNpryhiaa4QkuJpGzmTcZDNy6qL0yl0khTCk2NIhBxWb75iGVwPiyLAhEhakIsWvWeb5TyrLsSxQTr4h/4+plgV7pQjAJzHlBND0j580Um4kG4HbukT0tMiiLcMA/NSZgpJKShGg0JBhdiFhW4yeacWwcqw/YWIF1QMF4EhfClbhBjIySAX2E+bhOmagpefZMXQZn1yc4fIu/e2RjfGBwAcyJzeONBvY74NQB/2LPi7Wyzsk7Pk5sOB8tMOzIx2n9dYPv50x+FsuS4bSpSosXzObBwEWBnNPRXoVJKIO2CDrGFLA9QfXYCssvqYuEw/KV10vJm/q/K45u6uiXaugtgle4z2r2rqTzc=~0~-1~-1",
        ];

        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";
        $this->http->setCookie("_abck", $abck[$key]); // todo: sensor_data workaround

        return true;

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9257961.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399112,4752512,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8962,0.862266509431,811047376256,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-102,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1622094752512,-999999,17352,0,0,2892,0,0,3,0,0,B729A645D2CFE2A87A3A961CF15D0672~-1~YAAQ1MZMaAnkJn55AQAA5KlhrAWvrGInmQziqw7ADL0LWENTIwlqbhaCZBihfQSdtng/N46U1RwnTMEM7u+0xXLOwAxHF0bK48Y1PhM695v0ffry1F4cSj7RykpZGdnDy12AYYV0jr/IWeLb+PciOjOSKlrLAW/6SBK55jshk8rdFIgJMhy/jovvq5RR6YVRQCbKCWfn829sfw4WKsa83NG9tPiWMVVnPDY7huQ8E7avfjxZWEDOu1C5a1St01mx9ErqJCBGABVCVOBodXvapNDTzTq0rBuIzodYQlLDd4znfGg5vuI+xUrfulBw7lxOJZ9ntpTcJZtFIkqZZaakIgPNL6ao14eN+uBmDJVTaI7mOLFAB94o8p0fp9TojjQ=~-1~-1~-1,35509,-1,-1,30261693,PiZtE,96012,62,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,14257560-1,2,-94,-118,86509-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9206681.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.93 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,403427,3072480,1536,871,1536,960,1536,449,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8936,0.448774112224,819816536240,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-102,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1639633072480,-999999,17540,0,0,2923,0,0,2,0,0,FB80277587C7C6099BCF4C8E3AFE23D0~-1~YAAQmmvcF/mXCLx9AQAA8DG/wQdLeqkIzG762n+iJxq98CpgvRyxn8I/96lDZtWHSC/C+0SvJpFgsyRRtgU+BMihh9HnPGua6zDYMw3q+ieJOXauftxU6j1yO8RUi0yssw2lFGGkp0iEJo7w9yIwEH2xFtPodrxgqqTlopBUeF4OAr3ANCILZ3bY9PhwC2MCFCu6381Icm/+p5q29sTJxlc6rtMWv3ij1R6Bq68D0TJOtVGmiX9XMNdU1VCR9liliQtcLjP3Ic9u8+9mQSwBlbzBYxIc9DJAuaPsaM/MAXOcmhUxcKeOsWCYvtbdPD2RFeGdR7WeOnKp0jDoyqP+CwLs5o03R+71jY4QTg7DhMZioNlawLSmzj+iOu6ffrhz9cRcinmGhMqFllvIYw==~-1~-1~-1,37526,-1,-1,30261693,PiZtE,59538,77,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,414784767-1,2,-94,-118,88530-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9206681.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:95.0) Gecko/20100101 Firefox/95.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,403427,2442925,1536,871,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6012,0.506500860253,819816221462.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-102,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,0,0,1639632442925,-999999,17540,0,0,2923,0,0,2,0,0,EF2FB587492D0400D224CF00F6471E12~-1~YAAQnRDeFzkjqbd9AQAA3Y21wQdyikIfU5f4UNKUzrk5BEBM+ofBKyrAJzmQ6LsLD0vV+9s0/iEpITdMibQfk+ZnWtLIVuZ64ESTEOTYK0n4Cq0LtDWsSmOS3004F+bPbMAzU2AtsGkj3YAybJwTOdrOch5lRd2PGqKyuuSBZc/z5JvziY8PAnnhRlZ7cuvm5sf8OFIIATT3f0t+PbUXQj5n/xhbn6gluYqbU2RBtsT4KrVoJJPMmguRKZj2eFOmZSGYiwu2IVESLdtGbsNf7f8GBunlTMQDPthbtSBIbXm+7pKxbIAnhhUjb/kznOH3n4D0JXlymo/ls2kSCjeiJ+yY3KlCHwiO3+4G2OB/ZuMNa5yseiYkkH/CJbanKTJc1LwML5GrM9kRdEmuRo0=~-1~-1~-1,37403,-1,-1,26067385,PiZtE,25421,91,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,36644025-1,2,-94,-118,85759-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 3
            "7a74G7m23Vrp0o5c9257961.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399112,5074504,1536,871,1536,960,1536,452,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.272228306136,811047537251.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-102,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1622095074503,-999999,17352,0,0,2892,0,0,3,0,0,28131FCB1ADDF758499617CEF4347EE4~-1~YAAQz0MkFyt0wHx5AQAA5pRmrAVJxD7eukhVo57VlgnvLx4/ZDiSfOuRD2qwGOSz/rEoZHmLcguhOALo1+uTf2G79qnC2mPHiSOiAgkiYVCCHFC0XVxQDqozsryNEo7go7Mad6/hsOeWzUe9BMT83fazTOFPB9+k7WnpFR0pUPSjw6OmNPpHxBM6/SXrGwqCaOEvW9D2kqbLTas4YmdTNqxamaYD71SpxvV7AxU0StBUNO21+dLmc5eo30rhmtH3ucHbSKL4C+5UL+f4jtiB7ci0KT+wHwDD8DrFWSc1ny9VOA/bs0tMnz+DNFsI8ydcM9u4S11YSD6TrPpWr5uJ+L169smIBx3HkqIghWFVDjHPNg1dCIQuGdxrM/wpm/Dky94sVJpKR8SYNlwhDs4=~-1~-1~-1,37132,-1,-1,26067385,PiZtE,100100,55,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,380587866-1,2,-94,-118,85479-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            // 4
            "7a74G7m23Vrp0o5c9257961.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399111,2773244,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8962,0.509111082254,811046386622,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-102,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1622092773244,-999999,17352,0,0,2892,0,0,2,0,0,59F5BF57D6B80A8A9DAA0EB5FF44BB54~-1~YAAQjMZMaGc2rKF5AQAAkndDrAV9mg2NIVefQQC4WMhCcS1TtCVQacHVYDKQ8a21h0nK6PwE5s1IcbWxdbANMDBi+scJOaWnGX0183a1Jz0v5LbWvM98D9N7RK4srbLdiaOHf56WgKlR8Ub/JtMCCLh/NwzMg3y4EG4+J9eD1T0AduvlhAp77rFsbveh9XvrAMYY4xkNchlbrbXu9JJ45tKFG7sgD77cinUwSN/Cu9VUWd6WOBdfIUZd0py3YAcJKzY6Os8HNFlxDOOCArIaZNwW9/IBTFatWvlYQnRYPC1mBAMz+/CSxfkxvaFAuyaXF8NtpSC8zIcHc5Sr8JWf38muBjBg2IwIirTvgUZlEOvkhsRMlJ6B/S4GiLsRwPXozTqUC8qU0Op2jpqxMQ==~-1~-1~-1,36876,-1,-1,30261693,PiZtE,17722,86,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,207993255-1,2,-94,-118,87894-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9257961.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399112,4752512,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8962,0.04251706321,811047376256,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-102,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,530,0,1622094752512,16,17352,0,0,2892,0,0,530,0,0,B729A645D2CFE2A87A3A961CF15D0672~-1~YAAQ1MZMaArkJn55AQAABKxhrAXuwogIj6MBKazd2KzjDTLEC5AQ5YlF+VqCEwlep/oRKR4ZNqJUznSkwFKpY1g75gNPuwxLdC61AcWEXj8f2W/TynP/GWyiJYclntrtqlm5iWmxk4mvUq7aUO9mrC7l+va/OGdusdycusc7vdLug6oWAI305d096n5P4eW0keActToSQ+ySBFWq0uXUiD7ihK5QidZHELxL7p1Je3PjdpSUc2F8cK4O2V8veg+kaHXGq39TWOHPb30AJWP7Y1NsKaw0uGLeE7kzy23YLHIoO0tFehsgxNUCDMQ41ifQMWpde/kt0kXQAo4iiFUJWjjoHk6xGXowG6HF3MydNQbgikLr81jOOIIUsOfyl9yiLJ9QkCrrfDHFnMJp5g==~-1~||1-sqRylDQhxy-1-10-1000-2||~-1,39413,202,-511429997,30261693,PiZtE,63068,73,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,29,32,32,33,49,57,30,31,29,29,7,7,11,322,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,14257560-1,2,-94,-118,93650-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;19;6;0",
            // 1
            "7a74G7m23Vrp0o5c9206681.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.93 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,403427,3072480,1536,871,1536,960,1536,449,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8936,0.914355553457,819816536240,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-102,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,547,0,1639633072480,10,17540,0,0,2923,0,0,547,0,0,FB80277587C7C6099BCF4C8E3AFE23D0~-1~YAAQmmvcFxKYCLx9AQAA5TK/wQenYnLjL3ZUP/bNtGFw0vHUL5ekUgzMFKveXTXpLuVGrKMsVsmhXB3drYKM6CFUexKGwyRdJoe545WFPXer3JFrhCGqYpv9bRcwtkXhoTigHZNOx0zsrGqBazWxSgcLraCQQBg32k04n/dCuKKsbNZqgUh+5o4cvsbAXOvd9/Tt2a/+53tav5B/aF1DjhH2krURaXhyYQiQ45FATo1BHEmGptFy7f8AvYykzdQvnry8EHXvnYOwey6S11plvBZs/5k0Tw+kzmKwqZOg/4R0oVvgwhhVMypM9f8Rwkw9lHx6Hbu1Wr5cpVwZeLIOOSq8VSJjAE5XTE2GVbORka16ojCpntZNFdrLktvFvaFeuOZwWfU1VV4CMx1wyw==~-1~||1-TgFFoVlkEn-1-10-1000-2||~-1,40259,794,1777787166,30261693,PiZtE,31707,58,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,20,20,20,20,40,20,0,20,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5475-1,2,-94,-116,414784767-1,2,-94,-118,94272-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;11;5;0",
            // 2
            "7a74G7m23Vrp0o5c9206681.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:95.0) Gecko/20100101 Firefox/95.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,403427,2442925,1536,871,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6012,0.821197006410,819816221462.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-102,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,637,0,1639632442925,5,17540,0,0,2923,0,0,637,0,0,EF2FB587492D0400D224CF00F6471E12~-1~YAAQnRDeF0sjqbd9AQAAppe1wQfpS8Wne4ziBih2zrIzdJCXjqh8cuvL+5vv43uWXbTWHRVlvAD9LKMB4StiXrcRBHbLEIw956zVdHFFCO/yaQO4AMSNk9/Iqt9A68XYXR05V7800btG5sggeQDP9xJwnKznL2ZfJm18Az+Z7u6/hXwyBGueKOjMRUx0DAcnTi9M/9wfYBny/myWc9aL51/Kz7XJ4FeFzjlDpEI4pEZsv+9NOq9OEKuHnK0t+T9036MTwkGAmnZViT7mxXcg2FykQO436FIF2RLP9x4F3N7pqAuFpaybxjkUDZzKjLmX3erNfcHef6D66P6cxMNJZUYwOuITCBKRnc154jcNu2UXC74so+tZZhoCdPgAwCPhoRJ967tVimsvytgUkqw=~-1~||1-VFqOjzeeiR-1-10-1000-2||~-1,39000,349,973336337,26067385,PiZtE,17576,54,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,0,200,0,0,0,200,0,200,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,36644025-1,2,-94,-118,90217-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,0,Intel Inc.,Intel(R) HD Graphics 400,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;25;7;0",
            // 3
            "7a74G7m23Vrp0o5c9257961.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399112,5074504,1536,871,1536,960,1536,452,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.902808747451,811047537251.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-102,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,0,1,440,290,241;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,1004,32,0,0,0,972,513,0,1622095074503,7,17352,0,1,2892,0,0,520,440,0,28131FCB1ADDF758499617CEF4347EE4~-1~YAAQz0MkFzJ0wHx5AQAAzpVmrAUVdBRhSmG/bOhYYfo6ZyuC3w3eUlU083LbUHPeGN0vo5zCv2EEgNLqbDCCst6uFmP9GhSZXtyv8swZRRMi8m+nl4xpeLu6pykk17BULQ7Ps6zBs9pDBe9hlLNylOvgHRACae3RSNU2kV8HZK/mMRFEbnzGmBWsY/BllHWvjvq/roIqAh4rRWXltsb4OIyc1TOPWjWskOCMbMLkmUSabRoMTPXpXXLgk0zwo59MN9VvxKY1PV+Q4KaB+ZbEJYLIp+NcnalZAX6lVW7NOU6reB110HDo7CIjJtN6sL+4DkQm+3wMuSBmoo+7Dp26NzyJt+EjQqSS4VEkJpRQaMvVVIENR+3ZSDX4Wt6zOPkHhCadx/Vz+tjjKzhxtf0=~-1~||1-JjeSFDhhwD-1-10-1000-2||~-1,39373,970,-460396776,26067385,PiZtE,64105,124,0,-1-1,2,-94,-106,8,1-1,2,-94,-119,0,200,0,0,0,0,0,200,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.987ddac2108ae8,0.59a7ba360e0f88,0.8e23b5a19557c8,0.c1655b8444c9d,0.45242e6cfb492,0.9ae6e60eb9bd,0.9ae140c64d5d08,0.a968dc61b55938,0.bb44e7cd1691d8,0.18a7db981b2de;0,1,1,0,1,0,1,0,0,0;0,0,9,1,1,2,1,12,18,1;28131FCB1ADDF758499617CEF4347EE4,1622095074503,JjeSFDhhwD,28131FCB1ADDF758499617CEF4347EE41622095074503JjeSFDhhwD,1,1,0.987ddac2108ae8,28131FCB1ADDF758499617CEF4347EE41622095074503JjeSFDhhwD10.987ddac2108ae8,216,9,225,110,93,80,86,201,238,106,90,35,217,219,195,106,252,222,179,166,35,200,26,17,3,61,119,157,116,246,134,63,271,0,1622095075015;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,380587866-1,2,-94,-118,124311-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,0,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;18;9;0",
            // 4
            "7a74G7m23Vrp0o5c9257961.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399111,2773244,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8962,0.339912019169,811046386622,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-102,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,561,0,1622092773244,10,17352,0,0,2892,0,0,562,0,0,59F5BF57D6B80A8A9DAA0EB5FF44BB54~-1~YAAQjMZMaGo2rKF5AQAAgXhDrAXuuBomB7KyFr+5O9ptZdTJPSGvQAD9dJdFGSloW6m8VCpYXqa1jV81LCh7yQf2NwFWxD7XDVkkBd7PhY1XUJOw4vey5QaooSxt9ybqyLkdOGvMPqPDeKCyARXdRPMjBwkwk01m1wNTErZXegYQ2dLOJewnYKylZYjK+qYBHMmww9pUwCQKX+ZyzWRIxj3IOgEK+F5UB3ROzBaMp2Edf92hTh/H2DSdU9Dw6g5+0jxa3HcKuRv8dBL0TPGxRJk0ZODVSsmHQ5+fj4n7L13duwIC2NItkEvGCZJTPCWyvyORK+hRc+h+E7laWxFW/3SweeL4jnIDYldkeZ1DBJwWG3876r54afz6BqsYZ5xgWzogXcEPEbSQLJiOww==~-1~||1-koRPSUrVVm-1-10-1000-2||~-1,39206,407,-1382287806,30261693,PiZtE,20469,45,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,1120,33,32,32,50,52,31,30,29,28,7,7,11,396,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,207993255-1,2,-94,-118,93654-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;15;5;0",
        ];

        $thirdSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9257961.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399112,4752512,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8962,0.08512150242,811047376256,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-102,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,571,0,1622094752512,16,17352,0,0,2892,0,0,571,0,0,B729A645D2CFE2A87A3A961CF15D0672~-1~YAAQ1MZMaArkJn55AQAABKxhrAXuwogIj6MBKazd2KzjDTLEC5AQ5YlF+VqCEwlep/oRKR4ZNqJUznSkwFKpY1g75gNPuwxLdC61AcWEXj8f2W/TynP/GWyiJYclntrtqlm5iWmxk4mvUq7aUO9mrC7l+va/OGdusdycusc7vdLug6oWAI305d096n5P4eW0keActToSQ+ySBFWq0uXUiD7ihK5QidZHELxL7p1Je3PjdpSUc2F8cK4O2V8veg+kaHXGq39TWOHPb30AJWP7Y1NsKaw0uGLeE7kzy23YLHIoO0tFehsgxNUCDMQ41ifQMWpde/kt0kXQAo4iiFUJWjjoHk6xGXowG6HF3MydNQbgikLr81jOOIIUsOfyl9yiLJ9QkCrrfDHFnMJp5g==~-1~||1-sqRylDQhxy-1-10-1000-2||~-1,39413,202,-511429997,30261693,PiZtE,27214,59,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,29,32,32,33,49,57,30,31,29,29,7,7,11,322,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.6f6af3e9a6a2c,0.c6a85d8a27136,0.0701eb0ddcdb3,0.d4d34439a39fc,0.f02edaa83fcce,0.0e9e59a7fd7fd,0.07b91d9b464d6,0.100a7523d1881,0.8b9694db74c21,0.77f0f695b6fcc;0,0,1,0,5,1,2,0,1,1;0,0,0,0,20,4,3,0,9,0;B729A645D2CFE2A87A3A961CF15D0672,1622094752512,sqRylDQhxy,B729A645D2CFE2A87A3A961CF15D06721622094752512sqRylDQhxy,1,1,0.6f6af3e9a6a2c,B729A645D2CFE2A87A3A961CF15D06721622094752512sqRylDQhxy10.6f6af3e9a6a2c,214,197,217,136,161,12,42,215,192,241,230,68,54,239,232,116,55,244,226,114,200,59,168,26,110,124,84,178,53,136,54,207,332,0,1622094753083;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,14257560-1,2,-94,-118,126684-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0f7edf978cda71a82a5af4b78a8df770924ac922ce0e334cc52acd51016343e3,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;3;6;0",
            // 1
            "7a74G7m23Vrp0o5c9206681.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.93 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,403427,3072480,1536,871,1536,960,1536,449,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8936,0.05967235629,819816536240,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-102,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,630,0,1639633072480,10,17540,0,0,2923,0,0,631,0,0,FB80277587C7C6099BCF4C8E3AFE23D0~-1~YAAQmmvcFxKYCLx9AQAA5TK/wQenYnLjL3ZUP/bNtGFw0vHUL5ekUgzMFKveXTXpLuVGrKMsVsmhXB3drYKM6CFUexKGwyRdJoe545WFPXer3JFrhCGqYpv9bRcwtkXhoTigHZNOx0zsrGqBazWxSgcLraCQQBg32k04n/dCuKKsbNZqgUh+5o4cvsbAXOvd9/Tt2a/+53tav5B/aF1DjhH2krURaXhyYQiQ45FATo1BHEmGptFy7f8AvYykzdQvnry8EHXvnYOwey6S11plvBZs/5k0Tw+kzmKwqZOg/4R0oVvgwhhVMypM9f8Rwkw9lHx6Hbu1Wr5cpVwZeLIOOSq8VSJjAE5XTE2GVbORka16ojCpntZNFdrLktvFvaFeuOZwWfU1VV4CMx1wyw==~-1~||1-TgFFoVlkEn-1-10-1000-2||~-1,40259,794,1777787166,30261693,PiZtE,44961,66,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,20,20,20,20,40,20,0,20,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.09d75756ad55d,0.15d82fdb036e2,0.7e3d08c10860f,0.0e477f8f01da7,0.1efa41ddd6bc3,0.cbddc6c792f9b,0.1bd52cdc43502,0.b91ed63845086,0.2b8d67288324e,0.e450f0f09a6d3;1,0,0,0,0,1,2,2,1,3;0,2,1,1,0,5,17,15,4,20;FB80277587C7C6099BCF4C8E3AFE23D0,1639633072480,TgFFoVlkEn,FB80277587C7C6099BCF4C8E3AFE23D01639633072480TgFFoVlkEn,1,1,0.09d75756ad55d,FB80277587C7C6099BCF4C8E3AFE23D01639633072480TgFFoVlkEn10.09d75756ad55d,34,215,178,200,115,58,254,109,237,205,129,95,82,232,114,20,9,87,98,153,197,193,62,242,64,79,135,170,232,84,156,63,377,0,1639633073110;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5475-1,2,-94,-116,414784767-1,2,-94,-118,126459-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;4;5;0",
            // 2
            "7a74G7m23Vrp0o5c9206681.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:95.0) Gecko/20100101 Firefox/95.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,403427,2442925,1536,871,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6012,0.798889624399,819816221462.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-102,0,1,0,0,959,864,0;1,-1,0,0,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,804,0,1639632442925,5,17540,0,0,2923,0,0,804,0,0,EF2FB587492D0400D224CF00F6471E12~-1~YAAQnRDeF0sjqbd9AQAAppe1wQfpS8Wne4ziBih2zrIzdJCXjqh8cuvL+5vv43uWXbTWHRVlvAD9LKMB4StiXrcRBHbLEIw956zVdHFFCO/yaQO4AMSNk9/Iqt9A68XYXR05V7800btG5sggeQDP9xJwnKznL2ZfJm18Az+Z7u6/hXwyBGueKOjMRUx0DAcnTi9M/9wfYBny/myWc9aL51/Kz7XJ4FeFzjlDpEI4pEZsv+9NOq9OEKuHnK0t+T9036MTwkGAmnZViT7mxXcg2FykQO436FIF2RLP9x4F3N7pqAuFpaybxjkUDZzKjLmX3erNfcHef6D66P6cxMNJZUYwOuITCBKRnc154jcNu2UXC74so+tZZhoCdPgAwCPhoRJ967tVimsvytgUkqw=~-1~||1-VFqOjzeeiR-1-10-1000-2||~-1,39000,349,973336337,26067385,PiZtE,25291,45,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,0,200,0,0,0,200,0,200,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.9ab9b841b7997,0.dc83c36edc616,0.6e8655545b2758,0.646a4f5d90c67,0.61c4303c8c06a8,0.d35a224cfdd17,0.5c9c56c0a934b,0.8e72fa8bde1b6,0.c7933dd89ba8b,0.992459e65afa78;0,0,0,0,0,0,0,0,0,0;0,1,7,0,1,2,0,2,0,4;EF2FB587492D0400D224CF00F6471E12,1639632442925,VFqOjzeeiR,EF2FB587492D0400D224CF00F6471E121639632442925VFqOjzeeiR,1,1,0.9ab9b841b7997,EF2FB587492D0400D224CF00F6471E121639632442925VFqOjzeeiR10.9ab9b841b7997,41,64,247,161,233,204,157,230,212,68,191,52,83,253,32,134,106,67,150,99,48,117,17,197,5,249,112,158,87,63,120,14,632,0,1639632443729;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,36644025-1,2,-94,-118,122280-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) HD Graphics 400,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;3;7;0",
            // 3
            "7a74G7m23Vrp0o5c9257961.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399112,5074504,1536,871,1536,960,1536,452,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.902808747451,811047537251.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-102,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,0,1,440,290,241;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,1004,32,0,0,0,972,513,0,1622095074503,7,17352,0,1,2892,0,0,520,440,0,28131FCB1ADDF758499617CEF4347EE4~-1~YAAQz0MkFzJ0wHx5AQAAzpVmrAUVdBRhSmG/bOhYYfo6ZyuC3w3eUlU083LbUHPeGN0vo5zCv2EEgNLqbDCCst6uFmP9GhSZXtyv8swZRRMi8m+nl4xpeLu6pykk17BULQ7Ps6zBs9pDBe9hlLNylOvgHRACae3RSNU2kV8HZK/mMRFEbnzGmBWsY/BllHWvjvq/roIqAh4rRWXltsb4OIyc1TOPWjWskOCMbMLkmUSabRoMTPXpXXLgk0zwo59MN9VvxKY1PV+Q4KaB+ZbEJYLIp+NcnalZAX6lVW7NOU6reB110HDo7CIjJtN6sL+4DkQm+3wMuSBmoo+7Dp26NzyJt+EjQqSS4VEkJpRQaMvVVIENR+3ZSDX4Wt6zOPkHhCadx/Vz+tjjKzhxtf0=~-1~||1-JjeSFDhhwD-1-10-1000-2||~-1,39373,970,-460396776,26067385,PiZtE,64105,124,0,-1-1,2,-94,-106,8,1-1,2,-94,-119,0,200,0,0,0,0,0,200,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.987ddac2108ae8,0.59a7ba360e0f88,0.8e23b5a19557c8,0.c1655b8444c9d,0.45242e6cfb492,0.9ae6e60eb9bd,0.9ae140c64d5d08,0.a968dc61b55938,0.bb44e7cd1691d8,0.18a7db981b2de;0,1,1,0,1,0,1,0,0,0;0,0,9,1,1,2,1,12,18,1;28131FCB1ADDF758499617CEF4347EE4,1622095074503,JjeSFDhhwD,28131FCB1ADDF758499617CEF4347EE41622095074503JjeSFDhhwD,1,1,0.987ddac2108ae8,28131FCB1ADDF758499617CEF4347EE41622095074503JjeSFDhhwD10.987ddac2108ae8,216,9,225,110,93,80,86,201,238,106,90,35,217,219,195,106,252,222,179,166,35,200,26,17,3,61,119,157,116,246,134,63,271,0,1622095075015;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,380587866-1,2,-94,-118,124311-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,0,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;18;9;0",
            // 4
            "7a74G7m23Vrp0o5c9257961.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399111,2773244,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8962,0.548991400274,811046386622,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-102,0,1,0,1,959,864,0;1,-1,0,1,1425,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.walgreens.com/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,652,0,1622092773244,10,17352,0,0,2892,0,0,652,0,0,59F5BF57D6B80A8A9DAA0EB5FF44BB54~-1~YAAQjMZMaGo2rKF5AQAAgXhDrAXuuBomB7KyFr+5O9ptZdTJPSGvQAD9dJdFGSloW6m8VCpYXqa1jV81LCh7yQf2NwFWxD7XDVkkBd7PhY1XUJOw4vey5QaooSxt9ybqyLkdOGvMPqPDeKCyARXdRPMjBwkwk01m1wNTErZXegYQ2dLOJewnYKylZYjK+qYBHMmww9pUwCQKX+ZyzWRIxj3IOgEK+F5UB3ROzBaMp2Edf92hTh/H2DSdU9Dw6g5+0jxa3HcKuRv8dBL0TPGxRJk0ZODVSsmHQ5+fj4n7L13duwIC2NItkEvGCZJTPCWyvyORK+hRc+h+E7laWxFW/3SweeL4jnIDYldkeZ1DBJwWG3876r54afz6BqsYZ5xgWzogXcEPEbSQLJiOww==~-1~||1-koRPSUrVVm-1-10-1000-2||~-1,39206,407,-1382287806,30261693,PiZtE,77436,94,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,1120,33,32,32,50,52,31,30,29,28,7,7,11,396,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.76a2873e418a5,0.cc8bb94d3d687,0.b2c607a88abcb,0.d9c74d100c5ea,0.b00f14d672d7,0.729b7bb7ddb2b,0.7c955732cb18b,0.969b0b2c2a444,0.2d30cb707b23a,0.0946cf1452067;1,0,1,2,1,3,4,1,1,4;0,0,3,2,3,13,11,3,4,13;59F5BF57D6B80A8A9DAA0EB5FF44BB54,1622092773244,koRPSUrVVm,59F5BF57D6B80A8A9DAA0EB5FF44BB541622092773244koRPSUrVVm,1,1,0.76a2873e418a5,59F5BF57D6B80A8A9DAA0EB5FF44BB541622092773244koRPSUrVVm10.76a2873e418a5,92,125,110,243,53,116,241,99,224,154,41,236,247,240,27,61,22,112,58,15,47,179,220,238,27,201,36,124,234,83,43,138,339,0,1622092773896;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,207993255-1,2,-94,-118,125745-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0f7edf978cda71a82a5af4b78a8df770924ac922ce0e334cc52acd51016343e3,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;4;5;0",
        ];

        if (count($sensorData) != count($thirdSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $thirdSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    private function getCookiesFromSelenium($retry = false)
    {
        $this->logger->notice(__METHOD__);

        $cacheKey = "sensor_data_walgreens";
        $result = Cache::getInstance()->get($cacheKey);

        if (!empty($result) && $this->attempt == 0) {
            $this->logger->debug("set _abck from cache: {$result}");

            $this->http->setCookie("_abck", $result, ".walgreens.com");

            return null;
        }

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://www.walgreens.com/login.jsp");
            sleep(3);
            $login = $selenium->waitForElement(WebDriverBy::id('user_name'), 5);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if (!in_array($cookie['name'], [
                    '_abck',
                ])) {
                    continue;
                }

                $result = $cookie['value'];
                $this->logger->debug("set new _abck: {$result}");
                Cache::getInstance()->set($cacheKey, $cookie['value'], 60 * 60 * 20);
                $this->http->setCookie("_abck", $result, ".walgreens.com");

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (TimeOutException | SessionNotCreatedException | UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "Exception";
            // retries
            $retry = true;
        }// catch (TimeOutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return 1000;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg('/"loggedIn":"true"/') || $this->http->FindPreg('/"cardNumber":"([^\"]+)/ims')) {
            return true;
        }

        return false;
    }
}
