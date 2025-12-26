<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPetco extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.petco.com/shop/PalsRewardsandOffersView?catalogId=10051&myAccountActivePage=palsRewards&langId=-1&storeId=10151";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $request = \AwardWallet\Common\Selenium\FingerprintRequest::firefox();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN - 10;
        $request->platform = 'Linux x86_64';
        $fingerprint = $this->services->get(\AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$request]);

        $this->setProxyGoProxies();

        if ($fingerprint !== null) {
            $this->http->setUserAgent($fingerprint->getUseragent());
        }

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Email is not valid.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.petco.com/shop/LogonForm');

        sleep(2);
        // TODO: on mac not working
        $this->solveDatadomeCaptcha();

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 7);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@name = "action"]'), 0);
        $this->jsInjection($this);
        $this->saveResponse();

        if (!$loginInput || !$btn) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $btn->click();

        $passInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 5);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue") and not(@style)]'), 0);
        $this->saveResponse();

        if (!$passInput || !$btn) {
            if ($captchaFrame = $this->waitForElement(WebDriverBy::xpath('//iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha") and @width="100%" and @height="100%"]'), 0)) {
                $this->driver->switchTo()->frame($captchaFrame);
                $this->saveResponse();

                if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "You have been blocked")]'), 0)
                    || $this->waitForElement(WebDriverBy::cssSelector('.slider'), 0)
                ) {
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                    $this->DebugInfo = "You have been blocked";
                    $this->markProxyAsInvalid();
//                    throw new CheckRetryNeededException(3);
                }
            }

            return $this->checkErrors();
        }

        $this->logger->debug("set credentials");
        $passInput->sendKeys($this->AccountFields['Pass']);
        $btn->click();

        return true;
    }

    public function Login()
    {
        $res = $this->waitForElement(WebDriverBy::xpath('//h1[contains(., "Keep Your Account Safe")] | //p[contains(text(), "We\'ve sent an email with your code to") or contains(text(), "We\'ve sent a text message to")] | //span[contains(@class, "ulp-input-error-message")]'), 7);
        $this->saveResponse();

        // TODO: on mac not working
        if (!$res) {
            $this->solveDatadomeCaptcha();
        }

        if ($choiceSMS = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "SMS")]'), 0)) {
            $choiceSMS->click();

            $question = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'ve sent an email with your code to") or contains(text(), "Enter your phone number below.")]'), 7);
            $this->saveResponse();

            if ($question && strstr($question->getText(), 'Enter your phone number below')) {
                $this->AskQuestion($question->getText(), null, "QuestionPhone");

                return false;
            }
        }

        if ($this->processSecurityQuestion()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode('//span[contains(@class, "ulp-input-error-message")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Wrong email or password")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function processSecurityQuestion()
    {
        $this->logger->notice(__METHOD__);

        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue") and not(@style)]'), 0);
        $this->saveResponse();
        $questionElement = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'ve sent an email with your code to") or contains(text(), "We\'ve sent a text message to")]'), 0);

        if (!$questionElement || !$btn) {
            return false;
        }

        $question = $questionElement->getText();

        if ($phoneNumber = $this->http->FindSingleNode('//span[@class = "ulp-authenticator-selector-text"]')) {
            $question .= " " . $phoneNumber;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return true;
        }

        $this->logger->debug("Entering answer...");
        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $answerInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "code"]'), 0);
        $this->saveResponse();

        if (!$answerInput) {
            return false;
        }

        $answerInput->sendKeys($answer);
        $this->saveResponse();
        $this->logger->debug("click 'Submit'...");
        $btn->click();
        $this->logger->debug("find errors...");

        sleep(5);

        $error = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Sorry. That code is incorrect.') or contains(text(), 'Incorrect code. You have')]"), 0); // TODO: fake
        $this->saveResponse();

        if ($error) {
            $this->holdSession();
            $answerInput->clear();
            $this->AskQuestion($question, $error->getText(), "Question");
            $this->logger->error("answer was wrong");

            return false;
        }

        return true;
    }

    public function processPhone()
    {
        $this->logger->notice(__METHOD__);

        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue") and not(@style)]'), 0);
        $this->saveResponse();
        $questionElement = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Enter your phone number below.")]'), 0);

        if (!$questionElement || !$btn) {
            return false;
        }

        $question = $questionElement->getText();

        $this->logger->debug("Entering answer...");
        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $answerInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "phone"]'), 0);
        $this->saveResponse();

        if (!$answerInput) {
            return false;
        }

        $answerInput->sendKeys($answer);
        $this->saveResponse();
        $this->logger->debug("click 'Submit'...");
        $btn->click();
        $this->logger->debug("find errors...");

        sleep(5);

        $error = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Sorry. That code is incorrect.') or contains(text(), 'Incorrect code. You have')]"), 0); // TODO: fake
        $this->saveResponse();

        return !$this->processSecurityQuestion();
    }

    public function ProcessStep($step)
    {
        if ($step == "Question" && $this->processSecurityQuestion()) {
            return true;
        }

        if ($step == "QuestionPhone") {
            return $this->processPhone();
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//li[@class = "user-name"]/strong')));
        // Vital Care # (previously "Pals Rewards #")
        $this->SetProperty('Account', $this->http->FindSingleNode('//li[@class = "user-pals-number"]/strong', null, true, '/#(\d+)/'));
        // Member Since
        $this->SetProperty('PalsMemberSince', $this->http->FindPreg("/>\s*Member\s*Since\s*([^<]+)/ims"));

        if (isset($this->Properties['PalsMemberSince']) && $this->Properties['PalsMemberSince'] == 'null') {
            unset($this->Properties['PalsMemberSince']);
        }

        $this->SetProperty('PointsToNextReward', $this->http->FindSingleNode('//li[@class = "user-next-rewards"]/strong', null, true, '/You are (\d+) points away from your next reward/'));

        // Balance - Available Balance
        $balance = $this->http->FindSingleNode("//div[strong[contains(text(), 'Available Balance')]]/text()[last()]", null, true, '/(.+)\s+(?:points|Reward\s+Coupons)/ims');

        if (!$this->SetBalance($balance)) {
            if (
                $this->http->FindSingleNode("//div[strong[contains(text(), 'Available Balance')]]/text()[last()]") == 'points'
                || $this->http->FindSingleNode("//div[ul[li/strong[contains(text(), 'Pals Rewards')]] and contains(text()[last()], 'Sorry. No Rewards available.')]")
                // AccountID: 4315261
                || $this->http->FindSingleNode("//div[ul[li/strong[contains(text(), 'Pals Rewards #')]/following-sibling::span[contains(text()[last()], 'No pals rewards number')]]]")
            ) {
                $this->SetBalanceNA();
            }
            // AccountIDs: 2041292
            elseif (count($this->http->FindNodes("//h1[contains(text(), 'The store has encountered a problem processing the last request')]")) == 2) {
                throw new CheckException("The store has encountered a problem processing the last request. Try again later. If the problem persists, contact your site administrator.", ACCOUNT_PROVIDER_ERROR);
            }
        }// if (!$this->SetBalance($balance))

        // Reward Dollar Certificates
        $nodes = $this->http->XPath->query("//tbody[@id = 'rewardstbodyId']/tr");
        $this->logger->debug("Total {$nodes->length} certificates were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $certificateID = $this->http->FindSingleNode("td[2]", $nodes->item($i));
            $certificatesBalance = $this->http->FindSingleNode("td[5]/strong/following-sibling::text()", $nodes->item($i));
            $exp = $this->http->FindSingleNode("td[4]/strong/following-sibling::text()", $nodes->item($i));
            $expTime = strtotime($exp, false);
            $this->logger->debug('Expiration Date: ' . $exp . ', ' . $expTime);

            if (isset($certificateID) && isset($certificatesBalance) && $expTime) {
                $subAccounts[] = [
                    'Code'           => 'PalsRewardsCertificates' . $certificateID,
                    'DisplayName'    => 'Reward Dollar certificate ID ' . $certificateID,
                    'Balance'        => $certificatesBalance,
                    'ExpirationDate' => $expTime,
                ];
            }// if (isset($certificateID) && isset($certificatesBalance) && strtotime($exp))
        }// for ($i = 0; $i < $nodes->length; $i++)

        // Set SubAccounts
        if (isset($subAccounts)) {
            $this->SetProperty("CombineSubAccounts", false);
            $this->logger->debug("Total subAccounts: " . count($subAccounts));
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if (isset($subAccounts))
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@name = 'Logon']//div[contains(@class, 'g-recaptcha')]/@data-sitekey");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    protected function parseCoordinates($text): array
    {
        $this->logger->notice(__METHOD__);
        preg_match_all('/x=(\d+)/', $text, $m);
        $x = $m[1];
        preg_match_all('/y=(\d+)/', $text, $m);
        $y = $m[1];

        if (count($x) !== count($y)) {
            $this->logger->info('invalid coordinates in the text');

            return [];
        }
        $coords = [];

        for ($i = 0; $i < count($x); $i++) {
            $coords[] = ['x' => $x[$i], 'y' => $y[$i]];
        }
        $this->logger->info('parsed coords:');
        $this->logger->info(var_export($coords, true));

        return $coords;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function solveDatadomeCaptcha(): bool
    {
        $this->logger->notice(__METHOD__);
        $captchaFrame = $this->waitForElement(WebDriverBy::xpath('//iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha") and @width="100%" and @height="100%"]'), 15);

        if (!$captchaFrame) {
            $this->logger->info('captcha not found');
            $this->saveResponse();

            return true;
        }
        $this->driver->switchTo()->frame($captchaFrame);
        $slider = $this->waitForElement(WebDriverBy::cssSelector('.slider'), 5);
        $this->saveResponse();

        if (!$slider) {
            $this->logger->error('captcha not found');
            $this->driver->switchTo()->defaultContent();
            $this->saveResponse();

            if ($captchaFrame = $this->waitForElement(WebDriverBy::xpath('//iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha") and @width="100%" and @height="100%"]'), 0)) {
                $this->driver->switchTo()->frame($captchaFrame);
                $this->saveResponse();

                if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "You have been blocked")]'), 0)) {
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                    $this->DebugInfo = "You have been blocked";
//                    $this->markProxyAsInvalid();
//                    $retry = true;
                }
            }

            return false;
        }

        // loading images to Imagick
        [$puzzleEncoded, $imgEncoded] = $this->driver->executeScript('
            const baseImageCanvas = document.querySelector("#captcha__puzzle > canvas:first-child");
            const puzzleCanvas = document.querySelector("#captcha__puzzle > canvas:nth-child(2)");
            if (!baseImageCanvas || !puzzleCanvas) return [false, false];
            return [puzzleCanvas.toDataURL(), baseImageCanvas.toDataURL()];
        ');

        if (!$puzzleEncoded || !$imgEncoded) {
            $this->logger->error('captcha image not found');

            return false;
        }

        if (!extension_loaded('imagick')) {
            $this->DebugInfo = "imagick not loaded";
            $this->logger->error("imagick not loaded");

            return false;
        }

        // getting puzzle size and initial location on image
        $puzzle = new Imagick();
        $puzzle->setBackgroundColor(new ImagickPixel('transparent'));
        $puzzle->readImageBlob(base64_decode(substr($puzzleEncoded, 22))); // trimming "data:image/png;base64," part
        $puzzle->trimImage(0);
        $puzzleInitialLocationAndSize = $puzzle->getImagePage();
        $puzzle->clear();
        $puzzle->destroy();

        // saving captcha image
        $img = new Imagick();
        $img->setBackgroundColor(new ImagickPixel('transparent'));
        $img->readImageBlob(base64_decode(substr($imgEncoded, 22)));
        $path = '/tmp/seleniumPageScreenshot-' . getmypid() . '-' . microtime(true) . '.jpeg';
        $img->writeImage($path);
        $img->clear();
        $img->destroy();

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 60;
        $params = [
            'coordinatescaptcha' => '1',
            'textinstructions'   => 'Click on the most left edge of the dark puzzle / Кликните по самому левому краю темного паззла',
        ];
        $targetCoordsText = '';

        try {
            $targetCoordsText = $this->recognizer->recognizeFile($path, $params);
        } catch (CaptchaException $e) {
            $this->logger->error("CaptchaException: {$e->getMessage()}");

            if ($e->getMessage() === 'server returned error: ERROR_CAPTCHA_UNSOLVABLE') {
                $this->captchaReporting($this->recognizer, false); // it is solvable

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            if ($e->getMessage() === 'timelimit (60) hit') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }
        } finally {
            unlink($path);
        }

        $targetCoords = $this->parseCoordinates($targetCoordsText);
        $targetCoords = end($targetCoords);

        if (!is_numeric($targetCoords['x'] ?? null)) {
            $this->logger->error("something went wrong");

            return false;
        }

        $m = new MouseMover($this->driver);
        $distance = $targetCoords['x'] /* - $puzzleInitialLocationAndSize['x'] */;
        $stepLength = floor($distance / $m->steps);
        $pauseBetweenSteps = $m->duration / $m->steps;
        $m->enableCursor();
        $this->saveResponse();
//        $m->moveToElement($slider);
        $m = $this->driver->getMouse()->mouseDown($slider->getCoordinates());
        $distanceTraveled = 0;

        for ($stepsLeft = 50; $stepsLeft > 0; $stepsLeft--) {
            $m->mouseMove(null, $stepLength, 0);
            $distanceTraveled += $stepLength;
            usleep(round($pauseBetweenSteps * rand(80, 120) / 100));
        }
        $lastStep = round($distance - $distanceTraveled);

        if ($lastStep > 0) {
            $m->mouseMove(null, $lastStep, 0);
        }
        $this->saveResponse();
        $m->mouseUp();
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//title[contains(text(), "You have been blocked")]')) {
            $this->DebugInfo = $message;
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
        }

        $this->logger->debug('switch to defaultContent');
        $this->driver->switchTo()->defaultContent();
        $this->saveResponse();
        $this->logger->debug('waiting for page loading captcha result');

        return true;
    }

    private function jsInjection($selenium)
    {
        $this->saveResponse();
        $this->logger->notice("js injection");
        $selenium->driver->executeScript('try { document.querySelector(\'[style = "height:100vh;width:100%;position:absolute;top:0;left:0;z-index:2147483647;background-color:#ffffff;"]\').hidden = true; } catch (e) {}');
        $this->saveResponse();
    }
}
