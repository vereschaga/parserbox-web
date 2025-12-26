<?php

namespace AwardWallet\Engine\pobeda\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ChangeReservation extends \TAccountChecker
{
    public $mailFiles = "pobeda/it-76037234.eml, pobeda/it-80056425.eml";

    public $detectFrom = "reports@pobeda.aero";
    public $detectProvider = ['.pobeda.aero'];
    public $detectBody = [
        'ru' => ['Ваше бронирование было изменено'],
    ];
    public $detectSubject = [
        'Изменена бронь',
    ];
    public $lang = 'ru';
    public static $dict = [
        'ru' => [],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[" . $this->contains($this->detectProvider) . "]")->length == 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->detectSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Код брони")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Пассажир:")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*([[:alpha:] \-]+?)\s*(?:\(|\s*$)/u"))
        ;

        // Segments

        $segmentText = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Забронированный билет:")) . "]/following::text()[normalize-space()][1]");

        if (empty($segmentText)) {
            $segmentTexts = $this->http->FindNodes("//text()[" . $this->eq($this->t("Забронированные билеты:")) . "]/following::text()[normalize-space()][position()<10][following::text()[" . $this->eq($this->t("Пассажир:")) . "]]");
        } else {
            $segmentTexts[] = $segmentText;
        }
        $regexp = "/^(?<date>.+) (?<al>[A-Z\d][A-Z\d]|[A-Z\d][A-Z\d])(?<fn>\d{1,5}) (?<dName>.+) (?<dCode>[A-Z]{3})\s+-\s+(?<aName>.+) (?<aCode>[A-Z]{3})/u";

        foreach ($segmentTexts as $sText) {
            if (preg_match($regexp, $sText, $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                    ->date($this->normalizeDate($m['date']));

                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                    ->noDate();
            }
        }
        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Стоимость брони:")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#u", $total, $m)) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['currency']))
            ;
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 06.09.20 21:00
            '/^\s*(\d{1,2})\.(\d{1,2})\.(\d{2})\s+(\d{1,2}:\d{2})\s*$/u',
        ];
        $out = [
            '$1.$2.20$3, $4',
        ];
//        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        $str = strtotime(preg_replace($in, $out, $date));

        return $str;
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

    private function amount($price)
    {
        $total = PriceHelper::cost($price, ' ', ',');

        if (is_numeric($total)) {
            return $total;
        }
        $total = PriceHelper::cost($price, ' ', '.');

        if (is_numeric($total)) {
            return $total;
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("/^\s*([A-Z]{3})\s*$/", $s)) {
            return $code;
        }
        $sym = [
            '₽'    => 'RUB',
            'руб.' => 'RUB',
            '€'    => 'EUR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
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

    private function eq($field, $node = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return $node . '="' . $s . '"';
        }, $field)) . ')';
    }
}
