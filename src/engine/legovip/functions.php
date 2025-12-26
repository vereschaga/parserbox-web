<?php

class TAccountCheckerLegovip extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private $headers = [
        'Accept-Encoding' => 'gzip, deflate, br',
        'Accept'          => '*/*',
        'x-locale'        => 'en-US',
        'content-type'    => 'application/json',
        'Referer'         => 'https://shop.lego.com/en-US/vip',
        'lid'             => '',
        'features'        => '',
        'Origin'          => 'https://shop.lego.com',
    ];
    private $inputId;
    private $deviceFingerPrint = "846bcf5d1739e8dcf565dfd1968b161f:Mac + OS:10.14::";

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerLegovipSelenium.php";

        return new TAccountCheckerLegovipSelenium();
    }

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

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $data = '{"page_data":{"text":["link.log out","link.log out link","text.lifetime points","text.redeemable points acct overview widget","text.max fan level","word.to","cms.month:january","cms.month:february","cms.month:march","cms.month:april","cms.month:may","cms.month:june","cms.month:july","cms.month:august","cms.month:september","cms.month:october","cms.month:november","cms.month:december","text.points display","text.point display","text.days","text.hours","text.minutes","text.seconds","error.unknown","button.submit"]},"model_data":{"user":{"me":{"properties":["country","date_created_iso","email_address","encrypted_ct_id","facebook_user_id","fan_rank","first_name","last_name","language","mobile_phone_number","photo_url","redeemable_points","segments","third_party_id","tier","total_points","username"],"query":{"type":"me"}}},"client":{"current":{"properties":["fan_levels"],"query":{"type":"current"}}}}}';
        $this->http->PostURL('https://ct-prod.lego.com/request?widgetId=7566', $data);
        $response = $this->http->JsonLog();

        if (isset($response->model_data->user->me[0])) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->unsetDefaultHeader("Authorization");
        $this->http->unsetDefaultHeader("Accept");

        $this->http->GetURL('https://www.lego.com/en-us/vip');
        $clientId = $this->http->FindPreg('/"clientId":"([\w\-]+)"/');
        $this->logger->debug("clientid: {$clientId}");

        if (!$clientId) {
            return false;
        }
        $this->checkCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://identity.lego.com/api/v1/xsrf');
        $this->http->RetryCount = 2;
        $xsrf = $this->http->getCookieByName('XSRF-TOKEN');

        if (!$xsrf) {
            return false;
        }
        $data = [
            'username'                   => $this->AccountFields['Login'],
            'password'                   => $this->AccountFields['Pass'],
            '__RequestVerificationToken' => $xsrf,
            'rememberme'                 => "true",
            "DeviceFingerPrint"          => $this->deviceFingerPrint,
        ];
        $params = [
            'culture'         => 'en-US',
            'returnUrl'       => "/connect/authorize/callback?appContext=false&adultexperience=true&hideheader=true&scope=openid%20email%20profile%20dob&response_type=id_token%20token&client_id={$clientId}&redirect_uri=https%3A%2F%2Fwww.lego.com%2Fidentity%2Fcallback&ui_locales=en-us&state=Fa4KQLIBBcUYppEu&nonce=ii4MKTk4FqbRHeWY",
            'clientid'        => $clientId,
            'adultExperience' => 'true',
            'hideHeader'      => 'true',
        ];
        $headers = [
            "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
            "Origin" => null,
        ];
        $this->http->PostURL('https://identity.lego.com/api/v1/account/login?' . http_build_query($params), $data, $headers);
        $location = 'https://www.lego.com/identity/callback';
        /*if ($this->http->currentUrl() == 'https://identity.lego.com/en-US/editprofile') {
            $this->http->setMaxRedirects(1);
            $this->http->RetryCount = 0;
            $this->http->GetURL('https://identity.lego.com/connect/authorize?appContext=false&adultexperience=true&hideheader=true&scope=openid+email+profile+dob&response_type=id_token+token&client_id=316ad352-6573-4df0-b707-e7230ab7e0c7&redirect_uri=https%3A%2F%2Fshop.lego.com%2Fidentity%2Fcallback&ui_locales=en-US&state=JOThw0V_R3n-8_I1&nonce=46LZz1n0YkUAwEB3&prompt=none');
            $this->http->RetryCount = 2;
            $this->http->setMaxRedirects(5);
            $location = $this->http->Response['headers']['location'] ?? null;
        }*/

        $idToken = $this->http->FindPreg('/id_token=(.+?)&/', false, $this->http->currentUrl());
        $accessToken = $this->http->FindPreg('/access_token=(.+?)&/', false, $this->http->currentUrl());
        $sessionState = $this->http->FindPreg('/session_state=(.+?)$/', false, $this->http->currentUrl());

        if (!$idToken || !$accessToken || !$sessionState || empty($location)) {
            // Accept our Terms of Services
            if ($this->http->FindPreg('#/en-US/accepttos#i', false, $this->http->currentUrl())) {
                $this->throwAcceptTermsMessageException();
            }
            // Activate Code, We’ve sent an activation code to. Please enter the code below:
            if ($this->parseQuestion()) {
                return false;
            }
            // wrong form fields
            $error = $this->http->FindPreg('/error=([^&]+)/', false, $this->http->currentUrl());

            if ($error) {
                $error = base64_decode(urldecode($error));
                $this->logger->error("[Error]: '{$error}'");
                // Your username and/or password do not match our records.
                if (
                    $error == '{"username":"' . $this->AccountFields['Login'] . '","rememberme":true,"errors":["invalid_login"]}'
                    || $error == '{"username":"' . $this->AccountFields['Login'] . '","rememberMe":true,"errors":["invalid_login"]}'
                ) {
                    throw new CheckException("Your username and/or password do not match our records.", ACCOUNT_INVALID_PASSWORD);
                }
            }// if ($error)

            return false;
        }// if (!$idToken || !$accessToken)

        $this->http->GetURL($location);

        $this->headers['lid'] = http_build_query([
            'accessToken'  => $accessToken,
            'idToken'      => $idToken,
            'sessionState' => $sessionState,
        ]);

        $accessToken = explode('.', $accessToken);

        if (isset($accessToken[1])) {
            $json = $this->http->JsonLog(base64_decode($accessToken[1]));

            if (isset($json->sub)) {
                $this->inputId = $json->sub;
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (
            !$this->http->FindPreg('#/en-US/register/verify#i', false, $this->http->currentUrl())
            && !$this->http->FindPreg('#/en-US/twofactorauthenticate#i', false, $this->http->currentUrl())
        ) {
            return false;
        }

        unset($this->State['clientid']);

        if ($this->http->FindPreg('#/en-US/twofactorauthenticate#i', false, $this->http->currentUrl())) {
            $clientid = $this->http->FindPreg('/clientid=([^\&]+)/', false, $this->http->currentUrl());

            if (!$clientid) {
                return false;
            }

            $this->State['clientid'] = $clientid;
        }

        $this->Question = "We’ve sent an activation code to email. Please enter the code below:";
        $this->Step = "Question";
        $this->ErrorCode = ACCOUNT_QUESTION;

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://identity.lego.com/api/v1/xsrf');
        $this->logger->debug("clientid: {$this->State['clientid']}");

        if (!empty($this->State['clientid'])) {
            $data = [
                "token"                      => $this->Answers[$this->Question],
                "__RequestVerificationToken" => $this->http->getCookieByName('XSRF-TOKEN'),
                "rememberTwoFactor"          => "false",
                "rememberMe"                 => "True",
                "DeviceFingerPrint"          => $this->deviceFingerPrint,
            ];
            $params = [
                'culture'         => 'en-US',
                'returnUrl'       => "/connect/authorize/callback?client_id={$this->State['clientid']}&appContext=false&adultexperience=true&hideheader=true&scope=openid%20email%20profile%20dob&response_type=id_token%20token&redirect_uri=https%3A%2F%2Fwww.lego.com%2Fidentity%2Fcallback&ui_locales=en-us&state=C0AMWx1-1IAaLs7w&nonce=wKqY9kFO7VvmhVKi",
                'clientid'        => $this->State['clientid'],
                'adultExperience' => 'true',
                'hideHeader'      => 'true',
            ];
            $this->http->PostURL("https://identity.lego.com/api/v1/account/twofactorlogin?" . http_build_query($params), $data);
        } else {
            $this->http->PostURL('https://identity.lego.com/api/v1/fullaccount/register/adult/finish?clientid=6a34e0d1-1a2d-4ce7-acd6-7d936ed38001', json_encode([
                'code'    => $this->Answers[$this->Question],
                'culture' => 'en-US',
            ]), [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'x-xsrf-token' => $this->http->getCookieByName('XSRF-TOKEN'),
            ]);
        }

        $this->http->RetryCount = 2;
        unset($this->Answers[$this->Question]);
        $this->http->JsonLog();
        // The activation code is not valid. Please try again!
        if ($this->http->FindPreg('/"Code":\["code_validation_failed"\]/')
            || $this->http->FindPreg('/#error=\w+/', false, $this->http->currentUrl())) {
            $this->AskQuestion($this->Question, 'Two-factor code is not valid.');

            return false;
        }

        $idToken = $this->http->FindPreg('/id_token=(.+?)&/', false, $this->http->currentUrl());
        $accessToken = $this->http->FindPreg('/access_token=(.+?)&/', false, $this->http->currentUrl());
        $sessionState = $this->http->FindPreg('/session_state=(.+?)$/', false, $this->http->currentUrl());
        $this->headers['lid'] = http_build_query([
            'accessToken'  => $accessToken,
            'idToken'      => $idToken,
            'sessionState' => $sessionState,
        ]);
        $accessToken = explode('.', $accessToken);

        if (isset($accessToken[1])) {
            $json = $this->http->JsonLog(base64_decode($accessToken[1]));

            if (isset($json->sub)) {
                $this->inputId = $json->sub;
            }
        }

        return $this->Login();
    }

    public function Login()
    {
        if (!$this->graphqlAuth()) {
            return false;
        }

        $response = $this->http->JsonLog(null, 0);
        // LEGO® VIP LOYALTY PROGRAM
        if (isset($response->data->me->isVip, $response->data->me->username) && $response->data->me->isVip == false) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $data = '{"operationName":"VipLogin","variables":{"input":{"id":"' . $this->inputId . '"}},"query":"mutation VipLogin($input: VipProfileInput!) {\n  vipLogin(input: $input) {\n    ...vipActionFields\n    __typename\n  }\n}\n\nfragment vipActionFields on VipAction {\n  status\n  data {\n    ... on VipRedirectUrl {\n      url\n      __typename\n    }\n    ... on VipProfile {\n      id\n      firstName\n      lastName\n      vipNumber\n      email\n      verifiedEmail\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.lego.com/api/graphql", $data, $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->data->vipLogin->data->url)) {
            $this->http->GetURL($response->data->vipLogin->data->url);

            return $this->loginSuccessful();
        }
        $this->logger->error("redirect not found");

        if ($this->http->FindPreg("/\{\"errors\":\[\{\"message\":\"GraphQLError\",\"locations\":\[\{\"line\":2,\"column\":3\}\],\"path\":\[\"vipLogin\"],\"extensions\":\{\"code\":\"INTERNAL_SERVER_ERROR\",\"exception\":\{\"message\":\"GraphQLError\"\}\}\}\],\"data\":\{\"vipLogin\":null\}\}/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 3, false, "redeemable_points");
        $user = $response->model_data->user->me[0] ?? null;

        if (!$user) {
            $this->logger->error("profile not found");

            return;
        }

        if ($user->tier->currentLevel->title != 'Member') {
            $this->sendNotification("refs #17060. Need to check tier // RR");
        }

        // Balance - VIP POINTS
        $this->SetBalance($user->redeemable_points);
        // Points Earned Since Joining
        $this->SetProperty('LifetimePoints', number_format($user->total_points));
        // Name
        $this->SetProperty('Name', beautifulName("{$user->first_name} {$user->last_name}"));
    }

    private function graphqlAuth($referer = true)
    {
        $this->logger->notice(__METHOD__);

        $data = '{"operationName":"Login","variables":{},"query":"mutation Login {\n  login\n}\n"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.lego.com/api/graphql", $data, $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->data->login)) {
            return false;
        }
        $this->http->unsetDefaultHeader('Authorization');
        $this->headers['authorization'] = $response->data->login;
        $this->http->setCookie('gqauth', $response->data->login, 'www.lego.com');

        if ($referer) {
            $this->headers['Referer'] = 'https://www.lego.com/identity/callback';
        }

        $data = '{"operationName":"User","variables":{},"query":"query User {\n  me {\n    ...Header_User\n    ... on LegoUser {\n      cart {\n        ...Header_Cart\n        __typename\n      }\n      __typename\n    }\n    wishlist {\n      ...Header_Wishlist\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment Header_User on LegoUser {\n  email\n  username\n  isVip\n  isOver13yo\n  isOver18yo\n  bazaarvoiceToken\n  vip {\n    redeemablePoints\n    redeemableValue {\n      formattedAmount\n      __typename\n    }\n    pointsToNextTier\n    __typename\n  }\n  __typename\n}\n\nfragment Header_Cart on Cart {\n  id\n  totalLineItems\n  __typename\n}\n\nfragment Header_Wishlist on Wishlist {\n  id\n  items {\n    id\n    product {\n      id\n      productCode\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.lego.com/api/graphql", $data, $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->data->me)) {
            return false;
        }

        return true;
    }

    private function checkCookies()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://identity.lego.com/en-US/login/?returnUrl=%2Fconnect%2Fauthorize%2Fcallback%3FappContext%3Dfalse%26adultexperience%3Dtrue%26hideheader%3Dtrue%26scope%3Dopenid%2520email%2520profile%2520dob%26response_type%3Did_token%2520token%26client_id%3D316ad352-6573-4df0-b707-e7230ab7e0c7%26redirect_uri%3Dhttps%253A%252F%252Fwww.lego.com%252Fidentity%252Fcallback%26ui_locales%3Den-us%26state%3Dy09MZV9vsVeieO-U%26nonce%3DwgReXjcjEUdyfBCB");
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 10);
//            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
//            $button = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "submitButton"]'), 0);
            $this->saveToLogs($selenium);

            if (!$loginInput/* || !$passwordInput || !$button*/) {
                return false;
            }
//            $loginInput->sendKeys($this->AccountFields['Login']);
//            $passwordInput->sendKeys($this->AccountFields['Pass']);
//            $this->saveToLogs($selenium);
//            $button->click();

            $this->saveToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->saveToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function saveToLogs($selenium)
    {
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
