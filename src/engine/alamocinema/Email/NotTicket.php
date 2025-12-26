<?php

namespace AwardWallet\Engine\alamocinema\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NotTicket extends \TAccountChecker
{
    use ProxyList;
    public $mailFiles = "alamocinema/it-767914322.eml, alamocinema/it-771367456.eml, alamocinema/it-772274849.eml";

    public $lang = 'en';

    public $year;

    public static $dictionary = [
        "en" => [
            'Seat:'    => ['Seat:', 'Seat'],
            'Theater:' => ['Theater:', 'Theater'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Alamo Intermediate II Holdings')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//img[contains(@src, 'YOUVE_GOT_TICKETS')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Show Starts:'))}]")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('You returned a'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('thanks for returning your'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Return Details'))}]")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('you got a refund of'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your refund has been processed'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Refund Details'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]drafthouse\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/^Fwd(?:Order Confirmation:|Ticket Return Confirmation:|Refund Confirmation:)/", $parser->getSubject())) {
            $email->setIsJunk(true);

            return $email;
        }

        if (preg_match("/^Ticket Return Confirmation:/", $parser->getSubject())
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Booking ID:')]")->length === 0) {
            $email->setIsJunk(true);

            return $email;
        }

        $this->year = date("Y", strtotime($parser->getHeader('date')));

        $this->ParseEvent($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->type()->show();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Seat:'))}]/ancestor::tr[{$this->contains($this->t('Theater:'))}][1]/following::tr[1]/following::text()[normalize-space()][string-length()>5][not(contains(normalize-space(), 'Kid'))][1]");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Booking ID:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking ID:'))}\s*([A-Z\d]{5,})/");
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//img[contains(@src, 'barcode')]/following::text()[string-length()>2][1]", null, true, "/^([A-Z\d]+)$/");
        }

        $e->general()
            ->confirmation($confirmation);

        $traveller = $this->http->FindSingleNode("//img[contains(@src, 'victory-footer-topper.png')]/following::text()[normalize-space()][1]");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hey,')]", null, true, "/^{$this->opt($this->t('Hey,'))}\s*(.+)\!/");
        }

        if (!empty($traveller)) {
            $e->general()
                ->traveller($traveller);
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Refund Details']")->length > 0) {
            $e->general()
                ->cancelled();

            return $email;
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Order']/ancestor::table[2]/following::tr[string-length()>3][not(contains(normalize-space(), 'TX #'))][1]/descendant::tr[last()]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<cost>[\d\.\,\']+)$/", $price, $m)) {
            $e->price()
                ->cost(PriceHelper::parse($m['cost'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal']/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*([\d\.\,\']+)$/");

            if ($cost !== null) {
                $e->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $fee = $this->http->FindSingleNode("//text()[normalize-space()='Convenience Fee']/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*([\d\.\,\']+)$/");

            if ($fee !== null) {
                $e->price()
                    ->fee('Convenience Fee', PriceHelper::parse($fee, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Tax']/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*([\d\.\,\']+)$/");

            if ($tax !== null) {
                $e->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $total = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*([\d\.\,\']+)$/");

            if ($total !== null) {
                $e->price()
                    ->total(PriceHelper::parse($total, $m['currency']));
            }
        }

        $e->setName($this->http->FindSingleNode("//text()[{$this->eq($this->t('Theater:'))}]/preceding::text()[string-length()>5][1]/preceding::text()[normalize-space()][1]"));

        $info = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Theater:')][1]/preceding::text()[string-length()>5][1]");
        $year = $this->re("/MIN\s*\/\s*(?<year>\d{4})/", $info);

        if (!empty($year)) {
            $this->year = $year;
        }

        $date = $this->http->FindSingleNode("//text()[normalize-space()='Doors Open:']/preceding::text()[normalize-space()][1]");

        if (empty($date)) {
            $date = $this->http->FindSingleNode("//text()[normalize-space()='Theater']/ancestor::tr[1]/preceding::text()[string-length()>4][1]", null, true, "/^(\w+\s+\d+\/\d+)\s+[•]/");
        }

        $time = $this->http->FindSingleNode("//text()[normalize-space()='Show Starts:']/following::text()[normalize-space()][1]");

        if (empty($time)) {
            $time = $this->http->FindSingleNode("//text()[normalize-space()='Theater']/ancestor::tr[1]/preceding::text()[string-length()>4][1]", null, true, "/[•]\s+([\d\:]+\s*A?P?M)\s+[•]/");
        }

        if (!empty($date) && !empty($time)) {
            $e->setStartDate($this->normalizeDate($date . ' ' . $this->year . ', ' . $time));
        }

        if (preg_match("#\/\s*(?<min>\d+)\s*MIN\s*\/\s*(?<year>\d{4})#", $info, $m)) {
            $e->setEndDate(strtotime('+' . $m['min'] . ' min', $e->getStartDate()));
        }

        $notes = '';
        $theater = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Seat:'))}]/ancestor::tr[{$this->contains($this->t('Theater:'))}][1]/following::tr[1]/descendant::table[1]");

        if (!empty($theater)) {
            $notes = 'Theater: ' . $theater;
        }

        $row = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Seat:'))}]/ancestor::tr[{$this->contains($this->t('Theater:'))}][1]/following::tr[1]/descendant::table[2]");

        if (!empty($row)) {
            $notes .= ' Row: ' . $row;
        }

        $e->setNotes($notes);

        $seatText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Seat:'))}]/ancestor::tr[{$this->contains($this->t('Theater:'))}][1]/following::tr[1]/descendant::table[last()]");
        $e->setSeats(explode(", ", $seatText));

        $guests = $this->http->FindSingleNode("//text()[normalize-space()='Order']/ancestor::table[2]/following::tr[string-length()>3][1]/descendant::tr[1]", null, true, "/^(\d+)$/");

        if (!empty($guests)) {
            $e->setGuestCount($guests);
        }

        $kidsCount = $this->http->FindSingleNode("//text()[contains(normalize-space(), ' Kid Ticket')]", null, true, "/^(\d+)\s*{$this->opt($this->t(' Kid Ticket'))}/");

        if ($kidsCount !== null) {
            $e->setKidsCount($kidsCount);
        }

        $link = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'DIRECTIONS & PARKING')]/following::*[1]/@href", null, true, "/^(http.+)/");

        if (!empty($link)) {
            $this->http->GetURL($link);
            $url = $this->http->currentUrl();

            $partURL = $this->re("/\/([a-z\d\-]+\/[a-z\d\-]+)[#]/", $url);

            $jsonURL = 'https://drafthouse.com/s/mother/v2/core/venue/' . $partURL;

            $this->http->GetURL($jsonURL);

            $response = $this->http->JsonLog(null, 3, false, 'address');

            $address = $response->data->address->street1 ?? null;
            $address = !empty($street2 = $response->data->address->street2) ? $address . ', ' . $street2 : $address;
            $address = !empty($city = $response->data->address->city) ? $address . ', ' . $city : $address;
            $address = !empty($state = $response->data->address->state) ? $address . ', ' . $state : $address;
            $address = !empty($country = $response->data->address->country) ? $address . ', ' . $country : $address;

            if (!empty($address)) {
                $e->setAddress($address);
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            // Wed 10/9 2024, 7:25PM
            "/^(\w+)\s+(\d+)\/(\d+)\s+(\d{4})\,\s*(\d+\:\d+A?P?M)$/",
        ];
        $out = [
            "$1, $3.$2.$4, $5",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>[[:alpha:]]{2,}), (?<date>\d+ [[:alpha:]]{3,} \d{4})\s*$#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
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

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }
}
