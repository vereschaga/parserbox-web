<?php

namespace AwardWallet\Engine\golair;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class GolairExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    public $county = '';

    /**
     * {@inheritDoc}
     */
    public function getStartingUrl(AccountOptions $options): string
    {
        $this->county = $options->login2;

        if ($options->login2 === 'Brazil') {
            return 'https://www.smiles.com.br/group/guest/minha-conta';
        }

        return 'https://www.smiles.com.ar/login';
    }

    /**
     * {@inheritDoc}
     */
    public function isLoggedIn(Tab $tab): bool
    {
        if ($this->county === 'Brazil') {
            sleep(5);
            $loginFieldOrBalance = $tab->evaluate('//strong[contains(@class, "my-account__balance--value")] | //div[contains(@class, "action-register")]');

            return $loginFieldOrBalance->getNodeName() === 'STRONG';
        } else {
            $loginFieldOrBalance = $tab->evaluate('//div[contains(@class, "my-account__information row")] | //h2[contains(normalize-space(), "Smiles")]');

            return $loginFieldOrBalance->getNodeName() === 'DIV';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getLoginId(Tab $tab): string
    {
        if ($this->county === 'Brazil') {
            $loginId = $tab->evaluate('//span[contains(@class, "my-account__number--value")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
        } else {
            $tab->gotoUrl('https://www.smiles.com.ar/myaccount');
            sleep(5);
            $loginId = $tab->evaluate('//span[contains(@class, "member-number-text")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
        }

        return $loginId;
    }

    /**
     * {@inheritDoc}
     */
    public function logout(Tab $tab): void
    {
        $this->logger->info('!Try Logout');

        if ($this->county === 'Brazil') {
            $tab->gotoUrl("https://www.smiles.com.br/logout");
        } else {
            $tab->gotoUrl("https://www.smiles.com.ar/logout");
        }
        sleep(2);
    }

    /**
     * {@inheritDoc}
     */
    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        if ($this->county === 'Brazil') {
            return $this->loginForBrasil($tab, $credentials);
        } else {
            return $this->loginForArgentina($tab, $credentials);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        // TODO: Implement parse() method.
    }

    public function loginForBrasil(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[contains(@id, "identifier")]');
        $login->setValue($credentials->getLogin());

        $result = $tab->evaluate('//text()[starts-with(normalize-space(), "E-mail")]/following::iframe[contains(@title, "reCAPTCHA")][1]', EvaluateOptions::new()->allowNull(true)->timeout(10));

        if ($result) {
            $this->logger->notice('show captcha');
            $result = $tab->evaluate('//label[contains(text(),"Senha")]', EvaluateOptions::new()->timeout(90));
        }

        if ($result->getNodeName() === 'LABEL') {
            $password = $tab->evaluate('//input[contains(@id, "password")]');
            $password->setValue($credentials->getPassword());
            sleep(2);

            $tab->evaluate('//text()[starts-with(normalize-space(), "E-mail")]/following::button[1]')->click();
            sleep(5);
        } else {
            $this->logger->info('Captcha not solved!!!');
        }

        $errorOrTitle = $tab->evaluate('//strong[contains(@class, "my-account__balance--value")] | //h4[contains(normalize-space(), "Seus dados não estão corretos")]', EvaluateOptions::new()->nonEmptyString()->timeout(10));

        if ($errorOrTitle->getNodeName() === 'STRONG') {
            $this->logger->info('!logged in');

            //url profile page
            $tab->gotoUrl("https://www.smiles.com.br/group/guest/minha-conta");

            return new LoginResult(true);
        } else {
            $this->logger->info('!error logging in');

            $error = $errorOrTitle->getInnerText();

            return new LoginResult(false, $error);
        }
    }

    public function loginForArgentina(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[contains(@id, "dni")]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[contains(@id, "password")]');
        $password->setValue($credentials->getPassword());

        $result = $tab->evaluate('//text()[contains(normalize-space(), "Ingresá a tu cuenta Smiles")]/following::iframe[contains(@title, "reCAPTCHA")][1]', EvaluateOptions::new()->timeout(3));

        if ($result) {
            $this->logger->notice('show captcha');
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $result = $tab->evaluate('//div[contains(@class, "my-account")][contains(normalize-space(), "Millas")]', EvaluateOptions::new()->timeout(90));
        }

        if ($result->getNodeName() === 'DIV') {
            $tab->gotoUrl('https://www.smiles.com.ar/myaccount');
            sleep(5);
        } else {
            $this->logger->info('Captcha not solved!!!');
        }

        $errorOrTitle = $tab->evaluate('//div[contains(@class, "my-account__information row")] | //h2[contains(normalize-space(), "Smiles")]');

        if ($errorOrTitle->getNodeName() === 'DIV') {
            $this->logger->info('!logged in');

            return new LoginResult(true);
        } else {
            $this->logger->info('!error logging in');

            $error = $errorOrTitle->getInnerText();

            return new LoginResult(false, $error);
        }
    }
}
