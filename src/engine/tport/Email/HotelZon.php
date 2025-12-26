<?php

namespace AwardWallet\Engine\tport\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelZon extends \TAccountChecker
{
    public $mailFiles = "tport/it-112655613.eml, tport/it-20000812.eml, tport/it-36127629.eml, tport/it-36127826.eml";

    public $reBody = [
        'de' => ['Reservierungsnummer'],
        'fi' => ['Varausvahvistus'],
        'en' => ['Reservation number'],
        'sv' => ['Bokningsinformation'],
    ];
    public $lang = '';
    public static $dict = [
        'sv' => [
            'Reservation number' => 'Bokningsnummer',
            'Phone'              => 'Telefonnummer',
            'Arrival date'       => 'Ankomstdatum',
            'Departure'          => 'Avresedatum',
            'Room type'          => 'Rumstyp',
            //'Preferences'         => '',
            'Number of adults'    => 'Antal vuxna',
            'Payment information' => 'Betalningsinformation',
            'Rate'                => 'Pris(er)',
            'Total'               => 'Totalpris',
            'Rate description'    => 'Prisbeskrivning',
            'Guest'               => 'Gäst',
            'Cancellation policy' => 'Avbokningsregler',
        ],
        'de' => [
            'Reservation number'  => 'Reservierungsnummer',
            'Phone'               => 'Tel.',
            'Arrival date'        => 'Ankunftsdatum',
            'Departure'           => 'Abreise',
            'Preferences'         => 'Zimmertyp',
            'Number of adults'    => 'Anzahl Erwachsene',
            'Payment information' => 'Zahlung Informationen',
            'Rate'                => 'Preis(e)',
            'Total'               => 'Gesamtpreis',
            'Rate description'    => 'Preisdefinition',
            'Guest'               => 'Gast',
            'Cancellation policy' => 'Stornierungsrahmen',
        ],
        'fi' => [
            'Reservation number'  => 'Varausvahvistus',
            'Phone'               => 'Puhelinnumero',
            'Fax'                 => 'Faksi',
            'Arrival date'        => 'Tulopäivä',
            'Departure'           => 'Lähtöpäivä',
            'Preferences'         => 'Toiveet',
            'Number of adults'    => 'Aikuisten lukumäärä',
            'Payment information' => 'Maksutiedot',
            'Rate'                => 'Hinta/hinnat',
            'Total'               => 'Yhteensä',
            'Room type'           => 'Huonetyyppi',
            'Rate description'    => 'Hintakuvaus',
            'Guest'               => 'Matkustaja',
            'Cancellation policy' => 'Peruutussääntö',
        ],
        'en' => [
            'Reservation number'  => 'Reservation number',
            'Arrival date'        => 'Arrival date',
            'Payment information' => ['Payment information', 'Payment Method'],
        ],
    ];
    private $code;
    private static $providers = [
        'tport' => [
            'from' => ['travelport.com'],
            'subj' => [
                'de' => 'Reservierungsbestätigung',
                'fi' => 'Varausvahvistus',
                'en' => 'Reservation confirmation',
                'sv' => 'Bokningsbekräftelse',
            ],
            'body' => [
                '//a[contains(@href,"Travelport.com") or contains(@href,"travelport.com")]',
                'Travelport',
                'TRAVELPORT HOTELZON',
            ],
        ],
        'wagonlit' => [
            'from' => ['cwt.com', 'cwtsatotravel.com', 'carlsonwagonlit.com', 'contactcwt.com'],
            'subj' => [
                'de' => 'Reservierungsbestätigung',
                'fi' => 'Varausvahvistus',
                'en' => 'Reservation confirmation',
            ],
            'body' => [
                '//a[contains(@href,"carlsonwagonlit.com")]',
                'Carlson Wagonlit Travel',
                'CWT',
            ],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        if (stripos($from, '@hotelzon.com') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $this->parseEmail($email);
        $email->setType("Hotelzon" . ucfirst($this->lang));

        if (null !== ($code = $this->getProvider($parser))) {
            $email->setProviderCode($code);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ((null !== $this->getProviderByBody())
            && (
                $this->http->XPath->query("//img[contains(@src,'hotelzon')]")->length > 0
                || $this->http->XPath->query("//text()[contains(.,'Hotelzon')]")->length > 0
            )
        ) {
            foreach ($this->reBody as $lang => $reBody) {
                $arrBody = (array) $reBody;

                foreach ($arrBody as $re) {
                    if (strpos($body, $re) !== false) {
                        return $this->assignLang();
                    }
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'tport') {
                return null;
            } else {
                return $this->code;
            }
        }

        return $this->getProviderByBody();
    }

    private function getProviderByBody()
    {
        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($this->getField($this->t('Reservation number')))
            ->traveller($this->getField($this->t('Guest')));

        $hotelName = $this->http->FindSingleNode("(//text()[contains(.,'{$this->t('Phone')}')]/ancestor::tr[1]/preceding::table[1])[normalize-space(.)!=''][1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//img[contains(@src,'star.jpg')]/ancestor::tr[1]");
            $node = implode("\n",
                $this->http->FindNodes("//img[contains(@src,'star.jpg')]/ancestor::tr[1]/following::tr[normalize-space()!=''][1]/td[1]//text()[normalize-space()!='']"));

            if (preg_match("#(.+)\n([\+\-\d ]+)$#s", $node, $m)) {
                $address = preg_replace("#\s+#", ' ', $m[1]);
                $phone = $m[2];
            }
        } else {
            $address = $this->http->FindSingleNode("(//text()[contains(.,'{$this->t('Phone')}')])[1]/ancestor::tr[1]/preceding-sibling::tr[1]");
            $phone = $this->getField($this->t('Phone'));
            $fax = $this->getField($this->t('Fax'));
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//img[contains(@src, '//a-book.hotelzon.com/confirmationscaledimages')]/following::text()[normalize-space()!=''][1]/ancestor::td[1]");
            $node = implode("\n",
                $this->http->FindNodes("//img[contains(@src, '//a-book.hotelzon.com/confirmationscaledimages')]/following::text()[normalize-space()!=''][1]/ancestor::td[1]/following::text()[normalize-space()!=''][1]/ancestor::table[1]//text()[normalize-space()!='']"));

            if (preg_match("#(.+)\n([\+\-\d ]+)$#s", $node, $m)) {
                $address = preg_replace("#\s+#", ' ', $m[1]);
                $phone = $m[2];
            }
        }
        $r->hotel()->name($hotelName);

        if (isset($address)) {
            $r->hotel()->address($address);
        }

        if (isset($phone)) {
            $r->hotel()->phone($phone);
        }

        if (isset($fax) && !empty($fax)) {
            $r->hotel()->fax($fax);
        }

        // CheckInDate
        $dateCheckIn = $this->getField($this->t('Arrival date'));

        if ($dateCheckIn && ($dateCheckInNormal = $this->normalizeDate($dateCheckIn))) {
            $r->booked()->checkIn(strtotime($dateCheckInNormal));
        }

        if (!empty($r->getCheckInDate()) && ($time = $this->getField($this->t('Check-in')))) {
            $r->booked()->checkOut(strtotime($time, $r->getCheckInDate()));
        }

        // CheckOutDate
        $dateCheckOut = $this->getField($this->t('Departure'));

        if ($dateCheckOut && ($dateCheckOutNormal = $this->normalizeDate($dateCheckOut))) {
            $r->booked()->checkOut(strtotime($dateCheckOutNormal));
        }

        // Rate
        // Total
        // Currency
        $paymentNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Payment information'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]");

        if ($paymentNodes->length > 0) {
            $rate = $this->getField($this->t('Rate'), $paymentNodes->item(0));
            $acc = $this->re("/\(([\w\-]{5,})\)/", $this->getField($this->t('Loyalty card'), $paymentNodes->item(0)));

            if (!empty($acc)) {
                $r->program()
                    ->account($acc, false);
            }
            $total = $this->re("#(?:.+:)?\s*(.+)#", $this->getField($this->t('Total'), $paymentNodes->item(0)));

            if (!empty($total)) {
                $r->price()
                    ->total($this->re('/^(\d[.\d]*)/', $total))
                    ->currency($this->re('/(?:\b|[^A-Z])([A-Z]{3})(?:[^A-Z]|\b)/', $total));
            }
        }

        if (empty($rate)) {
            $rateTexts = $this->http->FindNodes('//text()[' . $this->eq($this->t('Arrival date')) . ']/following::text()[' . $this->eq($this->t('Rate')) . '][1]/ancestor::td[1]/following-sibling::td[normalize-space(.)]');
            $rateText = implode(' ', $rateTexts);

            if ($rateText) {
                $rate = $rateText;
            }
        }

        $roomType = $this->getField($this->t('Room type'));
        $roomTypeDescription = $this->getField($this->t('Preferences'));

        if (mb_strlen($roomType) > 200) {
            $roomTypeDescription = $roomType;
            $roomType = null;
        }
        $rateType = $this->getField($this->t('Rate description'));
        $room = $r->addRoom();

        if (isset($rate)) {
            $room->setRate($rate);
        }

        if (isset($roomType)) {
            $room->setType($roomType);
        }

        if (isset($roomTypeDescription)) {
            $room->setDescription($roomTypeDescription);
        }

        if (isset($rateType)) {
            $room->setRateType($rateType);
        }

        $r->booked()->guests($this->getField($this->t('Number of adults')));

        $cancellationPolicy = $this->http->FindSingleNode("//text()[contains(.,'{$this->t('Cancellation policy')}')]/ancestor::*[1]/following-sibling::*[1]");

        if (empty($cancellationPolicy)) {
            $cancellationPolicy = $this->http->FindSingleNode("//text()[contains(.,'{$this->t('Cancellation policy')}')]/following::text()[normalize-space(.)!=''][1]");
        }
        $r->general()->cancellation($cancellationPolicy);
        $this->detectDeadLine($r);

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#^(\d+) HR CANCELLATION REQUIRED$#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' hours');
        } elseif (preg_match("#^CANCEL BY (\d+.+\d+\s*[ap]m)$#i", $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($this->normalizeDate($m[1])));
        }
        $h->booked()
            ->parseNonRefundable("#THE AMOUNT DUE IS NOT REFUNDABLE EVEN IF THE BOOKING IS CANCELLED OR MODIFIED#");
    }

    private function getField($field, $root = null)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[1]/ancestor::td[1]/following-sibling::td[1]", $root);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Reservation number"], $words["Arrival date"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Reservation number'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Arrival date'])}]")->length > 0
                ) {
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

    private function normalizeDate(string $string)
    {
        $in = [
            // AUG/29/2018
            '/^([^\d\W]{3,})\/(\d{1,2})\/(\d{4})$/u',
            // 05/FEB/2019
            '/^(\d{1,2})\/([^\d\W]{3,})\/(\d{4})$/u',
            //20.02.2019
            '/^(\d{1,2})\.(\d+)\.(\d{4})$/u',
            //14.05.19 6PM
            '/^(\d{1,2})\.(\d+)\.(\d{2})\s+(\d+)\s*([ap]m)$/iu',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
            '$3-$2-$1',
            '20$3-$2-$1, $4:00$5',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $string));

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }
}
