<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSkywardsMobile extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useChromium();
        $this->http->saveScreenshots = true;
        $this->setProxyBrightData();
        $this->http->setUserAgent("Mozilla/5.0 (iPhone; CPU iPhone OS 12_1_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0 Mobile/15E148 Safari/604.1");
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://accounts.emirates.com/english/sso/login?clientId=2Dd6ZA5ZlFn4kGZwIaU1gUCHIF1RO2No&state=eyJhbGciOiJIUzI1NiJ9.eyJtZXRob2QiOiJwb3B1cCIsIlNlY3Rpb24iOm51bGx9.7tyqCixXuJDmT5ixKINurlDxQCf3-hBO9InUBCOtFVQ");

        $loginInput = $this->waitForElement(WebDriverBy::id('sso-email'), 10);
        $passwordInput = $this->waitForElement(WebDriverBy::id('sso-password'), 0);
        $btnLogIn = $this->waitForElement(WebDriverBy::id('login-button'), 0);

        if (!$loginInput || !$passwordInput || !$btnLogIn/* || !$captchaInput*/) {
            $this->logger->error('something went wrong');
            $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            return $this->checkErrors();
        }// if (!$loginInput || !$passwordInput)

        $loginInput->clear();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->clear();
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $btnLogIn->click();

        return true;
    }

    public function checkErrors()
    {
        return false;
    }

    public function twoStepVerification()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Two-step verification', ['Header' => 3]);

        if (
            $this->http->FindSingleNode('//p[contains(text(), "Please choose how you want to receive your passcode.")]')
            && ($email = $this->waitForElement(WebDriverBy::xpath("//div[label[@for = 'radio-button-email']]"), 0))
        ) {
            $email->click();
            $sendOTP = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'send-OTP-button']"), 0);
            $sendOTP->click();

            $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "An email with a 6-digit passcode has been sent to")]'), 5);
            $this->saveResponse();
            $this->holdSession();
        }

        $question = $this->http->FindSingleNode('//p[contains(text(), "An email with a 6-digit passcode has been sent to")]');

        if (!$question) {
            $this->logger->error("something went wrong");

            return false;
        }
        $answerInputs = $this->driver->findElements(WebDriverBy::xpath("//input[contains(@class, 'otp-input-field__input')]"));
        $this->saveResponse();
        $this->logger->debug("count answer inputs: " . count($answerInputs));

        if (!$question || empty($answerInputs)) {
            $this->logger->error("something went wrong");

            return false;
        }
        $this->saveResponse();

        if (!isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, 'Question');

            return false;
        }
        $this->logger->debug("entering answer...");
        $answer = $this->Answers[$question];

        foreach ($answerInputs as $i => $answerInput) {
            if (!isset($answer[$i])) {
                $this->logger->error("wrong answer");

                break;
            }
            $answerInput->sendKeys($answer[$i]);
            $this->saveResponse();
        }// foreach ($elements as $key => $element)
        unset($this->Answers[$question]);
        $this->saveResponse();

        $this->logger->debug("wait errors...");
        $errorXpath = "//p[
                contains(text(), 'The one-time passcode you have entered is incorrect')
                or contains(text(), ' incorrect attempts to enter your passcode. You have ')
                or contains(text(), 'Sorry, you have exceeded the allowed number of attempts to enter your passcode. Your account is temporarily locked for 30 minutes.')
        ]";
        $error = $this->waitForElement(WebDriverBy::xpath($errorXpath), 5);
        $this->saveResponse();

        if (!$error && $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Loading")]'), 0)) {
            $error = $this->waitForElement(WebDriverBy::xpath($errorXpath), 40);
            $this->saveResponse();
        }

        if ($error) {
            $message = $error->getText();

            if (
                strstr($message, 'The one-time passcode you have entered is incorrect')
                || strstr($message, ' incorrect attempts to enter your passcode. You have ')
            ) {
                $this->logger->notice("resetting answers");
                $this->AskQuestion($question, $message, 'Question');
                $this->holdSession();

                return false;
            } elseif (
            strstr($message, 'Sorry, you have exceeded the allowed number of attempts to enter your passcode. Your account is temporarily locked for 30 minutes.')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($error)
        $this->logger->debug("success");

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if (
            $this->isNewSession()
            || $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Your session has expired')]"), 0)
        ) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            return $this->twoStepVerification();
        }

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("
                //div[contains(@class, 'errorPanel')]
                | //div[@class = 'membershipNumber']
                | //h2[contains(text(), 'Points balance')]
                | //div[@class = 'welcome-message']/span
                | //h2[contains(text(), 'Verify account')]
            "), 50);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//p[
                contains(text(), "An email with a 6-digit passcode has been sent to")
                or contains(text(), "Please choose how you want to receive your passcode.")
            ]')
        ) {
            return $this->twoStepVerification();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
    }

    public function ParseItineraries()
    {
        $result = [];

        return $result;
    }

    public function ParseItinerary()
    {
    }

    /*
    function GetHistoryColumns() {
        return array(
            "Date" => "PostingDate",
            "Partner" => "Info",
            "Transaction" => "Description",
            "Type" => "Info",
            "Skywards Miles" => "Miles",
            "Tier miles" => "Info",
            "Bonus Miles" => "Bonus",// refs #4843
        );
    }

    protected $collectedHistory = false;

    function ParseHistory($startDate = null) {
        $this->http->Log('[History start date: '.((isset($startDate))?date('Y/m/d H:i:s', $startDate):'all').']');
        $result = array();
        $startTimer = microtime(true);

        // todo
        return $result;

        $page = 0;
//        $this->http->GetURL("http://www.emirates.com/SessionHandler.aspx?pageurl=/account/english/manage-account/my-statement/index.aspx&section=MYA");
        $this->http->GetURL("https://www.emirates.com/account/english/manage-account/my-statement/index.aspx?mode=JSON&dateRange=twelve_months");

        $response = json_decode($this->http->Response['body']);
        $this->http->Log("json: <pre>".var_export($response, true)."</pre>", false);
        if (isset($response->rows)) {
            $startIndex = sizeof($result);
            $result = $this->ParsePageHistory($startIndex, $startDate, $response->rows);
        }

        $this->http->Log("[Time parsing: ".(microtime(true) - $startTimer)."]");

        return $result;
    }

    function ParsePageHistory($startIndex, $startDate, $rows) {
        $result = array();
        foreach ($rows as $row) {

            if (isset($row->date) && strtotime($row->date))
                $postDate = strtotime($row->date);
            if (isset($startDate, $postDate) && $postDate < $startDate)
                break;
            if (!isset($postDate))
                $postDate = '';

            $result[$startIndex]['Date'] = $postDate;
            if (isset($row->partner))
                $result[$startIndex]['Partner'] = $row->partner;
            if (isset($row->transaction))
                $result[$startIndex]['Transaction'] = $row->transaction;
            if (isset($row->transaction, $row->totalSkywards) && preg_match("/Bonus/ims", $result[$startIndex]['Transaction']))
                $result[$startIndex]['Bonus Miles'] = $row->totalSkywards;
            elseif (isset($row->totalSkywards))
                $result[$startIndex]['Skywards Miles'] = $row->totalSkywards;
            if (isset($row->totalTier))
                $result[$startIndex]['Tier miles'] = $row->totalTier;
            # ----------------------------------- Details ------------------------------------ #
            if (!empty($row->innerRows))
                foreach ($row->innerRows as $innerRows ) {
                    $startIndex++;
                    $result[$startIndex]['Date'] = $postDate;
                    if (isset($innerRows->partner))
                        $result[$startIndex]['Partner'] = $innerRows->partner;
                    if (isset($innerRows->transaction))
                        $result[$startIndex]['Transaction'] = $innerRows->transaction;
                    if (isset($innerRows->totalSkywards, $innerRows->transaction) && preg_match("/Bonus/ims", $result[$startIndex]['Transaction']))
                        $result[$startIndex]['Bonus Miles'] = $innerRows->totalSkywards;
                    elseif (isset($innerRows->totalSkywards))
                        $result[$startIndex]['Skywards Miles'] = $innerRows->totalSkywards;
                    if (isset($innerRows->totalTier))
                        $result[$startIndex]['Tier miles'] = $innerRows->totalTier;
                }
            $startIndex++;
        }

        return $result;
    }
    */
}
