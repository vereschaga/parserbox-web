<?php

namespace AwardWallet\Engine\hardrock\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationVoucher extends \TAccountChecker
{
    public $mailFiles = "hardrock/it-399698563.eml, hardrock/it-408050794.eml";
    public $subjects = [
        'HARD ROCK HOTEL IBIZA - BOOKING CONFIRMATION',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Included taxes'                                               => ['Included taxes', 'Taxes included'],
            'In the event of reservation cancelation or modification less' => [
                'In the event of reservation cancelation or modification less',
                'A non refundable pre-payment of ',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@roibackbackhotelengine.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Hard Rock Hotel')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Property information'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Personal information'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]roibackbackhotelengine\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
           ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking ID:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking ID:'))}\s*([a-z\d]+)/"))
           ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Holder:')]", null, true, "/{$this->opt($this->t('Reservation Holder:'))}\s*(\D+)/"))
           ->status($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your reservation status is:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your reservation status is:'))}\s*(\D+)/"))
           ->date(strtotime(str_replace('/', '.', $this->http->FindSingleNode("//text()[normalize-space()='Booking date:']/following::text()[normalize-space()][1]", null, true, "/(\d+\/\d+\/\d{4})/"))));

        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation policies']/following::text()[starts-with(normalize-space(), 'Free cancellation until')][1]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation policies']/following::text()[{$this->starts($this->t('In the event of reservation cancelation or modification less'))}]");
        }

        if (!empty($cancellation)) {
            $h->setCancellation($cancellation);
        }

        $h->hotel()
           ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Name of the property:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Name of the property:'))}\s*(\D+)/"))
           ->address($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Address:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Address:'))}\s*(\D+)/"))
           ->phone($this->http->FindSingleNode("//text()[normalize-space()='Property information']/following::text()[starts-with(normalize-space(), 'Telephone:')][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Telephone:'))}\s*(.+)/"));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Your reservation']/following::text()[{$this->eq($this->t('Included taxes'))}][1]/preceding::text()[normalize-space()][not(contains(normalize-space(), '='))][1]", null, true, "/^(.*\d.*)$/");

        if (preg_match("/^(?<currency>\D)\s*(?<total>[\d\.\,]+)$/u", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
               ->total(PriceHelper::parse($m['total'], $currency))
               ->currency($currency);

            $points = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Points')]", null, true, "/^([\d\,\.]+\s*Points)$/siu");

            if (!empty($points)) {
                $h->price()
                    ->spentAwards($points);
            }
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Room']/ancestor::tr[1]/following-sibling::tr");

        $adults = 0;

        foreach ($nodes as $root) {
            $adult = $this->http->FindSingleNode("./descendant::td[6]", $root, true, "/^\s*(\d+)\s+adult/iu");

            if (!empty($adult)) {
                $adults += (int) $adult;
            }

            $checkIn = $this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^([\d\/]+)$/");

            if (!empty($checkIn)) {
                $h->booked()
                   ->checkIn(strtotime(str_replace('/', '.', $checkIn)));
            }

            $checkOut = $this->http->FindSingleNode("./descendant::td[3]", $root, true, "/^([\d\/]+)$/");

            if (!empty($checkOut)) {
                $h->booked()
                   ->checkOut(strtotime(str_replace('/', '.', $checkOut)));
            }

            $h->addRoom()
               ->setType($this->http->FindSingleNode("./descendant::td[1]", $root, true, "/^\s*\d+\s+\-\s*(.+)/"))
               ->setRate($this->http->FindSingleNode("./descendant::td[7]", $root));
        }

        $h->booked()
           ->guests($adults)
            ->rooms($nodes->length);

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Free cancellation until ([\d\/]+)/", $cancellationText, $m)) {
            $h->setDeadline(strtotime(str_replace('/', '.', $m[1])));
        }
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
