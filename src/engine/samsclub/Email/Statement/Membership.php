<?php

namespace AwardWallet\Engine\samsclub\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Membership extends \TAccountChecker
{
    public $mailFiles = "samsclub/statements/it-79203188.eml, samsclub/statements/it-79467344.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@samsclub.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], "Confirmação Filiação Sam's Club") !== false
            || $this->detectEmailFromProvider($headers['from']) === true
            && stripos($headers['subject'], 'Welcome to the Club') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//*[contains(normalize-space(),\"Confirmamos sua Filiação ao Sam's Club\") or contains(normalize-space(),\"Thank you for joining as a Sam's Club member\")]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $xpathHidden = "ancestor-or-self::*[contains(@style,'display:none') or contains(@style,'display: none')]";

        $st = $email->add()->statement();

        $name = $number = null;

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Prezado(a)') or starts-with(normalize-space(),'Dear')][not({$xpathHidden})]", null, true, "/^(?:Prezado\(a\)|Dear)\s+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u");
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//text()[normalize-space()='Número de Sócio Titular:' or normalize-space()='Your membership number is:'][not({$xpathHidden})]/following::text()[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");

        if (!$number) {
            $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Número de Sócio Titular')]", null, true, "/^Número de Sócio Titular[:\s]+([-A-Z\d]{5,})$/");
        }
        $st->setNumber($number);

        if ($name || $number) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
