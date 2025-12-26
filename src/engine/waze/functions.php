<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerWaze extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL_USA = 'https://www.waze.com/_user/api/user/points/details?geoEnv=na';
    private const REWARDS_PAGE_URL_WORLD = 'https://www.waze.com/_user/api/user/points/details?geoEnv=row';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] == 'USA') {
            $balanceLink = self::REWARDS_PAGE_URL_USA;
        } else { // World
            $balanceLink = self::REWARDS_PAGE_URL_WORLD;
        }
        $this->http->RetryCount = 0;
        $this->http->GetURL($balanceLink, [], 20);
        $this->http->RetryCount = 2;
        $details = $this->http->JsonLog(null, 0);

        return isset($details->computedPoints);
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""      => "Please select your region",
            "USA"   => "USA & Canada",
            "World" => "World",
        ];
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->GetURL("https://www.waze.com/en/");
        //$this->http->GetURL("https://www.waze.com/en/signin?redirect=/dashboard");
        // get cookies
        $this->http->GetURL("https://www.waze.com/login/get");
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->http->setDefaultHeader("Origin", "https://www.waze.com");
        $csrf = $this->http->getCookieByName("_csrf_token", "www.waze.com");
        $this->http->setDefaultHeader("X-CSRF-Token", $csrf);

        if ($this->http->Response['code'] != 200 || !$csrf) {
            return false;
        }

        // POST data
        $params = [
            'password' => $this->AccountFields['Pass'],
            'user_id'  => $this->AccountFields['Login'],
        ];

        //$this->http->GetURL($this->http->FindSingleNode("//script[contains(@src,'https://web-assets.waze.com/website/assets/application_legacy')]/@src"));
        //if($key = $this->http->FindPreg('/e\.SITEKEY="(.+?)",/')) {
        $recaptchaPublic = '6LfLjVIUAAAAAEJ5rgYY151d5Zy1qD46Gvc8MP42';
        $captcha = $this->parseReCaptcha($recaptchaPublic);

        if ($captcha === false) {
            return false;
        }
        $params['recaptchaResponse'] = $captcha;
        $params['recaptchaPublic'] = $recaptchaPublic;

        $this->http->PostURL('https://www.waze.com/login/create', $params);

        return true;
    }

    public function Login()
    {
        // check for errors
        return $this->checkErrors();
    }

    public function checkErrors()
    {
        // json-result is invalid and not syntax-correct, fix:
        $jsonResult = $this->http->Response['body'];
        $jsonResult = str_replace('reply:', '"reply":', $jsonResult);
        $jsonResult = preg_replace('#(?<pre>\{|\[|,)\s*(?<key>(?:\w|_)+)\s*:#im', '$1"$2":', $jsonResult);

        // check for errors
        $jsonResult = $this->http->JsonLog($jsonResult, 3, true);

        // login page should return JSON-result.
        if (!is_array($jsonResult)) {
            return false;
        }

        // success login?
        if (!isset($jsonResult['reply']['login']) || !$jsonResult['reply']['login']) {
            // invalid user name or password
            if (strstr($jsonResult['reply']['message'], 'invalid user name or password')) {
                throw new CheckException($jsonResult['reply']['message'], ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($jsonResult['reply']['message'], 'rate exceeded')) {
                throw new CheckRetryNeededException(3);
            }

            return false;
        }// if (!isset($jsonResult['reply']['login']) || !$jsonResult['reply']['login'])

        // everything ok
        return true;
    }

    public function Parse()
    {
        // refs #14180
        if ($this->AccountFields['Login2'] == 'USA') {
            $balanceLink = self::REWARDS_PAGE_URL_USA;
        } else { // World
            $balanceLink = self::REWARDS_PAGE_URL_WORLD;
        }

        if (!strstr($this->http->currentUrl(), $balanceLink)) {
            $this->http->GetURL($balanceLink);
        }

        // provider bug workaround  // refs #21065
        if ($this->http->currentUrl() == 'https://www.waze.com/error_500') {
            if ($this->AccountFields['Login2'] != 'USA') {
                return;
            }

            $this->http->GetURL('https://www.waze.com/Descartes/app/Session?language=en-US');
            $details = $this->http->JsonLog();

            // Balance - Points
            $this->SetBalance($details->totalPoints);
            // Edits
            $this->SetProperty('MapUpdates', $details->totalEdits ?? null);
            // Forum Posts
            $this->SetProperty('ForumPosts', $details->totalForumPosts);

            $this->http->GetURL("https://www.waze.com/UsersProfile/app/userInfo");
            $response = $this->http->JsonLog();
            // Name
            $this->SetProperty('Name', beautifulName($response->firstName . " " . $response->lastName));

            return;
        }

        // km -> miles
        $k = 0.62136994937697;
        // parse
        $details = $this->http->JsonLog();
        // Balance - Points
        $this->SetBalance($details->computedPoints);
        // Map Updates
        $this->SetProperty('MapUpdates', $details->mapUpdatesCount ?? null);
        // New Recorded Miles
        $this->SetProperty('NewRecordedMiles', round($details->newKilometersCount * $k, 2));
        // Existing Miles
        $this->SetProperty('ExistingMiles', round($details->existingKilometersCount * $k, 2));
        // Resolved Update Requests
        $this->SetProperty('ResolvedUpdateRequests', round($details->resolvedUpdateRequestsCount, 2));
        // Forum Posts
        $this->SetProperty('ForumPosts', $details->forumPostsCount);
        // Your Ranking
        $this->SetProperty('YourRanking', $details->ranking ?? null);

        $this->http->GetURL("https://www.waze.com/UsersProfile/app/userInfo");
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty('Name', beautifulName($response->firstName . " " . $response->lastName));
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => 'https://www.waze.com/signin/',
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
