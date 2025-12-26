<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3510328 extends \TAccountCheckerExtended
{
    public $mailFiles = "asia/it-10185609.eml, asia/it-1751552.eml, asia/it-3510328.eml";
    public $reBody = 'Cathay Pacific';
    public $reBody2 = "Departure";
    public $reSubject = "Confirmation";
    public $reFrom = "@dragonair.com";
    public $lang = 'en';
    public $subject;

    public function parseFlight(Email $email, $text)
    {
        $f = $email->add()->flight();

        $confNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Reference Number:')]/ancestor::tr[1]", null, true, "/Booking Reference Number\:\s*([A-Z\d]{6})/");

        if (empty($confNumber)) {
            $confNumber = $this->re("#Booking reference\s*\:\s*([A-Z\d]{6})#u", $this->subject);
        }
        $f->general()
            ->confirmation($confNumber);

        $f->general()
            ->travellers($this->nicePax($this->http->FindNodes("//text()[normalize-space()='Frequent Flyer Programme']/ancestor::table[1]/descendant::tr/td[1][not(contains(normalize-space(), 'Name'))]")));

        $accounts = $this->http->FindNodes("//text()[normalize-space()='Frequent Flyer Programme']/ancestor::table[1]/descendant::tr/td[2][not(contains(normalize-space(), 'Frequent Flyer Programme') or contains(normalize-space(), 'N/A'))]");

        foreach ($accounts as $account) {
            $pax = $this->http->FindSingleNode("//text()[{$this->eq($account)}]/ancestor::tr[1]/descendant::td[1]");

            if (!empty($pax)) {
                $f->addAccountNumber($account, false, $this->nicePax($pax));
            } else {
                $f->addAccountNumber($account, false);
            }
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Grand total']/following::text()[normalize-space()][1]");

        if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,\']+)/", $price, $m)) {
            $currency = $this->currency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $spentAwards = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total:')]/ancestor::tr[1]", null, true, "/Total\:\s*([\d\,\.]+)\s+[+]/");

        if (!empty($spentAwards)) {
            $f->price()
                ->spentAwards($spentAwards);
        }

        $xpath = "//*[normalize-space(text())='Flight']/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//*[normalize-space(text())='Flight']/ancestor::table[1]/tbody/tr";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->http->FindSingleNode("./td[1]", $root));

            $s = $f->addSegment();

            $s->airline()
                ->number($this->http->FindSingleNode("./td[2]", $root, true, "#\w{2}(\d+)#"))
                ->name($this->http->FindSingleNode("./td[2]", $root, true, "#(\w{2})\d+#"))
                ->operator($this->http->FindSingleNode("./td[3]", $root));

            $seats = array_filter($this->http->FindNodes("//text()[{$this->starts($s->getAirlineName() . $s->getFlightNumber() . '/ ')}]", null, "#\/\s*(\d+[A-Z])#"));

            if (count($seats) == 0) {
                $seats = array_filter($this->http->FindNodes("//text()[{$this->starts($s->getAirlineName() . $s->getFlightNumber() . ' / ')}]", null, "#\/\s*(\d+[A-Z])#"));
            }

            foreach ($seats as $seat) {
                $pax = $this->http->FindSingleNode("//text()[{$this->starts($s->getAirlineName() . $s->getFlightNumber() . '/ ' . $seat)}]/ancestor::tr[1]/descendant::td[1]");

                if (empty($pax)) {
                    $pax = $this->http->FindSingleNode("//text()[{$this->starts($s->getAirlineName() . $s->getFlightNumber() . ' / ' . $seat)}]/ancestor::tr[1]/descendant::td[1]");
                }

                if (!empty($pax)) {
                    $s->addSeat($seat, true, true, $this->nicePax($pax));
                } else {
                    $s->addSeat($seat);
                }
            }

            $s->departure()
                ->date(strtotime($this->http->FindSingleNode("./td[4]", $root, true, "#\d+:\d+#"), $date))
                ->code($this->http->FindSingleNode("./td[4]", $root, true, "#[A-Z]{3}#"));

            $s->arrival()
                ->code($this->http->FindSingleNode("./td[5]", $root, true, "#[A-Z]{3}#"));

            $time = $this->http->FindSingleNode("./td[5]", $root, true, "#\d+:\d+.*#");

            if (preg_match("#\s*(\d:\d+)\s*((?:[+\-]\s*\d+))\s*$#", $time, $m)) {
                $s->arrival()
                    ->date(strtotime($m[2] . ' days', strtotime($m[1], $date)));
            } else {
                $s->arrival()
                    ->date(strtotime($time, $date));
            }

            if ($this->http->XPath->query("./descendant::td", $root)->length == 8) {
                $s->extra()
                    ->aircraft($this->http->FindSingleNode("./td[7]", $root))
                    ->cabin($this->http->FindSingleNode("./td[8]", $root, true, "#(\w+)\s*\(\w\)#"))
                    ->bookingCode($this->http->FindSingleNode("./td[8]", $root, true, "#\w+\s*\((\w)\)#"))
                    ->stops($this->http->FindSingleNode("./td[6]", $root));

                $duration = $this->http->FindSingleNode("./td[6]", $root, true, "#\d+:\d+.*#");

                if (!empty($duration)) {
                    $s->setDuration($duration);
                }
            } else {
                $s->extra()
                    ->aircraft($this->http->FindSingleNode("./td[8]", $root))
                    ->cabin($this->http->FindSingleNode("./td[9]", $root, true, "#(\w+)\s*\(\w\)#"))
                    ->bookingCode($this->http->FindSingleNode("./td[9]", $root, true, "#\w+\s*\((\w)\)#"))
                    ->duration($this->http->FindSingleNode("./td[7]", $root))
                    ->stops($this->http->FindSingleNode("./td[6]", $root));
            }
        }
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["from"], $this->reFrom) !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;

        $this->subject = $parser->getSubject();

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        $this->parseFlight($email, strip_tags($body));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function nicePax($pax)
    {
        return preg_replace("/^(?:Mrs|Mr|Ms)/", "", $pax);
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
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

        return $s;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
