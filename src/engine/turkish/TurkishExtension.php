<?php

namespace AwardWallet\Engine\turkish;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class TurkishExtension extends AbstractParser implements LoginWithIdInterface
{
    private $loginOptionXpath;
    private $loginXpath;

    public function getStartingUrl(AccountOptions $options): string
    {
        if (isset($options->login2)) {
            switch ($options->login2) {
                case '1':
                    $this->loginOptionXpath = '//button[@id="preferencesMemberNumber"]';
                    $this->loginXpath = '//input[@id="tkNumber"]';

                    break;

                case '2':
                    $this->loginOptionXpath = '//button[@id="preferencesMail"]';
                    $this->loginXpath = '//input[@id="emailAddress"]';

                    break;

                case '4':
                    $this->loginOptionXpath = '//button[@id="preferencesIdNumber"]';
                    $this->loginXpath = '//input[@id="idNumber"]';

                    break;
            }
        }

        return 'https://www.turkishairlines.com/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//span[@id="signinbtn"] | //button[@id="signoutBTN"] | //span[contains(text(), "SIGNOUT")]', EvaluateOptions::new()->nonEmptyString());

        return $el->getNodeName() == "BUTTON" || $el->getNodeName() == "SPAN" && strstr($el->getInnerText(), "SIGNOUT");
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.turkishairlines.com/en-us/miles-and-smiles/account/');
        $el = $tab->evaluate('//span[@data-bind="text : msnumber"]', EvaluateOptions::new()->nonEmptyString());

        return $el->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $tab->evaluate('//span[@id="signinbtn"]')->click();

        if (isset($this->loginOptionXpath)) {
            $tab->evaluate('//button[@id="signInPreferencesButton"]')->click();
            $tab->evaluate($this->loginOptionXpath)->click();
        }

        $login = $tab->evaluate($this->loginXpath);
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="msPassword"]');
        $password->setValue($credentials->getPassword());

        sleep(1);

        $tab->evaluate('//button[@id="msLoginButton"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "error-message")] | //button[@id="signoutBTN"] | //span[contains(text(), "SIGNOUT")]');

        if (in_array($submitResult->getNodeName(), ['BUTTON', 'SPAN'])) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The e-mail you entered is not associated to a Miles&Smiles membership. Please check your e-mail and try again")
                || strstr($error, "The Miles&Smiles membership number you entered is incorrect. Please check the information you entered and try again")
                || strstr($error, "Turkish ID number has been entered incorrectly")
                || strstr($error, "The password you entered is incorrect. Please check your password and try again")
                || strstr($error, "You have entered an invalid information. Please check and try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@id="signoutBTN"] | //span[contains(text(), "SIGNOUT")]')->click();
        $tab->evaluate('//span[@id="signinbtn"]');
    }
}
