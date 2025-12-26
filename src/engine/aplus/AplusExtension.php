<?php

namespace AwardWallet\Engine\aplus;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class AplusExtension extends AbstractParser implements LoginWithIdInterface
{
    /**
     * {@inheritDoc}
     */
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://all.accor.com/account/index.en.shtml#/';
    }

    /**
     * {@inheritDoc}
     */
    public function isLoggedIn(Tab $tab): bool
    {
        $loginFieldOrBalance = $tab->evaluate('//p[normalize-space()="Rewards"] 
            | //p[@class="nav-header__number"] 
            | //label[normalize-space()="Email address or membership number"] 
            | //button[normalize-space()="Customize"]');

        return $loginFieldOrBalance->getNodeName() === 'P';
    }

    /**
     * {@inheritDoc}
     */
    public function getLoginId(Tab $tab): string
    {
        $loginId = $tab->evaluate('//text()[normalize-space()="Status"]/preceding::text()[normalize-space()][1]
        | //p[@class="nav-header__number"]',
            EvaluateOptions::new()
                ->nonEmptyString())
            ->getInnerText();

        return str_replace(' ', '', $loginId);
    }

    /**
     * {@inheritDoc}
     */
    public function logout(Tab $tab): void
    {
        $openMenuElm = $tab->evaluate("//button[contains(@aria-label, 'Open My account & Rewards menu')]");

        if ($openMenuElm) {
            $openMenuElm->click();
        }

        $logoutElm = $tab->evaluate("//button[normalize-space()='Logout']");

        if ($logoutElm) {
            $logoutElm->click();
        }

        $tab->gotoUrl("https://all.accor.com/account/index.en.shtml#/");

        $tab->evaluate('//h1[@class="sign-in__title"] 
            | //h1[@class="expressive-heading-05"]
            | //div[contains(@class, "cmp-headingpagehero-title")]/descendant::h1');
    }

    /**
     * {@inheritDoc}
     */
    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $cookieForm = $tab->evaluate("//button[normalize-space()='Customize'] 
            | //text()[normalize-space()='Email address or membership number']/following::input[1]",
            EvaluateOptions::new()
                ->allowNull(true));

        if ($cookieForm->getNodeName() === 'BUTTON') {
            $tab->evaluate("//button[normalize-space()='Customize']")->click();
            $tab->evaluate("//button[normalize-space()='Allow All']")->click();
        }

        $login = $tab->evaluate('//text()[normalize-space()="Email address or membership number"]/following::input[1]', EvaluateOptions::new()->allowNull(true));
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//text()[normalize-space()="Password"]/following::input[1]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate("//text()[normalize-space()='Password']/following::text()[normalize-space()='Log in'][1]/ancestor::button[1]")->click();

        $errorOrTitle = $tab->evaluate('//p[normalize-space()="Rewards"]');

        if ($errorOrTitle->getNodeName() === 'P') {
            return new LoginResult(true);
        } else {
            $error = $errorOrTitle->getInnerText();

            return new LoginResult(false, $error);
        }
    }
}
