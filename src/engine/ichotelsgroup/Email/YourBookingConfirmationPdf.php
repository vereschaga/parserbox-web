<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourBookingConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-121454023.eml"; // +1 bcdtravel(pdf)[en]

    public $reSubject = [
        'de' => 'Ihre Buchungsbestätigung',
        'en' => 'Room booking for', 'Hotel reservation suppot for',
    ];

    public static $dictionary = [
        'de' => [
            'Guest Name:'                               => ['Gastname:'],
            'Confirmation Number:'                      => ['Reservierungsnummer:'],
            'Thank you for choosing to stay with us at' => 'vielen Dank für Ihr Interesse am',
            'phone'                                     => 'T:',
            'fax'                                       => 'F:',
            'Room Type:'                                => 'Zimmer:',
            //            'Rate' => '',
            //            'per night' => '',
            'Arrival Date:'   => 'Anreise:',
            'Departure Date:' => 'Abreise:',
            //            'Check In / Check Out' => '',
            //            'Number of Guest' => '',
            'Cancellation Policy' => 'Konditionen',
            'cancellationEnd'     => ['Wir stehen Ihnen jederzeit'],
            'Payment'             => 'Preis',
        ],
        'en' => [
            'Guest Name:'                               => ['Guest Name:'],
            'Confirmation Number:'                      => ['Confirmation Number:', 'Confirmation No:'],
            'Thank you for choosing to stay with us at' => ['Thank you for choosing to stay with us at', 'Thank you for making a reservation at', 'We are pleased to confirm your room reservation at'],
            'phone'                                     => ['T:', 'Tel:', 'tel:', 'Ph:'],
            'fax'                                       => ['F:', 'Fax:', 'fax:'],
            'Cancellation Policy'                       => ['Cancellation Policy', 'Cancellation policy', 'Terms & Conditions'],
            'cancellationEnd'                           => ['Payment ', 'Payment:', 'Important Note:'],
            'Check In / Check Out'                      => ['Check–In/Out Time:', 'Check In / Check Out'],
            'Number of Guest'                           => ['Number of Guest', 'Number of Guests'],
        ],
    ];

    public $lang = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ihg.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if (strpos($textPdf, 'IHG Hotels') === false
                && stripos($textPdf, 'holidayinn.com') === false
                && stripos($textPdf, 'ANA Crowne Plaza Kobe') === false
                && stripos($textPdf, 'www.anacrowneplaza-kobe.jp') === false
                && stripos($textPdf, 'Presidente InterContinental Cozumel Resort & Spa') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parseHotel($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('YourBookingConfirmationPdf' . ucfirst($this->lang));

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

    private function parseHotel(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $hotelName = $this->re("/{$this->opt($this->t('Thank you for choosing to stay with us at'))}(?:\s+[Tt]he)?\s+([\s\S]{4,}?)(?:[!,.]|and)/", $text);
        $h->hotel()->name(preg_replace('/\s+/', ' ', $hotelName));

        $address = !empty($h->getHotelName()) ? $this->re("/^[ ]*{$this->opt([$h->getHotelName(), strtoupper($h->getHotelName())])}\n+(.{3,})/m", $text) : null;
        $h->hotel()
            ->address($address)
            ->phone($this->re("/\s{$this->opt($this->t('phone'))}\s*([+(\d][-. \d)(]{5,}[\d)])[ ]*(?:{$this->opt($this->t('fax'))}|$)/m", $text));

        $fax = $this->re("/ {$this->opt($this->t('fax'))}\s*([+(\d][-. \d)(]{5,}[\d)])$/m", $text);

        if (!empty($fax)) {
            $h->hotel()
                ->fax($fax);
        }

        // Guest Name: Lu, Rod
        $this->logger->debug($text);
        $guestName = $this->re("/{$this->opt($this->t('Guest Name:'))}\s+([[:alpha:]][-,&.\'[:alpha:] ]*[[:alpha:]])$/mu", $text);
        $h->general()->traveller($guestName);

        $room = $h->addRoom();
        $roomType = $this->re("/^[ ]*{$this->opt($this->t('Room Type:'))}\s+(.+[^:])$/m", $text);

        if (!empty($roomType)) {
            $room->setType($roomType);
        }

        $rate = $this->re("/^[ ]*{$this->opt($this->t('Rate'))}[:\s]+(.*\d.*{$this->opt($this->t('per night'))}.*)$/m", $text);

        if (empty($rate)) {
            $rate = $this->re("/Nightly rate\s*\:*\s*(\D+[\d\.\,]+)/", $text);
        }

        if (!empty($rate)) {
            $room->setRate($rate, false, true);
        }

        $description = $this->re("/Accommodation\:\s*(.+)/", $text);

        if (!empty($description)) {
            $room->setDescription($description);
        }

        $patterns['time'] = '\d{1,2}(?:[:.]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $h->booked()->checkIn2($this->normalizeDate($this->re("/{$this->opt($this->t('Arrival Date:'))}\s+(.{6,})$/m", $text)));
        $h->booked()->checkOut2($this->normalizeDate($this->re("/{$this->opt($this->t('Departure Date:'))}\s+(.{6,})$/m", $text)));
        $timeCheckIn = $timeCheckOut = '';
        $times = $this->re("/^[ ]*{$this->opt($this->t('Check In / Check Out'))}[:\s]+(.+\d.+)$/m", $text);

        if ($times && count(explode('/', $times)) === 2
            && preg_match("/^.*?({$patterns['time']}).*?\/.*?({$patterns['time']}).*$/", $times, $m)
        ) {
            $timeCheckIn = $m[1];
            $timeCheckOut = $m[2];
        }

        if (!$timeCheckIn) {
            $timeCheckIn = $this->re("/{$this->opt($this->t('The hotel check in time is'))}\s*({$patterns['time']})/", $text);
        }

        if (!$timeCheckOut) {
            $timeCheckOut = $this->re("/{$this->opt($this->t('The hotel check-out time is'))}\s*({$patterns['time']})/", $text);
        }

        if (!empty($timeCheckIn) && !empty($h->getCheckInDate())) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
        }

        if (!empty($timeCheckOut) && !empty($h->getCheckOutDate())) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
        }

        if (preg_match("/({$this->opt($this->t('Confirmation Number:'))})\s+(\d+)\b/", $text, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ' :'));
        }

        $guests = $this->re("/^[ ]*{$this->opt($this->t('Number of Guest'))}[: ]+(.+)$/m", $text);
        $adults = $this->re("/\b(\d{1,3})[ ]*{$this->opt($this->t('Adult'))}/", $guests);

        if ($adults === null) {
            $adults = $this->re("/^[ ]*{$this->opt($this->t('Pax:'))}\s*(\d{1,3})$/m", $text);
        }
        $h->booked()->guests($adults, false, true);

        $cancellation = $this->re("/^[ ]*{$this->opt($this->t('Cancellation Policy'))}[:\s]+([\s\S]+?)\s*(?:\n\n|{$this->opt($this->t('cancellationEnd'))})/m", $text);

        if ($cancellation && count(explode("\n", $cancellation)) < 10) {
            $cancellation = preg_replace('/\s+/', ' ', $cancellation);
            $h->general()->cancellation($cancellation);

            if (preg_match("/Cancelling your reservation latest (?<hour>{$patterns['time']}) \([^)(]+\) on the day before arrival will be free of charge\./i", $cancellation, $m) // en
            ) {
                $h->booked()->deadlineRelative('2 days', $m['hour']);
            } elseif (preg_match("/After (?<hour>{$patterns['time']}) (?<prior>\d{1,3}) Days? prior to Accomodation Day and No show 100%/i", $cancellation, $m) // en
            ) {
                $h->booked()->deadlineRelative($m['prior'] . ' days - 1 day', $m['hour']);
            } elseif (preg_match("/Cancellation or change must be received\s*(?<prior>\d+\s*days)\s*prior to arrival date in order to avoid/i", $cancellation, $m) // en
            ) {
                $h->booked()->deadlineRelative($m['prior'] . ' - 1 hour');
            }
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Payment'))}[:\s]+(?<currency>[A-Z]{3}) ?(?<amount>\d[,.\'\d]*)\b/m", $text, $m)) {
            $h->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));
        }
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Guest Name:']) || empty($phrases['Confirmation Number:'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Guest Name:']) !== false
                && $this->strposArray($text, $phrases['Confirmation Number:']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\.\s+([^\d\s]+)\s+(\d{4})#",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif (strcasecmp($m[1], 'Aguste') === 0) {
                $str = str_replace($m[1], 'August', $str);
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
