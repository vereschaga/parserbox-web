<?php

namespace AwardWallet\Engine\korean;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class KoreanExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    private $login2;

    public function getStartingUrl(AccountOptions $options): string
    {
        $this->login2 = $options->login2;

        return 'https://www.koreanair.com/login';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->querySelector('kc-global-header')->shadowRoot()->querySelector('kc-header-my, kc-button');

        return strstr($el->getNodeName(), "KC-HEADER-MY");
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->querySelector('kc-global-header')->shadowRoot()->querySelector('kc-header-my')->shadowRoot()->querySelector('#my-panel-btn')->click();
        $el = $tab->querySelector('kc-global-header')->shadowRoot()->querySelector('kc-header-my')->shadowRoot()->querySelector('p.mygroup__number');

        return $this->findPreg('/\d+\s\d+\s\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        if (isset($this->login2) && $this->login2 == 'sky') {
            $tab->evaluate('//button[@id="tab_1"]')->click();
        }

        if (isset($this->login2) && $this->login2 == 'uid') {
            $tab->evaluate('//button[@id="tab_0"]')->click();
        }

        $login = $tab->evaluate('//ke-text-input[@formcontrolname="userId"]//input');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//ke-password-input[@formcontrolname="password"]//input');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-click-name="Log-in"]')->click();

        $submitResult = $tab->evaluate('//button[@id="mainTabResvLogin"] | //em[@class="remark -negative ng-star-inserted"]');

        if ($submitResult->getNodeName() == 'BUTTON') {
            sleep(2); // prevent incorrect click on userbar

            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "No matching member information. Please check ID or password.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "Your request has not been processed successfully. If the problem persists, please contact the Service Center")) {
                $tab->evaluate('//button[@data-click-name="Log-in"]')->click();
                $submitResult = $tab->evaluate('//button[@id="mainTabResvLogin"] | //em[@class="remark -negative ng-star-inserted"]');
                $error = $submitResult->getInnerText();

                if (strstr($error, "Your request has not been processed successfully. If the problem persists, please contact the Service Center")) {
                    return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
                }
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->querySelector('kc-global-header')->shadowRoot()->querySelector('kc-lang-selector')->shadowRoot()->querySelector('button.ux-lang__util-link.-logout')->click();
        $tab->querySelector('kc-global-header')->shadowRoot()->querySelector('kc-button');
    }
}
