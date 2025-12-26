<?php

namespace AwardWallet\Engine\modpizza;

use AwardWallet\Engine\pieology\PieologyPunchhDotComExtension;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\Tab;

class ModpizzaExtension extends PieologyPunchhDotComExtension
{
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $code = "modpizza";

    public function parseExtendedProperties(Tab $tab)
    {
        $this->logger->notice(__METHOD__);

        if (
            $tab->evaluate('//strong[contains(text(), "Please agree on given terms and conditions.")]', EvaluateOptions::new()->timeout(10)->allowNull(true))
        ) {
            throw new \CheckException("In order to get the account updated please accept terms and conditions here: https://iframe.punchh.com/whitelabel/modpizza.", ACCOUNT_PROVIDER_ERROR);
        }
    }
}
