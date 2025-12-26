<?php

class TAccountCheckerJinling extends TAccountChecker
{
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.jinlinghotels.com/member/cardInfo", [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//a/span[text()='Log Out']")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setCookie("language", "en_GB", "www.jinlinghotels.com");
//        $this->http->GetURL("https://www.jinlingelite.com/english/index.aspx");
        $this->http->GetURL("https://www.jinlinghotels.com/member/home");

        if (!$this->http->ParseForm("cardForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("userName", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("loginEnum", "GW");
        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('vericode', $captcha);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.jinlingelite.com/english/index.aspx";

        return $arg;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
        $headers = [
            "Referer" => "https://www.jinlinghotels.com/member/home",
            "Accept"  => "*/*",
        ];

        if (!$this->http->PostForm($headers)) {
            return false;
        }
        // Successful
        if ($this->http->FindPreg("/^(?:success|https:\/\/www\.jinlinghotels\.com\/member\/home)$/")) {
            return true;
        }
        // Log in failed
        // Incorrect Password
        if ($message = $this->http->FindPreg("/^(?:Log in failed|Incorrect Password)$/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // retries
        if ($this->http->FindPreg("/^verify code error$/")) {
            throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (stripos($this->http->currentUrl(), '/member/cardInfo') === false) {
            $this->http->GetURL("https://www.jinlinghotels.com/member/cardInfo");
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'user_card_title']")));
        // Balance - Current Balance
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Current Balance:')]", null, true, "/\:\s*([^<]+)/"));
        // Card No
        $this->SetProperty("Account", $this->http->FindSingleNode("//span[contains(text(), 'Card No.:')]", null, true, "/\:\s*([^<]+)/"));
        // Status - Card Type
        $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(text(), 'Card Type:')]", null, true, "/\:\s*([^<]+)/"));
//        switch ($grade) {
//            case "A":
//                $status = "Jinling Elite Member";
//                break;
//            case "B":
//                $status = "Jinling Gold membership";
//                break;
//            case "C":
//                $status = "Jinling Platinum membership";
//                break;
//            default:
//                $status = '';
//        }

        $this->http->GetURL('https://www.jinlinghotels.com/member/coupon/list/unused');

        if (!$this->http->FindSingleNode("//div[@class='box_content' and contains(., 'You have no coupon yet')]")) {
            $this->sendNotification('refs #16424 - jinling: coupon');
        }

        $this->http->GetURL('https://www.jinlinghotels.com/member/order/hotels');

        if (!$this->http->FindSingleNode('//div[@class="box_content" and contains(., "You don\'t have any reservations yet")]')) {
            $this->sendNotification('refs #16424 - jinling: room Reservations');
        }

        $this->http->GetURL('https://www.jinlinghotels.com/member/order/meetings');

        if (!$this->http->FindSingleNode('//div[@class="box_content" and contains(., "You don\'t have any reservations yet")]')) {
            $this->sendNotification('refs #16424 - jinling: meeting reservations');
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $captcha = "https://www.jinlinghotels.com/member/vericode?" . date('UB');

        if (!$captcha) {
            return false;
        }
        $this->http->NormalizeURL($captcha);
        $file = $this->http->DownloadFile($captcha, "jpg");
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        return $captcha;
    }
}
