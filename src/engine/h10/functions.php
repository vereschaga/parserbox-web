<?php

class TAccountCheckerH10 extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://club.h10hotels.com/en/userprofile/';

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://club.h10hotels.com/en/';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
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
        $this->http->GetURL('https://club.h10hotels.com/en/');
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('loginModel.Username', $this->AccountFields['Login']);
        $this->http->SetInputValue('loginModel.Password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('recordar', "on");

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

        if ($message = $this->http->FindSingleNode('//li[contains(text(), "Invalid username or password")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->currentUrl() == 'https://club.h10hotels.com/') {
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);

            if ($this->loginSuccessful()) {
                return true;
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance -(Points)
        $this->SetBalance($this->http->FindSingleNode('//p[@class="puntos"]', null, true, self::BALANCE_REGEXP));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//strong[@class="h5 txt-ellipsis"]')));
        // Card number
        $this->SetProperty('CardNumber', $this->http->FindSingleNode("(//span[contains(text(),'Card number')]/following-sibling::strong)[1]"));
        // Card type
        $this->SetProperty("Status", $this->http->FindSingleNode('//div[@class="termometro"]//strong[@class="text-lg"]', null, true, '/^(.*)\,/'));
        // Member since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//div[@class="col-sm-4 col-sm nTit"]//p[1]/span[2]', null, true, "/([^<]+)/"));

        if ($this->Balance <= 0) {
            return;
        }
        // Expiration Date

        $this->logger->info('Expiration Date', ['Header' => 3]);
        $string = $this->http->FindPreg('/rows:\[([\S\s]*\,[\S\s]*)\]/im');

        if (!isset($string)) {
            return;
        }
        $string = str_replace('},', '}###', $string);
        $string = str_replace('\'', '"', $string);
        $string = preg_replace('/\s/', '', $string);
        $arString = explode('###', $string);
        $etalon = time();

        foreach ($arString as $value) {
            if ($value == '') {
                continue;
            }
            $strDate = $this->http->FindPreg('/"caducidad":"([\d\\/]*)"/', false, $value);
            $strPoints = $this->http->FindPreg('/"puntos":"<spanclass="circle">"\+"[\+|\-]([\d]*)"\+"<\\/span>"/', false, $value);

            if (!$strDate) {
                continue;
            }
            $itemTime = strtotime(str_replace('/', '-', $strDate));

            if ($etalon < $itemTime) {
                if (isset($arUnixTime[$itemTime])) {
                    $arUnixTime[$itemTime] += $strPoints;
                } else {
                    $arUnixTime[$itemTime] = $strPoints;
                }
            }
        }

        if (isset($arUnixTime)) {
            ksort($arUnixTime);
            $this->SetExpirationDate(key($arUnixTime));
            // Expiring Balance - (Points)
            $this->SetProperty("ExpiringBalance", current($arUnixTime));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//span[contains(text(),"Close")]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "The service is temporarily unavailable. Please try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("https://www.h10hotels.com/");

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We are working on improving our website.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
