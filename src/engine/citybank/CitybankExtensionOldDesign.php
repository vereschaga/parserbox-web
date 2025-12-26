<?php

namespace AwardWallet\Engine\citybank;

use AwardWallet\Engine\FindProxyOptions;
use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class CitybankExtensionOldDesign extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        switch ($options->login2) {
            case "Singapore":
            default:
                return "https://www.citibank.com.sg/SGGCB/#";
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="password"] | //a[@id="signoff-button"]');
        return $result->getAttribute('id') == 'signoff-button';
    }

    public function getLoginId(Tab $tab): string
    {
        $result = $tab->findText('//div[@id="welcome_msg"]', FindTextOptions::new()->preg('/!(.+)/'));
        return !empty($result) ? base64_encode($result) : '';
    }

    public function logout(Tab $tab): void
    {
        sleep(3);
        $tab->evaluate('//a[@id="signoff-button"]')->click();
        $tab->evaluate('//input[@name="password"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@name="username"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());
        //$tab->evaluate('//input[@name="remember"]')->click();
        $tab->evaluate('//a[@id="link_lkSignOn"]')->click();

        $result = $tab->evaluate('
            //a[@id="signoff-button"]
            | //div[@class="cS-errorPageContainer"] 
        ');
        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        /*
        Your log in attempt was unsuccessful.
        Your User ID or password may be incorrect. Please try again. If you are still unable to log in, try resetting your password.
        If you are still facing issues, please wait for 24 hours before trying again.
        */
        if (stristr($result->getInnerText(), "Your User ID or password may be incorrect. Please try again. If you are still unable to log in, try resetting your password.")) {
            return LoginResult::invalidPassword('Your User ID or password may be incorrect. Please try again. If you are still unable to log in, try resetting your password.');
        }

        /*
       I'm sorry, your session may have been timed out.
       Please click Citibank Online to go to Citibank Online.
       */
        if (stristr($result->getInnerText(), "I'm sorry, your session may have been timed out.")) {
            return LoginResult::invalidPassword('Your User ID or password may be incorrect. Please try again. If you are still unable to log in, try resetting your password.');
        }


        if ($result->getAttribute('id') == 'signoff-button') {
            return new LoginResult(true);
        }


        return new LoginResult(false);
    }
}
