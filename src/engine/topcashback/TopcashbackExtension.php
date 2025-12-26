<?php

namespace AwardWallet\Engine\topcashback;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class TopcashbackExtension extends AbstractParser implements LoginWithIdInterface
{
    public $domain = [
        'USA'     => 'https://www.topcashback.com',
        'Germany' => 'https://www.topcashback.de',
        'UK'      => 'https://www.topcashback.co.uk',
    ];

    public $url;
    public $login2;

    /**
     * {@inheritDoc}
     */
    public function getStartingUrl(AccountOptions $options): string
    {
        $this->login2 = $options->login2 ?? 'USA';

        $this->url = $this->domain[$this->login2];

        return $this->url;
    }

    /**
     * {@inheritDoc}
     */
    public function isLoggedIn(Tab $tab): bool
    {
        $loginButton = $tab->evaluate('//a[contains(@id, "SignINButton")] | //span[contains(@id, "lblAccount")]', EvaluateOptions::new()->nonEmptyString());

        return $loginButton->getNodeName() === 'SPAN';
    }

    /**
     * {@inheritDoc}
     */
    public function getLoginId(Tab $tab): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function logout(Tab $tab): void
    {
        // TODO: Implement logout() method.
        $this->logger->info('!Try Logout');

        if ($this->login2 === 'Germany') {
            $tab->gotoUrl($this->url . '/abmelden');
        } else {
            $tab->gotoUrl($this->url . '/logout');
        }
        sleep(2);
    }

    /**
     * {@inheritDoc}
     */
    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate("//a[contains(@id, 'SignINButton')]")->click();

        sleep(3);

        $login = $tab->evaluate('//input[contains(@name, "txtEmail")]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[contains(@name, "loginPasswordInput")]');
        $password->setValue($credentials->getPassword());

        sleep(1);

        $tab->evaluate('//button[contains(@id, "Loginbtn")]')->click();

        sleep(2);

        $tab->showMessage(Tab::MESSAGE_RECAPTCHA);

        $errorOrTitle = $tab->evaluate('//span[contains(@id, "LoginFailed")] | //span[contains(@id, "lblAccount")]/ancestor::div[1]', EvaluateOptions::new()->nonEmptyString()->timeout(30));

        if ($errorOrTitle->getNodeName() === 'DIV') {
            $this->logger->info('!logged in');

            return new LoginResult(true);
        } else {
            $this->logger->info('!error logging in');

            $error = $errorOrTitle->getInnerText();

            return new LoginResult(false, $error);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        // TODO: Implement parse() method.
        $this->logger->info('!Try Parse');
    }
}
