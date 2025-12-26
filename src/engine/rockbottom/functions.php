<?php

require_once __DIR__ . '/../pieology/functions.php';

class TAccountCheckerRockbottom extends TAccountCheckerPieologyPunchhDotCom
{
    use SeleniumCheckerHelper;

    public $code = "rockbottom";
    public $reCaptcha = true;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
//        $this->useGoogleChrome();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://iframe.punchh.com/customers/sign_in.iframe?slug=' . $this->code);

        if (!$this->http->ParseForm('user-form')) {
            return false;
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "user[email]"]'), 10);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "user[password]"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Login"]'), 0);
        $this->savePageToLogs($this);

        if (!$loginInput || !$passwordInput || !$button) {
            return false;
        }

        $this->logger->debug("set login");
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->click();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->savePageToLogs($this);
        $this->logger->debug("click by btn");

        if ($this->reCaptcha) {
            $captcha = $this->parseReCaptcha();

            if ($captcha !== false) {
                $this->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\");");
            }
        }// if ($this->reCaptcha)

//        $button->click();
        $this->driver->executeScript("submitInvisibleRecaptchaForm();");

        $this->waitForElement(WebDriverBy::xpath('
            //a[contains(@href, "sign_out")]
            | //div[@class = "alert-message"]
        '), 0);
        $this->savePageToLogs($this);

        // Incorrect information submitted
        if ($message = $this->http->FindSingleNode('//div[@class="alert-message"]//strong[contains(text(), "Incorrect information submitted")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return true;
    }

    public function parseExtendedProperties()
    {
        $this->logger->notice(__METHOD__);
    }
}

// Feature #5772
class TAccountCheckerRockbottomOld extends TAccountChecker
{
    private $token = null;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->LogHeaders = true;
        $this->http->GetURL("https://rewards.rockbottom.com/");
        // load iframe with login form
        if (!$this->http->FindSingleNode("//title[contains(text(), 'Welcome to Rock Rewards')]")) {
            return $this->checkErrors();
        }

        $this->http->GetURL('https://s3.us-east-1.amazonaws.com/stellar-rock-rrb6h0y4n8cb92fic3zm/content_pages/web_app/static_files/config-production.js?' . time());
        $response = $this->http->JsonLog($this->http->FindPreg('/"id":\s*(\[.+?\])/s'));

        if (empty($response) || count($response) !== 2) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://app.rockbottom.com/oauth/token', [
            'grant_type'    => 'password',
            'client_id'     => $response[0],
            'client_secret' => $response[1],
            'email'         => $this->AccountFields['Login'],
            'password'      => $this->AccountFields['Pass'],
        ], [
            'Accept' => 'application/vnd.stellar-v1+json',
        ]);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->access_token)) {
            $this->token = $response->access_token;

            return true;
        }
        // Invalid login. Please check your credentials and try again
        if ($message = $this->http->FindPreg('/"message":"(Invalid login. Please check your credentials and try again)"/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://app.rockbottom.com/api/profile.json?access_token={$this->token}&_=" . date("UB"));
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');
        // Card #
        $this->SetProperty("CardNumber", ArrayVal($data, 'card_id'));
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($data, 'first_name') . " " . ArrayVal($data, 'last_name')));

        $this->http->GetURL("https://app.rockbottom.com/api/summary.json?access_token={$this->token}&_=" . date("UB"));
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');
        $metrics = ArrayVal($data, 'metrics', []);

        foreach ($metrics as $row) {
            $label = ArrayVal($row, 'label');

            if ($label == 'Point') {
                // Points
                $this->SetBalance(ArrayVal($row, 'balance'));
            } else {
                $this->AddSubAccount([
                    "Code"        => 'rockbottom' . str_replace(' ', '', $label),
                    "DisplayName" => trim($label),
                    "Balance"     => ArrayVal($row, 'balance'),
                ], true);
            }
        }// foreach ($response['meritPlans'] as $row)
        /*
        // AccountID: 2898406
        if ($response['meritPlans'] && $this->ErrorCode == ACCOUNT_ENGINE_ERROR)
            $this->SetBalanceNA();
        */
    }
}
