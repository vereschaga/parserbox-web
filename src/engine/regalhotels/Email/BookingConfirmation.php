<?php

namespace AwardWallet\Engine\regalhotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "regalhotels/it-703473176.eml";
    public $subjects = [
        'Booking Confirmation - Booking Number:',
    ];

    public $lang = 'en';
    public $currency;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airport.regalhotel.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'on Regal Reservation System')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Room Type'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Daily Rate'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Details:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airport\.regalhotel\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Grand Total:')]");

        if (preg_match("/{$this->opt($this->t('Grand Total:'))}\s*\((?<currency>[A-Z]{3})\)\s*(?<total>[\d\.\,]+)/", $price, $m)) {
            $this->currency = $m['currency'];

            $email->price()
                ->total(PriceHelper::parse($m['total'], $this->currency))
                ->currency($this->currency);
        }

        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Confirmation Number:')]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $account = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Regal Club Member Number:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Regal Club Member Number:'))}\s*([A-Z\d]+)/");

            if (!empty($account)) {
                $pax = $this->http->FindSingleNode("//text()[{$this->contains($account)}]/preceding::text()[normalize-space()='Name:'][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Name:'))}\s*(.+)/");

                if (!empty($pax)) {
                    $h->addAccountNumber($account, false, preg_replace("/^(?:Mrs|Ms|Mr)/", "", $pax));
                } else {
                    $h->addAccountNumber($account, false);
                }
            }

            $h->general()
                ->confirmation($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\-\d]+)/"))
                ->travellers(preg_replace("/^(?:Mrs|Ms|Mr)/", "", $this->http->FindNodes("./following::table[1]/descendant::text()[starts-with(normalize-space(), 'Name:')]/ancestor::tr[1]", $root, "/^{$this->opt($this->t('Name:'))}\s*(.+)$/")))
                ->cancellation($this->http->FindSingleNode("./following::table[1]/descendant::text()[starts-with(normalize-space(), 'Cancellation Policy:')]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)/"));

            $h->hotel()
                ->name($this->http->FindSingleNode("//text()[normalize-space()='Reservation Details:']/preceding::text()[normalize-space()='Phone:'][1]/ancestor::tr[1]/preceding::tr[2]"))
                ->address($this->http->FindSingleNode("//text()[normalize-space()='Reservation Details:']/preceding::text()[normalize-space()='Phone:'][1]/ancestor::tr[1]/preceding::tr[1]", null, true, "/^(.+)\s*\(\s*Google Map/"))
                ->phone($this->http->FindSingleNode("//text()[normalize-space()='Reservation Details:']/preceding::text()[normalize-space()='Phone:'][1]/ancestor::tr[1]",
                    null, true, "/{$this->opt($this->t('Phone:'))}\s*([+\s\d\(\)]+)/"))
                ->fax($this->http->FindSingleNode("//text()[normalize-space()='Reservation Details:']/preceding::text()[normalize-space()='Fax:'][1]/ancestor::tr[1]",
                    null, true, "/{$this->opt($this->t('Fax:'))}\s*([+\s\d\(\)]+)/"));

            $h->booked()
                ->guests($this->http->FindSingleNode("./following::table[1]/descendant::text()[starts-with(normalize-space(), 'Number of Adults:')]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Number of Adults:'))}\s*(\d+)/"))
                ->kids($this->http->FindSingleNode("./following::table[1]/descendant::text()[starts-with(normalize-space(), 'Number of Children:')]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Number of Children:'))}\s*(\d+)/"));

            $roomArray = $this->http->FindNodes("./following::table[1]/descendant::text()[starts-with(normalize-space(), 'Daily Rate Breakdown')]/ancestor::tr[1]/preceding-sibling::tr[not(contains(normalize-space(), 'Room Type'))]/td[1]", $root);

            foreach ($roomArray as $roomName) {
                $room = $h->addRoom();
                $room->setType($roomName);

                $rate = $this->http->FindNodes("./following::table[1]/descendant::text()[starts-with(normalize-space(), 'Daily Rate Breakdown')]/ancestor::tr[1]/preceding::tr[1]/following-sibling::tr/descendant::td[last()]/preceding-sibling::td[2]", $root, "/^([\.\,\d\']+)$/");

                if (!empty($rate)) {
                    $room->setRates(array_filter($rate));
                }
            }

            $inTime = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Check-in and Check-out Time']/following::text()[normalize-space()='Check-in']/ancestor::tr[1]", null, true, "/From:\s*(\d+\:\d+)\s*$/");
            $outTime = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Check-in and Check-out Time']/following::text()[normalize-space()='Check-out']/ancestor::tr[1]", null, true, "/Before:\s*(\d+\:\d+)\s*$/");

            $inDate = $this->http->FindSingleNode("./following::table[1]/descendant::text()[normalize-space()='From']/ancestor::tr[1]/following::tr[1]/descendant::text()[string-length()>2][2]", $root);
            $outDate = $this->http->FindSingleNode("./following::table[1]/descendant::text()[normalize-space()='From']/ancestor::tr[1]/following::tr[1]/descendant::text()[string-length()>2][3]", $root);

            $h->booked()
                ->checkIn(strtotime($inDate . ', ' . $inTime))
                ->checkOut(strtotime($outDate . ', ' . $outTime));

            $total = $this->http->FindSingleNode("./following::table[1]/descendant::text()[normalize-space()='Total Room Charges:']/ancestor::td[1]/following-sibling::td[1]", $root, true, "/^([\d\.\,\']+)$/");

            if (!empty($total)) {
                $h->price()
                    ->total(PriceHelper::parse($total, $this->currency))
                    ->currency($this->currency);

                $cost = $this->http->FindSingleNode("./following::table[1]/descendant::text()[normalize-space()='Room Charges:']/ancestor::td[1]/following-sibling::td[1]", $root, true, "/^([\d\.\,\']+)$/");

                if ($cost !== null) {
                    $h->price()
                        ->cost(PriceHelper::parse($cost, $this->currency));
                }

                $tax = $this->http->FindSingleNode("./following::table[1]/descendant::text()[normalize-space()='Total Tax:']/ancestor::td[1]/following-sibling::td[1]", $root, true, "/^([\d\.\,\']+)$/");

                if ($tax !== null) {
                    $h->price()
                        ->tax(PriceHelper::parse($tax, $this->currency));
                }

                $fee = $this->http->FindSingleNode("./following::table[1]/descendant::text()[normalize-space()='Service Charge:']/ancestor::td[1]/following-sibling::td[1]", $root, true, "/^([\d\.\,\']+)$/");

                if ($fee !== null) {
                    $h->price()
                        ->fee('Service Charge', PriceHelper::parse($fee, $this->currency));
                }
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
}
