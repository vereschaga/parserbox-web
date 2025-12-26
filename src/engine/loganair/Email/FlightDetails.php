<?php

namespace AwardWallet\Engine\loganair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightDetails extends \TAccountChecker
{
    public $mailFiles = "loganair/it-195814922.eml, loganair/it-66963839.eml";
    public $subjects = [
        '/Thank you for booking with us\, here are your flight details$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Passengers' => ['Passengers', 'Travellers'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@loganair.co.uk') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Thank you for choosing to fly with Loganair')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking reference'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Additional items'))}]")->count() > 0/*
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Charges'))}]")->count() > 0*/;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]loganair\.co\.uk$/', $from) > 0;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = array_filter($this->http->FindNodes("//tr[{$this->contains($this->t('Flight(s):'))} and {$this->contains($this->t('Seat:'))} and {$this->contains($this->t('E‐ticket:'))}]/preceding::text()[contains(normalize-space(), '/')][1]", null, "/^([A-Z\/]+)$/"));

        if (count($travellers) == 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Flight(s):'))}]/preceding::text()[contains(normalize-space(), '/')][1]", null, "/^([A-Z\/]+)$/"));
        }
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference:'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]{6})$/"), 'Booking reference')
            ->travellers(preg_replace("/(?:MRS|MS|MR|MISS)$/u", "", $travellers), true);

        $tickets = $this->http->FindNodes("//tr[{$this->contains($this->t('Flight(s):'))} and {$this->contains($this->t('Seat:'))} and {$this->contains($this->t('E‐ticket:'))}]/following-sibling::tr[1]/descendant::text()[contains(normalize-space(), '/')]");

        if (count($tickets) == 0) {
            $tickets = $this->http->FindNodes("//text()[{$this->contains($this->t('Flight(s):'))}]/ancestor::tr[1]/following::tr[contains(normalize-space(), '/')][1]/descendant::text()[contains(normalize-space(), '/')]");
        }
        $f->issued()
            ->tickets($tickets, false);

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Charges'))}]/following::table[{$this->contains($this->t('Description:'))} and {$this->contains($this->t('Total'))}][1]/descendant::text()[{$this->eq($this->t('Price:'))}]/following::text()[normalize-space()][1]");
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Charges'))}]/following::table[{$this->contains($this->t('Description:'))} and {$this->contains($this->t('Total'))}][1]/descendant::text()[{$this->eq($this->t('Currency:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z]{3})$/");

        if (!empty($total) && !empty($currency)) {
            $f->price()
                ->total($total)
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Charges'))}]/following::table[{$this->contains($this->t('Description:'))} and {$this->contains($this->t('Fare'))}][1]/descendant::text()[{$this->eq($this->t('Price:'))}]/following::text()[normalize-space()][1]");

            if (!empty($cost)) {
                $f->price()
                    ->cost($cost);
            }

            $xpathFee = "//text()[normalize-space(.)='Charges']/following::table[contains(normalize-space(.), 'Description:') and not(contains(normalize-space(.), 'Fare') or contains(normalize-space(.), 'Total'))]";
            $fees = $this->http->XPath->query($xpathFee);

            foreach ($fees as $fee) {
                $feeName = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Description:']/following::text()[normalize-space()][1]", $fee);
                $feeCharge = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Price:']/following::text()[normalize-space()][1]", $fee);

                $f->price()
                    ->fee($feeName, $feeCharge);
            }
        }

        $xpath = "//text()[starts-with(normalize-space(), 'Flight ')]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight'))}]", $root, true, "/^{$this->opt($this->t('Flight'))}\s+([A-Z]{2})\d{2,4}$/"))
                ->number($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight'))}]", $root, true, "/^{$this->opt($this->t('Flight'))}\s+[A-Z]{2}(\d{2,4})$/"));

            $dateDepArriv = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Date:']/following::text()[normalize-space()][1]", $root);
            $timeDep = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Depart:']/following::text()[normalize-space()][1]", $root);
            $timeArriv = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Arrive:']/following::text()[normalize-space()][1]", $root);

            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()='To:']/preceding::text()[normalize-space()][1]", $root))
                ->date($this->normalizeDate($dateDepArriv . ', ' . $timeDep));

            $depCode = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[{$this->eq($s->getAirlineName() . $s->getFlightNumber())}]/ancestor::tr[2]/descendant::tr[2][contains(normalize-space(), 'Depart:')]", null, true, "/{$this->opt($this->t('Depart:'))}\s*([A-Z]{3})/su");

            if (!empty($depCode)) {
                $s->departure()
                    ->code($depCode);
            } else {
                $s->departure()
                    ->noCode();
            }

            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()='To:']/following::text()[normalize-space()][1]", $root))
                ->date($this->normalizeDate($dateDepArriv . ', ' . $timeArriv));

            $arrCode = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[{$this->eq($s->getAirlineName() . $s->getFlightNumber())}]/ancestor::tr[2]/descendant::tr[2][contains(normalize-space(), 'Arrive:')]", null, true, "/{$this->opt($this->t('Arrive:'))}\s*([A-Z]{3})/su");

            if (!empty($arrCode)) {
                $s->arrival()
                    ->code($arrCode);
            } else {
                $s->arrival()
                    ->noCode();
            }

            $flightsNodes = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[{$this->eq($s->getAirlineName() . $s->getFlightNumber())}]/ancestor::tr[2]/descendant::tr");

            foreach ($flightsNodes as $key => $flightsNode) {
                $key++;

                if ($flightsNode == $s->getAirlineName() . $s->getFlightNumber()) {
                    $seats = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[{$this->eq($s->getAirlineName() . $s->getFlightNumber())}]/following::table[1]/descendant::tr[$key]", null, "/^(\d+[A-Z])$/");

                    if (count(array_filter($seats)) > 0) {
                        $s->setSeats(array_filter($seats));
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseFlight($email);
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

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\s+(\w+)\s+(\d+)\,\s+([\d\:]+)$#', //06 Oct 20, 08:30
        ];
        $out = [
            '$1 $2 $3, $4',
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
}
