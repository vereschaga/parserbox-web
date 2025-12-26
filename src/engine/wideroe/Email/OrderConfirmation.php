<?php

namespace AwardWallet\Engine\wideroe\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderConfirmation extends \TAccountChecker
{
    public $mailFiles = "wideroe/it-11937655.eml, wideroe/it-12057825.eml";

    public $reFrom = [
        'wias.no',
        'wideroe.no',
    ];
    public $reBody = [
        'no'   => ['Bekreftelse på bestilling', 'Referansenummer'],
        'no2'  => ['for å laste ned kvittering(er)', 'Referansenummer'],
        'no3'  => ['Takk for din bestilling', 'Referansenummer'],
        'en'   => ['below for downloading', 'Booking reference'],
        'en2'  => ['Your booking is updated', 'Booking reference'],
    ];
    public $reSubject = [
        'no'  => 'Bekreftelse bestilling',
        'no2' => 'Bekreftelse og kvitteringer for reservasjon',
        'en'  => 'Confirmation and receipts for reservation',
    ];
    public $lang = '';
    public $date;
    public static $dict = [
        'no' => [
            //			"Referansenummer" => "",
            //			"Bestilling mottatt:" => "",
            //			"Rute" => "",
            //			"Dato" => "",
            "Navn" => ["Passasjerer", "Navn"],
            //			"Billettnummer" => "",
        ],
        'en' => [
            "Referansenummer" => ["Booking reference", "Booking reference:"],
            // "Bestilling mottatt:" => "",
            "Rute"          => "Flight",
            "Dato"          => "Date",
            "Navn"          => ["Name", "Passengers", "Receipt"],
            "Billettnummer" => "Ticket Number",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->AssignLang();
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->reFrom as $reFrom) {
            if (stripos($body, $reFrom) !== false) {
                return $this->AssignLang();
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $find = false;

        foreach ($this->reFrom as $reFrom) {
            if (strpos($headers["from"], $reFrom) !== false) {
                $find = true;
            }
        }

        if ($find == false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Referansenummer")) . "][1]/following::text()[normalize-space()][1]", null, true, "#\s*([A-Z\d]+)#");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Referansenummer")) . "])[1]", null, true, "#{$this->opt($this->t("Referansenummer"))}\:?\s+([A-Z\d]{5,})\b#");
        }

        if (!empty($confirmation)) {
            $f->general()
                ->confirmation($confirmation);
        }

        $dateReservation = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Bestilling mottatt:")) . "]/following::text()[normalize-space(.)][1]")));

        if (!empty($dateReservation)) {
            $this->date = $dateReservation;
        }

        $passenger = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Navn")) . "]/ancestor::tr[1][" . $this->contains($this->t("Billettnummer")) . "]/following-sibling::tr/td[1]", null, "#^\s*(.+\S)\s*\([A-Z]+\)\s*$#"));

        if (!empty($passenger)) {
            $f->general()
                ->travellers(preg_replace("/(?:MRS|MR|MS)$/", "", $passenger));
        }

        $tickets = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Navn")) . "]/ancestor::tr[1][" . $this->contains($this->t("Billettnummer")) . "]/following-sibling::tr/td[4]", null, "#^\s*([\d\-]{9,})\s*$#"));

        if (!empty($tickets)) {
            $f->setTicketNumbers($tickets, false);
        }

        $total = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Navn")) . "]/ancestor::tr[1][" . $this->contains($this->t("Billettnummer")) . "]/following-sibling::tr[normalize-space()][last()]/td[3]", null, "#^\s*(\d[\d\.\, ]+)\s*$#"));
        $currency = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Navn")) . "]/ancestor::tr[1][" . $this->contains($this->t("Billettnummer")) . "]/following-sibling::tr[normalize-space()][last()]/td[2]", null, "#^\s*([A-Z]{3})\s*$#");

        if (!empty($total) && !empty($currency)) {
            $f->price()
                ->total($total)
                ->currency($currency);
        }

        $xpath = "//text()[" . $this->eq($this->t("Rute")) . "]/ancestor::tr[" . $this->contains($this->t("Dato")) . "][1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $node = implode(" ", $this->http->FindNodes("./td[1]//text()[normalize-space()]", $root));

            if (preg_match("#\s+([A-Z\d]{2})\s*(\d+)\s*$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[2]", $root));
            $node = $this->http->FindSingleNode("./td[3]", $root);

            if (!empty($date) && preg_match("#(\d+[:.]\d+)\s*-\s*(\d+[:.]\d+)#", $node, $m)) {
                $depDate = strtotime(str_replace('.', ':', $m[1]), $date);

                if ($depDate < $this->date - 60 * 60 * 24 * 2) {
                    $depDate = strtotime("+1 year", $depDate);
                }

                $s->departure()
                    ->date($depDate);

                $arrDate = strtotime(str_replace('.', ':', $m[2]), $date);

                if ($arrDate < $this->date - 60 * 60 * 24 * 2) {
                    $arrDate = strtotime("+1 year", $arrDate);
                }

                $s->arrival()
                    ->date($arrDate);
            }

            $node = $this->http->FindSingleNode("./td[4]", $root);

            if (preg_match("#(.+?)\(([A-Z]{3})\)\s*-\s*(.+?)\(([A-Z]{3})\)#", $node, $m)) {
                $s->departure()
                    ->name(trim($m[1]))
                    ->code($m[2]);

                $s->arrival()
                    ->name(trim($m[3]))
                    ->code($m[4]);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        if (empty($date)) {
            return null;
        }
        $year = date('Y', $this->date);
        $in = [
            '#^\s*(\d+)[\s\.]+(\w+)[\.\s]*$#u', //17. feb
            '#^\s*(\w+)\s+(\d+)[\s\.]+([^\d\s\.\,]+)[\.\s]*$#u', //Søn 05 Aug
        ];
        $out = [
            '$1 $2 ' . $year,
            '$1, $2 $3 ' . $year,
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#(?<week>[^\d\s\,\.]+),\s+(?<date>\d+\s+[^\d\s]+\s+\d{4})#", $date, $m)) {
            $date = $m['date'];
            $week = WeekTranslate::number1($m[1], $this->lang);

            if (empty($week)) {
                return false;
            }
            $date = EmailDateHelper::parseDateUsingWeekDay($date, $week);

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

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $reBody[0] . '")]')->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return "(?:" . preg_quote($s) . ")"; }, $field)) . ')';
    }

    private function amount($s)
    {
        if (empty($s)) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }
}
