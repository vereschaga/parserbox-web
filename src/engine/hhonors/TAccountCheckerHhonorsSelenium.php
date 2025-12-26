<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerHhonorsSelenium extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    public const REWARDS_PROFILE_PAGE = 'https://www.hilton.com/en/hilton-honors/guest/my-account/';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $currentItin = 0;
    private $cntSkippedPast = 0;

    private $guestActivitiesSummary = null;

    private $wso2AuthToken = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->FilterHTML = false;
        $this->http->setHttp2(true);
        $this->KeepState = true;
        // refs #21922
        if ($this->attempt > 0) {
            $this->setProxyNetNut();
        } else {
            $this->setProxyGoProxies();
        }

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->useChromePuppeteer();
        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        return;

        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 3;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (
            $fingerprint !== null
            && (!isset($this->State['UserAgent']) || $this->attempt > 1)
        ) {
            $this->logger->info("selected fingerprint {$fingerprint->getId()}, {{$fingerprint->getBrowserFamily()}}:{{$fingerprint->getBrowserVersion()}}, {{$fingerprint->getPlatform()}}, {$fingerprint->getUseragent()}");
            $this->State['Fingerprint'] = $fingerprint->getFingerprint();
            $this->State['Resolution'] = [$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()];
            $this->State['UserAgent'] = $fingerprint->getUseragent();
        }

        if (isset($this->State['UserAgent'])) {
            $this->http->setUserAgent($this->State['UserAgent']);
        }

        if (isset($this->State['Fingerprint'])) {
            $this->logger->debug("set fingerprint");
            $this->seleniumOptions->fingerprint = $this->State['Fingerprint'];
        }

        if (isset($this->State['Resolution'])) {
            $this->logger->debug("set Resolution");
            $this->seleniumOptions->setResolution($this->State['Resolution']);
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        try {
            $this->http->GetURL(self::REWARDS_PROFILE_PAGE, [], 20);
        } catch (
            Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        $this->http->RetryCount = 2;

        try {
            if ($this->loginSuccessful()) {
                return true;
            }
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        try {
            $this->http->GetURL("https://www.hilton.com/en/hilton-honors/login/?forwardUrl=https%3A%2F%2Fwww.hilton.com%2Fen%2Fhilton-honors%2Fguest%2Fmy-account%2F");
        } catch (
            Facebook\WebDriver\Exception\WebDriverCurlException
            $e
        ) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);
        } catch (
            Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        /*
        $iframe = $this->waitForElement(WebDriverBy::xpath('//iframe[@data-e2e="loginIframe"]'), 7);
        $this->saveResponse();

        if (!$iframe) {
            if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                throw new CheckRetryNeededException(3, 1);
            }

            return false;
        }

        $this->driver->switchTo()->frame($iframe);
        */

        $login = $this->waitForElement(WebDriverBy::id('username'), 15);
        $pass = $this->waitForElement(WebDriverBy::id('password'), 5);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@data-e2e = "signInButton"]'), 0);
        $this->saveResponse();

        $hhonors = $this->getHhonors();

        if (!$login || !$pass || !$btn) {
            $this->callRetries();

            return $hhonors->checkErrors();
        }

        // refs #16199
        $this->AccountFields['Pass'] = substr($this->AccountFields['Pass'], 0, 32);

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->sendKeys($login, $this->AccountFields['Login'], 10);
        $mover->sendKeys($pass, $this->AccountFields['Pass'], 10);
        $this->saveResponse();

        try {
            $btn->click();
            $this->saveResponse();
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 5);
        }

        $res = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign Out")] | //span[@data-e2e = "errorText"] | //span[contains(text(), "Hilton Honors #")]'), 15);
        $this->saveResponse();

        if (!$res) {
            // 6LdEdPsSAAAAAGPeTmcbqmTd7dM9M42Zcl7jId8q
            $captcha = $hhonors->parseCaptcha($this->http->FindPreg("/data-key=\"([^\"]+)\"/"));

            if ($captcha === false) {
                throw new CheckRetryNeededException(3, 1);

                return false;
            }

            try {
                $this->http->GetURL("https://www.hilton.com/_sec/cp_challenge/verify?cpt-token={$captcha}");
            } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
                $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 5);
            }
            $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"] | //body'));
            sleep(5);
            $this->http->GetURL("https://www.hilton.com/en/hilton-honors/login/?forwardUrl=https%3A%2F%2Fwww.hilton.com%2Fen%2Fhilton-honors%2Fguest%2Fmy-account%2F");

            /*
            $iframe = $this->waitForElement(WebDriverBy::xpath('//iframe[@data-e2e="loginIframe"]'), 7);
            $this->saveResponse();

            if (!$iframe) {
                return false;
            }

            $this->driver->switchTo()->frame($iframe);
            */

            $login = $this->waitForElement(WebDriverBy::id('username'), 15);
            $pass = $this->waitForElement(WebDriverBy::id('password'), 5);
            $btn = $this->waitForElement(WebDriverBy::xpath('//button[@data-e2e = "signInButton"]'), 0);
            $this->saveResponse();

            if (!$login || !$pass || !$btn) {
                $this->callRetries();

                return $hhonors->checkErrors();
            }

            try {
                $mover = new MouseMover($this->driver);
                $mover->logger = $this->logger;
                $login->click();
                $mover->sendKeys($login, $this->AccountFields['Login'], 10);
                $pass->click();
                $mover->sendKeys($pass, $this->AccountFields['Pass'], 10);
                $this->saveResponse();

                $btn->click();
                $this->saveResponse();
            } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
                $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 5);
            }
        }// if (!$res)

        return true;
    }

    public function Login()
    {
        $res = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign Out")] | //span[@data-e2e = "errorText"] | //span[contains(text(), "Hilton Honors Card")]'), 20);
        $this->saveResponse();

        if (!$res) {
            if ($this->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0)) {
                $this->waitFor(function () {
                    $this->saveResponse();

                    return !$this->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                }, 120);
            }

            // prvider bug fix
            if ($btn = $this->waitForElement(WebDriverBy::xpath('//button[@data-e2e = "signInButton"]'), 0)) {
                $btn->click();
            }

            $res = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign Out")] | //span[@data-e2e = "errorText"] | //span[contains(text(), "Hilton Honors Card")]'), 20);
            $this->saveResponse();
        }

        if ($message = $this->http->FindSingleNode('//span[@data-e2e = "errorText"]')) {
            $this->logger->error("[Error]: {$message}");

            /*
            if ($status == 'invalid_recaptcha') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }
            */

            $this->captchaReporting($this->recognizer);

            if (strstr($message, "Please try again. Be careful: too many attempts will lock your account.")) {
                $this->markProxySuccessful();

                throw new CheckException("Your login didn’t match our records. Please try again. Be careful: too many attempts will lock your account.", ACCOUNT_INVALID_PASSWORD);
            }

            // refs #24563
            if (strstr($message, "Something went wrong, and your request wasn't submitted. Please try again later.")) {
//                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                throw new CheckRetryNeededException(2, 0, $message);
            }
        }// if ($message = $this->http->FindSingleNode('//span[@data-e2e = "errorText"]'))

        // captcha not accepteed, call retry
        if (!$res && $this->http->FindPreg("/data-key=\"([^\"]+)\"/")) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 10);
        }

        try {
            $this->driver->switchTo()->defaultContent();
        } catch (WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 5);
        }

        $this->saveResponse();
        // Access is allowed
        try {
            if ($this->loginSuccessful()) {
                $this->markProxySuccessful();
                $this->captchaReporting($this->recognizer);

                return true;
            }
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);

            sleep(5);

            if ($this->loginSuccessful()) {
                $this->markProxySuccessful();
                $this->captchaReporting($this->recognizer);

                return true;
            }
        }

        // no errors, no auth (AccountID: 3462308)
        if (strstr($this->http->Response['body'], '{"errors":[{"message":"Not Found","locations":[],"path":["guest"],"extensions":{"code":"404","exception":{}},"context":"dx-guests-gql","code":404}],"data":{"guest":null},"extensions":{"logSearch":"')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // provider bug fix, it helps
        if (
            strstr($this->http->Response['body'], '{"errors":[{"message":"Gateway Timeout","locations":[],"path":["guest"],"extensions":{"code":"504","exception":')
            || $this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')
            || $this->http->FindPreg('/\{"errors":\[\{"message":"Service Unavailable",/')
            || (
                $this->http->FindPreg('/"path":\["guest"\],"extensions":\{"code":"503","exception":\{"originalError":\{"message":"\[Breaker:/')
                && $this->http->FindPreg('/\],"data":\{"guest":null\},"extensions":\{"logSearch":"mdc.client_message_id/')
            )
        ) {
            $this->markProxySuccessful();
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(3, 5, self::PROVIDER_ERROR_MSG);
        }

        if ($this->http->FindPreg('/\{"fault":\{"code":900901,"message":"Invalid Credentials","description":"Expired access token\."\}\}/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(3, 5);
        }

        return $this->getHhonors()->checkErrors();
    }

    public function Parse()
    {
        $data = $this->http->JsonLog(null, 0);
        $guestInfo = $data->data->guest->personalinfo ?? null;
        // Name
        $firstName =
            $guestInfo->name->firstName
            ?? $this->http->getCookieByName("firstName")
            ?? ''
        ;
        $lastName = $guestInfo->name->lastName ?? '';
        $name = trim(beautifulName("{$firstName} {$lastName}"));
        $this->SetProperty("Name", $name);
        // Member Number
        $this->SetProperty("Number", $data->data->guest->hhonors->hhonorsNumber ?? null);
        // Status
        $this->SetProperty("Status", $data->data->guest->hhonors->summary->tierName ?? null);

        try {
            $brandGuest = $this->getBrandGuest();
        } catch (
            UnexpectedJavascriptException
            | Facebook\WebDriver\Exception\JavascriptErrorException
            | Facebook\WebDriver\Exception\ScriptTimeoutException $e
        ) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');

            sleep(5);
            $brandGuest = $this->getBrandGuest();
        }

        if (empty($brandGuest)) {
            $brandGuest = $data;
        }

        // refs#23957#note-5
        if (isset($brandGuest->data->guest->hhonors->isLifetimeDiamond)
            && $brandGuest->data->guest->hhonors->isLifetimeDiamond === true) {
            $this->SetProperty("Status", 'Lifetime Diamond');
        }

        // Qualification Period
        $this->SetProperty("YearBegins", strtotime("1 JAN"));
        // Stays
        $this->SetProperty("Stays", $this->http->FindPreg("/\"qualifiedStays\":\s*([^,]+)/"));
        // Nights
        $this->SetProperty("Nights", $brandGuest->data->guest->hhonors->summary->qualifiedNights ?? null);
        // Base Points
        $this->SetProperty("BasePoints", $brandGuest->data->guest->hhonors->summary->qualifiedPointsFmt ?? null);
        // To Maintain Current Level
        $this->SetProperty("ToMaintainCurrentLevel", $brandGuest->data->guest->hhonors->summary->qualifiedNightsMaint ?? null);
        // To Reach Next Level
        $this->SetProperty("ToReachNextLevel", $brandGuest->data->guest->hhonors->summary->qualifiedNightsNext ?? null);
        // Points To Next Level
        $this->SetProperty("PointsToNextLevel", $brandGuest->data->guest->hhonors->summary->qualifiedPointsNextFmt ?? null);
        // Balance - Total Points
        $balance = $brandGuest->data->guest->hhonors->summary->totalPointsFmt ?? $data->props->pageProps->userSession->totalPoints ?? null;

        // refs #20867
        $this->logger->info('Free Night Rewards', ['Header' => 3]);
        $script = '
            fetch("https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_hotel_MyAccount", {
                "credentials": "include",
                "headers": {
                    "Accept": "*/*",
                    "Accept-Language": "en-US,en;q=0.5",
                    "Content-Type": "application/json",
                    "Authorization": "' . $this->wso2AuthToken->tokenType . ' ' . $this->wso2AuthToken->accessToken . '"
                },
                "referrer": "https://www.hilton.com/en/hilton-honors/guest/my-account/",
                "body": atob("eyJvcGVyYXRpb25OYW1lIjoiZ3Vlc3RfaG90ZWxfTXlBY2NvdW50IiwidmFyaWFibGVzIjp7Imd1ZXN0SWQiOkdVRVNUX0lELCJsYW5ndWFnZSI6ImVuIn0sInF1ZXJ5IjoicXVlcnkgZ3Vlc3RfaG90ZWxfTXlBY2NvdW50KCRndWVzdElkOiBCaWdJbnQhLCAkbGFuZ3VhZ2U6IFN0cmluZyEpIHtcbiAgZ3Vlc3QoZ3Vlc3RJZDogJGd1ZXN0SWQsIGxhbmd1YWdlOiAkbGFuZ3VhZ2UpIHtcbiAgICBpZDogZ3Vlc3RJZFxuICAgIGd1ZXN0SWRcbiAgICBwZXJzb25hbGluZm8ge1xuICAgICAgbmFtZSB7XG4gICAgICAgIGZpcnN0TmFtZSBAdG9UaXRsZUNhc2VcbiAgICAgICAgX190eXBlbmFtZVxuICAgICAgfVxuICAgICAgZW1haWxzIHtcbiAgICAgICAgdmFsaWRhdGVkXG4gICAgICAgIF9fdHlwZW5hbWVcbiAgICAgIH1cbiAgICAgIHBob25lcyB7XG4gICAgICAgIHZhbGlkYXRlZFxuICAgICAgICBfX3R5cGVuYW1lXG4gICAgICB9XG4gICAgICBoYXNVU0FkZHJlc3M6IGhhc0FkZHJlc3NXaXRoQ291bnRyeShjb3VudHJ5Q29kZXM6IFtcIlVTXCJdKVxuICAgICAgX190eXBlbmFtZVxuICAgIH1cbiAgICBoaG9ub3JzIHtcbiAgICAgIGhob25vcnNOdW1iZXJcbiAgICAgIGlzVGVhbU1lbWJlclxuICAgICAgaXNMaWZldGltZURpYW1vbmRcbiAgICAgIGlzT3duZXJcbiAgICAgIGlzT3duZXJIR1ZcbiAgICAgIGlzQW1leENhcmRIb2xkZXJcbiAgICAgIHN1bW1hcnkge1xuICAgICAgICB0aWVyXG4gICAgICAgIHRpZXJOYW1lXG4gICAgICAgIG5leHRUaWVyXG4gICAgICAgIHJlcXVhbFRpZXJcbiAgICAgICAgcG9pbnRzRXhwaXJhdGlvblxuICAgICAgICB0aWVyRXhwaXJhdGlvblxuICAgICAgICBuZXh0VGllck5hbWVcbiAgICAgICAgdG90YWxQb2ludHNGbXRcbiAgICAgICAgcXVhbGlmaWVkTmlnaHRzXG4gICAgICAgIHF1YWxpZmllZE5pZ2h0c05leHRcbiAgICAgICAgcXVhbGlmaWVkUG9pbnRzXG4gICAgICAgIHF1YWxpZmllZFBvaW50c05leHRcbiAgICAgICAgcXVhbGlmaWVkUG9pbnRzRm10XG4gICAgICAgIHF1YWxpZmllZFBvaW50c05leHRGbXRcbiAgICAgICAgcXVhbGlmaWVkTmlnaHRzTWFpbnRcbiAgICAgICAgcm9sbGVkT3Zlck5pZ2h0c1xuICAgICAgICBzaG93UmVxdWFsTWFpbnRhaW5NZXNzYWdlXG4gICAgICAgIHNob3dSZXF1YWxEb3duZ3JhZGVNZXNzYWdlXG4gICAgICAgIG1pbGVzdG9uZXMge1xuICAgICAgICAgIGFwcGxpY2FibGVOaWdodHNcbiAgICAgICAgICBib251c1BvaW50c1xuICAgICAgICAgIGJvbnVzUG9pbnRzRm10XG4gICAgICAgICAgYm9udXNQb2ludHNOZXh0XG4gICAgICAgICAgYm9udXNQb2ludHNOZXh0Rm10XG4gICAgICAgICAgbWF4Qm9udXNQb2ludHNcbiAgICAgICAgICBtYXhCb251c1BvaW50c0ZtdFxuICAgICAgICAgIG1heE5pZ2h0c1xuICAgICAgICAgIG5pZ2h0c05leHRcbiAgICAgICAgICBzaG93TWlsZXN0b25lQm9udXNNZXNzYWdlXG4gICAgICAgICAgX190eXBlbmFtZVxuICAgICAgICB9XG4gICAgICAgIF9fdHlwZW5hbWVcbiAgICAgIH1cbiAgICAgIGFtZXhDb3Vwb25zIHtcbiAgICAgICAgX2F2YWlsYWJsZSB7XG4gICAgICAgICAgdG90YWxTaXplXG4gICAgICAgICAgX190eXBlbmFtZVxuICAgICAgICB9XG4gICAgICAgIF9oZWxkIHtcbiAgICAgICAgICB0b3RhbFNpemVcbiAgICAgICAgICBfX3R5cGVuYW1lXG4gICAgICAgIH1cbiAgICAgICAgX3VzZWQge1xuICAgICAgICAgIHRvdGFsU2l6ZVxuICAgICAgICAgIF9fdHlwZW5hbWVcbiAgICAgICAgfVxuICAgICAgICBhdmFpbGFibGUoc29ydDoge2J5OiBzdGFydERhdGUsIG9yZGVyOiBhc2N9KSB7XG4gICAgICAgICAgLi4uR3Vlc3RISG9ub3JzQW1leENvdXBvblxuICAgICAgICAgIF9fdHlwZW5hbWVcbiAgICAgICAgfVxuICAgICAgICBoZWxkIHtcbiAgICAgICAgICAuLi5HdWVzdEhIb25vcnNBbWV4Q291cG9uXG4gICAgICAgICAgX190eXBlbmFtZVxuICAgICAgICB9XG4gICAgICAgIHVzZWQge1xuICAgICAgICAgIC4uLkd1ZXN0SEhvbm9yc0FtZXhDb3Vwb25cbiAgICAgICAgICBfX3R5cGVuYW1lXG4gICAgICAgIH1cbiAgICAgICAgX190eXBlbmFtZVxuICAgICAgfVxuICAgICAgX190eXBlbmFtZVxuICAgIH1cbiAgICBfX3R5cGVuYW1lXG4gIH1cbn1cblxuZnJhZ21lbnQgR3Vlc3RISG9ub3JzQW1leENvdXBvbiBvbiBHdWVzdEhIb25vcnNEZXRhaWxDb3Vwb24ge1xuICBjaGVja0luRGF0ZVxuICBjaGVja091dERhdGVcbiAgY29kZU1hc2tlZFxuICBjaGVja091dERhdGVGbXQobGFuZ3VhZ2U6ICRsYW5ndWFnZSlcbiAgZW5kRGF0ZVxuICBlbmREYXRlRm10KGxhbmd1YWdlOiAkbGFuZ3VhZ2UpXG4gIGxvY2F0aW9uXG4gIG51bWJlck9mTmlnaHRzXG4gIG9mZmVyTmFtZVxuICBwb2ludHNcbiAgcmV3YXJkVHlwZVxuICBzdGFydERhdGVcbiAgc3RhdHVzXG4gIGhvdGVsIHtcbiAgICBuYW1lXG4gICAgaW1hZ2VzIHtcbiAgICAgIG1hc3RlcihpbWFnZVZhcmlhbnQ6IGhvbm9yc1Byb3BlcnR5SW1hZ2VUaHVtYm5haWwpIHtcbiAgICAgICAgdXJsXG4gICAgICAgIGFsdFRleHRcbiAgICAgICAgX190eXBlbmFtZVxuICAgICAgfVxuICAgICAgX190eXBlbmFtZVxuICAgIH1cbiAgICBfX3R5cGVuYW1lXG4gIH1cbiAgX190eXBlbmFtZVxufVxuIn0=").replace("GUEST_ID", ' . $this->wso2AuthToken->guestId . '),
                "method": "POST",
                "mode": "cors"
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("guest_hotel_MyAccount", JSON.stringify(body)));
            })
        ';

        try {
            $this->driver->executeScript($script);
        } catch (
            ScriptTimeoutException
            | TimeOutException
            | Facebook\WebDriver\Exception\TimeoutException
            | Facebook\WebDriver\Exception\ScriptTimeoutException
            $e
        ) {
            $this->logger->error("TimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\UnexpectedJavascriptException $e) {
            $this->logger->error('JavascriptException: ' . $e->getMessage(), ['HtmlEncode' => true]);

            if (str_contains($e->getMessage(), 'Failed to fetch')) {
                sleep(5);
                $this->driver->executeScript($script);
            }
        }
        sleep(2);
        $guest_hotel_MyAccount = $this->driver->executeScript("return localStorage.getItem('guest_hotel_MyAccount');");
        $this->logger->info("[Form guest_hotel_MyAccount]: " . $guest_hotel_MyAccount);

        if (!empty($guest_hotel_MyAccount)) {
            $this->http->SetBody($guest_hotel_MyAccount);
            $this->http->SaveResponse();
        }

        $freeNightResponse = $this->http->JsonLog();

        $this->logger->info('Free Night Rewards: Ready to use', ['Header' => 4]);
        $availableCoupons = $freeNightResponse->data->guest->hhonors->amexCoupons->available ?? [];
        $this->parseFreeNightRewards($availableCoupons);

        $this->logger->info('Free Night Rewards: Reserved for upcoming stay', ['Header' => 4]);
        $reservedCoupons = $freeNightResponse->data->guest->hhonors->amexCoupons->held ?? [];
        $this->parseFreeNightRewards($reservedCoupons, true);

        // Expiration Date  // refs #4761
        $this->http->GetURL("https://www.hilton.com/en/hilton-honors/guest/activity/");
        $this->SetBalance($balance);

        // refs #19889, AccountID: 4960107, 5353853
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $balance === "") {
            $this->logger->notice("provider bug fix balance === '{$balance}'");
            $this->SetBalance(0);
        }

        $this->logger->info('Expiration Date', ['Header' => 3]);
        $this->getHistoryPreload();

        if (isset($this->Properties['Status']) && $this->Properties['Status'] != 'Lifetime Diamond') {
            foreach ($this->guestActivitiesSummary as $transaction) {
                if (isset($transaction->departureDate) && in_array($transaction->guestActivityType, ['past', 'other'])) {
                    $departureDate = $transaction->departureDate;
                    $departureDateTime = strtotime($departureDate);
                    if (!isset($maxTime) || $departureDateTime > $maxTime) {
                        $maxTime = $departureDateTime;
                        $exp = strtotime("+24 months", $departureDateTime);
                        if ($exp
                            // https://redmine.awardwallet.com/issues/21728#note-8
                            && $transaction->totalPoints != 0
                        ) {
                            $this->SetProperty('LastActivity', $departureDate);
                            $this->SetExpirationDate($exp);
                        }
                    }
                }
            }// foreach ($this->guestActivitiesSummary as $transaction)
        } elseif (isset($this->Properties['Status']) && $this->Properties['Status'] == 'Lifetime Diamond') {
            $this->SetExpirationDateNever();
            $this->SetProperty('AccountExpirationWarning', 'do not expire with elite status');
            $this->ClearExpirationDate();
        }
    }

    public function GetHistoryColumns()
    {
        return $this->getHhonors()->GetHistoryColumns();
    }

    public function getHistoryPreload()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->getHistory();
        } catch (
            ScriptTimeoutException
            | TimeOutException
            | Facebook\WebDriver\Exception\TimeoutException
            | Facebook\WebDriver\Exception\ScriptTimeoutException
            $e
        ) {
            $this->logger->error("TimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);

            sleep(5);
            $this->getHistory();
        }
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');

        if (isset($startDate)) {
            $startDate = strtotime('-4 day', $startDate);
            $this->logger->debug('>> [set historyStartDate date -4 days]: ' . $startDate);
        }
        $startTimer = $this->getTime();

        $this->getHistoryPreload();
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->getHhonors()->ParsePageHistory($this->guestActivitiesSummary, $startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParseItineraries()
    {
        $this->getHistoryPreload();
        $guestActivitiesSummary = json_encode($this->guestActivitiesSummary);
        $activities = json_decode($guestActivitiesSummary, true);

        $upcoming = [];
        $cancelled = [];
        $past = [];
        $this->cntSkippedPast = 0;
        $checkNewType = false;

        foreach ($activities as $activity) {
            $type = ArrayVal($activity, 'guestActivityType');

            if ($type === 'upcoming') {
                $upcoming[] = $activity;
            } elseif ($type === 'cancelled') {
                $cancelled[] = $activity;
            } elseif ($type === 'past') {
                $past[] = $activity;
            } elseif ($type === 'other') {
                $this->logger->notice('Skipping type other');
            } else {
                $this->logger->notice("New type: {$type}");
                $checkNewType = true;
            }
        }

        if ($checkNewType) {
            $this->sendNotification('Check new itin type // MI');
        }
        $cntUpcoming = count($upcoming);
        $cntCancelled = count($cancelled);
        $cntPast = count($past);
        $this->logger->info(sprintf('Found %s upcoming itineraries', $cntUpcoming));
        $this->logger->info(sprintf('Found %s cancelled itineraries', $cntCancelled));
        $this->logger->info(sprintf('Found %s past itineraries', $cntPast));

        if (empty($activities) && ($cntUpcoming + $cntCancelled + $cntPast) == 0 && $this->http->FindPreg('/^\[\]$/', false, $guestActivitiesSummary)) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        $this->logger->info("Parse main info for itineraries (total: {$cntUpcoming})", ['Header' => 3]);

        foreach ($upcoming as $i => $activity) {
            if ($i >= 50) {
                $this->logger->debug("Save {$i} reservations");

                break;
            }

            $reservationData = $this->getReservationData($activity);

            // sometimes it helps
            if ($this->http->FindPreg("/\"data\":\{\"reservation\":null\},\"extensions\":/")) {
                sleep(2);
                $reservationData = $this->getReservationData($activity);
            }

            if ($reservationData) {
                $this->parseItinerary($reservationData);
            } else {
                $this->parseMinimalItinerary($activity);
            }

            if ($i % 5 === 0) {
                $this->logger->notice('Increase Time Limit: 100 sec');
                $this->increaseTimeLimit(50);
            }
        }
        $this->logger->info("Parse info for cancelled itineraries (total: {$cntCancelled})", ['Header' => 3]);

        foreach ($cancelled as $activity) {
            $this->parseMinimalItinerary($activity, $cntCancelled <= 20);
        }

        if ($this->ParsePastIts) {
            $this->logger->info("Parse info for past itineraries (total: {$cntPast})", ['Header' => 3]);

            foreach ($past as $activity) {
                $this->parseMinimalItinerary($activity, false);
            }
        } else {
            // cause not interest
            $cntPast = 0;
        }
        $this->logger->debug("cntSkippedPast " . $this->cntSkippedPast);
        $this->logger->debug("cntUpcoming " . $cntUpcoming);
        $this->logger->debug("cntCancelled " . $cntCancelled);
        $this->logger->debug("cntPast " . $cntPast);
        // NoItineraries
        if (!empty($activities) && count($this->itinerariesMaster->getItineraries()) === 0
            && $cntUpcoming + $cntCancelled + $cntPast === $this->cntSkippedPast
        ) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    private function callRetries()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('
                    //h1[contains(text(), "Access Denied")]
                    | //span[contains(text(), "This site can’t be reached")]
                ')
            || empty($this->http->Response['body'])
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 1);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == 'wso2AuthToken') {
                $this->wso2AuthToken = $this->http->JsonLog($cookie['value']);
            }
        }

        if (!isset($this->wso2AuthToken->accessToken) || !isset($this->wso2AuthToken->guestId)) {
            $this->logger->error("get brand guest failed: token or guest id not found");

            return false;
        }

        $this->driver->executeScript('
            fetch("https://www.hilton.com/graphql/customer?operationName=guest", {
                "credentials": "include",
                "headers": {
                    "Accept": "application/json; charset=utf-8",
                    "Accept-Language": "en-US,en;q=0.5",
                    "Content-Type": "application/json; charset=utf-8",
                    "Authorization": "' . $this->wso2AuthToken->tokenType . ' ' . $this->wso2AuthToken->accessToken . '"
                },
                "referrer": "https://www.hilton.com/en/hilton-honors/login/?forwardPageURI=%2Fen%2Fhilton-honors%2Fguest%2Fmy-account%2F",
                "body": "{\"query\":\"query guest($guestId: BigInt!, $language: String!) {\\\n  guest(guestId: $guestId, language: $language) {\\\n    guestId\\\n    hhonors {\\\n      hhonorsNumber\\\n      isFamilyAndFriends\\\n      isLongTenure10\\\n      isTeamMember\\\n      isTeamMemberSpouse\\\n      isOwner\\\n      isLongTenure20\\\n      isOwnerHGV\\\n      isOwnerHGVNew\\\n      summary {\\\n        points: totalPointsFmt\\\n        tier\\\n        tierName\\\n        totalPoints\\\n        totalPointsFmt\\\n      }\\\n      packages {\\\n        packageName\\\n      }\\\n    }\\\n    personalinfo {\\\n      name {\\\n        firstName @toTitleCase\\\n      }\\\n    }\\\n  }\\\n}\",\"variables\":{\"guestId\":' . $this->wso2AuthToken->guestId . ',\"language\":\"en\"},\"operationName\":\"guest\"}",
                "method": "POST",
                "mode": "cors"
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("guestData", JSON.stringify(body)));
            })
        ');
        sleep(2);
        $guestData = $this->driver->executeScript("return localStorage.getItem('guestData');");
        $this->logger->info("[Form guest]: " . $guestData);

        // it helps
        if (empty($guestData)) {
            sleep(5);
            $guestData = $this->driver->executeScript("return localStorage.getItem('guestData');");
            $this->logger->info("[Form guest]: " . $guestData);
        }

        if (!empty($guestData)) {
            $this->http->SetBody($guestData);
            $this->http->SaveResponse();
        }

        $data = $this->http->JsonLog();

        if ($data->data->guest->hhonors->hhonorsNumber ?? null) {
            return true;
        }

        return false;
    }

    private function getHistory()
    {
        $this->logger->notice(__METHOD__);

        if (!empty($this->guestActivitiesSummary)) {
            return $this->guestActivitiesSummary;
        }

        $startDate = date("Y-m-d", strtotime("-1 year"));
        $endDate = date("Y-m-d", strtotime("+1 year"));
        $this->increaseTimeLimit();

        $this->driver->executeScript('
            fetch("https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_guestActivitySummaryOptions", {
                "credentials": "include",
                "headers": {
                    "Accept": "*/*",
                    "Accept-Language": "en-US,en;q=0.5",
                    "Content-Type": "application/json",
                    "Authorization": "' . $this->wso2AuthToken->tokenType . ' ' . $this->wso2AuthToken->accessToken . '"
                },
                "referrer": "https://www.hilton.com/en/hilton-honors/guest/activity/",
                "body": atob("eyJvcGVyYXRpb25OYW1lIjoiZ3Vlc3RfZ3Vlc3RBY3Rpdml0eVN1bW1hcnlPcHRpb25zIiwidmFyaWFibGVzIjp7Imd1ZXN0SWQiOkdVRVNUX0lELCJsYW5ndWFnZSI6ImVuIiwic3RhcnREYXRlIjoiU1RBUlRfREFURSIsImVuZERhdGUiOiJFTkRfREFURSIsImd1ZXN0QWN0aXZpdHlUeXBlcyI6WyJ1cGNvbWluZyIsImNhbmNlbGxlZCIsInBhc3QiLCJvdGhlciJdLCJzb3J0IjpbeyJieSI6ImFycml2YWxEYXRlIiwib3JkZXIiOiJhc2MifV19LCJxdWVyeSI6InF1ZXJ5IGd1ZXN0X2d1ZXN0QWN0aXZpdHlTdW1tYXJ5T3B0aW9ucygkZ3Vlc3RJZDogQmlnSW50ISwgJGxhbmd1YWdlOiBTdHJpbmchLCAkc3RhcnREYXRlOiBTdHJpbmchLCAkZW5kRGF0ZTogU3RyaW5nISwgJGd1ZXN0QWN0aXZpdHlUeXBlczogW0d1ZXN0QWN0aXZpdHlUeXBlXSwgJHNvcnQ6IFtTdGF5SEhvbm9yc0FjdGl2aXR5U3VtbWFyeVNvcnRJbnB1dCFdKSB7XG4gIGd1ZXN0KGd1ZXN0SWQ6ICRndWVzdElkLCBsYW5ndWFnZTogJGxhbmd1YWdlKSB7XG4gICAgaWQ6IGd1ZXN0SWRcbiAgICBndWVzdElkXG4gICAgaGhvbm9ycyB7XG4gICAgICBzdW1tYXJ5IHtcbiAgICAgICAgdGllck5hbWVcbiAgICAgICAgdG90YWxQb2ludHNcbiAgICAgICAgX190eXBlbmFtZVxuICAgICAgfVxuICAgICAgX190eXBlbmFtZVxuICAgIH1cbiAgICBhY3Rpdml0eVN1bW1hcnlPcHRpb25zKFxuICAgICAgaW5wdXQ6IHtncm91cE11bHRpUm9vbVN0YXlzOiB0cnVlLCBzdGFydERhdGU6ICRzdGFydERhdGUsIGVuZERhdGU6ICRlbmREYXRlLCBndWVzdEFjdGl2aXR5VHlwZXM6ICRndWVzdEFjdGl2aXR5VHlwZXN9XG4gICAgKSB7XG4gICAgICBndWVzdEFjdGl2aXRpZXNTdW1tYXJ5KHNvcnQ6ICRzb3J0KSB7XG4gICAgICAgIC4uLlN0YXlBY3Rpdml0eVN1bW1hcnlcbiAgICAgICAgcm9vbURldGFpbHMge1xuICAgICAgICAgIC4uLlN0YXlSb29tRGV0YWlsc1xuICAgICAgICAgIF9fdHlwZW5hbWVcbiAgICAgICAgfVxuICAgICAgICB0cmFuc2FjdGlvbnMge1xuICAgICAgICAgIC4uLlN0YXlUcmFuc2FjdGlvblxuICAgICAgICAgIF9fdHlwZW5hbWVcbiAgICAgICAgfVxuICAgICAgICBfX3R5cGVuYW1lXG4gICAgICB9XG4gICAgICBfX3R5cGVuYW1lXG4gICAgfVxuICAgIF9fdHlwZW5hbWVcbiAgfVxufVxuXG5mcmFnbWVudCBTdGF5QWN0aXZpdHlTdW1tYXJ5IG9uIFN0YXlISG9ub3JzQWN0aXZpdHlTdW1tYXJ5IHtcbiAgbnVtUm9vbXNcbiAgc3RheUlkXG4gIGFycml2YWxEYXRlXG4gIGF1dG9VcGdyYWRlZFN0YXlcbiAgaXNTdGF5VXBzZWxsXG4gIGlzU3RheVVwc2VsbE92ZXJBdXRvVXBncmFkZVxuICBkZXBhcnR1cmVEYXRlXG4gIGhvdGVsTmFtZVxuICBkZXNjXG4gIGRlc2NGbXQ6IGRlc2MgQHRvVGl0bGVDYXNlXG4gIGd1ZXN0QWN0aXZpdHlUeXBlXG4gIGNoZWNraW5FbGlnaWJpbGl0eVN0YXR1c1xuICBicmFuZENvZGVcbiAgYm9va0FnYWluVXJsXG4gIGNoZWNraW5VcmxcbiAgY29uZk51bWJlclxuICBjeGxOdW1iZXJcbiAgZGlnaXRhbEtleU9mZmVyZWRVcmxcbiAgbGVuZ3RoT2ZTdGF5XG4gIHZpZXdGb2xpb1VybFxuICB2aWV3T3JFZGl0UmVzZXJ2YXRpb25VcmxcbiAgYmFzZVBvaW50c1xuICBiYXNlUG9pbnRzRm10XG4gIGJvbnVzUG9pbnRzXG4gIGJvbnVzUG9pbnRzRm10XG4gIGVhcm5lZFBvaW50c1xuICBlYXJuZWRQb2ludHNGbXRcbiAgdG90YWxQb2ludHNcbiAgdG90YWxQb2ludHNGbXRcbiAgdXNlZFBvaW50c1xuICB1c2VkUG9pbnRzRm10XG4gIHJvb21OdW1iZXJcbiAgc2hvd0F1dG9VcGdyYWRlSW5kaWNhdG9yXG4gIF9fdHlwZW5hbWVcbn1cblxuZnJhZ21lbnQgU3RheVJvb21EZXRhaWxzIG9uIFN0YXlISG9ub3JzQWN0aXZpdHlSb29tRGV0YWlsIHtcbiAgYmFzZVBvaW50c0ZtdFxuICBib251c1BvaW50c0ZtdFxuICBjaGVja2luVXJsXG4gIGN4bE51bWJlclxuICBjaGVja2luRWxpZ2liaWxpdHlTdGF0dXNcbiAgZ3Vlc3RBY3Rpdml0eVR5cGVcbiAgcm9vbVNlcmllc1xuICByb29tTnVtYmVyXG4gIHJvb21UeXBlTmFtZVxuICByb29tVHlwZU5hbWVGbXQ6IHJvb21UeXBlTmFtZSBAdHJ1bmNhdGUoYnlXb3JkczogdHJ1ZSwgbGVuZ3RoOiAzKVxuICB0b3RhbFBvaW50c0ZtdFxuICB1c2VkUG9pbnRzRm10XG4gIHZpZXdGb2xpb1VybFxuICBib29rQWdhaW5VcmxcbiAgYWRqb2luaW5nUm9vbVN0YXlcbiAgdHJhbnNhY3Rpb25zIHtcbiAgICAuLi5TdGF5VHJhbnNhY3Rpb25cbiAgICBfX3R5cGVuYW1lXG4gIH1cbiAgX190eXBlbmFtZVxufVxuXG5mcmFnbWVudCBTdGF5VHJhbnNhY3Rpb24gb24gU3RheUhIb25vcnNUcmFuc2FjdGlvbiB7XG4gIHRyYW5zYWN0aW9uSWRcbiAgdHJhbnNhY3Rpb25UeXBlXG4gIHBhcnRuZXJOYW1lXG4gIGJhc2VFYXJuaW5nT3B0aW9uXG4gIGd1ZXN0QWN0aXZpdHlQb2ludHNUeXBlXG4gIGRlc2NyaXB0aW9uXG4gIGRlc2NyaXB0aW9uRm10OiBkZXNjcmlwdGlvbiBAdG9UaXRsZUNhc2VcbiAgYmFzZVBvaW50c1xuICBiYXNlUG9pbnRzRm10XG4gIGJvbnVzUG9pbnRzXG4gIGJvbnVzUG9pbnRzRm10XG4gIGVhcm5lZFBvaW50c1xuICBlYXJuZWRQb2ludHNGbXRcbiAgdXNlZFBvaW50c1xuICB1c2VkUG9pbnRzRm10XG4gIF9fdHlwZW5hbWVcbn1cbiJ9").replace("GUEST_ID", ' . $this->wso2AuthToken->guestId . ').replace("START_DATE", "' . $startDate . '").replace("END_DATE", "' . $endDate . '"),
                "method": "POST",
                "mode": "cors"
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("historyData", JSON.stringify(body)));
            })
        ');
        sleep(2);
        $historyData = $this->driver->executeScript("return localStorage.getItem('historyData');");
        $this->logger->debug("[Form historyData]: " . $historyData);

        $response = $this->http->JsonLog($historyData, 3, false, 'guestActivitiesSummary');
        $this->guestActivitiesSummary = $response->data->guest->activitySummaryOptions->guestActivitiesSummary ?? [];

        return $this->guestActivitiesSummary;
    }

    private function parseFreeNightRewards($coupons, $reserved = false)
    {
        $displayNameDescription = '';

        if ($reserved === true) {
            $displayNameDescription = 'Reserved ';
        }

        foreach ($coupons as $coupon) {
            $code = str_replace('••••• ', '', $coupon->codeMasked);
            $exp = $coupon->endDate;
            $displayName = $displayNameDescription . $coupon->offerName . ' Certificate # ' . $coupon->codeMasked;
            $this->AddSubAccount([
                'Code'           => "amexFreeNightRewards" . str_replace(' - ', '', $displayNameDescription) . $code . strtotime($exp),
                'DisplayName'    => $displayName,
                'Balance'        => $coupon->points,
                'Number'         => $coupon->codeMasked,
                'ExpirationDate' => strtotime($exp),
            ]);
        }// foreach ($amexCoupons as $amexCoupon)
    }

    private function getBrandGuest()
    {
        $this->logger->notice(__METHOD__);
        $this->driver->executeScript('
            fetch("https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_hotel_MyAccount", {
                "credentials": "include",
                "headers": {
                    "Accept": "*/*",
                    "Accept-Language": "en-US,en;q=0.5",
                    "Content-Type": "application/json",
                    "Authorization": "' . $this->wso2AuthToken->tokenType . ' ' . $this->wso2AuthToken->accessToken . '"
                },
                "referrer": "https://www.hilton.com/en/hilton-honors/guest/my-account/",
                "body": atob("eyJvcGVyYXRpb25OYW1lIjoiZ3Vlc3RfaG90ZWxfTXlBY2NvdW50IiwidmFyaWFibGVzIjp7Imd1ZXN0SWQiOkdVRVNUX0lELCJsYW5ndWFnZSI6ImVuIn0sInF1ZXJ5IjoicXVlcnkgZ3Vlc3RfaG90ZWxfTXlBY2NvdW50KCRndWVzdElkOiBCaWdJbnQhLCAkbGFuZ3VhZ2U6IFN0cmluZyEpIHtcbiAgZ3Vlc3QoZ3Vlc3RJZDogJGd1ZXN0SWQsIGxhbmd1YWdlOiAkbGFuZ3VhZ2UpIHtcbiAgICBpZDogZ3Vlc3RJZFxuICAgIGd1ZXN0SWRcbiAgICBwZXJzb25hbGluZm8ge1xuICAgICAgbmFtZSB7XG4gICAgICAgIGZpcnN0TmFtZSBAdG9UaXRsZUNhc2VcbiAgICAgICAgX190eXBlbmFtZVxuICAgICAgfVxuICAgICAgZW1haWxzIHtcbiAgICAgICAgdmFsaWRhdGVkXG4gICAgICAgIF9fdHlwZW5hbWVcbiAgICAgIH1cbiAgICAgIHBob25lcyB7XG4gICAgICAgIHZhbGlkYXRlZFxuICAgICAgICBfX3R5cGVuYW1lXG4gICAgICB9XG4gICAgICBoYXNVU0FkZHJlc3M6IGhhc0FkZHJlc3NXaXRoQ291bnRyeShjb3VudHJ5Q29kZXM6IFtcIlVTXCJdKVxuICAgICAgX190eXBlbmFtZVxuICAgIH1cbiAgICBoaG9ub3JzIHtcbiAgICAgIGhob25vcnNOdW1iZXJcbiAgICAgIGlzVGVhbU1lbWJlclxuICAgICAgaXNMaWZldGltZURpYW1vbmRcbiAgICAgIGlzT3duZXJcbiAgICAgIGlzT3duZXJIR1ZcbiAgICAgIGlzQW1leENhcmRIb2xkZXJcbiAgICAgIHN1bW1hcnkge1xuICAgICAgICB0aWVyXG4gICAgICAgIHRpZXJOYW1lXG4gICAgICAgIG5leHRUaWVyXG4gICAgICAgIHJlcXVhbFRpZXJcbiAgICAgICAgcG9pbnRzRXhwaXJhdGlvblxuICAgICAgICB0aWVyRXhwaXJhdGlvblxuICAgICAgICBuZXh0VGllck5hbWVcbiAgICAgICAgdG90YWxQb2ludHNGbXRcbiAgICAgICAgcXVhbGlmaWVkTmlnaHRzXG4gICAgICAgIHF1YWxpZmllZE5pZ2h0c05leHRcbiAgICAgICAgcXVhbGlmaWVkUG9pbnRzXG4gICAgICAgIHF1YWxpZmllZFBvaW50c05leHRcbiAgICAgICAgcXVhbGlmaWVkUG9pbnRzRm10XG4gICAgICAgIHF1YWxpZmllZFBvaW50c05leHRGbXRcbiAgICAgICAgcXVhbGlmaWVkTmlnaHRzTWFpbnRcbiAgICAgICAgcm9sbGVkT3Zlck5pZ2h0c1xuICAgICAgICBzaG93UmVxdWFsTWFpbnRhaW5NZXNzYWdlXG4gICAgICAgIHNob3dSZXF1YWxEb3duZ3JhZGVNZXNzYWdlXG4gICAgICAgIG1pbGVzdG9uZXMge1xuICAgICAgICAgIGFwcGxpY2FibGVOaWdodHNcbiAgICAgICAgICBib251c1BvaW50c1xuICAgICAgICAgIGJvbnVzUG9pbnRzRm10XG4gICAgICAgICAgYm9udXNQb2ludHNOZXh0XG4gICAgICAgICAgYm9udXNQb2ludHNOZXh0Rm10XG4gICAgICAgICAgbWF4Qm9udXNQb2ludHNcbiAgICAgICAgICBtYXhCb251c1BvaW50c0ZtdFxuICAgICAgICAgIG1heE5pZ2h0c1xuICAgICAgICAgIG5pZ2h0c05leHRcbiAgICAgICAgICBzaG93TWlsZXN0b25lQm9udXNNZXNzYWdlXG4gICAgICAgICAgX190eXBlbmFtZVxuICAgICAgICB9XG4gICAgICAgIF9fdHlwZW5hbWVcbiAgICAgIH1cbiAgICAgIGFtZXhDb3Vwb25zIHtcbiAgICAgICAgX2F2YWlsYWJsZSB7XG4gICAgICAgICAgdG90YWxTaXplXG4gICAgICAgICAgX190eXBlbmFtZVxuICAgICAgICB9XG4gICAgICAgIF9oZWxkIHtcbiAgICAgICAgICB0b3RhbFNpemVcbiAgICAgICAgICBfX3R5cGVuYW1lXG4gICAgICAgIH1cbiAgICAgICAgX3VzZWQge1xuICAgICAgICAgIHRvdGFsU2l6ZVxuICAgICAgICAgIF9fdHlwZW5hbWVcbiAgICAgICAgfVxuICAgICAgICBhdmFpbGFibGUoc29ydDoge2J5OiBzdGFydERhdGUsIG9yZGVyOiBhc2N9KSB7XG4gICAgICAgICAgLi4uR3Vlc3RISG9ub3JzQW1leENvdXBvblxuICAgICAgICAgIF9fdHlwZW5hbWVcbiAgICAgICAgfVxuICAgICAgICBoZWxkIHtcbiAgICAgICAgICAuLi5HdWVzdEhIb25vcnNBbWV4Q291cG9uXG4gICAgICAgICAgX190eXBlbmFtZVxuICAgICAgICB9XG4gICAgICAgIHVzZWQge1xuICAgICAgICAgIC4uLkd1ZXN0SEhvbm9yc0FtZXhDb3Vwb25cbiAgICAgICAgICBfX3R5cGVuYW1lXG4gICAgICAgIH1cbiAgICAgICAgX190eXBlbmFtZVxuICAgICAgfVxuICAgICAgX190eXBlbmFtZVxuICAgIH1cbiAgICBfX3R5cGVuYW1lXG4gIH1cbn1cblxuZnJhZ21lbnQgR3Vlc3RISG9ub3JzQW1leENvdXBvbiBvbiBHdWVzdEhIb25vcnNEZXRhaWxDb3Vwb24ge1xuICBjaGVja0luRGF0ZVxuICBjaGVja091dERhdGVcbiAgY29kZU1hc2tlZFxuICBjaGVja091dERhdGVGbXQobGFuZ3VhZ2U6ICRsYW5ndWFnZSlcbiAgZW5kRGF0ZVxuICBlbmREYXRlRm10KGxhbmd1YWdlOiAkbGFuZ3VhZ2UpXG4gIGxvY2F0aW9uXG4gIG51bWJlck9mTmlnaHRzXG4gIG9mZmVyTmFtZVxuICBwb2ludHNcbiAgcmV3YXJkVHlwZVxuICBzdGFydERhdGVcbiAgc3RhdHVzXG4gIGhvdGVsIHtcbiAgICBuYW1lXG4gICAgaW1hZ2VzIHtcbiAgICAgIG1hc3RlcihpbWFnZVZhcmlhbnQ6IGhvbm9yc1Byb3BlcnR5SW1hZ2VUaHVtYm5haWwpIHtcbiAgICAgICAgdXJsXG4gICAgICAgIGFsdFRleHRcbiAgICAgICAgX190eXBlbmFtZVxuICAgICAgfVxuICAgICAgX190eXBlbmFtZVxuICAgIH1cbiAgICBfX3R5cGVuYW1lXG4gIH1cbiAgX190eXBlbmFtZVxufVxuIn0=").replace("GUEST_ID", ' . $this->wso2AuthToken->guestId . '),
                "method": "POST",
                "mode": "cors"
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("guest_hotel_MyAccount", JSON.stringify(body)));
            })
        ');
        $this->logger->debug("request sent");
        sleep(2);
        $this->logger->debug("get data");
        $guestHotelMyAccountData = $this->driver->executeScript("return localStorage.getItem('guest_hotel_MyAccount');");
        $this->logger->info("[Form guest_hotel_MyAccount]: " . $guestHotelMyAccountData);

        if (!empty($guestHotelMyAccountData)) {
            $this->http->SetBody($guestHotelMyAccountData);
            $this->http->SaveResponse();
        }

        return $this->http->JsonLog();
    }

    private function getReservationData($activity)
    {
        $this->logger->notice(__METHOD__);
        $confNumber = ArrayVal($activity, 'confNumber');
        $lastName = $this->http->FindPreg('/lastName=(.+)/', false, ArrayVal($activity, 'viewOrEditReservationUrl'));
        $this->logger->error('[lastName]: "' . $lastName . '"');
        $this->logger->error('[confNo]: "' . $confNumber . '"');

        if (!$lastName) {
            $this->logger->error('lastName is missing');

            return null;
        }
        $arrivalDate = ArrayVal($activity, 'arrivalDate');
        $guestId = $this->wso2AuthToken->guestId;

        if (!isset($this->wso2AuthToken->guestId)) {
            $this->logger->error('guestId missing');

            return null;
        }

        if (!isset($this->wso2AuthToken->accessToken)) {
            $this->logger->error('auth token missing');

            return null;
        }

        $auth = "Bearer {$this->wso2AuthToken->accessToken}";

        try {
            $this->sendReservationRequest($auth, $confNumber, $lastName, $arrivalDate, $guestId);
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);

            sleep(5);
            $this->sendReservationRequest($auth, $confNumber, $lastName, $arrivalDate, $guestId);
        }

        $result = $this->http->JsonLog(null, 3, true);

        $message = $result->errors[0]->message ?? null;

        if ($message && in_array($message, ['Gateway Timeout', 'Service Unavailable'])) {
            sleep(5);
            $this->logger->error("[Retrying]: {$message}");
            $this->sendNotification("Gateway Timeout / Service Unavailable // RR");
            $this->sendReservationRequest($auth, $confNumber, $lastName, $arrivalDate, $guestId);
            $result = $this->http->JsonLog(null, 3, true);
        }

        return $result;
    }

    private function sendReservationRequest($auth, $confNumber, $lastName, $arrivalDate, $guestId)
    {
        $this->logger->notice(__METHOD__);
        $this->driver->executeScript($script = '
            fetch("https://www.hilton.com/graphql/customer?appName=dx-reservations-ui&language=en&operationName=reservation", {
                "credentials": "include",
                "headers": {
                    "Accept": "*/*",
                    "Accept-Language": "en-US,en;q=0.5",
                    "Content-Type": "application/json",
                    "Authorization": "' . $auth . '"
                },
                "referrer": "https://www.hilton.com/en/hilton-honors/guest/my-account/",
                "body": atob("eyJxdWVyeSI6InF1ZXJ5IHJlc2VydmF0aW9uKCRjb25mTnVtYmVyOiBTdHJpbmchLCAkbGFuZ3VhZ2U6IFN0cmluZyEsICRndWVzdElkOiBCaWdJbnQsICRsYXN0TmFtZTogU3RyaW5nISwgJGFycml2YWxEYXRlOiBTdHJpbmchKSB7XG4gIHJlc2VydmF0aW9uKFxuICAgIGNvbmZOdW1iZXI6ICRjb25mTnVtYmVyXG4gICAgbGFuZ3VhZ2U6ICRsYW5ndWFnZVxuICAgIGF1dGhJbnB1dDoge2d1ZXN0SWQ6ICRndWVzdElkLCBsYXN0TmFtZTogJGxhc3ROYW1lLCBhcnJpdmFsRGF0ZTogJGFycml2YWxEYXRlfVxuICApIHtcbiAgICAuLi5SRVNFUlZBVElPTl9GUkFHTUVOVFxuICB9XG59XG5cbiAgICAgIFxuICAgIGZyYWdtZW50IFJFU0VSVkFUSU9OX0ZSQUdNRU5UIG9uIFJlc2VydmF0aW9uIHtcbiAgYWRkT25zUmVzTW9kaWZ5RWxpZ2libGVcbiAgY29uZk51bWJlclxuICBhcnJpdmFsRGF0ZVxuICBkZXBhcnR1cmVEYXRlXG4gIGNhbmNlbEVsaWdpYmxlXG4gIG1vZGlmeUVsaWdpYmxlXG4gIGN4bE51bWJlclxuICByZXN0cmljdGVkXG4gIGFkam9pbmluZ1Jvb21TdGF5XG4gIGFkam9pbmluZ1Jvb21zRmFpbHVyZVxuICBzY2FSZXF1aXJlZFxuICBhdXRvVXBncmFkZWRTdGF5XG4gIHNob3dBdXRvVXBncmFkZUluZGljYXRvclxuICBzcGVjaWFsUmF0ZU9wdGlvbnMge1xuICAgIGNvcnBvcmF0ZUlkXG4gICAgZ3JvdXBDb2RlXG4gICAgaGhvbm9yc1xuICAgIHBuZFxuICAgIHByb21vQ29kZVxuICAgIHRyYXZlbEFnZW50XG4gICAgZmFtaWx5QW5kRnJpZW5kc1xuICAgIHRlYW1NZW1iZXJcbiAgICBvd25lclxuICAgIG93bmVySEdWXG4gIH1cbiAgY2xpZW50QWNjb3VudHMge1xuICAgIGNsaWVudElkXG4gICAgY2xpZW50VHlwZVxuICAgIGNsaWVudE5hbWVcbiAgfVxuICBjb21tZW50cyB7XG4gICAgZ2VuZXJhbEluZm9cbiAgfVxuICBkaXNjbGFpbWVyIHtcbiAgICBkaWFtb25kNDhcbiAgICBmdWxsUHJlUGF5Tm9uUmVmdW5kYWJsZVxuICAgIGhnZkNvbmZpcm1hdGlvblxuICAgIGhndk1heFRlcm1zQW5kQ29uZGl0aW9uc1xuICAgIGhob25vcnNDYW5jZWxsYXRpb25DaGFyZ2VzXG4gICAgaGhvbm9yc1BvaW50c0RlZHVjdGlvblxuICAgIGhob25vcnNQcmludGVkQ29uZmlybWF0aW9uXG4gICAgbGVuZ3RoT2ZTdGF5XG4gICAgcmlnaHRUb0NhbmNlbFxuICAgIHRvdGFsUmF0ZVxuICAgIHRlYW1NZW1iZXJFbGlnaWJpbGl0eVxuICAgIHZhdENoYXJnZVxuICB9XG4gIGNlcnRpZmljYXRlcyB7XG4gICAgdG90YWxQb2ludHNcbiAgICB0b3RhbFBvaW50c0ZtdFxuICB9XG4gIGNvc3Qge1xuICAgIGN1cnJlbmN5IHtcbiAgICAgIGN1cnJlbmN5Q29kZVxuICAgICAgY3VycmVuY3lTeW1ib2xcbiAgICAgIGRlc2NyaXB0aW9uXG4gICAgfVxuICAgIHJvb21SZXZVU0Q6IHRvdGFsQW1vdW50QmVmb3JlVGF4KGN1cnJlbmN5Q29kZTogXCJVU0RcIilcbiAgICB0b3RhbEFkZE9uc0Ftb3VudFxuICAgIHRvdGFsQWRkT25zQW1vdW50Rm10XG4gICAgdG90YWxBbW91bnRCZWZvcmVUYXhcbiAgICB0b3RhbEFtb3VudEFmdGVyVGF4Rm10OiBndWVzdFRvdGFsQ29zdEFmdGVyVGF4Rm10XG4gICAgdG90YWxBbW91bnRBZnRlclRheDogZ3Vlc3RUb3RhbENvc3RBZnRlclRheFxuICAgIHRvdGFsQW1vdW50QmVmb3JlVGF4Rm10XG4gICAgdG90YWxTZXJ2aWNlQ2hhcmdlc1xuICAgIHRvdGFsU2VydmljZUNoYXJnZXNGbXRcbiAgICB0b3RhbFRheGVzXG4gICAgdG90YWxUYXhlc0ZtdFxuICB9XG4gIGZvb2RBbmRCZXZlcmFnZUNyZWRpdEJlbmVmaXQge1xuICAgIGRlc2NyaXB0aW9uXG4gICAgaGVhZGluZ1xuICAgIGxpbmtMYWJlbFxuICAgIGxpbmtVcmxcbiAgfVxuICBndWFyYW50ZWUge1xuICAgIGN4bFBvbGljeUNvZGVcbiAgICBjeGxQb2xpY3lEZXNjXG4gICAgZ3VhclBvbGljeUNvZGVcbiAgICBndWFyUG9saWN5RGVzY1xuICAgIGd1YXJNZXRob2RDb2RlXG4gICAgdGF4RGlzY2xhaW1lcnMge1xuICAgICAgdGV4dFxuICAgICAgdGl0bGVcbiAgICB9XG4gICAgZGlzY2xhaW1lciB7XG4gICAgICBsZWdhbFxuICAgIH1cbiAgICBwYXltZW50Q2FyZCB7XG4gICAgICBjYXJkQ29kZVxuICAgICAgY2FyZE5hbWVcbiAgICAgIGNhcmROdW1iZXJcbiAgICAgIGNhcmRFeHBpcmVEYXRlXG4gICAgICBleHBpcmVEYXRlOiBjYXJkRXhwaXJlRGF0ZUZtdChmb3JtYXQ6IFwiTU1NIHl5eXlcIilcbiAgICAgIGV4cGlyZURhdGVGdWxsOiBjYXJkRXhwaXJlRGF0ZUZtdChmb3JtYXQ6IFwiTU1NTSB5eXl5XCIpXG4gICAgICBleHBpcmVkXG4gICAgICBwb2xpY3kge1xuICAgICAgICBiYW5rVmFsaWRhdGlvbk1zZ1xuICAgICAgfVxuICAgIH1cbiAgICBkZXBvc2l0IHtcbiAgICAgIGFtb3VudFxuICAgIH1cbiAgICB0YXhEaXNjbGFpbWVycyB7XG4gICAgICB0ZXh0XG4gICAgICB0aXRsZVxuICAgIH1cbiAgfVxuICBndWVzdCB7XG4gICAgZ3Vlc3RJZFxuICAgIHRpZXJcbiAgICBuYW1lIHtcbiAgICAgIGZpcnN0TmFtZVxuICAgICAgbGFzdE5hbWVcbiAgICAgIG5hbWVGbXRcbiAgICB9XG4gICAgZW1haWxzIHtcbiAgICAgIGVtYWlsQWRkcmVzc1xuICAgICAgZW1haWxUeXBlXG4gICAgfVxuICAgIGFkZHJlc3NlcyB7XG4gICAgICBhZGRyZXNzTGluZTFcbiAgICAgIGFkZHJlc3NMaW5lMlxuICAgICAgY2l0eVxuICAgICAgY291bnRyeVxuICAgICAgc3RhdGVcbiAgICAgIHBvc3RhbENvZGVcbiAgICAgIGFkZHJlc3NGbXRcbiAgICAgIGFkZHJlc3NUeXBlXG4gICAgfVxuICAgIGhob25vcnNOdW1iZXJcbiAgICBwaG9uZXMge1xuICAgICAgcGhvbmVOdW1iZXJcbiAgICAgIHBob25lVHlwZVxuICAgIH1cbiAgfVxuICBwcm9wQ29kZVxuICBub3IxVXBncmFkZShwcm92aWRlcjogXCJET0hXUlwiKSB7XG4gICAgY29udGVudCB7XG4gICAgICBidXR0b25cbiAgICAgIGRlc2NyaXB0aW9uXG4gICAgICBmaXJzdE5hbWVcbiAgICAgIHRpdGxlXG4gICAgfVxuICAgIG9mZmVyTGlua1xuICAgIHJlcXVlc3RlZFxuICAgIHN1Y2Nlc3NcbiAgfVxuICBub3RpZmljYXRpb25zIHtcbiAgICBzdWJUeXBlXG4gICAgdGV4dFxuICAgIHR5cGVcbiAgfVxuICByZXF1ZXN0cyB7XG4gICAgc3BlY2lhbFJlcXVlc3RzIHtcbiAgICAgIHBldHNcbiAgICAgIHNlcnZpY2VQZXRzXG4gICAgfVxuICB9XG4gIHJvb21zIHtcbiAgICBnbnJOdW1iZXJcbiAgICByZXNDcmVhdGVEYXRlRm10KGZvcm1hdDogXCJ5eXl5LU1NLWRkXCIpXG4gICAgYWRkT25zIHtcbiAgICAgIGFkZE9uQ29zdCB7XG4gICAgICAgIGFtb3VudEFmdGVyVGF4XG4gICAgICAgIGFtb3VudEFmdGVyVGF4Rm10XG4gICAgICB9XG4gICAgICBhZGRPbkRldGFpbHMge1xuICAgICAgICBhZGRPbkF2YWlsVHlwZVxuICAgICAgICBhZGRPbkRlc2NyaXB0aW9uXG4gICAgICAgIGFkZE9uQ29kZVxuICAgICAgICBhZGRPbk5hbWVcbiAgICAgICAgYW1vdW50QWZ0ZXJUYXhcbiAgICAgICAgYW1vdW50QWZ0ZXJUYXhGbXRcbiAgICAgICAgYXZlcmFnZURhaWx5UmF0ZVxuICAgICAgICBhdmVyYWdlRGFpbHlSYXRlRm10XG4gICAgICAgIGNhdGVnb3J5Q29kZVxuICAgICAgICBjb3VudHMge1xuICAgICAgICAgIG51bUFkZE9uc1xuICAgICAgICAgIGZ1bGZpbGxtZW50RGF0ZVxuICAgICAgICAgIHJhdGVcbiAgICAgICAgICByYXRlRm10XG4gICAgICAgIH1cbiAgICAgICAgbnVtQWRkT25EYXlzXG4gICAgICB9XG4gICAgfVxuICAgIGFkZGl0aW9uYWxOYW1lcyB7XG4gICAgICBmaXJzdE5hbWVcbiAgICAgIGxhc3ROYW1lXG4gICAgfVxuICAgIGNlcnRpZmljYXRlcyB7XG4gICAgICBjZXJ0TnVtYmVyXG4gICAgICB0b3RhbFBvaW50c1xuICAgICAgdG90YWxQb2ludHNGbXRcbiAgICB9XG4gICAgbnVtQWR1bHRzXG4gICAgbnVtQ2hpbGRyZW5cbiAgICBjaGlsZEFnZXNcbiAgICBhdXRvVXBncmFkZWRTdGF5XG4gICAgaXNTdGF5VXBzZWxsXG4gICAgaXNTdGF5VXBzZWxsT3ZlckF1dG9VcGdyYWRlXG4gICAgcHJpb3JSb29tVHlwZSB7XG4gICAgICByb29tVHlwZU5hbWVcbiAgICB9XG4gICAgY29zdCB7XG4gICAgICBjdXJyZW5jeSB7XG4gICAgICAgIGN1cnJlbmN5Q29kZVxuICAgICAgICBjdXJyZW5jeVN5bWJvbFxuICAgICAgICBkZXNjcmlwdGlvblxuICAgICAgfVxuICAgICAgYW1vdW50QWZ0ZXJUYXg6IGd1ZXN0VG90YWxDb3N0QWZ0ZXJUYXhcbiAgICAgIGFtb3VudEFmdGVyVGF4Rm10OiBndWVzdFRvdGFsQ29zdEFmdGVyVGF4Rm10XG4gICAgICBhbW91bnRCZWZvcmVUYXhcbiAgICAgIGFtb3VudEJlZm9yZVRheEZtdFxuICAgICAgYW1vdW50QmVmb3JlVGF4Rm10VHJ1bmM6IGFtb3VudEFmdGVyVGF4Rm10KGRlY2ltYWw6IDAsIHN0cmF0ZWd5OiB0cnVuYylcbiAgICAgIHNlcnZpY2VDaGFyZ2VGZWVUeXBlXG4gICAgICBzZXJ2aWNlQ2hhcmdlUGVyaW9kcyB7XG4gICAgICAgIHNlcnZpY2VDaGFyZ2VzIHtcbiAgICAgICAgICBhbW91bnRcbiAgICAgICAgICBhbW91bnRGbXRcbiAgICAgICAgICBkZXNjcmlwdGlvblxuICAgICAgICB9XG4gICAgICB9XG4gICAgICB0b3RhbFNlcnZpY2VDaGFyZ2VzXG4gICAgICB0b3RhbFNlcnZpY2VDaGFyZ2VzRm10XG4gICAgICB0b3RhbFRheGVzXG4gICAgICB0b3RhbFRheGVzRm10XG4gICAgICByYXRlRGV0YWlscyhwZXJOaWdodDogdHJ1ZSkge1xuICAgICAgICBlZmZlY3RpdmVEYXRlRm10KGZvcm1hdDogXCJtZWRpdW1cIilcbiAgICAgICAgZWZmZWN0aXZlRGF0ZUZtdEFkYTogZWZmZWN0aXZlRGF0ZUZtdChmb3JtYXQ6IFwibG9uZ1wiKVxuICAgICAgICByYXRlQW1vdW50XG4gICAgICAgIHJhdGVBbW91bnRGbXRcbiAgICAgICAgcmF0ZUFtb3VudEZtdFRydW5jOiByYXRlQW1vdW50Rm10KGRlY2ltYWw6IDAsIHN0cmF0ZWd5OiB0cnVuYylcbiAgICAgIH1cbiAgICAgIHVwZ3JhZGVkQW1vdW50XG4gICAgICB1cGdyYWRlZEFtb3VudEZtdFxuICAgIH1cbiAgICBndWFyYW50ZWUge1xuICAgICAgY3hsUG9saWN5Q29kZVxuICAgICAgY3hsUG9saWN5RGVzY1xuICAgICAgZ3VhclBvbGljeUNvZGVcbiAgICAgIGd1YXJQb2xpY3lEZXNjXG4gICAgfVxuICAgIG51bUFkdWx0c1xuICAgIG51bUNoaWxkcmVuXG4gICAgcmF0ZVBsYW4ge1xuICAgICAgY29uZmlkZW50aWFsUmF0ZXNcbiAgICAgIGhob25vcnNNZW1iZXJzaGlwUmVxdWlyZWRcbiAgICAgIGFkdmFuY2VQdXJjaGFzZVxuICAgICAgcHJvbW9Db2RlXG4gICAgICBkaXNjbGFpbWVyIHtcbiAgICAgICAgZGlhbW9uZDQ4XG4gICAgICAgIGZ1bGxQcmVQYXlOb25SZWZ1bmRhYmxlXG4gICAgICAgIGhob25vcnNDYW5jZWxsYXRpb25DaGFyZ2VzXG4gICAgICAgIGhob25vcnNQb2ludHNEZWR1Y3Rpb25cbiAgICAgICAgaGhvbm9yc1ByaW50ZWRDb25maXJtYXRpb25cbiAgICAgICAgbGVuZ3RoT2ZTdGF5XG4gICAgICAgIHJpZ2h0VG9DYW5jZWxcbiAgICAgICAgdG90YWxSYXRlXG4gICAgICB9XG4gICAgICByYXRlUGxhbkNvZGVcbiAgICAgIHJhdGVQbGFuTmFtZVxuICAgICAgcmF0ZVBsYW5EZXNjXG4gICAgICBzcGVjaWFsUmF0ZVR5cGVcbiAgICAgIHNlcnZpY2VDaGFyZ2VzQW5kVGF4ZXNJbmNsdWRlZFxuICAgIH1cbiAgICByb29tVHlwZSB7XG4gICAgICBhZGFBY2Nlc3NpYmxlUm9vbVxuICAgICAgcm9vbVR5cGVDb2RlXG4gICAgICByb29tVHlwZU5hbWVcbiAgICAgIHJvb21UeXBlRGVzY1xuICAgICAgcm9vbU9jY3VwYW5jeVxuICAgIH1cbiAgfVxuICB0YXhQZXJpb2RzIHtcbiAgICB0YXhlcyB7XG4gICAgICBkZXNjcmlwdGlvblxuICAgIH1cbiAgfVxuICBwYXltZW50T3B0aW9ucyB7XG4gICAgY2FyZE9wdGlvbnMge1xuICAgICAgcG9saWN5IHtcbiAgICAgICAgYmFua1ZhbGlkYXRpb25Nc2dcbiAgICAgIH1cbiAgICB9XG4gIH1cbiAgdG90YWxOdW1BZHVsdHNcbiAgdG90YWxOdW1DaGlsZHJlblxuICB0b3RhbE51bVJvb21zXG4gIHVubGltaXRlZFJld2FyZHNOdW1iZXJcbn1cbiAgICAiLCJvcGVyYXRpb25OYW1lIjoicmVzZXJ2YXRpb24iLCJ2YXJpYWJsZXMiOnsiY29uZk51bWJlciI6IkNPTkZfTlVNQkVSIiwibGFuZ3VhZ2UiOiJlbiIsImd1ZXN0SWQiOkdVRVNUX0lELCJsYXN0TmFtZSI6IkxBU1RfTkFNRSIsImFycml2YWxEYXRlIjoiQVJSSVZBTF9EQVRFIn19").replace("GUEST_ID", ' . $this->wso2AuthToken->guestId . ').replace("CONF_NUMBER", ' . $confNumber . ').replace("LAST_NAME", "' . $lastName . '").replace("ARRIVAL_DATE", "' . $arrivalDate . '"),
                "method": "POST",
                "mode": "cors"
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("reservationData", JSON.stringify(body)));
            })
        ');
        $this->logger->debug("request sent");
        $this->logger->debug(var_export($script, true));

        sleep(2);
        $this->logger->debug("get data");
        $reservationData = $this->driver->executeScript("return localStorage.getItem('reservationData');");
        $this->logger->info("[Form reservation]: " . $reservationData);

        if (!empty($reservationData)) {
            $this->http->SetBody($reservationData);
            $this->http->SaveResponse();
        }
    }

    private function parseItinerary($data): void
    {
        $this->logger->notice(__METHOD__);
        $hhonors = $this->getHhonors();
        $reservation = $hhonors->arrayVal($data, ['data', 'reservation']);

        if (!$reservation) {
            $this->sendNotification('check parse itinerary');

            return;
        }
        $departureDate = $reservation['departureDate'] ?? '';
        $isPast = strtotime($departureDate) < strtotime('-1 day', time());

        if ($isPast && !$this->ParsePastIts) {
            $this->logger->info('Skipping hotel: in the past');
            $this->cntSkippedPast++;

            return;
        }
        $arrivalDate = $reservation['arrivalDate'] ?? '';
        /*
        if ($arrivalDate && $arrivalDate === $departureDate) {
            $this->logger->error('Skipping hotel: the same arrival / departure dates');
            $this->cntSkippedPast++;
            return;
        }
        */

        $hotel = $this->itinerariesMaster->createHotel();
        // confirmation number
        $conf = $reservation['confNumber'] ?? null;
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        $hotel->addConfirmationNumber($conf, 'Confirmation number', true);
        // check in date
        $hotel->setCheckInDate(strtotime($arrivalDate));
        // check out date
        $hotel->setCheckOutDate(strtotime($departureDate));
        // cancellation policy
        $hotel->setCancellation($hhonors->arrayVal($reservation, ['disclaimer', 'hhonorsCancellationCharges']), false, true);

        if ($hhonors->arrayVal($reservation, ['cost', 'totalTaxes']) && strpos($hhonors->arrayVal($reservation, ['cost', 'totalTaxes']), '-') === false) {
            // total
            $hotel->obtainPrice()->setTotal($hhonors->arrayVal($reservation, ['cost', 'totalAmountBeforeTax']));
            // tax
            $hotel->obtainPrice()->setTax($hhonors->arrayVal($reservation, ['cost', 'totalTaxes']));
            // currency
            $hotel->obtainPrice()->setCurrencyCode($hhonors->arrayVal($reservation, ['cost', 'currency', 'currencyCode']));
            // spent awards
            $hotel->obtainPrice()->setSpentAwards($hhonors->arrayVal($reservation, ['certificates', 'totalPointsFmt']), false, true);
        }
        // rooms
        foreach (ArrayVal($reservation, 'rooms', []) as $key => $roomData) {
            $room = $hotel->addRoom();
            $rateDetails = $hhonors->arrayVal($roomData, ['cost', 'rateDetails']);

            // the number of entries in the rates (1) does not match the number of nights (0)
            if ($hotel->getCheckInDate() != $hotel->getCheckOutDate()) {
                foreach ($rateDetails as $rateDetail) {
                    // TODO: Different number of rates for each room, because of this an error occurs
                    if ($key > 0 && count($hotel->getRooms()[0]->getRates()) != count($rateDetails)) {
                        continue;
                    }
                    $room->addRate($hhonors->arrayVal($rateDetail, ['rateAmountFmt']));
                }
            }

            // type
            $room->setType($hhonors->arrayVal($roomData, ['roomType', 'roomTypeName']));
            // description
            $desc = $hhonors->arrayVal($roomData, ['roomType', 'roomTypeDesc']);

            if ($desc) {
                $desc = preg_replace('/\s+/', ' ', strip_tags($desc));
                $room->setDescription($desc ? trim($desc) : null, false, true);
            }
            // cancellation policy
            $cancelation = $hhonors->arrayVal($roomData, ['guarantee', 'cxlPolicyDesc']);

            if (empty($hotel->getCancellation()) && !empty($cancelation)) {
                $hotel->setCancellation($cancelation);
            }
        }

        $hotel->parseNonRefundable('/If you cancel for any reason, attempt to modify this reservation, or do not arrive on your specified check-in date, your payment is non-refundable/');
        // Deadline
        $hhonors->detectDeadLine($hotel);
        // guest count
        $hotel->setGuestCount($reservation['totalNumAdults'] ?? null);
        // kids count
        $hotel->setKidsCount($reservation['totalNumChildren'] ?? null, false, true);
        // hotel name
        $propCode = $reservation['propCode'] ?? null;
        $hotelData = $this->getHotelData($propCode);

        if ($hotelData) {
            $skip = $hhonors->addHotelData($hotel, $hotelData, $arrivalDate, $departureDate);

            if ($skip) {
                $this->logger->error('Skipping hotel: the same arrival / departure dates');
                $this->itinerariesMaster->removeItinerary($hotel);
                $this->cntSkippedPast++;

                return;
            }
        }

        $this->logger->info('Parsed Hotel:');
        $this->logger->info(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function parseMinimalItinerary($activity, ?bool $withDetails = true)
    {
        $this->logger->notice(__METHOD__);
        $hhonors = $this->getHhonors();

        if (!$activity) {
            $this->sendNotification('check parse minimal itinerary');

            return;
        }
        $departureDate = ArrayVal($activity, 'departureDate');
        $isPast = strtotime($departureDate) < strtotime('-1 day');

        if ($isPast && !$this->ParsePastIts) {
            $this->logger->info('Skipping hotel: in the past');
            $this->cntSkippedPast++;

            return;
        }
        $cancelled = null;
        // cancelled
        if (ArrayVal($activity, 'guestActivityType') === 'cancelled') {
            $cancelled = true;
        }
        $arrivalDate = ArrayVal($activity, 'arrivalDate');

        if ($arrivalDate && $arrivalDate === $departureDate) {
            $this->logger->error('Skipping hotel: the same arrival / departure dates');

            if ($cancelled) {
                $this->cntSkippedPast++;
            }

            return;
        }
        $propCode = $this->http->FindPreg('/ctyhocn=(\w+)/', false, ArrayVal($activity, 'bookAgainUrl'));
        $rooms = ArrayVal($activity, 'roomDetails', []);

        if (!$propCode) {
            $propCodes = [];

            foreach ($rooms as $room) {
                $propCodes[] = $this->http->FindPreg('/ctyhocn=(\w+)/', false, ArrayVal($room, 'bookAgainUrl'));
            }
            $propCodes = array_unique($propCodes);

            if (count($propCodes) === 1) {
                $propCode = array_shift($propCodes);
            }
        }

        if (!$propCode && !$cancelled) {
            $this->logger->error('Skipping hotel: property code is missing');

            return;
        }
        $hotel = $this->itinerariesMaster->createHotel();

        if (isset($cancelled)) {
            $hotel->setCancelled(true);
        }
        // check in date
        $hotel->setCheckInDate(strtotime($arrivalDate));
        // check out date
        $hotel->setCheckOutDate(strtotime($departureDate));

        if ($propCode && $withDetails) {
            // hotel name, address, check in / check out times
            $hotelData = $this->getHotelData($propCode);

            if ($hotelData) {
                $skip = $hhonors->addHotelData($hotel, $hotelData, $arrivalDate, $departureDate);

                if ($skip) {
                    $this->logger->error('Skipping hotel: the same arrival / departure dates');
                    $this->cntSkippedPast++;

                    return;
                }
            }
        } else {
            if (!empty(ArrayVal($activity, 'hotelName'))) {
                $hotel->hotel()
                    ->name(ArrayVal($activity, 'hotelName'));
            }

            $hotel->hotel()->noAddress();
            $cxlNumber = [];

            foreach ($rooms as $room) {
                $r = $hotel->addRoom();
                $r->setType(ArrayVal($room, 'roomTypeName'));
                $cxlNumber[] = ArrayVal($room, 'cxlNumber');
            }
            $cxlNumber = array_values(array_unique($cxlNumber));

            if (!empty($cxlNumber) && !empty($cxlNumber[0])) {
                $hotel->general()->cancellationNumber($cxlNumber[0]);
            }
        }

        // confirmation number
        $conf = ArrayVal($activity, 'confNumber');
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        $hotel->addConfirmationNumber($conf, 'Confirmation number', true);
        // spent awards
        $usedPoints = (int) (ArrayVal($activity, 'usedPoints', 0));

        if ($usedPoints) {
            $hotel->obtainPrice()->setSpentAwards(ArrayVal($activity, 'usedPointsFmt'));
        }

        $this->logger->info('Parsed Hotel:');
        $this->logger->info(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function getHotelData($propCode): ?array
    {
        $this->logger->notice(__METHOD__);

        if (!$propCode) {
            $this->logger->error('hotel property code is missing');

            return null;
        }

        $script = '
            fetch("https://www.hilton.com/graphql/customer?appName=dx-res-ui&operationName=brand_hotel_shopAvailOptions&originalOpName=getHotel&bl=en&ctyhocn=$propCode", {
                "headers": {
                    "Accept": "*/*",
                    "Accept-Language": "en-US,en;q=0.5",
                    "Content-Type": "application/json",
                    "Authorization": "Bearer ' . $this->wso2AuthToken->accessToken . '"
                },
                "body": atob("eyJxdWVyeSI6InF1ZXJ5IGJyYW5kX2hvdGVsX3Nob3BBdmFpbE9wdGlvbnMoJGxhbmd1YWdlOiBTdHJpbmchLCAkY3R5aG9jbjogU3RyaW5nISkge1xuICBob3RlbChjdHlob2NuOiAkY3R5aG9jbiwgbGFuZ3VhZ2U6ICRsYW5ndWFnZSkge1xuICAgIGN0eWhvY25cbiAgICBleHRlcm5hbFJlc1N5c3RlbVxuICAgIGJyYW5kQ29kZVxuICAgIGNvbnRhY3RJbmZvIHtcbiAgICAgIHBob25lTnVtYmVyXG4gICAgICBuZXR3b3JrRGlzY2xhaW1lclxuICAgIH1cbiAgICBkaXNwbGF5IHtcbiAgICAgIHByZU9wZW5Nc2dcbiAgICAgIG9wZW5cbiAgICAgIHJlc0VuYWJsZWRcbiAgICAgIHRyZWF0bWVudHNcbiAgICB9XG4gICAgY3JlZGl0Q2FyZFR5cGVzIHtcbiAgICAgIGd1YXJhbnRlZVR5cGVcbiAgICAgIGNvZGVcbiAgICAgIG5hbWVcbiAgICB9XG4gICAgYWRkcmVzcyB7XG4gICAgICBhZGRyZXNzU3RhY2tlZDogYWRkcmVzc0ZtdChmb3JtYXQ6IFwic3RhY2tlZFwiKVxuICAgICAgYWRkcmVzc0xpbmUxXG4gICAgICBjb3VudHJ5TmFtZV9ub1R4OiBjb3VudHJ5TmFtZVxuICAgICAgY291bnRyeVxuICAgICAgc3RhdGVcbiAgICAgIG1hcENpdHlcbiAgICB9XG4gICAgYnJhbmQge1xuICAgICAgZm9ybWFsTmFtZVxuICAgICAgZm9ybWFsTmFtZV9ub1R4OiBmb3JtYWxOYW1lXG4gICAgICBpc1BhcnRuZXJCcmFuZFxuICAgICAgbmFtZVxuICAgICAgcGhvbmUge1xuICAgICAgICBzdXBwb3J0TnVtYmVyXG4gICAgICAgIHN1cHBvcnRJbnRsTnVtYmVyXG4gICAgICB9XG4gICAgICB1cmxcbiAgICAgIHNlYXJjaE9wdGlvbnMge1xuICAgICAgICB1cmxcbiAgICAgIH1cbiAgICB9XG4gICAgbG9jYWxpemF0aW9uIHtcbiAgICAgIGN1cnJlbmN5IHtcbiAgICAgICAgY3VycmVuY3lDb2RlXG4gICAgICAgIGN1cnJlbmN5U3ltYm9sXG4gICAgICAgIGRlc2NyaXB0aW9uXG4gICAgICB9XG4gICAgfVxuICAgIG92ZXJ2aWV3IHtcbiAgICAgIHJlc29ydEZlZURpc2Nsb3N1cmVEZXNjXG4gICAgfVxuICAgIG5hbWVcbiAgICBwcm9wQ29kZVxuICAgIHNob3BBdmFpbE9wdGlvbnMge1xuICAgICAgbWF4QXJyaXZhbERhdGVcbiAgICAgIG1heERlcGFydHVyZURhdGVcbiAgICAgIG1pbkFycml2YWxEYXRlXG4gICAgICBtaW5EZXBhcnR1cmVEYXRlXG4gICAgICBtYXhOdW1PY2N1cGFudHNcbiAgICAgIG1heE51bUNoaWxkcmVuXG4gICAgICBtYXhOdW1Sb29tc1xuICAgICAgYWdlQmFzZWRQcmljaW5nXG4gICAgICBhZHVsdEFnZVxuICAgICAgYWRqb2luaW5nUm9vbXNcbiAgICB9XG4gICAgaG90ZWxBbWVuaXRpZXM6IGFtZW5pdGllcyhmaWx0ZXI6IHtncm91cHNfaW5jbHVkZXM6IFtob3RlbF19KSB7XG4gICAgICBpZFxuICAgICAgbmFtZVxuICAgIH1cbiAgICBzdGF5SW5jbHVkZXNBbWVuaXRpZXM6IGFtZW5pdGllcyhcbiAgICAgIGZpbHRlcjoge2dyb3Vwc19pbmNsdWRlczogW3N0YXldfVxuICAgICAgdXNlQnJhbmROYW1lczogdHJ1ZVxuICAgICkge1xuICAgICAgaWRcbiAgICAgIG5hbWVcbiAgICB9XG4gICAgaW1hZ2VzIHtcbiAgICAgIG1hc3RlcihpbWFnZVZhcmlhbnQ6IGJvb2tQcm9wZXJ0eUltYWdlVGh1bWJuYWlsKSB7XG4gICAgICAgIF9pZFxuICAgICAgICBhbHRUZXh0XG4gICAgICAgIHZhcmlhbnRzIHtcbiAgICAgICAgICBzaXplXG4gICAgICAgICAgdXJsXG4gICAgICAgIH1cbiAgICAgIH1cbiAgICB9XG4gICAgZmFtaWx5UG9saWN5XG4gICAgcmVnaXN0cmF0aW9uIHtcbiAgICAgIGNoZWNraW5UaW1lRm10KGxhbmd1YWdlOiAkbGFuZ3VhZ2UpXG4gICAgICBjaGVja291dFRpbWVGbXQobGFuZ3VhZ2U6ICRsYW5ndWFnZSlcbiAgICAgIGVhcmx5Q2hlY2tpblRleHRcbiAgICB9XG4gICAgcGV0cyB7XG4gICAgICBkZXNjcmlwdGlvblxuICAgIH1cbiAgICB0cmlwQWR2aXNvckxvY2F0aW9uU3VtbWFyeSB7XG4gICAgICByYXRpbmdGbXQoZGVjaW1hbDogMSlcbiAgICB9XG4gIH1cbn0iLCJvcGVyYXRpb25OYW1lIjoiYnJhbmRfaG90ZWxfc2hvcEF2YWlsT3B0aW9ucyIsInZhcmlhYmxlcyI6eyJsYW5ndWFnZSI6ImVuIiwiY3R5aG9jbiI6IkNJVFlfSE9DTiJ9fQ==").replace("CITY_HOCN", "' . $propCode . '"),
                "method": "POST",
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("brand_hotel_shopAvailOptionsData", JSON.stringify(body)));
            })
        ';
        $this->logger->debug("request sent");
        $this->logger->debug(var_export($script, true));
        try {
            $this->driver->executeScript($script);
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\UnexpectedJavascriptException $e) {
            $this->logger->error('JavascriptException: ' . $e->getMessage(), ['HtmlEncode' => true]);

            if (str_contains($e->getMessage(), 'Failed to fetch')) {
                sleep(5);
                $this->driver->executeScript($script);
            }
        }

        $this->logger->debug("request sent");
        sleep(2);
        $this->logger->debug("get data");
        $brand_hotel_shopAvailOptionsData = $this->driver->executeScript("return localStorage.getItem('brand_hotel_shopAvailOptionsData');");
        $this->logger->debug("[Form brand_hotel_shopAvailOptions]: " . $brand_hotel_shopAvailOptionsData);

        if (!empty($brand_hotel_shopAvailOptionsData)) {
            $this->http->SetBody($brand_hotel_shopAvailOptionsData);
            $this->http->SaveResponse();
        }

        $this->increaseTimeLimit();

        if ($this->http->FindPreg('/"extensions":\{"code":"GRAPHQL_VALIDATION_FAILED","exception":\{"code":500/')) {
            $this->driver->executeScript($script);
            $this->logger->debug("request sent 2");
            sleep(2);
            $this->logger->debug("get data");
            $brand_hotel_shopAvailOptionsData = $this->driver->executeScript("return localStorage.getItem('brand_hotel_shopAvailOptionsData');");
            $this->logger->info("[Form brand_hotel_shopAvailOptions]: " . $brand_hotel_shopAvailOptionsData);

            if (!empty($brand_hotel_shopAvailOptionsData)) {
                $this->http->SetBody($brand_hotel_shopAvailOptionsData);
                $this->http->SaveResponse();
            }
        }

        if (empty($brand_hotel_shopAvailOptionsData)) {
            $this->logger->error("failed brand_hotel_shopAvailOptionsData");

            return null;
        }

        return $this->http->JsonLog(null, 3, true);
    }

    /** @return TAccountCheckerHhonors */
    private function getHhonors()
    {
        if (!isset($this->hhonors)) {
            $this->hhonors = new TAccountCheckerHhonors();
            $this->hhonors->AccountFields = $this->AccountFields;
            $this->hhonors->http = $this->http;

            $this->hhonors->HistoryStartDate = $this->HistoryStartDate;
            $this->hhonors->historyStartDates = $this->historyStartDates;
            $this->hhonors->http->LogHeaders = $this->http->LogHeaders;
            $this->hhonors->ParseIts = $this->ParseIts;
            $this->hhonors->ParsePastIts = $this->ParsePastIts;
            $this->hhonors->WantHistory = $this->WantHistory;
            $this->hhonors->WantFiles = $this->WantFiles;
            $this->hhonors->strictHistoryStartDate = $this->strictHistoryStartDate;

            $this->hhonors->itinerariesMaster = $this->itinerariesMaster;
            $this->hhonors->logger = $this->logger;
            $this->hhonors->globalLogger = $this->globalLogger; // fixed notifications
            $this->hhonors->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        return $this->hhonors;
    }
}
