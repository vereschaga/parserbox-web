<?php

// refs #2022

class TAccountCheckerWebmiles extends TAccountChecker
{
    public $regionOptions = [
        ""   => "Select your country",
        'ch' => 'Switzerland',
        'at' => 'Austria',
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = $this->regionOptions;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        switch ($this->AccountFields['Login2']) {
            case 'at':
                $loc = 'at';

                break;

            case 'de':
//				if (!isset($loc)) $loc = 'de';
                throw new CheckException('It seems that webmiles website (https://www.webmiles.de/) no longer exists.', ACCOUNT_PROVIDER_ERROR); /*review*/

                break;

            default:
                $loc = 'ch';

                break;
        }
        $this->http->GetURL('https://www.webmiles.' . $loc . '/');
        $this->http->Form['topbox_loginform%5B__is_submitted%5D'] = '1';
        $this->http->Form['topbox_loginform%5B__hard_errors%5D'] = '0';
        $this->http->Form['topbox_loginform%5B__soft_errors%5D'] = '0';
        $this->http->Form['identity'] = $this->AccountFields['Login'];
        $this->http->Form['credential'] = $this->AccountFields['Pass'];
        $this->http->Form['position'] = 'top';
        $this->http->FormURL = 'https://www.webmiles.' . $loc . '/auth/login/check';

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        //$arg['CookieURL'] = '';
        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//p[contains(text(), 'die gewünschte Seite ist nicht verfügbar oder es ist ein Fehler aufgetreten.')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'The request could not be satisfied.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        switch ($this->AccountFields['Login2']) {
            case 'at':
            default:
                $this->http->PostForm();
                $error = $this->http->FindSingleNode('//div[@id="loginform_errorMessages"]');

                if (isset($error) && $error != "") {
                    throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
                } //utf8_encode($error);
                // Access is allowed
                if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
                    return true;
                }

                if ($this->http->FindPreg('/Passwort vergessen?/ims')) {
                    throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
                }
                // Bitte Zugangsdaten überprüfen und erneut versuchen
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Bitte Zugangsdaten überprüfen und erneut versuchen')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                //# Maintenance
                if ($this->http->FindPreg('/\/maintenance/ims', false, $this->http->currentUrl())) {
                    throw new CheckException("We're sorry, website is down for scheduled maintenance. Please try again later.", ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->http->FindPreg("/(webmiles ist deshalb vorübergehend nicht verfügbar\.)/ims")) {
                    throw new CheckException("We're sorry, webmiles is currently temporarily unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
                }/*chcekd*/

                // provider error?
                if ($this->http->Response['code'] == 404 && empty($this->http->Response['body'])) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                break;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
            case 'at': //return false; break;
                $loc = 'at';

                break;

            default:
                $loc = 'ch';

                break;
        }
//        $this->http->GetURL('https://www.webmiles.'.$loc.'/mein-konto');
        // Name
        $name = $this->http->FindSingleNode('//span[contains(text(),"Willkommen,")]', null, true, '/Willkommen, (.*)/');

        if (!isset($name)) {
            $name = $this->http->FindSingleNode('//span[contains(text(),"Bienvenu")]', null, true, '/Bienvenu.*, (.*)/');
        }
        $this->SetProperty("Name", beautifulName($name));
        $Balance = $this->http->FindSingleNode('//div[@class="account"]');

        if (isset($Balance)) {
            $Balance = str_replace("'", "", $Balance);
            $this->SetBalance($Balance);
        }
        $this->SetProperty("Pending", $this->http->FindSingleNode('//div[@class="basket"]/a'));

        //# Expiration date  // refs #6483
        $this->http->GetURL('https://www.webmiles.' . $loc . '/mein-konto');
        $nodes = $this->http->XPath->query("//p[strong[contains(text(), 'Diese Bonusmeilen verfallen bald:')]]/following-sibling::table[1]//tr");
        $this->logger->debug("Total {$nodes->length} exp nodes were found");

        if ($nodes->length > 0) {
            $exp = $this->http->FindSingleNode("td[1]", $nodes->item(0));

            if ($exp = strtotime($exp)) {
                $this->SetExpirationDate($exp);
            }
            //# Expiring Balance
            $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode("td[2]", $nodes->item(0)));

            if ($nodes->length > 1) {
                $this->sendNotification("Multiple nodes with expiration date // RR");
            }
        }// if ($nodes->length > 0)
    }
}
