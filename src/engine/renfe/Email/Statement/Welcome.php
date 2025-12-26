<?php

namespace AwardWallet\Engine\renfe\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "renfe/statements/it-77258842.eml, renfe/statements/it-77366892.eml, renfe/statements/it-77427824.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@renfe.es') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".renfe.com/") or contains(@href,"www.renfe.com") or contains(@href,"venta.renfe.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"www.renfe.com") or contains(.,"@renfe.es")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//*[contains(normalize-space(),'Tu número de tarjeta +Renfe') or contains(normalize-space(),'Tu cuenta se ha creado correctamente, con el siguiente email:')]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $number = $login = null;

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'¡Bienvenido ')]", null, true, "/^¡Bienvenido\s+({$patterns['travellerName']})(?:[ ]*[!]|$)/u"); // it-77427824.eml

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Tu número de tarjeta +Renfe es:'] ]/*[normalize-space()][2]", null, true, "/^[-A-Z\d]{5,}$/"); // it-77258842.eml

        if ($number === null) {
            // it-77427824.eml
            $number = $this->http->FindSingleNode("//*[normalize-space()='Tu número de tarjeta +Renfe']/following-sibling::table[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");
        }

        if ($number !== null) {
            $st->setNumber($number)
                ->addProperty('CardNumber', $number);
        }

        $login = $this->http->FindSingleNode("//text()[normalize-space()='Tu cuenta se ha creado correctamente, con el siguiente email:']/following::text()[normalize-space()][1]", null, true, "/^\S+@\S+$/"); // it-77366892.eml

        $st->setLogin($number ?? $login);

        if ($name || $number || $login) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
