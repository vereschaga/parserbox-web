<?php

namespace AwardWallet\Engine\royalcaribbean\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PlannerConfirmation extends \TAccountChecker
{
    public $mailFiles = "royalcaribbean/it-30236119.eml, royalcaribbean/it-108534830.eml";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "@royalcaribbean.com";
    private $detectSubject = [
        "en" => ["Cruise Planner Confirmation", "Online Payment Confirmation"],
    ];

    private $detectBody = [
        "en" => "Cruise Summary",
    ];

    private $providerCode = '';

    private $lang = "en";

    private $date;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

//        foreach($this->reBody2 as $lang=>$re){
        //			if(strpos($this->http->Response["body"], $re) !== false){
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);
        $this->parseHtml($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers['subject'])) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers['from']) === false
                && stripos($headers['subject'], 'Costco Travel') === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        $body = $this->http->Response["body"];

        foreach ($this->detectBody as $dBody) {
            if (strpos($body, $dBody) !== false) {
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

    public static function getEmailProviders()
    {
        return ['celebritycruises', 'royalcaribbean'];
    }

    private function parseHtml(Email $email)
    {
        $c = $email->add()->cruise();

        // General
        $c->general()
            ->confirmation($this->nextText("Reservation ID:"), "Reservation ID")
            ->travellers(array_unique(array_filter($this->http->FindNodes("//td[normalize-space()='Guest Names']/ancestor::tr[1]/following-sibling::tr/td[1]//text()"))))
        ;
        $accounts = array_unique(array_filter($this->http->FindNodes("//td[normalize-space()='Crown & Anchor']/ancestor::tr[1]/following-sibling::tr/td[2]//text()", null, "#^\s*(\d{5,})\-\w+\s*$#")));

        if (!empty($accounts)) {
            $c->program()->accounts($accounts, false);
        }

        // Details
        $c->details()
            ->description($this->http->FindSingleNode("//td[normalize-space()='ONLINE CHECK-IN' or normalize-space()='Online Check-In']/ancestor::*[contains(.,'FAQ')][1]/following::text()[normalize-space()][1]/ancestor::td[1]"))
            ->ship($this->http->FindSingleNode("//td[normalize-space()='ONLINE CHECK-IN' or normalize-space()='Online Check-In']/ancestor::*[contains(.,'FAQ')][1]/following::text()[normalize-space()][1]/ancestor::td[1]/following::text()[normalize-space()][1]/ancestor::td[1]"))
            ->room($this->nextText("Stateroom Nbr:"))
        ;

        $this->date = $this->normalizeDate($this->nextText("Boarding Date:"));

        $xpath = "//td[normalize-space()='Port of Call']/ancestor::tr[1][following::tr[normalize-space()][1][contains(., 'Arrive')]]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $date = $this->http->FindSingleNode("./td[normalize-space()][1]", $root);
            $name = $this->http->FindSingleNode("./td[normalize-space()][last()]", $root);
            $time1 = $this->http->FindSingleNode("./following::tr[normalize-space()][1][contains(., 'Arrive')]//text()[contains(normalize-space(), 'Arrive')][1]/following::text()[normalize-space()][1]", $root, true, "#.*\d.*#");
            $time2 = $this->http->FindSingleNode("./following::tr[normalize-space()][1][contains(., 'Arrive')]//text()[contains(normalize-space(), 'Depart')][1]/following::text()[normalize-space()][1]", $root, true, "#.*\d.*#");

            if (empty($time1) && empty($time2)) {
                continue;
            }

            if (isset($previous) && $previous['name'] !== $name) {
                unset($previous);
            }

            if (!(empty($time1) && !empty($time2) && isset($previous))) {
                $s = $c->addSegment();
            }

            if (!empty($time1)) {
                $s
                    ->setName($name)
                    ->setAshore($this->normalizeDate($date . ' ' . $time1))
                ;
            } elseif (isset($previous)) {
                $s
                    ->setName($previous['name'] ?? null)
                    ->setAshore($previous['date'] ?? null)
                ;
                unset($previous);
            }

            if (!empty($time2)) {
                $s
                    ->setName($name)
                    ->setAboard($this->normalizeDate($date . ' ' . $time2))
                ;
            } else {
                $previous['name'] = $name;
                $previous['date'] = $this->normalizeDate($date . ' ' . $time1);
            }
        }
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@celebritycruises.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".celebritycruises.co.uk/") or contains(@href,"www.celebritycruises.co.uk")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Celebrity Cruises Ltd. All rights reserved") or contains(.,"@celebrity.com")]')->length > 0
        ) {
            $this->providerCode = 'celebritycruises';

            return true;
        }

        if (stripos($headers['from'], '@royalcaribbean.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".royalcaribbean.de/") or contains(@href,"www.royalcaribbean.de")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Royal Caribbean Cruises Ltd. All rights reserved") or contains(.,"@rccl.com")]')->length > 0
        ) {
            $this->providerCode = 'royalcaribbean';

            return true;
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^\s*([^\s\d\.\,]+)[\-\s]+(\d{1,2})\s+([^\d\s]+)\s+(\d+:\d+(\s*[AP]M)?)\s*$#ui", //MON-03 DEC 15:00, WED-09 JAN 8:00 AM
        ];
        $out = [
            '$1, $2 $3 ' . $year . ' $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^\s*(?<week>[^\s\d\.\,]+), (?<date>.*\d{4}.*)\s*$#", $str, $m)) {
            $weekDayNumber = WeekTranslate::number1($m['week'], $this->lang);

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weekDayNumber);
        }

        if (!preg_match("#\b\d{4}\b#", $str)) {
            return null;
        }

        return strtotime($str);
    }

    private function nextText($field, $root = null, $regex = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regex);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }
}
