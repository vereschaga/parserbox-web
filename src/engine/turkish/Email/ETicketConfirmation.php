<?php

namespace AwardWallet\Engine\turkish\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "turkish/it-10113431.eml";
    public $reFrom = "support@airtkt.com";
    public $reSubject = [
        "en"=> "E-Ticket Confirmation For Booking Id",
    ];
    public $reBody = ['www.CheapFareGuru.com', 'www.AirTkt.com', 'www.LastMinuteFareDeal.com'];
    public $reBody2 = [
        "en"=> "Depart:",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(Email $email)
    {
        $it = [];
        $f = $email->add()->flight();

        $conirmation = $this->nextText("Airline Confirmation No.:");

        if (!empty($conirmation)) {
            $f->general()
                ->confirmation($conirmation);
        } else {
            $confs = $this->http->FindNodes("//text()[contains(normalize-space(), 'Confirmation No:') or contains(normalize-space(), 'Confirmation No.:')]/ancestor::tr[1]", null, "/Confirmation\s*No\.?\:\s*([A-Z\d]{6})$/");

            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf);
            }
        }

        $email->ota()
            ->confirmation($this->nextText("Agency Booking ID:"));

        $f->general()
            ->travellers($this->http->FindNodes("//text()[" . $this->contains("Passenger(s)") . "]/ancestor::tr[1]/following-sibling::tr/td[1]/descendant::text()[normalize-space(.)]"));

        $tikects = $this->http->FindNodes("//text()[" . $this->contains("Passenger(s)") . "]/ancestor::tr[1]/following-sibling::tr/td[2]/descendant::text()[normalize-space(.)]");

        if (count($tikects) > 0) {
            $f->setTicketNumbers($tikects, false);
        }

        $total = $this->amount($this->nextText("Total Charge:"));
        $currency = $this->currency($this->nextText("Total Charge:"));

        if (!empty($currency) && $total !== null) {
            $f->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        }

        $status = $this->nextText("Status:");

        if (!empty($status)) {
            $f->setStatus($status);
        }

        $xpath = "//text()[" . $this->eq("Depart:") . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][1]", $root)));

            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode(".//text()[" . $this->contains("Flight ") . "]", $root, true, "#Flight (\w{2})\s+\d+$#"))
                ->number($this->http->FindSingleNode(".//text()[" . $this->contains("Flight ") . "]", $root, true, "#Flight \w{2}\s+(\d+)$#"));

            $s->departure()
                ->name($this->http->FindSingleNode(".//text()[" . $this->eq("Depart:") . "]/ancestor::td[1]/following-sibling::td[1]", $root))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq("Depart:") . "]/ancestor::tr[1]/following-sibling::tr[2]/td[2]", $root)), $date))
                ->noCode();

            $depTerminal = $this->http->FindSingleNode(".//text()[" . $this->eq("Depart:") . "]/ancestor::tr[1]/following-sibling::tr[3]/td[2]", $root, true, "#Terminal (.+)#");

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $s->arrival()
                ->name($this->http->FindSingleNode(".//text()[" . $this->eq("Arrive:") . "]/ancestor::td[1]/following-sibling::td[1]", $root))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq("Arrive:") . "]/ancestor::tr[1]/following-sibling::tr[2]/td[2]", $root)), $date))
                ->noCode();

            $arrTerminal = $this->http->FindSingleNode(".//text()[" . $this->eq("Arrive:") . "]/ancestor::tr[1]/following-sibling::tr[3]/td[2]", $root, true, "#Terminal (.+)#");

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $aircraft = $this->http->FindSingleNode(".//text()[" . $this->eq("Aircraft:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $miles = $this->http->FindSingleNode(".//text()[" . $this->eq("Mileage") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (!empty($miles)) {
                $s->extra()
                    ->miles($miles);
            }

            $cabin = $this->http->FindSingleNode(".//text()[" . $this->eq("Class:") . "]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#^\w-(.+)#");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $bookingCode = $this->http->FindSingleNode(".//text()[" . $this->eq("Class:") . "]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#^(\w)(?:-|$)#");

            if (!empty($bookingCode)) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }

            $seat = $this->http->FindSingleNode(".//text()[" . $this->eq("Seat:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }

            $duration = $this->http->FindSingleNode(".//text()[" . $this->eq("Travel Time:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $meal = $this->http->FindSingleNode(".//text()[" . $this->eq("Meal:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $stops = $this->http->FindSingleNode(".//text()[" . $this->eq("Stopovers:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            if ($stops !== null) {
                $s->extra()
                    ->stops($stops);
            }
        }
    }

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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody[0]) === false && strpos($body, $this->reBody[1]) === false && strpos($body, $this->reBody[2]) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

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

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+ (\d+) ([^\s\d]+) (\d{4})$#", //Saturday 23 DEC 2017
            "#^[^\s\d]+, (\d+) ([^\s\d]+) (\d{4}) (\d+:\d+ [AP]M)$#", //Sunday, 24 DEC 2017 5:30 PM
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
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
}
