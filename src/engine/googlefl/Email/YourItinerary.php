<?php

namespace AwardWallet\Engine\googlefl\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourItinerary extends \TAccountChecker
{
    public $mailFiles = "googlefl/it-62751841.eml, googlefl/it-63104684.eml, googlefl/it-63261881.eml, googlefl/it-86463453.eml";
    public $subject = '/Your itinerary from Google Flights/';
    public $body = [
        "Here's the itinerary you sent to yourself from Google Flights",
        "Round trip",
        "View on Google",
    ];
    public $lang = "en";
    public $date;

    private static $dict = [
        'en' => [
            'Round trip' => ['Round trip', 'One way'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match($this->subject, $headers['subject'])) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'itinerary you sent to yourself from Google Flights')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Round trip') or contains(normalize-space(), 'One way')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'View on Google')]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Including taxes and fees')]/preceding::text()[normalize-space()][1]", null, true, "/^\S{1}(\d+)$/");
        $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Including taxes and fees')]/preceding::text()[normalize-space()][1]", null, true, "/^(\S{1})\d+$/");

        if (!empty($total) && !empty($currency)) {
            $f->price()
                ->total($total)
                ->currency($this->normalizeCurrency($currency));
        }

        $mainCabin = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Multi')]/ancestor::div[1]", null, true, "/^\s*Multi\-city\s*\S+\s*(\w+)\s*\S+\s*\d\s*/");

        $names = explode(',', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Multi')]/preceding::text()[normalize-space()][1]/ancestor::div[1]"));

        $xPath = "//a[starts-with(normalize-space(), 'View on Google')]/following::tr/descendant::img/ancestor::tr[1]";
        $node = $this->http->XPath->query($xPath);

        foreach ($node as $i => $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/.+[AP]M(?:[+-]\d)?\s+(\w.+)/"))
                ->noNumber()
            ;

            $depDate = $this->http->FindSingleNode("./preceding::tr[1]", $root);
            $depTime = $this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^([\d\:]+\s*A?P?M)\s*\–/");

            $depName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Round trip'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(\D+)\s+[–]/u");

            if (empty($depName)) {
                $depName = $this->re("/^(\D+)\s*\–/u", trim($names[$i]));
            }

            $s->departure()
                ->name($depName)
                ->date($this->normalizeDate($depDate . ', ' . $depTime))
                ->noCode();

            $arrDate = $this->http->FindSingleNode("./preceding::tr[1]", $root);
            $arrTime = $this->http->FindSingleNode("./descendant::td[2]", $root, true, "/\s*\–\s*([\d\:]+\s*A?P?M)\s+/");
            $fullDate = $this->normalizeDate($arrDate . ', ' . $arrTime);

            if (empty($arrTime)) {
                $arrTime = $this->http->FindSingleNode("./descendant::td[2]", $root, true, "/\s*\–\s*([\d\:]+\s*A?P?M)[+]\d\s+/");
                $fullDate = strtotime('+1 day', $this->normalizeDate($arrDate . ', ' . $arrTime));
            }

            $arrName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Round trip'))}]/preceding::text()[normalize-space()][1]", null, true, "/\s+[–]\s+(\D+)$/u");

            if (empty($arrName)) {
                $arrName = $this->re("/\–\s*(\D+)/u", trim($names[$i]));
            }

            $s->arrival()
                ->name($arrName)
                ->date($fullDate)
                ->noCode();

            $stops = $this->http->FindSingleNode("./descendant::td[3]", $root, true, "/^(\d+)\s*/");

            if (!empty($stops)) {
                $s->extra()
                    ->stops($stops);
            }

            $cabin = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Round trip'))}]/ancestor::div[1]", null, true, "/{$this->opt($this->t('Round trip'))}\s+\•\s+(\w+)\s+\•\s+\d+/");

            if (!empty(trim($cabin))) {
                $s->extra()
                    ->cabin($cabin);
            } elseif (!empty($mainCabin)) {
                $s->extra()
                    ->cabin($mainCabin);
            }
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);

        $in = [
            "#^\w+\,\s*(\w+)\s+(\d+)\,\s+([\d\:]+\s*A?P?M)$#", //Tue, Nov 19, 6:30 AM
        ];
        $out = [
            "$2 $1 $year, $3",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
