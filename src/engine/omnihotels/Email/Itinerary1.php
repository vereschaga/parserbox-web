<?php

namespace AwardWallet\Engine\omnihotels\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "omnihotels/it-1.eml, omnihotels/it-1693999.eml, omnihotels/it-1903590.eml, omnihotels/it-2.eml, omnihotels/it-2656897.eml, omnihotels/it-3142345.eml, omnihotels/it-3199626.eml";
    public static $dict = [
        'en' => [],
    ];

    private $detectFrom = "omnihotels.com";

    private $lang = 'en';

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && stripos($headers["from"], $this->detectFrom) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), '@omnihotels.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHtml($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(., 'CONFIRMATION #') or contains(., 'CANCELLATION #')]", null, true, "#\#\s*([A-Z\d]{5,})#"))
            ->traveller($this->http->FindSingleNode("//strong[normalize-space(.)='GUEST']/following::strong[not(contains(., 'Guest'))][1]"))
        ;

        if ($this->http->FindSingleNode("//text()[contains(., 'CANCELLATION #')][1]")) {
            $h->general()
                ->status('Cancelled')
                ->cancelled(true);
        }

        // Hotel
        $hotelText = implode("\n", $this->http->FindNodes("//strong[normalize-space() = 'GUEST']/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("#^\s*(?<name>.+)\s+(?<addr>[\s\S]+?)\s+(?:Phone|Fax)#", $hotelText, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(str_replace("\n", ', ', $m['addr']))
            ;
        }

        if (preg_match("#Phone\s*:\s*([\d\-\+\(\( ]{5,})\s*\n#", $hotelText, $m)) {
            $h->hotel()->phone($m[1]);
        }

        if (preg_match("#Fax\s*:\s*([\d\-\+\(\( ]{5,})\s*\n#", $hotelText, $m)) {
            $h->hotel()->fax($m[1]);
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//strong[normalize-space()='ARRIVING:']/following::text()[normalize-space()][1]")
                    . ' ' . $this->http->FindSingleNode("//strong[normalize-space()='CHECK IN TIME:']/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//strong[normalize-space()='DEPARTING:']/following::text()[normalize-space()][1]")
                    . ' ' . $this->http->FindSingleNode("//strong[normalize-space()='CHECK OUT TIME:']/following::text()[normalize-space()][1]")))
            ->guests($this->http->FindSingleNode("//strong[normalize-space()='NUMBER OF GUESTS']/following::text()[normalize-space()][1]", null, true, "#(\d+)\s*Adult\(s\)#"))
            ->kids($this->http->FindSingleNode("//strong[normalize-space()='NUMBER OF GUESTS']/following::text()[normalize-space()][1]", null, true, "#(\d+)\s*Child\(ren\)#"))
        ;

        // Room
        $r = $h->addRoom();
        $r
            ->setType($this->http->FindSingleNode("//strong[normalize-space(.)='ACCOMMODATIONS']/following::text()[normalize-space()][1]"))
            ->setRate($this->http->FindSingleNode("//strong[contains(., 'ROOM RATE')]/following::tr[normalize-space() and not(.//tr)][1][not(contains(., 'Subtotal'))]/td[2]"))
            ->setRateType($this->http->FindSingleNode("//strong[contains(., 'ROOM RATE')]/following::tr[normalize-space() and not(.//tr)][1]/td[1][not(contains(., 'Subtotal'))]", null, true, "#(.+?)\s+\d+ nights?\s*$#"), true, true)
        ;

        // Cancellation
        $cancellation = $this->http->FindSingleNode("//text()[contains(., 'CANCELLATION:')]/following::text()[normalize-space(.)][1]");

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);
            //  Cancel by 12PM on 09/30/2018 to avoid $257.66 penalty.
            if (preg_match("#Cancel by ([\dapm]+) on ([\d\/]+) to avoid#i", $cancellation, $m)) {
                $h->booked()->deadline($this->normalizeDate($m[2] . ' ' . $m[1]));
            }
        }

        // Price
        $total = $this->http->FindSingleNode("(//text()[{$this->eq('Grand Total')}]/ancestor::td[1]/following-sibling::td[1])[1]");

        if (preg_match("#(?<total>\d[\d\.\,]*)\s*(?<cur>[A-Z]{3})\b#", $total, $m) || preg_match("#(?<cur>[A-Z]{3})\s*(?<total>\d[\d\.\,]*)\b#", $total, $m)) {
            $h->price()
                ->total($m['total'])
                ->currency($m['cur'])
            ;
        }
        $rate = $this->http->FindSingleNode("(//text()[{$this->starts('Subtotal (')}]/ancestor::tr[1])[1]");

        if (preg_match("#\((?<nights>\d+) nights?\)\s+(?<total>\d[\d\.]*)\s*(?<cur>[A-Z]{3})\b#", $rate, $m) || preg_match("#\((?<nights>\d+) nights?\)\s+(?<cur>[A-Z]{3})\s*(?<total>\d[\d\.]*)\b#", $rate, $m)) {
            if (empty($r->getRate())) {
                $r->setRate($m['total'] / $m['nights'] . ' ' . $m['cur']);
            }
            $h->price()->cost($m['total']);
        }

        $taxes = $this->http->FindSingleNode("(//text()[{$this->starts('Taxes')}]/ancestor::tr[1])[1]");

        if (preg_match("#(?<total>\d[\d\.]*)\s*(?<cur>[A-Z]{3})\b#", $taxes, $m) || preg_match("#(?<cur>[A-Z]{3})\s*(?<total>\d[\d\.]*)\b#", $taxes, $m)) {
            $h->price()->tax($m['total']);
        }
        $fees = $this->http->FindSingleNode("(//text()[{$this->starts('Fees')}]/ancestor::tr[1])[1]");

        if (preg_match("#(?<total>\d[\d\.]*)\s*(?<cur>[A-Z]{3})\b#", $fees, $m) || preg_match("#(?<cur>[A-Z]{3})\s*(?<total>\d[\d\.]*)\b#", $fees, $m)) {
            $h->price()->fee('Fees', $m['total']);
        }

        return $email;
    }

    private function normalizeDate($date)
    {
        $in = [
            //07/12/2017 4:00 PM
            '#(\d+)\/(\d+)\/(\d+)\s+(\d+:\d+\s+[ap]m)#i',
            //09/30/2018 12PM
            '#(\d+)\/(\d+)\/(\d+)\s+(\d+)\s*([ap]m)\s*#i',
        ];
        $out = [
            '$2.$1.$3 $4',
            '$2.$1.$3 $4:00$5',
        ];
        $date = preg_replace($in, $out, $date);

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
