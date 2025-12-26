<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;
use AwardWallet\Engine\srilankan\QuestionAnalyzer;

class TAccountCheckerSrilankan extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.srilankan.com/flysmiles/my-account/account-summary';

    private $seleniumAuth = false;
    private $currentSeleniumURL = null;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = self::REWARDS_PAGE_URL;

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /**
         * prevent blocking by js script.
         *
         * (function(){
         * var securemsg;
         * var dosl7_common;
         */
        //$this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));

        if ($this->attempt === 0) {
            $this->setProxyBrightData();
        } else {
            $this->setProxyGoProxies();
        }

        //$this->http->setRandomUserAgent();
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
        $this->http->GetURL('https://www.srilankan.com/flysmiles');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $this->AccountFields['Login'] = preg_replace("/^UL/ims", '', $this->AccountFields['Login']);
        $headers = [
            'Content-Type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
//        $this->http->GetURL("https://www.srilankan.com/flysmiles/home/DoMemberLogin?un={$this->AccountFields['Login']}&pw={$this->AccountFields['Pass']}", $headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
//        if ($this->http->FindPreg("/src=\"(\/_Incapsula_Resource\?[^\"]+)/")) {
        $this->seleniumAuth = true;
        $this->selenium();
//            $this->incapsula();
//        }

        $this->incapsulaWorkaround(true);

        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        // Access is allowed
        if (!empty($response->SkyProfile) || $this->loginSuccessful()) {
            return true;
        }

        if ($question = $this->http->FindSingleNode("//p[contains(text(), 'A One-Time Password has been sent to')]")) {
            $this->AskQuestion($question, null, "Question");

            if (!QuestionAnalyzer::isOtcQuestion($question)) {
                $this->sendNotification("need to fix QuestionAnalyzer");
            }

            return false;
        }

        // catch errors
        if (!empty($response->errorlist)) {
            $error = json_encode($response->errorlist);
            $this->logger->error("[Error]: {$error}");
            // Current PASSWORD Specified is Invalid
            if ($this->http->FindPreg('/Current PASSWORD Specified is Invalid/', false, $error)) {
                throw new CheckException('Current PASSWORD Specified is Invalid', ACCOUNT_INVALID_PASSWORD);
            }
            // Invalid USERNAME Specified
            if ($this->http->FindPreg('/Invalid USERNAME Specified/', false, $error)) {
                throw new CheckException('Invalid USERNAME Specified', ACCOUNT_INVALID_PASSWORD);
            }
            // Your account is not active. Please contact FlySmiLes Service Centre
            if ($this->http->FindPreg('/Your account is not active.Please contact FlySmiLes Service Centre/', false, $error)) {
                throw new CheckException('Your account is not active. Please contact FlySmiLes Service Centre', ACCOUNT_INVALID_PASSWORD);
            }
            // Error occured. Please try again.
            if ($this->http->FindPreg('/\[\{"Id":0,"Exp":null,"des":null,"dte":".\/Date\(-\d+00000\).\/","IpAdd":null\}\]/', false, $error)) {
                throw new CheckException('Error occured. Please try again.', ACCOUNT_PROVIDER_ERROR);
            }
        }// if (!empty($response->errorlist))

        if ($message = $this->http->FindSingleNode("//div[@id = 'ffperrormsg' and normalize-space(.) != ''] | //div[@id = 'ffpmodalerrormsg' and normalize-space(.) != ''] | //div[contains(@class, 'error-message')]")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Please enter correct password')
                || strstr($message, 'Please enter correct username')
                || strstr($message, 'Invalid USERNAME Specified')
                || $message == 'Invalid Username or Password'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Maximum number of wrong login attempts reached.') {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function seleniumRetrieve($url, $arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL($url);
            $manage = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(),'Manage Booking')]"), 0);
            $manage->click();
            sleep(2);
            $lastName = $selenium->waitForElement(WebDriverBy::id("lastname2"), 0);
            $bookRef = $selenium->waitForElement(WebDriverBy::id("bookref2"), 0);
            $btnMybSearch = $selenium->waitForElement(WebDriverBy::id("btnMybSearch"), 0);
            $lastName->sendKeys($arFields['LastName']);
            $bookRef->sendKeys($arFields['ConfNo']);
            $btnMybSearch->click();

            sleep(7);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);

            $this->logger->notice(__METHOD__);
            $referer = $selenium->http->currentUrl();
            $this->logger->debug("parse captcha form");
            $dataUrl = $this->http->FindPreg('#"(/_Incapsula_Resource\?SWCNGEEC=.+?)"#');
            $action = $this->http->FindPreg("/xhr2.open\(\"POST\", \"([^\"]+)/");

            if (!$dataUrl || !$action) {
                return false;
            }
            $dataUrl = "https://book.srilankan.com$dataUrl";
            $this->http->GetURL($dataUrl);
            $data = $this->http->JsonLog();

            if (!isset($data->gt, $data->challenge)) {
                return false;
            }
            $request = $this->parseGeettestRuCaptcha($data->gt, $data->challenge, $referer);

            if ($request === false) {
                $this->logger->error("geetest failed = true");

                return false;
            }
            $this->http->FilterHTML = true;

            $selenium->driver->executeScript('var post_body = "geetest_challenge=' . $request->geetest_challenge . '&geetest_validate=' . $request->geetest_validate . '&geetest_seccode=' . $request->geetest_seccode . '";
            if (window.XMLHttpRequest) {
                xhr2 = new XMLHttpRequest;
            } else {
                xhr2 = new ActiveXObject("Microsoft.XMLHTTP");
            }
            xhr2.open("POST", "' . $action . '", true);
            xhr2.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr2.onreadystatechange = function(){
                if (xhr2.readyState == 4) {
                    if (xhr2.status == 200) {
                        document.getElementById("reese84-resubmit-form").submit();
                    } else {
                        window.location.reload(true);
                    }
                }
            }
            xhr2.send(post_body);');

            sleep(5);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        } // catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }
    }

    public function Parse()
    {
        if (
            $this->http->currentUrl() != self::REWARDS_PAGE_URL
            && (
                !isset($this->currentSeleniumURL)
                || $this->currentSeleniumURL != self::REWARDS_PAGE_URL
            )
        ) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if (
            $this->http->FindPreg("/window\[\"bobcmn\"\] = /")
            || $this->http->FindPreg("/_Incapsula_Resource/")
        ) {
            $this->selenium();
            // Please login to view your account summary details
            if (!$this->http->FindSingleNode("(//div[contains(text(), 'FlySmiLes No')])[2]")
                && $this->http->FindSingleNode("//h4[contains(text(), 'Please login to view your account summary details')]")) {
                throw new CheckRetryNeededException(2);
            }
        }

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(text(), 'Member Name')]/following-sibling::div[last()]")));
        //# FlySmiLes No
        $this->SetProperty("FlySmiLesNo", $this->http->FindSingleNode("(//div[contains(text(), 'FlySmiLes No')])[2]", null, true, "/FlySmiLes\s*No\s*:\s*([^<]+)/ims"));
        //# Tier Miles
        $this->SetProperty("TierMiles", $this->http->FindSingleNode("//div[contains(text(), 'Tier Miles')]/parent::div/following-sibling::div[1]/div[contains(text(), 'SriLankan Airlines')]/following-sibling::div[last()]"));
        //# Current Tier
        $this->SetProperty("CurrentTier", $this->http->FindSingleNode("//div[contains(text(), 'Current Tier')]/following-sibling::div[last()]"));
        //# Flight Sectors Flown
        $this->SetProperty("FlightSectorsFlown", $this->http->FindSingleNode("//div[contains(text(), 'Flight Sectors Flown')]/parent::div/following-sibling::div[1]/div[contains(text(), 'SriLankan Airlines')]/following-sibling::div[last()]"));
        //# Balance - FlySmiLes Miles
        $this->SetBalance($this->http->FindSingleNode("(//div[contains(text(), 'FlySmiLes Miles')])[2]", null, true, "/Miles\s*:\s*([^<]+)/ims"));
        // Next Miles Expiry Date
        $exp = $this->http->FindSingleNode("//div[contains(text(), 'Next Miles Expiry Date')]/following-sibling::div[last()]");

        if ($exp && strtotime($exp)) {
            // invalid expiration date: -62135596800
            if (trim($exp) == '1/1/0001') {
                $this->logger->notice("Skip wrong exp date {$exp}");
            } else {
                $this->SetExpirationDate(strtotime($exp));
            }
        }// if ($exp && strtotime($exp))
        // Next Miles to Expiry
        $this->SetProperty("MilesToExpiry", $this->http->FindSingleNode("//div[contains(text(), 'Next Miles to Expiry')]/following-sibling::div[last()]"));
        // You need ... tier miles to upgrade to a ...
        $this->SetProperty("NeedToNextLevel", $this->http->FindPreg("/You need (\d+)\s*tier miles to upgrade to/ims"));

        if ($this->http->FindPreg("/tier miles to upgrade to/is")) {
            $this->sendNotification('srilankan: NeedToNextLevel');
        }
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "PNR",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.srilankan.com/en_uk/us";
    }

    public function notifications($arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("srilankan - failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}");

        return null;
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->RetryCount = 0;

        //$this->http->GetURL($this->ConfirmationNumberURL($arFields));
        /*$this->http->GetURL('https://www.srilankan.com');
        $this->http->PostURL('https://www.srilankan.com/en_uk/us/home/SetVariable?key=US', []);
        $this->http->GetURL('https://www.srilankan.com/en_uk/us');*/

        //if ($this->http->FindPreg("/src=\"(\/_Incapsula_Resource\?[^\"]+)/")) {
        $this->seleniumRetrieve('https://www.srilankan.com/en_uk/us', $arFields);

        //}

        /*if (!$this->http->ParseForm("form_booking_manager")) {
            return $this->notifications($arFields);
        }

        $this->http->SetInputValue("bookref2", $arFields['ConfNo']);
        $this->http->SetInputValue("REC_LOC", $arFields['ConfNo']);
        $this->http->SetInputValue("DIRECT_RETRIEVE_LASTNAME", $arFields['LastName']);
        $this->http->SetInputValue("lastname2", $arFields['LastName']);
        $this->setHiddenFields($arFields);
        $this->http->FormURL = 'https://www.srilankan.com/en_uk/home/srilakan-override';

        if (!$this->http->PostForm()) {
            return $this->notifications($arFields);
        }

        if ($this->http->FindPreg('/The website book.srilankan.com requires that all visitors\s*be running JavaScript\./')) {
            $this->http->GetURL($this->http->currentUrl());
        }
        $this->http->setCookie('reese84', '3:wRFprqUw/7bp/16zwH8YxA==:xP+V8AlURYFBtpAta+NFw1M4JfOovPZH8+FyFfzuf10KK49fzE9px6NSFuWZSriCo/nx1EKzHlS+G6rR22aqmNkCK+forJUVeCSOdTx80qdvSNeCkfd1NTboettdxyK9l68WC/yiWdRSsIt8kfgiK4uwTPyinE/UuO4pSdbs/MZgYGtUwbsHWR4GOMWJnqKkp2TMhkLYVXSb7hdEdBfsnMRaWDAhn/SMEUoAd4LJwJ/s68LqopEt6Ee2oPpIHUw105IS3fKzGKeyLQm+3zHxkIlCu3ZoAxvZ5WU5BRKqQJ663uhiKqf7wtSq2urtJ6TcAY8ZOnT/kDwiruvBOjG+qHnvLDObvY08LwT6Ioypqr2a5FyMMMJrcASWh/BlIfaOoSMsBLdOQoQ0pnJG0lV6wBmrzze0PVg96+W3Ja4SV0EqGwX3kIloF0EP1mtEXlfCL1pLbBECCH9ute7MtZlj2xOgwHkFCJWr9LGUuvYPf6xl4+HWDR7OMa1IV/zcPzgLpCFRTMaTOLRrz9Ddraqc/Sv0RFbjCvaZfzFfm/9W+6hR7oVg350fPcgKgjLM2vwPfHzO94ZmlH4KOmzGEUTinbYWXXg/t2Ds51tzrXjfX2T8MMOldMphYfjEb3q8bG4tT9raOzQPHFgvrDKjjunWGzoWuXe5eM6lCaDLhDvhuG6VhW9Eymjon09QfqproFoNT/Zh17MxEeJVGwRKaajGB6Z98qjh84rpUR2c7sr+iGtFGLKsTH6xcUiI83CI1szv:rZsMQ9c99IX1Hv98cjp0SjqzTCkY9JEhtF+fkAsNols=', '.book.srilankan.com');

        $this->http->ParseForm('form_booking_engine');

        $this->http->FormURL = 'https://book.srilankan.com/plnext/srilankanairDX/Override.action?';

        $this->http->PostForm([
            'Referer' => 'https://www.srilankan.com/',
            'Origin'  => 'https://www.srilankan.com',
            'Accept'  => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,* / *;q=0.8',
        ]);

        if ($this->http->FindPreg('/"captcha\.title":"Pardon our interruption/')
            || $this->http->FindSingleNode("//h1[contains(.,'Pardon Our Interruption') or contains(., 'This site can’t be reached')]")) {
            $this->sendNotification('failed to retrieve itinerary by conf // MI');

            $this->http->ParseForm('reese84-resubmit-form');
            //$this->http->PostForm();
            $form = $this->http->Form;
            $formUrl = $this->http->FormURL;

            $this->incapsula();

            sleep(3);
            $this->http->Form = $form;
            $this->http->FormURL = $formUrl;
            $this->http->PostForm([
                'Accept'  => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,* / *;q=0.8',
                'Referer' => 'https://book.srilankan.com/plnext/srilankanairDX/Override.action?',
                'Origin'  => 'https://www.srilankan.com',
            ]);
        }*/

        $data = $this->http->FindPreg('/PlnextPageProvider.init\(\{\s*config\s*:\s*(\{.+?\})\s*,\s*pageEngine/');
        // Booking Not Found
        if ($error = $this->http->FindPreg("/We are unable to find this confirmation number\.\s*Please validate your entry and try again or contact us for further information\./ims")) {
            return $error;
        }

        if (!$data) {
            $script = $this->http->FindSingleNode('//script[@defer]/@src');
            $this->http->NormalizeURL($script);
            $this->http->GetURL($script);

            $pid = $this->http->FindPreg('/FingerprintWrapper\(\{path:"\/(.+?)"/');

            $this->http->NormalizeURL($pid);
            $headers = [
                'X-Distil-Ajax' => $this->http->FindPreg('/ajax_header:"(.+?)"/'),
            ];
            $this->http->PostURL($pid, [], $headers, 30);
            $data = $this->http->FindPreg('/PlnextPageProvider.init\(\{\s*config\s*:\s*(\{.+?\})\s*,\s*pageEngine/');
        }

        if (!$data) {
            return null;
        }
        $data = $this->http->JsonLog($data, 2, true);
        $it = $this->ParseConfirmationItineraryJson($data);

        return null;
    }

    public function ArrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'form_container']/div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->increaseTimeLimit(300);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, true, 3, 1);

        $this->increaseTimeLimit($recognizer->RecognizeTimeout);

        return $captcha;
    }

    protected function setHiddenFields($arFields)
    {
        $this->logger->notice(__METHOD__);
        $fields = [
            'ACTION'                       => 'MODIFY',
            'bookref2'                     => $arFields['ConfNo'],
            'DIRECT_RETRIEVE'              => 'TRUE',
            'DIRECT_RETRIEVE_LASTNAME'     => $arFields['LastName'],
            'EMBEDDED_TRANSACTION'         => 'RetrievePNR',
            // 'idxxui'=> '01djssqn3xdzadgnllwzvz2t',
            'ishsbc'                       => 'F',
            'isredemption'                 => 'F',
            'lastname2'                    => $arFields['LastName'],
            'REC_LOC'                      => $arFields['ConfNo'],
            'sitelangm'                    => 'GB',
            // 'SO_SITE_ALLOW_APC'            => 'TRUE',
            'SO_SITE_ALLOW_DIRECT_RT'      => 'TRUE',
            'SO_SITE_ALLOW_PNR_MODIF'      => 'Y',
            'SO_SITE_ALLOW_PNR_SERV'       => 'YES',
            'SO_SITE_ALLOW_PREF_CURRENCY2' => 'FALSE',
            'SO_SITE_ALLOW_PREF_CURRENCY'  => 'FALSE',
            'SO_SITE_ATC_ALLOW_LSA_INDIC'  => 'TRUE',
            'SO_SITE_ATC_ELG_CHECK_CAT31'  => 'TRUE',
            // 'SO_SITE_ATC_FARE_DRIVEN'      => 'TRUE',
            'SO_SITE_ATC_FP_PRICING_TYPE'  => 'O',
            'SO_SITE_ATC_FP_TAX_PER_TYPE'  => 'PAX',
            'SO_SITE_ATC_ISSUE_MCO_REFUND' => 'TRUE',
            'SO_SITE_ATC_ISSUE_MCO_W_ETKT' => 'TRUE',
            'SO_SITE_ATC_MISC_DOC_MODE'    => 'EMD',
            'SO_SITE_ATC_POS_POT_FLOWN'    => 'TRUE',
            'SO_SITE_ATC_SCHEDULE_DRIVEN'  => 'TRUE',
            'SO_SITE_BOOL_DISPLAY_ETKT'    => 'TRUE',
            'SO_SITE_BOOL_ETKT_RECEIPT'    => 'TRUE',
            'SO_SITE_BOOL_ISSUE_ETKT'      => 'TRUE',
            'SO_SITE_BOOL_RBK_ISSUE_ETKT'  => 'TRUE',
            'SO_SITE_BOOL_RK_ETKT_FAIL'    => 'TRUE',
            'SO_SITE_CURRENCY_FORMAT_JAVA' => '0.00',
            'SO_SITE_DEFAULT_CFF'          => 'ATC',
            'SO_SITE_DFLT_DATE_RANGE'      => '3',
            'SO_SITE_DISPL_SPECIAL_REQS'   => 'TRUE',
            'SO_SITE_DISPLAY_OPERATED_BY'  => 'FALSE',
            'SO_SITE_ETKT_VIEW_ENABLED'    => 'TRUE',
            'SO_SITE_FD_DISPLAY_MODE'      => '0',
            'SO_SITE_FP_DIRECT_NON_STOP'   => 'FALSE',
            'SO_SITE_FP_MAX_DATE_RANGE'    => '3',
            'SO_SITE_FP_TAX_PER_TYPE'      => 'PNR',
            'SO_SITE_ISSUE_TKT_PER_PAX'    => 'TRUE',
            'SO_SITE_MOP_CALL_ME'          => 'FALSE',
            'SO_SITE_MOP_EXT'              => 'FALSE',
            'SO_SITE_MOP_PAY_LATER'        => 'FALSE',
            // 'SO_SITE_OFFICE_ID'            => 'CMBUL08AI',
            'SO_SITE_OTHER_AIRLINES_REC'   => 'HIDE',
            'SO_SITE_OXML_PIL_PH1'         => 'TRUE',
            'SO_SITE_OXML_VERBS_PIL_PH1'   => 'TARIPQ,TARCPQ',
            'SO_SITE_PNR_SERV_REQ_LOGIN'   => 'NO',
            'SO_SITE_QUEUE_CATEGORY'       => '',
            'SO_SITE_QUEUE_OFFICE_ID'      => '',
            'SO_SITE_QUEUE_PNR_TICKETED'   => 'TRUE',
            'SO_SITE_QUEUE_SUCCESS_ETKT'   => 'FALSE',
            'SO_SITE_RT_PRICE_FROM_TST'    => 'Y',
            'SO_SITE_RT_SHOW_PRICES'       => 'TRUE',
            'SO_SITE_RUI_HIDE_PRICE_FARES' => 'IT',
            'SO_SITE_SEND_ITR_WITH_TTP'    => 'TRUE',
            'SO_SITE_SI_1AXML_USE_DCX'     => 'TRUE',
            'SO_SITE_SM_FEATURE'           => 'Y',
            'SO_SITE_USE_TP_SITEMOP'       => 'TRUE',
            'SO_SITE_USER_CURRENCY_CODE'   => '',
            'TRIP_FLOW'                    => 'YES',
        ];

        foreach ($fields as $k => $v) {
            $this->http->SetInputValue($k, $v);
        }
    }

    private function incapsulaWorkaround($retry = false)
    {
        $this->logger->notice(__METHOD__);
        // incapsula workaround
        $filter = $this->http->FilterHTML;
        $this->http->FilterHTML = false;

        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        if (
            $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")
            || $this->http->FindPreg("/<head>\s*<META NAME=\"robots\" CONTENT=\"noindex,nofollow\">\s*<script src=\"\/_Incapsula_Resource\?SWJIYLWA=[^\"]+\">\s*<\/script>\s*<body>/")
        ) {
            if ($retry) {
                throw new CheckRetryNeededException(3, 1, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return true;
        }

        $this->http->FilterHTML = $filter;

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'logoff')]/@href")) {
            return true;
        }

        return false;
    }

    private function incapsula($isRedirect = true)
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $this->logger->debug("parse captcha form");
        $dataUrl = $this->http->FindPreg('#"(/_Incapsula_Resource\?SWCNGEEC=.+?)"#');
        $action = $this->http->FindPreg("/xhr2.open\(\"POST\", \"([^\"]+)/");

        if (!$dataUrl || !$action) {
            return false;
        }
        $this->http->NormalizeURL($dataUrl);
        $this->http->GetURL($dataUrl);
        $data = $this->http->JsonLog();

        if (!isset($data->gt, $data->challenge)) {
            return false;
        }
        $request = $this->parseGeettestRuCaptcha($data->gt, $data->challenge, $referer);

        if ($request === false) {
            $this->logger->error("geetest failed = true");

            return false;
        }
        $this->http->RetryCount = 0;
        $this->http->NormalizeURL($action);
        $data = [
            'geetest_challenge' => $request->geetest_challenge,
            'geetest_validate'  => $request->geetest_validate,
            'geetest_seccode'   => $request->geetest_seccode,
        ];
        $headers = [
            "Accept"     => "*/*",
            "Referer"    => $referer,
        ];
        $this->http->PostURL($action, $data, $headers);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        return true;
    }

    private function parseGeettestRuCaptcha($gt, $challenge, $pageurl)
    {
        $this->logger->notice(__METHOD__);
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $pageurl,
            "proxy"      => $this->http->GetProxy(),
            'api_server' => 'api.geetest.com',
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
        $request = $this->http->JsonLog($captcha);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
            $request = $this->http->JsonLog($captcha);
        }

        if (empty($request)) {
            $this->logger->error("geetestFailed = true");

            return false;
        }

        return $request;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $result = false;
        $retry = false;
        // get cookies from curl
        $allCookies = array_merge($this->http->GetCookies(".www.srilankan.com"), $this->http->GetCookies(".www.srilankan.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies(".srilankan.com"), $this->http->GetCookies(".srilankan.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("www.srilankan.com"), $this->http->GetCookies("www.srilankan.com", "/", true));

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();

            $selenium->useFirefox();
//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->saveScreenshots = true;
            $selenium->disableImages();
//            $selenium->useCache();

            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
            $selenium->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();

            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.srilankan.com/flysmiles/my-account/sdfsdfsef");
            } catch (ScriptTimeoutException | TimeOutException $e) {
                $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            foreach ($allCookies as $key => $value) {
                try {
                    $this->logger->debug("name => $key, 'value' => $value, domain => .srilankan.com");
                    $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".srilankan.com"]);
                } catch (InvalidCookieDomainException $e) {
                    $this->logger->error("InvalidCookieDomainException: " . $e->getMessage());
                    $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
                }
            }

            if ($this->seleniumAuth === true) {
                try {
                    $selenium->http->GetURL("https://www.srilankan.com/flysmiles/my-account/account-summary");

                    if ($navLogin = $selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'Main-login'] | //a[@href=\"/flysmiles/user-login\"]"), 10)) {
                        $navLogin->click();
                    }

                    $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'txtusername' or @id = 'ffp_username' or @id = 'username']"), 10);
                    $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'txtpassword' or @id = 'ffp_password' or @id = 'password']"), 5);
                    sleep(5);
                    $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'btnffpmodal'] | //button[@id = 'btnffpmodal1'] | //div[contains(@class, 'active')]//button[@onclick=\"submitFfp1();\"] | //button[contains(@class, 'custom-login-button')]"), 5);
                    $this->savePageToLogs($selenium);

                    if (!$login || !$pass || !$btn) {
                        return false;
                    }

                    $this->logger->debug("set login");
                    $login->sendKeys($this->AccountFields['Login']);
                    $this->logger->debug("set pass");
                    $pass->sendKeys($this->AccountFields['Pass']);

                    $btn->click();

                    $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Logout')] | //div[@id = 'ffperrormsg'] | //input[@id = 'btnFSLogout'] | //div[contains(@class, 'error-message')]"), 20);
                    $this->savePageToLogs($selenium);

                    if ($selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'btnFSLogout']"), 0)) {
                        $selenium->http->GetURL("https://www.srilankan.com/flysmiles/my-account/account-summary");
                        $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Logout')]"), 20);
                        $this->savePageToLogs($selenium);
                    }
                } catch (ScriptTimeoutException | TimeOutException $e) {
                    $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
                    $selenium->driver->executeScript('window.stop();');
                }
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
                // save page to logs
                $this->savePageToLogs($selenium);

                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                    $retry = true;
                }

                $this->currentSeleniumURL = $selenium->http->currentUrl();
                $this->logger->debug("[lastSeleniumURL]: {$this->currentSeleniumURL}");

                return true;
            }

            $selenium->http->GetURL(self::REWARDS_PAGE_URL);

            if (!$selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Member Name')]/following-sibling::div[last()]"), 5)) {
                $this->logger->notice("selenium auth");
                $login = $selenium->waitForElement(WebDriverBy::id("txtusername"), 0);
                $pass = $selenium->waitForElement(WebDriverBy::id("txtpassword"), 0);
                $btn = $selenium->waitForElement(WebDriverBy::id("btnffpmodal1"), 0);
                // save page to logs
                $this->savePageToLogs($selenium);

                if (!$login || !$pass || !$btn) {
                    $this->logger->error("something went wrong");

                    return false;
                }
                $login->sendKeys($this->AccountFields['Login']);
                $pass->sendKeys($this->AccountFields['Pass']);
                $btn->click();

                if ($selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Member Name')]/following-sibling::div[last()] | //p[contains(text(), 'A One-Time Password has been sent to')]"), 5)) {
                    $cookies = $selenium->driver->manage()->getCookies();

                    foreach ($cookies as $cookie) {
                        $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                    }

                    $result = true;
                }
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            $this->currentSeleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[lastSeleniumURL]: {$this->currentSeleniumURL}");
        } finally {
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $result;
    }

    private function ParseConfirmationItineraryJson(?array $data): array
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'T'];
        $reservationInfo = $this->ArrayVal($data, ['pageDefinitionConfig', 'pageData', 'business', 'RESERVATION_INFO']);

        if (!$reservationInfo) {
            $this->logger->info('Json has changed, cannot find RESERVATION_INFO');

            return [];
        }
        $listItinerary = $this->ArrayVal($data, ['pageDefinitionConfig', 'pageData', 'business', 'ItineraryList', 'listItinerary']);

        if (!$listItinerary) {
            $this->logger->info('Json has changed, cannot find listItinerary');

            return [];
        }
        // RecordLocator
        $result['RecordLocator'] = ArrayVal($reservationInfo, 'locator');
        // Passengers
        $passengers = [];

        foreach (ArrayVal($reservationInfo, 'liTravellerInfo') as $trav) {
            $lastName = $this->ArrayVal($trav, ['identity', 'lastName'], '');
            $firstName = $this->ArrayVal($trav, ['identity', 'firstName'], '');
            $name = beautifulName("{$firstName} {$lastName}");
            $passengers[] = $name;
        }
        $result['Passengers'] = $passengers;
        // ReservationDate
        $result['ReservationDate'] = strtotime(ArrayVal($reservationInfo, 'creationDate'));
        // TripSegments
        $result['TripSegments'] = [];
        $segments = $this->ArrayVal($listItinerary, [0, 'listSegment'], []);

        foreach ($segments as $seg) {
            if (!$seg) {
                $this->logger->info('Json has changed, cannot find listSegment');

                continue;
            }
            $ts = [];
            // DepCode
            $ts['DepCode'] = $this->ArrayVal($seg, ['beginLocation', 'locationCode']);
            // ArrCode
            $ts['ArrCode'] = $this->ArrayVal($seg, ['endLocation', 'locationCode']);
            // DepartureTerminal
            $ts['DepartureTerminal'] = $this->ArrayVal($seg, ['beginTerminal']);
            // ArrivalTerminal
            $ts['ArrivalTerminal'] = $this->ArrayVal($seg, ['endTerminal']);
            // FlightNumber
            $ts['FlightNumber'] = $this->ArrayVal($seg, ['flightNumber']);
            // AirlineName
            $ts['AirlineName'] = $this->ArrayVal($seg, ['airline', 'code']);
            // Stops
            $ts['Stops'] = $this->ArrayVal($seg, ['nbrOfStops']);
            // Duration
            $dur = ArrayVal($seg, 'flightTime', 0);

            if ($dur) {
                $ts['Duration'] = date('G\h i\m', $dur / 1000);
            }
            // DepDate
            $ts['DepDate'] = strtotime($this->ArrayVal($seg, ['beginDate']));
            // ArrDate
            $ts['ArrDate'] = strtotime($this->ArrayVal($seg, ['endDate']));
            // Aircraft
            $ts['Aircraft'] = $this->ArrayVal($seg, ['equipment', 'name']);
            // Cabin
            $ts['Cabin'] = $this->ArrayVal($seg, ['listCabin', 0, 'name']);
            $result['TripSegments'][] = $ts;
        }

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }
}
