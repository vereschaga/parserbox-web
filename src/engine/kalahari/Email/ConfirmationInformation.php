<?php

namespace AwardWallet\Engine\kalahari\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationInformation extends \TAccountChecker
{
    public $mailFiles = "kalahari/it-593775609.eml";
    public $subjects = [
        'Kalahari Confirmation Information',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your confirmation number is:' => ['Your confirmation number is:', 'Confirmation Number:'],
            'Check-out is at'              => ['Check-out is at', 'Check-out'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@kalahariresorts.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Welcome to Kalahari')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Check-in is at'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('VIEW OR UPDATE YOUR RESERVATION'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]@kalahariresorts.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cancellation Policy:')]/ancestor::span[1]", null, true, "/{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)/");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cancellation Policy:')]/ancestor::span[2]", null, true, "/{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)/");
        }
        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Welcome to Kalahari,')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Welcome to Kalahari,'))}\s*(\D*)\!/"), false)
            ->cancellation($cancellation);

        $hotelInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your confirmation number is:'))}]/ancestor::tr[1]");

        if (preg_match("/^Your adventure starts now at\s+(?<hotelName>.+)\s+in\s+.*\.\s+{$this->opt($this->t('Your confirmation number is:'))}\s+(?<confNumber>[A-Z\d]{6,})/", $hotelInfo, $m)) {
            $h->general()
                ->confirmation($m['confNumber']);

            $h->hotel()
                ->name($m['hotelName']);
        }

        $contactInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($h->getHotelName())}]/ancestor::table[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/{$h->getHotelName()}\n(?<address>(?:.*\n*){1,2})(?<phone>[\d\-A-Z]+\s+[\(\)\d\-]+)/", $contactInfo, $m)) {
            if (preg_match("/^(?<p1>[\d\-]+)\D*\((?<p2>[\d\-]+)\)$/", $m['phone'], $match)) {
                $h->hotel()
                    ->phone($match['p1'] . $match['p2']);
            }

            $h->hotel()
                ->address(str_replace("\n", ", ", $m['address']));
        }

        $inDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in is at'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(.+\d{4})$/");
        $inTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in is at'))}]", null, true, "/{$this->opt($this->t('Check-in is at'))}\s*([\d\:]+\s*a?p?m)\./");

        if (!empty($inDate) && !empty($inTime)) {
            $h->booked()
                ->checkIn(strtotime($inDate . ', ' . $inTime));
        }

        $outDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out is at'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(.+\d{4})$/");
        $outTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out is at'))}]", null, true, "/{$this->opt($this->t('Check-out is at'))}\s*([\d\:]+\s*a?p?m)\./");

        if (!empty($outDate) && !empty($outTime)) {
            $h->booked()
                ->checkOut(strtotime($outDate . ', ' . $outTime));
        }

        $guests = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Number of Guests:')]/ancestor::span[1]", null, true, "/\s+(\d+)\s*{$this->opt($this->t('Adult'))}/");

        if (!empty($guests)) {
            $h->booked()
                ->guests($guests);
        }

        $kids = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Number of Guests:')]/ancestor::span[1]", null, true, "/\s+(\d+)\s*{$this->opt($this->t('Child'))}/");

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
        }

        $this->detectDeadLine($h);

        $rate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Average Nightly Rate:')]/ancestor::span[1]", null, true, "/{$this->opt($this->t('Average Nightly Rate:'))}\s*(.+)/");
        $rateType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rate Plan:')]/ancestor::span[1]", null, true, "/{$this->opt($this->t('Rate Plan:'))}\s*(.+)/");
        $roomType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Room Style:')]/ancestor::span[1]", null, true, "/{$this->opt($this->t('Room Style:'))}\s*(.+)/");

        if (!empty($rate) || !empty($rateType) || !empty($roomType)) {
            $room = $h->addRoom();

            if ($rate) {
                $room->setRate($rate);
            }

            if ($rateType) {
                $room->setRateType($rateType);
            }

            if ($roomType) {
                $room->setType($roomType);
            }
        }

        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Amount*:')]/ancestor::span[1]", null, true, "/{$this->opt($this->t('Total Amount*:'))}\s*(.+)/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
            $h->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancel up to\s*(\d+)\s*days?\s*prior to arrival (?:without charge|with no charge)/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' day');
        }
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
