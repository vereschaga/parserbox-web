<?php

namespace AwardWallet\Engine\uniorlres\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "uniorlres/it-204308016.eml, uniorlres/it-205703360.eml, uniorlres/it-205815840.eml";
    public $subjects = [
        'Your Reservation Confirmation ',
        'Your Reservation Cancellation ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Reservation Number:'  => ['Reservation Number:', 'Original Confirmation:', 'Original Confirmation'],
            '#Woahments at'        => ['#Woahments at', '#Woahments during your stay at'],
            'Guest Name:'          => ['Guest Name:', 'Guest Name'],
            'Cancellation Number:' => ['Cancellation Number:', 'Cancellation Number'],
            'Arrival Date:'        => ['Arrival Date:', 'Arrival Date'],
            'Departure Date:'      => ['Departure Date:', 'Departure Date'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@hotelres.universalorlando.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Official Universal Orlando')]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Information'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Cancellation Information'))}]")->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hotelres\.universalorlando\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Cancellation Information'))}]")->length > 0) {
            $h->general()
                ->cancelled()
                ->cancellationNumber($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Number:'))}]/following::text()[normalize-space()][1]"));
        }

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Number:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name:'))}]/following::text()[normalize-space()][1]"), true);

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation:'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $hotelNameText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('#Woahments at'))}]", null, true, "/{$this->opt($this->t('#Woahments at'))}\s*(.+)/");

        if (preg_match("/[®]/", $hotelNameText)) {
            $hotelName = $this->re("/^(.+)[®]/u", $hotelNameText);
        } else {
            $hotelName = $this->re("/^(.+)\!/i", $hotelNameText);
        }

        $h->hotel()
            ->name($hotelName);

        $address = implode(', ', $this->http->FindNodes("//text()[{$this->contains($this->t('Guest Contact Center'))}]/ancestor::p[1]/following-sibling::p"));

        if (!empty($address)) {
            $h->hotel()
                ->address($address);
        } else {
            $h->hotel()
                ->noAddress();
        }

        $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Date:'))}]/following::text()[normalize-space()][1]");
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in Time:'))}]/ancestor::tr[1]/descendant::td[2]");

        $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Date:'))}]/following::text()[normalize-space()][1]");
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out Time:'))}]/ancestor::tr[1]/descendant::td[2]");

        $h->booked()
            ->checkIn(strtotime($dateCheckIn . ', ' . $timeCheckIn))
            ->checkOut(strtotime($dateCheckOut . ', ' . $timeCheckOut));

        $this->detectDeadLine($h);

        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Guests:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/(\d+)\s*{$this->opt($this->t('Adult'))}/");

        if (!empty($guests)) {
            $h->booked()
                ->guests($guests);
        }

        $kids = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Guests:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/(\d+)\s*{$this->opt($this->t('Children'))}/");

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
        }

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Cost:'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>\D)\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Cancel by (?<hours>[\d\:]+\s*A?P?M) local hotel time at least (?<prior>\d+\s*days?) prior to arrival to avoid/ui', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], $m['hours']);
        }
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
