<?php

namespace AwardWallet\Engine\jumeirah\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "jumeirah/it-178444896.eml, jumeirah/it-684541082.eml";
    public $subjects = [
        'Reservation Confirmation',
        'Thank you for your booking on',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Reservation Details:' => ['Reservation Details:', 'Booking Details:'],
            'Confirmation Number:' => ['Confirmation Number:', 'Jumeirah.com ID:'],
            'Cancelation Policy:'  => ['Cancelation Policy:', 'Cancellation Policy:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true && strpos($headers['subject'], 'Jumeirah.com') === false) {
            return false;
        }

        foreach ($this->subjects as $phrase) {
            if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Jumeirah Group')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('We look forward to welcoming you to'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Details:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('MANAGE YOUR BOOKING'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jumeirah\.com$/', $from) > 0;
    }

    public function ParseHotel(Hotel $h, \DOMNode $root): void
    {
        $h->general()
            ->confirmation($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation Number:'))}]/ancestor::tr[1]/td[2]", $root))
            ->cancellation($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Cancelation Policy:'))}]/following::text()[normalize-space()][1]", $root));

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("descendant::text()[normalize-space()='Check In:']/ancestor::tr[1]/td[2]", $root)))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("descendant::text()[normalize-space()='Check Out:']/ancestor::tr[1]/td[2]", $root)))
            ->guests($this->http->FindSingleNode("descendant::text()[normalize-space()='Number of Adults:']/ancestor::tr[1]/td[2]", $root));

        $kids = $this->http->FindSingleNode("descendant::text()[normalize-space()='Number of Children:']/ancestor::tr[1]/td[2]", $root);
        $infants = $this->http->FindSingleNode("descendant::text()[normalize-space()='Number of Infants:']/ancestor::tr[1]/td[2]", $root);

        if ($infants !== null) {
            $kids += intval($infants);
        }

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
        }

        $roomType = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Room ')][not(contains(normalize-space(),'Rates'))]/following::text()[normalize-space()][1]", $root, true, "/^.+[^:]$/");
        $rateType = $this->http->FindSingleNode("descendant::text()[normalize-space()='Room Rates:'][1]/ancestor::tr[1]/descendant::td[2]", $root);

        if ($roomType || $rateType !== null) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($rateType)) {
                $room->setRateType($rateType);
            }
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'For information or to find out about ')]", null, true, "/{$this->opt($this->t('For information or to find out about '))}(.+)/");
        $address = preg_replace("/contact info.+/", "", $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Hotel Details:')]/following::text()[normalize-space()='Address:']/following::text()[normalize-space()][1]"));
        $phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Hotel Details:')]/following::text()[normalize-space()='Phone:']/following::text()[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Hotel Details:')]/following::text()[starts-with(normalize-space(),'Phone:')]", null, true, "/{$this->opt($this->t('Phone:'))}\s*(.+)/");

        $hotelRoots = $this->http->XPath->query("//tr[ *[normalize-space()][1][{$this->eq($this->t('Check In'), 'translate(.,":","")')}] ]/ancestor::*[ descendant::tr[ *[normalize-space()][1][{$this->eq($this->t('Check Out'), 'translate(.,":","")')}] ] ][1]");

        foreach ($hotelRoots as $hRoot) {
            $h = $email->add()->hotel();
            $h->general()->traveller($traveller);
            $h->hotel()->name($hotelName)->address($address)->phone($phone);
            $this->ParseHotel($h, $hRoot);

            if ($hotelRoots->length > 1) {
                $roomCostVal = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('TotalRoomCost'), 'translate(.,"*: ","")')}] ]/*[normalize-space()][2]", $hRoot, true, "/^(.*\d.*?)\s+-\s+\d{1,3}\s+night\(s\)$/i");

                if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $roomCostVal, $matches)) {
                    // AED 54,400.00
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                    $h->price()->currency($matches['currency'])->cost(PriceHelper::parse($matches['amount'], $currencyCode));
                }
            }
        }

        if ($hotelRoots->length === 1) {
            $this->parsePrice($h);
        } else {
            $this->parsePrice($email);
        }

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

    private function parsePrice($obj): void
    {
        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total Payable:']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>\d[\d.,]*)$/", $total, $m)) {
            $obj->price()->currency($m['currency'])->total(PriceHelper::parse($m['total'], $m['currency']));

            $cost = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Total Room Cost Excluding Taxes & Fees*')]/ancestor::tr[1]/descendant::td[2]", null, true, "/\s(\d[\d.,]*)$/");

            if ($cost !== null) {
                $obj->price()->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Applicable Taxes & Fees')]/ancestor::tr[1]/descendant::td[2]", null, true, "/\s(\d[\d.,]*)$/");

            if ($tax !== null) {
                $obj->price()->tax(PriceHelper::parse($tax, $m['currency']));
            }
        }
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Full\, non\-refundable, prepayment due at time of booking/", $cancellationText)) {
            $h->setNonRefundable(true);
        }

        if (preg_match("/Reservation must be cancelled (\d+) days prior to arrival to avoid penalty/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');
        }
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 13 DECEMBER 2022 (Check In Time: From 15:00)
            // 14 - August 2023 (Check Out Time: From 12:00)
            "#^\s*(\d+)\s*(?:-\s*)?(\w+)\s*(\d{4})\D+(\d+\:\d+)\)\s*$#u",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }
}
