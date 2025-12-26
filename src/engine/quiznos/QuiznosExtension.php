<?php

namespace AwardWallet\Engine\quiznos;

use AwardWallet\Common\Parsing\Exception\ProfileUpdateException;
use AwardWallet\Engine\pieology\PieologyPunchhDotComExtension;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\Tab;

class QuiznosExtension extends PieologyPunchhDotComExtension
{
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $code = "quiznos";

    public function parseExtendedProperties(Tab $tab)
    {
        $this->logger->notice(__METHOD__);

        if (
            $tab->findText('//strong[contains(text(), "Favorite Location can\'t be blank") or contains(text(), "Please enter a valid postal zip code.")]', FindTextOptions::new()->allowNull(true))
            && $tab->findText('//*[contains(., "I agree to the Quiznos")]', FindTextOptions::new()->allowNull(true)->preg('/I agree to the Quiznos/'))
            && $tab->getUrl() == 'https://iframe.punchh.com/customers/edit.iframe?slug=quiznos'
        ) {
            throw new ProfileUpdateException();
        }
    }
}
