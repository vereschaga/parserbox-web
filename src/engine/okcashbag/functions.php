<?php

class TAccountCheckerOkcashbag extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://www.okcashbag.com/mycashbag/point/myPointDetail.do';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $fromIsLoggedIn = false;

    private $data = [
        "sst_cd"     => "",
        "return_url" => "",
    ];
    private $headers = [
        "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        "Accept-Encoding" => "gzip, deflate, br",
        "Content-Type"    => "application/x-www-form-urlencoded",
    ];

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.okcashbag.com/mycashbag/point/myPointDetail.do";

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['tokenId'])) {
            return false;
        }

        if ($this->loginSuccessful($this->State['tokenId'])) {
            $this->fromIsLoggedIn = true;

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.okcashbag.com/index.do");
        $url = $this->http->FindPreg("/location.href\s=.+?'(https:\/\/member\.okcashbag\.com\/ocb\/login\/login\/.+)';/");
        $this->http->GetURL($url);

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("loginname", $this->AccountFields['Login']);
        $this->http->SetInputValue("passwd", $this->AccountFields['Pass']);
        $this->http->SetInputValue("loginkeep", "Y");
        $this->http->SetInputValue("idsave", "");

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue("captchaTxt", $captcha);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'OK캐쉬백 시스템 정기점검')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $message = $this->http->FindSingleNode("//h4[contains(@class,'accent txtPoint')]/following-sibling::p");

        if ($message) {
            $this->logger->debug("Message: " . $message, ['pre' => true]);

            if ($message == "아이디 혹은 비밀번호가 불일치 합니다. 재시도 버튼을 통해서 다시 로그인해 주세요 (브라우저 뒤로가기 버튼 사용 시 재시도 불가)") {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == "OK캐쉬백 시스템 오류입니다. 문제가 계속되면 고객센터에 문의해 주세요.") {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        $tokenId =
            $this->http->FindPreg('/document\.responseForm\.samlResponse\.value = "(.+?)";/')
            ?? $this->http->FindPreg('/name="samlResponse" value="(.+?)"/')
        ;

        if (!$tokenId) {
            return false;
        }
        $this->captchaReporting($this->recognizer);

        if ($this->loginSuccessful($tokenId)) {
            $this->State["tokenId"] = $tokenId;

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Balance - P
        $this->SetBalance($this->http->FindSingleNode("//span[@id='spanUsablePoint']"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id='header']/@data-user", null, true, '/\{"name": "(.+?)",/')));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->fromIsLoggedIn === true
            && $this->http->FindSingleNode("//div[@id='header']/@data-user") == '{"name": "", "point": "-"}'
        ) {
            throw new CheckRetryNeededException(2, 0);
        }

        // quantity Issue date  Coupon name  Validity period
        // 번호	   발급일자	   쿠폰명	    유효기간
        $this->http->GetURL("https://www.okcashbag.com/mycashbag/coupon/myAllCouponDetail.do");
        $couponsNames = $this->http->FindNodes('//th[normalize-space() = "쿠폰명"]/following::tr/td[3]');

        if (!isset($couponsNames)) {
            return;
        }

        foreach ($couponsNames as $key => $coupon) {
            $key++;
            $issueDate = $this->http->FindSingleNode("//th[normalize-space() = '쿠폰명']/following::tr[{$key}]/td[2]", null, true, "/\d{4}\.\d{1,2}\.\d{1,2}/");
            $exp = str_replace('.', '/', $this->http->FindSingleNode("//th[normalize-space() = '쿠폰명']/following::tr[{$key}]/td[4]", null, true, "/\d{4}\.\d{1,2}\.\d{1,2}/"));

            if (!isset($exp) || !isset($issueDate)) {
                $this->sendNotification("Something is wrong with the coupon");

                return;
            }
            $this->AddSubAccount([
                "Code"           => "okcashbagCoupon" . md5($issueDate . $coupon . $exp),
                "DisplayName"    => $coupon,
                'Balance'        => null,
                'IssueDate'      => $issueDate,
                'ExpirationDate' => strtotime($exp),
            ]);
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $file = $this->http->DownloadFile('https://member.okcashbag.com/simpleCaptcha.jsp', "png");
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        return $captcha;
    }

    private function loginSuccessful($tokenId)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.okcashbag.com/displayadmin.do?login=Y", $this->data + ["samlResponse" => $tokenId], $this->headers);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode('//li[@class = "logout"]/a[normalize-space() = "로그아웃"]')) {
            return true;
        }

        return false;
    }
}
