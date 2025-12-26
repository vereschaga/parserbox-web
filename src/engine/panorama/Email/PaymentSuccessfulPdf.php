<?php

namespace AwardWallet\Engine\panorama\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PaymentSuccessfulPdf extends \TAccountChecker
{
    public $mailFiles = "panorama/it-11404511.eml, panorama/it-11432938.eml, panorama/it-11441682.eml, panorama/it-11476324.eml, panorama/it-11530003.eml, panorama/it-11586917.eml, panorama/it-28599702.eml, panorama/it-39111263.eml, panorama/it-39251963.eml, panorama/it-9953437.eml";

    public static $dict = [
        'en' => [
            "Booking Ref" => ["Scoot Booking Ref", "Booking Ref"],
            "Departure"   => ["Departure:", "Departure"],
            //			"Arrival" => "",
            //			"Terminal" => "",
            //			"Equipment" => "",
            //			"Traveling time" => "",
            //			"Booking class" => "",
            //			"Meal" => "",
            //			"PASSENGERS" => "",
            //          "MY FLIGHT" =>"",
            //			"Seat" => "",
            //			"Date of birth" => "",
            //			"Loyalty card" => "",
            //			"MY TOTAL" => "",
            //			"Fare" => "",
            //			"Tax" => "",
            "GRAND TOTAL" => ["GRAND TOTAL", "TOTAL AMOUNT PAID"],
        ],
        'ru' => [
            "Booking Ref"    => "Номер бронирования",
            "Departure"      => "Отправление",
            "Arrival"        => "Прибытие",
            "Terminal"       => "Терминал",
            "Equipment"      => "Тип воздушного судна",
            "Traveling time" => "Продолжительность полета",
            "Booking class"  => "Класс бронирования",
            "Meal"           => "Питание",
            "PASSENGERS"     => "ПАССАЖИРЫ",
            "MY FLIGHT"      => "МОЙ РЕЙС",
            //			"Seat" => "",
            "Date of birth" => "Дата рождения",
            "Loyalty card"  => "Panorama Club:",
            "MY TOTAL"      => "ИТОГ",
            "Fare"          => "Тариф:",
            "Tax"           => "Таксы",
            "GRAND TOTAL"   => "ОБЩАЯ СТОИМОСТЬ",
        ],
        'he' => [
            "Booking Ref"    => "מספר הזמנה",
            "Departure"      => "י צ י א ה",
            "Arrival"        => "ה ג ע ה",
            "Terminal"       => "ה ט ר מי נ ל",
            "Equipment"      => "סוג המטוס",
            "Traveling time" => "משך הטיסה",
            //			"Booking class" => "",
            //			"Meal" => "",
            "PASSENGERS" => "נו ס עי ם",
            "MY FLIGHT"  => "ה טי ס ה ש לי",
            //			"Seat" => "",
            "Date of birth" => "תאריך לידה",
            "Loyalty card"  => "מועדון פנורמה",
            "MY TOTAL"      => "ס ך ה כו ל",
            "Fare"          => "ת ע ר י ף",
            "Tax"           => "א ג רו ת",
            "GRAND TOTAL"   => "סה\"כ ערך",
        ],
        'uk' => [
            "Booking Ref"    => "Номер бронювання",
            "Departure"      => "Відправлення",
            "Arrival"        => "Прибуття",
            "Terminal"       => "Термінал",
            "Equipment"      => "Тип повітряного судна",
            "Traveling time" => "Тривалість польоту",
            "Booking class"  => "Клас бронювання",
            //			"Meal" => "",
            "PASSENGERS" => "ПАСАЖИРИ",
            "MY FLIGHT"  => "МІЙ РЕЙС",
            //			"Seat" => "",
            "Date of birth" => "Дата народження",
            "Loyalty card"  => "Panorama Club:",
            "MY TOTAL"      => "ПІДСУМОК",
            "Fare"          => "Тариф:",
            "Tax"           => "Такси",
            "GRAND TOTAL"   => "ЗАГАЛЬНА ВАРТІСТЬ",
        ],
        'de' => [
            "Booking Ref" => "Buchungsreferenz",
            "Departure"   => "Abflug",
            "Arrival"     => "Ankunft",
            //			"Terminal" => "",
            "Equipment"      => "Abflug",
            "Traveling time" => "Flugdauer",
            "Booking class"  => "Buchungsklasse",
            "Meal"           => "Verpflegung",
            "PASSENGERS"     => "FLUGGÄSTE",
            "MY FLIGHT"      => "MEIN FLUG",
            //			"Seat" => "",
            "Date of birth" => "Geburtsdatum",
            "Loyalty card"  => "Panorama Club",
            "MY TOTAL"      => "INSGESAMT",
            "Fare"          => "Tarif",
            "Tax"           => "Steuern",
            "GRAND TOTAL"   => "GESAMTSUMME",
        ],
        'fr' => [
            "Booking Ref"    => "Num de réservation",
            "Departure"      => "Départ",
            "Arrival"        => "Arrivée",
            "Terminal"       => "Terminal",
            "Equipment"      => "Type d'avion",
            "Traveling time" => "Temps de vol",
            //			"Booking class" => "",
            //			"Meal" => "",
            "PASSENGERS" => "PASSAGERS",
            "MY FLIGHT"  => "MON VOL",
            //			"Seat" => "",
            "Date of birth" => "Date de naissance",
            //			"Loyalty card" => "",
            "MY TOTAL"    => "TOTAL",
            "Fare"        => "Tarif:",
            "Tax"         => "Taxes",
            "GRAND TOTAL" => "MONTANT TOTAL",
        ],
        'it' => [
            "Booking Ref"    => "Codige della prenotazione",
            "Departure"      => "Partenza",
            "Arrival"        => "Arrivo",
            "Terminal"       => "Terminal",
            "Equipment"      => "Aeromobile",
            "Traveling time" => "Durata del volo",
            //			"Booking class" => "",
            //			"Meal" => "",
            "PASSENGERS" => "PASSEGGERI",
            "MY FLIGHT"  => "IL MIO VOLO",
            //			"Seat" => "",
            "Date of birth" => "Data di nascita",
            //			"Loyalty card" => "",
            "MY TOTAL"    => "TOTALE",
            "Fare"        => "Tariffa:",
            "Tax"         => "Tasse",
            "GRAND TOTAL" => "IMPORTO TOTALE",
        ],
        'pl' => [
            "Booking Ref"    => "Numer rezerwacji",
            "Departure"      => ["Wylot", "Odlot"],
            "Arrival"        => "Pryzlot",
            "Terminal"       => "Terminał",
            "Equipment"      => "Typ samolotu",
            "Traveling time" => "Czas trwania lotu",
            "Booking class"  => "Klasa rezerwacji",
            "Meal"           => "Zasilanie",
            "PASSENGERS"     => "PASAŻEROWIE",
            //          "MY FLIGHT" =>"",
            //			"Seat" => "",
            "Date of birth" => "Data urodzenia",
            "Loyalty card"  => "Panorama Club",
            "MY TOTAL"      => "PODSUMOWANIE",
            "Fare"          => "Taryfa:",
            "Tax"           => "Podatek",
            "GRAND TOTAL"   => "ŁĄCZNA WARTOŚĆ",
        ],
        'es' => [
            "Booking Ref"    => "Código de reserva",
            "Departure"      => "Salida",
            "Arrival"        => "Llegada",
            "Terminal"       => "Terminal",
            "Equipment"      => "Tipo de avión",
            "Traveling time" => "Duración de vuelo",
            //			"Booking class" => "",
            //			"Meal" => "",
            "PASSENGERS" => "PASAJEROS",
            //          "MY FLIGHT" =>"",
            //			"Seat" => "",
            "Date of birth" => "Fecha de nacimiento",
            //			"Loyalty card" => "",
            "MY TOTAL"    => "TOTAL",
            "Fare"        => "Tarifa:",
            "Tax"         => "Tasas",
            "GRAND TOTAL" => "IMPORTE TOTAL",
        ],
        'lt' => [
            "Booking Ref"    => "Rezervacijos numeris",
            "Departure"      => "Išvykimas",
            "Arrival"        => "Atvykimas",
            "Terminal"       => "Terminalas",
            "Equipment"      => "Orlaivio tipas",
            "Traveling time" => "Skrydžio trukmė",
            "Booking class"  => "Rezervacijos klasė",
            "Meal"           => "Maitinimas",
            "PASSENGERS"     => "KELEIVIAI",
            //          "MY FLIGHT" =>"",
            //			"Seat" => "",
            "Date of birth" => "Gimimo data",
            //			"Loyalty card" => "",
            "MY TOTAL"    => "IŠ VISO",
            "Fare"        => "Tarifas:",
            "Tax"         => "Mokestis",
            "GRAND TOTAL" => "BENDRA SUMA",
        ],
        'sv' => [
            "Booking Ref"    => "Bokningsnummer",
            "Departure"      => "Avgång",
            "Arrival"        => "Ankomst",
            "Terminal"       => "Terminalen",
            "Equipment"      => "Typ av flygplan",
            "Traveling time" => "Varaktigheten av flygningen",
            //            "Booking class" => "",
            //            "Meal" => "",
            "PASSENGERS" => "PASSAGERARNA",
            //          "MY FLIGHT" =>"",
            //			"Seat" => "",
            "Date of birth" => "Födelsedatum",
            //			"Loyalty card" => "",
            "MY TOTAL"    => "MIN TOTALA",
            "Fare"        => "Pris:",
            "Tax"         => "Taxarna",
            "GRAND TOTAL" => "TOTALKOSTNAD",
        ],
    ];

    private $langDetect = [
        'en'  => ['PAYMENT SUCCESSFUL', 'BOOKING CONFIRMATION', 'Departure'],
        'ru'  => ['ОПЛАТА УСПЕШНАЯ', 'ПОДТВЕРЖДЕНИЕ БРОНИРОВАНИЯ'],
        'uk'  => ['ОПЛАТА УСПІШНА', 'ПІДТВЕРДЖЕННЯ БРОНЮВАННЯ'],
        'de'  => ['ZAHLUNG WURDE ERFOGREICH AUSGEFÜHRT', 'RESERVIERUNGSBESTÄTIGUNG'],
        'fr'  => ['PAIEMENT ACCEPTÉ'],
        'it'  => ['IL PAGAMENTO È STATO EFFETTUATO CON SUCCESSO', 'LA MIA PRENOTAZIONE'],
        'pl'  => ['PŁATNOŚĆ ZAKOŃCZYŁA SIĘ POWODZENIEM'],
        'es'  => ['PAGO REALIZADO SATISFACTORIAMENTE'],
        'lt'  => ['MOKĖJIMAS ATLIKTAS SĖKMINGAI'],
        'sv'  => ['BETALNING GENOMFÖRD!'],
        'he'  => ['‫ה ת ש ל ו ם ב ו צ ע ב ה צ ל ח ה!‬'],
    ];
    private $detectSubject = [
        ' of reservation',
    ];
    private $lang = '';
    private $providerCode = '';

    private $pdfNamePattern = '.*pdf';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text) || stripos($text, 'Payment Details') !== false) {
                continue;
            }

            $this->assignProvider($text, $parser->getHeaders());
            $this->assignLang($text);

            if ($this->lang === 'he') {
                $this->parseEmail_he($email, $text);
            } else {
                $this->parseEmail($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($this->providerCode !== 'panorama') {
            $email->setProviderCode($this->providerCode);
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignProvider($textPdf, $parser->getHeaders()) && $this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Ukraine International Airlines') === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'FlyUIA.com') !== false
            || stripos($from, '@flyuia.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['panorama', 'scoot'];
    }

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

    private function parseEmail(Email $email, string $text)
    {
        if ($this->strposAll($text, $this->t("Departure")) === false && $this->strposAll($text, $this->t("Arrival")) === false && $this->strposAll($text, $this->t("Traveling time")) === false) {
            return $email;
        }
        $f = $email->add()->flight();

        if (preg_match("#" . $this->preg_implode($this->t("Booking Ref")) . "\n*[: ]+([A-Z\d]{5,})\b#", $text, $m)) {
            $f->general()->confirmation($m[1]);
        } elseif (preg_match("#" . $this->preg_implode($this->t("Booking Ref")) . "\n*[: ]+.*\n\s*([A-Z\d]{5,})\b#", $text, $m)) {
            $f->general()->confirmation($m[1]);
        }

        // ticketNumbers
        // travellers
        $patterns['passengers'] = "#"
            . "^[ ]*(?<traveller>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$" // MR. FABIO MARONGIU
            . "(?:\s+^[ ]*{$this->preg_implode($this->t("Ticket number"))}[: ]+(?<tNo>\d{7,})(?:[ ]{2}.+|$))?" // Ticket number: 5662411985927
            . "\s+^[ ]*{$this->preg_implode($this->t("Date of birth"))}[: ]+" // Date of birth: 20.04.1981
            . "#mu";

        if (preg_match_all($patterns['passengers'], $text, $ticketMatches)) {
            $ticketNumbers = array_values(array_filter($ticketMatches['tNo']));

            if (count($ticketNumbers) > 0) {
                $f->setTicketNumbers(array_unique($ticketNumbers), false);
            }
            $f->general()->travellers(array_unique($ticketMatches['traveller']), true);
        } elseif (preg_match_all("/^(?:MISS|MRS|MR|MS|DR)[ ]+(?<traveller>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s/mu", $text, $ticketMatches)) {
            $f->general()->travellers(array_unique($ticketMatches['traveller']), true);
        }

        if (preg_match_all("#\s+(?:Panorama Club|Panorama Club CORPORATE|" . $this->preg_implode($this->t("Loyalty card")) . ")[: ]*(?:PS *)?(\d{5,})#", $text, $accountMatches)
            || preg_match_all("#\s+(?:Panorama Club|Panorama Club CORPORATE|" . $this->preg_implode($this->t("Loyalty card")) . ")[: ]*\n.{50,}[ ]{3,}(?:PS *)?(\d{5,})\n#", $text, $accountMatches)
        ) {
            $f->program()->accounts(array_unique($accountMatches[1]), false);
        }

        $passPos = mb_strpos($text, $this->t("PASSENGERS"));

        if (!empty($passPos)) {
            $flightsText = mb_substr($text, 0, $passPos);
        } else {
            $flightsText = $text;
        }
        $passPos = mb_strpos($flightsText, $this->t("MY FLIGHT"));

        if (!empty($passPos)) {
            $flightsText = mb_substr($flightsText, $passPos);
        }

        $segments = $this->split("#\n([ ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(?:\:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))?[ ]*\d{1,5}.*\s*\n(?:.*\n)?\s*" . $this->preg_implode($this->t("Departure")) . ")#u", $flightsText);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            $tableText = $this->re("#^([\s\S]+{$this->preg_implode($this->t("Arrival"))}[\s\S]+?)(?:\n.+\([A-Z]{3}\) - .+(?:\n.*)?\([A-Z]{3}\)|\n\n|$)#u", $stext);
            $tablePos = $this->TableHeadPos($this->inOneRow($tableText));
            $tablePos[0] = 0;

            if (preg_match("#^[ ]*{$this->preg_implode($this->t("Departure"))}[: ]{2,}{$this->preg_implode($this->t("Arrival"))}(?:[: ]+|$)#mu", $stext)) {
                $sType = 1; // it-?.eml
            } else {
                $sType = 2; // it-11432938.eml
            }

            if (
                $sType === 1
                && preg_match("#^(.+?[ ]{2}){$this->preg_implode($this->t("Arrival"))}(?:[: ]+|$)#mu", $tableText, $matches)
            ) {
                $tablePos[1] = mb_strlen($matches[1]);
            }

            if (preg_match("#^(.+?[ ]{2}){$this->preg_implode($this->t("Traveling time"))}#mu", $tableText, $matches)) {
                // Warning! Language 'de': Departure === Equipment
                $tablePos[$sType === 1 ? 2 : 1] = mb_strlen($matches[1]);
            }

            if (
                $sType === 2
                && (preg_match("#^(.+?[ ]{2}){$this->preg_implode($this->t("Booking class"))}#mu", $tableText, $matches)
                    || preg_match("#^(.+?[ ]{2}){$this->preg_implode($this->t("Meal"))}#mu", $tableText, $matches))
            ) {
                $tablePos[2] = mb_strlen($matches[1]);
            }

            $table = $this->SplitCols($tableText, $tablePos);

            if (isset($table[2]) && preg_match("/^[\n\s]+$/", $table[2])) {
                unset($table[2]);
            }

            if (count($table) !== 2 && count($table) !== 3) {
                $this->logger->notice("Segment table was parsed incorrectly! Segment: $stext");

                break;
            }

            // Airline
            if (preg_match("#\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(?:\:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])|[ ]{2,}[a-zA-Z ]+)?\s*(\d{1,5})\b#", $table[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $regexp = "[:\s]+(?<name>[\s\S]+?)\s*\((?<code>[A-Z]{3})\)(?:\s+(?<term>.*{$this->preg_implode($this->t("Terminal"))}.*))?\s*\n\s*(?<date>[\s\S]+?)";
            // Departure
            if (preg_match("#{$this->preg_implode($this->t("Departure"))}{$regexp}(?:\s+{$this->preg_implode($this->t("Arrival"))}[:\s]+|\s*$)#", $table[0], $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name(trim(preg_replace("#\s*\n\s*#", ' ', $m['name'])))
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(!empty($m['term']) ? trim(preg_replace("#\s*" . $this->preg_implode($this->t("Terminal")) . "[\s:]*#", '', $m['term'])) : null, true, true)
                ;
            }
            // Arrival
            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Arrival")) . $regexp . "(?:\n\n|$)#", $table[$sType === 1 ? 1 : 0], $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name(trim(preg_replace("#\s*\n\s*#", ' ', $m['name'])))
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(!empty($m['term']) ? trim(preg_replace("#\s*" . $this->preg_implode($this->t("Terminal")) . "[\s:]*#", '', $m['term'])) : null, true, true)
                ;
            }

            // Extra
            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Equipment")) . "[:\s]+([\s\S]+?)\s+" . $this->preg_implode($this->t("Traveling time")) . "#", $table[$sType === 1 ? 2 : 1], $m)) {
                $s->extra()
                    ->aircraft(trim(preg_replace("#\s*\n\s*#", ' ', $m[1])))
                ;
            }

            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Traveling time")) . "[:\s]+([\s\S]+?)(?:\n\n|$)#", $table[$sType === 1 ? 2 : 1], $m)) {
                $s->extra()
                    ->duration(trim(preg_replace("#\s*\n\s*#", ' ', $m[1])))
                ;
            }

            if ($sType === 2 && !empty($table[2])) {
                $table[2] = preg_replace("#\n\s*\n\s*\*[\s\S]+#", '', $table[2]);

                if (preg_match("#^\s*(.+(?:\n[^:]+)?)(\n\n|\n.+:)#", $table[2], $m)) {
                    $s->extra()
                        ->cabin(trim(preg_replace("#\s*\n\s*#", ' ', $m[1])))
                    ;
                }

                if (preg_match("#\n\s*" . $this->preg_implode($this->t("Booking class")) . "[:\s]+([A-Z]{1,2})\s+#", $table[2], $m)) {
                    $s->extra()
                        ->bookingCode($m[1])
                    ;
                }

                if (preg_match("#\n\s*" . $this->preg_implode($this->t("Meal")) . "[:\s]+(.+(?:\n[^:]+)?)(\n\n|\n.+:|$)#", $table[2], $m)) {
                    $s->extra()
                        ->meal(trim(preg_replace("#\s*\n\s*#", ' ', $m[1])))
                    ;
                }
            }

            // seats
            if (
                !empty($s->getDepCode()) && !empty($s->getArrCode()) && !empty($f->getTravellers())
                && preg_match_all("#^[ ]*(?:{$this->preg_implode($this->t("Seat"))}[: ]+)?{$s->getDepCode()}[ ]*-[ ]*{$s->getArrCode()}[ ]*,[ ]*(\d{1,5}[A-Z])(?:[ ]{2}|$)#m", $text, $seatMatches)
                && count($seatMatches[1]) <= count($f->getTravellers())
            ) {
                $s->extra()->seats($seatMatches[1]);
            }
        }

        // Price
        if (preg_match("#(?:^|\n)[ ]*{$this->preg_implode($this->t("GRAND TOTAL"))}[ ]{3,}([^\d\s]*)[ ]*(\d[,.\'\d ]+)\n#u", $text, $m)) {
            $f->price()
                ->total($this->amount($m[2]))
                ->currency($this->currency($m[1]))
            ;
        }
        $posTotal = strpos($text, $this->t("MY TOTAL"));

        if (!empty($posTotal)) {
            $totalText = substr($text, $posTotal);

            if (preg_match("#(?:^|\n)[ ]*" . $this->t("Fare") . "[ ]{3,}([^\d\s]*)[ ]*(\d[\d \.\,]+)\n#u", $totalText, $m)) {
                $f->price()
                    ->cost($this->amount($m[2]))
                    ->currency($this->currency($m[1]))
                ;
            } else {
                if (preg_match_all("#(?:^|\n)[ ]*" . $this->t("Fare") . "[ ]{3,}([^\d\s]*)[ ]*(\d[\d \.\,]+)\n#", substr($text, 0, $posTotal), $costMatches)) {
                    $f->price()
                        ->cost(array_sum(array_map([$this, 'amount'], $costMatches[2])))
                        ->currency($this->currency($costMatches[1][0]))
                    ;
                }
            }

            if (preg_match("#(?:^|\n)[ ]*" . $this->t("Tax") . "[ ]{3,}([^\d\s]*)[ ]*(\d[\d \.\,]+)\n#", $totalText, $m)) {
                $taxTotal = $this->amount($m[2]);
            }
        }

        $posTax = strpos($text, '>' . $this->t("Tax") . ' ');

        if (!empty($posTax) && !empty($posTotal)) {
            $it['Fees'] = [];
            $taxSum = 0.0;

            if (preg_match_all("#(?:^|\n)>" . $this->t("Tax") . "[ ]+.+([\s\S]+?)(\n.+\n\s*" . $this->preg_implode($this->t("Date of birth")) . "[: ]+|\n\n|$)#", substr($text, $posTax, $posTotal - $posTax), $mat)) {
                foreach ($mat[1] as $taxText) {
                    $rows = explode("\n", $taxText);

                    foreach ($rows as $key => $row) {
                        if (preg_match("#^[ ]*(?<name>.+?)(?<space>[ ]+)[^\d\s]{0,5}\s*(?<amount>\d[\d \.\,]+)$#", $row, $m)) {
                            if (isset($rows[$key + 1]) && preg_match("#^.*\D{2,}$#", $rows[$key + 1])
                                    && preg_match("#^[ ]*(\S+).*$#", $rows[$key + 1], $word)
                                    && (mb_strlen($word[1]) + 2 >= strlen($m['space'] || preg_match("#^[ ]*\(.+#", $rows[$key + 1], $word)))
                                    ) {
                                $m['name'] .= ' ' . trim($rows[$key + 1]);
                            }
                            $find = false;

                            foreach ($it['Fees'] as $i => $value) {
                                if ($value['Name'] == $m['name']) {
                                    $it['Fees'][$i]['Charge'] += $this->amount($m['amount']);
                                    $taxSum += $this->amount($m['amount']);
                                    $find = true;

                                    break;
                                }
                            }

                            if ($find == false) {
                                $it['Fees'][] = ['Name' => $m['name'], 'Charge' => $this->amount($m['amount'])];
                                $taxSum += $this->amount($m['amount']);
                            }
                        }
                    }
                }
            }

            if (!empty($taxTotal) && !empty($taxSum) && (strval($taxSum) == strval($taxTotal))) {
                foreach ($it['Fees'] as $value) {
                    $f->price()->fee($value['Name'], $value['Charge']);
                }
            }
        }

        return $email;
    }

    private function parseEmail_he(Email $email, string $text)
    {
        if ($this->lang === 'he') {
            $text = str_replace([html_entity_decode("&#8234;"), html_entity_decode("&#8235;"), html_entity_decode("&#8236;")], '', $text);
        }

        if ($this->strposAll($text, $this->t("Departure")) === false && $this->strposAll($text, $this->t("Arrival")) === false && $this->strposAll($text, $this->t("Traveling time")) === false) {
            return $email;
        }
        $f = $email->add()->flight();

        if (preg_match("#" . $this->preg_implode($this->t("Booking Ref")) . "([A-Z\d]{5,})[ ]*:#", $text, $m)) {
            $f->general()->confirmation($m[1]);
        }

        // ticketNumbers
        // travellers
        // TODO: no examples fo 'he' with TicketNumbers. next block need rewrite
        $patterns['passengers'] = "#"
            . "^[ ]*(?<traveller>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$" // MR. FABIO MARONGIU
            . "(?:\s+^[ ]*{$this->preg_implode($this->t("Ticket number"))}[: ]+(?<tNo>\d{7,})(?:[ ]{2}.+|$))?" // Ticket number: 5662411985927
            . "\s+^[ ]*.+?{$this->preg_implode($this->t("Date of birth"))}" // Date of birth: 20.04.1981
            . "#mu";

        if (preg_match_all($patterns['passengers'], $text, $travellerMatches)) {
            $ticketNumbers = array_values(array_filter($travellerMatches['tNo']));

            if (!empty($ticketNumbers[0])) {
                $f->setTicketNumbers($ticketNumbers, false);
            }
            $f->general()->travellers($travellerMatches['traveller'], true);
        }

        if (preg_match_all("#\s+(?:Panorama Club|Panorama Club CORPORATE|" . $this->preg_implode($this->t("Loyalty card")) . ")[: ]*(?:PS *)?(\d{5,})#", $text, $accountMatches)
            || preg_match_all("#\s+(?:Panorama Club|Panorama Club CORPORATE|" . $this->preg_implode($this->t("Loyalty card")) . ")[: ]*\n.{50,}[ ]{3,}(?:PS *)?(\d{5,})\n#", $text, $accountMatches)
        ) {
            $f->program()->accounts(array_unique($accountMatches[1]), false);
        }

        $passPos = mb_strpos($text, $this->t("PASSENGERS"));

        if (!empty($passPos)) {
            $flightsText = mb_substr($text, 0, $passPos);
        } else {
            $flightsText = $text;
        }
        $passPos = mb_strpos($flightsText, $this->t("MY FLIGHT"));

        if (!empty($passPos)) {
            $flightsText = mb_substr($flightsText, $passPos);
        }

        $segments = $this->split("#\n([ ]*(?:Economy[ ]*)?(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(?:\:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))?[ ]*\d{1,5}.*\s*\n(?:.*\n)?\s*.*?" . $this->preg_implode($this->t("Departure")) . ")#u", $flightsText);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            $tableText = $this->re("#^([\s\S]+{$this->preg_implode($this->t("Arrival"))}[\s\S]+?)\n\n(?:.+\)\s+\-\s+\([A-Z]{3}.+\s+.+\)\([A-Z]{3}|\s*$)#u", $stext);
            $tablePos = $this->TableHeadPos($this->inOneRow($tableText));
            $tablePos[0] = 0;

            if (preg_match("#^[ ]*{$this->preg_implode($this->t("Departure"))}[: ]{2,}{$this->preg_implode($this->t("Arrival"))}(?:[: ]+|$)#mu", $stext)) {
                $sType = 1; // it-?.eml
            } else {
                $sType = 2; // it-11432938.eml
            }

            if (
                $sType === 1
                && preg_match("#^(.+?[ ]{2}){$this->preg_implode($this->t("Arrival"))}(?:[: ]+|$)#mu", $tableText, $matches)
            ) {
                $tablePos[1] = mb_strlen($matches[1]);
            }

            if (preg_match("#^(.+?[ ]{2}){$this->preg_implode($this->t("Traveling time"))}#mu", $tableText, $matches)) {
                // Warning! Language 'de': Departure === Equipment
                $tablePos[$sType === 1 ? 2 : 1] = mb_strlen($matches[1]);
            }

            if (
                $sType === 2
                && (preg_match("#^(.+?[ ]{2}){$this->preg_implode($this->t("Booking class"))}#mu", $tableText, $matches)
                    || preg_match("#^(.+?[ ]{2}){$this->preg_implode($this->t("Meal"))}#mu", $tableText, $matches))
            ) {
                $tablePos[2] = mb_strlen($matches[1]);
            }
            $table = $this->SplitCols($tableText, $tablePos);

            if (count($table) !== 2 && count($table) !== 3) {
                $this->logger->notice("Segment table was parsed incorrectly! Segment: $stext");

                break;
            }

            //TODO: no email examples for sType = 1
            // Airline
            if (preg_match("#\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(?:\:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])|[ ]{2,}[a-zA-Z ]+)?\s*(\d{1,5})\b#", end($table), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $regexp = "[:\s]+(?<name>[\s\S]+?)\s*\)\s*\((?<code>[A-Z]{3})(?:\s+(?<term>.*{$this->preg_implode($this->t("Terminal"))}.*))?\s*\n\s*(?<date>[\s\S]+?)";
            // Departure
            if (preg_match("#{$this->preg_implode($this->t("Departure"))}{$regexp}(?:\s+{$this->preg_implode($this->t("Arrival"))}[:\s]+|\s*$)#", $table[2], $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name(trim(preg_replace("#\s*\n\s*#", ' ', $m['name'])))
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(!empty($m['term']) ? trim(preg_replace("#\s*" . $this->preg_implode($this->t("Terminal")) . "[\s:]*#", '', $m['term'])) : null, true, true)
                ;
            }
            // Arrival
            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Arrival")) . $regexp . "(?:\n\n|$)#", $table[$sType === 1 ? 1 : 2], $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name(trim(preg_replace("#\s*\n\s*#", ' ', $m['name'])))
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(!empty($m['term']) ? trim(preg_replace("#\s*" . $this->preg_implode($this->t("Terminal")) . "[\s:]*#", '', $m['term'])) : null, true, true)
                ;
            }

            // Extra
            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Equipment")) . "([\s\S]+?)[:\s]+" . $this->preg_implode($this->t("Traveling time")) . "#", $table[$sType === 1 ? 2 : 1], $m)) {
                $s->extra()
                    ->aircraft(trim(preg_replace("#\s*\n\s*#", ' ', $m[1])))
                ;
            }

            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Traveling time")) . "[:\s]*([\s\S]+?)(?:\n\n|$)#", $table[$sType === 1 ? 2 : 1], $m)) {
                $s->extra()
                    ->duration(trim(preg_replace("#\s*\n\s*#", ' ', $m[1])))
                ;
            }

            if ($sType === 2) {
                $table[2] = preg_replace("#\n\s*\n\s*\*[\s\S]+#", '', $table[0]);

                if (preg_match("#^\s*(.+(?:\n[^:]+)?)(\n\n|\n.+:)#", $table[0], $m)) {
                    $s->extra()
                        ->cabin(trim(preg_replace("#\s*\n\s*#", ' ', $m[1])))
                    ;
                }

                if (preg_match("#\n\s*" . $this->preg_implode($this->t("Booking class")) . "[:\s]+([A-Z]{1,2})\s+#", $table[0], $m)) {
                    $s->extra()
                        ->bookingCode($m[1])
                    ;
                }

                if (preg_match("#\n\s*" . $this->preg_implode($this->t("Meal")) . "[:\s]+(.+(?:\n[^:]+)?)(\n\n|\n.+:|$)#", $table[0], $m)) {
                    $s->extra()
                        ->meal(trim(preg_replace("#\s*\n\s*#", ' ', $m[1])))
                    ;
                }
            }

            // seats
            // no example - see main parse if there are
        }

        // Price
        if (preg_match("#^[ ]*(\d[\d\.,]+)[ ]*(\S+)[ ]+{$this->preg_implode($this->t("GRAND TOTAL"))}\s*$#mu", $text, $m)) {
            $f->price()
                ->total($this->amount($m[1]))
                ->currency($this->currency($m[2]))
            ;
        }
        $posTotal = strpos($text, $this->t("MY TOTAL"));

        if (!empty($posTotal)) {
            $totalText = substr($text, $posTotal);

            if (preg_match("#^[ ]*(\d[\d\.,]+)[ ]*(\S+)[ ]+" . $this->t("Fare") . "[: ]*$#mu", $totalText, $m)) {
                $f->price()
                    ->cost($this->amount($m[1]))
                    ->currency($this->currency($m[2]))
                ;
            }

            if (preg_match("#^[ ]*(\d[\d\.,]+)[ ]*(\S+)[ ]+" . $this->t("Tax") . "[: ]*$#mu", $totalText, $m)) {
                $taxTotal = $this->amount($m[1]);
                $f->price()->tax($taxTotal);
            }
        }

        return $email;
    }

    private function normalizeDate($date)
    {
        $in = [
            // 31 янв 2018 09:35 (местное время)
            '#^\s*(\d+)\s+(\w+)\.?\s+(\d+)\s+(\d+:\d+(?:\s*[ap]m)?)(?:\s*\(?.+\)?.*|\s*$)#isu',
            // '09יול ) 10:40 2019זמן מקומי('
            '#^\s*(\d+)\s*(.+?)\s*\)\s+(\d+:\d+)\s+(\d{4})\s*(?<localtime>\w+\s*\w+)\s*\(\s*$#isu',
        ];
        $out = [
            '$1 $2 $3 $4',
            '$1 $2 $4, $3',
        ];
        $date = preg_replace($in, $out, $date);
        $str = $this->dateStringToEnglish($date);

        return strtotime($str);
    }

    private function assignProvider(string $text, array $headers): bool
    {
        if (stripos($text, 'Scoot Booking Ref') !== false) {
            $this->providerCode = 'scoot';

            return true;
        }

        $phrases = [
            'Ukraine International Airlines', // en
            'Международные Авиалинии Украины', // ru
            'Міжнародні Авіалінії України', // uk
            'Międzynarodowe linie lotnicze Ukrainy', // pl
            'Ukrainos tarptautinės avialinijos', // lt
            'Ukraine Intl Airlines', // sv
            'חברת תעופה ‪ ,UIA', // he
        ];

        foreach ($phrases as $phrase) {
            if (stripos($text, $phrase) !== false) {
                $this->providerCode = 'panorama';

                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(string $body): bool
    {
        foreach ($this->langDetect as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($body, $phrase) !== false) {
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", trim($s))));
    }

    private function currency($s)
    {
        $sym = [
            '₣'  => 'DJF',
            'kr' => 'SEK',
            'KR' => 'SEK',
            'Ft' => 'HUF',
            'FT' => 'HUF',
            '₴'  => 'UAH',
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            'zł' => 'PLN',
            'ZŁ' => 'PLN',
            'Kč' => 'CZK',
            'KČ' => 'CZK',
            '₺'  => 'TRY',
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function strposAll($haystack, $needles)
    {
        if (is_string($needles)) {
            return strpos($haystack, $needles);
        }

        if (is_array($needles)) {
            foreach ($needles as $needle) {
                if (strpos($haystack, $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
