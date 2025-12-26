<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerEvaSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const WAIT_TIMEOUT = 10;

    private $eva;
    private $PostForm = [];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->FilterHTML = false;

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $resolutions = [
            [1366, 768],
            [1920, 1080],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);

        if ($this->attempt == 0) {
            $this->http->SetProxy($this->proxyDOP());
        } elseif ($this->attempt == 1) {
            $this->setProxyGoProxies();
        } else {
            $this->setProxyNetNut();
        }

        $this->useChromePuppeteer();
        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->usePacFile(false);
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();
            $this->http->GetURL("https://eservice.evaair.com/flyeva/eva/ffp/frequent-flyer.aspx");

            $loginInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'content_wuc_login_Account']"), 7);

            if ($accept = $this->waitForElement(WebDriverBy::xpath("//button[@data-all=\"Accept All Cookies\"]"), 3)) {
                $accept->click();
                $this->saveResponse();
            }

            $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'content_wuc_login_Password']"), 0);
            $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Login')]"), 0);
            $this->saveResponse();

            if (!$loginInput || !$passwordInput || !$button) {
                return false;
            }

            $this->clickCloudFlareCheckboxByMouse($this, '//input[@id = "content_wuc_login_Account"]', 5, 5);

            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->driver->executeScript('document.querySelector(\'input[id="content_wuc_login_Remember"]\').checked = true;');
            $this->saveResponse();

            $captchaCode = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'content_wuc_login_CaptchaCode']"), 0);
            $captcha = $this->waitForElement(WebDriverBy::xpath('//div[@id = "c_eva_ffp_login_logincaptcha_CaptchaDiv"]'), 0);

            if (!$captchaCode || !$captcha) {
                return false;
            }

            $x = $captcha->getLocation()->getX();
            $y = $captcha->getLocation()->getY() - 200;
            $this->driver->executeScript("window.scrollBy($x, $y)");
            $this->saveResponse();
            $imageData = $this->driver->executeScript("
                let canvas = document.createElement('CANVAS'),
                    ctx = canvas.getContext('2d'),
                    img = document.getElementById('c_eva_ffp_login_logincaptcha_CaptchaImage');
    
                canvas.height = img.height;
                canvas.width = img.width;
                ctx.drawImage(img, 0, 0);
                let imageData = canvas.toDataURL('image/png');
                
                return imageData;             
            ");

            if (!$imageData) {
                $this->logger->error('Failed to get screenshot of iFrame with captcha');

                return false;
            }

            $imageData = str_replace('data:image/png;base64,', '', $imageData);
            //            $this->logger->debug("png;base64: {$imageData}");

            if (!empty($imageData)) {
                $this->logger->debug("decode image data and save image in file");
                // decode image data and save image in file
                $imageData = base64_decode($imageData);
                $image = imagecreatefromstring($imageData);
                $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true) . ".png";
                imagejpeg($image, $file);
            }

            if (!isset($file)) {
                return false;
            }

            $this->logger->debug("file: " . $file);

            $this->recognizer = $this->getCaptchaRecognizer();
            $this->recognizer->RecognizeTimeout = 120;

            $captcha = $this->recognizeCaptcha($this->recognizer, $file);
            unlink($file);

            if ($accept = $this->waitForElement(WebDriverBy::xpath("//button[@data-all=\"Accept All Cookies\"]"), 3)) {
                $accept->click();
                $this->saveResponse();
            }

            $captchaCode->click();
            $captchaCode->sendKeys($captcha);
            $this->saveResponse();
            $this->logger->debug("click by btn");
            $button->click();

            if ($this->waitForElement(WebDriverBy::xpath('//div[h3[contains(text(), "Self Award Miles")]]/following-sibling::p/span[contains(@class, "color-green") and contains(@class, "vertical-baseline")] | //div[@id="wuc_Error"]/descendant::li'), 10)) {
                /*
                $this->sendNotification('refs #24888 eva - success config was found // IZ');
                $this->markConfigAsBadOrSuccess(true);
                */
            } else {
                /*
                $this->markConfigAsBadOrSuccess(false);
                $this->sendNotification('refs #24888 eva - network error detected // IZ');
                */
                // "I'm not a robot"
                if ($iframe = $this->waitForElement(WebDriverBy::xpath("//iframe[@id = 'sec-cpt-if']"), 0)) {
                    $this->driver->switchTo()->frame($iframe);
                    $robotCheckbox = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'robot-checkbox']"), 5);
                    if ($robotCheckbox) {
                        $this->logger->debug("click by checkbox");
//                $selenium->driver->executeScript('document.querySelector(\'#sec-cpt-if\').contentWindow.document.querySelector(\'#robot-checkbox\').click()');
                        $this->driver->executeScript('document.querySelector(\'#robot-checkbox\').click()');
                        sleep(2);
                        $this->savePageToLogs($this);
                        $this->logger->debug("click by 'Proceed' btn");
                        $btn = $this->waitForElement(WebDriverBy::xpath("//*[@id='progress-button']"), 2);
                        if ($btn) {
                            $btn->click();
                        }
//                $selenium->driver->executeScript('document.querySelector(\'#sec-cpt-if\').contentWindow.document.querySelector(\'#proceed-button\').click()');

                        $this->waitFor(function () {
                            return is_null($this->waitForElement(WebDriverBy::xpath("//*[@id='progress-button']"), 0));
                        }, 50);
                        $this->savePageToLogs($this);

                        $this->driver->switchTo()->defaultContent();
                    }
                }
            }

            $cookies = $this->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->saveResponse();
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

            return true;
        } catch (Facebook\WebDriver\Exception\UnknownErrorException | WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//div[h3[contains(text(), "Self Award Miles")]]/following-sibling::p/span[contains(@class, "color-green") and contains(@class, "vertical-baseline")] | //p[contains(text(), "We have sent a letter containing the verification code")] | //div[@id="wuc_Error"]/descendant::li | //span[contains(text(), "Join Infinity MileageLands")] | //span[contains(text(), "This site can’t be reached")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($this->processQuestion()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[@id="wuc_Error"]/descendant::li')) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Wrong CAPTCHA entry. Please try again.') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if (
                stripos($message, 'No data found. Please input correct Membership Number, E-Mail Address or Username!') !== false
                || (stripos($message, 'You have input wrong password') !== false && stripos($message, 'You have input wrong password five times') == false)
                || strstr($message, 'Currently the system is unable to log in by E-Mail Address.')
                || strstr($message, 'You have not applied for the password yet, please click here to acquire your password. Thank you!')
                || strstr($message, 'Incomplete Membership Number or E-Mail Address or Username.')
                || $message == 'Invalid Membership Number.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                stripos($message, 'You have input wrong password five times. For privacy security policy, the log-in function will be inaccesssible until you finish the procedure of “Forgot Password".') !== false
                || stripos($message, 'For privacy security policy, the log-in function will be inaccesssible until you finish the procedure') !== false
                || stripos($message, 'The functions of on-line services for this member had been terminated. If you want to use this system again please contact Infinity MileageLands Service Center.') !== false
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (stripos($message, 'The system is temporarily unavailable, so please try it later. We apologize for any inconvenience that might have caused you.') !== false) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (stripos($message, 'Your password strength is not strong enough. Please check your email for the password reset. It will take you to a page where you can reset your password.') !== false) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//span[contains(text(), "Join Infinity MileageLands")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//title[contains(text(), "Request Rejected")] | //span[contains(text(), "This site can’t be reached")]')) {
            throw new CheckRetryNeededException(3, 0);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance -  Self Award Miles
        // refs #5696
        $this->SetBalance($this->http->FindSingleNode('//div[h3[contains(text(), "Self Award Miles")]]/following-sibling::p/span[contains(@class, "color-green") and contains(@class, "vertical-baseline")]'));
        // Membership status
        $this->SetProperty('Status', Html::cleanXMLValue($this->http->FindSingleNode("//dt[contains(text(), 'Your current membership status is')]", null, true, "/([A-Za-z]+)\s*card/ims")));

        // Expiration Date  // refs #19216
        // Search all dates with miles > 0
        $nodes = $this->http->XPath->query("//div[h3[contains(text(), 'Own Earned miles which will expire within 6 months')]]/following-sibling::table//tr[td[3]]");
        $this->logger->debug("Total {$nodes->length} exp nodes were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $miles = $this->http->FindSingleNode("td[2]", $node);

            if ($miles > 0) {
                $expire[] = [
                    'date'  => "01 " . $this->http->FindSingleNode("td[1]", $node),
                    'miles' => $miles,
                ];
            }// if ($miles > 0)
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (isset($expire)) {
            // Find the nearest date with non-zero balance
            $N = count($expire);
            $this->logger->debug(">>> Total nodes with expiring miles " . $N);
            // Log
            $this->logger->debug(var_export($expire, true), ['pre' => true]);
            $i = 0;

            while (($N > 0) && ($i < $N)) {
                if ($date = strtotime($expire[$i]['date'])) {
                    $this->SetExpirationDate($date);
                    // Set Property 'Expiring Balance' (Mileage Balance)
                    $this->SetProperty('ExpiringBalance', $expire[$i]['miles']);

                    break;
                }// if ($date = strtotime($expire[$i]['date']))
                $i++;
            }// while (($N > 0 ) && ($i < $N))
        }// if (isset($expire))
        elseif ($this->http->FindSingleNode('//h3[contains(text(), "There is no mile which will be expired within 6 months.")]')) {
            $this->ClearExpirationDate();
        }

        // Needed Status Miles to Next Level
        $this->SetProperty('EarnedFlightMiles', $this->http->FindSingleNode('//p/node()[contains(normalize-space(.), "more Status Miles")]/preceding-sibling::span[1]'));
        // Needed Sectors to Next Level
        $this->SetProperty('EarnedSectors', $this->http->FindSingleNode('//p/node()[contains(normalize-space(.), "more Status Miles")]/following-sibling::span[1]'));

        if (!isset($this->Properties['EarnedSectors']) && isset($this->Properties['EarnedFlightMiles'])) {
            $this->SetProperty('EarnedSectors', $this->http->FindSingleNode('//div[h2[contains(normalize-space(text()), "Upgrade")]]/following-sibling::div[2]//p[contains(normalize-space(.), "more Sectors")]/node()[contains(normalize-space(.), "You need to obtain")]/following-sibling::span[1]'));
        }
        // (Validity) Status Expiration Date
        $this->SetProperty('StatusExpirationDate', $this->http->FindSingleNode('//dt[contains(text(), "Validity")]/following-sibling::dd', null, true, "/\-\s*([^<]+)/"));
        // Membership Number
        $this->SetProperty('MemberNo', $this->http->FindSingleNode('//span[contains(text(), "Membership Number:")]/following-sibling::span'));
        // Name
        $this->SetProperty('Name', beautifulName(Html::cleanXMLValue($this->http->FindSingleNode('(//p[contains(text(), "Hello,")]/span)[1]'))));
    }

    public function ParseItineraries()
    {
        $eva = $this->getEva();
        $eva->ParseItineraries();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        return $this->processQuestion();
    }

    protected function getEva()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->eva)) {
            $this->eva = new TAccountCheckerEva();
            $this->eva->http = new HttpBrowser("none", new CurlDriver());
            $this->eva->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->eva->http);
            $this->eva->State = $this->State;
            $this->eva->AccountFields = $this->AccountFields;
            $this->eva->itinerariesMaster = $this->itinerariesMaster;
            $this->eva->HistoryStartDate = $this->HistoryStartDate;
            $this->eva->historyStartDates = $this->historyStartDates;
            $this->eva->http->LogHeaders = $this->http->LogHeaders;
            $this->eva->ParseIts = $this->ParseIts;
            $this->eva->ParsePastIts = $this->ParsePastIts;
            $this->eva->WantHistory = $this->WantHistory;
            $this->eva->WantFiles = $this->WantFiles;
            $this->eva->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->eva->http->setDefaultHeader($header, $value);
            }

            $this->eva->globalLogger = $this->globalLogger;
            $this->eva->logger = $this->logger;
            $this->eva->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->eva->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->eva;
    }

    private function processQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $question = $this->http->FindSingleNode('//p[contains(text(), "We have sent a letter containing the verification code")]/text()');
        $this->logger->debug('[Question]: ' . $question);

        if (!$question) {
            $this->logger->debug('Question not found');

            return $this->checkErrors();
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return true;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $input = $this->waitForElement(WebDriverBy::xpath('//input[@data-required="Please input your verification code."]'), self::WAIT_TIMEOUT);

        if (!$input) {
            $this->logger->debug('Input not found');

            return $this->checkErrors();
        }

        $input->clear();
        $input->sendKeys($answer);

        $submit = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "CONFIRM") and not(@id="btnModalBox")]'), self::WAIT_TIMEOUT);

        if (!$submit) {
            $this->logger->debug('Submit not found');

            return $this->checkErrors();
        }

        $this->logger->debug("Submit question");
        $submit->click();
        sleep(5);
        $this->saveResponse();
        $error = $this->http->FindSingleNode('//li[contains(text(), "The verification code you entered is incorrect")]/text()');

        if ($error) {
            $this->logger->error("[Error]: {$error}");
            $this->holdSession();
            $this->AskQuestion($question, $error, "Question");

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/\"([^\"]+)\",\s*\{\s*action:\s*\"wucLogin/");

        if (!$key) {
            return false;
        }

        /*
        $postData = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "websiteKey" => $key,
            "minScore"   => 0.3,
            "pageAction" => "wucLogin",
            "apiDomain"  => "www.recaptcha.net",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => "wucLogin",
            "min_score" => 0.3,
            "domain"    => "recaptcha.net",
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $link = $this->http->FindSingleNode("//img[@id = 'c_eva_ffp_login_logincaptcha_CaptchaImage']/@src");

        if (!$link) {
            return false;
        }

        $this->http->NormalizeURL($link);
        // https://eservice.evaair.com/FLYEVA/BotDetectCaptcha.ashx?get=image&c=c_eva_ffp_login_logincaptcha&t=678f503f20984c39a2055e0a43bee448
        $this->logger->debug("Download Image by URL: {$link}");
        $recognizer = $this->getCaptchaRecognizer();

        return $this->recognizeCaptchaByURL($recognizer, $link, 'jpg');
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//div[h3[contains(text(), "Self Award Miles")]]/following-sibling::p/span[contains(@class, "color-green") and contains(@class, "vertical-baseline")]', null, false) != null) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // In an on-going effort of improving our online services, the EVA Air website will be down for maintenance
        if (
            $message = $this->http->FindSingleNode('
                //h1[contains(text(), "EVA Air Website Maintenance Announcement")]
                | //p[contains(text(), "In an on-going effort of improving our online services, the EVA Air website will be down for maintenance")]
                | //text()[contains(., "In order to provide the best quality to our customers, EVA Air Official Website will be currently unavailable for upgrade maintenance")]
                | //div[contains(text(), "We\'re busy updating the website for you and will be back shortly.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // System is down for maintenance
        if (
            $message = $this->http->FindPreg("/(System is down for maintenance)/ims")
            ?? $this->http->FindPreg("/Due to the network issue, our website is temporarily unavailable\. We sincerely appreciate your understanding and thank you for your consideration of any inconvenience caused\./")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The resource cannot be found
        if ($message = $this->http->FindSingleNode("//i[contains(text(), 'The resource cannot be found')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Server Error in '/' Application
        if (
            $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            || $this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Couldn\'t resolve host \'eservice\.evaair\.com\')/ims")
            // Service Unavailable
            || $this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable")]')
            || $this->http->FindPreg('/<h1>Service Unavailable<\/h1>/ims')
            || $this->http->FindPreg('/<TITLE>Service Unavailable<\/TITLE>/ims')
            /*
            || in_array($this->http->Response['code'], [500, 302])
            */
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->AccountFields["Login"] == '1306579363' && ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, this page is not available. You may be following a broken link or outdated link on this site. You can continue using the following links or use the search box')]"))) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//title[contains(text(), "Challenge Validation")]')
        ) {
            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//body[@id="t" and @class="neterror"]')) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
