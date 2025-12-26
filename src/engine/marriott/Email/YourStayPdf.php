<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourStayPdf extends \TAccountChecker
{
    public $mailFiles = "marriott/it-14293109.eml, marriott/it-14293161.eml, marriott/it-14293176.eml, marriott/it-57645469.eml, marriott/it-60100740.eml";
    private $langDetectors = [
        'en' => ['Number of Guests:', 'NUMBER OF GUESTS:'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Room:'             => ['Room:', 'ROOM:'],
            'Room Type:'        => ['Room Type:', 'ROOM TYPE:'],
            'Number of Guests:' => ['Number of Guests:', 'NUMBER OF GUESTS:'],
            'Rate:'             => ['Rate:', 'RATE:'],
            'Arrive:'           => ['Arrive:', 'ARRIVE:'],
            'Depart:'           => ['Depart:', 'DEPART:'],
            'Time:'             => ['Time:', 'TIME:'],
        ],
    ];

    // hard-coded hotels
    private $supportedHotels = [
        'AC South San Francisco Airport/Oyster Point Waterf' => [
            'names'     => ['\bAC\s+HOTELS\s+BY\s+MARRIOTT\s*®?'],
            'addresses' => ['\b1333 VETERANS BLVD[·.\s]+S SAN FRANCISCO, CA 94080\b'],
        ],
        'TownePlace Suites by Marriott Chicago Lombard' => [
            'names'     => ['\bTOWNEPLACE\s+SUITES\s*®?\s+CHICAGO\s+LOMBARD\b'],
            'addresses' => ['\b455 East 22nd Street[·\s]+Lombard IL 60148\b'],
        ],
        'Residence Inn by Marriott Sacramento Downtown at Capitol Park' => [
            'names'     => ['\bResidence\s+Inn\s+by\s+Marriott\s+Sacramento\s+Downtown\s+at\s+Capitol\s+Park\b'],
            'addresses' => ['\b1121\s+15th\s+Street\s+Sacramento\s+Ca\s+95814\b'],
        ],
        'Fairfield Inn & Suites by Marriott Santa Maria' => [
            'names'     => ['\bFairfield\s+Inn\s+&\s+Suites\s*®?\s+Santa\s+Maria\b'],
            'addresses' => ['\b2061\s+N.\s+Roemer\s+Ct.\s+Santa\s+Maria\s+Ca\s+93454\b'],
        ],
        'Fairfield Inn & Suites' => [
            'names'     => ['\bFairfield\s+Inn\s+&\s+Suites\b'],
            'addresses' => ['\b325\s+West\s+33rd\s+St\s+New\s+York,\s+Ny\s+10001\b'],
        ],
        'Courtyard New York JFK Airport' => [
            'names'     => ['\bCourtyard\s+New\s+York\s+JFK\s+Airport\b'],
            'addresses' => ['\b145-11\s+N\s+Conduit\s+Avenue\s+Jamaica\s+Ny\s+11436\b'],
        ],
        'Courtyard Sacramento' => [
            'names'     => ['\bCourtyard\s+Sacramento!'],
            'addresses' => ['\b4422\s+Y\s+Street\s+Sacramento\s+Ca\s+95817\b'],
        ],
        'Courtyard San Angelo' => [
            'names'     => ['\bCourtyard\s+San\s+Angelo\b'],
            'addresses' => ['\b2572\s+Southwest\s+Blvd\s+San\s+Angelo,\s+TX\s+76901\b'],
        ],
        'Courtyard Frederick' => [
            'names'     => ['\bCourtyard\s+Frederick\b'],
            'addresses' => ['\b5225\s+Westview\s+Dr\s+Frederick,\s+Md\s+21703\b'],
        ],
        'Courtyard Sacramento Cal Expo' => [
            'names'     => ['\bCourtyard\s+Sacramento\s+Cal\s+Expo\b'],
            'addresses' => ['\b1782\s+Tribute\s+Road\s+Sacramento\s+Ca\s+95815\b'],
        ],
        'Courtyard Los Angeles LAX/El Segundo' => [
            'names'     => ['\bCourtyard\s+LOS\s+ANGELES\s+LAX\s*\/\s*EL\s+SEGUNDO\b'],
            'addresses' => ['\b2000\s+E\s+Mariposa\s+Avenue\s+El\s+Segundo,\s+Ca\s+90245\b'],
        ],
        'Courtyard Los Angeles LAX Century Blvd.' => [
            'names'     => ['\bCourtyard\s+Los\s+Angeles\s+LAX\s+Century Blvd.'],
            'addresses' => ['\b6161\s+West\s+Century\s+Blvd.\s+Los\s+Angeles\s+CA\s+90045\b'],
        ],
        'Courtyard Boston Logan Airport' => [
            'names'     => ['\bCourtyard\b'],
            'addresses' => ['\b225\s+McCLELLAN\s+HWY\s+Boston\s+Ma\s+02128\b'],
        ],
        'Moxy Minneapolis Uptown' => [
            'names'     => ['\bMOXY\s+Hotels\s+Minneapolis\s+Uptown\b'],
            'addresses' => ['\b1121\s+West\s+Lake\s+Street\s+Minneapolis,\s+MN\s+55408\b'],
        ],
        'Moxy NYC Chelsea' => [
            'names'     => ['\bMOXY\s+Hotels\s+NYC\s+Chelsea\b'],
            'addresses' => ['\b105\s+W.\s+28th\s+St\s+New\s+York,\s+NY\s+10001\b'],
        ],
        'MOXY Hotels Columbus Short North' => [
            'names' => ['\bMOXY Hotels\s*Columbus\s*Short\s*North\b'],
            'addresses' => ['\b808\s*N\.\s*High\s*Street\s*Columbus\,\s*OH\s*43215\b'],
        ],
        'MOXY Hotels Miami South Beach' => [
            'names' => ['\bMOXY\s*Hotels\s*Miami\s*South\s*Beach\b'],
            'addresses' => ['\b915\s*Washington\s*Ave\s*Miami\,\s*FL\s*33139\b'],
        ],
        'MOXY Hotels Seattle Downtown' => [
            'names' => ['\bMOXY\s*Hotels\s*Seattle\s*Downtown\b'],
            'addresses' => ['\b1016\s*Republican\s*Street\s*Seattle\,\s*WA\s*98109\b'],
        ],
        'MOXY Hotels NYC Times Square' => [
            'names' => ['\bMOXY\s*Hotels\s*NYC\s*Times\s*Square\b'],
            'addresses' => ['\b485\s*7th\s*Avenue\s*New\s*York\,\s*NY\s*10018\b'],
        ],
        'MOXY Hotels MOXY San Diego Downtown' => [
            'names' => ['\bMOXY\s*Hotels\s*MOXY\s*San\s*Diego\s*Downtown\b'],
            'addresses' => ['\b831\s*6th\s*Avenue\s*San Diego\,\s*CA\s*92101\b'],
        ],
        'MOXY Hotels NYC East Village' => [
            'names' => ['\bMOXY\s*Hotels\s*NYC\s*East\s*Village\b'],
            'addresses' => ['\b112\s*East\s*11th\s*Street\s*New\s*York\,\s*NY\s*10003\b'],
        ],
        'MOXY Hotels Portland Downtown' => [
            'names' => ['\bMOXY\s*Hotels\s*Portland\s*Downtown\b'],
            'addresses' => ['\b585\s*SW\s*10th\s*Street\s*Portland\,\s*OR\s*97205\b'],
        ],
        'MOXY Hotels' => [
            'names' => ['\bMOXY\s*Hotels\b'],
            'addresses' => [
                '\b2552\s*Guadalupe\s*Street\s*Austin\,\s*TX\s*78705\b',
                '\b1220\s*King\s*Street\s*Chattanooga\,\s*TN\s*37403\b',
                '\b210\s*O\'Keefe Avenue\s*New Orleans\,\s*LA\s*70112\b',
                '\b240\s*Tremont\s*Street\s*Boston\,\s*MA\s*02116\b',
                '\b40\s*North\s*Front\s*Street\s*Memphis\,\s*TN\s*38103\b',
                '\b530\s*North\s*LaSalle\s*Drive\s*Chicago\,\s*IL\s*60654\b'
            ],
        ],
        'MOXY Hotels NYC Downtown' => [
            'names' => ['\bMOXY\s*Hotels\s*NYC\s*Downtown\b'],
            'addresses' => ['\b26\s*Ann\s*St\s*New\s*York\,\s*NY\s*10038\b'],
        ],
        'MOXY Hotels Nashville Vanderbilt At Hillsboro Village' => [
            'names' => ['\bMOXY\s*Hotels\s*Nashville\s*Vanderbilt\s*At\s*Hillsboro\s*Village\b'],
            'addresses' => ['\b1911\s*Belcourt\s*Avenue\s*Nashville\,\s*TN\s*37212\b'],
        ],
        'AC Hotel Base' => [
            'names'         => ['\bAC\s+HOTELS\s+BY\s+MARRIOTT\s*®?[A-Z\s]*\b'],
            'addressesBase' => ['\d{3,}.+?\d{3,}'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@marriott.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return preg_match('/Your .+ Stay at .+/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = false;
        $detectLanguage = false;

        // Detected Provider (from HTML)
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Marriott International, Inc. All rights reserved") or contains(normalize-space(.),"Your privacy is important to Marriott.")]')->length > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//marriott.com")]')->length > 0;

        if ($condition1 || $condition2) {
            $detectProvider = true;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            // Detected Provider (from PDF)
            if (!$detectProvider) {
                $detectProvider = stripos($textPdf, 'See our "Privacy & Cookie Statement" on Marriott.com') !== false || stripos($textPdf, 'Operated under license from Marriott International, Inc') !== false;
            }

            // Detected Language (from PDF)
            $detectLanguage = $this->assignLangPdf($textPdf);

            if ($detectProvider && $detectLanguage) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLangPdf($textPdf)) {
                $textPdfFull .= $textPdf . "\n";
            }
        }

        if (!$textPdfFull) {
            $this->logger->debug('Can\'t determine a language!');

            return $email;
        }

        $email->setType('YourStayPdf' . ucfirst($this->lang));
        $this->parsePdf($email, $textPdfFull);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parsePdf(Email $email, string $textPdf)
    {
        $patterns = [
            'time' => '\d{1,2}[:]+\d{2}(?:[ ]*[AaPp]\.?[Mm]\.?)?',
        ];

        $posStart = 0;
        $posEnd = strpos($textPdf, 'See our "Privacy & Cookie Statement" on Marriott.com.');

        if ($posEnd === false) {
            $posEnd = strpos($textPdf, 'Operated under license from Marriott');
        }

        if ($posStart === false || $posEnd === false) {
            return;
        }

        $text = substr($textPdf, $posStart, $posEnd - $posStart);

        $h = $email->add()->hotel();

        // hotelName
        // address
        // phone
        if (preg_match("/\s*[\W]*(.+?)\s+^([^\n]+[ ]{2,}){$this->opt($this->t('Room:'))}/ms", $text, $matches)) {
            if (preg_match('/^\S/', $matches[1])) {
                $matches[1] = str_repeat(' ', 30) . $matches[1];
            }

            $this->parseNameAddress($h, $matches[1]); // it-14293161

            if (!$h->getHotelName() || !$h->getAddress()) {
                $headerTable = $this->splitCols($matches[1]); // it-14293176
                $headerText = implode("\n", $headerTable);
                $this->parseNameAddress($h, $headerText);
            }

            if (!$h->getHotelName() || !$h->getAddress()) {
                $headerTable = $this->splitCols($matches[1], [0, mb_strlen($matches[2])]); // it-14293109
                $headerText = implode("\n", $headerTable);
                $this->parseNameAddress($h, $headerText);
            }

            if (!$h->getHotelName() || !$h->getAddress()) {
                // it-57645469.eml

                /*
                    Courtyard by Marriott Los Angeles Hacienda Heights / Orange County
                    1905 S. Azusa Avenue, Hacienda Heights Ca 91745 P 626.965.1700
                    Marriott.com/LAXHH
                */
                $pattern = "/[\W\s]*"
                    . "(?<name>.{3,}?)(?:\s+\/.{2,}?)?\n+"
                    . "[ ]*(?<address>.{3,}?)\s+[PT][ ]*(?<phone>\d{3}\.\d{3}\.\d{4})\n+"
                    . "[\w. ]*(?i)(?:Marriott.com|Springhillsuites.com|\b[A-z\d][-.A-z\d]*[A-z\d]\.com)"
                    . "/";

                if (preg_match($pattern, $matches[1], $m)) {
                    $h->hotel()
                        ->name(preg_replace('/\s+/', ' ', $m['name']))
                        ->address(preg_replace('/\s+/', ' ', $m['address']))
                        ->phone($m['phone']);
                }
            }
        }

        // travellers
        $guestName = preg_match("/^[ ]*([A-z][-.\'\/A-z ]*?[.A-z])[ ]{2,}{$this->opt($this->t('Room:'))}/m", $text, $matches) ? $matches[1] : '';

        if (!empty($guestName)) {
            $h->general()->travellers([$guestName]);
        }

        $r = $h->addRoom();

        // r.type
        $roomType = preg_match("/\b{$this->opt($this->t('Room Type:'))}[ ]*(.+?)(?:[ ]{2,}|$)/m", $text, $matches) ? $matches[1] : '';
        $r->setType($roomType);

        // guestCount
        $guests = preg_match("/\b{$this->opt($this->t('Number of Guests:'))}[ ]*(\d{1,3}?)(?:[ ]{2,}|$)/m", $text, $matches) ? $matches[1] : '';
        $h->booked()->guests($guests);

        // r.rate
        $roomRate = preg_match("/(?:^|[ ]{2,}){$this->opt($this->t('Rate:'))}[ ]*(.+?)(?:[ ]{2,}|$)/mu", $text, $matches) ? $matches[1] : '';
        $r->setRate($roomRate, true);

        /*
            Arrive: 11May18    Time: 03:51PM    Depart: 13May18    Time: 02:15PM
        */

        // OR

        /*
            ARRIVE: 22MAY20    TIME: 03:09PM
            DEPART: 24MAY20    TIME: 02:07PM
        */

        $patterns['dateTime'] = "/"
            . "{$this->opt($this->t('Arrive:'))}[ ]*(?<dateCheckIn>.{6,}?)"
            . "[ ]+{$this->opt($this->t('Time:'))}[ ]*(?<timeCheckIn>{$patterns['time']})"
            . "\s+{$this->opt($this->t('Depart:'))}[ ]*(?<dateCheckOut>.{6,}?)"
            . "[ ]+{$this->opt($this->t('Time:'))}[ ]*(?<timeCheckOut>{$patterns['time']})?"
            . "/";

        if (preg_match($patterns['dateTime'], $text, $matches)) {
            $h->booked()->checkIn2($matches['dateCheckIn'] . ', ' . $matches['timeCheckIn']);
            $h->booked()->checkOut2($matches['dateCheckOut'] . ', ' . $matches['timeCheckOut']);
        }

        $paymentTableText = preg_match("/\n(.+ {$this->opt($this->t('Credits'))}\n[\s\S]+?)\n[ ]*{$this->opt($this->t('Balance:'))}.+/i", $text, $m) ? $m[1] : null;
        $tablePos = [0];

        if (preg_match("/^(((.+ ){$this->opt($this->t('Description'))}[ ]+){$this->opt($this->t('Charges'))}[ ]+){$this->opt($this->t('Credits'))}\n/i", $paymentTableText, $matches)) {
            unset($matches[0]);

            foreach (array_reverse($matches) as $textHeaders) {
                $tablePos[] = mb_strlen($textHeaders);
            }
        }
        $paymentTable = $this->splitCols($paymentTableText, $tablePos);

        // p.total
        if (preg_match_all('/^[ ]*Amount:[ ]*(\d[,.\d ]*?)(?:[ ]+|$)/im', $text, $amountMatches)) {
            $amounts = array_map(function ($item) {
                return $this->normalizeAmount($item);
            }, $amountMatches[1]);
            $h->price()->total(array_sum($amounts));
        } else {
            $amounts = [];
            $pTRows = preg_split('/[ ]*\n+[ ]*/', trim($paymentTable[3]));

            foreach ($pTRows as $pTRow) {
                if (preg_match("/^{$this->opt($this->t('Credits'))}$/i", $pTRow)) {
                    continue;
                }

                if (preg_match("/^\d[,.\'\d ]*$/", $pTRow)) {
                    $amounts[] = $this->normalizeAmount($pTRow);
                } else {
                    $amounts = [];

                    break;
                }
            }

            if (count($amounts)) {
                $h->price()->total(array_sum($amounts));
            }
        }

        // accountNumbers
        if (preg_match('/\s+Account #[ ]*([X]{5,}\d{4,})\./i', $text, $m)) {
            // Marriott Bonvoy Account # XXXXX0000.    |    Rewards Account # XXXXX4145.
            $h->program()->account($m[1], true);
        }

        // confirmationNumber
        if (preg_match('/Account\s+Statement\s+or\s+your\s+online\s+Statement\s+for\s+updated\s+activity/i', $text)
            || preg_match('/Enroll\s+today\s+at\s+the\s+front\s+desk/i', $text)
        ) {
            $h->general()->noConfirmation();
        }
    }

    private function parseNameAddress($h, $headerText)
    {
        $this->logger->debug($headerText);

        foreach ($this->supportedHotels as $supportedHotel) {
            $hotelName = '';
            $hotelAddress = '';

            foreach ($supportedHotel['names'] as $name) {
                if (preg_match('/(' . $name . ')/iu', $headerText, $matches)) {
                    $hotelName = $matches[1];
                }
            }

            if (isset($supportedHotel['addresses'])) {
                foreach ($supportedHotel['addresses'] as $address) {
                    if (preg_match('/(' . $address . ')/i', $headerText, $matches)) {
                        $hotelAddress = $matches[1];
                    }
                }
            } elseif (isset($supportedHotel['addressesBase'])) {
                foreach ($supportedHotel['addressesBase'] as $address) {
                    if (preg_match('/(' . $address . ')\s+T:/is', $headerText, $matches)) {
                        $hotelAddress = $matches[1];
                    }
                }
            }

            if ($hotelName && $hotelAddress) {
                $h->hotel()->name(preg_replace('/\s+/', ' ', $hotelName));
                $h->hotel()->address(preg_replace('/\s+/', ' ', $hotelAddress));

                if ( // T916.929.7900
                    preg_match('/\b[PT]?[: ]*(\d{3}\.\d{3}\.\d{4})\b/', $headerText, $m)
                    // t(612) 822 5020    |    T: 214 290 0111
                    || preg_match('/(?:^|[ ]{2})[PT]?[: ]*([+(\d][-. \d)(]{5,}[\d)])(?:[ ]{2}|$)/im', $headerText, $m)
                ) {
                    $h->hotel()->phone($m[1]);
                }

                break;
            }
        }
    }

    private function rowColsPos($row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLangPdf($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
