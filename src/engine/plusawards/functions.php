<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPlusawards extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.partnerplusbenefit.com/application/account/account-information.action';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setUserAgent(HttpBrowser::PROXY_USER_AGENT);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);

        if ($this->http->Response['code'] == 403) {
            $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        }

        $this->http->RetryCount = 2;

        if ($this->http->currentUrl() === self::REWARDS_PAGE_URL && $this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.partnerplusbenefit.com/application/gb/en/public/start-page.action');

        if (!$this->http->ParseForm("login")) {
            if (
                strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
            ) {
                throw new CheckRetryNeededException(2, 3);
            }

            return $this->checkErrors();
        }
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("doLogin", "Login");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Maintenance
        if ($this->http->FindPreg('/serverinfo\/maintenance/ims', false, $this->http->currentUrl())) {
            throw new CheckException("Sorry, the website is temporarily unavailable.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // Due to technical problems Swiss PartnerPlusBenefit is temporarily not available.
        if ($message = $this->http->FindPreg("/(Due to technical problems Swiss PartnerPlusBenefit is temporarily not available\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // JBWEB000065: HTTP Status 404 - /application/pages2/common/A090NewChooseLocalePage.jsp
        if (
            $this->http->FindSingleNode("//h1[contains(text(), ': HTTP Status 404 - /')]")
            || $this->http->FindPreg('/The requested URL was rejected. Please consult with your administrator\./')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * The request URL could not be retrieved.
         *
         * Yours Lufthansa PartnerPlusBenefit Team
         */
        //if ($this->http->FindPreg("/The request URL could not be retrieved\./") && $this->http->Response['code'] == 404)
        //    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            // TODO: refs #15771
            $this->checkErrors();
        }
        $response = $this->http->JsonLog();

        // This user name was deleted. Please contact the PartnerPlusBenefit Helpdesk.
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'container-wide')]//p[contains(text(), 'This user name was deleted. Please contact the PartnerPlusBenefit Helpdesk.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Either the user name or the password you entered is incorrect. Please re-enter the details.
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'container-wide')]//p[contains(text(), 'Either the user name or the password you entered is incorrect.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // This user name is blocked. Please contact the PartnerPlusBenefit Service Centre.
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'container-wide')]//p[contains(text(), 'This user name is blocked.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode("//div[@class='alert alert-danger']/p")) {
            $this->logger->error("[Error]: {$message}");

            if (in_array($message, [
                'You have to change your password before you are able to use your account with all functions.',
                'Чтобы пользоваться своим счетом и всеми функциями программы, измените свой пароль.',
                'Tiene que cambiar su contraseña antes de poder utilizar de nuevo su cuenta con todas las funciones.',
                "La password deve essere modificata prima di poter utilizzare nuovamente l'account in tutte le funzioni.",
                "You have to change your password before you are able to use your account with all functions again.",
                "Vous devez changer votre mot de passe avant de pouvoir utiliser votre compte en entier.",
                "Sie müssen zunächst Ihr Passwort ändern, bevor Sie Ihr Konto im vollen Umfang nutzen können.",
                "Voce deve alterar a senha antes de poder acessar a sua conta e todas as suas funções novamente.",
            ])) {
                $this->throwProfileUpdateMessageException();
            }
            // strange error
            if (
                in_array($message, [
                    'No participant was selected',
                    'Kein Teilnehmer wurde gewählt',
                ])
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message != 'Error code not yet implemented or unknown error (Code null). Please report this error code to the Service Centre. Thank you.') {
                return false;
            }
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'panel-status-box']/br[1]/following-sibling::node()[1]")));
        // Account Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//div[@class = 'panel-status-box']/div[@class='text-uppercase']/following-sibling::div", null, false, '/[A-Z]{1,2}\d+/'));
        // Balance - Your BenefitPoints balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'points-balance-banner')]/text()[1]"));

        if ($this->Balance <= 0) {
            return;
        }

        $this->logger->info("Expiration date", ['Header' => 3]);

        // Expiration Date
        $this->http->GetURL('https://www.partnerplusbenefit.com/application/account/account-expiration');

        if (!$this->http->ParseForm('account_expiration')) {
            return;
        }
        $this->http->SetInputValue('startDate', date('d.m.Y'));
        $this->http->SetInputValue('endDate', date('d.m.Y', strtotime('+100 year')));
        $this->http->SetInputValue('doShow', $this->http->FindSingleNode("//input[@name='doShow']/@value"));

        if (!$this->http->PostForm()) {
            return;
        }

        $nodes = $this->http->XPath->query("//table[@class='table table-condensed table-hover ppb']/tbody/tr[td[2]>0]");
        unset($exp);

        foreach ($nodes as $node) {
            $date = $this->http->FindSingleNode("td[1]", $node);
            $points = $this->http->FindSingleNode("td[2]", $node, false, self::BALANCE_REGEXP);

            if ((empty($exp) || strtotime($date, false) < $exp) && $points > 0) {
                if ($exp = strtotime($date, false)) {
                    $this->logger->debug("Expiration Date  {$date} - " . var_export($exp, true));
                    $this->SetExpirationDate($exp);

                    // Points to Expire
                    $this->SetProperty('PointsToExpire', $points);
                }
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")
           // && $this->http->FindSingleNode("//div[@class = 'panel-body']/div[@class='text-uppercase']/following-sibling::div", null, false, '/[A-Z]{2}\d+/')
        ) {
            return true;
        }

        return false;
    }
}
