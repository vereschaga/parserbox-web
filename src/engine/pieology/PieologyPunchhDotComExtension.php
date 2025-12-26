<?php

namespace AwardWallet\Engine\pieology;

use AwardWallet\Common\Parsing\Exception\AcceptTermsException;
use AwardWallet\Common\Parsing\Exception\ProfileUpdateException;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class PieologyPunchhDotComExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;
    public $code = "pieology";
    private $name;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://iframe.punchh.com/customers/sign_in.iframe?slug=' . $this->code;
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//input[@id="user_email"] | //strong[contains(text(), "You are already signed in.")]');

        return $el->getNodeName() == 'STRONG';
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://iframe.punchh.com/customers/edit.iframe?slug=' . $this->code);

        return $tab->evaluate('//input[@id="user_email"]')->getAttribute('value');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="user_email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="user_password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@value="Login" or @value="Log In"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "alert-message")]/p/strong');

        if (strstr($submitResult->getInnerText(), "Captcha verification failed")) {
            $login = $tab->evaluate('//input[@id="user_email"]');
            $login->setValue($credentials->getLogin());

            $password = $tab->evaluate('//input[@id="user_password"]');
            $password->setValue($credentials->getPassword());

            $tab->showMessage(Message::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//div[contains(@class, "alert-message")]/p/strong[not(contains(text(), "Captcha verification failed"))]');
        }

        if (strstr($submitResult->getInnerText(), "Incorrect information submitted. Please retry.")) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        if (strstr($submitResult->getInnerText(), "Signed in successfully.")) {
            return new LoginResult(true);
        }

        if (strstr($submitResult->getInnerText(), "Please agree on given terms and conditions")) {
            throw new AcceptTermsException();
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('/customers/sign_out.iframe?slug=' . $this->code);
        $tab->evaluate('//strong[contains(text(), "Signed out successfully.")]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();

        if (
            $tab->getUrl() != 'https://iframe.punchh.com/customers/edit.iframe?slug=' . $this->code . '/secure_migration'
            && $tab->getUrl() != 'https://iframe.punchh.com/whitelabel/' . $this->code
        ) {
            $tab->gotoUrl('https://iframe.punchh.com/whitelabel/' . $this->code);
        }

        if (in_array($this->code, ["cicispizza"])) {
            // Balance - Current Card ... Slices
            $balance = $tab->findText('//div[@class="iframe-container"]//span[@class="current-checkins"]', FindTextOptions::new()->timeout(10)->allowNull(true)->preg("/^(\d+)\s*(?:Slice|Punch)/"));

            if (isset($balance)) {
                $statement->setBalance($balance);
            }
            // Available Redeemable cards / Available small combo meals
            $availableCards = $tab->findText('//div[@class="iframe-container"]//span[@class = "current-redeemable-card"]', FindTextOptions::new()->timeout(10)->allowNull(true));

            if (isset($availableCards)) {
                $statement->addProperty('AvailableCards', $availableCards);
            }
            // ... more to go to fill up card / ... more punches to go - Slices to Fill Up Card
            $toNextReward = $tab->findText('//div[@class="iframe-container"]//span[@class = "current-checkins-left"]', FindTextOptions::new()->timeout(10)->allowNull(true));

            if (isset($toNextReward)) {
                $statement->addProperty('ToNextReward', $toNextReward);
            }
        } else {
            // Balance - Current Points
            $balance = $tab->findText('//div[@class="iframe-container"]//span[@class="current-points"]', FindTextOptions::new()->timeout(10)->allowNull(true));

            if (isset($balance)) {
                $statement->setBalance($balance);
            }
        }
        // Membership Level
        if (in_array($this->code, ["freebirds", "coffeebean", "moes", "saladworks", "vinovolo", "tucanos", "condadotacos"])) {
            $tier = $tab->findText('//div[@class="iframe-container"]//span[@class="membership-level"]', FindTextOptions::new()->timeout(10)->allowNull(true));

            if (isset($tier)) {
                $statement->addProperty('Tier', $tier);
            }
        }
        // Banked Rewards
        if (in_array($this->code, [
            "maxnermas",
            "lunagrill",
            "piefive",
            "moes",
            "saladworks",
            "beefsteak",
            "tucanos",
            "eploco",
            "pollotropical",
            "grubburger",
            "condadotacos",
            "graeters",
            "jimmyjohns",
            "bibibop",
        ])) {
            $bankedRewards = $tab->findText("//span[@class='banked-rewards']", FindTextOptions::new()->timeout(10)->allowNull(true));

            if (isset($bankedRewards)) {
                $statement->addProperty('BankedRewards', $bankedRewards);
            }
        }

        if (in_array($this->code, [
            "eploco",
        ])) {
            $level = $tab->findText("//span[@class='membership-level']", FindTextOptions::new()->timeout(10)->allowNull(true));

            if (isset($level)) {
                $statement->addProperty('Level', $level);
            }
        }
        // Name
        if ($tab->getUrl() != 'https://iframe.punchh.com/customers/edit.iframe?slug=' . $this->code) {
            $tab->gotoUrl('https://iframe.punchh.com/customers/edit.iframe?slug=' . $this->code);
        }
        $name = beautifulName(trim($tab->findText('//input[@id="user_first_name"]/@value', FindTextOptions::new()->timeout(10)->allowNull(true)) . ' ' . $tab->findText('//input[@id="user_last_name"]/@value', FindTextOptions::new()->allowNull(true))));

        if (isset($name)) {
            $statement->addProperty('Name', $name);
        } else {
        }
        // Membership Card Number
        if (in_array($this->code, ["maxnermas"])) {
            $card = $tab->findText("//input[@id='user_card_number']/@value", FindTextOptions::new()->timeout(10)->allowNull(true));

            if (isset($card)) {
                $statement->addProperty('Card', $card);
            }
        }

        /*
        $this->http->GetURL('https://iframe.punchh.com/whitelabel/'.$this->code.'/gift_cards.iframe');
        */

        // Rewards
        $tab->gotoUrl('https://iframe.punchh.com/whitelabel/' . $this->code . '/offers');
        $tab->evaluate('//label[@for="redemption_reward_id"]', EvaluateOptions::new()->timeout(10)->allowNull(true));
        $rewards = $tab->evaluateAll("//select[@id = 'redemption_reward_id']/option", EvaluateOptions::new()->visible(false));
        $rewardsCount = count($rewards);
        $this->logger->debug("Total {$rewardsCount} rewards were found");
        $statement->addProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            if (!empty($reward->getAttribute('data-expiry_date'))) {
                $this->logger->debug("Exp v.1: " . $reward->getAttribute('data-expiry_date'));
                $exp = strtotime($reward->getAttribute('data-expiry_date'), false);
            } elseif (!empty($reward->getAttribute('end_date')) && !stristr($reward->getInnerText(), '(Never Expires)')) {
                $this->logger->debug("Exp v.2: " . $reward->getAttribute('end_date'));
                $exp = strtotime(str_replace(',', '', $reward->getAttribute('end_date')), false);
            } else {
                $exp = false;
            }
            $this->logger->debug("Exp: " . $exp);
            $displayName = Html::cleanXMLValue($reward->getInnerText());
            $this->logger->debug("[displayName]: {$displayName}");

            if (strstr($displayName, '(Never Expires)')) {
                $displayName = preg_replace('/\s*\(Never Expires\)/ims', '', $displayName);
            }

            if (strstr($displayName, '(Expires on: ')) {
                // refs #21640
                $this->logger->debug("set exp date from displayName");
                $exp = strtotime($this->findPreg('/\s*\(Expires on:([^)]+)\)/ims', $displayName), false);

                $displayName = preg_replace('/\s*\(Expires on:[^)]+\)/ims', '', $displayName);
            }
            $statement->AddSubAccount([
                'Code'           => $this->code . 'Reward' . md5($displayName . $exp),
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => $exp,
            ]);
        }// foreach ($rewards as $reward)

        $this->parseExtendedProperties($tab);

        if ($tab->evaluate('//strong[
            contains(text(), "Birthday can\'t be blank.")
            or contains(text(), "Please enter a valid phone number.")
            ]', EvaluateOptions::new()->allowNull(true))
            && $tab->getUrl() == 'https://iframe.punchh.com/customers/edit.iframe?slug=' . $this->code
            && !empty($statement->getProperties()['Name'])
        ) {
            throw new ProfileUpdateException();
        }

        if ($tab->evaluate('//strong[
                contains(text(), "Please agree on given terms and conditions.")
                or contains(text(), "Please select your Favorite Location")
            ]', EvaluateOptions::new()->allowNull(true))
            && $tab->getUrl() == 'https://iframe.punchh.com/customers/edit.iframe?slug=' . $this->code
            && !empty($statement->getProperties()['Name'])
        ) {
            throw new AcceptTermsException();
        }
    }

    public function parseExtendedProperties(Tab $tab)
    {
        $this->logger->notice(__METHOD__);
    }
}
