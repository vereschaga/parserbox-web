<?php

namespace AwardWallet\Engine\hardrock\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2084330 extends \TAccountChecker
{
    public $mailFiles = "hardrock/it-208851659.eml, hardrock/it-398027058.eml, hardrock/it-401721354.eml, hardrock/it-43967515.eml";
    public $subjects = [
        '',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Thank you for making your reservation at Hard Rock Hotel & Casino' => [
                'The Team at Seminole Hard Rock Hotel & Casino Hollywood',
                'Thank you for making your reservation at Hard Rock Hotel & Casino',
                'your stay with us at Hard Rock Hotel Vallarta are below',
                'The details of your stay with us at Lake',
                "You're all set, and the details of your stay with us at Hard Rock",
                "You're all set, and the details of your stay with us at HARD ROCK",
            ],

            'Confirmation Number' => ['Confirmation Number', 'Confirmation', 'Confirmation:', 'CONFIRMATION:'],

            'Guest Name' => ['Guest Name', 'Guest Name:', 'GUEST NAME:'],

            'Arrival Date'         => ['Arrival Date', 'Arrival Date:', 'ARRIVAL DATE:'],
            'Departure Date'       => ['Departure Date', 'Departure Date:', 'DEPARTURE DATE:'],
            'Check-in Time'        => ['Check-in Time', 'Check-in Time:', 'Check In Time:', 'Check-In Time:'],
            'Check-out Time'       => ['Check-out Time', 'Check-out Time:', 'Check Out Time:'],
            'Room Type'            => ['Room Type', 'Room Description:', 'ROOM DESCRIPTION:'],
            'Best Available Rate:' => ['Best Available Rate:', 'Average Daily Rate:'],
            'Number of Guests'     => ['Number of Guests', 'NUMBER OF GUESTS:', 'Adults'],
            'Plan Your Stay'       => ['Plan Your Stay', 'PLAN YOUR STAY'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@hrhvegas.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Hard Rock')]")) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for making your reservation at Hard Rock Hotel & Casino'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Guest Name'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hrhvegas\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))}]", null, true, "/{$this->opt($this->t('Confirmation Number'))}\s*(.+)/");
        }

        $h->general()
            ->confirmation(trim($confirmation, ':'));

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Guest Name']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Name'))}]", null, true, "/{$this->opt($this->t('Guest Name'))}\s*(\D+)/");
        }

        $h->general()
            ->traveller($traveller, true);

        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy']/ancestor::tr[1]/descendant::td[2]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'If you should need to cancel your reservation')]");
        }

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $depDate = $this->http->FindSingleNode("//text()[normalize-space()='Arrival Date']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (empty($depDate)) {
            $depDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Date'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($depDate)) {
            $depDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival Date'))}]", null, true, "/{$this->opt($this->t('Arrival Date'))}\s*(.+)/");
        }

        $arrDate = $this->http->FindSingleNode("//text()[normalize-space()='Departure Date']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (empty($arrDate)) {
            $arrDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Date'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($arrDate)) {
            $arrDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure Date'))}]", null, true, "/{$this->opt($this->t('Departure Date'))}\s*(.+)/");
        }

        $inTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-in Time']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/\s([\d\:]+\s*A?P?M)$/");

        if (empty($inTime)) {
            $inTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in Time'))}]/following::text()[normalize-space()][1]", null, true, "/^([\d\:]+\s*A?P?M)$/");
        }

        $outTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-out Time']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/\s([\d\:]+\s*A?P?M)$/");

        if (empty($outTime)) {
            $outTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out Time'))}]/following::text()[normalize-space()][1]", null, true, "/^([\d\:]+\s*A?P?M)$/");
        }

        if (empty($inTime) && empty($outTime)) {
            $inOutText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-in time is at ')]");

            if (preg_match("/Check-in time is at (?<inTime>\d+a?p?m) and check-out time is (?<outTime>\d+a?p?m)\./", $inOutText, $m)) {
                $inTime = $m['inTime'];
                $outTime = $m['outTime'];
            }
        }

        $h->booked()
            ->checkIn(strtotime($depDate . ', ' . $inTime))
            ->checkOut(strtotime($arrDate . ', ' . $outTime));

        $hotelNameText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Team')]");

        if (preg_match("/^{$this->opt($this->t('The Team at'))}\s*(.+)/", $hotelNameText, $m)
            || preg_match("/^(.+){$this->opt($this->t('Team'))}$/", $hotelNameText, $m)) {
            $hotelName = $m[1];
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//img[contains(@src, 'HRH_logo')]/following::text()[normalize-space()][1]");
        }

        $address = $this->http->FindSingleNode("//text()[normalize-space()='Guest Name']/following::text()[contains(normalize-space(), '|') or  contains(normalize-space(), 'FL')][last()]");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Plan Your Stay'))}]/preceding::text()[normalize-space()][2]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//img[contains(@src, 'HRH_logo')]/following::text()[normalize-space()][2]");
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address);

        $phone = $this->http->FindSingleNode("//text()[normalize-space()='Main Number']/ancestor::tr[1]/descendant::td[2]");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Call us at')]/following::text()[normalize-space()][1]", null, true, "/^([+][\d\s\-A-Z]+)$/");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Plan Your Stay'))}]/preceding::text()[normalize-space()][1]", null, true, "/^([+]*[\d\s\-A-Z]+)$/");
        }

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('ROOM TYPE:'))}]", null, true, "/{$this->opt($this->t('ROOM TYPE:'))}\s*(.+)/");
        }

        $roomRate = $this->http->FindSingleNode("//text()[normalize-space()='Daily Rate']/following::text()[normalize-space()][1]", null, true, "/\:\s*(.+)/");

        if (empty($roomRate)) {
            $roomRate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Best Available Rate:'))}]", null, true, "/{$this->opt($this->t('Best Available Rate:'))}\s*(.+)/iu");
        }

        if (empty($roomRate)) {
            $roomRate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Best Available Rate:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Best Available Rate:'))}\s*([A-Z]{3}\s*\D\s*[\d\.\,]+)/iu");
        }

        if (!empty($roomType) || !empty($roomRate)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomRate)) {
                $room->setRate($roomRate);
            }
        }

        $guestText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Guests'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        $this->logger->debug($guestText);

        if (preg_match("/^(?<adults>\d+)\s*Adult.*(?<kids>\d+)\s*Children$/u", $guestText, $m)) {
            $h->booked()
                ->guests($m['adults'])
                ->kids($m['kids']);
        } else {
            $adults = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Guests'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

            if (empty($adults)) {
                $adults = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Guests'))}]", null, true, "/^{$this->opt($this->t('Number of Guests'))}\s*(\d+)$/");
            }

            if (!empty($adults)) {
                $h->booked()
                    ->guests($adults);
            }

            $kids = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Nights:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Children:'))}\s*(\d+)/");

            if ($kids !== null) {
                $h->booked()
                    ->kids($kids);
            }
        }

        $this->detectDeadLine($h);

        $totalText = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (empty($totalText)) {
            $totalText = $this->http->FindSingleNode("//text()[normalize-space()='Total Stay:']/following::text()[normalize-space()][1]");
        }

        if (empty($totalText)) {
            $totalText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'TOTAL CHARGES:')]", null, true, "/{$this->opt($this->t('TOTAL CHARGES:'))}\s*(.+)/");
        }

        if (preg_match("/^(?<currency>\D)\s*(?<total>[\d\.\,]+)$/", $totalText, $m)
            || preg_match("/^(?<currency>[A-Z]{3})\s*\D*\s*(?<total>[\d\.\,]+)$/", $totalText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $tax = $this->http->FindSingleNode("//text()[normalize-space()='Total Taxes *']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D([\d\.\,]+)/");

        if (empty($tax)) {
            $tax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'TAX:')]", null, true, "/^{$this->opt($this->t('TAX:'))}\s*\D{1,3}\s*([\d\.\,]+)/");
        }

        if (!empty($tax)) {
            $h->price()
                ->tax($tax);
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

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Cancellations must be made at least (?<prior>\d+ hours?) prior to arrival to receive a full refund of the deposit/',
                $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['prior']);
        }

        if (preg_match("/before\s*([\d\:]+\s*a?p?m) the day before your intended arrival in order to obtain a deposit refund/", $cancellationText, $m)) {
            $h->booked()->deadlineRelative('1 day', $m[1]);
        }
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
