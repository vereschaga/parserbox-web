<?php

use AwardWallet\Common\Parsing\Html;

require_once __DIR__ . '/../globaltestmarket/functions.php';

class TAccountCheckerMysurvey extends TAccountCheckerGlobaltestmarket
{
}

class TAccountCheckerMysurveyOld extends TAccountChecker
{
    public $regionOptions = [
        'us' => 'USA',
        'de' => 'Germany',
        'fr' => 'France',
        'uk' => 'United Kindom',
        'au' => 'Australia',
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function LoadLoginForm()
    {
        //$this->http->LogHeaders = true;
        $this->http->removeCookies();
        $sURL = 'https://www.mysurvey.com/';

        switch ($this->AccountFields['Login2']) {
            case 'de':
                $sURL = 'https://de.mysurvey.com/';

                break;

            case 'uk':
                $sURL = 'http://uk.mysurvey.com/';

                break;

            case 'fr':
                $sURL = 'http://fr.mysurvey.com/';

                break;

            case 'au':
                $sURL = 'http://www.mysurvey.net.au/';

                break;

            default:
                // 'https://www.mysurvey.com/' do not showing error for incorrect email
                if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                    throw new CheckException('Invalid Email and/or Password', ACCOUNT_INVALID_PASSWORD);
                }

                break;
        }
        $this->http->GetURL($sURL);

        if (!$this->http->ParseForm("formLogin") && !$this->http->ParseForm("formLoginSmall")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('userPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('savePassword', 'Yes');
        $this->http->SetInputValue('formsubmit', 'Sign In');

        return true;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "We\'re sorry - an error has occurred")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Website maintenance
        if ($this->http->FindPreg('/MaintenanceMessage.gif/ims')
            //# URL => http://maintenance.tns-global.com
            || preg_match('/maintenance/ims', $this->http->currentUrl())) {
            throw new CheckException("Mysurvey.com is currently unavailable for website maintenance", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/Please contact us if the error continues/ims")) {
            throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        }
        // Internal server error
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Internal server error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        //# Successful access
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//p[@class = 'rewardsconfirm']", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Invalid Email and/or Password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "login information you entered is incorrect") or contains(text(), "password you entered is incorrect")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'You cannot be logged in to your account') or contains(text(), 'You cannot log in to MySurvey') or contains(text(), 'but the account used is currently not active')]", null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'You cannot be logged in to your account because your membership is not valid')]", null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //us and de the same
        if ($message = $this->http->FindSingleNode('//div[@class="ui-state-error ui-corner-all"]/p')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/the account used is currently not active/ims')) {
            throw new CheckException("The account is currently not active", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/Confirm Email/ims')) {
            throw new CheckException("Please confirm your email address", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//b[contains(text(),'Please log in if you wish to return to MySurvey.com.')]", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Username and/or Password Invalid')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/We\'re sorry - an error has occurred. Please contact us if the error continues./ims')) {
            $this->throwProfileUpdateMessageException();
        }
        // We apologise, but the account is no longer active. Please contact us if you have any questions.
        if (strstr($this->http->currentUrl(), 'myContent=INACTIVEACCOUNT')) {
            throw new CheckException('We apologise, but the account is no longer active.', ACCOUNT_INVALID_PASSWORD);
        }

        // retries
        if ($this->http->FindSingleNode("//p[contains(text(), 'Your session has expired')]")) {
            throw new CheckRetryNeededException(3, 15);
        }

        // hardcode
        $this->logger->debug($this->http->FindSingleNode("//div[@id = 'login']/fieldset[@class = 'loginSmall']"));

        if ($this->http->ParseForm("formLogin") && $this->http->FindSingleNode("//p[contains(text(), 'Sign in Here!')]")
            || $this->AccountFields['Login'] == 'rattone11@yahoo.com'
            || $this->AccountFields['Login'] == 'lisahammer416@gmail.com'
            || $this->AccountFields['Login'] == 'cmwj12@optusnet.com.au'
            || $this->AccountFields['Login'] == 'brucecharming@gmail.com'
            || $this->AccountFields['Login'] == 'forsavingsonly@aol.com'
            || ($this->AccountFields['Login2'] == 'us'
                && $this->http->FindSingleNode("//div[@id = 'login']/fieldset[@class = 'loginSmall']") == 'Email address Keep me logged in Password Forgot your password?')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
            case 'de':
                //# Balance
                $this->SetBalance($this->http->FindSingleNode('//div[@class="pointsR"]/child::strong[contains(text(), "Punkte")]', null, true, '/(\d*) Punkte/ims'));
                //# Points redeemed
                $this->SetProperty("PointsRedeemed", $this->http->FindSingleNode('//div[@class="pointsR"]/child::strong[contains(text(), "Lose")]', null, true, '/(\d*) Lose/ims'));

                break;

            case 'fr':
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'feature_sidebar_member']/strong")));
                // Balance
                $this->SetBalance($this->http->FindSingleNode("//div[@class = 'pointsR']/strong[1]", null, true, '/([^<]+)\s*point/ims'));
                // Tickets
                $this->SetProperty("Tickets", $this->http->FindSingleNode("//div[@class = 'pointsR']/strong[2]", null, true, '/([^<]+)\s*ticket/ims'));

                break;

            case 'uk':
                $this->http->GetURL("http://uk.mysurvey.com/index.cfm?action=Main.update&dmgname=UPDATE");
                //# Name
                $this->SetProperty("Name", beautifulName(Html::cleanXMLValue(
                    $this->http->FindSingleNode("//input[contains(@id, 'FIRST_NAME')]/@value") . ' ' .
                    $this->http->FindSingleNode("//input[contains(@id, 'LAST_NAME')]/@value")
                )));
                //# Prize Entries
                $this->SetProperty("Entries", $this->http->FindSingleNode("//font[contains(text(), 'You have:')]/strong[2]", null, true, "/(\d+)/ims"));
                $balance = $this->http->FindSingleNode("//p[contains(text(), 'Total points:')]", null, true, "/(\d+)/ims");

                if (!isset($balance)) {
                    $balance = $this->http->FindSingleNode("//font[contains(text(), 'You have:')]/strong[1]", null, true, "/(\d+)/ims");
                }
                //# Balance - Points
                $this->SetBalance($balance);

                break;

            case 'au':
                $this->http->GetURL('http://www.mysurvey.net.au/index.cfm?action=Main.update&dmgname=UPDATE');
                // First Name / Last Name
                $this->SetProperty('Name', beautifulName(Html::cleanXMLValue(
                    $this->http->FindSingleNode('//input[@name="FIRSTNAME"]/@value') . ' ' . $this->http->FindSingleNode('//input[@name="LASTNAME"]/@value')
                )));
                // You have %% points
                $this->SetBalance($this->http->FindSingleNode('//td[contains(text(), "You have")]/following-sibling::td[1]'));
                // Prize Entries
                $this->SetProperty('Entries', $this->http->FindSingleNode('//td[contains(text(), "Prize Entries")]/preceding-sibling::td[1]'));
                // My Loyalty
                $this->SetProperty('Status', $this->http->FindSingleNode('//td[contains(text(), "My Loyalty")]/following-sibling::td[2]'));
                // "need points after status - "My Loyalty"
                $points = $this->http->FindSingleNode('//div[@id="userContent"]//a[contains(@href, "Content=loyaltypage")]/../following-sibling::td[1]');

                if ($points && ($points = explode('/', $points)) && isset($points[1])) {
                    $pointCurrent = (int) $points[0];
                    $pointLimit = (int) $points[1];
                    $pointLimit > $pointCurrent ? $this->SetProperty('PointsNeedNext', ($pointLimit - $pointCurrent)) : null;
                }

                break;

            default:
                $this->http->GetURL("https://www.mysurvey.com/index.cfm?action=Main.update&dmgname=UPDATE");
                //# Sweeps Entries
                $this->SetProperty("Entries", $this->http->FindSingleNode("//div[contains(@class, 'lrpadding')]/font/strong[2]", null, true, "/(\d+)/ims"));
                //# Reward Points
                $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'lrpadding')]/font/strong[1]", null, true, "/(\d+)/ims"));
                //# Name
                $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[@name = 'FIRSTNAME']/@value")
                    . ' ' . $this->http->FindSingleNode("//input[@name = 'LASTNAME']/@value"));

                if (strlen($name) > 2) {
                    $this->SetProperty("Name", beautifulName($name));
                }

                break;
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["NoCookieURL"] = true;

        return $arg;
        $sURL = 'https://www.mysurvey.com/index.cfm?action=Main.membersGeneral&MyContent=rewardsa';

        switch ($this->AccountFields['Login2']) {
            case 'de':
                $sURL = 'https://de.mysurvey.com/index.cfm?action=Main.membersGeneral&MyContent=reward';

                break;

            case 'fr':
                $sURL = 'https://fr.mysurvey.com/index.cfm?action=Main.membersGeneral&MyContent=reward';

                break;

            case 'uk':
                $sURL = 'https://uk.mysurvey.com/index.cfm?action=Main.membersGeneral&MyContent=reward';

                break;

            case 'au':
                $sURL = 'http://www.mysurvey.net.au/index.cfm?action=Main.membersGeneral&MyContent=reward';

                break;
        }
        $arg["CookieURL"] = $sURL;
        $arg['SuccessURL'] = $sURL;

        return $arg;
    }
}
