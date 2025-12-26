<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Engine\MonthTranslate;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "@bestwestern";
    public $reSubject = [
        "en" => "Your reservation at the Best Western",
    ];
    public $reBody = 'bestwestern';
    public $reBody2 = [
        "en" => "ROOM INFORMATION",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "";

    public function parseHtml()
    {
        $it = [];

        $it['Kind'] = "R";

        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[{$this->contains($this->t('BOOKING NO:'))}]", null, true, "#{$this->opt($this->t('BOOKING NO:'))}\s+([A-Z\d]+)#");

        $it['HotelName'] = $this->nextText($this->t("CONFIRMATION OF BOOKING"));

        $it['Address'] = implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('CHECK-IN:'))}]/ancestor::tr[2]/td[1]//tr[position()>1]"));

        $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText($this->t("CHECK-IN:"))));

        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText($this->t("CHECK-OUT:"))));

        $it['Phone'] = $this->nextText($this->t("CONTACT NUMBER:"));

        $it['GuestNames'] = $this->http->FindNodes("(//text()[{$this->starts($this->t('Guest Names'))}]/ancestor::td[1]//text()[normalize-space(.)!=''])[position()>1]");

        $it['Guests'] = $this->re("#(\d+)\s+{$this->opt($this->t('Adult'))}#i", $this->nextText($this->t("Occupancy:")));

        $rooms = $this->http->FindNodes("//text()[translate(normalize-space(.), '1234567890', 'dddddddddd')='" . $this->t("ROOM") . " d']", null, "#\d+#");
        $it['Rooms'] = !empty($rooms) ? $rooms[count($rooms) - 1] : null;

        $it['RateType'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Rate Type:")) . "]/following::text()[normalize-space(.)][1]");

        $it['CancellationPolicy'] = $this->nextText($this->t("Small print for room"));

        $it['RoomType'] = array_map("trim", $this->http->FindNodes("//text()[translate(normalize-space(.), '1234567890', 'dddddddddd')='" . $this->t("ROOM") . " d']/ancestor::td[1]", null, "#{$this->opt($this->t('ROOM'))}\s+\d+[\s\-]+(.+)#"));

        $tot = $this->getTotalCurrency($this->nextText($this->t('TOTAL PRICE')));

        if (!empty($tot['Total'])) {
            $it['Total'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        return [$it];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

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

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = $this->parseHtml();

        $result = [
            'emailType'  => 'YourReservation' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
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
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)/(\d+)/(\d{4}), (\d+:\d+)$#", //29/04/2016, 14:00
        ];
        $out = [
            "$1.$2.$3, $4",
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->starts($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
