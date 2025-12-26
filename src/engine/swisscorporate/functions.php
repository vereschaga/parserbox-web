<?php

// refs #2023

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSwisscorporate extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.partnerplusbenefit.com/application/pages2/common/A090NewChooseLocalePage.jsp?urlParamLanguage=de&urlParamCountry=CH');

        if ($token = $this->http->FindPreg('/name="org.apache.struts.taglib.html.TOKEN" value="([^\"]*)"/ims')) {
            $this->http->Log("TOKEN  --->> " . var_export($token, true) . " <<----", true);
            $this->http->Form['org.apache.struts.taglib.html.TOKEN'] = $token;
        } else {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->Form['doLogin'] = 'Login';
        $this->http->FormURL = 'https://www.partnerplusbenefit.com/application/module/common/login.do';

        return true;
    }

    public function checkErrors()
    {
        //# Maintenance
        if ($message = $this->http->FindPreg("/is temporarily not available/ims")) {
            throw new CheckException("The website is temporarily not available. We thank you for your patience and
            your understanding.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        // provider bug
        $this->http->Log("[Current URL]: {$this->http->currentUrl()}");

        if ($this->http->currentUrl() == 'https://www.partnerplusbenefit.com/application/module/common/login.do'
            && $this->http->Response['code'] == 0) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.partnerplusbenefit.com/application/pages2/common/A090NewChooseLocalePage.jsp?urlParamLanguage=de&urlParamCountry=CH';

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'Logout')]/@href")
            || $this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//font[@class="error"]')) {
            throw new CheckException(utf8_encode($message), ACCOUNT_PROVIDER_ERROR);
        }
        // Dieser Benutzername ist gesperrt. Bitte wenden Sie sich an das Service-Team.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Dieser Benutzername ist gesperrt. Bitte wenden Sie sich an das Service-Team.')]")) {
            throw new CheckException(utf8_encode($message), ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/eingegebene Kennwort ist falsch/ims')) {
            throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != 'https://www.partnerplusbenefit.com/application/partner/postbox/showPostbox.do') {
            $this->http->GetURL('https://www.partnerplusbenefit.com/application/partner/postbox/showPostbox.do');
        }
        //# userID
        $this->SetProperty("Name", $this->http->FindSingleNode("//pre[@class = 'userID']"));
        //# Account Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//pre[@class = 'userID']/following-sibling::b[1]", null, true, '/(..[0-9]+)/'));
        //# Balance
        $nBalance = $this->http->FindSingleNode('//b[text()="Ihr aktueller BenefitPunktestand"]/../following::td[1]', null, true, '/(.*) /');
        // Your current ... balance
        if (!isset($nBalance)) {
            $nBalance = $this->http->FindSingleNode('//b[contains(text(), "Your current")]/../following::td[1]/b', null, true, '/(.*) /ims');
        }
        // Your company's current BenefitPoints balance
        if (!isset($nBalance)) {
            $nBalance = $this->http->FindSingleNode('//b[contains(text(), "Your company\'s current")]/../following::td[1]/b', null, true, '/(.*) /ims');
        }

        if (!isset($nBalance)) {
            $nBalance = $this->http->FindSingleNode('//b[contains(text(), "Состояние Вашего счета")]/../following::td[1]/b', null, true, '/(.*) /ims');
        }

        if (!isset($nBalance)) {
            $nBalance = $this->http->FindSingleNode('//b[contains(text(), "Il suo saldo attuale di PuntiBenefit")]/../following::td[1]/b', null, true, '/(.*) /ims');
        }

        if (!isset($nBalance)) {
            $nBalance = $this->http->FindSingleNode('//b[contains(text(), "Aktualny stan konta z punktami Państwa firmy")]/../following::td[1]/b', null, true, '/(.*) /ims');
        }

        if (!isset($nBalance)) {
            $nBalance = $this->http->FindSingleNode('//b[contains(text(), "O seu saldo actual de pontos Benefit")]/../following::td[1]/b', null, true, '/(.*) /ims');
        }

        if (!isset($nBalance)) {
            $nBalance = $this->http->FindSingleNode('//b[contains(text(), "Su saldo de puntos Benefit actual")]/../following::td[1]/b', null, true, '/(.*) /ims');
        }

        if (!isset($nBalance)) {
            $nBalance = $this->http->FindSingleNode('//b[contains(text(), "Το τρέχον υπόλοιπο πόντων Benefit")]/../following::td[1]/b', null, true, '/(.*) /ims');
        }
        $this->SetBalance(str_replace(".", ",", $nBalance));

        //# Expiration Date
        $exp = $this->http->FindSingleNode("//td[contains(text(), 'werden folgende Punkte')]", null, true, "/am\s*([^<]+)\s*werden/ims");

        if (!isset($exp)) {
            $exp = $this->http->FindSingleNode("//td[contains(text(), 'пропадут следующие баллы')]", null, true, "/:\s*([^<]+)\s*пропадут/ims");
        }

        if (!isset($exp)) {
            $exp = $this->http->FindSingleNode("//td[contains(text(), 'pontos cujo prazo de validade termina em')]", null, true, "/termina\s*em\s*([^<]+):/ims");
        }
        $this->http->Log("Expiration Date  $exp - " . var_export(strtotime($exp), true), true);

        if ($exp = strtotime($exp)) {
            $this->SetExpirationDate($exp);
        }
        //# Points to Expire
        $this->SetProperty("PointsToExpire", $this->http->FindSingleNode("//td[contains(text(), 'werden folgende Punkte')]//following-sibling::td[1]", null, true, '/([\d\.\,]+)/'));

        if (!isset($this->Properties['PointsToExpire'])) {
            $this->SetProperty("PointsToExpire", $this->http->FindSingleNode("//td[contains(text(), 'пропадут следующие баллы')]//following-sibling::td[1]", null, true, '/([\d\.\,]+)/'));
        }

        if (!isset($this->Properties['PointsToExpire'])) {
            $this->SetProperty("PointsToExpire", $this->http->FindSingleNode("//td[contains(text(), 'pontos cujo prazo de validade termina em')]//following-sibling::td[1]", null, true, '/([\d\.\,]+)/'));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($message = $this->http->FindSingleNode('//li[contains(text(), "Чтобы пользоваться своим счетом и всеми функциями программы, измените свой пароль.") or contains(text(), "Sie müssen zunächst Ihr Passwort ändern, bevor Sie Ihr Konto im vollen Umfang nutzen können.") or contains(text(), "You have to change your password before you are able to use your account with all functions") or contains(text(), "La password deve essere modificata prima di poter utilizzare nuovamente l\'account in tutte le funzioni.") or contains(text(), "Musisz zmienić swoje hasło aby mieć możliwość korzystania z konta.") or contains(text(), "Tiene que cambiar su contraseña antes de poder utilizar de nuevo su cuenta con todas las funciones.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode('//div[contains(text(), "Here you are able to change your password.") or contains(text(), "Qui può modificare la Sua password.") or contains(text(), "Hier haben Sie die Möglichkeit, Ihr Kennwort zu ändern.") or contains(text(), "Aquí puede cambiar su contraseña.") or contains(text(), "Здесь у Вас есть возможность изменить Ваш пароль.")]')) {
                throw new CheckException("Lufthansa (PartnerPlusBenefit) website is asking you to change your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        //# Full Name
        $this->http->GetURL("https://www.partnerplusbenefit.com/application/module/registration/profile.do");
        $name = CleanXMLValue($this->http->FindSingleNode("(//input[contains(@name, 'partnerFirstName')]/@value)[1]")
            . ' ' . $this->http->FindSingleNode("(//input[contains(@name, 'partnerLastName')]/@value)[1]"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }
}
