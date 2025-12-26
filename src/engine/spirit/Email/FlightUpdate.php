<?php

namespace AwardWallet\Engine\spirit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightUpdate extends \TAccountChecker
{
    public $mailFiles = "spirit/it-12788125.eml, spirit/it-12797277.eml, spirit/it-12942316.eml, spirit/it-12942334.eml, spirit/it-1808788.eml, spirit/it-1809218.eml, spirit/it-2900686.eml, spirit/it-3126882.eml, spirit/it-61725246.eml, spirit/it-61947731.eml, spirit/it-78037698.eml";

    public $lang = "en";
    private $reFrom = "@fly.spirit-airlines.com";
    private $reSubject = [
        "en"=> "Spirit Airlines Flight",
    ];
    private $reBody = ['spirit-airlines.com', 'spiritairlines.com', 'spirit.com'];
    private $reBody2 = [
        "en"  => "You're all set to receive information for the following flight(s):",
        'en2' => 'This notification is to inform you of the following change(s):',
        'en3' => 'We want to inform you of the following delay:',
        'en4' => 'We want to inform you of the following flight arrival:',
        'en5' => 'Things change quickly, so please keep an eye out for additional updates',
        'en6' => 'Flight Status Notifications begin 48 hours prior to the original scheduled departure time.',
    ];

    private static $dictionary = [
        "en" => [],
    ];
    private $date = null;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $check = false;

        foreach ($this->reBody as $re) {
            if (strpos($body, $re) !== false) {
                $check = true;
            }
        }

        if (!$check) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->date = strtotime($parser->getHeader('date'));
        $this->parseFlight($email);

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

    //                 $itsegment['DepCode'] = $this->http->FindSingleNode("./ancestor::tr[2]/preceding-sibling::tr[1]//td[not(.//td)][1]", $root, true, "#\(([A-Z]{3})\)#");
    private function parseFlight(Email $email)
    {
        $this->http->XPath->registerNamespace('php', 'http://php.net/xpath');
        $this->http->XPath->registerPhpFunctions('preg_match');

        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        if ($this->http->FindSingleNode("//text()[{$this->starts($this->t('Flight Update:'))}]")) {
            $f->general()->status('updated');
        }

        $nodes = $this->http->XPath->query($xpath = "//text()[{$this->eq("Flight:")}]/ancestor::tr[2]");
        $this->logger->debug($xpath);

        $date = '';

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            // Date
            $date_temp = implode(' ', $this->http->FindNodes("./preceding::tr[php:functionString('preg_match', '/[A-Z]{3}/s', .)][1]/preceding::tr[td/p/b]//text()[normalize-space()][not(ancestor-or-self::strike)]", $root));

            if (empty($date_temp)) {
                $date_temp = implode(' ', $this->http->FindNodes("./preceding::tr[php:functionString('preg_match', '/\([A-Z]{3}\)/s', .)][1]/preceding-sibling::tr[last()]//text()[normalize-space()][not(ancestor-or-self::strike)]", $root));
            }

            if (empty($date_temp)) {
                $date_temp = implode(' ', $this->http->FindNodes("./preceding::tr[php:functionString('preg_match', '/\([A-Z]{3}\)/s', .)][1]/preceding::tr[1]//text()[normalize-space()][not(ancestor-or-self::strike)]", $root));
            }

            if (!empty($date_temp)) {
                $date = $this->normalizeDate($date_temp);
            }

            if ($this->http->FindSingleNode("//text()[{$this->contains($this->t('At Spirit Airlines'))}]")) {
                $s->airline()->name('NK');
            } //Spirit Airlines
            $s->airline()->number($this->http->FindSingleNode(".//text()[{$this->eq('Flight:')}]/../following-sibling::text()[1]", $root));

            $names = array_filter($this->http->FindNodes("./preceding::tr[php:functionString('preg_match', '/\([A-Z]{3}\)/s', .)][1]/td", $root));

            if (empty($names)) {
                $names = array_filter($this->http->FindNodes(".//text()[{$this->eq('Terminal:')}]/ancestor::tr[1]/preceding-sibling::tr[contains(.,':')]/preceding-sibling::tr//td[string-length(normalize-space(.))>12]", $root));
            }
            //print_r($names);
            if (!empty($names)) {
                $names = array_values($names);

                if ($m = $this->parseName($names[0])) {
                    $s->departure()->name($m['name']);
                    $s->departure()->code($m['code']);
                }

                if ($m = $this->parseName($names[1])) {
                    $s->arrival()->name($m['name']);
                    $s->arrival()->code($m['code']);
                }
                // it-61725246.eml
                if (empty($s->getDepCode()) && empty($s->getArrCode())) {
                    $codes = array_filter($this->http->FindNodes(".//text()[{$this->eq('Terminal:')}]/ancestor::tr[1]/preceding-sibling::tr//td[string-length(normalize-space(.))=3]", $root));
                    $s->departure()->name($names[0]);
                    $s->departure()->code($codes[0]);
                    $s->arrival()->name($names[1]);
                    $s->arrival()->code($codes[1]);
                }
            }
            $times = array_filter(
                $this->http->FindNodes(".//text()[{$this->eq('Terminal:')}]/ancestor::tr[1]/preceding-sibling::tr[contains(.,':')][1]//td[string-length(normalize-space(.))>3]/text()", $root)
            );

            if (empty($times)) {
                $times = array_filter(
                    $this->http->FindNodes(".//text()[{$this->eq('Terminal:')}]/ancestor::tr[1]/preceding-sibling::tr[contains(.,':')][1]//td[string-length(normalize-space(.))>3]/p[last()]", $root)
                );
            }

            if (empty($times)) {
                $times = array_filter(
                    $this->http->FindNodes(".//text()[{$this->eq('Terminal:')}]/ancestor::tr[1]/preceding-sibling::tr[contains(.,':')][1]//td[string-length(normalize-space(.))>3]/span[last()]", $root)
                );
            }

            if (!empty($times)) {
                $times = array_values($times);
                $s->departure()->date(strtotime($times[0], $date));
                $s->arrival()->date(strtotime($times[1], $date));
            }

            $terminals = $this->http->FindNodes(".//text()[{$this->eq('Terminal:')}]/ancestor::td[1]");

            if (!empty($terminals)) {
                $terminals = array_values($terminals);

                if (preg_match("/{$this->opt($this->t('Terminal:'))}\s*(\w+)/", $terminals[0], $m)) {
                    $s->departure()->terminal($m[1]);
                }

                if (preg_match("/{$this->opt($this->t('Terminal:'))}\s*(\w+)/", $terminals[1], $m)) {
                    $s->arrival()->terminal($m[1]);
                }
            }
        }
    }

    private function parseName($str)
    {
        if (preg_match('/^(?<name>.+?)\s+\((?<code>[A-Z]{3})\)/', $str, $m)
            || preg_match('/^(?<code>[A-Z]{3}) - (?<name>.+)/', $str, $m)
            || preg_match('/^(?<name>.+?)\s+(?<code>[A-Z]{3})/', $str, $m)) {
            return $m;
        }

        return null;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date, $lang = 'en')
    {
        $year = date('Y', $this->date);

        $in = [
            //Saturday, April 28
            '#^(\w+),?\s+(\w+)\s+(\d{1,2})$#',
        ];
        $out = [
            "$3 $2 {$year}",
        ];
        $outWeek = [
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date), $lang);
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function dateStringToEnglish($date, $lang = 'en')
    {
        if (preg_match('#[[:alpha:]]{3,}#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $lang)) {
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

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            //'$'=>'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
