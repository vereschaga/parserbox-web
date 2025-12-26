<?php

namespace AwardWallet\Engine\viva\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AirTickets extends \TAccountChecker
{
    public $mailFiles = "viva/it-12232103.eml, viva/it-12232115.eml, viva/it-12232118.eml, viva/it-66665466.eml, viva/it-66830427.eml";

    public $reFrom = 'support@viva.gr';
    public $reSubject = [
        "en" => ["Viva Οrder No "],
        "el" => ["Viva Παραγγελία No "],
    ];
    public $reBody = '.viva.gr';
    public $reBody2 = [
        'en' => "Trip leg",
        'el' => "Σκέλος ταξιδιού",
    ];
    public $lang = '';
    public $emailSubject;
    public $date;
    public static $dict = [
        'en' => [
            'subjectPattern' => '#Viva Οrder No\s*(?<tripN>\d{5,}):.+? on (?<date>[\d\-]+) \d+:\d+#', //Viva Οrder No 8237327202: air tickets for 1 person from Corfu to Budapest on 09-03-16 11:20
            //			'Trip leg' => '',
            //			'Check-In:' => '',
            //			'Terminal(Departure):' => '',
            //			'Departure:' => '',
            //			'Airport:' => '',
            //			'Company:' => '',
            //			'Operated by' => '', // to check
            //			'Flight:' => '',
            //			'Terminal(Arrival):' => '',
            //			'Arrival:' => '',
            //			'Name' => '',
            //			'Ticket No' => '',
        ],
        'el' => [
            'subjectPattern'       => '#Viva Παραγγελία No\s*(?<tripN>\d{5,}):.+? στις (?<date>[\d\/]+) \d+:\d+#', //Viva Παραγγελία No 3465136460: αεροπορικά εισιτήρια για 1 άτομο από Αθήνα προς Βουδαπέστη στις 27/03/16 15:50
            'Trip leg'             => 'Σκέλος ταξιδιού',
            'Check-In:'            => 'Check-In:',
            'Terminal(Departure):' => 'Terminal(Αναχώρηση):',
            'Departure:'           => 'Αναχώρηση:',
            'Airport:'             => 'Αεροδρόμιο:',
            'Company:'             => 'Εταιρία:',
            'Operated by'          => 'με αεροσκάφος της',
            'Flight:'              => 'Αριθμός πτήσης:',
            'Terminal(Arrival):'   => 'Terminal(Άφιξη):',
            'Arrival:'             => 'Άφιξη:',
            'Name'                 => 'Όνομα',
            'Ticket No'            => 'Αρ. Εισιτηρίου',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->emailSubject = $parser->getSubject();
        $this->date = strtotime($parser->getHeader('date'));
        $body = $this->http->Response['body'];
        $this->assignLang($body);
        $this->parseFlight($email);
        $this->parseFerry($email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if ($this->http->XPath->query("//a[" . $this->contains($this->reBody, '@href') . "]")->length > 0) {
            return $this->assignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $subject) {
                if (stripos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 2;
    }

    private function parseFerry(Email $email)
    {
        $this->logger->notice(__METHOD__);
        $xpath = "//text()[{$this->eq($this->t('Trip leg'))}]/ancestor::tr[1]/following-sibling::tr[td[{$this->eq($this->t('Vessel:'))}]]/preceding-sibling::tr[td[{$this->eq($this->t('Trip leg'))}]]";
        $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            return;
        }

        $f = $email->add()->ferry();
        $f->general()->noConfirmation();

        if (!empty($this->emailSubject) && preg_match($this->t("subjectPattern"), $this->emailSubject, $m)) {
            $f->ota()->confirmation($m['tripN']);
            $date = $this->normalizeDate($m['date']);

            if (!empty($date)) {
                $this->date = $date;
            }
        }
        $f->general()->travellers($this->http->FindNodes("//text()[{$this->contains($this->t("Dear"))}]/following-sibling::strong[1]"));

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#^\s*(.+?)\s*\(([A-Z]{3})\)\s*-->\s*(.+?)\s*\(([A-Z]{3})\)\s*$#", $node, $m)) {
                $s->departure()->name("{$m[1]} ({$m[2]})");
                $m[3] = trim($m[3]);

                if ($m[3] == 'Piraeus' && $m[4] == 'MLO') {
                    $m[4] = 'PIR';
                }
                $s->arrival()->name("{$m[3]} ({$m[4]})");
            }
            $date = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root);
            $s->departure()->date($this->normalizeDate($date));
            $s->arrival()->noDate();

            $s->extra()->carrier($this->http->FindSingleNode("./following-sibling::tr[td[{$this->eq($this->t('Company:'))}]]/td[2]", $root));
            $name = $this->http->FindSingleNode("./following-sibling::tr[td[{$this->eq($this->t('Vessel:'))}]]/td[2]", $root);
            $num = $this->http->FindSingleNode("./following-sibling::tr[td[{$this->eq($this->t('Ferry operator Code:'))}]]/td[2]", $root);
            $s->extra()->vessel("{$name} / {$num}");
        }
    }

    private function parseFlight(Email $email)
    {
        $this->logger->notice(__METHOD__);
        $xpath = "//text()[{$this->eq($this->t('Trip leg'))}]/ancestor::tr[1]/following-sibling::tr[td[{$this->eq($this->t('Airport:'))}]]/preceding-sibling::tr[td[{$this->eq($this->t('Trip leg'))}]]";
        $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            return;
        }

        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        if (!empty($this->emailSubject) && preg_match($this->t("subjectPattern"), $this->emailSubject, $m)) {
            $f->ota()->confirmation($m['tripN']);
            $date = $this->normalizeDate($m['date']);

            if (!empty($date)) {
                $this->date = $date;
            }
        }
        $f->general()->travellers($this->http->FindNodes("//text()[" . $this->eq($this->t("Name")) . "]/ancestor::tr[1][" . $this->contains($this->t("Ticket No")) . "]/following-sibling::tr/td[1]"));
        $f->issued()->tickets($this->http->FindNodes("//text()[" . $this->eq($this->t("Name")) . "]/ancestor::tr[1][" . $this->contains($this->t("Ticket No")) . "]/following-sibling::tr/td[2]", null, "#^\s*([\d\- ]+)\s*$#"), false);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $conf = $this->http->FindSingleNode("(./following-sibling::tr[.//text()[{$this->eq($this->t('Check-In:'))}]]/td[2])[1]",
                $root, true, "#^\s*([A-Z\d]{5,})\b#");

            if (!empty($conf)) {
                $confTemp = $conf;
            }

            if (!empty($confTemp)) {
                $s->setConfirmation($confTemp);
            }

            $count = count($this->http->FindNodes("(./following-sibling::tr[.//text()[(" . $this->eq($this->t("Trip leg")) . ")] or (position() = last())])[1]/preceding-sibling::tr", $root))
                    - count($this->http->FindNodes("./preceding-sibling::tr", $root));

            $s->airline()->number($this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Flight:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#^\s*(\d+)\s*$#"));
            $node = $this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Company:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#^\s*(.+)\s*$#");

            if (preg_match("#^(.+?)(?:\(\s*(?:" . $this->preg_implode($this->t("Operated by")) . ")\s*(.+)\))?$#", $node, $m)) {
                $s->airline()->name(trim($m[1]));

                if (!empty($m[2])) {
                    $s->airline()->operator($m[2]);
                }
            }

            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#^\s*(.+?)\(([A-Z]{3})\)\s*-->\s*(.+?)\(([A-Z]{3})\)\s*$#", $node, $m)) {
                $s->departure()->name(trim($m[1]));
                $s->departure()->code($m[2]);
                $s->arrival()->name(trim($m[3]));
                $s->arrival()->code($m[4]);
            }

            $node = $this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Departure:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (preg_match("#(.+)\((.+?)\)$#", $node, $m)) {
                $s->departure()->date($this->normalizeDate($m[1]));
                $s->departure()->name(!empty($s->getDepName()) ? $s->getDepName() . ', ' . $m[2] : $m[2]);
            }

            $node = $this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Departure:")) . "]/ancestor::tr[1]/following-sibling::tr[1][" . $this->contains($this->t("Airport:")) . "]/td[2]", $root);

            if (!empty($node) && preg_match("#^(.+?)(?:\((.*terminal.*)\))?$#i", $node, $m)) {
                $s->departure()->name(!empty($s->getDepName()) ? trim($m[1]) . ', ' . $s->getDepName() : trim($m[1]));

                if (!empty($m[2])) {
                    $s->departure()->terminal(trim(str_ireplace('terminal', '', $m[2])));
                }
            }
            $node = $this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Terminal(Departure):")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (!empty($node)) {
                $s->departure()->terminal(trim(str_ireplace('terminal', '', $m[2])));
            }

            if (!empty($s->getDepName())) {
                $s->departure()->name(trim($s->getDepName(), ' ,'));
            }

            $node = $this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (preg_match("#(.+)\((.+?)\)$#", $node, $m)) {
                $s->arrival()->date($this->normalizeDate($m[1]));
                $s->arrival()->name(!empty($s->getArrName()) ? $s->getArrName() . ', ' . $m[2] : $m[2]);
            }

            $node = $this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::tr[1]/following-sibling::tr[1][" . $this->contains($this->t("Airport:")) . "]/td[2]", $root);

            if (!empty($node) && preg_match("#^(.+?)(?:\((.*terminal.*)\))?$#i", $node, $m)) {
                $s->arrival()->name(!empty($s->getArrName()) ? trim($m[1]) . ', ' . $s->getArrName() : trim($m[1]));

                if (!empty($m[2])) {
                    $s->arrival()->terminal(trim(str_ireplace('terminal', '', $m[2])));
                }
            }

            $node = $this->http->FindSingleNode("./following-sibling::tr[position()<={$count}]//text()[" . $this->eq($this->t("Terminal(Arrival):")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (!empty($node)) {
                $s->arrival()->terminal(trim(str_ireplace('terminal', '', $m[2])));
            }

            if (!empty($s->getArrName())) {
                $s->arrival()->name(trim($s->getArrName(), ' ,'));
            }
        }
    }

    private function assignLang($body)
    {
        foreach ($this->reBody2 as $lang => $reBody) {
            if (stripos($body, $reBody) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        if (empty($date)) {
            return null;
        }
        $year = date('Y', $this->date);
        $in = [
            '#^\s*(\d+)[\-]+(\d+)[\-]+(\d{2})\s*$#u', //09-03-16
            // Κυρ 13/Σεπ 16:30
            // Sat 03/Sep 11:20
            '#^\s*([^\d\s]+)\s+(\d+)\/([^\d\s]+)\s+(\d+:\d+)\s*$#u',
        ];
        $out = [
            '$1 $2 20$3',
            '$1, $2 $3 ' . $year . ' $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            } elseif ($en = MonthTranslate::translate($m[1], 'el')) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#(?<week>[^\d\s,.]+),\s+(?<date>\d+\s+[^\d\s]+\s+\d{4}\s*\d+:\d+)#", $date, $m)) {
            $dateL = $m['date'];
            $week = WeekTranslate::number1($m[1], $this->lang);

            if (!isset($week)) {
                $week = WeekTranslate::number1($m[1], 'el');
            }

            if (!isset($week)) {
                return false;
            }
            $date = EmailDateHelper::parseDateUsingWeekDay($dateL, $week);

            return $date;
        }

        return strtotime($date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "{$text} = \"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map('preg_quote', $field));
    }
}
