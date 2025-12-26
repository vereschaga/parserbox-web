<?php

namespace AwardWallet\Engine\starlux;

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
use \AwardWallet\Common\Parser\Util\PriceHelper;

class StarluxExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.starlux-airlines.com/en-US/cosmile/my-cosmile/account-overview';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//div[@data-qa="qa-field-cosmileId"]//p | //input[contains(@id, "inputID") and not(@type="password")]');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        $loginIDElement = $tab->evaluate('//div[@data-qa="qa-field-cosmileId"]//p', EvaluateOptions::new()->nonEmptyString());
        return $loginIDElement->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[contains(@id, "inputID") and not(@type="password")]');
        sleep(1);
        $login = $tab->evaluate('//input[contains(@id, "inputID") and not(@type="password")]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[contains(@id, "inputID") and @type="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-qa="qa-btn-login"]')->click();

        $submitResult = $tab->evaluate('
            //p[@data-qa="qa-msg-errorAlert"]/span
            | //h1[contains(text(), "Identity Verification")]
            | //p[@data-qa="qa-err-id"] | //p[@data-qa="qa-err-password"]
            | //div[@data-qa="qa-field-cosmileId"]
        ', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'SPAN') {
            $message = $submitResult->getInnerText();

            if (
                strstr($message, "Google reCAPTCHA authentication failed, please contact STARLUX Customer Service Center. (99205)")
            ) {
                return new LoginResult(false, $message, null, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($message, "Invalid account or password, please try again.(04303_0755)")
            ) {
                return new LoginResult(false, $message, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $message);
        } elseif ($submitResult->getNodeName() == 'H1') {
            $questionElement = $tab->evaluate('//p[contains(@data-i18n-text, "CW_member")]');
            $question = $questionElement->getInnerText();
            $tab->showMessage($question);

            $result = $tab->evaluate('//div[@data-qa="qa-field-cosmileId"]//p', EvaluateOptions::new()->allowNull(true)->timeout(180));
            if (!$result) {
                return LoginResult::identifyComputer();
            }
            return new LoginResult(true);       
        } elseif ($submitResult->getNodeName() == 'P') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $openDialogButton = $tab->evaluate('//button[@data-qa="qa-btn-openDialog"]');
        $openDialogButton->click();
        $logoutButton = $tab->evaluate('//button[@data-qa="qa-btn-logout"]');
        $logoutButton->click();
        $tab->evaluate('//input[contains(@id, "inputID") and not(@type="password")]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        $loginIDElement = $tab->evaluate('//div[@data-qa="qa-field-cosmileId"]//p', EvaluateOptions::new()->nonEmptyString());
        // Name
        $statement->addProperty('AccountNumber', $loginIDElement->getInnerText());
        $name = $tab->findText('//span[@data-qa="qa-lbl-memberName"]', FindTextOptions::new()->nonEmptyString()->preg('/Hi,\s(.*)/i'));
        // COSMILE Member ID
        $statement->addProperty('Name', beautifulName($name));
        $statusElement = $tab->evaluate('//div[@data-qa="qa-field-cardLevel"]/p/span', EvaluateOptions::new()->nonEmptyString());
        // Status
        $statement->addProperty('Status', beautifulName($statusElement->getInnerText()));
        $qualifyingSectorsElement = $tab->evaluate('//span[@data-qa="qa-lbl-qualifyingSectors"]');
        // Qualifying Sectors
        $statement->addProperty('Sectors', $qualifyingSectorsElement->getInnerText());
        $qualifyingMilesElement = $tab->evaluate('//span[@data-qa="qa-lbl-qualifyingMiles"]');
        // Tier Miles
        $statement->addProperty('TierMiles', PriceHelper::parse($qualifyingMilesElement->getInnerText()));
        $balanceElement = $tab->evaluate('//span[@data-qa="qa-lbl-awardMilesd"]');
        // Current Valid Award Mileage
        $statement->setBalance(PriceHelper::parse($balanceElement->getInnerText()));
    }
}