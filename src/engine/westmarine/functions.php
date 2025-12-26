<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerWestmarine extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.westmarine.com/my-account';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful() && !strstr($this->http->currentUrl(), '/login')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        /*
        $this->setProxyBrightData(); // prevent "error: Network error 28 - Operation timed out after 60001 milliseconds with 0 bytes received" on post request
        */
        $this->http->GetURL("https://www.westmarine.com/login");

        if (!$this->http->ParseForm("login-form")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("loginEmail", $this->AccountFields['Login']);
        $this->http->SetInputValue("loginPassword", $this->AccountFields['Pass']);

        /*$captcha = $this->parseCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }*/

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $timeout = 80;

        if (!$this->http->PostForm([], $timeout)) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if (isset($response->redirectUrl)) {
            $redirectUrl = $response->redirectUrl;
            $this->http->NormalizeURL($redirectUrl);
            $this->http->GetURL($redirectUrl);
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        // Invalid login or password. Remember that password is case-sensitive. Please try again.
        if (isset($response->error[0]) && strstr($response->error[0], 'Invalid login or password. Remember that password is case-sensitive. Please try again.')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(strip_tags($response->error[0]), ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Points Available
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Points Available')]", null, true, '/([\d\,\.\-\s*]+)Point/ims'));
        // Member Since
        $this->SetProperty("StartDate", $this->http->FindSingleNode("//div[contains(text(), 'Member Since')]", null, true, '/Since\s*([^<]+)/ims'));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode('//div[contains(@class, "loyalty-badge")]'));

        $this->http->GetURL('https://www.westmarine.com/profile');
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@id = 'firstName']/@value") . " " . $this->http->FindSingleNode("//input[@id = 'lastName']/@value")));

        // Reward Certificates
        $this->http->GetURL("https://www.westmarine.com/on/demandware.store/Sites-WestMarine-Site/en_US/Loyalty-ShowCertificates");
        // Member #
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(text(), 'Member')]/following-sibling::span"));

        $certificates = $this->http->XPath->query("//th[contains(text(), 'Certificate Number')]/parent::tr/following-sibling::tr[td[4]]"); //todo: old xpath
        $this->logger->debug("Total Reward Certificates found: " . $certificates->length);

        for ($i = 0; $i < $certificates->length; $i++) {
            $certificateNumber = $this->http->FindSingleNode("td[1]", $certificates->item($i));
            $balance = $this->http->FindSingleNode("td[2]", $certificates->item($i), true, "/\\$[\d.,]+/");
            //$displayName = $this->http->FindSingleNode("td[3]", $certificates->item($i));
            $expirationDate = str_replace('-', '/', $this->http->FindSingleNode("td[4]", $certificates->item($i)));
            $this->logger->debug("{$expirationDate}: " . strtotime($expirationDate));

            if (isset($balance, $certificateNumber) && strtotime($expirationDate)) {
                $this->AddSubAccount([
                    'Code'           => 'westmarineRewardCertificates' . $i,
                    'DisplayName'    => "Certificate # " . $certificateNumber,
                    'Balance'        => $balance,
                    'ExpirationDate' => strtotime($expirationDate),
                ]);
            }// if (isset($balance, $displayName, $certificateNumber) && strtotime($expirationDate))
        }// for ($i = 0; $i < $nodes->length; $i++)
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//button[@type='submit']/@data-sitekey");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'Logout')]/@href")) {
            return true;
        }

        return false;
    }
}
