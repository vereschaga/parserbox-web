<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerRivegauche extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    // Cards type: standart/gold
    private $cardOptions = [
        ""         => "Select a card",
        'standart' => 'Standart',
        'gold'     => 'Gold',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        //$this->http->SetProxy($this->proxyReCaptcha());
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->cardOptions;
    }

    public function LoadLoginForm()
    {
        // Form URL
        $this->http->GetURL('http://www.rivegauche.ru/discount/savings');
        // captcha second sending workaround
        $this->http->setCookie("HTTP_REFERER", "http%3A%2F%2Fwww.rivegauche.ru%2Fdiscount%2Fsavings");
        // Form
        if (!$this->http->ParseForm('savings-check-form')) {
            return $this->checkErrors();
        }
        // Card type
        switch ($this->AccountFields['Login2']) {
            default:
                return false;

            case 'standart':
                $this->http->SetInputValue('cardtype', 0);

                break;

            case 'gold':
                $this->http->SetInputValue('cardtype', 1);

                break;
        }
        // Card num
        $this->http->SetInputValue('cardnum', $this->AccountFields['Login']);
        $this->http->SetInputValue('op', '');

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('captcha_response', $captcha);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//title[contains(text(), '502 Bad Gateway')]")) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        return false;
    }

    public function Login()
    {
        // post form
        if (!$this->http->PostForm(['Referer' => 'http://www.rivegauche.ru/discount/savings'])) {
            return $this->checkErrors();
        }
        // success login?
        if ($this->http->FindSingleNode("//h2[contains(@class, 'number-card')]")) {
            return true;
        }
        // failed to login
        if ($message = $this->http->FindSingleNode('//div[@class="carderror"]/ul[@class="carderrors"]/li[1]')) {
            // wrong card num
            if ($message == 'Неверный номер карты') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            } elseif ($message == 'Карта заблокирована') {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            } else {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
        // provider error
        if ($this->http->FindPreg("/Fatal error: Call to undefined function dsm\(\)/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Captcha no valid
        if ($this->http->FindSingleNode("//img[starts-with(@id, 'captcha_image_')]/@src")) {
            // cookie HTTP_REFERER exclude this site bug for debug proxy, but not working on prod
            if (!$this->http->ParseForm('savings-check-form')) {
                return $this->checkErrors();
            }
            // Card type
            switch ($this->AccountFields['Login2']) {
                default:
                    return false;

                case 'standart':
                    $this->http->SetInputValue('cardtype', 0);

                    break;

                case 'gold':
                    $this->http->SetInputValue('cardtype', 1);

                    break;
            }
            // Card num
            $this->http->SetInputValue('cardnum', $this->AccountFields['Login']);
            $this->http->SetInputValue('op', '');

            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('captcha_response', $captcha);
            // post form
            if (!$this->http->PostForm(['Referer' => 'http://www.rivegauche.ru/discount/savings'])) {
                return $this->checkErrors();
            }
            // success login?
            if ($this->http->FindSingleNode("//h2[contains(@class, 'number-card')]")) {
                return true;
            }
//            throw new CheckRetryNeededException(2, 5);
        }

        return false;
    }

    public function Parse()
    {
        // Card Number
        $this->SetProperty('CardNumber', $this->http->FindSingleNode("//h2[contains(@class, 'number-card')]", null, true, '/№\s*([^<]+)/ims'));
        // total savings (ru: общая сумма накоплений)
        $totalSavings = $this->http->FindSingleNode('(//div[@class="values"]/div[@class="row"]/div[@class="savingsAmmount"])[1]');

        if (isset($totalSavings)) {
            $this->SetProperty('TotalSavings', $totalSavings . ' rub');
        }
        // Birthday bonuses - main balance (ru: сумма бонуса к Вашему дню рождения)
        $this->SetBalance($this->http->FindSingleNode('(//div[@class="values"]/div[@class="row"]/div[@class="savingsAmmount"])[2]'));
        // Last Activity
        $this->SetProperty('LastActivity', $this->http->FindPreg("/дата последней покупки по карте\s([^<]+)/ims"));

        // Покупки по карте не совершались!
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && ($this->http->FindSingleNode("//div[contains(text(), 'Покупки по карте не совершались!')]")
                || $this->http->FindSingleNode("//h2[@class = 'number-card standard']"))) {
            $this->SetBalanceNA();
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $url = $this->http->FindSingleNode("//img[@class='captcha_image']/@src");

        if (!$url) {
            return false;
        }
        $this->http->NormalizeURL($url);
        /*
         // Add a click event to CAPTCHA images to reload the CAPTCHA image
          $(".captcha_image", context).click(function() {
            $(this).attr('src', $(this).attr('src').replace(/\?.*$/, '') + '?r=' + Math.random());
          })
         */
        $url .= preg_replace('/\?.*$/', '?r=' . $this->random(), $url);
        $file = $this->http->DownloadFile($url, "jpeg");
        $this->logger->debug("file: " . $file);
        $this->recognizer = $this->getCaptchaRecognizer();
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);

        return $captcha;
    }
}
