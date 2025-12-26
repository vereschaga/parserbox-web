<?php

namespace AwardWallet\Engine\yatra\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "yatra/it-13753153.eml, yatra/it-13753354.eml, yatra/it-13753405.eml";

    public $reFrom = ["bookings@agentsfortravel.com", "@tsi-yatra.com"];
    public $reBody = [
        'en' => ['Thank you for booking with TSI-Yatra', 'E- Ticket copy will be shared shortly'],
    ];
    public $reSubjectRegExp = [
        '#Booking Confirmation\s+\([A-Z\d]{5,6}\)#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }
        $this->date = strtotime($parser->getDate());
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'Yatra')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubjectRegExp as $reSubject) {
                    if (preg_match($reSubject, $headers["subject"])) {
                        return true;
                    }
                }
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

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Please note your booking reference number'))}]/following::text()[normalize-space(.)!=''][1]"),
                $this->t('Booking reference number'));
        $f = $email->add()->flight();
        $f->general()
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Details'))}]/following::table[1]/descendant::tr[position()>1]/td[2]"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Please note your booking reference number'))}]/following::text()[normalize-space(.)!=''][1]"));

        $xpath = "//text()[{$this->eq($this->t('Flight Details'))}]/following::table[1]/descendant::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $s->extra()->status($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)!=''][3]", $root));
            $node = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)!=''][2]", $root);

            if (preg_match("#^([A-Z\d]{2})[\s\-]*(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $s->airline()
                ->confirmation($this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)!=''][2]", $root, false, "#^\s*([A-Z\d]{5,})\s*$#"));

            $node = implode("\n", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)!='']", $root));

            if (preg_match("#^ *(?:Terminal\s+(?:T\-)?(.+?),)? *(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                if (isset($m[1]) && !empty($m[1])) {
                    $s->departure()
                        ->terminal($m[1]);
                }
                $s->departure()
                    ->name($m[2])
                    ->code($m[3]);
            }

            if (!empty($class = $this->re("#{$this->opt($this->t('Class'))}[\s\-]+([A-Z]{1,2})#", $node))) {
                $s->extra()->bookingCode($class);
            }
            $date = $this->re("#{$this->opt($this->t('Departure'))}[\s:]+(.+)#", $node);
            $s->departure()->date($this->normalizeDate($date));

            if (!empty($node = $this->http->FindSingleNode("./td[4]", $root))) {
                //parse to detect junk by service
                if (preg_match("#(\d+)\s+{$this->opt($this->t('Stop'))}\s+(.+)#", $node, $m)) {
                    $punkt = $m[2];
                    $s->arrival()->name($punkt);
                }
                $s->arrival()->noDate()->noCode();

                $s = $f->addSegment();
                $s->airline()->noNumber()->noName();

                if (isset($punkt)) {
                    $s->departure()->name($punkt);
                }
                $s->departure()->noDate()->noCode();
            }

            $node = implode("\n", $this->http->FindNodes("./td[3]/descendant::text()[normalize-space(.)!='']", $root));

            if (preg_match("#^ *(?:Terminal\s+(?:T\-)?(.+?),)? *(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                if (isset($m[1]) && !empty($m[1])) {
                    $s->arrival()
                        ->terminal($m[1]);
                }
                $s->arrival()
                    ->name($m[2])
                    ->code($m[3]);
            }

            if (!empty($class = $this->re("#{$this->opt($this->t('Class'))}[\s\-]+([A-Z]{1,2})#", $node))) {
                $s->extra()->bookingCode($class);
            }
            $date = $this->re("#{$this->opt($this->t('Arrival'))}[\s:]+(.+)#", $node);
            $s->arrival()->date($this->normalizeDate($date));
        }

        //getting sum's
        $total = $tax = $cost = $fee = null;
        $xpath = "//text()[{$this->eq($this->t('Fare Details'))}]/following::table[1]/descendant::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./td[4]", $root));

            if (!empty($tot['Total'])) {
                $cost += $tot['Total'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./td[5]", $root));

            if (!empty($tot['Total'])) {
                $tax += $tot['Total'];
            }
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("{$xpath}/descendant::td[{$this->starts($this->t('Service Charge'))}]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $fee += $tot['Total'];
            $f->price()
                ->fee($this->http->FindSingleNode("{$xpath}/descendant::td[{$this->starts($this->t('Service Charge'))}]"), $tot['Total']);
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("{$xpath}/descendant::td[{$this->starts($this->t('Total'))}]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $total += $tot['Total'];
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        if ($tax + $cost + $fee === $total) {
            $f->price()
                ->cost($cost)
                ->tax($tax);
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Mon, 15Feb, 11:45
            '#^(\w+),\s+(\d+)\s*(\w+),\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
        ];
        $out = [
            '$2 $3 ' . $year . ' $4',
        ];
        $outWeek = [
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("Rs.", "INR", $node);
        $node = str_replace("Rs", "INR", $node);
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
