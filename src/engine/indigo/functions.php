<?php
/**
 * Class TAccountCheckerIndigo
 * Display name: IndiGo
 * Database ID: 994
 * Author: AKolomiytsev
 * Created: 20.10.2014 5:45.
 */
class TAccountCheckerIndigo extends TAccountChecker
{
    use AwardWallet\Engine\ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setRandomUserAgent();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://book.goindigo.in/");

        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(3, 0);
        }

        if (!$this->http->ParseForm("memberLoginForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("memberLogin.MemberMobileNo", $this->AccountFields['Login']);
        $this->http->SetInputValue("memberLogin.Username", $this->AccountFields['Login']);
        $this->http->SetInputValue("memberLogin.Password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("memberLogin_Submit", "Login");

        return true;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && strtolower($properties['Currency']) == 'USD') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        } elseif (isset($properties['Currency'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f " . $properties['Currency']);
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function checkErrors()
    {
        // Our website is currently experiencing heavy traffic
        if ($message = $this->http->FindPreg("/Our website is currently experiencing heavy traffic\./")) {
            throw new CheckException($message . " PLease try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // HTTP Error 503. The service is unavailable.
            $this->http->FindSingleNode("//p[contains(text(), 'HTTP Error 503. The service is unavailable.')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Your User ID is still not migrated to use Login process with Mobile Number.
        if ($this->AccountFields['Login'] == 'dave.slusher' && $this->http->Response['code'] == 0) {
            throw new CheckException('Your User ID is still not migrated to use Login process with Mobile Number.', ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://book.goindigo.in/";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, '/Member/Logout')]/@href)[1]")) {
            return true;
        }

        /*
        if ($message = $this->http->FindSingleNode("//div[@class='span8 main-body-section page-body']/h4"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        */

        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'The system did not recognize the user login information and/or password that you entered.')]
                | //p[contains(normalize-space(text()), 'Your User ID is still not migrated to use Login process with Mobile Number. Kindly login with User ID and Password.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // You must reset your password to continue.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'You must reset your password to continue.')]")) {
            throw new CheckException("IndiGo website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance
        $this->SetBalance($this->http->FindSingleNode("//label[contains(text(), 'Balance:')]/following-sibling::input[1]/@value", null, true, "/([\d\,\.\s]+)/"));
        // Currency
        $this->SetProperty("Currency", $this->http->FindSingleNode("//label[contains(text(), 'Balance:')]/following-sibling::input[1]/@value", null, true, "/[A-Z]{3}/"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//label[contains(text(), 'User:')]/following-sibling::input[1]/@value")));
        // Account
        $this->SetProperty("Account", $this->http->FindSingleNode("//label[contains(text(), 'Account Number:')]/following-sibling::input[1]/@value"));
    }
}
