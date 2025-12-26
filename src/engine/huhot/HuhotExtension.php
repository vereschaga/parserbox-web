<?php

namespace AwardWallet\Engine\huhot;

use AwardWallet\Engine\california\CaliforniaExtension;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\AccountOptions;

class HuhotExtension extends CaliforniaExtension implements LoginWithIdInterface
{
    /*
    * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
    *
    * like as huhot, canes, whichwich, boloco
    *
    * like as freebirds, maxermas  // refs #16823
    */

    private $login;
    public $code = "lettuce";

    public function getStartingUrl(AccountOptions $options): string
    {
        $this->login = $options->login;
        return 'https://huhot.myguestaccount.com/guest/nologin/account-balance';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        /**
         * Session is too short on this site, unable to stay logged in
         */
        return false;
    }

    public function getLoginId(Tab $tab): string
    {
        /**
         * Login ID is not present on the site, so just return the login
         */
        return $this->login;
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="printedCard"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[contains(@class, "nologinCardnumberSubmitButton")]')->click();

        $submitResult = $tab->evaluate('
            //span[contains(@class, "alert-danger")]
            | //div[contains(@class, "pointsRepeater")]
        ', EvaluateOptions::new()->nonEmptyString());

        if (
            $submitResult->getNodeName() == 'SPAN'
            && strstr($submitResult->getInnerText(), "CAPTCHA")
        ) {
            $tab->showMessage(Message::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('
                //span[contains(@class, "alert-danger") and not(contains(text(), "CAPTCHA"))]
                | //div[contains(@class, "pointsRepeater")]
            ', EvaluateOptions::new()->nonEmptyString());
        }

        if ($submitResult->getNodeName() == 'SPAN') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Invalid card number.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }
}