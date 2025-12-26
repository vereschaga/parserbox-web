<?php

namespace AwardWallet\Engine\viewhotel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "viewhotel/it-290850757.eml, viewhotel/it-301535157.eml, viewhotel/it-311304716.eml";

    public $detectFrom = "noreply@iqwebbook.com";

    public $detectSubjects = [
        "The VIEW Hotel - Reservation Confirmation",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
//        if (!isset($headers['from']) || stripos($headers['from'], $this->detectFrom) === false) {
//            return false;
//        }
        foreach ($this->detectSubjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*/@*[{$this->contains('iqwebbook')}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains('@monumentvalleyview.com')}]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//tr[*[1]/descendant::text()[normalize-space()][1][{$this->eq(['Booking Information'])}] "
                . " and *[2]/descendant::text()[normalize-space()][1][{$this->eq(['Guest Information'])}] ]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@iqwebbook.com') !== false;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextTd($this->t('Confirmation')))
            ->traveller(preg_replace("/^ *(Mrs|Mr|Mstr|Miss|Ms|Dr)\.? /", '',
                $this->http->FindSingleNode("//text()[{$this->eq(['Guest Information'])}]/following::text()[normalize-space()][1]")), true)
            ->cancellation(preg_replace('/^.+(Cancellation Policy:|\.\s*CANCELLATION:)/', '',
                $this->http->FindSingleNode("//node()[{$this->eq(['Cancellation Policy'])}][following-sibling::*[normalize-space()]][1]/following-sibling::*[normalize-space()][1]")), true, true);

        // Hotel
        $hotelInfo = implode("\n", $this->http->FindNodes("//tr[*[1]/descendant::text()[normalize-space()][1][{$this->eq('Booking Information')}] and *[2]/descendant::text()[normalize-space()][1][{$this->eq('Guest Information')}] ]"
            . "/following::text()[contains(., '|')][1]/ancestor::table[1]//tr[normalize-space()]"));

        if (preg_match("/^(?<name>.+?) *\| *(?<address>.+)\n(?<phone>[\d \-\+()\.]*\d+[\d \-\+()\.]*?)s*(?:\||$)/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace('/\s*\|\s*/', ', ', $m['address']))
                ->phone($m['phone'])
            ;
        }

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->nextTd($this->t('Check-In'))))
            ->checkOut(strtotime($this->nextTd($this->t('Check-Out'))))
            ->guests($this->nextTd($this->t('Guests'), "/^\s*(\d+)(?:\s*\(up to\))?\s*adult/i"))
            ->kids($this->nextTd($this->t('Guests'), "/^\s*(\d+)\s*children/i"), true, true)
        ;

        // Roms
        $room = $h->addRoom();
        $room
            ->setType($this->nextTd($this->t('Room Type')))
            ->setRateType($this->nextTd($this->t('Rate Type')))
        ;

        $ratesTd = $this->http->FindNodes("//text()[{$this->eq($this->t('Rate Type'))}]/following::text()[normalize-space()][2]/ancestor::*"
            . "[not(.//text()[{$this->eq($this->t('Rate Type'))}]) and not(.//text()[{$this->starts($this->t('Total Room:'))}])]"
            . "[following::text()[normalize-space()][1][{$this->starts($this->t('Total Room:'))}]]//td[not(.//td)]");

        $rates = [];

        if (count($ratesTd) % 2 == 0) {
            for ($i = 0; $i < count($ratesTd); $i += 2) {
                $rates[] = $ratesTd[$i + 1];
            }
        }
        $night = $this->nextTd($this->t('Nights'));

        if (count($rates) == $night) {
            $room->setRates($rates);
        }

        $total = $this->getTotal($this->nextTd($this->t('Total for Room :')));
        $h->price()
            ->total($total['amount'])
            ->currency($total['currency']);
        $tax = $this->getTotal($this->nextTd($this->t('taxes & services:'), null, 'contains'));
        $h->price()
            ->tax($tax['amount']);
        $cost = $this->getTotal($this->nextTd($this->t('Total Room:'), null, 'contains'));
        $h->price()
            ->cost($cost['amount']);

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function nextTd($field, $regexp = null, $type = 'eq')
    {
        if ($type == 'contains') {
            $rule = $this->contains($field);
        } elseif ('starts' === $type) {
            $rule = $this->starts($field);
        } else {
            $rule = $this->eq($field);
        }

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", null, true, $regexp);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

//        if (preg_match('/Cancellations before\s*(\d+\/\d+\/\d{4}\,\s*[\d\:]+\s*[AP]M)\s*\([^)]+\)\s*are fully refundable/us', $cancellationText, $m)) {
//            // Cancellations before 12/23/2013, 06:00 PM (America/Los Angeles) are fully refundable
//            $h->booked()->deadline(strtotime($m[1]));
//        } elseif (preg_match('/This reservation is non-refundable\./us', $cancellationText, $m)) {
//            $h->booked()->nonRefundable();
//        }
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function getTotal($text)
    {
        $text = trim($text);
        $result = ['amount' => null, 'currency' => null];

        if (preg_match('/^\s*\d[\d\.\, ]*\s*$/', $text)) {
            $text .= ' USD';
        }

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '$' => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
