<?php

namespace AwardWallet\Engine\rfh\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourUpcomingStay extends \TAccountChecker
{
    public $mailFiles = "rfh/it-169718852.eml, rfh/it-643510052.eml";

    private $detectFrom = "roccofortehotels.com";
    private $detectSubject = [
        'Your upcoming stay at',
    ];
    private $subject;
    private $lang = 'en';
    private static $dictionary = [
        'en' => [
            'ARRIVAL:'               => 'ARRIVAL:',
            'DEPARTURE:'             => 'DEPARTURE:',
            'YOUR REFERENCE:'        => 'YOUR REFERENCE:',
            'TOP 5 THINGS TO DO IN'  => 'TOP 5 THINGS TO DO IN',
            'GETTING HERE'           => 'GETTING HERE',
            'is located on'          => 'is located on',
            'HotelnameFromSubjectRE' => 'Your upcoming stay at (?<name>.+)',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        if ($this->http->XPath->query("//text()[normalize-space()='CONFIRMATION REF:']")->length === 1) {
            $this->parseEmail($email);
        } else {
            $this->parseEmail2($email);
            $otaConf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'ITINERARY NUMBER')]", null, true, "/{$this->opt($this->t('ITINERARY NUMBER'))}\s*([A-Z\d]{10,})/");

            if (!empty($otaConf)) {
                $email->ota()
                    ->confirmation($otaConf);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'roccofortehotels.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if ($this->http->XPath->query("//text()[{$this->eq($this->t("ARRIVAL:"))}]/following::text()[{$this->starts($this->t("TOP 5 THINGS TO DO IN"))}]/following::text()[{$this->eq($this->t("GETTING HERE"))}]")->length > 0) {
                return true;
            } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'RESERVATIONS')]/following::text()[contains(normalize-space(), 'CHECK-IN:')][1]/following::text()[contains(normalize-space(), 'ROOM TYPE:')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers["subject"])
            || stripos($headers['from'], $this->detectFrom) === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->eq($this->t("YOUR REFERENCE:"))}])[1]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d{5,})\s*$/"));

        // Hotel
        if (preg_match("/" . $this->t("HotelnameFromSubjectRE") . "/", $this->subject, $m) && !empty($m['name'])) {
            $hotelname = $m['name'];
        }

        if (empty($hotelname)) {
            $hotelname = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("GETTING HERE"))}])[1]/following::text()[normalize-space()][1]", null, true,
                "/^(.+?) {$this->opt($this->t("is located on"))}/");
        }

        $h->hotel()
            ->name($hotelname)
            ->address($this->http->FindSingleNode("(//text()[{$this->starts($this->t("TOP 5 THINGS TO DO IN"))}])[1]", null, true,
                "/^\s*{$this->opt($this->t("TOP 5 THINGS TO DO IN"))}\s+(.+)/"))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->eq($this->t("ARRIVAL:"))}])[1]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->eq($this->t("DEPARTURE:"))}])[1]/following::text()[normalize-space()][1]")))
        ;

        return $email;
    }

    private function ParseEmail2(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[normalize-space()='CONFIRMATION REF:']");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]{10,})$/"));

            $h->general()
                ->traveller($this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Customer Name')][1]", $root, true, "/{$this->opt($this->t('Customer Name'))}\s*(.+)/"));

            if (preg_match("/Itinerary Confirmation\s*(?<name>.+)\s+(?<ota>[A-Z\d]{8,})/", $this->subject, $m) && !empty($m['name'])) {
                $h->hotel()
                    ->name($m['name'])
                    ->address($this->http->FindSingleNode("(//text()[{$this->contains($this->t("We are pleased to confirm your reservation at"))}])[1]", null, true,
                        "/\s*{$this->opt($this->t("We are pleased to confirm your reservation at "))}{$m['name']}\,\s+(.+)\./"));
            }

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->eq($this->t("CHECK-IN:"))}])[1]/following::text()[normalize-space()][1]")))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->eq($this->t("CHECK-OUT:"))}])[1]/following::text()[normalize-space()][1]")));

            $guestsText = $this->http->FindSingleNode("./following::text()[normalize-space()='GUESTS:'][1]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/Adults\:\s*(?<guests>\d+)\,\s*Children\:\s*(?<kids>\d+)/", $guestsText, $m)) {
                $h->booked()
                    ->guests($m['guests'])
                    ->kids($m['kids']);
            }

            $roomCount = $this->http->FindSingleNode("./following::text()[normalize-space()='NUMBER OF ROOMS:'][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($roomCount)) {
                $h->booked()
                    ->rooms($roomCount);
            }

            $roomType = $this->http->FindSingleNode("./following::text()[normalize-space()='ROOM TYPE:'][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($roomType)) {
                $h->addRoom()->setType($roomType);
            }

            $price = $this->http->FindSingleNode("./following::text()[normalize-space()='TOTAL:'][1]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
                $h->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
        $in = [
            //16/11/2016
            '#^\s*(\d{1,2})\s*\/(\d{1,2})\/(\d{4})\s*$#',
        ];
        $out = [
            '$1.$2.$3',
        ];
        $str = preg_replace($in, $out, $date);
        //		$str = $this->dateStringToEnglish($str);
        return strtotime($str);
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nextText($field, $root = null, $regexp = null, $n = 1)
    {
        $nextText = $this->re("#{$this->opt($field)}\s+(.+)#", $root);

        if (isset($regexp)) {
            if (preg_match($regexp, $nextText, $m)) {
                return $m[$n];
            } else {
                return null;
            }
        }

        return $nextText;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s*', preg_quote($s));
        }, $field)) . ')';
    }

    private function contains($field, $node = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(' . $node . ",'" . $s . "')";
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
