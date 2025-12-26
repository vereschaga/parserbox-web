<?php

namespace AwardWallet\Engine\wideroe\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OpenToCheckIn extends \TAccountChecker
{
    public $mailFiles = "wideroe/it-12189924.eml";

    public $reFrom = [
        'wias.no',
        'wideroe.no',
    ];
    public $reBody = [
        'en' => ['Your flight is now open for check-in'],
        'no' => ['Du kan nå sjekke inn på din reise'],
    ];
    public $reSubject = [
        'en' => 'is open for check-in. Ref',
    ];
    public $lang = '';
    public $subject;
    public static $dict = [
        'en' => [
            //			"Booking reference:" => "",
            //			"Passenger(s):" => "",
            //			"Flight:" => "",
            //			"From:" => "",
            //			"Date:" => "",
            //			"To:" => "",
        ],
        'no' => [
            "Booking reference:" => "Referansenummer:",
            "Passenger(s):"      => "Passasjer(er):",
            "Flight:"            => "Flynummer:",
            "From:"              => "Fra:",
            "Date:"              => "Dato:",
            "To:"                => "Til:",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
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
            if (stripos($headers["from"], $reFrom) !== false) {
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

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking reference:")) . "][1]/following::text()[normalize-space()][1]", null, true, "#\s*([A-Z\d]+)#"))
            ->travellers(array_map("trim", explode(",", $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Passenger(s):")) . "][1]/following::text()[normalize-space()][1]"))), true);

        $s = $f->addSegment();

        $s->airline()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Flight:")) . "][1]/following::text()[normalize-space()][1]", null, true, "#\s*([A-Z\d]{2})\d{1,5}\b#"))
            ->number($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Flight:")) . "][1]/following::text()[normalize-space()][1]", null, true, "#\s*[A-Z\d]{2}(\d{1,5})\b#"));

        $s->departure()
            ->name(trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("From:")) . "][1]/following::text()[normalize-space()][1]", null, true, "#(.+?)\([A-Z]{3}\)#")))
            ->code($this->http->FindSingleNode("//text()[" . $this->eq($this->t("From:")) . "][1]/following::text()[normalize-space()][1]", null, true, "#.+?\(([A-Z]{3})\)#"))
            ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("From:")) . "][1]/following::text()[" . $this->eq($this->t("Date:")) . "][1]/following::text()[normalize-space()][1]"))));

        $s->arrival()
            ->name(trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("To:")) . "][1]/following::text()[normalize-space()][1]", null, true, "#(.+?)\([A-Z]{3}\)#")))
            ->code($this->http->FindSingleNode("//text()[" . $this->eq($this->t("To:")) . "][1]/following::text()[normalize-space()][1]", null, true, "#.+?\(([A-Z]{3})\)#"))
            ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("To:")) . "][1]/following::text()[" . $this->eq($this->t("Date:")) . "][1]/following::text()[normalize-space()][1]"))));

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*[^\d\s]+\s+(\d+)[\s\.]+(\w+)[\.\s]+(\d{4})\s+(\d+:\d+)\s*$#u', // Tor 06. okt 2016 14:25
            '#^(\d+)[\s\.]+(\w+)[\.\s]+(\d{4})\s+(\d+:\d+)\s*$#u', // 06. okt 2016 14:25
        ];
        $out = [
            '$1 $2 $3 $4',
            '$1 $2 $3 $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return $date;
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
            foreach ($this->reBody as $lang => $reBodies) {
                foreach ($reBodies as $reBody) {
                    if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $reBody . '")]')->length > 0) {
                        $this->lang = substr($lang, 0, 2);

                        return true;
                    }
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
}
