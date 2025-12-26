<?php

namespace AwardWallet\Engine\royalcaribbean;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\ElementNotFoundException;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\QuerySelectorOptions;
use AwardWallet\ExtensionWorker\Tab;

class RoyalcaribbeanExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.royalcaribbean.com/account/upcoming-cruises';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//global-login | //a[@interaction="loyalty number"]/span');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//a[@interaction="loyalty number"]/span', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->querySelector("global-login")->shadowRoot()->querySelector('sign-in-form')->shadowRoot()->querySelector('input-text')->shadowRoot()->querySelector('input');
        $login->setValue($credentials->getLogin());

        $password = $tab->querySelector("global-login")->shadowRoot()->querySelector('sign-in-form')->shadowRoot()->querySelector('input-password')->shadowRoot()->querySelector('input');
        $password->setValue($credentials->getPassword());

        $tab->querySelector("global-login")->shadowRoot()->querySelector('sign-in-form')->shadowRoot()->querySelector('primary-button')->shadowRoot()->querySelector('button')->click();

        $tab->evaluate('//a[@interaction="loyalty number"]/span | //global-login', EvaluateOptions::new());
        sleep(5);
        $submitResult = $tab->evaluate('//a[@interaction="loyalty number"]/span | //global-login', EvaluateOptions::new());

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } else {
            $options = QuerySelectorOptions::new()->timeout(3);
            $signInFormShadowContainer = $tab
                ->querySelector('global-login', $options)
                ->shadowRoot()
                ->querySelector('sign-in-form', $options)
                ->shadowRoot();

            try {
                $error = $signInFormShadowContainer
                    ->querySelector('input-error', $options)
                    ->shadowRoot()
                    ->querySelector('p', $options)
                    ->getInnerText();

                return $this->checkLoginErrors($error);
            } catch (ElementNotFoundException $e) {
            }

            try {
                $error = $signInFormShadowContainer
                    ->querySelector('input-text', $options)
                    ->shadowRoot()
                    ->querySelector('input-error', $options)
                    ->shadowRoot()
                    ->querySelector('p', $options)
                    ->getInnerText();

                return $this->checkLoginErrors($error);
            } catch (ElementNotFoundException $e) {
            }

            try {
                $error = $signInFormShadowContainer
                    ->querySelector('input-password', $options)
                    ->shadowRoot()
                    ->querySelector('input-error', $options)
                    ->shadowRoot()
                    ->querySelector('p', $options)
                    ->getInnerText();

                return $this->checkLoginErrors($error);
            } catch (ElementNotFoundException $e) {
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//cruise-planner-main-nav-profile')->click();
        $tab->evaluate('//a[@data-sel="option-logout"]')->click();
        $tab->evaluate('//a[@id="rciHeaderSignIn"]');
    }

    private function checkLoginErrors($error)
    {
        if (
            strstr($error, "We can't find that email. Please check the email you entered or create a new account")
            || strstr($error, "The email or password is not correct")
            || strstr($error, "Something's not right, so give it another try.")
            || strstr($error, "Password is required")
        ) {
            return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
        }

        return new LoginResult(false, $error);
    }
}
