<?php

namespace AwardWallet\Engine\nhhotels\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "nhhotels/it-62049852.eml, nhhotels/it-33841933.eml, nhhotels/it-33893965.eml, nhhotels/it-35196921.eml, nhhotels/it-8228739.eml, nhhotels/it-48480570.eml, nhhotels/it-47909176.eml, nhhotels/it-54191532.eml, nhhotels/it-57037871.eml";

    public $reBody = [
        'en'   => ['Reservation number', 'Room'],
        'en2'  => ['Reservation number', 'Booker'],
        'en3'  => ['Booking Details', 'Occupancy:'],
        'de'   => ['Ihre Reservierung', 'Zimmer'],
        'es'   => ['Es un placer confirmar que tu reserva se ha realizado con éxito', 'reserva'],
        'es2'  => ['La reserva está casi completa', 'Garantizar Reserva'],
        'fr'   => ['Numéro de réservation', 'Votre réservation est confirmée et garantie'],
        'fr2'  => ['N° de réservation', 'Date d’arrivée'],
        'fr3'  => ['Numéro de réservation', 'PRÉPAYER EN LIGNE'],
        'nl'   => ['Hartelijk dank voor uw reservering', 'Kamers'],
        'nl2'  => ['Uw reservering bij', 'Kamers'],
        'nl3'  => ['Reserveringsnummer', 'Kamers'],
        'it'   => ['prenotazione', 'Camera'],
        'it2'  => ['prenotazione', 'camera'],
        'pt'   => ['Número da reserva', 'Quarto'],
        'pt2'  => ['Número de reserva', 'Quarto'],
        'pt3'  => ['Número da reserva', 'Sua reserva no'],
        'pt4'  => ['Detalhes da reserva', 'Ocupação:'],
    ];
    public $reSubject = [
        'en' => 'Your booking',
        'de' => 'Group Reservierungsbest',
        'nl' => 'Uw reservering bij', 'Dit zijn uw reserveringsgegevens van uw reservering in',
        'it' => 'La tua prenotazione NH Hotels',
    ];
    public $lang = '';
    public $year;
    public static $dict = [
        'en' => [
            'Reservation number' => ['Reservation number', 'Your reservation number is'],
            //			'Your reservation at' => '',
            'HotelNameRegex' => '#Your\s+reservation\s+at\s+the\s+(.+?)\s+has\s+been\s+completed\s+successfully#',
            //			'Rooms' => '',
            //			'Occupancy:' => '',
            //			'Adult' => '',
            //			'Child' => '',
            //			'Baby' => '',
            'Check-In'  => ['Check-In', 'Check-in', 'Check In', 'Check in', 'Arrival date'],
            'Check-Out' => ['Check-Out', 'Check-out', 'Check Out', 'Check out', 'Check out date'],
            'Price'     => ['Price', 'Room'],
            //			'VAT' => '',
            'Final price' => ['Final price', 'Total price'],
            'Guests'      => ['Guests', 'Guest details'],
            //			'Rate includes:' => '',
            'Rate Conditions' => ['Rate Conditions', 'See reservation terms and conditions'],
            'Rate'            => ['Rate', 'rate'],
            'Cancell'         => ['Cancell', 'cancell'],
            //            "Manage Your Booking" => "Manage reservation",
            //'MainGuest' => '',
        ],
        'de' => [
            'Reservation number'  => 'Reservierungsnummer',
            'Your reservation at' => 'Ihre Reservierung im',
            'HotelNameRegex'      => '#Ihre\s+Reservierung\s+im\s+(.+?)\s+war\s+erfolgreich#',
            'Rooms'               => 'Zimmer',
            'Occupancy:'          => 'Zimmerbelegung:',
            'Room'                => 'Zimmer',
            'Adult'               => ['Erwachsene', 'Erwachsener'],
            //			'Child' => '',
            //			'Baby' => '',
            'Check-In'        => ['Anreise', 'Anreisedatum'],
            'Check-Out'       => ['Abreise', 'Abreisedatum'],
            'Price'           => ['Preis', 'Zimmer'],
            'VAT'             => 'MwSt',
            'Final price'     => ['Endgültiger Preis', 'Gesamtbetrag'],
            'Guests'          => 'Gäste',
            'Occupancy'       => 'Belegung',
            'Rate includes:'  => 'Im Preis enthalten:',
            'Rate Conditions' => 'Preiskonditionen',
            'Rate'            => ['Preis', 'preis'],
            'Cancell'         => ['Storni', 'storni'],
            //            "Manage Your Booking" => "",
            //'MainGuest' => '',
        ],
        'es' => [
            'Reservation number'  => 'Número de reserva',
            'Your reservation at' => 'Tu reserva en el',
            'HotelNameRegex'      => '#Tu\s+reserva\s+en\s+el\s+(.+?)\s+se\s+realizó\s+con\s+éxito#u',
            'Rooms'               => ['Habitaciónes', 'Habitaciones'],
            'Occupancy:'          => 'Ocupación',
            'Room'                => 'Habitación',
            'Adult'               => ['adulto'],
            //			'Child' => '',
            //			'Baby' => '',
            'Check-In'        => ['Check in'],
            'Check-Out'       => ['Check out', 'Check-out'],
            'Price'           => 'Precio',
            'VAT'             => 'IVA',
            'Final price'     => ['precio Final', 'Precio total:'],
            'Guests'          => 'Nuestros huéspedes',
            'Occupancy'       => 'Ocupación',
            'Rate includes:'  => 'La tarifa incluye:',
            'Rate Conditions' => 'Condiciones de tarifa',
            //			'Rate' => ['Preis','preis'],
            'Cancell' => ['cancelar', 'modificada'],
            //            'Manage Your Booking' => '',
            'MainGuest' => 'Persona que reserva:',
        ],
        'pt' => [
            'Reservation number'  => ['Número da reserva', 'Número de reserva', 'O seu número de reserva é'],
            'Your reservation at' => 'Sua reserva no',
            'HotelNameRegex'      => '#Sua reserva no\s+(.+?)\s+foi concluída com sucesso#u',
            'Rooms'               => ['Quartos'],
            'Occupancy:'          => 'Ocupação:',
            'Room'                => 'Quarto',
            'Adult'               => ['Adulto', 'adultos'],
            'Child'               => 'crianças',
            //			'Baby' => '',
            'Check-In'        => ['Check-in', 'Data da chegada:'],
            'Check-Out'       => ['Check-out', 'Data do check-out::'],
            'Price'           => ['Preço', 'Quarto', 'Resumo do preço'],
            'VAT'             => ['VAT', 'IVA'],
            'Final price'     => ['Preço total', 'Preço Total'],
            'Guests'          => ['Hóspedes', 'Detalhes do hóspede'],
            'Occupancy'       => 'Ocupação',
            'Rate includes:'  => 'A tarifa inclui:',
            'Rate Conditions' => ['Condições da tarifa', 'Ver termos e condições da reserva'],
            //			'Rate' => ['Preis','preis'],
            'Cancell'             => ['cancel'],
            "Manage Your Booking" => ["Gerenciar sua reserva"],
            'MainGuest'           => 'Agente de reserva:',
            'Booking Details'     => 'Detalhes da reserva',
            'Bed type'            => 'Tipo de cama',
        ],
        'fr' => [
            'Reservation number'  => ['Numéro de réservation', 'N° de réservation'],
            'Your reservation at' => 'Votre réservation au',
            'HotelNameRegex'      => '#Votre réservation au\s+(.+?)\s+a été effectuée avec succès#',
            'Rooms'               => ['Chambres', 'Rooms'],
            'Occupancy:'          => ['Occupation:', 'Occupation :'],
            'Room'                => ['Chambre', 'Chambres'],
            'Adult'               => ['Adulte', 'adultes'],
            //'Child' => '',
            //			'Baby' => '',
            'Check-In'    => ["Date d’arrivée", "Arrivée", "PRÉPAYER EN LIGNE"],
            'Check-Out'   => ["Date de départ", "TARIF NON REMBOURSABLE"],
            'Price'       => ['Total', 'Chambres'],
            'VAT'         => 'Taxe de séjour',
            'Final price' => ['Tarif final', 'Prix total'],
            //			'Guests' => 'Gäste',
            'Occupancy' => ['Occupation', 'Occupation '],
            //			'Rate includes:' => 'Im Preis enthalten:',
            //			'Rate Conditions' => 'Preiskonditionen',
            //			'Rate' => ['Preis','preis'],
            'Cancell' => ['Annulation'],
            //            "Manage Your Booking" => "",
            "PAY AT THE HOTEL" => "PAYER À L'HÔTEL",
            'MainGuest'        => 'Personne ayant effectué la réservation:',
            'Booking Details'  => 'Détails de la réservation',
            'Bed type'         => 'Type de lit',
        ],
        'nl' => [
            'Reservation number'  => 'Reserveringsnummer',
            'Your reservation at' => 'Uw reservering bij',
            'HotelNameRegex'      => '#Uw reservering bij\s+(.+?)\s+is gemaakt#',
            'Rooms'               => 'Kamers',
            'Occupancy:'          => ['Bezetting:', 'Beschikbaarheid:'],
            'Room'                => 'Kamer',
            'Adult'               => ['volwassenen', 'volwassene'],
            //			'Child' => '',
            //			'Baby' => '',
            'Check-In'    => ['Check-in:', 'Aankomstdatum'],
            'Check-Out'   => ['Check-out:', 'Uitcheckdatum'],
            'Price'       => ['Total', 'Kamer'],
            'VAT'         => 'BTW',
            'Final price' => 'Totaalprijs',
            'Guests'      => 'Gasten',
            'Occupancy'   => ['Bezetting', 'Beschikbaarheid'],
            //			'Rate includes:' => '',
            'Rate Conditions' => 'Tariefvoorwaarden',
            //			'Rate' => ['Preis','preis'],
            'Cancell'             => ['annule'],
            "Manage Your Booking" => "Reserveringen",
            //'MainGuest' => '',
        ],
        'it' => [
            'Reservation number'  => ['Numero di prenotazione', 'Numero della prenotazione'],
            'Your reservation at' => 'La tua prenotazione presso',
            'HotelNameRegex'      => '#La tua prenotazione presso\s+(.+?)\s+è stata#u',
            'Rooms'               => 'Camere',
            'Room'                => 'Camera',
            'Adult'               => ['Adulti', 'Adulto'],
            //			'Child' => '',
            //			'Baby' => '',
            'Check-In'     => ['Check-in:', 'Check-in', 'Data di arrivo:', 'Check-In:'],
            'Check-Out'    => ['Check-Out:', 'Check-out', 'Data di check-out:', 'Check-Out:'],
            'Price'        => 'Camera',
            'VAT'          => 'IVA',
            'Final price'  => 'Prezzo totale',
            'Guests'       => ['Gli ospiti', 'Numero della prenotazione:', 'Dati ospite'],
            'Occupancy'    => ['Occupazione', 'Capienza:'],
            'Occupancy:'   => ['Capienza:', 'Occupazione:'],
            //			'Rate includes:' => '',
            //			'Rate Conditions' => '',
            //			'Rate' => ['Preis','preis'],
            //			'Cancell' => [''],
            "Manage Your Booking" => "Gestisci Le Tue Prenotazioni ",
            'Room information'    => 'Informazioni sulla camera',
            'MainGuest'           => ['Ospite'],
            'Booking Details'     => ['Dettagli della prenotazione'],
            'Bed type'            => ['Tipologia di letto'],
        ],
    ];

    /** @var \HttpBrowser */
    private $http2; // for remote html-content

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");
//            return null;
        }

        $this->http2 = clone $this->http;

        $parser->getSubject();

        if (!$this->parseEmail($email, $parser->getSubject())) {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".nh-hotels.com/") or contains(@href,"www.nh-hotels.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(.,"@nh-hotels.com")]')->length > 0
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'NH Hotel') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrase) {
            if (stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'NH Hotel Group') !== false
            || strpos($from, 'NH_Hotel_Group') !== false
            || stripos($from, '@nh-hotels.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email, $subject)
    {
        $patterns = [
            'time'          => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
            'phone'         => '[+(\d][-.\s\d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        if ($url = $this->http->FindSingleNode("//a[{$this->eq($this->t('Manage Your Booking'))}]/@href")) {
            $this->http2->GetURL($url);
        }

        $h = $email->add()->hotel();

        $confirms = [];
        $rows = $this->http->XPath->query("//text()[{$this->starts($this->t('Reservation number'))}]");

        foreach ($rows as $row) {
            $conf = $this->http->FindSingleNode(".", $row, true, "#{$this->opt($this->t('Reservation number'))}[:\s]*([A-Z\d\-]{5,})\s*$#");

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][1]", $row, true, "#^\s*([A-Z\d\-]{5,})\s*$#");
            }
            $confirms[] = $conf;
        }
        $confirms = array_unique(array_filter($confirms));

        foreach ($confirms as $conf) {
            $h->general()
                ->confirmation($conf);
        }

        $accounts = $this->http->FindNodes("//text()[contains(normalize-space(), 'Membership')]", null, "/\:\s*(\d{5,})/");

        if (!empty($accounts)) {
            $h->setAccountNumbers($accounts, false);
        }

        // hotelName
        $hotelName = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Your reservation at'))}])[last()]", null,
            true, $this->t('HotelNameRegex'));

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//img[@alt='star rating']/ancestor::tr[1]/preceding-sibling::tr[normalize-space(.)!=''][1]");
        }

        if (!empty($url) && empty($hotelName)) { // from URL
            $hotelName = $this->http2->FindSingleNode("//a[{$this->eq($this->t('Hotel Information'))}]/ancestor::div[position()<3]/descendant::h1");
        }

        if (empty($hotelName)) {
            $nh = $this->http->FindSingleNode("//td[contains(text(), 'NH')]");

            if (!empty($nh)) {
                if (stripos($subject, $this->http->FindSingleNode("//td[contains(text(), 'NH')]")) !== false) {
                    $hotelName = $nh;
                }
            }
        }

        // address
        if (empty($address)) {
            $address = implode(' ',
                array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Your reservation at'))}]/preceding::table[1]//tr[normalize-space(.)!=''][2]//text()[not(./ancestor::a)]")));
        }

        if (empty($address)) {
            $address = implode(' ',
                array_filter($this->http->FindNodes("//img[@alt='star rating']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!=''][1]")));
        }

        if (empty($address)) {
            $address = implode(' ',
                array_filter($this->http->FindNodes("//img[@altx='star rating']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!=''][1]")));
        }

        if (empty($address)) {
            $address = implode(' ',
                array_filter($this->http->FindNodes("//img[@alt='star rating']/ancestor::tr[1]/following::tr[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!=''][1]")));
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Your reservation at')]/preceding::table[1][count(./descendant::tr[normalize-space(.)])>1]/descendant::tr[normalize-space(.) and ./descendant::a][1]/descendant::text()[normalize-space(.)!=''][1]");
        }

        // phone
        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation at'))}]/preceding::text()[normalize-space(.)!=''][not(contains(.,'@'))][1][starts-with(normalize-space(.),'+')]");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//img[@alt='star rating']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][2]/descendant::text()[normalize-space(.)!=''][1][starts-with(normalize-space(.),'+')]");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//img[@alt='star rating']/ancestor::tr[1]/following::tr[normalize-space(.)!=''][2]/descendant::text()[string-length()>5][1][starts-with(normalize-space(.),'+')]");
        }

        // address, phone
        if (!empty($url) && (empty($address) || empty($phone))) { // from URL
            $addressTexts = $this->http2->FindNodes("//a[normalize-space(.)='Hotel Information']/ancestor::div[1]/descendant::address/descendant::text()[normalize-space(.) and not(contains(.,'@'))]");
            $addressText = implode("\n", $addressTexts);

            if (preg_match("/^(?<address>.+?)[ ]*\n+[ ]*(?<phone>{$patterns['phone']})$/s", $addressText, $matches)) {
                $address = preg_replace('/\s+/', ' ', $matches['address']);
                $phone = $matches['phone'];
            }
        }

        if (empty($address)) {
            $addr = $this->http->FindSingleNode("//td[contains(text(), 'NH')]/ancestor::tr[1]/following-sibling::tr[3]/td[1]/text()");

            if (!empty($addr)) {
                $address = $addr;
            }
        }

        if (empty($phone)) {
            $ph = $this->http->FindSingleNode("//td[contains(text(), 'NH')]/ancestor::tr[1]/following-sibling::tr[5]/td[1]/a[1]");

            if (!empty($ph)) {
                $phone = $ph;
            }
        }

        if (empty($address) && empty($hotelName)) {
            $address = $this->http2->FindSingleNode("//a[{$this->eq($this->t('See more information about the hotel'))}]/preceding-sibling::text()[normalize-space()][1]");
            $hotelName = $this->http2->FindSingleNode("//a[{$this->eq($this->t('See more information about the hotel'))}]/ancestor::tr[1]/preceding-sibling::tr[last()]");
            $phone = $this->http2->FindSingleNode("//a[{$this->eq($this->t('See more information about the hotel'))}]/ancestor::tr[1]/following-sibling::tr//a[contains(@href,'tel:')]");
        }

        if (empty($address) && empty($hotelName)) {
            $address = $this->http->FindSingleNode("//img[contains(@src, 'ic-poi')]/ancestor::tr[1]");
            $phone = $this->http->FindSingleNode("//img[contains(@src, 'ic-phone')]/ancestor::td[1]");
            $hotelName = $this->http->FindSingleNode("//img[contains(@src, 'ic-poi')]/preceding::text()[normalize-space()][1]");
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone);

        $rooms = 0;
        $guests = 0;
        $kids = 0;
        $nodes = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Occupancy:'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]"));

        if (empty($nodes)) {
            $nodes = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Occupancy'))}]/ancestor::td[1]/following-sibling::td[1]"));
        }

        if (empty($nodes)) {
            $nodes = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Booking Details'))}]/following::text()[{$this->eq($this->t('Occupancy:'))}][1]/ancestor::td[1]/following::td[normalize-space(.)!=''][1]"));
        }

        foreach ($nodes as $node) {
            if (preg_match("#(\d+)\s*{$this->opt($this->t('Room'))}#ui", $node, $m)) {
                $rooms += $m[1];
            }

            if (preg_match("#(\d+)\s*{$this->opt($this->t('Adult'))}#ui", $node, $m)) {
                $guests += $m[1];
            }

            if (preg_match("#(\d+)\s*{$this->opt($this->t('Child'))}#ui", $node, $m)) {
                $kids += $m[1];
            }

            if (preg_match("#(\d+)\s*{$this->opt($this->t('Baby'))}#ui", $node, $m)) {
                $kids += $m[1];
            }
        }

        if ($rooms > 0) {
            $h->booked()
                ->rooms($rooms);
        }

        if ($guests > 0) {
            $h->booked()
                ->guests($guests);
        }

        if ($kids > 0) {
            $h->booked()
                ->kids($kids);
        }

        $separator = "\s+(?:at|a|à|a las|bei|um|om|alle|às|to|a partir das|até)\s+";
        // 13/12/2017 a las 15:00    |    09/11/2017 to 15:00 Uhr    |    22/02/2019 to 15:00 ore
        $patterns['dateTime'] = "/^(?<date>.{6,}?){$separator}(?<time>{$patterns['time']})\s*(?:Uhr|ore|horas|Heures|uren|hours)?$/iu";
        $patterns['dateTime2'] = "/^(?<date>.{6,}?){$separator}[: ]+$/i"; // 09/10/2018 at :
        $patterns['dateTime3'] = "/^(?<date>.{6,}?)(?<time>{$patterns['time']})$/iu";

        // checkInDate
        $dateCheckIn = $timeCheckIn = null;
        $dateCheckInText = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Check-In'))}]/following::text()[normalize-space(.)!=''][1])[1]");

        if (preg_match($patterns['dateTime'], $dateCheckInText, $m)
            || preg_match($patterns['dateTime2'], $dateCheckInText, $m)
            || preg_match($patterns['dateTime3'], $dateCheckInText, $m)
        ) {
            $dateCheckIn = strtotime($this->normalizeDate(trim($m['date'])));

            if (!empty($m['time'])) {
                $timeCheckIn = $m['time'];
            }
        }
        $timeCheckIn2 = $this->http2->FindSingleNode("(//li[{$this->starts($this->t('Check-In'))}])[1]", null, true, "/({$patterns['time']})$/");

        if ($timeCheckIn2) {
            $timeCheckIn = $timeCheckIn2;
        }

        if ($dateCheckIn && $timeCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        // checkOutDate
        $dateCheckOut = $timeCheckOut = null;
        $dateCheckOutText = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Check-Out'))}]/following::text()[normalize-space(.)!=''][1])[1]");

        if (empty($dateCheckOutText)) {
            $dateCheckOutText = $this->http->FindSingleNode("(//td[{$this->starts($this->t('Check-Out'))}]/following-sibling::td[normalize-space(.)!=''][1])[1]");
        }

        if (preg_match($patterns['dateTime'], $dateCheckOutText, $m)
            || preg_match($patterns['dateTime2'], $dateCheckOutText, $m)
            || preg_match($patterns['dateTime3'], $dateCheckOutText, $m)
        ) {
            $dateCheckOut = strtotime($this->normalizeDate($m['date']));

            if (!empty($m['time'])) {
                $timeCheckOut = $m['time'];
            }
        }
        $timeCheckOut2 = $this->http2->FindSingleNode("//li[{$this->starts($this->t('Check-Out'))}]", null, true, "/({$patterns['time']})$/");

        if ($timeCheckOut2) {
            $timeCheckOut = $timeCheckOut2;
        }

        if ($dateCheckOut && $timeCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $this->year = date('Y', $dateCheckIn);

        $h->booked()
            ->checkIn($dateCheckIn)
            ->checkOut($dateCheckOut);

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Price'))}])[last()]/following::text()[normalize-space(.)!=''][1]"));

        if ($tot['Total'] !== null) {
            $h->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('VAT'))}]/following::text()[normalize-space(.)!=''][1]"));

        if ($tot['Total'] !== null) {
            $h->price()
                ->tax($tot['Total'])
                ->currency($tot['Currency']);
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Final price'))}]/following::text()[normalize-space(.)!=''][1]"));

        if (empty($tot['Total'])) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Details'))}]/following::text()[{$this->eq($this->t('Final price'))}][1]/following::text()[normalize-space(.)!=''][1]"));
        }

        if ($tot['Total'] !== null) {
            $h->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $types = $this->http->FindNodes("//text()[{$this->eq($this->t('Rooms'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]/tr[" . $this->starts($this->t('PAY AT THE HOTEL')) . "]/preceding-sibling::tr[normalize-space()][1]");

        if (count($types) === 0) {
            $types = $this->http->FindNodes("//text()[{$this->starts($this->t('Room'))}]/following::text()[{$this->eq($this->t('Bed type'))}]/following::text()[normalize-space()][1]");
        }

        if ($types) {
            foreach ($types as $type) {
                $r = $h->addRoom();
                $r->setType($type);
            }
        } elseif ($type = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Rooms'))} or {$this->eq($this->t('Room information'))} and following::text()[1][not(contains(., ','))]])[1]/ancestor::tr[1]/following-sibling::tr[2]//text()[string-length(normalize-space(.)) > 6]")) {
            $r = $h->addRoom();
            $r->setType($type);
            $roomDescriptionRows = $this->http->FindNodes("//text()[{$this->eq($this->t('Rate includes:'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][position()<4 and not({$this->contains($this->t('Rate'))})]");

            if (!empty($roomDescriptionRows)) {
                $r->setDescription(implode(' ', $roomDescriptionRows));
            }
        }

        $guestNames = [];

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Bed type'))}]")->length > 0) {
            $guestRows = $this->http->XPath->query("//text()[{$this->starts($this->t('Guests'))}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space()]");
        } else {
            $guestRows = $this->http->XPath->query("//text()[{$this->starts($this->t('Guests'))}]/ancestor::tr[1]/following-sibling::tr");
        }

        foreach ($guestRows as $gRow) {
            $gRowText = $this->http->FindSingleNode('.', $gRow);

            if (empty($gRowText) && count($guestNames) > 0) {
                break;
            } elseif (preg_match("/^{$patterns['travellerName']}$/u", $gRowText)) {
                $guestNames[] = $gRowText;
            }
        }

        if (count($guestNames) === 0) {
            if ($h->getGuestCount() == 1) {
                $guestNames[] = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Reservation number'))}]/following::text()[normalize-space(.)!=''])[2][not({$this->starts($this->t('Check-in'))})]");
                $description = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Reservation number'))}]/following::text()[normalize-space(.)!=''])[3][not({$this->starts($this->t('Check-in'))})]");

                if (!empty($description)) {
                    $r->setDescription($description);
                }
            } else {
                $i = 0;

                do {
                    $i++;
                } while ($this->http->XPath->query("//text()[{$this->eq($this->t('Rooms'))}]/ancestor::tr[1]/following-sibling::tr[{$this->starts($this->t('Reservation number'))}][1]/following-sibling::tr[normalize-space(.)!=''][{$i}][{$this->starts($this->t('Check-in'))}]")->length == 0 && $i < 15);
                $rows = $this->http->XPath->query("//text()[{$this->eq($this->t('Rooms'))}]/ancestor::tr[1]/following-sibling::tr[{$this->starts($this->t('Reservation number'))}]");
                $guestNames = [];

                foreach ($rows as $row) {
                    $guestNode = implode("\n", $this->http->FindNodes("./following-sibling::tr[normalize-space(.)!=''][position()<{$i}]", $row));

                    if (preg_match("/^({$patterns['travellerName']})(?:\n.+)?(?:\n.+:|$)/u", $guestNode, $m)) {
                        // it-47909176.eml, it-57037871.eml
                        $guestNames = array_merge($guestNames, explode("\n", $m[1]));
                    }
                }
            }
        }

        if (count($guestNames) > 0) {
            $h->general()->travellers(array_unique($guestNames));
        } else {
            // it-48480570.eml
            $rtav = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('MainGuest'))} and following::text()[1][not(contains(., ','))]])[1]/following::text()[normalize-space(.)!=''][1]");

            if (!empty($rtav)) {
                $h->general()->traveller($rtav);
            }
        }

        // cancellation
        $cancellation = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Rate Conditions'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][position()<5 and {$this->contains($this->t('Cancell'))}]"));

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancell'))}]");
        }

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);
            $this->detectDeadLine($h, $cancellation);
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match('/Ihre Reservierung kann kostenfrei bis (\d{1,2}\-\w+\-\d{2,4})\s+(\d{1,2}:\d{2})(?::\d{2})?\s*storniert werden/i', $cancellationText, $m) //de
            || preg_match('/Tu reserva puede ser modificada sin coste hasta (\d{1,2}\-\w+\-\d{2,4}) (\d{1,2}:\d{2})\:/', $cancellationText, $m) //es
            || preg_match('/Dit tarief kan kosteloos worden gewijzigd of geannuleerd tot (\d{1,2}\-\w+\-\d{2,4}) (\d{1,2}:\d{2})\:/', $cancellationText, $m) //nl
            || preg_match('/Your reservation can be cancelled free of charge until (\d{1,2}\-\w+\-\d{2,4}) (\d{1,2}:\d{2})\:/', $cancellationText, $m) //en
            || preg_match('/A sua reserva pode ser cancelada gratuitamente até (?<date>\d{1,2}-[[:alpha:]]{3,}-\d{2,4}) (?<time>\d{1,2}:\d{2}):/u', $cancellationText, $m) // pt
        ) {
            $h->booked()->deadline(strtotime($this->normalizeDate($m[1]) . ' ' . $m[2]));
        } elseif (preg_match("#^Deze reservering kan kosteloos worden geannuleerd of gewijzigd tot (?<prior>\d+) dag\(en\) voor aankomst.#", $cancellationText, $m)
            || preg_match("#This reservation can be cancelled free of charge until (?<prior>\d+) day\/s before the arrival#u", $cancellationText, $m)
            || preg_match("#Esta taxa pode ser modificada ou cancelada gratuitamente até (?<prior>\d+) dias antes de sua chegada#u", $cancellationText, $m)
            || preg_match("#Cancellation and modification conditions This rate can be modified or cancelled free of charge until (?<prior>\d+) day/s before the arrival#u", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'] . " days", '00:00');
        } elseif (preg_match("#^Annulation gratuite jusqu\’au\s*(\d+\s*\w+)$#u", $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($this->normalizeDate($m[1] . ' ' . $this->year)));
        } elseif (preg_match("#Die Stornierung oder Änderung Ihrer Reservierung ist kostenpflichtig#", $cancellationText)
                || preg_match("#Your reservation cannot be cancelled or modified free of charge#", $cancellationText)
                || preg_match("#A sua reserva não pode ser cancelada ou modificada gratuitamente#", $cancellationText)) {
            $h->booked()->nonRefundable();
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            // 22/02/2019
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/u',
            // 15-Feb-2020
            '/^(\d{1,2})-([[:alpha:]]{3,})-(\d{2,4})$/u',
            //03 Mag 2024
            '/^(\d+\s*\w+\s*\d{4})$/u',
            //Ven 03 Mag 2024
            '/^\D+\s*(\d+\s*\w+\s*\d{4})\s*$/u',
        ];
        $out = [
            '$3-$2-$1',
            '$1 $2 $3',
            '$1',
            '$1',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);			// 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);	// 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
