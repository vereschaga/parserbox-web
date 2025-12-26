<?php

namespace AwardWallet\Engine\sncf\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTrip extends \TAccountChecker
{
    public $mailFiles = "sncf/it-138303820.eml, sncf/it-138526176.eml";
    public $subjects = [
        'Votre voyage à',
    ];

    public $lang = 'fr';
    public $date;

    public static $dictionary = [
        "fr" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@newsletter.oui.sncf') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'SNCF Connect')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Mon carnet de voyage'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Voiture'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Votre aller à'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]newsletter\.oui\.sncf$/', $from) > 0;
    }

    public function ParseTrain(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Référence :')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Référence :'))}\s*([A-Z\d]{5,})/"))
            ->traveller($this->http->FindSingleNode("//img[contains(@src, 'pictCard')]/following::text()[normalize-space()][1]"), true);

        $nodes = $this->http->XPath->query("//img[contains(@src, 'icoTrans')]");

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $startDate = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Votre aller à') or starts-with(normalize-space(), 'Votre retour à')][1]/ancestor::td[1]/following::td[1]/descendant::text()[normalize-space()][1]", $root);

            $seatInfo = $this->http->FindSingleNode("./following::text()[normalize-space()][1]/ancestor::tr[1]/following::tr[1]", $root);
            $this->logger->debug($seatInfo);

            if (preg_match("/Voiture\s*(?<carNumber>\d+)\s*Places?\s*(?<seat>.+)/u", $seatInfo, $m)) {
                $s->setCarNumber($m['carNumber']);

                if (isset($m['seat'])) {
                    $s->extra()
                        ->seats(explode(',', $m['seat']));
                }
            } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'Placement libre')]")->length > 0) {
                $carNumber = $this->http->FindSingleNode("./following::text()[normalize-space()][1]/ancestor::tr[1]", $root, true, "/^(\d+)[\s\-]+Placement libre/");
                $s->setNumber($carNumber);
            }

            $cabin = $this->http->FindSingleNode("./following::text()[normalize-space()][1]/ancestor::tr[1]", $root);

            if (preg_match("/^(?<number>\d+)[\-\s]+\s*(?<cabin>.+classe)$/", $cabin, $m)) {
                $s->setNumber($m['number']);
                $s->extra()
                    ->cabin($m['cabin']);
            } else {
                $s->setNoNumber(true);
            }

            $depInfo = $this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[contains(normalize-space(), ':')][2]", $root);

            if (preg_match("/^(?<depTime>[\d\:]+\s*A?P?M?)\s+(?<depName>.+)/", $depInfo, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($startDate . ', ' . $m['depTime']))
                    ->name($m['depName']);
            }
            $arrInfo = $this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[contains(normalize-space(), ':')][1]", $root);

            if (preg_match("/^(?<arrTime>[\d\:]+\s*A?P?M?)\s+(?<arrName>.+)/", $arrInfo, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($startDate . ', ' . $m['arrTime']))
                    ->name($m['arrName']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->ParseTrain($email);

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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug($date);

        $year = date('Y', $this->date);
        $in = [
            //mardi 8 février, 14:19
            '/^(\w+)\s*(\d+)\s*(\w+)\,\s*([\d\:]+\s*A?P?M?)$/u',
        ];
        $out = [
            "$2 $3 $year, $4",
        ];
        $outWeek = [
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = strtotime($str);
        }

        return $str;
    }
}
