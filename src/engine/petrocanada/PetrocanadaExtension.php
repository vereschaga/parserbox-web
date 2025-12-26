<?php

namespace AwardWallet\Engine\petrocanada;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use function AwardWallet\ExtensionWorker\beautifulName;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class PetrocanadaExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.petro-canada.ca/en/personal/my-petro-points';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $captchaElm = $tab->evaluate("//h1[contains(@class, 'zone-name-title') and normalize-space()='www.petro-canada.ca']",
        EvaluateOptions::new()
            ->visible(true)
            ->allowNull(true)
            ->timeout(5));

        if ($captchaElm) {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
        }

        $element = $tab->evaluate('//span[contains(@class, "user-info") and contains(@class, "card-number")] 
        | //input[@id="email"]
        | //p[contains(text(),"Verify you are human by completing the action below.")]',
            EvaluateOptions::new()->timeout(90));

        return $element->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[contains(@class, "user-info") and contains(@class, "card-number")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $element = $tab->evaluate('//input[@id="email"]
        | //p[contains(text(),"Verify you are human by completing the action below.")]');

        if (stristr($element->getInnerText(), 'Verify you are human by completing the action below.')) {
            $tab->showMessage('In order to log in to this account, you need to solve the CAPTCHA. Once logged in, sit back and relax, we will do the rest.');
            $element = $tab->evaluate('//input[@id="email"]',
                EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$element) {
                return LoginResult::identifyComputer();
            }
        }

        $tab->evaluate('//input[@id="email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="password"]')->setValue($credentials->getPassword());

        sleep(1); //because is not working

        $submit = $tab->evaluate('//button[contains(@class, "sign-in") and contains(@class, "button")]');
        $submit->setProperty("disabled", false);
        $submit->setProperty('className', "sign-in__button button button--full button--primary");
        $submit->click();

        $submitResult = $tab->evaluate('//span[contains(@class, "user-info") and contains(@class, "card-number")] | //p[@class="help-text"] | //div[contains(@class, "form-error")]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'P') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "invalid.recaptcha")
            ) {
                return new LoginResult(false, 'The website is experiencing technical difficulties, please try to check your balance at a later time.', null, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($error, "Sorry, an error occurred on our servers.  Our tech team has been notified.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($error, "The email or password you entered is not correct. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@data-action="/api/petrocanadaaccounts/signout"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//a[@href="/en/personal/login"]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $this->logger->notice(__METHOD__);
        $statement = $master->createStatement();
        // Name
        $statement->addProperty("Name", beautifulName($tab->evaluate("//p[@class = 'account-well__name'] | //div[@class = 'user-info__full-name']")->getInnerText()));
        // Account #
        $statement->addProperty("Number", $tab->evaluate("//span[(@class = 'user-info__card-number')]")->getInnerText());
        // Balance - Petro-Points
        $statement->setBalance($tab->evaluate("//p[contains(text(), 'You have')]/following-sibling::strong[1]")->getInnerText());
    }
}
