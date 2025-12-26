<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;

class TAccountCheckerRestaurant extends TAccountChecker
{
    private const REWARDS_PROFILE_PAGE = "https://www.restaurant.com/account";

    private $certificates;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'restaurantCertificate')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PROFILE_PAGE, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PROFILE_PAGE);
        $this->http->RetryCount = 2;

        $this->http->ParseForm(null, '//form[@action="https://www.restaurant.com/login"]');
        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('cf-turnstile-response', $captcha);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if (
            $error = $this->http->FindSingleNode('//div[@class="invalid-feedback"]/text()')
            ?? $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]/div')
        ) {
            $this->logger->error("[Error]: {$error}");

            if (
                strstr($error, "These credentials do not match our records")
                || strstr($error, "There was an error trying to process your input")
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $error;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//img[@src="https://www.restaurant.com/web/images/icons/user-portrait.svg"]/..')));

        $this->http->GetURL('https://www.restaurant.com/points');
        // Balance - Points
        if (!$this->SetBalance(PriceHelper::parse($this->http->FindSingleNode('//text()[contains(.,"You have ")]/following-sibling::strong[contains(text(), "points")]', null, true, '/(.+?)\spoints/'))));
        {
            if ($this->http->FindSingleNode('//span[contains(text(),"You have no points yet.")]')) {
                $this->SetBalanceNA();
            }
        }

        $this->http->GetURL('https://www.restaurant.com/certificates?status=unused');

        if (!$this->http->FindPreg('/You don\'t have any  certificates yet/')) {
            $this->logger->info('Certificates', ['Header' => 3]);

            $this->parseCerfificatesPage();

            // Get all pages with certificates
            while ($link = $this->http->FindSingleNode('//a[@rel="next"]/@href')) {
                $this->http->NormalizeURL($link);
                $this->http->GetURL($link);
                $this->parseCerfificatesPage();
            }
        }

        // Number of certificates
        $this->SetProperty("NumberOfCertificates", $this->certificates);
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $key = "0x4AAAAAAAWb-gxnYBKdmIoD"; //todo

        if (!$key) {
            return false;
        }

        $postData = [
            "type"       => "TurnstileTaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "websiteKey" => $key,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'turnstile',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseCerfificatesPage()
    {
        $certificates = $this->http->XPath->query('//div[contains(@class, "details-card") and not(a)]');
        $this->certificates += $certificates->length;

        for ($i = 0; $i < $certificates->length; $i++) {
            $certificate = $certificates->item($i);
            $code = $this->http->FindSingleNode(".//span[contains(text(), 'Certificate Number:')]/following-sibling::span[1]", $certificate);
            $displayName = Html::cleanXMLValue($this->http->FindSingleNode('.//a[contains(@class, "certificates-title")]', $certificate));
            $location = Html::cleanXMLValue($this->http->FindSingleNode('.//a[contains(@class, "certificates-title")]/../span[@class="font-body-s-regular"]', $certificate));
            $balance = $this->http->FindSingleNode('.//div[contains(@class, "price-badge")]//span', $certificate);
            $purchaseDate = $this->http->FindSingleNode(".//span[contains(text(), 'purchased:')]/following-sibling::span[1]", $certificate, true);

            if (isset($balance, $displayName, $location)) {
                $this->AddSubAccount([
                    'Code'              => 'restaurantCertificate' . $code,
                    'DisplayName'       => $displayName . " ($location)",
                    'Balance'           => $balance,
                    'CertificateNumber' => $code,
                    'PurchaseDate'      => $purchaseDate,
                ]);
            }
        }
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // provider error
        if (
            $this->http->FindPreg("/Server Error in \'\/\' Application\./")
            || $this->http->FindSingleNode('
                    //p[contains(text(), "The following error was encountered while trying to retrieve the URL:")]
                    | //h1[contains(text(), "504 Gateway Time-out")]
                    | //h1[contains(text(), "502 Bad Gateway")]
                    | //h2[contains(text(), "The requested URL could not be retrieved")]
                ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // provider bug (AccountID: 3090360, 3301618, 3511289, 3705304)
        if ($this->http->Response['code'] == 0 && in_array($this->AccountFields['Login'], ['mccuistion@aol.com', 'arwaller1@gmail.com', 'redsocksrule93@yahoo.com', 's.rohan86@gmail.com', 'Jlbrown2012@gmail.com', 'jordan.nadler@aol.com', 'sinhanurag@hotmail.com', 'ycetindil@hotmail.com', 'tayler3@gmail.com', 'd.schagrin@gmail.com', 'christinamcasto@gmail.com', 'chadmd23@gmail.com', 'eaatterberry@gmail.com'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // AccountID: 5346676
        if ($this->http->Response['code'] == 403
            /*
            in_array($this->AccountFields['Login'], [
                'ahlaughlin@gmail.com',
                'habeasnat@yahoo.com',
                'kathiranney@gmail.com',
                'atherfz@gmail.com',
                'stevendbach@gmail.com', // 5373364
                'ryan.braley+deals@gmail.com', // 4149922
                'goyanks444@gmail.com', // 2410513
                ])
            */
            && $this->http->FindSingleNode('//center[normalize-space() = "Microsoft-Azure-Application-Gateway/v2"]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'We are currently performing scheduled maintenance to improve your experience.') ]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 6221215
        if ($this->http->Response['code'] == 500 && $this->AccountFields['Login'] == 'markdavidhansen@gmail.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // maintenance
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.restaurant.com/");
        $this->http->RetryCount = 2;

        if (
            $message = $this->http->FindSingleNode('
                //h1[contains(text(), "We are currently performing scheduled maintenance to Restaurant.com")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $message = $this->http->FindPreg('/The requested service is temporarily unavailable. It is either overloaded or under maintenance. Please try later./')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//button[contains(text(), "Logout")]')) {
            return true;
        }

        return false;
    }
}
