<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BeingTicketed extends \TAccountChecker
{
    public $mailFiles = "";
    public $detectSubjects = [
        'flight is being ticketed',
        'flight is confirmed',
    ];

    public $detectBody = [
        'en' => ['Your flight is being ticketed', 'Your flight is confirmed'],
    ];

    public $lang = 'en';
    public static $dictionary = [
        "en" => [
//            'TripActions booking ID:' => '',
//            'Taxes and Fees' => '',
//            '' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@tripactions.com') === false) {
            return false;
        }
        foreach ($this->detectSubjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.tripactions.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tripactions\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
//        ($this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\d]{6})/"));

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[".$this->eq($this->t("TripActions booking ID:"))."]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,7})\s*$/"));


        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[".$this->eq($this->t("Confirmation:"))."]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,7})\s*$/"));


//        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'You canceled your Flight')]")->length > 0) {
//            $f->general()
//                ->status('cancelled')
//                ->cancelled();
//        }

        $traveller = $this->http->FindSingleNode("//text()[".$this->eq($this->t('Traveler'))."]/following::text()[normalize-space()][2]");
        if (!empty($travellers) || !$f->getCancelled()) {
            $f->general()
                ->traveller($traveller,true);
        }

        // Price
        $total = $this->http->FindSingleNode("//td[".$this->eq($this->t("Total"))."]/following-sibling::td[normalize-space()][1]");
        if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)
            || preg_match("/^\s*[^\d\s]{0,5}\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $total, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $m['curr']))
                ->currency($m['curr'])
            ;
        }
        $cost = $this->http->FindSingleNode("//td[".$this->eq($this->t("Subtotal"))."]/following-sibling::td[normalize-space()][1]");
        if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $cost, $m)
            || preg_match("/^\s*[^\d\s]{0,5}\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $cost, $m)) {
            $f->price()
                ->cost(PriceHelper::parse($m['amount'], $m['curr']))
            ;
        }
        $feesXpath = $this->http->XPath->query("//td[".$this->eq($this->t("Taxes and Fees"))."][following-sibling::td[normalize-space()][1]]");
        foreach ($feesXpath as $fx) {
            $name = $this->http->FindSingleNode(".", $fx);
            $amount = $this->http->FindSingleNode("following-sibling::td[normalize-space()][1]", $fx);
            if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $amount, $m)
                || preg_match("/^\s*[^\d\s]{0,5}\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $amount, $m)) {
                $f->price()
                    ->fee($name, PriceHelper::parse($m['amount'], $m['curr']));
            }
        }
//        $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal']/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/([\d\,\.]+)/");
//        $fee = $this->http->FindSingleNode("//text()[normalize-space()='Trip Fee']/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/([\d\,\.]+)/");
//        $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes']/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/([\d\,\.]+)/");
//        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/([A-Z]{3})/");
//
//        if (!empty($total) && !empty($currency)) {
//            $f->price()
//                ->total(cost($total))
//                ->currency($currency);
//        }
//
//        if (!empty($cost)) {
//            $f->price()
//                ->cost(cost($cost));
//        }
//
//        if (!empty($tax)) {
//            $f->price()
//                ->tax(cost($tax));
//        }
//
//        if (!empty($fee)) {
//            $f->price()
//                ->fee('Trip Fee', cost($fee));
//        }

//        $ticket = $this->http->FindSingleNode("//text()[normalize-space()='E-Ticket:' or normalize-space()='e-Ticket:']/following::text()[normalize-space()][1]", null, true, "/^([\d\/]+)$/");
//
//        if (!empty($ticket)) {
//            $f->issued()
//                ->ticket($ticket, false);
//        }

        $timef = "contains(translate(normalize-space(.), '1234567890', 'dddddddddd'), 'd:dd')";
        $xpath = "//tr[td[2][.//img and not(normalize-space())] and td[1][{$timef}] and td[3][{$timef}]  and count(*[{$timef}]) = 2 ]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {

            $s = $f->addSegment();

            // Airline
            $node = implode(' ', $this->http->FindNodes("preceding::tr[not(.//tr)][normalize-space()][3]//text()[normalize-space()]", $root));
            if (preg_match("/ (?<name>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<number>\d{1,5})\s*$/u", $node, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $node = $this->http->FindSingleNode("preceding::tr[not(.//tr)][normalize-space()][2]", $root);
            if (preg_match("/(.+?)\s*\(\s*([A-Z]{1,2})\s*\)\s*$/u", $node, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2])
                ;
            }

            $route = implode("\n", $this->http->FindNodes("following::tr[not(.//tr)][normalize-space()][1]//text()[normalize-space()]", $root));
            if (preg_match("/^\s*(.+?)\s*\n([A-Z]{3})\n\s*(.+?)\s*\n([A-Z]{3})\s*$/s", $route, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
                $s->arrival()
                    ->name($m[3])
                    ->code($m[4]);

                //  BWI → CLT
                $seats = array_filter($this->http->FindNodes("//text()[normalize-space()='".$m[2]." → ".$m[4]."']/preceding::text()[normalize-space()][1]",
                    null, "/^\s*(\d{1,3}[A-Z])\s*$/"));
                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }

            $date = $this->http->FindSingleNode("preceding::tr[not(.//tr)][normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][1]", $root);
            $time = $this->http->FindSingleNode("*[1]", $root);
            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $time));
            }

            $date = $this->http->FindSingleNode("preceding::tr[not(.//tr)][normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][2]", $root);
            $time = $this->http->FindSingleNode("*[3]", $root);
            if (!empty($date) && !empty($time)) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $time));
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
//            "#^\w+\,\s*(\d+\s*\w+)\,\s*([\d\:]+\s*A?M?)$#", //Sat, 20 Mar, 7:20 AM
        ];
        $out = [
//            "$1 , $2",
        ];
        $str = preg_replace($in, $out, $str);

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            }
//        }

        return strtotime($str);
    }
}
