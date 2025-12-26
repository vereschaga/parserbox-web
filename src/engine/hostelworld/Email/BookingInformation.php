<?php

namespace AwardWallet\Engine\hostelworld\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingInformation extends \TAccountChecker
{
    public $mailFiles = "hostelworld/it-12262888.eml, hostelworld/it-12763448.eml, hostelworld/it-14953677.eml, hostelworld/it-150979137.eml, hostelworld/it-150985507.eml, hostelworld/it-151104118.eml, hostelworld/it-153149443.eml, hostelworld/it-52037572.eml, hostelworld/it-59210575.eml, hostelworld/it-59222501.eml";
    protected $langDetectors = [
        'en' => ['Check Out:'],
        'es' => ['Llegada:'],
        'pt' => ['Hora de chegada:'],
        'de' => ['Auschecken:', 'einchecken:'],
        'it' => ['Cancellazione gratuita', 'Totale pagato'],
        'fr' => ['Informations sur la réservation'],
        'sv' => ['bokningsinformation'],
        'no' => ['bestillingsinformasjon'],
    ];
    protected $lang = '';
    protected static $dict = [
        'en' => [
            'Booking Information' => ['Booking Information', 'booking information'],
            'your booking is'     => ['your booking is', 'your booking has been'],
            //            'cancelled' => '',
        ],
        'es' => [
            'Booking Information'           => ['Información de reserva'],
            'Hi '                           => 'Hola ',
            'your booking is'               => 'tu reserva está',
            'Reference Number:'             => 'Número de reserva:',
            'Check In:'                     => 'Llegada:',
            'Arrival Time:'                 => 'Hora de llegada:',
            'You are due to arrive here at' => 'Su llegada esta programada para las',
            'Check Out:'                    => 'Salida:',
            'Total Cost:'                   => 'Coste total:',
            'Total Paid:'                   => 'Total abonado:',
            //            'cancelled' => '',
        ],
        'pt' => [
            'Booking Information'           => 'Informações sobre a reserva',
            'Hi '                           => 'Oi ',
            'your booking is'               => 'sua reserva está',
            'Reference Number:'             => 'Número da reserva:',
            'Check In:'                     => ['Check-In:', 'Chega:'],
            'Arrival Time:'                 => 'Hora de chegada:',
            'You are due to arrive here at' => 'Você deverá chegar aqui as',
            'Check Out:'                    => ['Check-out:', 'Parte:'],
            'Free cancellation before'      => 'Cancelamento gratuito até',
            'Total Cost:'                   => 'Custo total:',
            'Total Paid:'                   => 'Total pago:',
            //            'cancelled' => '',
        ],
        'de' => [
            'Booking Information'           => 'Buchungsinformationen',
            'Hi '                           => 'Hi ',
            'your booking is'               => 'deine Buchung ist',
            'Reference Number:'             => 'Reservierungsnummer:',
            'Check In:'                     => ['Einchecken:', 'einchecken:'],
            'Arrival Time:'                 => 'Ankunftszeit:',
            'You are due to arrive here at' => 'Deine angegebene Ankunftszeit ist',
            'Check Out:'                    => ['Auschecken:', 'überprüfen:'],
            'Free cancellation before'      => 'Kostenlose Stornierung bis',
            'Total Cost:'                   => 'Gesamtkosten:',
            'Total Paid:'                   => 'Gezahlter Gesamtbetrag:',
            //            'cancelled' => '',
        ],
        'it' => [
            'Booking Information'           => 'Informazioni per la prenotazione',
            'Hi '                           => 'Ciao ',
            'your booking is'               => 'la tua prenotazione é',
            'Reference Number:'             => 'Numero di prenotazione:',
            'Check In:'                     => ['Data di arrivo:', 'Arrivo:'],
            'Arrival Time:'                 => 'Ora di arrivo:',
            'You are due to arrive here at' => ["Il suo arrivo qui e' previsto alle"],
            'Check Out:'                    => ['Data di partenza:', 'Partenza:'],
            'Check out before'              => ['Check out:', 'check out is before'],
            'Free cancellation before'      => 'Cancellazione gratuita entro le',
            'Total Cost:'                   => 'Costo totale:',
            'Total Paid:'                   => 'Totale pagato:',
            //            'cancelled' => '',
        ],
        'fr' => [
            'Booking Information'           => 'Informations sur la réservation',
            'Hi '                           => 'Bonjour ',
            'your booking is'               => 'votre réservation est',
            'Reference Number:'             => 'Numéro de réservation:',
            'Check In:'                     => ['Arrivée:'],
            'Arrival Time:'                 => "Heure d'arrivée:",
            //'You are due to arrive here at' => [""],
            'Check Out:'                    => ['Départ:'],
            //'Check out before' => [''],
            'Free cancellation before'      => "Annulation gratuite jusqu'à",
            'Total Cost:'                   => 'Coût total:',
            'Total Paid:'                   => 'Total payé:',
            //            'cancelled' => '',
        ],
        'no' => [
            'Booking Information'           => 'bestillingsinformasjon',
            'Hi '                           => 'Hei',
            'your booking is'               => 'bestillingen din er',
            'Reference Number:'             => 'Reservasjonsnummer:',
            'Check In:'                     => ['Innsjekk:'],
            'Arrival Time:'                 => "Ankomsttid:",
            'You are due to arrive here at' => ["Du ankommer her på"],
            'Check Out:'                    => ['Utsjekk:'],
            'Check out before'              => ['Check out before'],
            'Free cancellation before'      => "Gratis avbestilling innen kl.",
            'Total Cost:'                   => 'Totalbeløp:',
            'Total Paid:'                   => 'Total Betalt:',
            //            'cancelled' => '',
        ],
        'sv' => [
            'Booking Information'           => 'bokningsinformation',
            'Hi '                           => 'Hej ',
            'your booking is'               => 'din bokning är',
            'Reference Number:'             => 'Bokningsnummer:',
            'Check In:'                     => ['Checka in:'],
            'Arrival Time:'                 => 'Ankomsttid:',
            'You are due to arrive here at' => ['Din ankomsttid är'],
            'Check Out:'                    => ['Checka ut:'],
            'Check out before'              => ['Check out before'],
            'Free cancellation before'      => 'Gratis avbokning senast kl. ',
            'Total Cost:'                   => 'Totalkostnad:',
            'Total Paid:'                   => 'Totalt betalat:',
            //'cancelled' => '',
        ],
    ];
    private $download = [
        'en'=> 'Download the Hostelworld App',
        'es'=> 'Descargar la aplicación Hostelworld',
        'pt'=> 'Baixar o app Hostelworld',
        'de'=> 'Bestätigte Buchung von hostelworld',
        'it'=> 'Scarica la App di Hostelworld',
        'fr'=> 'Téléchargez l’application Hostelworld',
        'no'=> 'Last ned Hostelworld-appen',
        'sv'=> 'Hostelworlds mobilapp',
    ];
    private $detectSubject = [
        'en'  => 'Confirmed booking from hostelworld.com',
        'en2' => 'Hostelworld.com - Booking Cancelled',
        'es'  => 'Reserva confirmada Hostelworld.com',
        'pt'  => 'Reserva confirmada pelo Hostelworld.com',
        'de'  => 'Bestätigte Buchung von hostelworld.com',
        'it'  => 'Prenotazione confermata da Hostelworld.com',
        'fr'  => "Reservation confirmée de la part d'Hostelworld.com",
        'no'  => "Bekreftet bestilling fra Hostelworld.com",
        'sv'  => "Hostelworlds Villkor",
    ];

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Hostelworld') !== false
            || stripos($from, '@hostelworld.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query("//node()[({$this->contains($this->download)}) or contains(.,'@hostelworld.com')]")->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"hostelworld.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() === false) {
            return false;
        }
        $this->parseEmail($email);

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

    protected function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        $patterns = [
            'phone'   => '[-+\d\s\/)(]{5,}[\d)]', // +81-762561100 / +81-762561101
            'time'    => '\d{1,2}(?:[:.]\d{2})?(?:\s*[AaPp][Mm])?', // 11:30pm    |    1pm
            'payment' => '(\D+)(\d[,.\d\s]*)', // €5.20    |    TRY160.00
        ];

        // Hi Stephen, your booking is confirmed
        $guestStatus = $this->http->FindSingleNode("//*[self::th or self::td][not(.//*[self::th or self::td])][({$this->starts($this->t('Hi '))}) and ({$this->contains($this->t('your booking is'))})]");

        // GuestNames
        if (preg_match("/^{$this->opt($this->t('Hi '))}\s*(?<fullName>[A-z][-.'A-z ]*[.A-z])\s*,/i", $guestStatus, $matches)) {
            $h->addTraveller($matches['fullName']);
        }

        // Status
        if (preg_match("/{$this->opt($this->t('your booking is'))}\s*(\w+)/u", $guestStatus, $matches)) {
            $h->general()
                ->status($matches[1]);

            if (preg_match("#^" . $this->opt($this->t('cancelled')) . "$#i", $matches[1])) {
                $h->general()
                    ->cancelled();
            }
        }

        // ConfirmationNumber
        if ($conf = $this->http->FindSingleNode("//p[{$this->starts($this->t('Reference Number:'))}]", null, true, '/^[^:]+:\s*([-A-Z\d]{5,})$/')) {
            $h->general()
                ->confirmation($conf);
        } elseif ($confInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your old reference number is'))}]")) {
            $h->general()
                ->confirmation($this->http->FindPreg("/{$this->opt($this->t('Your old reference number is'))}\s*([-A-Z\d]{5,})/"), 'old reference number')
                ->confirmation($this->http->FindPreg("/{$this->opt($this->t('your new reference number is'))}\s*([-A-Z\d]{5,})/"), 'new reference number', true);
        }

        $xpathFragment1 = "//text()[{$this->eq($this->t('Booking Information'))}]/following::text()[normalize-space(.)!='']";

        // HotelName
        if ($hName = $this->http->FindSingleNode($xpathFragment1 . '[1]', null, true, '/^.{0,1}\w.+/s')) {
            $h->hotel()
                ->name($hName)
                ->address($this->http->FindSingleNode($xpathFragment1 . '[2]', null, true, '/\w.+/s'));
        }

        // Phone
        $phone = $this->http->FindSingleNode($xpathFragment1 . '[4]', null, true, '/^' . $patterns['phone'] . '/');

        if ($phone) {
            $h->hotel()
                ->phone($phone);
        }

        // Fax
        $fax = $this->http->FindSingleNode($xpathFragment1 . '[5]', null, true, '/^' . $patterns['phone'] . '/');

        if ($fax) {
            $h->hotel()
                ->fax($fax);
        }

        // CheckInDate
        $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In:'))}]/following::text()[normalize-space(.)!=''][1]");

        if ($dateCheckIn) {
            $h->booked()
                ->checkIn($this->normalizeDate($dateCheckIn));
            $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Time:'))}]/following::text()[normalize-space(.)!=''][1]",
                null, true, '/(' . $patterns['time'] . ')/');

            if (empty($timeCheckIn)) {
                $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check in from'))}]", null,
                    true, '/(' . $patterns['time'] . ')/');
            }

            if (empty($timeCheckIn)) {
                $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You are due to arrive here at'))}]",
                    null, true, '/(' . $patterns['time'] . ')/');
            }

            if ($timeCheckIn && $h->getCheckInDate()) {
                $timeCheckIn = str_replace(".", ":", $timeCheckIn);
                $h->booked()
                    ->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
            }
        }

        // CheckOutDate
        $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out:'))}]/following::text()[normalize-space(.)!=''][1]");

        if ($dateCheckOut) {
            $h->booked()
                ->checkOut($this->normalizeDate($dateCheckOut));
            $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check out before'))}]",
                null, true, "/{$this->opt($this->t('Check out before'))}\s+({$patterns['time']})/");

            if (empty($timeCheckOut)) {
                $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('check out time is before'))}]",
                    null, true, "/{$this->opt($this->t('check out time is before'))}\s+({$patterns['time']})/");
            }

            if (empty($timeCheckOut)) {
                $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check out Time'))}][1]", null, true, "/{$this->t('Check out Time')}\:[ ]*before[ ]*(\d{1,2}:\d{2} [ap]m)/");
            }

            if ($timeCheckOut && $h->getCheckOutDate()) {
                $timeCheckOut = str_replace(".", ":", $timeCheckOut);
                $h->booked()
                    ->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
            }
        }

        // Rate
        // RoomType
        $rateTexts = [];
        $roomTypeValues = [];
        $rateRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Check Out:'))}]/ancestor::table[./descendant::text()[{$this->eq($this->t('Arrival Time:'))}]][1]/following::table[ ./following::text()[{$this->eq($this->t('Total Cost:'))}] ]");

        if ($rateRows->count() == 0) {
            $rateRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Check Out:'))}]/ancestor::table[./descendant::text()[{$this->eq($this->t('Arrival Time:'))}]][1]/following::table/descendant::text()[{$this->contains($this->t('Bed'))}]/ancestor::table[1]");
        }

        foreach ($rateRows as $rateRow) {
            $rateDate = $this->http->FindSingleNode('./descendant::td[normalize-space(.) and not(.//td)][1]', $rateRow);
            $ratePayment = $this->http->FindSingleNode('./descendant::tr[normalize-space(.) and not(.//tr)][2]/td[normalize-space(.)][2]',
                $rateRow, true, '/^(' . $patterns['payment'] . ')/');

            if ($rateDate && $ratePayment) {
                $rateTexts[] = $rateDate . ': ' . $ratePayment;
                $roomTypeText = $this->http->FindSingleNode('./descendant::tr[normalize-space(.) and not(.//tr)][2]/td[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1]',
                    $rateRow);

                if ($roomTypeText) {
                    $roomTypeValues[] = $roomTypeText;
                }
            } elseif ($h->getCancelled() && count($this->http->FindNodes('./descendant::tr[normalize-space(.) and not(.//tr)]', $rateRow)) == 1) {
                $roomTypeValues[] = $this->http->FindSingleNode('./descendant::td[normalize-space(.) and not(.//td)][1]', $rateRow);
                $rateTexts[] = $this->http->FindSingleNode('./descendant::tr[normalize-space(.) and not(.//tr)][1]/td[normalize-space(.)][2]',
                    $rateRow, true, '/^(' . $patterns['payment'] . ')/');
            }
        }

        if (count($rateTexts)) {
            $rateValue = implode('; ', $rateTexts);

            if (mb_strlen($rateValue) < 201) {
                if (!isset($r)) {
                    $r = $h->addRoom();
                }
                $r->setRate($rateValue);
            }
        }

        if (count($roomTypeValues)) {
            if (!isset($r)) {
                $r = $h->addRoom();
            }
            $r->setType(implode('; ', array_unique($roomTypeValues)));
        }

        // Currency
        // Total
        $totalCost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Cost:'))}]/following::text()[normalize-space(.)!=''][1]", null, true, '/.+\d$/');

        if (preg_match('/^' . $patterns['payment'] . '/', $totalCost, $matches)) {
            $h->price()
                ->currency($this->normalizeCurrency($matches[1]))
                ->total($this->normalizePrice($matches[2]));
        }

        /* $totalPaid = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Paid:'))}]", null, true, '/^[^:]+:\s*(.+)/');
         if ( preg_match('/\s*(\d[,.\d]*)/', $totalPaid, $m) )
             $h->price()
                 ->total($this->normalizePrice($m[1]));

         if ( preg_match('/([^\d]+)\s*\d[,.\d]/', $totalPaid, $m) )
             $h->price()
                 ->currency($this->normalizeCurrency($m[1]));*/
        // Taxes
//            $arrivalPay = $this->http->FindSingleNode("//text()[{$this->starts($this->t('The balance of'))}]", null,
//                true,
//                "/^{$this->opt($this->t('The balance of'))}\s+(.+?)\s+{$this->opt($this->t('is payable on arrival'))}/");
//            if (preg_match('/^' . preg_quote($matches[1], '/') . '\s*(\d[,.\d]*)/', $arrivalPay, $m)) {
//                $h->price()
//                    ->tax($this->normalizePrice($m[1]));
//            }
//        }

        // CancellationPolicy
        $cancellationPolicyText = $this->http->FindSingleNode("(//node()[{$this->starts($this->t('Free cancellation before'))}][1])[2]");

        if (empty($cancellationPolicyText)) {
            $cancellationPolicyText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation policy:'))}]",
            null, true, "/{$this->opt($this->t('Cancellation policy:'))}\s*(.+)/");
        }

        if (!empty($cancellationPolicyText)) {
            $h->general()
                ->cancellation($cancellationPolicyText);
            //En
            if (preg_match('/Free cancellation before (\d{1,2}:\d{2}) on (\d{1,2})th (\w+ \d{2,4}).+/', $cancellationPolicyText, $m)) {
                $h->booked()
                    ->deadline(strtotime($m[2] . ' ' . $m[3] . ', ' . $m[1]));
            } elseif (preg_match('/At least (\d{1,2} days) advance notice for free cancellation/', $cancellationPolicyText, $m)) {
                $h->booked()
                    ->deadlineRelative($m[1], '00:00');
            }
            //Pt
            if (preg_match('/Cancelamento gratuito até (\d{1,2}:\d{2})\s+em\s+(\d{1,2})\s+(\w+ \d{2,4}).+/', $cancellationPolicyText, $m)) {
                $h->booked()
                    ->deadline($this->normalizeDate($m[2] . ' ' . $m[3] . ', ' . $m[1]));
            }
            //de
            if (preg_match('/Kostenlose Stornierung bis (\d{1,2}:\d{2})\s+am\s+(\d{1,2})\s+(\w+ \d{2,4}).+/', $cancellationPolicyText, $m)) {
                $h->booked()
                    ->deadline($this->normalizeDate($m[2] . ' ' . $m[3] . ', ' . $m[1]));
            }
            //it
            if (preg_match('/Cancellazione gratuita entro le (\d+)\;(\d+) del (\d+\s*\w+\s*\d{4}) \(CEST\)/', $cancellationPolicyText, $m)
            || preg_match("/Annulation gratuite jusqu'à (\d+)\:(\d+), le (\d+\s*\w+\s*\d{4}) \(BST\)/", $cancellationPolicyText, $m)
            || preg_match("/Gratis avbestilling innen kl. (\d+)\.(\d+) den (\d+\s*\w+\s*\d{4}) \(/", $cancellationPolicyText, $m)
            || preg_match("/Gratis avbokning senast kl. (\d+)\:(\d+) den (\d+\s*\w+\s*\d{4}) \(/", $cancellationPolicyText, $m)
            ) {
                $h->booked()
                    ->deadline($this->normalizeDate($m[3] . ', ' . $m[1] . ':' . $m[2]));
            }
        }
    }

    protected function normalizeCurrency($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];
        $string = trim($string);

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    protected function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Fri 27th Apr 2018
            '#^[\w\-]+,?\s+(\d+)\w*\s+(\w+)\s+(\d{4})$#u',
            //26 Abr 2020, 23:59 (Pt)
            '#^(\d+)\w*\s+(\w+)\s+(\d{4})[,]\s(\d+[:]\d+)#u',
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }
}
