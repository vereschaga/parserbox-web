<?php

namespace AwardWallet\Engine\ryanair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: fix different ryanair/it-5589573.eml with parser `BoardingPassPDF`

class It4027271 extends \TAccountChecker
{
    public $mailFiles = "ryanair/it-104435775.eml, ryanair/it-10024314.eml, ryanair/it-10031405.eml, ryanair/it-11350863.eml, ryanair/it-11463478.eml, ryanair/it-13688330.eml, ryanair/it-15415849.eml, ryanair/it-15841503.eml, ryanair/it-27093938.eml, ryanair/it-2849039.eml, ryanair/it-2886999.eml, ryanair/it-2898697.eml, ryanair/it-2934854.eml, ryanair/it-2956680.eml, ryanair/it-2956768.eml, ryanair/it-3016417.eml, ryanair/it-3034988.eml, ryanair/it-3130068.eml, ryanair/it-3130070.eml, ryanair/it-3130582.eml, ryanair/it-3293331.eml, ryanair/it-3318525.eml, ryanair/it-3331358.eml, ryanair/it-36223416.eml, ryanair/it-3688216.eml, ryanair/it-3741507.eml, ryanair/it-3878352.eml, ryanair/it-3920589.eml, ryanair/it-4002695.eml, ryanair/it-4002839.eml, ryanair/it-4009484.eml, ryanair/it-4009488.eml, ryanair/it-4009493.eml, ryanair/it-4021455.eml, ryanair/it-4026671.eml, ryanair/it-4027271.eml, ryanair/it-4027572.eml, ryanair/it-4030234.eml, ryanair/it-4030607.eml, ryanair/it-4031347.eml, ryanair/it-4031852.eml, ryanair/it-4031853.eml, ryanair/it-4034384.eml, ryanair/it-4037404.eml, ryanair/it-4039261.eml, ryanair/it-4039269.eml, ryanair/it-4039271.eml, ryanair/it-4039277.eml, ryanair/it-4043158.eml, ryanair/it-4046525.eml, ryanair/it-4049180.eml, ryanair/it-4049615.eml, ryanair/it-4050986.eml, ryanair/it-4084913.eml, ryanair/it-4100452.eml, ryanair/it-4125649.eml, ryanair/it-4131734.eml, ryanair/it-4148547.eml, ryanair/it-4148550.eml, ryanair/it-4219728.eml, ryanair/it-4233556.eml, ryanair/it-4314468.eml, ryanair/it-4804802.eml, ryanair/it-5211133.eml, ryanair/it-5214614.eml, ryanair/it-5373336.eml, ryanair/it-5563630.eml, ryanair/it-5730942.eml, ryanair/it-5771858.eml, ryanair/it-6549785.eml, ryanair/it-66604612.eml, ryanair/it-8324069.eml, ryanair/it-8364129.eml, ryanair/it-8408929.eml, ryanair/it-8422849.eml, ryanair/it-8476633.eml, ryanair/it-8514824.eml, ryanair/it-114147171.eml";

    private $reSubject = [
        'nl' => ['Ryanair Reisoverzicht'],
        'no' => ['Ryanair reiserute'],
        'pl' => ['Potwierdzenie rezerwacji Ryanair'],
        'pt' => ['Itinerário de Viagem Ryanair'],
        'it' => ['Itinerario di Viaggio Ryanair', 'È il momento di fare il check-in'],
        'es' => ['Itinerario de Viaje Ryanair'],
        'de' => ['Ryanair Buchungsbestätigung', 'Zeit zum Online-Check-in'],
        'sv' => ['Ryanair reseplan'],
        'da' => ['Ryanair Rejseplan', 'Dine flyafgangsoplysninger'],
        'fr' => ['Itinéraire de Voyage'],
        'ca' => ['Itinerari de Viatge Ryanair', 'Temps per facturar'],
        'el' => ['Δρομολόγιο ταξιδίου Ryanair', 'Ήρθε η ώρα για check in'],
        'lt' => ['Laikas užsiregistruoti skrydžiui', 'Užsakymo patvirtinimas:Ačiū, kadnaudojatės „Ryanair“ paslaugomis'],
        'hu' => ['Ryanair Utazási segéd', 'Itt van az utasfelvétel ideje'],
        'en' => ['Ryanair Travel Itinerary', 'Time to check in'],
        'zh' => ['Ryanair Travel Itinerary', 'Time to check in'],
        'ro' => ['Confirmarea rezervării: Îți mulțumim că ai rezervat la Ryanair'],
        'cs' => ['Potvrzení rezervace: Děkujeme vám za rezervaci u společnosti Ryanair'],
        'bg' => ['Потвърждение на резервацията: Благодарим Ви, че направихте резервация с Ryanair'],
    ];

    private $lang = '';
    private $date;

    private $reBody = 'Ryanair';
    private $reBody2 = [
        "nl"        => "VERTREK:",
        "no"        => "AVGANG:",
        "pl"        => "WYLOT:",
        "pl2"       => "Czas wylotu",
        "pt"        => "PARTIDA:",
        "pt2"       => "Hora de partida",
        "it"        => "PARTENZA:",
        "it2"       => "Ora di partenza",
        "es"        => "SALIDA:",
        "es2"       => "Hora de salida",
        "de"        => "ABFLUG:",
        "sv"        => "AVGÅR:",
        "da"        => "AFGANG:",
        "da2"       => "Afrejsetidspunkt",
        "fr"        => "DÉPART",
        "ca"        => "SORTIDA:",
        "hu"        => "INDULÁS:",
        "hu2"       => "Indulás -",
        "hu3"       => "Az indulás időpontja",
        "el"        => "ΑΝΑΧΩΡΗΣΗ:",
        "lt"        => "Išvykimas",
        "lt2"       => "Jūsų skrydžio informacija",
        "en"        => "DEPART:",
        'de2'       => 'Fluggäste:',
        'el2'       => 'Οι πληροφορίες της πτήσης σας',
        //        'ro'=>'',
        'sv2'   => 'Din flyginformation',
        'zh'    => '航班信息',
        'zh2'   => '出发：',
        'cs'    => 'Vaše letové informace',
        'cs2'   => 'Informace o vašem letu',
        'bg'    => 'Заминаване',
        'he'    => 'נוסעים):',
    ];

    private static $dictionary = [
        "nl" => [
            "Seat Details:" => "Details van zitplaats:",
            "Fl.num"        => "Vluchtnummer",
            "Reservation:"  => "Reserveringsnummer:",
            //			"Status:" => "",
            "PASSENGER(S):"           => "PASSAGIER(S):",
            "Total paid"              => "Totaal betaald bedrag",
            "DEPART:"                 => "VERTREK:",
            "ARRIVAL:"                => "AANKOMST:",
            "DATE:"                   => "DATUM:",
            "TIME:"                   => "TIJD:",
            'Your flight information' => 'Jouw vluchtinformatie',
            'title'                   => ["Dhr", "Mej", 'Mw', 'Kind'],
            'Flight out:'             => ['Uitgaande vlucht:', 'Inkomend:'],
        ],
        "no" => [
            //			"Seat Details:" => "NOTTRANSLATED",
            "Fl.num"       => "Avgang nr.:",
            "Reservation:" => "Reservasjon:",
            //			"Status:" => "",
            "PASSENGER(S):" => "PASSASJER(ER):",
            "Total paid"    => "Sum betalt",
            "DEPART:"       => "AVGANG:",
            "ARRIVAL:"      => "ANKOMST:",
            "DATE:"         => "DATO:",
            "TIME:"         => "TIDSPUNKT:",
            //			'Your flight information' => '',
            'title' => ["Herr", "Fru", "Frøken", "Frk"],
            // 'Flight out:' => [''],
        ],
        "pl" => [
            "Seat Details:"           => "Miejsca:",
            "Fl.num"                  => ["Nr lotu", "Nr lotu:"],
            "Reservation:"            => "Nr rezerwacji:",
            "Status:"                 => ["Status rezerwacji:", "Status:"],
            "PASSENGER(S):"           => ["PASAŻER(-OWIE):", "Pasażer(-owie):", "Pasażer(owie):"],
            "Total paid"              => "Łącznie zapłacono",
            "DEPART:"                 => "WYLOT:",
            "ARRIVAL:"                => "PRZYLOT:",
            "DATE:"                   => "DATA:",
            "TIME:"                   => "GODZINA:",
            'Your flight information' => 'Informacje dotyczące Twojego lotu',
            'title'                   => ["Pan", "Pani", "Dziecko", "Panna"],
            'Flight out:'             => ['Trasa wylotu:', 'Powrót:', 'Lot powrotny:'],
        ],
        "pt" => [
            "Seat Details:" => "Lugar a bordo:",
            "Fl.num"        => "N.º do voo",
            "Reservation:"  => "Reserva:",
            //			"Status:" => "",
            "PASSENGER(S):"           => ["PASSAGEIRO(S):"],
            "Total paid"              => "Total pago",
            "DEPART:"                 => "PARTIDA:",
            "ARRIVAL:"                => "CHEGADA:",
            "DATE:"                   => "DATA:",
            "TIME:"                   => "HORA:",
            'Your flight information' => 'As informações do teu voo',
            'title'                   => ["Sig", "Sr", "Sra", "Mna"],
            'Flight out:'             => ['Voo de ida:', 'Voo de regresso:'],
        ],
        "it" => [
            "Seat Details:" => "Dettagli posto:",
            "Fl.num"        => "N. volo",
            "Reservation:"  => "Prenotazione:",
            //			"Status:" => "",
            "PASSENGER(S):"           => "PASSEGGERO/I:",
            "Total paid"              => "Totale pagato",
            "DEPART:"                 => ["PARTENZA:", 'Partenza'],
            "ARRIVAL:"                => ["ARRIVO:", 'Arrivo'],
            "DATE:"                   => "DATA:",
            "TIME:"                   => "ORARIO:",
            'Your flight information' => 'Le informazioni del tuo volo',
            'title'                   => ["Sig.ra", "Sig", "Sig.na"],
            'Flight out:'             => ['Volo di andata:', 'Volo di ritorno:'],
        ],
        "es" => [
            "Seat Details:" => "Datos del asiento:",
            "Fl.num"        => "Número de vuelo",
            "Reservation:"  => "Número de reserva:",
            //			"Status:" => "",
            "PASSENGER(S):"           => ["PASAJERO/S:", "Pasajero/s:", "Pasajero(s):"],
            "Total paid"              => "Total pagado",
            "DEPART:"                 => "SALIDA:",
            "ARRIVAL:"                => "LLEGADA:",
            "DATE:"                   => "FECHA:",
            "TIME:"                   => "HORA:",
            'Your flight information' => 'La información de tu vuelo',
            'title'                   => ["Sr", "Sra", "Srta", "Niño"],
            'Flight out:'             => ['Vuelo de ida:', 'Vuelo de vuelta:'],
        ],
        "de" => [
            "Seat Details:" => "Sitzplatzdetails:",
            "Fl.num"        => "Flug-Nr.",
            "Reservation:"  => "Reservierung:",
            //			"Status:" => "",
            "PASSENGER(S):"           => ["FLUGGAST/FLUGGÄSTE:", 'FLUGGÄSTE', 'Fluggäste', 'Fluggäste:', "Leisure Plus Fluggäste:",
                'Fluggast/-gäste:', ],
            "Total paid"              => ["Insgesamt bezahlt", 'INSGESAMT BEZAHLT'],
            "DEPART:"                 => "ABFLUG:",
            "ARRIVAL:"                => "ANKUNFT:",
            "DATE:"                   => "DATUM:",
            "TIME:"                   => ["ZEIT:", "UHRZEIT:"],
            'Your flight information' => 'Ihre Fluginformationen',
            'title'                   => ["Herr", "Frau", "Fräulein", "Kind"],
            'Flight out:'             => ['Hinflug:', 'Rückflug:'],
        ],
        "sv" => [
            //			"Seat Details:" => "NOTTRANSLATED",
            "Fl.num"       => "Flygnr",
            "Reservation:" => "Bokning:",
            //			"Status:" => "",
            "PASSENGER(S):"           => "PASSAGERARE:",
            "Total paid"              => "Totalt betalat",
            "DEPART:"                 => "AVGÅR:",
            "ARRIVAL:"                => ["ANKOMMER:", "ANLÄNDER:"],
            "DATE:"                   => "DATUM:",
            "TIME:"                   => "TID:",
            'Your flight information' => 'Din flyginformation',
            'title'                   => ["Herr", "Fru", "Fröken", 'Barn'],
            'Flight out:'             => ['Avresa:', 'Returresa:'],
        ],
        "da" => [
            //			"Seat Details:" => "NOTTRANSLATED",
            "Fl.num"       => "Flynummer",
            "Reservation:" => "Reservation:",
            //			"Status:" => "",
            "PASSENGER(S):"           => ["PASSAGER(ER):", "Passager(er):"],
            "Total paid"              => ["BETALT I ALT", "Betalt i alt"],
            "DEPART:"                 => "AFGANG:",
            "ARRIVAL:"                => "ANKOMST:",
            "DATE:"                   => "DATO:",
            "TIME:"                   => ["TIDSPUNKT:", "TID:"],
            'Your flight information' => ['Dine oplysninger om flyafgangen', 'Dine flyafgangsoplysninger'],
            'title'                   => ["Hr", "Frk", "Fru", "Fr"],
            'Flight out:'             => ['Udrejse:', 'Returrejse:'],
            'To '                     => ["Til "],
            'Departure time - '       => 'Afrejsetidspunkt - ',
            'Arrival time - '         => 'Ankomsttidspunkt - ',
        ],
        "fr" => [
            "Seat Details:"           => "Détails du siège :",
            "Fl.num"                  => "N° de vol :",
            "Reservation:"            => "Réservation",
            "Status:"                 => "Statut :",
            "PASSENGER(S):"           => ["PASSAGER(S)", 'Plus Passager(s)', 'Passager(s)'],
            "Total paid"              => "Total réglé",
            "DEPART:"                 => "DÉPART :", //["DÉPART", "Départ"],
            "ARRIVAL:"                => "ARRIVÉE", //["ARRIVÉE","Arrivée"],
            "DATE:"                   => "DATE",
            "TIME:"                   => "HEURE",
            'Your flight information' => 'Vos informations de vol',
            'title'                   => ["Mme", "M", "Mlle", "Enfant", "Melle"],
            'Flight out:'             => ['Vol aller:', 'Arrivée:'],
        ],
        "ca" => [
            "Seat Details:" => "Detalls del seient:",
            "Fl.num"        => "Número de vol:",
            "Reservation:"  => "Reserva:",
            //			"Status:" => "",
            "PASSENGER(S):"           => "PASSATGER/PASSATGERS:",
            "Total paid"              => "Total pagat",
            "DEPART:"                 => "SORTIDA:",
            "ARRIVAL:"                => "ARRIBADA",
            "DATE:"                   => "DATA",
            "TIME:"                   => "HORA",
            'Your flight information' => 'La informació del teu vol',
            'title'                   => ["Sr", "Sra", "Srta", "Nen"],
            // 'Flight out:' => [''],
        ],
        "hu" => [
            "Seat Details:"           => "Ülőhellyel kapcsolatos részletek:",
            "Fl.num"                  => "Járatszám:",
            "Reservation:"            => ["Foglalás:", "Foglalási szám:"],
            "Status:"                 => "Státusz:",
            "PASSENGER(S):"           => "UTAS(OK):",
            "Total paid"              => "Teljes kifizetett összeg",
            "DEPART:"                 => "INDULÁS:",
            "ARRIVAL:"                => "ÉRKEZÉS:",
            "DATE:"                   => "DÁTUM:",
            "TIME:"                   => "IDŐ",
            'Your flight information' => 'Az Ön járatának információja',
            'title'                   => ["Úr", "Hölgy", "kisasszony", 'Ifj'],
            'Flight out:'             => ['Odaút:'],
        ],
        "el" => [
            //			"Seat Details:" => "NOTTRANSLATED",
            "Fl.num"       => "Αρ. πτήσης:",
            "Reservation:" => "Αριθμός κράτησης:",
            //			"Status:" => "",
            "PASSENGER(S):" => "ΕΠΙΒΑΤΗΣ(ΕΣ)",
            "Total paid"    => "Σύνολο πληρωμής",
            "DEPART:"       => "ΑΝΑΧΩΡΗΣΗ:",
            "ARRIVAL:"      => "ΆΦΙΞΗ:",
            "DATE:"         => "ΗΜΕΡΟΜΗΝΙΑ:",
            "TIME:"         => "ΏΡΑ:",
            //			'Your flight information' => '',
            'title' => ["Κος", 'Κα', 'Δεσποινίς', 'Διδα'],
            // 'Flight out:' => [''],
        ],
        "lt" => [
            //			"Seat Details:" => "NOTTRANSLATED",
            //			"Fl.num" => "",
            "Reservation:" => "Rezervacija:",
            //			"Status:" => "",
            "PASSENGER(S):"           => ["PASSENGER(S):", "Keleivis (-iai):"],
            "Total paid"              => "Iš viso sumokėta",
            "DEPART:"                 => ["Išvykimas:", "IŠVYKIMAS:"],
            "ARRIVAL:"                => ["Atvykimas:", "ATVYKIMAS:"],
            "DATE:"                   => ["Data:", "DATA:"],
            "TIME:"                   => ["Laikas:", "LAIKAS:"],
            'Your flight information' => 'Jūsų skrydžio informacija',
            'title'                   => ["Ponas", "Ponia", "Panelė", 'Mrs', 'Mr', 'Ms'],
            'Flight out:'             => ['Išvykstamasis skrydis:', 'Grįžtamasis skrydis:'],
        ],
        "en" => [
            "Seat Details:" => ["Seat Details:", "Seat details:"],
            "Fl.num"        => ["Fl.num", "Flight no:"],
            'Reservation:'  => ['Reservation:', 'Reservation :'],
            "Total paid"    => ["Total paid", "TOTAL PAID"],
            'PASSENGER(S):' => ['PASSENGER(S):', 'Passenger(s):', 'Business Plus Passenger(s):'],
            //			'Your flight information' => '',
            'title'       => ["Mrs", "miss", "mr", "ms", "mss", "Child", "Dr"],
            'Flight out:' => ['Flight back:', 'Flight out:'],
        ],
        "ro" => [
            //			"Seat Details:" => "",
            //			"Fl.num" => "",
            "Reservation:" => "Rezervare:",
            //			"Status:" => "",
            "PASSENGER(S):" => ["PASAGER(I):", "Pasager(I):", 'Pasager(i):'],
            "Total paid"    => "Total achitat",
            //			"DEPART:" => "",
            //			"ARRIVAL:" => "",
            //			"DATE:" => "",
            //			"TIME:" => "",
            'Your flight information' => ['Informațiile tale de zbor', 'Informaţii despre zborul dvs.'],
            'title'                   => ["Dl", "Dna", "Dra", 'MS', 'MRS', 'MR'],
            'Flight out:'             => ['Zbor tur:', 'Zbor retur:'],
        ],
        "zh" => [
            //			"Seat Details:" => "",
            //			"Fl.num" => "",
            "Reservation:" => ["预留：", "预订："],
            //			"Status:" => "",
            "PASSENGER(S):"           => ["乘客:"],
            "Total paid"              => "支付总额",
            "DEPART:"                 => "出发：",
            "ARRIVAL:"                => "到达:",
            "DATE:"                   => "日期:",
            "TIME:"                   => "时间:",
            'Your flight information' => '航班信息',
            //            'title' => ["", ""],
            'Flight out:' => ['出港航班：'],
        ],
        "cs" => [
            //			"Seat Details:" => "",
            //			"Fl.num" => "",
            "Reservation:" => "Rezervace:",
            //			"Status:" => "",
            "PASSENGER(S):" => ["Cestující:"],
            "Total paid"    => ["Celkově Zaplaceno", "Celkově zaplaceno"],
            //			"DEPART:" => "",
            //			"ARRIVAL:" => "",
            //			"DATE:" => "",
            //			"TIME:" => "",
            'Your flight information' => ['Vaše letové informace', 'Informace o vašem letu'],
            'title'                   => ["MS", "MR"],
            'Flight out:'             => ["Let tam:", "Let zpět:"],
        ],
        "bg" => [
            "Seat Details:" => "Пътник(ци):",
            "Fl.num"        => "Изходящ полет",
            "Reservation:"  => "Резервация:",
            //			"Status:" => "",
            "PASSENGER(S):" => "Пътник(ци):",
            "Total paid"    => "Общо Платено",
            "DEPART:"       => "Заминаване",
            "ARRIVAL:"      => "Пристигане",
            //"DATE:" => "DATUM:",
            //"TIME:" => "TIJD:",
            'Your flight information' => 'Информация за Вашия полет',
            'title'                   => ["MS", "MR"],
            // 'Flight out:' => [''],
        ],
        "he" => [
            // "Seat Details:" => "Пътник(ци):",
            "Fl.num"        => "טיסה החוצה:",
            "Reservation:"  => "הזמנה:",
            //			"Status:" => "",
            "PASSENGER(S):" => "נוסעים):",
            "Total paid"    => "סך הכל שולם באמצעות",
            // "DEPART:"       => "Заминаване",
            // "ARRIVAL:"      => "Пристигане",
            //"DATE:" => "DATUM:",
            //"TIME:" => "TIJD:",
            'Your flight information' => 'פרטי הטיסה שלך',
            'title'                   => ["מר"],
            // 'Flight out:' => [''],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $this->http->FilterHTML = false;

        if ($this->http->XPath->query("//img | //a")->length === 0) {
            $this->http->SetEmailBody($parser->getBody()); // html detected as plaintext
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (
                mb_stripos($this->http->Response["body"], $re, 0, 'UTF-8') !== false
                || $this->http->XPath->query("//node()[{$this->contains([$re, mb_strtoupper($re), ucwords(mb_strtolower($re))])}]")->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        if (empty($this->lang)) {
            foreach (self::$dictionary as $lang => $dict) {
                if (isset($dict['Your flight information']) && preg_match("/{$this->opt($dict['Your flight information'])}/", $this->http->Response["body"])) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        if (empty($this->lang)) {
            $this->lang = 'en';
        }

        if ($this->http->XPath->query("//td")->length > 0) {
            $this->flightHtml($email);
            $type = 'Html';
        } else {
            if (stripos($this->http->Response['body'], '<body')) {
                $this->http->SetEmailBody(preg_replace("#^(>*)#m", '', $this->http->Response["body"]));
                $this->flightHtml($email);
                $type = 'Html';
            } else {
                $this->flightPlain($email);
                $type = 'Plain';
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function flightPlain(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $text = $this->http->Response['body'];

        if (preg_match('/<[Bb][Rr]\b[ ]*\/?>/', $text)) {
            $text = $this->htmlToText($text);
        }
        $f = $email->add()->flight();

        // RecordLocator
        $posCode = '';
        $seats = [];

        if (is_array($this->t("Reservation:"))) {
            foreach ($this->t("Reservation:") as $value) {
                $posCode = mb_strpos($text, $value);

                if (!empty($posCode)) {
                    break;
                }
            }
        } else {
            $posCode = mb_strpos($text, $this->t("Reservation:"));
        }

        $info = preg_replace("#^(\s*>\s*)#m", "", substr($text, $posCode, 200));
        $info = str_replace("*", "", $info);

        if (preg_match("#" . $this->opt($this->t("Reservation:")) . "\s*([A-Z\d]{5,6})#", $info, $m)) {
            $f->general()->confirmation($m[1]);
        }

        if (preg_match("#" . $this->opt($this->t("Status:")) . "\s*(.*)#iu", $info, $m)) {
            $f->general()->status($m[1]);
        }

        $lowerPass = ucfirst(strtolower(is_array($this->t("PASSENGER(S):")) ? $this->t("PASSENGER(S):")[0] : $this->t("PASSENGER(S):")));
        $posPass = mb_strpos($text, $lowerPass);

        if ($posPass == false) {
            $lowerPass = is_array($this->t("PASSENGER(S):")) ? $this->t("PASSENGER(S):")[0] : $this->t("PASSENGER(S):");
            $posPass = mb_strpos($text, $lowerPass);
        }

        if ($posPass !== false) {
            $posTo = false;

            if (is_array($this->t("Total paid"))) {
                foreach ($this->t("Total paid") as $subj) {
                    if (($posTo = stripos($text, $subj, $posPass)) !== false) {
                        break;
                    }
                }
            } else {
                $posTo = stripos($text, $this->t("Total paid"), $posPass);
            }

            if ($posTo) {
                $info = substr($text, $posPass, $posTo - $posPass);
            } else {
                $info = substr($text, $posPass);
            }
            $info = preg_replace("#^(\s*>\s*)#m", "", $info);
            $info = str_replace("*", "", $info);

            if (preg_match_all("#\n\s*(?:\W\s+)?[\w\.]+\s+([\w ]+)\s*\n#u", $info, $passengerMatches, PREG_OFFSET_CAPTURE)) {
                foreach ($passengerMatches[1] as $key => $value) {
                    if (strcmp(strtoupper($value[0]), $value[0]) !== 0) {
                        break;
                    }
                    $f->general()->traveller(trim($value[0]));

                    if (isset($passengerMatches[1][$key + 1][1])) {
                        $infoSeat = substr($info, $value[1], $passengerMatches[1][$key + 1][1] - $value[1]);

                        if (preg_match("#{$this->opt($this->t("Seat Details:"))}\s*(\d{1,3}[A-Z](\s*,\s*\d{1,3}[A-Z])*)#", $infoSeat, $mat)) {
                            $seatsP = explode(",", $mat[1]);

                            if (preg_match_all("#{$this->opt($this->t("Fl.num"))}:\s*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,5})#", $infoSeat, $match)) {
                                if (count($seatsP) == count($match[1])) {
                                    foreach ($match[1] as $key1 => $value1) {
                                        $seats[$match[1][$key1]][] = $seatsP[$key1];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // Flight out: FR2240 02C, Checked Bag
            if (empty($seats) && preg_match_all('/:\s*([A-Z\d]+)\s+(\w{2,3}),\s+/', $info, $seatMatches)) {
                foreach ($seatMatches[1] as $key => $value) {
                    $seats[$value][] = $seatMatches[2][$key];
                }
            }
        }

        $infoFlight = mb_substr($text, $posCode, $posPass - $posCode);
        $infoFlight = preg_replace("#^(\s*>\s*)#m", "", $infoFlight);
        $infoFlight = str_replace("*", "", $infoFlight);

        /**
        [Flight out icon]
        To Barcelona El Prat T2 FR2240
        Marrakesh - Barcelona El Prat T2
        Fri, 13 Sep 19
        Departure time - 09:40
        Arrival time - 13:05
        (RAK) - (BCN)
         */
        if (preg_match_all("/\[Flight out icon\].+?\([A-Z]{3}\)\s*-(?:[ ]*>)?\s*\([A-Z]{3}\)/s", $infoFlight, $flightMatches)) {
            foreach ($flightMatches[0] as $key => $flight) {
                $pattern = "/\](?<DepName>.+?)\s+(?<AirlineName>[A-Z]{2})\s*(?<FlightNumber>\d+)\s+(?<ArrName>.+?)\s+(?<DepDate>\w+, \d+ \w+ \d+)\s+"
                    . "Departure time - (?<DepTime>\d+:\d+)\s+Arrival time - (?<ArrTime>\d+:\d+)\s+"
                    . "\((?<DepCode>[A-Z]{3})\)\s*-(?:[ ]*>)?\s*\((?<ArrCode>[A-Z]{3})\)/s";

                if (preg_match($pattern, $flight, $mat)) {
                    $s = $f->addSegment();
                    $s->airline()
                        ->name($mat["AirlineName"])
                        ->number($mat["FlightNumber"]);

                    // Departure
                    if (preg_match("#^(.+)(?:\s+T([A-Z\d]))?$#U", trim($mat["DepName"]), $match)) {
                        $s->departure()->name($match[1]);

                        if (!empty($match[2])) {
                            $s->departure()->terminal($match[2]);
                        }
                    }
                    $s->departure()->date($this->normalizeDate(trim($mat["DepDate"]) . ', ' . $mat["DepTime"]));

                    // Arrival
                    if (preg_match("#^(.+)(?:\s+T([A-Z\d]))?$#U", $mat["ArrName"], $match)) {
                        $s->arrival()->name($match[1]);

                        if (!empty($match[2])) {
                            $s->arrival()->terminal($match[2]);
                        }
                    }
                    $s->arrival()->date($this->normalizeDate(trim($mat["ArrDate"] ?? $mat["DepDate"]) . ', ' . $mat["ArrTime"]));

                    $s->departure()->code($mat['DepCode']);
                    $s->arrival()->code($mat['ArrCode']);

                    if (isset($seats[$s->getAirlineName() . $s->getFlightNumber()])) {
                        $s->extra()->seats($seats[$s->getAirlineName() . $s->getFlightNumber()]);
                    }
                }
            }
        } elseif (preg_match_all("#((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,5}\s+{$this->opt($this->t("DEPART:"))}[\s\S]+{$this->opt($this->t("ARRIVAL:"))}[\s\S]+{$this->opt($this->t("TIME:"))}\s*\d+:\d+\s+)#uiU", $infoFlight, $flightMatches)) {
            foreach ($flightMatches[1] as $key => $flight) {
                $pattern = "#(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<FlightNumber>\d{1,5})\s+"
                        . $this->opt($this->t("DEPART:")) . "\s*(?<DepName>.+)\s+"
                        . $this->opt($this->t("DATE:")) . "\s*(?<DepDate>.+)\s+"
                        . $this->opt($this->t("TIME:")) . "\s*(?<DepTime>\d+:\d+)\s+"
                        . $this->opt($this->t("ARRIVAL:")) . "\s*(?<ArrName>.+)\s+"
                        . $this->opt($this->t("DATE:")) . "\s*(?<ArrDate>.+)\s+"
                        . $this->opt($this->t("TIME:")) . "\s*(?<ArrTime>\d+:\d+)\s+#iu";

                if (preg_match($pattern, $flight, $mat)) {
                    $s = $f->addSegment();
                    $s->airline()
                        ->name($mat["AirlineName"])
                        ->number($mat["FlightNumber"]);

                    // Departure
                    if (preg_match("#^(.+)(?:\s+T([A-Z\d]))?(?:\s+\(([A-Z]{3})\))?\s*$#U", $mat["DepName"], $match)) {
                        $s->departure()->name($match[1]);

                        if (!empty($match[2])) {
                            $s->departure()->terminal($match[2]);
                        }

                        if (!empty($match[3])) {
                            $s->departure()->code($match[3]);
                        }
                    }
                    $s->departure()->date($this->normalizeDate(trim($mat["DepDate"]) . ', ' . $mat["DepTime"]));

                    // Arrival
                    if (preg_match("#^(.+)(?:\s+T([A-Z\d]))?(?:\s+\(([A-Z]{3})\))?\s*$#U", $mat["ArrName"], $match)) {
                        $s->arrival()->name($match[1]);

                        if (!empty($match[2])) {
                            $s->arrival()->terminal($match[2]);
                        }

                        if (!empty($match[3])) {
                            $s->arrival()->code($match[3]);
                        }
                    }
                    $s->arrival()->date($this->normalizeDate(trim($mat["ArrDate"]) . ', ' . $mat["ArrTime"]));

                    if (isset($seats[$mat["AirlineName"] . $mat['FlightNumber']])) {
                        $s->extra()->seats($seats[$mat["AirlineName"] . $mat['FlightNumber']]);
                    }
                }
            }
        }
        $pos = false;

        if (is_array($this->t("Total paid"))) {
            foreach ($this->t("Total paid") as $subj) {
                if (($pos = strpos($text, $subj)) !== false) {
                    break;
                }
            }
        } else {
            $pos = strpos($text, $this->t("Total paid"));
        }

        if ($pos !== false) {
            $info = substr($text, $pos, 200);
            $info = preg_replace("#^(\s*>\s*)#m", "", $info);
            $info = str_replace("*", "", $info);

            if (preg_match("#{$this->opt($this->t("Total paid"))}\s+(?:.*\n)(\d[\d\.\,\s]*\d*)\s*([A-Z]{3})#u", $info, $m)) {
                $f->price()
                    ->total($this->amount($m[1]))
                    ->currency($m[2]);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Ryanair Customer Services') !== false
            || stripos($from, '@ryanair.com') !== false
            || stripos($from, '@care.ryanair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body) || (strlen($body) < 500)) {
            $body = $parser->getPlainBody();
        }

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (
                mb_stripos($body, $re, 0, 'UTF-8') !== false
                || $this->http->XPath->query("//*[{$this->contains([$re, mb_strtoupper($re), ucwords(mb_strtolower($re))])}]")->length > 0
            ) {
                return true;
            }
        }

        foreach (self::$dictionary as $dict) {
            if ((isset($dict['Your flight information']) && preg_match("/{$this->opt($dict['Your flight information'])}/u", $body))
                     || false !== stripos($body, 'Your flight information')) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 3;
    }

    /**
     * @return Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function flightHtml(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);

        if ($this->isJunk()) {
            $this->logger->debug('Email is junk!');
            $email->setIsJunk(true);

            return;
        }

        $xpathTime = 'contains(translate(.,"0123456789： ","∆∆∆∆∆∆∆∆∆∆:"),"∆:∆∆")';

        // Seats
        $seats = [];
        $xpath = "//text()[{$this->eq($this->t('Seat Details:'))}]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seat = $this->http->FindSingleNode("./following::text()[string-length(normalize-space(.))>1][1]", $root);

            if ($flights = $this->http->FindNodes("./ancestor::tr[{$this->contains($this->t("Fl.num"))}][1]/td[2]//text()[{$this->eq($this->t("Fl.num"))}]/following::text()[normalize-space(.)][1]", $root, "/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s*$/")) {
                $sts = array_map('trim', explode(",", $seat));

                foreach ($flights as $k=> $flight) {
                    if (isset($sts[$k]) && preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $sts[$k])) {
                        $seats[$flight][] = $sts[$k];
                    }
                }
            } else {
                $flight = $this->http->FindSingleNode("./ancestor::tr[1]/td[2]/descendant::text()[normalize-space(.)][1]", $root, null, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/");

                if ($seat && $flight && preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $seat)) {
                    $seats[$flight][] = $seat;
                }
            }
        }

        $f = $email->add()->flight();

        // RecordLocator
        $rl = $this->nextText($this->t("Reservation:"), null, "#^\s*([A-Z\d]{5,7})\s*$#");

        if (empty($rl)) {
            $rl = $this->nextText("Reservation:", null, "#^\s*([A-Z\d]{5,7})\s*$#");
        }

        if (empty($rl)) {
            $rl = $this->nextText($this->t("Reservation:"), null, "#^\s*([A-Z\d]{5,7})\s*$#", 2);
        }

        if (empty($rl)) {
            $rl = $this->http->FindSingleNode("//div[{$this->contains($this->t("Reservation:"))} and not(.//div)]/following-sibling::div[1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");
        }

        if (!empty($rl)) {
            $f->general()->confirmation($rl);
        }
        $status = $this->http->FindSingleNode("//div[{$this->contains($this->t("Status:"))} and not(.//div)]", null, true, '/:\s*[<>\s]*(\w+)/iu');

        if (!empty($status)) {
            $f->general()->status($status);
        }

        // Passengers
        if (is_array($this->t("PASSENGER(S):"))) {
            $passTitle = [];

            foreach ($this->t("PASSENGER(S):") as $key => $value) {
                $passTitle[] = $value;
                $passTitle[] = $this->mb_ucfirst(mb_strtolower($value, 'UTF-8'));
            }
        } else {
            $passTitle = [$this->t("PASSENGER(S):"), $this->mb_ucfirst(mb_strtolower($this->t("PASSENGER(S):"), 'UTF-8'))];
        }

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//*[{$this->starts($passTitle)}]/ancestor::tr[1]/following-sibling::tr[normalize-space(./td[1]) and ./td[2]]/descendant::text()[string-length(normalize-space(.))>1][1][./ancestor::tr[1]/descendant::text()[normalize-space(.)][2]]", null, '/^([^}{\d]{2,})$/');
            $passengers = array_values(array_filter($passengers));
        }

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//*[{$this->starts($passTitle)}]/ancestor::tr[1]/following-sibling::tr[normalize-space(./td[1]) and ./td[2]]/descendant::text()[string-length(normalize-space(.))>1][2]", null, '/^([^}{:\d]{2,})$/');
            $passengers = array_values(array_filter($passengers));
        }

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//*[" . $this->starts($passTitle, 'text()') . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(./td[1]) and ./td[2]]/descendant::text()[string-length(normalize-space(.))>1][1]", null, '/^([^}{:\d]{2,})$/');
            $passengers = array_values(array_filter($passengers));
        }

        if (empty($passengers)) {
            $passengers = array_filter($this->http->FindNodes("//*[{$this->starts($passTitle)}]/ancestor::tr[1]/following-sibling::tr/descendant::tr/td[@colspan=3]", null, '/([^}{:\d]{2,})$/'));
        }

        if (empty($passengers)) {
            $passengers = array_filter($this->http->FindNodes("//*[{$this->starts($passTitle)}]/ancestor::tr[1]/following-sibling::tr[count(descendant::tr)=2 or count(descendant::tr)=3]/descendant::tr[1]", null, '/^([^}{:\d]{2,})$/'));
        }

        $passengers = array_map(function ($v) {return preg_replace("#^\s*" . $this->opt($this->t('title')) . "[.]?\s+(.+)#i", '$1', $v); }, $passengers);

        //$flightNums = $this->http->XPath->query($xpath = "//*[{$this->starts($passTitle)}]/ancestor::tr[1]/following-sibling::tr[count(descendant::tr)=2 or count(descendant::tr)=3]/descendant::tr[count(descendant::td)>=3]/td[position()>1]");
        $flightNums = $this->http->XPath->query($xpath = "//text()[{$this->starts($this->t('Flight out:'))}]/ancestor::tr[1]/td");

        foreach ($flightNums as $flightNum) {
            if (
                preg_match('/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)/', $flightNum->nodeValue, $m)
                && ($s = $this->http->FindSingleNode('following-sibling::td[1]', $flightNum, true, '/\b(\d{1,4}[A-Z])\b/'))
            ) {
                $seats[$m['flightNumber']][] = $s;
            }
        }

        if (!empty($passengers[0])) {
            $f->general()->travellers(array_unique(array_map(function ($pax) { return preg_replace(['/=E2=80=A2/', '/(?:Reserve|No travel).+/i'], ['', ''], $pax); }, $passengers)), true);
        } else {
            $passenger = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t('Your flight information')) . "])[1]", null, true, "#" . $this->opt($this->t('Your flight information')) . ",\s*(.+)#");

            if (!empty($passenger)) {
                $f->general()->traveller($passenger, false);
            }
        }

        // TotalCharge
        // Currency
        $total = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Total paid'))}]/ancestor::td[1]/following-sibling::td[1])[1]");

        if (!$total) {
            $total = $this->nextText($this->t("Total paid"));
        }

        if (preg_match("#(\d[\d.,\s]*\d*)\s*([A-Z]{3})#", $total, $m)) {
            $f->price()
                ->total($this->amount($m[1]))
                ->currency($m[2]);
        }

        // type 1: with title Depart (it-5730942.eml)

        $xpath = "//text()[" . $this->starts($this->t("DEPART:")) . "]/ancestor::td[" . $this->contains($this->t("ARRIVAL:")) . "][1]";
        $segments = $this->http->XPath->query($xpath);
        $this->logger->debug("type 1 -> {$xpath}");
        $this->logger->debug("type 1: with title Depart -> {$segments->length}");

        if ($segments->length === 0) {
            if (is_array($this->t("DEPART:"))) {
                $depart = [];

                foreach ($this->t("DEPART:") as $key => $value) {
                    $depart[] = $this->mb_ucfirst(mb_strtolower($value, 'UTF-8'));
                }
            } else {
                $depart = $this->mb_ucfirst(mb_strtolower($this->t("DEPART:"), 'UTF-8'));
            }

            if (is_array($this->t("ARRIVAL:"))) {
                $arrival = [];

                foreach ($this->t("ARRIVAL:") as $key => $value) {
                    $arrival[] = $this->mb_ucfirst(mb_strtolower($value, 'UTF-8'));
                }
            } else {
                $arrival = $this->mb_ucfirst(mb_strtolower($this->t("ARRIVAL:"), 'UTF-8'));
            }
            $xpath = "//text()[" . $this->starts($depart) . "]/ancestor::td[" . $this->contains($arrival) . "][1]";
            $segments = $this->http->XPath->query($xpath);
        }

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            if (is_array($this->t("DEPART:"))) {
                $depart = [];

                foreach ($this->t("DEPART:") as $key => $value) {
                    $depart[] = $value;
                    $depart[] = $this->mb_ucfirst(mb_strtolower($value, 'UTF-8'));
                }
            } else {
                $depart = [$this->t("DEPART:"), $this->mb_ucfirst(mb_strtolower($this->t("DEPART:"), 'UTF-8'))];
            }
            // Flight
            if ($this->http->FindSingleNode(".", $root, true, "#^\s*" . $this->opt($depart) . "#ui")) {
                $flight = $this->http->FindSingleNode("./preceding::table[normalize-space(.)!=''][1]", $root);
            } else {
                $flight = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
            }
            $re = '/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\b/';

            if (preg_match($re, $flight, $matches)) {
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
            } elseif (($flight = $this->http->FindSingleNode('ancestor::tr[2]/preceding-sibling::tr[1]', $root)) && preg_match($re, $flight, $matches)) {
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
            }

            $depCode = $this->re("#\(([A-Z]{3})\)#", $this->nextCol($depart, $root));

            if (empty($depCode)) {
                $depCode = $this->http->FindSingleNode("./descendant::text()[{$this->starts($depart)}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][last()]",
                    $root, false, "#^\s*\(([A-Z]{3})\)#");
            }

            if (!empty($depCode)) {
                $s->departure()->code($depCode);
            }

            if (is_array($this->t("DATE:"))) {
                $dateRule = [];

                foreach ($this->t("DATE:") as $key => $value) {
                    $dateRule = array_unique(array_merge($dateRule, [$value, $this->mb_ucfirst(mb_strtolower($value, 'UTF-8'))]));
                }
            } else {
                $dateRule = [$this->t("DATE:"), $this->mb_ucfirst(mb_strtolower($this->t("DATE:"), 'UTF-8'))];
            }

            if (is_array($this->t("TIME:"))) {
                $timeRule = [];

                foreach ($this->t("TIME:") as $key => $value) {
                    $timeRule = array_unique(array_merge($timeRule, [$value, $this->mb_ucfirst(mb_strtolower($value, 'UTF-8')), $this->mb_ucfirst(mb_strtolower($value, 'UTF-8'))]));
                }
            } else {
                $timeRule = [$this->t("TIME:"), $this->mb_ucfirst(mb_strtolower($this->t("TIME:"), 'UTF-8')), $this->mb_ucfirst(mb_strtolower($this->t("TIME2:"), 'UTF-8'))];
            }

            $dateDep = $this->nextCol($dateRule, $root);
            $timeDep = $this->nextCol($timeRule, $root);

            if ($dateDep && $timeDep) {
                $s->departure()->date($this->normalizeDate($dateDep . ', ' . $timeDep));
            }

            if (is_array($this->t("ARRIVAL:"))) {
                $arrival = [];

                foreach ($this->t("ARRIVAL:") as $key => $value) {
                    $arrival[] = $value;
                    $arrival[] = $this->mb_ucfirst(mb_strtolower($value, 'UTF-8'));
                }
            } else {
                $arrival = [$this->t("ARRIVAL:"), $this->mb_ucfirst(mb_strtolower($this->t("ARRIVAL:"), 'UTF-8'))];
            }

            $arrCode = $this->re("#\(([A-Z]{3})\)#", $this->nextCol($arrival, $root));

            if (empty($arrCode)) {
                $arrCode = $this->http->FindSingleNode("./descendant::text()[{$this->starts($depart)}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][last()]",
                    $root, false, "#\s*\(([A-Z]{3})\)\s*$#");
            }

            if (!empty($arrCode)) {
                $s->arrival()->code($arrCode);
            }

            // ArrDate
            $dateArr = $this->nextCol($dateRule, $root, 2);
            $timeArr = $this->nextCol($timeRule, $root, 2);

            if ($dateArr && $timeArr) {
                $s->arrival()->date($this->normalizeDate($dateArr . ', ' . $timeArr));
            }

            if (empty($s->getDepDate()) && empty($s->getArrDate())) {
                $date = $this->http->FindSingleNode("./descendant::text()[{$this->starts($depart)}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space(.)!=''][1]",
                    $root);
                $timeDep = $this->nextText($depart, $root);
                $timeArr = $this->nextText($arrival, $root);

                if ($dDate = $this->normalizeDate($date . ' ' . $timeDep)) {
                    $s->departure()->date($dDate);
                }

                if ($aDate = $this->normalizeDate($date . ' ' . $timeArr)) {
                    $s->arrival()->date($aDate);
                }
            }

            if (empty($s->getDepDate()) && empty($s->getArrDate()) && empty($s->getDepCode()) && ($segInfo = $this->http->FindSingleNode("ancestor::table[1]", $root))) {
                if (preg_match('/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\b/', $segInfo, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                if (preg_match('/(\d{1,2}\/\d{2}\/\d{2,4})/', $segInfo, $m)) {
                    $date = strtotime(str_replace('/', '.', $m[1]));
                }

                if (!empty($date) && preg_match('/Partenza[ ]*\-[ ]*(\d{1,2}:\d{2})\s*Arrivo[ ]*\-[ ]*(\d{1,2}:\d{2})/', $segInfo, $m)) {
                    $s->departure()
                        ->date(strtotime($m[1], $date));
                    $s->arrival()
                        ->date(strtotime($m[2], $date));
                }

                if (preg_match('/\(([A-Z]{3})\)[ ]*\-(?:[ ]*>)?[ ]*\(([A-Z]{3})\)/', $segInfo, $m)) {
                    $s->departure()
                        ->code($m[1]);
                    $s->arrival()
                        ->code($m[2]);
                }
            }

            // Seats
            if (!empty($s->getFlightNumber()) && isset($seats[$s->getFlightNumber()])) {
                $s->extra()->seats($seats[$s->getFlightNumber()]);
            }
        }

        if ($segments->length > 0) {
            return;
        }

        // type 2: without title Depart (it-27093938.eml)

        $xpath = "//img[{$this->contains(['flight-from-icon', 'flight-to-icon'], '@src')} or contains(@alt,'Flight') or (@width='24' and @height='24')]/ancestor::tr[1][following::tr[normalize-space()][1][{$xpathTime}]][not(contains(normalize-space(), 'Powered by'))][normalize-space()]";

        if (0 === $this->http->XPath->query($xpath)->length) {
            $xpath = "//text()[normalize-space(.)='Your flight information']/ancestor::tr[1]/following::tr[normalize-space(.)][starts-with(normalize-space(.), 'To ')][not(.//tr)]";
        }

        if (0 === $this->http->XPath->query($xpath)->length) {
            $xpath = "//text()[{$this->eq($this->t('Your flight information'))}]/ancestor::tr[1]/following::tr/td[normalize-space(.)][{$this->starts($this->t('To '))}][contains(@style,'#F3F9FE') or contains(@style,'#f3f9fe')]";
        }

        $this->logger->debug("type 2 -> {$xpath}");
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            //$this->logger->debug("segment-$i: type 2");

            // FlightNumber
            $flight = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $root);

            if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/', $flight, $m)
                || preg_match('/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)(?:\D|\s*$)/', $root->nodeValue, $m)
            ) {
                $s->airline()->name($m[1])->number($m[2]);
            }

            if (!empty($s->getFlightNumber()) && !empty($seats[$s->getFlightNumber()])) {
                $s->extra()->seats($seats[$s->getFlightNumber()]);
            }

            $patterns['route'] = '/^\s*>?\s*(\(([A-Z]{3})\)\s*-(?:[ ]*>)?\s*\(([A-Z]{3})\))\s*>?\s*$/'; // (BRU) - (OPO)     |    > (KBP) - > (MAN) >

            $route = $this->http->FindSingleNode("descendant::text()[normalize-space()][last()]", $root, true, $patterns['route'])
                ?? $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::tr[not(.//tr)][normalize-space()][last()]/descendant::text()[normalize-space()][last()]", $root, true, $patterns['route'])
                ?? $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::tr[not(.//tr)][normalize-space()][last()]", $root, true, $patterns['route'])
                ?? $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::text()[normalize-space()][last()]", $root, true, $patterns['route'])
            ;

            if (preg_match($patterns['route'], $route, $m)) {
                $s->departure()->code($m[2]);
                $s->arrival()->code($m[3]);
            }

            $patterns['airports'] = '/^\s*\b(.{3,}\s+-\s+.{3,}[\w\)])\s*$/'; // Milano Malpensa T1 - TXL; Dublin T1 - London (Gatwick)

            $airports = $this->http->FindSingleNode("descendant::text()[normalize-space()][3]", $root, null, $patterns['airports'])
                ?? $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::tr[not(.//tr)][normalize-space()][1]", $root, null, $patterns['airports'])
                ?? $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root, null, $patterns['airports'])
            ;

            if (preg_match('/\S.+?\s+([A-Z\d]{1,4})\s*\-/', $airports, $m)) {
                $s->departure()->terminal(preg_replace("#^T(\d)$#", '$1', $m[1]));
            }

            if (preg_match('/\S.+?\s+\-\s*\S.+?\b([A-Z\d]{1,4})\s*$/', $airports, $m)) {
                $s->arrival()->terminal(preg_replace("#^T(\d)$#", '$1', $m[1]));
            }

            $xpathSegmentCell = "ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/..";
            $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?'; // 12:15 | 4:19PM    |    2:00 p.m.

            // $this->logger->debug($root->nodeValue);
            if (preg_match("/\s{3,}(\w+,.+?)\s+Departure time - ({$patterns['time']})\s+Arrival time - ({$patterns['time']})/", $root->nodeValue, $m)
                || preg_match("/\s{3}((?:\w+|\d{1,2})(?:,|\/).+?)\s+{$this->opt($this->t('Departure time - '))}({$patterns['time']})\s+{$this->opt($this->t('Arrival time - '))}({$patterns['time']})/", $root->nodeValue, $m)
            ) {
                /*
                    To Milan Malpensa T1 FR6325
                    Dublin T1 - Milan Malpensa T1    Sat, 18 Jan 20    Departure time - 12:15    Arrival time - 15:40    (DUB) - (MXP)
                */
                $s->departure()->date($this->normalizeDate($m[1] . ' ' . $m[2]));
                $s->arrival()->date($this->normalizeDate($m[1] . ' ' . $m[3]));
            } elseif ($this->http->XPath->query($xpathSegmentCell . "/descendant::tr[not(.//tr[normalize-space()]) and {$xpathTime}]", $root)->length === 2) {
                $date = $this->http->FindSingleNode($xpathSegmentCell . "/descendant::tr[not(.//tr[normalize-space()]) and normalize-space()][3]", $root)
                    ?? $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root);

                $depTime = $this->http->FindSingleNode($xpathSegmentCell . "/descendant::tr[not(.//tr[normalize-space()]) and normalize-space()][4]", $root, true, "/\s-\s*({$patterns['time']})/")
                    ?? $this->http->FindSingleNode($xpathSegmentCell . "/descendant::text()[{$xpathTime}][1]", $root);

                if (!empty($date) && !empty($depTime)) {
                    $s->departure()->date($this->normalizeDate($date . ' ' . $depTime));
                }

                $arrTime = $this->http->FindSingleNode($xpathSegmentCell . "/descendant::tr[not(.//tr[normalize-space()]) and normalize-space()][5]", $root, true, "/\s-\s*({$patterns['time']})/")
                    ?? $this->http->FindSingleNode($xpathSegmentCell . "/descendant::text()[{$xpathTime}][2]", $root);

                if (!empty($date) && !empty($arrTime)) {
                    $s->arrival()->date($this->normalizeDate($date . ' ' . $arrTime));
                }
            } elseif ($this->http->XPath->query($xpathSegmentCell . "/descendant::tr[not(.//tr[normalize-space()]) and normalize-space()]", $root)->length === 4
                || $this->http->XPath->query($xpathSegmentCell . "/descendant::tr[not(.//tr[normalize-space()]) and {$xpathTime}]", $root)->length === 1
            ) {
                $s->departure()->date($this->normalizeDate($this->http->FindSingleNode($xpathSegmentCell . "/descendant::tr[not(.//tr[normalize-space()]) and normalize-space()][3]", $root)));
                $s->arrival()->noDate();
            }

            /*$dateVariants = [
                '[^\d\W]{2,}\s*,\s*\d{1,2}\s+[^\d\W]{3,}\s+\d{2,4}', // Wed, 13 Mar 19
            ];

            if ( empty($s->getDepDate()) && empty($s->getArrDate())
                && preg_match("/(" . implode('|', $dateVariants) . ")\s*Departure time\s+-\s+({$patterns['time']})\s*Arrival time\s+-\s+({$patterns['time']})/u", $this->http->FindSingleNode("ancestor-or-self::*[contains(normalize-space(.),'Departure')][not(.//tr)][1]", $root), $m)
            ) {
                $m[1] = $this->normalizeDate($m[1]);
                $s->departure()->date( strtotime($m[1] . ' ' . $m[2]) );
                $s->arrival()->date( strtotime($m[1] . ' ' . $m[3]) );
            }*/
        }
    }

    private function isJunk(): bool
    {
        // it-114147171.eml
        $roots = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::table[{$this->starts($this->t('Reservation:'))}]/following-sibling::table[{$this->starts($this->t('Destination:'))}] and *[normalize-space()][2]/descendant::table[{$this->starts($this->t('Date:'))}]/following-sibling::table[{$this->starts($this->t('Depart:'))}] ]");

        if ($roots->length !== 1) {
            return false;
        }
        $root = $roots->item(0);

        $confirmation = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Reservation:'))}] ]/*[2]", $root, true, "/^[-A-Z\d]{5,}$/");
        $nameArr = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Destination:'))}] ]/*[2]", $root, true, "/^.{3,}$/");
        $dateDep = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Date:'))}] ]/*[2]", $root, true, "/^.*\d.*$/");
        $timeDep = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Depart:'))}] ]/*[2]", $root, true, "/^\d{1,2}:\d{2}.*/");

        if ($confirmation && $nameArr && $dateDep && $timeDep) {
            return true;
        }

        return false;
    }

    private function nextText($field, $root = null, $regexp = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[{$this->starts($field)}])[{$n}]/following::text()[normalize-space(.)!=''][1]", $root, true, $regexp);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        if (!is_array($field)) {
            $field = [$field];
        }
        $rule = implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), '{$s}')"; }, $field));

        return $this->http->FindSingleNode("(.//td[not(.//td) and ({$rule})])[{$n}]/following-sibling::td[normalize-space(.)!=''][1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->logger->info("Date: {$str}");
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+\s+(\d+\s+[^\d\s.]+\s+\d{4}),\s+(\d{1,2}:\d{2})$#",
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s.]+)\s+(\d{2}),\s+(\d{1,2}:\d{2})\s*$#",
            "#^(\d{1,2})([^\d\W\.\,]+)[.]?(\d{4}),\s+(\d{1,2}:\d{2})$#u", // 08Δεκ2017, 21:10
            "#^\w+\.?\s+(\d+)\s+([^\d\s.]+).?\s+(\d{4}),\s+(\d{1,2}:\d{2})$#u",
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4}),\s+(\d{1,2}:\d{2})$#",
            "#^(\d{2})(\d{1,2})(\d{4}), (\d{1,2}:\d{2})$#", // 24112017, 14:10    [OR]    0322018, 17:55
            "#^[^\s\d]+ (\d+) (\d+) (\d{4}), (\d{1,2}:\d{2})$#", // so 22 4 2017, 18:10
            "#^, $#",
            "#^\s*(\d{1,2})[./](\d{2})[./](\d{4})[ \-]+(\d{1,2}:\d{2})\s*$#", // 20.09.2017 - 12:00
            "#^\s*[^\s\d]+[,\s]+(\d{1,2})\s+([^\d\s]+)\s+(\d{2})[ \-]+(\d{1,2}:\d{2})\s*$#", // 20.09.2017 - 12:00
            "#^\s*([^\d\s,]+(?: [^\d\s,]+)?)\s*[,\s]\s*(\d+\s+[^\d\s.,]+)[\s,]+\s*(\d{1,2}:\d{2})\s*$#u",
            // 05 Jun 14, 17:50
            "#^\s*(\d+)\s+([^\d\s.]+)\s+(\d{2}),\s+(\d{1,2}:\d{2})\s*$#",
        ];
        $out = [
            "$1, $2",
            "$1 $2 20$3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "",
            "$1.$2.$3, $4",
            "$1 $2 20$3, $4",
            // "$1 $2 20$3, $4",
            "$1, $2 {$year}, $3",
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->info("Date 1: {$str}");

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } else {
                $remainingLangs = array_diff(array_keys(self::$dictionary), [$this->lang]);
                $remainingLangs[] = 'ru'; // it-11463478.eml

                foreach ($remainingLangs as $lang) {
                    if ($en = MonthTranslate::translate($m[1], $lang)) {
                        $str = str_replace($m[1], $en, $str);

                        break;
                    }
                }
            }
        }
        // $this->logger->info("Date 2: {$str}");

        if (preg_match("/^(?<week>\w+(?: \w+)?), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            // $this->logger->info("Date 4: " . WeekTranslate::translate($m['week'], $this->lang));
            // $this->logger->info("Date 4: " . $m['week']);
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            // $this->logger->info("weeknum " . $weeknum);
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        // $this->logger->info("weeknum " . EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum));
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }
        // $this->logger->info("str " . $str);

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

    private function mb_ucfirst($str, $enc = 'utf-8')
    {
        return mb_strtoupper(mb_substr($str, 0, 1, $enc), $enc) . mb_substr($str, 1, mb_strlen($str, $enc), $enc);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s); }, $field)) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function htmlToText($s = '', $brConvert = true): string
    {
        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b[ ]*\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
