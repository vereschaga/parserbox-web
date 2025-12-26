<?php

namespace AwardWallet\Engine\leohotels\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "leohotels/it-43425524.eml, leohotels/it-54186972.eml, leohotels/it-53505262.eml"; // +1 bcdtravel

    public $reFrom = '@leonardo-hotels.com';
    public $reBody = [
        'de' => ['bestätigen gern folgende Reservierung'],
        'en' => [
            'Many thanks for your reservation',
            'thank you very much for your reservation',
            'thank you for your request and your interest in our hotel',
            'Looking forward to welcoming you in our hotel',
        ],
    ];
    public $reSubject = [
        '#Leonardo.+?Confirmation#',
    ];
    public $lang = '';
    public static $dict = [
        'de' => [
            "Confirmation"     => "Reservierungsbestätigung -",
            "Best regards"     => "Reservation Office",
            "Telephone"        => "Tel",
            "Fax"              => "Fax",
            "Guest name"       => "Gastname",
            "Arrival"          => "Anreise",
            "Departure"        => "Abreise",
            "Room"             => "Zimmer",
            "Number of guests" => "Personenanzahl",
            //			"Arrangement" => "",
            "Room rate per night" => "Rate pro Nacht & Zimmer",
            "Total amount"        => "Gesamtpreis",
        ],
        'en' => [
            "Confirmation" => ["Confirmation -", "Urgent information on reservation -", "Reservation Fattal internet club member – fattal.co.il No."],
            //			"Best regards" => "",
            "Telephone" => ["Telephone", "Phone", "T"],
            //			"Fax" => "",
            //			"Guest name" => "",
            //			"Arrival" => "",
            //			"Departure" => "",
            "Room"             => ["Room", "Room reserved"],
            "Number of guests" => ["Number of guests", "No. of guests", "Guests"],
            //			"Arrangement" => "",
            "Room rate per night" => ["Room rate per night", "Room rate 1st night", "Rate per room/night"],
            "Total amount"        => ["Total amount", "Total amount per room", "Total for stay per room", "Total"],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }
            $this->assignLang($textPdf);
            $this->parsePdf($email, $textPdf);
        }
        $email->setType('Confirmation' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if (stripos($textPdf, 'www.leonardo-hotels.com') === false
                && stripos($textPdf, '@leonardo-hotels.com') === false
                && stripos($textPdf, "Best regards\nLeonardo Hotel") === false
                && stripos($textPdf, 'Leonardo Hotel Munich City East') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (preg_match($reSubject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function parsePdf(Email $email, string $plainText): void
    {
        $patterns = [
            'time'           => '\d{1,2}(?:[:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
            'phone'          => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
            'travellerName'  => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*\/(?:[[:upper:]]+ )*[[:upper:]]+', // KOH/KIM LENG MR
        ];

        $segments = $this->split("#(\n\s*" . $this->t('Guest name') . ":)#", $plainText);

        foreach ($segments as $segText) {
            $h = $email->add()->hotel();

            if (preg_match("#({$this->preg_implode($this->t('Confirmation'))})[ ]*([A-Z\d]{5,})$#m", $plainText, $m)
                || preg_match('/Confirmation for (reservation number:)\s*\w+\s+\#([A-Z\d]+)/', $plainText, $m)
            ) {
                $h->general()->confirmation($m[2], rtrim($m[1], ' :-'));
            }

            $hotelName = $address = null;

            if (preg_match("#^[ ]*(?<name>.{3,}?)(?:[ ]*,[ ]*|[ ]+I[ ]+)(?<address>.{3,})#", $plainText, $m) // it-43425524.eml
                || preg_match("#^[ ]*(?<name>.{3,}?)[\n]+[ ]*(?<address>.{3,}?) - T:#", $plainText, $m) // it-53505262.eml
            ) {
                if (strpos($plainText, $m['name']) !== strrpos($plainText, $m['name'])) {
                    $hotelName = $m['name'];
                }
                $address = $m['address'];
            }

            if (empty($address) && !empty($hotelName)) {
                $address = $this->re("#\n\s*" . preg_quote($hotelName) . "[ ]+(.+?)\s+Telefon#", $plainText);
            }
            $phone = $this->re("#(?:^[ ]*| - ){$this->preg_implode($this->t('Telephone'))}[ ]*:[ ]*({$patterns['phone']})#m", $plainText);
            $fax = $this->re("#{$this->preg_implode($this->t('Fax'))}[ ]*:[ ]*({$patterns['phone']})#", $plainText);
            $h->hotel()
                ->name($hotelName)
                ->phone($phone, false, true)
                ->fax($fax, false, true);

            if (!empty($address)) {
                $h->hotel()->address($address);
            } elseif (empty($address) && !empty($hotelName)) {
                $h->hotel()->noAddress();
            }

            $guestNames = preg_split("/\n[ ]*/", trim($this->re("#{$this->preg_implode($this->t('Guest name'))}[ ]*:((?:[ ]*(?:{$patterns['travellerName']}|{$patterns['travellerName2']})\n){1,20}).+:#i", $segText)));
            $h->general()->travellers($guestNames);

            $h->booked()->checkIn2($this->normalizeDate($this->re("#" . $this->t('Arrival') . ":\s+(.+)#i", $segText)));
            $timeCheckIn = $this->re("#^[ ]*{$this->preg_implode($this->t('Check-in time'))}[ ]*:(?:[ ]*{$this->preg_implode($this->t('as from'))})?[ ]*({$patterns['time']})$#m", $segText);

            if (!empty($timeCheckIn) && !empty($h->getCheckInDate())) {
                if (preg_match('/^((\d{1,2})(?:[: ]+\d{2})?)\s*[AaPp][Mm]$/', $timeCheckIn, $m) && (int) $m[2] > 12) {
                    $timeCheckIn = $m[1];
                } // 21:51 PM    ->    21:51

                if (preg_match('/^\d{1,2}$/', $timeCheckIn)) {
                    $timeCheckIn .= ':00';
                }
                $h->booked()->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
            }

            $h->booked()->checkOut2($this->normalizeDate($this->re("#" . $this->t('Departure') . ":\s+(.+)#i", $segText)));

            $h->booked()
                ->rooms($this->re("#\n\s*{$this->preg_implode($this->t('Room'))}[ ]*:[ ]*(\d+)#i", $segText), false, true)
                ->guests($this->re("#{$this->preg_implode($this->t('Number of guests'))}[ ]*:[ ]*(\d{1,3})$#im", $segText));

            $room = $h->addRoom();

            $roomType = trim($this->re("#\n\s*{$this->preg_implode($this->t('Room'))}[ ]*:[ ]*(?:\d+)? (.+)#i", $segText) . ', ' . $this->re("#" . $this->t('Arrangement') . ":\s+(.+)#i", $segText), ' ,');
            $room->setType($roomType);

            $rateText = $this->re("#^[ ]*{$this->preg_implode($this->t('Room rate per night'))}[ ]*:[ ]*((?:\s*[^\n:]+$)+)#im", $segText);

            if ($rateText !== null && count(preg_split('/\n+/', $rateText)) > 1) {
                $rateRange = $this->parseRateRange($rateText);

                if ($rateRange !== null) {
                    $room->setRate($rateRange);
                }
            } elseif ($rateText !== null) {
                $room->setRate($rateText);
            }

            $tot = $this->re("#^[ ]*{$this->preg_implode($this->t('Total amount'))}[ ]*:[ ]*(.+?)[ ]*(?:{$this->preg_implode($this->t('Bed & Breakfast'))})?$#im", $segText);

            if (preg_match("/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/", $tot, $m)
                || preg_match("/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*?)$/", $tot, $m)
            ) {
                // 178,20 EURO    |    EUR 178,20
                $h->price()
                    ->total($this->normalizeAmount($m['amount']))
                    ->currency(preg_replace('/^EURO$/', 'EUR', $m['currency']));
            }

            $salesConditions = $this->re("#^[ ]*{$this->preg_implode($this->t('Sales conditions'))}[ ]*:[ ]*((?:\n?.+$)*?)(?:\n\n|^.+:)#m", $segText);
            $salesConditions = preg_replace('/\s+/', ' ', $salesConditions);

            if (preg_match("#{$this->preg_implode($this->t('cancel'))}#i", $salesConditions)) {
                $h->general()->cancellation($salesConditions);
            }

            if (!empty($h->getCheckInDate()) && !empty($h->getCancellation())) {
                if (preg_match("/^free cancellation before (?<hour>{$patterns['time']}) the day of arrival/i", $h->getCancellation(), $m)) {
                    $h->booked()->deadlineRelative('0 days', $m['hour']);
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($text)
    {
        foreach ($this->reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\.(\d+)\.(\d{2})$#',
            '#^(\d+)\.(\d+)\.(\d{4})$#',
        ];
        $out = [
            '20$3-$2-$1',
            '$3-$2-$1',
        ];

        return preg_replace($in, $out, $date);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * Dependencies `$this->normalizeAmount()`.
     */
    private function parseRateRange(?string $string): ?string
    {
        if (preg_match_all('/night[ ]+(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/m', $string, $rateMatches)
        ) {
            // 2nd night 80,19 EURO
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return $this->normalizeAmount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                }
            }
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map('preg_quote', $field)) . ')';
    }
}
