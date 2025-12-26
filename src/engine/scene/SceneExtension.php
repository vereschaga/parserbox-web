<?php

namespace AwardWallet\Engine\scene;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class SceneExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.sceneplus.ca/";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//button[span[contains(text(),"Sign in")]] | //button[@aria-label="Account"]', EvaluateOptions::new()->visible(false));

        return str_contains($result->getAttribute('aria-label'), 'Account');
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//button[@aria-label="Account" and text()]');
        sleep(3);

        return $tab->findTextNullable('//button[@aria-label="Account" and text()]',
            FindTextOptions::new()->visible(false)->preg('/\w+/i'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//button[@aria-label="Account"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//button[@aria-label="Sign out"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//button[span[contains(text(),"Sign in")]]', EvaluateOptions::new()->visible(false));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//button[span[contains(text(),"Sign in")]]', EvaluateOptions::new()->visible(false))->click();
        sleep(1);
        $tab->evaluate('//input[@name="Sign in name"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="Password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@id="next"]')->click();

        $errorOrSuccess = $tab->evaluate('//div[@class="error pageLevel"] | //h2[contains(text(),"Check your phone…")] | //button[@aria-label="Account" and text()]');

        if (str_contains($errorOrSuccess->getInnerText(), 'Good ')) {
            return new LoginResult(true);
        }

        // Check your phone… Where should we send your 2-step verification code?
        if (str_contains($errorOrSuccess->getInnerText(), 'Check your phone…')) {
            $this->logger->notice('Waiting 90 seconds for the passage of 2fa ');
            $tab->showMessage(tab::MESSAGE_IDENTIFY_COMPUTER);
            $errorOrSuccess = $tab->findTextNullable('//button[@aria-label="Account" and text()]',
                FindTextOptions::new()->timeout(180)->allowNull(true));

            if ($errorOrSuccess) {
                return new LoginResult(true);
            } else {
                return LoginResult::identifyComputer();
            }
        }

        if (str_contains($errorOrSuccess->getInnerText(),
            'Please check your Scene+ number or password and ensure you have registered your card.')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();

        $this->waitFor(function (Tab $tab) {
            return !$tab->evaluate('//*[@id="mainContent"]//span[contains(text(),"Loading")]',
                EvaluateOptions::new()->allowNull(true)->visible(false)->timeout(0));
        }, $tab);

        if ($tab->evaluate('//*[@id="mainContent"]//span[contains(text(),"Loading")]', EvaluateOptions::new()->timeout(1)->visible(false)->allowNull(true))) {
            $tab->saveScreenshot();
            $this->logger->error('Api not loaded');

            return;
        }

        // Name
        $name = $tab->findTextNullable('//button[@aria-label="Account" and text()]',
            FindTextOptions::new()->visible(false)->preg('/\w+/i'));

        if (!empty($name)) {
            $st->addProperty('Name', beautifulName($name));
        }
        // Balance - PTS
        $balance = $tab->findTextNullable('//a[@href="/points" and p[span]]',
            FindTextOptions::new()->visible(false)->preg('/([\d.,]+)/i'));

        if (!isset($balance)) {
            return;
        }

        $tab->saveScreenshot();
        $st->setBalance($balance);
    }

    public function waitFor($whileCallback, Tab $tab, $timeoutSeconds = 15)
    {
        $start = time();

        do {
            try {
                if (call_user_func($whileCallback, $tab)) {
                    return true;
                }
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
            sleep(1);
        } while ((time() - $start) < $timeoutSeconds);

        return false;
    }
}
