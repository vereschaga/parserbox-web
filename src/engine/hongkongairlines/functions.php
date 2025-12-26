<?php

// used Hainan

use AwardWallet\Engine\ProxyList;

class TAccountCheckerHongkongairlines extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerHongkongairlinesSelenium.php";

        return new TAccountCheckerHongkongairlinesSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        //$this->http->SetProxy($this->proxyDOP());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        /*
        $this->http->GetURL("https://www.hongkongairlines.com/");
        */
        $this->http->GetURL("https://www.hongkongairlines.com/member/users/login_view?langType=en_HK");

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }

        return $this->selenium();

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        // refs #14684
        $this->AccountFields['Pass'] = substr($this->AccountFields['Pass'], 0, 6);

        $this->http->setMaxRedirects(10);
        /*
         *
         * https://passport.cnblogs.com/scripts/jsencrypt.min.js
         *
        function encrypt(){
            $.ajax({
                type: 'post',
                contentType: "application/x-www-form-urlencoded;charset=UTF-8",
                encoding:"UTF-8",
                url: "/member/users/loginPublicKeyGet.do",
                dataType: 'text',
                async:false,
                success: function(result){
                    var encrypt = new JSEncrypt();
                    encrypt.setPublicKey(result);
                    $("#password").val(encrypt.encrypt($("#password").val()));
                    $("#loginCode").val(encrypt.encrypt($("#loginCode").val()));
                    $("#txtran").val(encrypt.encrypt($("#txtran").val()));
                },
                error: function(request){
                    alert("Connection error");
                }
            })
        }
        //登入
        function login(){
            if(validIsPass()==false){
                return;
            }
            encrypt();
            document.getElementById("loginForm").submit();
        }
        */
        $this->http->GetURL("https://www.hongkongairlines.com/member/users/login.do?loginId=" . urlencode($this->AccountFields['Login']) . "&pwd=" . urlencode($this->AccountFields['Pass']) . "&verifCode={$captcha}");
        $this->http->setMaxRedirects(5);

        return true;
    }

    public function Login()
    {
        /*
        // new auth
        if ($this->http->ParseForm("waitingForm")) {
            $this->http->FormURL = 'https://new.hongkongairlines.com/hxair/ibe/common/homeRedirect.do';
            $this->http->PostForm();
        }// if ($this->http->ParseForm("waitingForm"))
        */

        // Access is allowed
        if ($this->loginSuccessful()) {
//            $this->http->GetURL("https://new.hongkongairlines.com/hxair/ibe/profile/profileAccount.do");
            return true;
        }
        // Invalid credentials
        if ($message = trim($this->http->FindSingleNode("
                //div[@id = 'login_error']/font[normalize-space(text()) != '']
                | //span[@id = 'errorMsg' and normalize-space(text()) != '']
            "))
        ) {
            if (
                strstr($message, 'Code not correct!')
                || strstr($message, '验证码错误')
                || strstr($message, 'Verification code does not match')
            ) {
                // do not send report! it often may not true
                //$this->recognizer->reportIncorrectlySolvedCAPTCHA();
                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }// if (strstr($message, 'Code not correct!'))

            // Card number or password is incorrect. Please retry.
            if (strstr($message, 'Card number or password is incorrect.')
                // ERROR Incorrect username or password
                || strstr($message, 'Incorrect username or password')
                // Your membership number has been upgraded to ... . Please login again with new membership number.
                || strstr($message, 'Your membership number has been upgraded to')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // We are sorry, a system error has occurred.
            elseif (strstr($message, 'We are sorry, a system error has occurred.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } else {
                $this->logger->error("[Error]: {$message}");
            }
        }// if ($message = trim($this->http->FindSingleNode("//div[@id = 'login_error']/font[normalize-space(text()) != '']")))

        // hard code
        if (in_array($this->AccountFields['Login'], [
            '7006813210',
            '7007848223', ]
            )
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 2590473, 2036937
        /*
        sleep(3);
        $this->http->GetURL("https://www.hongkongairlines.com/member/users/login_view");
        // Access is allowed
        if ($this->loginSuccessful())
            return true;
        */

        /*
        // retries
        if ($this->http->currentUrl() == "https://www.hongkongairlines.com/member/users/login_view")
            throw new CheckRetryNeededException(2, 10);
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/\{\s*Surname\s*=\s*'([^\']+)/") . " " . $this->http->FindPreg("/\{\s*Surname\s*=\s*'[^\']+\'\s*\+\s*\"\s*\"\s*\+\s*\'([^']+)/")));
        // Membership No.
        $this->SetProperty("MemberNo", $this->http->FindPreg("/var\s*cid\s*=\s*'([^']+)/"));
        // Balance - Points Management
        $this->SetBalance($this->http->FindPreg("/var\s*POINTS\s*=\s*'([^']+)/"));
        // Exp date - Points validity
        $exp = $this->http->FindPreg("/var\s*EXPDATE\s*=\s*'([^']+)/");
        $this->logger->debug("Exp: " . $exp . " length: " . strlen($exp));

        if (strlen($exp) == 8) {
            $part = str_split($exp, 2);

            if (isset($part[3])) {
                $exp = $part[3] . '.' . $part[2] . '.' . $part[0] . $part[1];
                $this->logger->debug("Exp: " . $exp);

                if (strtotime($exp)) {
                    $this->SetExpirationDate(strtotime($exp));
                }
            }// if (isset($part[3]))
        }// if (strlen($exp) == 8)
        elseif (strstr($exp, '/')) {
            $this->logger->debug("Exp date: {$exp} / " . strtotime($exp) . " ");

            if (strtotime($exp)) {
                $this->SetExpirationDate(strtotime($exp));
            }
        }// elseif (strstr($exp, '/'))
        // Status
        $status = $this->http->FindPreg("/var\s*GRADE\s*=\s*'([^']+)/");

        if ($status == 'PLATINUM') {
            $this->SetProperty("Status", "Platinum");
        } elseif ($status == 'GOLDEN') {
            $this->SetProperty("Status", "Gold");
        } elseif ($status == 'SILVER') {
            $this->SetProperty("Status", "Silver");
        } elseif ($status == 'STANDARD') {
            $this->SetProperty("Status", "Standard");
        } elseif ($status == 'SELECT') {
            $this->SetProperty("Status", "Select");
        }
        // Qualifying Points
        $this->SetProperty("QualifyingPoints", $this->http->FindPreg("/var\s*requalifyGradeQpTotal\s*=\s*'([^\']+)/"));
        // Qualifying Segments
        $this->SetProperty("QualifyingSegments", $this->http->FindPreg("/var\s*requalifyGradeSegTotal\s*=\s*'([^\']+)/"));
        // Points to Next Level
        $this->SetProperty("PointsToNextLevel", $this->http->FindPreg("/var SQPsPlati\s*=\s*'([^\']+)/"));
        // Qualifying Segments to Next Level
        $this->SetProperty("SegmentsToNextLevel", $this->http->FindPreg("/var\s*SQSsPlati\s*=\s*'([^\']+)/"));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        /*
        $file = $this->http->DownloadFile("https://www.hongkongairlines.com/member/users/getLoginCode", "jpg");
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);
        */
        $img = $this->waitForElement(WebDriverBy::xpath("//img[@id = 'img_captcha']"), 0);

        if (!$img) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $pathToScreenshot = $this->takeScreenshotOfElement($img);
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot);
        unlink($pathToScreenshot);

        return $captcha;
    }

//    public function IsLoggedIn()
//    {
//        $this->http->RetryCount = 0;
//        $this->http->GetURL("https://new.hongkongairlines.com/hxair/ibe/profile/profileAccount.do", [], 20);
//        $this->http->RetryCount = 2;
//        if ($this->loginSuccessful()) {
//            return true;
//        }
//
//        return false;
//    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/var\s*cid\s*=\s*'([^']+)/")) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $startTimer = $this->getTime();
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->http->brotherBrowser($selenium->http);
            $this->logger->notice("Running Selenium...");
            $this->getTime($startTimer);
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;

            if (false) {
                $selenium->http->setRandomUserAgent(5, true, false);
                $selenium->useFirefox();
                $delay = 60;
            } else {
                $selenium->useChromium(SeleniumFinderRequest::CHROMIUM_80);
                $delay = 10;
            }
            $selenium->useCache();
//            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.hongkongairlines.com/member/users/login_view?langType=en_HK");
            } catch (UnexpectedAlertOpenException $e) {
                $this->logger->error("exception: " . $e->getMessage());
                $error = $selenium->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $selenium->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException $e) {
                $this->logger->debug("no alert, skip");
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'loginCode']"), 5);
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 5);
            $captchaFiled = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'txtran']"), 0);
//            $btn = $selenium->waitForElement(WebDriverBy::xpath('//img[@aria-label = "Login"]'), 5);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (/*!$btn || */ !$login || !$pass || !$captchaFiled) {
                $this->logger->error("something went wrong");

                return $this->checkErrors();
            }
            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $captcha = $selenium->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $captchaFiled->sendKeys($captcha);
//            $btn->click();

            /*
             * encrypt credentials in selenium
             * /
            $selenium->driver->executeScript('encrypt();');
            sleep(2);
            $selenium->driver->executeScript('localStorage.setItem(\'login\', $("#loginCode").val());');
            $selenium->driver->executeScript('localStorage.setItem(\'pass\', $("#password").val());');
            $selenium->driver->executeScript('localStorage.setItem(\'captcha\', $("#txtran").val());');

            $loginId = $selenium->driver->executeScript("return localStorage.getItem('login');");
            $this->logger->info("[Form login: " . $loginId);
            $pwd = $selenium->driver->executeScript("return localStorage.getItem('pass');");
            $this->logger->info("[Form pass: " . $pwd);
            $verifCode = $selenium->driver->executeScript("return localStorage.getItem('captcha');");
            $this->logger->info("[Form captcha: " . $verifCode);
            $cookies = $selenium->driver->manage()->getCookies();
            foreach ($cookies as $cookie)
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], isset($cookie['expiry']) ? $cookie['expiry'] : null);

            $data = [
                "loginId"   => $loginId,
                "pwd"       => $pwd,
                "verifCode" => $verifCode,
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.hongkongairlines.com/member/users/login.do", $data);
            $this->http->RetryCount = 2;

            return true;
            */

            $selenium->driver->executeScript('login()');

            $success = $selenium->waitForElement(WebDriverBy::xpath("
                //span[@class = 'member' or contains(normalize-space(text()), 'Award Points')]
                | //span[@id = 'errorMsg' and normalize-space(text()) != '']
                | //div[@id = 'login_error']/font[normalize-space(text()) != '']
            "), $delay);

            try {
                $this->savePageToLogs($selenium);
            } catch (UnexpectedAlertOpenException $e) {
                $this->logger->error("exception: " . $e->getMessage());
                $error = $selenium->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $selenium->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            }// catch (UnexpectedAlertOpenException $e)

            // provider bug fix
            if (
                !$success
                && !$this->http->FindSingleNode("
                    //div[@id = 'login_error']/font[normalize-space(text()) != '']
                    | //span[@id = 'errorMsg' and normalize-space(text()) != '']
                ")
            ) {
                // AccountID: 2590473, 2036937
                $selenium->http->GetURL("https://new.hongkongairlines.com/hxair/ibe/deeplink/profileAccount.do?language=en&market=HK");
                $success = $selenium->waitForElement(WebDriverBy::xpath("//span[@class = 'member' or contains(normalize-space(text()), 'Award Points')]"), 10);
                // save page to logs
                $this->savePageToLogs($selenium);
//                // Access is allowed
//                if ($this->loginSuccessful())
//                    $this->sendNotification("hainan (hongkongairlines). See logs");
                // provider bug fix
                if (
                    !$success
                    && $this->http->FindSingleNode("//h2[normalize-space(text()) = 'encounteredSomeProblems']/following-sibling::p[normalize-space(text()) = 'notSubmitContinuously']")
                ) {
                    $retry = true;
                }
                // This site can’t be reached
                if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")) {
                    $this->DebugInfo = "This site can’t be reached";
                    $retry = true;
                }// if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]"))
            }

            if ($this->http->FindNodes("//span[@class = 'member' or contains(text(), 'Award Points')]")) {
                $selenium->recognizer->reportGoodCaptcha();
            } elseif ($message = trim($this->http->FindSingleNode("//div[@id = 'login_error']/font[normalize-space(text()) != '']"))) {
                if (
                    !strstr($message, 'Code not correct!')
                    && !strstr($message, '验证码错误')
                    && !strstr($message, 'Verification code does not match')
                ) {
                    $selenium->recognizer->reportGoodCaptcha();
                }
            }

            // selenium bug fix
            if ($this->http->FindPreg("/^<head><\/head><body><pre style=\"word-wrap: break-word; white-space: pre-wrap;\"><\/pre><\/body>$/")) {
                $retry = true;
            }
            // provider bug fix
            if (
                !$success
                && empty($message)
                && $selenium->http->currentUrl() == 'https://www.hongkongairlines.com/en_HK/homepage'
            ) {
                $retry = true;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (UnexpectedJavascriptException | UnknownServerException | WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $retry = true;
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";
            $retry = true;
        }
        /*
        catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException exception: " . $e->getMessage());
        }
        */
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        $this->getTime($startTimer);

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Service Unavailable - Zero size object
        if ($this->http->FindSingleNode('
                //h1[contains(text(), "Service Unavailable - Zero size object")]
                | //p[contains(text(), "Sorry, the page you are looking for is currently unavailable.")]
                | //body[contains(text(), "Internal Server Error")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    /*
    function Parse() {
        $http = $this->http;
        // Name
        $this->SetProperty("Name", beautifulName($http->FindSingleNode("//td[strong[contains(text(), 'Member’s Name')]]/following-sibling::td[1]")));

        // AccountID: 2590473, 2036937
        if (empty($this->Properties['Name']))
            $this->SetProperty("Name", beautifulName($http->FindSingleNode("//li[contains(text(), 'Member No.：')]/preceding-sibling::li[1]")));

        // Member Status
        $this->SetProperty("Status", $http->FindSingleNode("//td[strong[contains(text(), 'Member Status')]]/following-sibling::td[1]"));
        // Member No.
        $this->SetProperty("MemberNo", $http->FindSingleNode("//li[contains(text(), 'Member No.：')]", null, true, "/：\s*([^<]+)/"));
        // Balance - Points Management
        $this->SetBalance($http->FindSingleNode("//li[contains(text(), 'Points Management：')]", null, true, "/：\s*([^<]+)/"));
        // Exp date - Points validity
        $exp = $this->http->FindSingleNode("//td[strong[contains(text(), 'Points validity')]]/following-sibling::td[1]");
        $this->logger->debug("Exp: ".$exp." length: ".strlen($exp));
        if (strlen($exp) == 8) {
            $part = str_split($exp, 2);
            if (isset($part[3])) {
                $exp = $part[3].'.'.$part[2].'.'.$part[0].$part[1];
                $this->logger->debug("Exp: ".$exp);
                if (strtotime($exp))
                    $this->SetExpirationDate(strtotime($exp));
            }// if (isset($part[3]))
        }// if (strlen($exp) == 8)
        elseif (strstr($exp, '/')) {
            $this->logger->debug("Exp date: {$exp}/ ".strtotime($exp)." ");
            if (strtotime($exp))
                $this->SetExpirationDate(strtotime($exp));
        }// elseif (strstr($exp, '/'))
    }
    */
}
