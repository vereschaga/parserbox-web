<?php

namespace AwardWallet\Engine\westjet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CheckInReminder extends \TAccountChecker
{
    public $mailFiles = "westjet/it-21738152.eml, westjet/it-22337046.eml, westjet/it-48616590.eml, westjet/it-48679380.eml";

    public static $dictionary = [
        "en" => [
            'Hello ' => ['Hello '],
            //			'Reservation code:' => '',
            //			'WestJet Rewards ID:' => '',
            'Flight to ' => ['Flight to', 'Flight '],
            //			'Depart:' => '',
            //			'Arrive:' => '',
            //			'Terminal:' => '',
            //			'Flight:' => '',
            //			'Duration:' => '',
            //			'Operated by:' => '',
            //			'Aircraft type:' => '',
        ],
        "fr" => [
            'Hello '            => 'Bonjour ',
            'Reservation code:' => 'Code de réservation :',
            //			'WestJet Rewards ID:' => '',
            'Flight to '     => 'Vol pour ',
            'Depart:'        => 'Départ :',
            'Arrive:'        => 'Arrivée :',
            'Terminal:'      => 'Aérogare :',
            'Flight:'        => 'Vol :',
            'Duration:'      => 'Durée :',
            'Operated by:'   => 'Opéré par :',
            'Aircraft type:' => 'Type d’avion :',
        ],
    ];

    private $detectFrom = 'noreply@notifications.westjet.com';

    private $detectSubject = [
        "en"  => "Your trip starts here. Let's do this.",
        "en2" => "Pre-purchase your meal now",
        "en3" => "Your check-in reminder",
        "en4" => "Your special request",
        "en5" => "You are eligible for a special upgrade",
        "en6" => "Upgrade your seat for more space",
        "fr"  => "Votre voyage commence ici. Allez-y.",
        "fr2" => "Achetez votre repas à l'avance dès maintenant",
        "fr3" => "Votre rappel d'enregistrement",
    ];

    private $detectCompany = 'from WestJet';

    private $detectBody = [
        "en" => ["Flight to ", 'You can now place an offer to upgrade for your upcoming flight.', "This is your opportunity to upgrade"],
        "fr" => "Vol pour ",
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $reBody) {
            if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($parser->getHTMLBody());

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $reBody) {
            if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation code:")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#"))
        ;
        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hello ")) . "][1]", null, true, "#^" . $this->preg_implode($this->t("Hello ")) . "(.+?)[.,]#"); //#^\s*(?:Hello |Hello, )\s*(.+?)[^\w\s]#

        if (!empty($traveller)) {
            $f->general()->traveller($traveller, false);
        }

        $account = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("WestJet Rewards ID:")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (!empty($account)) {
            $f->program()->account($account, false);
        }

        $xpath = "//text()[" . $this->starts($this->t("Flight to ")) . "][1]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $air = $s->airline();

            $airName = $this->http->FindSingleNode("./ancestor::td[1]/following-sibling::td//text()[" . $this->starts($this->t("Flight:")) . "]/following::text()[normalize-space(.)][1][not(./ancestor::b)]", $root, true, "#^\s*([A-Z\d]{2})\s*\d+\s*$#");

            if (empty($airName)) {
                $airName = $this->http->FindSingleNode("./following-sibling::tr//text()[" . $this->starts($this->t("Flight:")) . "]/following::text()[normalize-space(.)][1][not(./ancestor::b)]", $root, true, "#^\s*([A-Z\d]{2})\s*\d+\s*$#");

                if (empty($airName)) {
                    $airName = $this->http->FindSingleNode(".", $root, true, '#' . $this->opt($this->t("Flight to ")) . '([A-Z\d]{2})\s*\d+\s#');
                }
            }

            if (!empty($airName)) {
                $air->name($airName);
            }

            $airNumber = $this->http->FindSingleNode("./ancestor::td[1]/following-sibling::td//text()[" . $this->starts($this->t("Flight:")) . "]/following::text()[normalize-space(.)][1][not(./ancestor::b)]", $root, true, "#^\s*[A-Z\d]{2}\s*(\d+)\s*$#");

            if (empty($airNumber)) {
                $airNumber = $this->http->FindSingleNode("./following-sibling::tr//text()[" . $this->starts($this->t("Flight:")) . "]/following::text()[normalize-space(.)][1][not(./ancestor::b)]", $root, true, "#^\s*[A-Z\d]{2}\s*(\d+)\s*$#");

                if (empty($airNumber)) {
                    $airNumber = $this->http->FindSingleNode(".", $root, true, '#' . $this->opt($this->t("Flight to ")) . '[A-Z\d]{2}\s(\d+)\s#');
                }
            }

            if (!empty($airNumber)) {
                $air->number($airNumber);
            }

            $airOperator = $this->http->FindSingleNode("./ancestor::td[1]/following-sibling::td//text()[" . $this->starts($this->t("Operated by:")) . "]/following::text()[normalize-space(.)][1][not(./ancestor::b)]", $root, true, "#(.+?)( DBA |$)#");

            if (empty($airOperator)) {
                $airOperator = $this->http->FindSingleNode("./following-sibling::tr//text()[" . $this->starts($this->t("Operated by:")) . "]/following::text()[normalize-space(.)][1][not(./ancestor::b)]", $root, true, "#(.+?)( DBA |$)#");
            }

            if (!empty($airOperator)) {
                $air->operator($airOperator, true, true);
            }

            // Departure
            $dep = $s->departure();

            $depCode = $this->http->FindSingleNode("(./following-sibling::tr[1][count(.//text()[normalize-space()]) = 2]//text()[normalize-space()])[1]",
                $root, true, "#^\s*([A-Z]{3})\s*$#");

            if (empty($depCode)) {
                $depCode = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[3]",
                    $root); //5
            }

            if (!empty($depCode)) {
                $dep->code($depCode);
            }

            $depName = $this->http->FindSingleNode("./following-sibling::tr[2]/td[1]", $root, true,
                "#(.+?)\s*" . $this->preg_implode($this->t("Depart:")) . "#");

            if (empty($depName)) {
                $depName = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[6]",
                    $root, true, "#(.+?)\s*" . $this->preg_implode($this->t("Depart:")) . "#");
            }

            if (!empty($depName)) {
                $dep->name($depName);
            }

            $depTerminal = $this->http->FindSingleNode("./following-sibling::tr[2]/td[1]", $root, true,
                "#" . $this->preg_implode($this->t("Terminal:")) . "\s*(.+)#");

            if (empty($depTerminal)) {
                $depTerminal = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[6]", $root, true,
                    "#" . $this->preg_implode($this->t("Terminal:")) . "\s*(.+)#");
            }

            if (!empty($depTerminal)) {
                $dep->terminal($depTerminal, true, true);
            }

            $depDate = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[2]/td[1]", $root, true,
                    "#" . $this->preg_implode($this->t("Depart:")) . "(.+?)(?:" . $this->preg_implode($this->t("Terminal:")) . "|$)#"));

            if (empty($depDate)) {
                $depDate = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[6]", $root, true,
                    "#" . $this->preg_implode($this->t("Depart:")) . "(.+?)(?:" . $this->preg_implode($this->t("Terminal:")) . "|$)#"));
            }

            if (!empty($depDate)) {
                $dep->date($depDate);
            }

            //Arrival
            $arr = $s->arrival();

            $arrCode = $this->http->FindSingleNode("(./following-sibling::tr[1][count(.//text()[normalize-space()]) = 2]//text()[normalize-space()])[last()]",
                    $root, true, "#^\s*([A-Z]{3})\s*$#");

            if (empty($arrCode)) {
                $arrCode = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[5]",
                    $root);
            }

            if (!empty($arrCode)) {
                $arr->code($arrCode);
            }

            $arrName = $this->http->FindSingleNode("./following-sibling::tr[2]/td[2]", $root, true,
                    "#(.+?)\s*" . $this->preg_implode($this->t("Arrive:")) . "#");

            if (empty($arrName)) {
                $arrName = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[7]",
                    $root, true, "#(.+?)\s*" . $this->preg_implode($this->t("Arrive:")) . "#");
            }

            if (!empty($arrName)) {
                $arr->name($arrName);
            }

            $arrTerminal = $this->http->FindSingleNode("./following-sibling::tr[2]/td[2]", $root, true,
                    "#" . $this->preg_implode($this->t("Terminal:")) . "\s*(.+)#");

            if (empty($arrTerminal)) {
                $arrTerminal = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[7]", $root, true,
                    "#" . $this->preg_implode($this->t("Terminal:")) . "\s*(.+)#");
            }

            if (!empty($arrTerminal)) {
                $arr->terminal($arrTerminal, true, true);
            }

            $arrDate = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[2]/td[2]", $root, true,
                    "#" . $this->preg_implode($this->t("Arrive:")) . "(.+?)(?:" . $this->preg_implode($this->t("Terminal:")) . "|$)#"));

            if (empty($arrDate)) {
                $arrDate = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[7]", $root, true,
                    "#" . $this->preg_implode($this->t("Arrive:")) . "(.+?)(?:" . $this->preg_implode($this->t("Terminal:")) . "|$)#"));
            }

            if (!empty($arrDate)) {
                $arr->date($arrDate);
            }

            // Extra
            $extra = $s->extra();

            $duration = $this->http->FindSingleNode("./ancestor::td[1]/following-sibling::td//text()[" . $this->starts($this->t("Duration:")) . "]/following::text()[normalize-space(.)][1][not(./ancestor::b)]",
                $root);

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode("./following-sibling::tr//text()[" . $this->starts($this->t("Duration:")) . "]/following::text()[normalize-space(.)][1][not(./ancestor::b)]", $root);
            }

            if (!empty($duration)) {
                $extra->duration($duration, true, true);
            }

            $aircraft = $this->http->FindSingleNode("./ancestor::td[1]/following-sibling::td//text()[" . $this->starts($this->t("Aircraft type:")) . "]/following::text()[normalize-space(.)][1][not(./ancestor::b)]",
                $root);

            if (empty($aircraft)) {
                $aircraft = $this->http->FindSingleNode("./following-sibling::tr//text()[" . $this->starts($this->t("Aircraft type:")) . "]/following::text()[normalize-space(.)][1][not(./ancestor::b)]", $root);
            }

            if (!empty($aircraft)) {
                $extra->aircraft($aircraft, true, true);
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        if (empty($str)) {
            return false;
        }

        $in = [
            "#^\s*(\d+:\d+(?:\s*[AP]M)?)\s*([^\d\s\.]+)\s+(\d+),\s*(\d{4})\s*$#", // 7:44 AM August 19, 2018
            "#^\s*(\d+:\d+(?:\s*[AP]M)?)\s*(\d+)\s+([^\d\s\.]+)\s+(\d{4})\s*$#", // 09:00 08 novembre 2018
        ];
        $out = [
            "$3 $2 $4 $1",
            "$2 $3 $4 $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
