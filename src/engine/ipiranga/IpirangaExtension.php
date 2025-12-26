<?php

namespace AwardWallet\Engine\ipiranga;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class IpirangaExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.kmdevantagens.com.br/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//p[contains(text(), "Entre")] | //p[contains(text(), "Olá,")]');

        return strstr($el->getInnerText(), "Olá,");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//p[contains(text(), "Olá,")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/Olá,\s(.*)/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//p[contains(text(), "Entre")]');
        sleep(3);
        $tab->evaluate('//p[contains(text(), "Entre")]')->click();

        $login = $tab->evaluate('//input[@id="cpf"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(text(), "ENTRAR")]')->click();

        $submitResult = $tab->evaluate('//p[contains(text(), "Olá,")] | //div[contains(@class,"MuiAlert-message")]/div | //p[contains(@class, "Mui-error") and @id="cpf-helper-text"] | //p[contains(@class, "Mui-error") and not(@id="cpf-helper-text")]', EvaluateOptions::new()->timeout(30));

        if (strstr($submitResult->getAttribute('class'), "Mui-error")) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        if ($submitResult->getNodeName() == 'P') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Error: Login ou senha incorreto")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//p[contains(text(), "Olá,")]')->click();
        $tab->evaluate('//span[contains(text(), "Sair")]')->click();
        $tab->evaluate('//p[contains(text(), "Entre")]');
    }
}
