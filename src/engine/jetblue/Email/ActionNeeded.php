<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ActionNeeded extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-60485007.eml, jetblue/it-60540644.eml, jetblue/it-75806842.eml, jetblue/it-83399625.eml";

    private $detectFrom = "info@change.jetblue.com";
    private $detectSubject = [
        'Action needed – a message about your upcoming flight',
        'ACTION NEEDED: JetBlue flight schedule change',
        'An important message about your upcoming JetBlue flight',
    ];
    private $detectCompany = '.jetblue.com';
    private $detectBody = [
        'en' => [
            'We’ve made some changes to our schedule affecting your upcoming trip with us',
            "we've made some changes to our schedule which will impact your upcoming trip with us",
            "We've made some changes to our schedule which will impact your upcoming trip with us",
            'we have confirmed you on a new flight',
            'Please take note of your new departure details below',
            'We\'ve made some adjustments to our schedule',
        ],
    ];

    private $date;

    private $lang = 'en';
    private static $dict = [
        'en' => [
            'Dear'                 => ['Dear', 'Hello,', 'Hi,', 'Hi ,', 'Hi'],
            'CONFIRM MY FLIGHT'    => ['CONFIRM MY FLIGHT', 'Acknowledge changes', 'Please take note of your new departure details below'],
            'confirmation code is' => ['confirmation code is', 'trip with us on confirmation code', 'trip with us on confirmation', 'flight on confirmation code:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
//        foreach ($this->detectBody as $lang => $detectBody) {
//            if ($this->http->XPath->query("//*[".$this->contains($detectBody)."]")->length > 0) {
//                $this->lang = $lang;
//                break;
//            }
//        }
        $this->date = strtotime($parser->getDate());

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '" . $this->detectCompany . "') or contains(., 'Download the JetBlue')]")->length == 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers["subject"])
            || (stripos($headers['from'], $this->detectFrom) === false)
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
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Confirmation code:"))}]/following::text()[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t("confirmation code is"))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t("confirmation code is"))}\s*([A-Z\d]{5,7})\./");

        if (!empty($confirmation)) {
            $f->general()->confirmation($confirmation);
        } elseif ($this->http->XPath->query("//text()[{$this->starts($this->t('Confirmation code'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('CONFIRM MY FLIGHT'))}]")->length > 0
        ) {
            $f->general()->noConfirmation();
        }

        $traveller = $this->http->FindSingleNode("//*[" . $this->eq($this->t("Your New Flight Itinerary")) . "]/preceding::text()[" . $this->eq($this->t("Dear")) . "][1]/following::text()[normalize-space()][1]",
            null, true, "#^\s*([^\d,.]+?)[,.!]$#");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//*[" . $this->eq($this->t("Your New Flight Itinerary")) . "]/preceding::text()[" . $this->starts($this->t("Dear")) . "][1]", null, true, "#{$this->opt($this->t('Dear'))}[ ]*(.+?)[,.!]$#");
        }
        $f->general()->traveller($traveller);

        //Segments
        $xpath = "//*[" . $this->eq($this->t("Your New Flight Itinerary")) . "]/following::td[" . $this->eq($this->t("Departure")) . "]/preceding-sibling::td[" . $this->eq($this->t("Flight")) . "]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $from = $this->http->FindSingleNode("./td[4]", $root);
            $to = $this->http->FindSingleNode("./td[6]", $root);

            if (isset($s) && $s->getDepName() == $from && $s->getArrName() == $to) {
                // для удаления дублирующих сегментов
                continue;
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[3]", $root));

            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./td[2]", $root))
                ->number($this->http->FindSingleNode("./td[1]", $root))
            ;

            // Departure
            $s->departure()
                ->name($from)
                ->date(($date) ? strtotime($this->http->FindSingleNode("./td[5]", $root), $date) : false)
            ;

            if (preg_match("#\(\s*([A-Z]{3})\s*\)\s*$#", $s->getDepName(), $m)) {
                $s->departure()->code($m[1]);
            } elseif (!empty($s->getDepName())) {
                $s->departure()->noCode();
            }

            // Arrival
            $s->arrival()
                ->name($to)
                ->date(($date) ? strtotime($this->http->FindSingleNode("./td[7]", $root), $date) : false)
            ;

            if (preg_match("#\(\s*([A-Z]{3})\s*\)\s*$#", $s->getArrName(), $m)) {
                $s->arrival()->code($m[1]);
            } elseif (!empty($s->getDepName())) {
                $s->arrival()->noCode();
            }

            if ($this->http->FindSingleNode("./preceding::text()[" . $this->eq($this->t("Your Old Flight Itinerary")) . "]", $root)
            && !$this->http->FindSingleNode("./preceding::text()[" . $this->eq($this->t("Your New Flight Itinerary")) . "]", $root)) {
                $s->extra()->cancelled();
            }
        }

        return $email;
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
        $year = date('Y', $this->date);
        $in = [
            //Mon, May 20
            '#^\s*(\w+)\s+(\d+)\s*$#u',
        ];
        $out = [
            '$2 $1 ' . $year,
        ];
        $str = preg_replace($in, $out, $date);
        $str = EmailDateHelper::parseDateRelative($str, ($this->date - 60 * 60 * 24 * 60));

        return $str;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s*', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)='{$s}'";
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), '{$s}')";
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }
}
