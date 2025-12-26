<?php

namespace AwardWallet\Engine\pegasus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// it-4290903.eml, it-4320643.eml

class It2606748 extends \TAccountChecker
{
    public $mailFiles = "pegasus/it-2606748.eml, pegasus/it-2655879.eml, pegasus/it-2680150.eml, pegasus/it-2680151.eml, pegasus/it-4290903.eml, pegasus/it-5388450.eml";

    public $reSubject = [
        "Online Ticket Reservation from Pegasus Airlines",
        "Pegasus Havayollari Bilet Bilginiz",
        "Confirmation de Pegasus Airlines pour les sièges",
    ];

    public $reBody = [
        "We wish you a pleasant flight. Don't forget to have a look at your flight details",
        "Pegasus Ailesi olarak iyi uçuşlar dileriz. Uçuş detaylarına ve senin için",
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Departure'],
        'tr' => ['Kalkış'],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "tr" => [
            'Reservation' => 'Rezervasyon',
            'Passenger'   => 'Yolcu',
            'Flight No'   => 'Uçuş No',
            'Departure'   => 'Kalkış',
            'Arrival'     => 'Varış',
            'Duration:'   => 'Süre:',
            'Seat'        => 'Koltuk',
            'Meal'        => 'Yemek',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->assignLang() == true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Pegasus')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Departure'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Passenger'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flypgs\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation'))}]/following::text()[normalize-space()][1]"))
            ->travellers(array_unique($this->http->FindNodes("//*[{$this->eq($this->t('Passenger'))}]/ancestor::tr[1]/following-sibling::tr/td[1]")));

        $xpath = "//text()[{$this->eq($this->t('Departure'))}]/ancestor::table[3]";
        //$this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight No'))}]/following::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]{2})/"))
                ->number($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight No'))}]/following::text()[normalize-space()][1]", $root, true, "/^[A-Z\d]{2}(\d{2,4})/"));

            $depDate = str_replace('/', '.', $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure'))}]/following::text()[normalize-space()][1]", $root));
            $depTime = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]/following::tr[contains(normalize-space(), ':')][1]/descendant::td[normalize-space()][1]", $root);

            $arrDate = str_replace('/', '.', $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][last()]", $root));
            $arrTime = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]/following::tr[contains(normalize-space(), ':')][1]/descendant::td[normalize-space()][last()]", $root);

            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure'))}]/preceding::img[1]/preceding::text()[normalize-space()][1]", $root))
                ->noCode()
                ->date(strtotime($depDate . ', ' . $depTime));

            $depTerminal = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departure Terminal Info :'))}][1]", $root, true, "/{$this->opt($this->t('Departure Terminal Info :'))}\s*(.+)/");

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure'))}]/preceding::img[1]/following::text()[normalize-space()][1]", $root))
                ->noCode()
                ->date(strtotime($arrDate . ', ' . $arrTime));

            $arrTerminal = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrival Terminal Info :'))}][1]", $root, true, "/{$this->opt($this->t('Arrival Terminal Info :'))}\s*(.+)/");

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $duration = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Duration:'))}]/following::text()[normalize-space()][1]", $root);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $seats = array_filter($this->http->FindNodes(".//tr[normalize-space()][1][*[3][{$this->eq($this->t("Seat"))}]]/ancestor::table[1]//tr/*[3]",
                $root, "/^\s*(\d{1,2}[A-Z])\s*$/"));

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }

            $meals = array_filter($this->http->FindNodes(".//tr[normalize-space()][1][*[5][{$this->eq($this->t("Meal"))}]]/ancestor::table[1]//tr/*[5][not({$this->eq($this->t("Meal"))})]",
                $root, "/^\s*\S.{3,}\s*$/"));

            if (!empty($meals)) {
                $s->extra()
                    ->meals($meals);
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $reBody) {
            foreach ($reBody as $word) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$word}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
