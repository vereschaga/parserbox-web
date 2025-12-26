<?php

namespace AwardWallet\Engine\payless\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CarRentalReservation extends \TAccountChecker
{
    public $mailFiles = "payless/it-1828091.eml, payless/it-2514217.eml, payless/it-31735569.eml, payless/it-8920926.eml";

    public static $dictionary = [
        "en" => [
            'ESTIMATED TOTAL' => ['ESTIMATED TOTAL', 'Estimated Total'],
        ],
    ];

    public $lang = "en";
    private $reFrom = "@paylesscar.com";
    private $reSubject = [
        "en"=> "Payless Car Rental Reservation",
    ];
    private $reBody = 'PaylessCar.com';
    private $reBody2 = [
        "en"=> "Pick-up:",
    ];

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
        $body = preg_replace("#\s*\n\s*#", " ", $parser->getHTMLBody());

        if (strpos($body, $this->reBody) === false && $this->http->XPath->query("//img[@alt='Payless Car Rental' or contains(@src,'.paylesscar.com/')]")->length === 0) {
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
        $email->setType($class[count($class) - 1] . ucfirst($this->lang));

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

    private function parseHtml(Email $email)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->nextText($this->t("Confirmation number:")))
            ->traveller($this->nextText($this->t("Renter's name:")));

        $r->pickup()
            ->date(strtotime($this->normalizeDate($this->nextText($this->t("Pick-up:"), null, 2))))
            ->openingHours(implode(" ", $this->http->FindNodes("//text()[normalize-space()='Hours:']/ancestor::table[1]/descendant::text()[normalize-space()!=''][position()>1]")));

        $r->dropoff()
            ->date(strtotime($this->normalizeDate($this->nextText($this->t("Drop-off:"), null, 2))))
            ->location($this->nextText($this->t("Drop-off:")));

        $node = $this->http->FindSingleNode("//text()[" . $this->eq("Map") . "]/preceding::text()[normalize-space(.)][1]");

        if (!empty($node)) {
            $pickupLocation = implode(", ", $this->http->FindNodes("//text()[" . $this->eq("Map") . "]/preceding::text()[position()>1 and preceding::text()[" . $this->eq("Location:") . "]]"));

            if (empty($pickupLocation) && preg_match('/(.+)\s+([\d-]+)/s', $node, $m)) {
                $pickupLocation = $m[1];
                $pickupPhone = $m[2];
            } else {
                $pickupPhone = $node;
            }
            $r->pickup()
                ->location($pickupLocation)
                ->phone($pickupPhone);
        } else {
            $node = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Location:']/ancestor::td[1]/descendant::text()[normalize-space()!=''][position()>1]"));

            if (preg_match("/(.+?)(?:\n([\d\-\(\)\+ ]+))?$/s", $node, $m)) {
                $r->pickup()
                    ->location(preg_replace("/\s+/", " ", $m[1]));

                if (isset($m[2]) && !empty(trim($m[2]))) {
                    $r->pickup()
                        ->phone(trim($m[2]));
                }
            }
        }

        if ($this->nextText($this->t("Pick-up:")) == $this->nextText($this->t("Drop-off:"))) {
            $r->dropoff()->same();
        }

        // car
        $type = $this->nextText($this->t("Car Type:"));

        if (strpos($type, 'similar') !== false) {
            $r->car()->model($type);
        } else {
            $r->car()->type($type);
        }

        // price
        $total = trim($this->nextText($this->t("ESTIMATED TOTAL")) . ' ' . $this->nextText($this->t("ESTIMATED TOTAL"), null, 2));
        $r->price()
            ->total($this->amount($total))
            ->currency($this->currency($total));
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
            "#^(\d{4}-\d+-\d+T\d+:\d+:\d+)$#", //2017-10-27T20:00:00
            "#^[^\s\d]+, ([^\s\d]+) (\d+), (\d{4}) @ (\d+:\d+ [AP]M)$#", //Mon, Aug 11, 2014 @ 05:00 PM
        ];
        $out = [
            "$1",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->http->log($str);
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
