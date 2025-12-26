<?php

namespace AwardWallet\Engine\supershuttle\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Transfer extends \TAccountChecker
{
    public $mailFiles = "supershuttle/it-46239970.eml";

    private $subjects = [
        'en' => ['Booking Confirmation'],
    ];

    private $lang = 'en';

    private $detects = [
        'en'  => 'SuperShuttle values your safety',
        'en2' => 'View your trip summary and receipt below',
    ];

    private $from = '/[@\.]supershuttle\.com/i';

    private $prov = 'supershuttle';

    private static $dict = [
        'en' => [
            'Airline'       => ['Airline', 'AIRLINE'],
            'Fare'          => ['Fare', 'FARE'],
            'Receipt total' => ['Receipt total', 'RECEIPT TOTAL', 'Receipt Total'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $lang => $detect) {
            if (false !== stripos($body, $detect) || 0 < $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect}')]")->length) {
                $this->lang = substr($lang, 0, 2);
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'SuperShuttle') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (0 < $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect}')]")->length) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email): void
    {
        $total = $this->http->FindSingleNode("//td[{$this->eq($this->t('Receipt total'))} and not(.//td)]/following-sibling::td[1]");

        if (($tot = $this->re('/^(?:[^\d)(]+)?(\d[,.\'\d]*)$/', $total))) {
            // $24.42
            $email->price()
                ->total($tot)
                ->currency($this->currency($total));
        }

        $xpath = "//text()[starts-with(normalize-space(.), 'Confirmation Number')]/ancestor::table[{$this->contains($this->t('Airline'))}][1]";
        $its = $this->http->XPath->query($xpath);

        if (0 === $its->length) {
            $this->logger->alert("Segments did not found by xpath: {$xpath}");
        }

        foreach ($its as $it) {
            $t = $email->add()->transfer();

            $fare = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Fare'))} and not(.//td)]/following-sibling::td[1]", $it);

            if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d]*)$/', $fare, $m)) {
                $t->price()
                    ->cost($m['amount'])
                    ->currency($this->currency($m['currency']));
            }

            $taxs = [
                'Web/Group discount', 'WEB/GROUP DISCOUNT',
                'Airport Access Fee', 'AIRPORT ACCESS FEE',
                'Airport Entry Fee', 'AIRPORT ENTRY FEE',
                'Convenience Fee', 'CONVENIENCE FEE',
                'Tip', 'TIP',
            ];

            foreach ($taxs as $tax) {
                $feeCharge = $this->http->FindSingleNode("descendant::td[{$this->eq($tax)} and not(.//td)]/following-sibling::td[1]", $it, true, '/^(?:[^\d)(]+)?(\d[,.\'\d]*)$/');

                if ($feeCharge !== null) {
                    $t->price()->fee($tax, $feeCharge);
                }
            }

            if ($paxs = $this->http->FindNodes("descendant::td[translate('d', '0123456789', '0123456789') and not(.//td) and contains(normalize-space(.), 'Passenger')]/preceding-sibling::td[normalize-space(.)]", $it)) {
                $t->general()
                    ->travellers($paxs);
            }

            if ($conf = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(.), 'Confirmation Number')][1]/following::text()[normalize-space(.)][1]", $it)) {
                $t->general()
                    ->confirmation($conf);
            }

            $date = strtotime($this->http->FindSingleNode("descendant::p[starts-with(normalize-space(.), 'Pickup time:')]/preceding-sibling::*[normalize-space(.)][1][name() = 'h2' or name() = 'p']", $it, true, '/\w+,?\s+\w+\s+\d{1,2},? \d{2,4}/'));

            $s = $t->addSegment();

            $re = '/(?<Code>[A-Z]{3})[ ]+\-[ ]+(?<Name>.+)/';

            $xpathFromTo = "descendant::tr[ count(*)=3 and *[1][normalize-space()] and *[2]/descendant::img and *[3][normalize-space()] ]";

            $dep = $this->http->FindSingleNode($xpathFromTo . "/*[1]/p[normalize-space()][last()]", $it);
            $pickUpTime = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(.), 'Pickup time:')][1]", $it, true, '/(\d{1,2}:\d{2} [AP]M)/');

            if (preg_match($re, $dep, $m)) {
                $s->departure()
                    ->code($m['Code'])
                    ->name($m['Name'])
                    ->date(strtotime($pickUpTime, $date))
                ;
            } else {
                $s->departure()
                    ->name($dep)
                    ->date(strtotime($pickUpTime, $date))
                ;
            }

            $arr = $this->http->FindSingleNode($xpathFromTo . "/*[3]/p[normalize-space()][last()]", $it);

            if (preg_match($re, $arr, $m)) {
                $s->arrival()
                    ->code($m['Code'])
                    ->name($m['Name'])
                    ->noDate()
                ;
            } else {
                $s->arrival()
                    ->name($arr)
                    ->noDate()
                ;
            }
        }
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
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
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
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
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }
}
