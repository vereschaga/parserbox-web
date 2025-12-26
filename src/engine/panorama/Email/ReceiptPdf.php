<?php

namespace AwardWallet\Engine\panorama\Email;

use AwardWallet\Engine\MonthTranslate;

class ReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "panorama/it-10049431.eml, panorama/it-10051109.eml, panorama/it-10129005.eml, panorama/it-10153516.eml, panorama/it-10158239.eml, panorama/it-11915384.eml, panorama/it-11915711.eml, panorama/it-11963025.eml, panorama/it-12033152.eml, panorama/it-13650856.eml, panorama/it-13711374.eml, panorama/it-27318678.eml, panorama/it-29041098.eml, panorama/it-30464212.eml, panorama/it-39013605.eml, panorama/it-39017392.eml, panorama/it-9845281.eml, panorama/it-9911496.eml";

    public static $dictionary = [
        "fr" => [
            "itineraryTitle"  => "Reçu itineraire passager",
            "PNR locator:"    => ["Numéro de\nréservation:", "Numéro de"],
            "Passenger:"      => "Passager:",
            "Ticket number:"  => "Numéro de billet:",
            "Loyalty card:"   => "Carte de fidélité:",
            "Grand Total"     => "MONTANT TOTAL",
            "Fare"            => "Tarif:",
            "Taxes"           => "Taxes",
            "Booking date:"   => "Date de réservation:",
            "Flight details:" => "Détails du vol:",
            "Departure:"      => "Départ:",
            "Arrival:"        => "Arrivée:",
            "Equipment:"      => "Type d'avion:",
            "Class(RBD)"      => ["ClasseRBD", "Classe RBD"],
            "Duration:"       => "Durée:",
            //			"h" => "",
            //			"m" => "",
            "Total travelling time:" => ["Durée de l'escale:", "Durée de vol totale:"],
        ],
        "ru" => [
            "itineraryTitle"         => ["Маршрут-квитанция пассажира", "Электронная квитанция"],
            "PNR locator:"           => ["Номер\nбронирования:", "Номер"],
            "Passenger:"             => "Пассажир:",
            "Ticket number:"         => "Номер билета:",
            "Loyalty card:"          => ["Panorama Club:", "Карта программы\nлояльности:", "Карта программы"],
            "Grand Total"            => "Общая стоимость",
            "Fare"                   => "Тариф:",
            "Taxes"                  => "Таксы",
            "Booking date:"          => "Дата бронирования:",
            "Flight details:"        => "Детали полëта:",
            "Departure:"             => "Отправление:",
            "Arrival:"               => "Прибытие:",
            "Equipment:"             => "Тип воздушного\nсудна:",
            "Class(RBD)"             => ["Класс(RBD)", 'Класс (RBD)'],
            "Duration:"              => "Продолжительность:",
            "h"                      => "ч.",
            "m"                      => "мин",
            "Total travelling time:" => ["Общее время полета:", "Общее время в пути:"],
            'Terminal'               => 'Терминал',
        ],
        "it" => [
            "itineraryTitle"  => "Ricevuta dell'itinerario del passeggero",
            "PNR locator:"    => ["Сodice della\nprenotazione:", "Сodice della"],
            "Passenger:"      => "Рasseggero:",
            "Ticket number:"  => "Numero del biglietto:",
            "Loyalty card:"   => ["Carta del programma\ndi fidelizzazione:", "Carta del programma"],
            "Grand Total"     => "IMPORTO TOTALE",
            "Fare"            => "Tariffa:",
            "Taxes"           => "Tassas",
            "Booking date:"   => "Data di prenotazione:",
            "Flight details:" => "Informazioni sul volo:",
            "Departure:"      => "Partenza:",
            "Arrival:"        => "Arrivo:",
            "Equipment:"      => "Aeromobile:",
            "Class(RBD)"      => ["Classe(RBD)", "Classe (RBD)"],
            "Duration:"       => "Durata:",
            //			"h" => "",
            //			"m" => "",
            "Total travelling time:" => ["Tempo totale di volo:", "Durata dello stopover"],
        ],
        "de" => [
            "itineraryTitle"  => "Buchungsbestätigung",
            "PNR locator:"    => "Buchungsreferenz:",
            "Passenger:"      => "Passagier:",
            "Ticket number:"  => "Ticketnummer:",
            "Loyalty card:"   => "Vielfliegerkarte:",
            "Grand Total"     => "GESAMTSUMME",
            "Fare"            => "Tarif:",
            "Taxes"           => "Steuern",
            "Booking date:"   => "Buchungsdatum",
            "Flight details:" => "Flugangaben:",
            "Departure:"      => "Abflug:",
            "Arrival:"        => "Ankunft:",
            "Equipment:"      => "Fluggerät:",
            "Class(RBD)"      => ["Klasse(RBD)", "Klasse (RBD)"],
            "Duration:"       => "Dauer:",
            //			"h" => "",
            //			"m" => "",
            "Total travelling time:" => ["Dauer des Zwischenstopps", "Gesamtreisezeit:"],
        ],
        "es" => [
            "itineraryTitle" => "Itinerario del pasajero",
            "PNR locator:"   => "Código de reserva:",
            "Passenger:"     => "Pasajero:",
            "Ticket number:" => "Número de billete:",
            //            "Loyalty card:" => "",
            "Grand Total"     => "IMPORTE TOTAL",
            "Fare"            => "Tarifa:",
            "Taxes"           => "Tasas",
            "Booking date:"   => "Fecha de reserva:",
            "Flight details:" => "Datos de vuelo:",
            "Departure:"      => "Salida:",
            "Arrival:"        => "Llegada:",
            "Equipment:"      => "Tipo de avión:",
            "Class(RBD)"      => ["Clase(RBD)", "Clase(RBD)"],
            "Duration:"       => "Duración:",
            //			"h" => "",
            //			"m" => "",
            "Total travelling time:" => ["Duración de escala:", "Duración total del viaje:"],
        ],
        "pl" => [
            //            "itineraryTitle" => "",
            "PNR locator:"   => "Numer rezerwacji:",
            "Passenger:"     => "Pasażer:",
            "Ticket number:" => "Numer bileta:",
            "Loyalty card:"  => "Panorama Club:",
            "Grand Total"    => "Wartość ogólna",
            "Fare"           => "Taryfa:",
            "Taxes"          => "Opłaty",
            "Booking date:"  => "Data rezerwacji:",
            //			"Flight details:" => "",
            "Departure:" => "Odlot:",
            "Arrival:"   => "Przylot:",
            "Equipment:" => ["Rodzaj statku\npowietrznego", 'Typ samolotu'],
            "Class(RBD)" => ["Klasa (RBD)", "Klasa(RBD)"],
            "Duration:"  => "Czas trwania:",
            //			"h" => "",
            //			"m" => "",
            "Total travelling time:" => "Ogólny czas lotu:",
        ],
        "he" => [
            //            "itineraryTitle" => "",
            "PNR locator:"   => "‫מ ס פ ר ה ז מ נ ה‪:‬",
            "Passenger:"     => "נו ס ע‪:‬‬",
            "Ticket number:" => "‫מ ס פ ר כ ר טי ס ‪:",
            "Loyalty card:"  => ":Panorama Club",
            "Grand Total"    => "‫סה\"כ עלות",
            "Fare"           => "‫ת ע רי ף",
            "Taxes"          => "‫א ג רו ת",
            "Booking date:"  => "‫ת א רי ך ה ה ז מ נ ה",
            //			"Flight details:" => "",
            "Departure:" => "י צי א ה ‪:",
            "Arrival:"   => "ה ג ע ה‪:",
            "Equipment:" => "‫סו ג ה מ טו ס‪:‬",
            "Class(RBD)" => "‫מחלקת שירות )‪(RBD‬‬",
            "Duration:"  => "זמן עצירה‪:",
            //			"h" => "",
            //			"m" => "",
        ],
        "sv" => [
            //            "itineraryTitle" => "",
            "PNR locator:"   => "Biljettnummer:",
            "Passenger:"     => "Passagerare:",
            "Ticket number:" => "Biljettnummer:",
            //            "Loyalty card:" => ":",
            "Grand Total"   => "Totalkostnad",
            "Fare"          => "Pris:",
            "Taxes"         => "Avgifter",
            "Booking date:" => "Bokningsdatum:",
            //			"Flight details:" => "",
            "Departure:" => "Avgång:",
            "Arrival:"   => "Ankomst:",
            "Equipment:" => "Typ av flygplan:",
            "Class(RBD)" => ["Klass (RBD)", "Klass(RBD)"],
            "Duration:"  => "Varaktighet:",
            //			"h" => "",
            //			"m" => "",
            //			"Total travelling time:" => "",
            'Terminal' => 'Terminalen',
        ],
        "lt" => [
            "itineraryTitle" => "Keleivio maršruto kvitas",
            "PNR locator:"   => "Rezervacijos numeris:",
            "Passenger:"     => "Keleivis:",
            "Ticket number:" => "Bilieto numeris:",
            "Loyalty card:"  => "Panorama Club:",
            "Grand Total"    => "Bendra kaina",
            "Fare"           => "Tarifas:",
            //            "Taxes" => "",
            "Booking date:"   => "Rezervacijos data:",
            "Flight details:" => "Skrydžio detalės:",
            "Departure:"      => "Išvykimas:",
            "Arrival:"        => "Atvykimas:",
            "Equipment:"      => "Orlaivio tipas:",
            "Class(RBD)"      => ["Klasė(RBD)", "Klasė (RBD)"],
            "Duration:"       => "Trukmė:",
            //			"h" => "",
            //			"m" => "",
            "Total travelling time:" => "Persėdimo trukmė:",
            "Terminal"               => "Terminalas",
        ],
        "tr" => [
            "itineraryTitle" => "Yolcu güzergar makbuzu",
            "PNR locator:"   => "Rezervasyon No.:",
            "Passenger:"     => "Yolcu:",
            "Ticket number:" => "Bilet No:",
            //            "Loyalty card:" => "",
            "Grand Total" => "Toplam ücret",
            "Fare"        => "Ücret:",
            //            "Taxes" => "",
            "Booking date:"   => "Rezervasyon tarihi:",
            "Flight details:" => "Uçuş detayları:",
            "Departure:"      => "Nereden:",
            "Arrival:"        => "Geliş:",
            "Equipment:"      => "Uçak tipi:",
            "Class(RBD)"      => ["Sınıf(RBD)", "Sınıf (RBD)"],
            "Duration:"       => "Süre:",
            //			"h" => "",
            //			"m" => "",
            "Total travelling time:" => "Toplam uçuş süresi:",
            "Terminal"               => "Terminal",
        ],
        "uk" => [
            "itineraryTitle"  => "Маршрут-квитанція пасажира",
            "PNR locator:"    => "Номер бронювання:",
            "Passenger:"      => "Пасажир:",
            "Ticket number:"  => "Номер квитка:",
            "Loyalty card:"   => "Panorama Club:",
            "Grand Total"     => "Загальна вартість",
            "Fare"            => "Тариф:",
            "Taxes"           => "Такси",
            "Booking date:"   => "Дата бронювання:",
            "Flight details:" => "Деталі польоту:",
            "Departure:"      => "Відправлення:",
            "Arrival:"        => "Прибуття:",
            "Equipment:"      => ["Тип повітряного судна:", "Тип повітряного\nсудна:", "Тип повітряногосудна:"],
            "Class(RBD)"      => ["Клас(RBD)", "Клас (RBD)"],
            "Duration:"       => "Тривалість:",
            //			"h" => "",
            //			"m" => "",
            "Total travelling time:" => "Загальний часпольоту:",
            //            "Terminal" => "",
        ],
        "en" => [
            "itineraryTitle"         => ["Passenger itinerary receipt", "Electronic miscellaneous document"],
            "Class(RBD)"             => ["Class(RBD)", "Class (RBD)", "Class\n(RBD)", "Class(RBD):", "Class (RBD):", "Class\n(RBD):", "Class\n\n(RBD):"],
            "Total travelling time:" => ["Total travelling time:", "Duration of stopover:"],
        ],
    ];

    public $lang = "";

    private $subjects = [
        'Ukraine International Airlines reservation',
        'Ukraine International Airlines ordered services for reservation',
    ];
    private $pdfPattern = "("
        . "E-ticket receipt .* \d+_?.pdf"
        . "|Confirmation of the ordered services .* for ticket .*pdf"
        . "|confirmation[-_ ]ordered[-_ ]services[-_ ].*[-_ ]booking[-_ ].*pdf"
        . ")";
    private $reBody = [
        'he'  => ['ד ף ה מ ס לו ל ש ל נו ס ע‪', 'פ ר טי טי ס ה‪:'],
        'fr'  => ['Reçu itineraire passager', 'Ukraine International Airlines'],
        'ru'  => ['Маршрут-квитанция пассажира', 'Международные Авиалинии Украины'],
        'ru2' => ['Электронная квитанция (EMD)', 'Международные Авиалинии Украины'],
        'it'  => ["Ricevuta dell'itinerario del passeggero", 'Ukraine International Airlines'],
        'de'  => ["Buchungsbestätigung:", 'Ukraine International Airlines'],
        'es'  => ["Código de reserva:", 'Ukraine International Airlines'],
        'pl'  => ['Szczegóły lotu', 'Międzynarodowe Linie Lotnicze Ukrainy'],
        'sv'  => ['Flygningdetaljer', 'Internationella flygbolag i Ukraina'],
        'lt'  => ['Keleivio maršruto kvitas', 'Ukrainos Tarptautinės Oro linijos'],
        'tr'  => ['Yolcu güzergar makbuzu', 'Ukraine İnternational'],
        'uk'  => ['Маршрут-квитанція пасажира', 'Міжнародні Авіалінії України'],
        'en'  => ['Flight details:', 'Ukraine International Airlines'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'FlyUIA.com') !== false
            || stripos($from, '@flyuia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $phrase) {
            if (stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        foreach ($this->reBody as $re) {
            if (strpos($text, $re[0]) !== false && strpos($text, $re[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

//            $this->logger->info($this->text);

            foreach ($this->reBody as $lang => $re) {
                if (stripos($textPdf, $re[0]) !== false && stripos($textPdf, $re[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }

            $pdfParts = $this->splitText($textPdf, "/^[ ]*{$this->opt($this->t('itineraryTitle'))}/im");

            if (count($pdfParts) === 0) {
                $pdfParts = [$textPdf];
            }

            foreach ($pdfParts as $pdfPart) {
                $itinerary = [];
                $this->parsePdf($itinerary, $pdfPart);
                $itineraries[] = $itinerary;
            }
        }
        $itineraries = $this->mergeItineraries($itineraries, true);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function mergeItineraries($its, $sumTotal = false)
    {
        $delSums = false;
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                    foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                        if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])
                            && (isset($tsJ['Seats']) || isset($tsI['Seats']))
                        ) {
                            $new = [];

                            if (isset($tsJ['Seats'])) {
                                $new = array_merge($new, (array) $tsJ['Seats']);
                            }

                            if (isset($tsI['Seats'])) {
                                $new = array_merge($new, (array) $tsI['Seats']);
                            }
                            $its[$j]['TripSegments'][$flJ]['Seats'] = array_values(array_filter(array_unique($new)));
                            $its[$i]['TripSegments'][$flI]['Seats'] = array_values(array_filter(array_unique($new)));
                        }
                    }
                }

                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize',
                    array_unique(array_map('serialize', $its[$j]['TripSegments'])));

                $mergeFields = ['Passengers', 'AccountNumbers', 'TicketNumbers'];

                foreach ($mergeFields as $mergeField) {
                    if (isset($its[$j][$mergeField]) || isset($its[$i][$mergeField])) {
                        $new = [];

                        if (isset($its[$j][$mergeField])) {
                            $new = array_merge($new, $its[$j][$mergeField]);
                        }

                        if (isset($its[$i][$mergeField])) {
                            $new = array_merge($new, $its[$i][$mergeField]);
                        }
                        $new = array_values(array_filter(array_unique(array_map("trim", $new))));
                        $its[$j][$mergeField] = $new;
                    }
                }

                if ($sumTotal) {
                    $sumFields = ['TotalCharge', 'BaseFare', 'Tax'];

                    foreach ($sumFields as $sumField) {
                        if ($sumTotal && (isset($its[$j][$sumField]) || isset($its[$i][$sumField]))) {
                            if (isset($its[$j]['Currency'], $its[$i]['Currency']) && !empty($its[$j]['Currency']) && !empty($its[$i]['Currency']) && $its[$j]['Currency'] !== $its[$i]['Currency']) {
                                $delSums = true;
                            } else {
                                $new = 0.0;

                                if (isset($its[$j][$sumField])) {
                                    $new += $its[$j][$sumField];
                                }

                                if (isset($its[$i][$sumField])) {
                                    $new += $its[$i][$sumField];
                                }

                                if (!empty($new)) {
                                    $its[$j][$sumField] = $new;
                                }
                            }
                        }
                    }
                }
                unset($its[$i]);
            }
        }

        if ($delSums) {
            $its2 = $its;

            foreach ($its2 as $i => $it) {
                $delElements = ['TotalCharge', 'BaseFare', 'Tax', 'Currency'];

                foreach ($delElements as $delElement) {
                    if (isset($it[$delElement])) {
                        unset($its[$i][$delElement]);
                    }
                }
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function parsePdf(&$itinerary, $text)
    {
        // Booking date:                26.11.2017                                          Passenger:                  Mr. ADI SHALOM
        // Ticket number:               5662410602874                                                                   DISKIN
        // PNR locator:                 SE4ZLI                                              Loyalty card:
        $mainTableText = $this->strposArray($text, $this->t("Booking date:")) < $this->strposArray($text, $this->t("Passenger:"))
            ? $this->re("#\n([^\n\S]*{$this->opt($this->t("Booking date:"))}.*?)\n\n#siu", $text)
            : $this->re("#\n([^\n\S]*{$this->opt($this->t("Passenger:"))}.*?)\n\n#siu", $text);
        $mainTablePos = [0];

        if (preg_match("#^(.+)  {$this->opt($this->t("Passenger:"))}#m", $mainTableText, $m)) {
            $mainTablePos[] = mb_strlen($m[1]);
        }

        if (preg_match("#^(.+)  {$this->opt($this->t("Loyalty card:"))}#m", $mainTableText, $m)) {
            $mainTablePos[] = mb_strlen($m[1]);
        }
        sort($mainTablePos);
        $mainTablePos = array_values(array_unique($mainTablePos));
        $mainTable = $this->SplitCols($mainTableText, $mainTablePos);

        if (count($mainTable) !== 1 && count($mainTable) !== 2) {
            $this->logger->alert('Incorrect parse mainTable!');

            return;
        }
        $mainTableGlue = implode("\n", $mainTable);

        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#^[ ]*{$this->opt($this->t("PNR locator:"))}[ ]*([A-Z\d]{5,7})$#m", $mainTableGlue);

        // Passengers
        $passenger = $this->re("#^[ ]*{$this->opt($this->t("Passenger:"))}\s*([[:alpha:](][-)(.\'[:alpha:]\s]*[)[:alpha:]])$#mu", $mainTableGlue);
        // Пані (Mrs.) VITA SMACHELYUK
        if ($passenger) {
            $it['Passengers'] = [preg_replace('/\s+/', ' ', preg_replace("#\s*{$this->opt($this->t("Loyalty card:"))}$#", '', $passenger))];
        }

        // TicketNumbers
        $ticketNumber = $this->re("#^[ ]*{$this->opt($this->t("Ticket number:"))}[ ]*(\d{3}[- ]*\d{5,}[- ]*\d{1,2})$#m", $mainTableGlue);

        if ($ticketNumber) {
            $it['TicketNumbers'] = [$ticketNumber];
        }

        // AccountNumbers
        $ffNumber = $this->re("#^[ ]*{$this->opt($this->t("Loyalty card:"))}[ ]*(.+)$#m", $mainTableGlue);

        if ($ffNumber) {
            $it['AccountNumbers'] = [$ffNumber];
        }

        $it = array_filter($it);

        // Currency
        // TotalCharge
        if (preg_match("#{$this->opt($this->t("Grand Total"))}[^\n\S]+(?<currency>[A-Z]{3}) ?(?<amount>\d[,.\'\d]*)(?:[ ]{2,}|\n|$)#iu", $text, $m)) {
            $it['Currency'] = $this->currency($m['currency']);
            $it['TotalCharge'] = $this->amount($m['amount']);
        }

        // Fare               Taxes                       Fees    Ticket price
        // USD 165.00         YK:   USD   11.00                   USD 315.92
        $TaxFeesTable = $this->SplitCols($this->re("#\n([^\S\n]*" . $this->t("Fare") . "\s+.*?)\n\n\n#msiu", $text));

        if (count($TaxFeesTable) == 4) {
            // Tax
            $it['Tax'] = array_sum(array_map([$this, "amount"], explode("\n", $this->re("#" . $this->t("Taxes") . "\s+(\S.+)#msiu", $TaxFeesTable[1]))));

            // BaseFare
            $it['BaseFare'] = $this->amount(trim($this->re("#" . $this->t("Fare") . "\s+(.+)#msiu", $TaxFeesTable[0])));
        }

        // ReservationDate
        $bookingDate = $this->re("#^[ ]*{$this->opt($this->t("Booking date:"))}[ ]*(.{6,})$#m", $mainTableGlue);

        if ($bookingDate) {
            $it['ReservationDate'] = strtotime($this->normalizeDate($bookingDate));
        }

        $segmentsText = $this->re("#\n[ ]*{$this->opt($this->t("Flight details:"))}\n+([\s\S]+?)(?:{$this->opt($this->t("Flight details:"))}|$)#", $text);
        preg_match_all("#^([ ]*.{3,}\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(?::(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))? \d{1,5}$\s+[\s\S]*?{$this->opt($this->t("Arrival:"))}[\s\S]*?)(?:\n\n|{$this->opt($this->t("Total travelling time:"))})#m", $segmentsText ?? $text, $segments);

        if (empty($segments[1])) {
            return null;
        }

        foreach ($segments[1] as $stext) {
            // Departure:                                                                       Class(RBD)                  Economy (V)
            // 03 Dec 2017                  Tel Aviv Yafo, Tel Aviv Yafo Ben Gurion             Fare basis:                 VL2LMP1
            //                              International (TLV)                                 Refund:                     At a charge
            // 22:00 (local time)           Terminal - 3                                        Change of                   At a charge
            $tableText = $this->re("#^([ ]*{$this->opt($this->t("Departure:"))}[\s\S]+)#imu", $stext);
            $tablePos = $this->colsPos($tableText, 12);
            $table = $this->splitCols($tableText, $tablePos);

            if (count($table) !== 4) {
                $tableHeadDep = $this->re("#^[ ]*{$this->opt($this->t("Departure:"))}.*\n(.+)#imu", $tableText);
                $tablePos = $this->rowColsPos($tableHeadDep);
                $table = $this->splitCols($tableText, $tablePos);
            }

            if (count($table) !== 4) {
                $tableBodyArr = $this->re("#^([ ]*{$this->opt($this->t("Arrival:"))}[\s\S]+)#imu", $stext);
                $tablePos = $this->colsPos($tableBodyArr, 12);
                $table = $this->splitCols($tableText, $tablePos);
            }

            if (count($table) !== 4) {
                $tableHeadArr = $this->re("#^[ ]*{$this->opt($this->t("Arrival:"))}.*\n(.+)#imu", $tableText);
                $tablePos = $this->rowColsPos($tableHeadArr);
                $table = $this->splitCols($tableText, $tablePos);
            }

            if (count($table) !== 4) {
                if (preg_match("/^((.+ ){$this->opt($this->t('Duration:'))}[ ]*)\d/m", $stext, $m)) {
                    $tablePos[2] = mb_strlen($m[2]);
                    $tablePos[3] = mb_strlen($m[1]);
                }
                $table = $this->splitCols($tableText, $tablePos);
            }

            if (count($table) != 4) {
                $this->logger->alert('Incorrect parse table!');

                return;
            }

            // Tel Aviv Yafo, Tel Aviv Yafo Ben Gurion International (TLV) - Lviv, Lviv International                     PS 784
            // (LWO)

            $headerTable = $this->SplitCols($this->re("#^(.*?)\n[^\n\S]*" . $this->t("Departure:") . "#msiu", $stext));

            if (count($headerTable) != 2) {
                // Kiev, Aeropuerto de Kiev Boryspil (KBP) (KBP) - Madrid, Aeropuerto Barajas de Madrid PS 945
                // (MAD)
                /*
                 * PS 1784
                 * or
                 * PS:7W 1008
                 * */

                if (preg_match("#(.+?)\s*(PS[\:A-Z\d]*\s+\d+)(.*)#su", $stext, $m)) {
                    $headerTable[0] = $m[1] . $m[3];
                    $headerTable[1] = $m[2];
                } else {
                    $this->logger->alert('Incorrect parse headerTable!');

                    return;
                }
            }

            $itsegment = [];

            // AirlineName
            // FlightNumber
            if (preg_match("#(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<FlightNumber>\d+)#iu", trim(str_replace("\n", " ", $headerTable[1])), $m)) {
                // PS 1784
                $keys = ["AirlineName", "FlightNumber"];

                foreach ($keys as $k) {
                    $itsegment[$k] = $m[$k];
                }
            } elseif (preg_match("#^(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z]):\w{2}\s+(?<FlightNumber>\d+)$#iu", trim(str_replace("\n", " ", $headerTable[1])), $m)) {
                // PS:7W 1008 (example: it-11963025.eml)
                $keys = ["AirlineName", "FlightNumber"];

                foreach ($keys as $k) {
                    $itsegment[$k] = $m[$k];
                }
            }

            // DepCode
            // DepName
            // ArrCode
            // ArrName
            if (preg_match("#(?<DepName>.*?)\s*\((?<DepCode>[A-Z]{3})\)\s+-\s+(?<ArrName>.*?)\s*\((?<ArrCode>[A-Z]{3})\)#iu", trim(str_replace("\n", " ", $headerTable[0])), $m)) {
                $keys = ["DepName", "DepCode", "ArrName", "ArrCode"];

                foreach ($keys as $k) {
                    $itsegment[$k] = preg_replace('/^\W*$/u', '', preg_replace('/\s+/', ' ', $m[$k]));
                }
            }

            // DepartureTerminal
            $names = array_merge([], array_filter(array_map('trim', explode("\n\n", $table[1]))));

            if (count($names) >= 2) {
                $itsegment['DepartureTerminal'] = $this->re("#{$this->t('Terminal')} - (.+)#iu", $names[0]);
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(trim(str_replace("\n", " ", $this->re("#{$this->t("Departure:")}\n+(.*?){$this->t("Arrival:")}#msiu", $table[0])))));

            // ArrivalTerminal
            if (count($names) >= 2) {
                $itsegment['ArrivalTerminal'] = $this->re("#{$this->t('Terminal')} - (.+)#iu", $names[1]);
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate(trim(str_replace("\n", " ", $this->re("#{$this->t("Arrival:")}\n+(.+)#s", $table[0])))));

            $table[3] = preg_replace("/\n(\d{1,3}\s*{$this->opt($this->t('h'))})(\n+)(\d{1,3}\s*{$this->opt($this->t('m'))})/i", '$2$1 $3', $table[3]); // duration normalize

            // Aircraft
            $itsegment['Aircraft'] = $this->field($this->t("Equipment:"), $table[2], $table[3]);

            // Cabin
            $itsegment['Cabin'] = $this->re("#(.*?) \([A-Z]{1,2}\)#", $this->field($this->t("Class(RBD)"), $table[2], $table[3]));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#\(([A-Z]{1,2})\)#", $this->field($this->t("Class(RBD)"), $table[2], $table[3]));

            // Duration
            $duration = $this->field($this->t("Duration:"), $table[2], $table[3]);

            if (preg_match("/^\d.*/", $duration)) {
                $itsegment['Duration'] = $duration;
            }

            // Seats
            if (!empty($itsegment['DepCode']) && !empty($itsegment['ArrCode']) && !empty($itsegment['FlightNumber'])
                && preg_match_all("/^[ ]*{$itsegment['DepCode']}[- ]+{$itsegment['ArrCode']}[:A-Z\d ]+{$itsegment['FlightNumber']} .+{$this->opt($this->t('Seat'))} (\d{1,5}[A-Z])(?:[ ]{2}|$)/m", $text, $m)
            ) {
                $itsegment['Seats'] = $m[1];
            }

            $it['TripSegments'][] = $itsegment;
        }

        $itinerary = $it;
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
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s*([^\s\d.]+)\.?\s*(\d{4})\s+(\d+:\d+)[ ]*\(.*\).*$#", //17 Nov. 2017 05:30 (local time)
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }

            if (strtotime($str) == false) {
                $uk = $this->transliterateMonthLatToUk($m[1]);

                if ($en = MonthTranslate::translate($uk, 'uk')) {
                    $str = str_replace($m[1], $en, $str);
                }
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

    private function field($fns, $names, $values)
    {
        $fns = (array) $fns;

        foreach ($fns as $fn) {
            if (($pos = strpos($names, $fn)) !== false) {
                $start = substr_count(substr($names, 0, $pos), "\n");
                $end = $start + substr_count($fn, "\n");
                $afterRows = explode("\n", substr($names, $pos + strlen($fn)));
                array_shift($afterRows);

                foreach ($afterRows as $row) {
                    if (strlen($row) == 0) {
                        $end++;
                    } else {
                        break;
                    }
                }
                $rows = explode("\n", $values);
                $res = [];

                for ($i = $start; $i <= $end; $i++) {
                    $res[] = $rows[$i];
                }

                return trim(implode(" ", $res));
            }
        }

        return null;
    }

    private function rowColsPos($row)
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

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        $field = (array) $field;
        $field = array_map(function ($s) { return str_replace(["(", ")"], ["\(", "\)"], $s); }, $field);

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function transliterateMonthLatToUk($st)
    {
        //"січень","лютий","березень","квітень","травень","червень","липень","серпень","вересень","жовтень","листопад","грудень"
        //letters which use in names of month in 'uk'
        // "абвгдежзиійклнопрстучью"
        $st = trim(strtolower($st));

        $doubleSign = ['zh' => 'ж', 'ch' => 'ч', 'iu' => 'ю', 'yu' => 'ю', 'li' => 'ли'];

        foreach ($doubleSign as $key => $value) {
            if (mb_strpos($st, $key) !== false) {
                $st = str_replace($key, $value, $st);
            }
        }

        if (strpos($st, 'i') !== (strlen($st) - 1)) {
            $st = str_replace('i', 'I', $st);
        }

        if (strpos($st, 'n') === (strlen($st) - 1)) {
            $st .= 'ь';
        }
        $singleSign = [
            'a' => 'а',
            'b' => 'б',
            'v' => 'в',
            'g' => 'г',
            'd' => 'д',
            'e' => 'е',
            'z' => 'з',
            'y' => 'и',
            'i' => 'й',
            'k' => 'к',
            'l' => 'л',
            'n' => 'н',
            'o' => 'о',
            'p' => 'п',
            'r' => 'р',
            's' => 'с',
            't' => 'т',
            'u' => 'у',
        ];

        foreach ($singleSign as $key => $value) {
            if (mb_strpos($st, $key) !== false) {
                $st = str_replace($key, $value, $st);
            }
        }
//        $st = strtr($st,
//            "abvgdezyiklnoprstu",
//            "абвгдезийклнопрсту"
//        );
        return strtolower($st);
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

    private function splitText($textSource = '', string $pattern, $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
