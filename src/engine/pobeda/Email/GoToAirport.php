<?php

namespace AwardWallet\Engine\pobeda\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class GoToAirport extends \TAccountChecker
{
    public $mailFiles = "pobeda/it-74905459.eml, pobeda/it-92950809.eml";

    public $detectFrom = "reports@pobeda.aero";
    public $detectProvider = ['.pobeda.aero'];
    public $detectBody = [
        'ru' => ['Напоминаем Вам, что пора выезжать в аэропорт'],
    ];
    public $detectSubject = [
        'Пора выезжать в аэропорт',
    ];
    public $lang = 'ru';
    public static $dict = [
        'ru' => [
            'Здравствуйте' => ['Здравствуйте', 'Уважаемые пассажиры'],
        ],
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
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        // General
        $f->general()->confirmation($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Код бронирования")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,7})\s*$/"));

        $travellerNodes = array_filter($this->http->FindNodes("//h1[{$this->starts($this->t("Здравствуйте"))}]", null, "/{$this->opt($this->t("Здравствуйте"))}(?:[ ]+{$patterns['travellerName']})?[ ]*,[ ]*({$patterns['travellerName']})(?:\s*[:;!?]|$)/u"));

        if (count(array_unique($travellerNodes)) === 1) {
            $f->general()->traveller(array_shift($travellerNodes));
        }

        // Segments
        $s = $f->addSegment();

        $s->airline()
            ->name($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Номер рейса")) . "]", null, true, "/ –\s+([A-Z\d][A-Z\d]|[A-Z\d][A-Z\d])\d{1,5}\s*$/"))
            ->number($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Номер рейса")) . "]", null, true, "/ –\s+(?:[A-Z\d][A-Z\d]|[A-Z\d][A-Z\d])(\d{1,5})\s*$/"))
        ;

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Дата рейса")) . "]", null, true, "/\s+–\s+(.+)/"));
        $time = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Плановое время отправления")) . "]", null, true, "/\s+–\s+(\d{1,2}:\d{2})\s*\(/");

        $s->departure()
            ->noCode()
            ->name($this->http->FindSingleNode("//tr[" . $this->eq($this->t("Погода по маршруту")) . "]/following-sibling::tr[1]/descendant-or-self::tr[not(.//tr)][1][count(td[normalize-space()]) = 2]/td[normalize-space()][1]"))
            ->date((!empty($date) && !empty($time)) ? strtotime($time, $date) : null)
        ;

        $s->arrival()
            ->noCode()
            ->name($this->http->FindSingleNode("//tr[" . $this->eq($this->t("Погода по маршруту")) . "]/following-sibling::tr[1]/descendant-or-self::tr[not(.//tr)][1][count(td[normalize-space()]) = 2]/td[normalize-space()][2]"))
            ->noDate()
        ;
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
            // 11 июля 2019 года, четверг    |    7 мая 2021 г , пятница
            '/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*г(?:ода)?[, ]+[-[:alpha:]]+\s*$/iu',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

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
