<?php

namespace AwardWallet\Engine\vueling\Email;

class ATicket extends \TAccountChecker
{
    public $mailFiles = "vueling/it-1.eml, vueling/it-12233584.eml, vueling/it-12233774.eml, vueling/it-1413738.eml, vueling/it-1739923.eml, vueling/it-1739925.eml, vueling/it-1839497.eml, vueling/it-1931831.eml, vueling/it-2.eml, vueling/it-2420787.eml, vueling/it-2913147.eml, vueling/it-3.eml, vueling/it-4102439.eml, vueling/it-4108530.eml, vueling/it-4248689.eml, vueling/it-4310517.eml, vueling/it-4330774.eml, vueling/it-4431752.eml, vueling/it-5325078.eml, vueling/it-5573419.eml, vueling/it-5605268.eml, vueling/it-9041312.eml";

    public $reSubject = [
        'es' => [
            '#Vueling\s*/\s*Información\s+de\s+tu\s+reserva#',
            '#Información\s*sobre\s*pasajeros\s*residentes#',
        ],
        'it' => ['#Vueling\s*/\s*Informazioni\s+sulla\s+prenotazione#'],
        'fr' => ['#Vueling\s*/\s*Informations\s+sur\s+votre\s+réservation#'],
        'de' => [
            '#Vueling\s*/\s*Information\s+zu\s+Ihrer\s+Buchung#',
            '#Ihr\s+nächster\s+Flug#',
        ],
        'pt' => ['#Vueling\s*/\s*Informação\s+da\s+tua\s+reserva#'],
        'ru' => ['#Vueling\s*/\s*Информация\s+о\s+ваших\s+авиабилетах#'],
        'gl' => ['#Vueling\s*/\s*Información\s+da\s+túa\s+reserva#'],
        'nl' => ['#Informatie\s+Vueling\s*/\s*Informatie\s+over\s+je\s+reservering#'],
        'ca' => ['#Vueling\s+/\s+Informació\s+de\s+la\s+teva\s+reserva#'],
        'en' => [
            '#Vueling\s*/\s*Information\s+on\s+your\s+booking#',
            '#Information\s+on\s+resident\s+passengers#',
        ],
    ];

    public $lang = '';

    public $reBody = [
        'es'  => ['Detalles del vuelo', 'Vuelo'],
        'es2' => ['Código de reserva', 'Vuelo'],
        'ru'  => ['Код бронирования', 'Рейс'],
        'de'  => ['Buchungscode', 'Flug'],
        'it'  => ['Codice prenotazione:', 'Volo'],
        'fr'  => ['Code de réservation', 'Vol'],
        'pt'  => ['Passageiro', 'Voo'],
        'gl'  => ['Pasaxeiro', 'Voo'],
        'nl'  => ['Reserveringscode', 'Vlucht'],
        'ca'  => ['Detalls de la reserva', 'Vol'],
        'en'  => ['Booking code', 'Flight'],
        'en2' => ['Confirmation number', 'Flight'],
    ];

    public static $dict = [
        'pt' => [
            'Confirmation number' => 'Código de reserva',
            'Passengers'          => 'Passageiro',
            'Total'               => 'Preço Total',
            'Flight'              => 'Voo',
            'Seats'               => ['Assento de ida', 'Assento de volta'],
        ],
        'gl' => [
            'Confirmation number' => 'Código de reserva',
            'Passengers'          => 'Pasaxeiro',
            'Total'               => 'Prezo total',
            'Flight'              => 'Voo',
            'Seats'               => ['Asento ida', 'Asento volta'],
        ],
        'ru' => [
            'Confirmation number' => 'Код бронирования',
            'Passengers'          => 'Пассажир',
            'Total'               => 'Общая стоимость',
            'Flight'              => 'Рейс',
            'Seats'               => ['Место при перелете туда', 'Место при перелете обратно'],
        ],
        'de' => [
            'Confirmation number' => 'Buchungscode',
            'Passengers'          => 'Passagier',
            'Total'               => 'Gesamtpreis',
            'Flight'              => 'Flug',
            'Seats'               => ['Sitzplatz Hinflug', 'Sitzplatz Rückflug'],
        ],
        'it' => [
            'Confirmation number' => 'Codice prenotazione:',
            'Passengers'          => 'Passeggero',
            'Total'               => 'Prezzo totale',
            'Flight'              => 'Volo',
            'Seats'               => ['Posto andata', 'Posto ritorno'],
        ],
        'fr' => [
            'Confirmation number' => 'Code de réservation',
            'Passengers'          => 'Passager',
            'Total'               => 'Prix total',
            'Flight'              => 'Vol',
            'Seats'               => ['Place aller', 'Place retour'],
        ],
        'es' => [
            'Confirmation number' => ['Código de reserva', 'digo de reserva'],
            'Passengers'          => 'Pasajero',
            'Total'               => 'Precio Total:',
            'Flight'              => 'Vuelo',
            'Seats'               => ['Asiento ida', 'Asiento vuelta'],
        ],
        'nl' => [
            'Confirmation number' => 'Reserveringscode',
            'Passengers'          => 'Passagier',
            'Total'               => 'Totaalprijs:',
            'Flight'              => 'Vlucht',
            'Seats'               => ['Stoel heen', 'Stoel terug'],
        ],
        'ca' => [
            'Confirmation number' => 'Codi de reserva:',
            'Passengers'          => 'NOTTRANSLATED',
            'Total'               => 'NOTTRANSLATED',
            'Flight'              => 'Vol',
            'Seats'               => "NOTTRANSLATED",
        ],
        'en' => [
            'Confirmation number' => ['Booking code', 'Confirmation number'],
            'Passengers'          => 'Traveller',
            'Total'               => ['Total Cost:', 'Prix total:'],
            'Flight'              => 'Flight',
            'Seats'               => ['Outbound seat', 'Return seat'],
        ],
    ];

    private $seg = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $its = $this->parseEmail();

        return [
            'emailType'  => 'ATicket_' . $this->lang,
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[contains(text(),'Vueling') or contains(text(),'vueling')]")->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (preg_match($phrase, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Vueling Airlines') !== false
            || stripos($from, '@vueling.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Confirmation number'))}]/following::text()[normalize-space(.)!=''][1])[1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");
        $it['Passengers'] = array_filter($this->http->FindNodes("//tr[starts-with(normalize-space(.), '" . $this->t('Passengers') . "')]/following-sibling::tr[normalize-space(.)!='' and count(td)=3]/td[1]"));
        $total = $this->http->FindSingleNode("//*[name() = 'span' or name() = 'font'][{$this->starts($this->t('Total'))}]/following::*[normalize-space(.)!=''][1]");

        if (preg_match("#(\d[\d\.,\s]*\d*)\s+(.+)#", $total, $math)) {
            $it['TotalCharge'] = cost($math[1]);
            $it['Currency'] = currency($math[2]);
        }
        //get seats
        $xpath = "//text()[{$this->eq($this->t('Seats'))}]/ancestor::tr[2]/following-sibling::tr";
        $passengerInfoRows = $this->http->XPath->query($xpath);
        $seats = [];

        foreach ($passengerInfoRows as $sn) {
            $passengerSeats = $this->http->FindNodes('./td[position() > 1]', $sn);
            $i = 0;

            foreach ($passengerSeats as $s) {
                if (preg_match('#(\d+\w+)#', $s, $m)) {
                    $seats[$i][] = $m[1];
                }
                $i++;

                if (preg_match('#^\d+\w+\s*\/\s*(\d+\w+)#', $s, $m)) {
                    $seats[$i][] = $m[1];
                    $i++;
                }
            }
        }

        $xpath = "//img[contains(@src, 'Avion') or contains(@src, 'avion')]/following::text()[({$this->starts($this->t('Flight'))}) and not(contains(normalize-space(.), 'umbuch'))]";
        $roots = $this->http->XPath->query($xpath);
        $type = 1;

        if ($roots->length === 0) {
            $xpath = "//text()[{$this->contains($this->t('ONE WAY'))} or {$this->contains($this->t('RETURN'))}]/ancestor::*[1]/following::*[{$this->contains($this->t('Flight'))}]";
            $roots = $this->http->XPath->query($xpath);
            $type = 2; // for bcd
        }

        if ($roots->length === 0) {
            $this->logger->info('Segments not found: ' . $xpath);

            return false;
        }

        if ($type === 1) {
            $i = -1;

            foreach ($roots as $root) {
                $this->seg = [];

                if (!preg_match('/[A-Z]{2}\s*\d+/', $root->nodeValue)) {
                    continue;
                }
                $i++;
                $this->parseFlight($this->http->FindSingleNode('.', $root));

                $date = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[normalize-space(.)!=''][1]/ancestor::tr[1]", $root));
                $depName = $this->processingCodes($this->http->FindSingleNode("following::text()[normalize-space(.)!=''][1]/ancestor::tr[1]", $root));
                $this->departureSegment($depName, $date);

                $arrXPath = "following::*[1]/descendant::tr[2]";
                $arrName = $this->processingCodes($this->http->FindSingleNode($arrXPath, $root));

                if (empty($arrName) && $this->http->FindSingleNode($arrXPath) === null) {
                    $arrName = $this->processingCodes($this->http->FindSingleNode("following::text()[normalize-space(.)!=''][1]/ancestor::tr[1]/following::tr[1]", $root));
                }

                if (empty($arrName) && $this->http->FindSingleNode($arrXPath) === null) {
                    $arrName = $this->processingCodes($this->http->FindSingleNode("following::tr[2]", $root));
                }
                $this->arrivalSegment($arrName, $date);

                if (isset($seats[$i]) and !empty($seats[$i])) {
                    $this->seg['Seats'] = $seats[$i];
                }

                $it['TripSegments'][] = $this->seg;
            }
        } elseif ($type === 2) {
            foreach ($roots as $root) {
                $this->parseFlight($this->http->FindSingleNode('.', $root));

                $date = $this->normalizeDate($this->http->FindSingleNode('preceding::*[normalize-space(.)!=""][1]', $root));
                $depName = $this->http->FindNodes('following::font[normalize-space(.)!=""][position() < 4]', $root);
                $depName = implode(' ', $depName);
                $depName = $this->processingCodes($depName);
                $this->departureSegment($depName, $date);

                $arrName = $this->http->FindNodes('following::font[normalize-space(.)!=""][position() > 3 and position() < 7]', $root);
                $arrName = implode(' ', $arrName);
                $arrName = $this->processingCodes($arrName);
                $this->arrivalSegment($arrName, $date);

                $it['TripSegments'][] = $this->seg;
            }
        }

        return [$it];
    }

    private function departureSegment($depName, $date)
    {
        if (count($depName) >= 3 && !empty($date)) {
            $this->seg['DepName'] = $depName['Name'];
            $this->seg['DepCode'] = $depName['Code'];

            if (($terminalDep = trim($depName['Terminal'])) !== '') {
                $this->seg['DepartureTerminal'] = $terminalDep;
            }
            $this->seg['DepDate'] = strtotime($date . ', ' . $depName['Time']);
        }
    }

    private function arrivalSegment($arrName, $date)
    {
        if (count($arrName) >= 3 && !empty($date)) {
            $this->seg['ArrName'] = $arrName['Name'];
            $this->seg['ArrCode'] = $arrName['Code'];

            if (($terminalArr = trim($arrName['Terminal'])) !== '') {
                $this->seg['ArrivalTerminal'] = $terminalArr;
            }
            $this->seg['ArrDate'] = strtotime($date . ', ' . $arrName['Time']);
        }
    }

    private function parseFlight($flightNum)
    {
        if (preg_match("#\w*\s+(\w{2})\s*(\d+)#", $flightNum, $m)) {
            $this->seg['AirlineName'] = $m[1];
            $this->seg['FlightNumber'] = (int) $m[2];
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            "#.+\s+(\d{2})\s*[indae]*\s+(\w+)\s+(\d{4})#ui", // u - for 'ru' lang
            '#^([^\d\s]+)\s+(\d+)\s+(\d{4})$#',
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match('/\d{1,2}\s+([^\d\s]{3,})\s+\d{4}/', $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } else { // it-9041312.eml
                $remainingLangs = array_diff(array_keys(self::$dict), [$this->lang]);

                foreach ($remainingLangs as $lang) {
                    if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $lang)) {
                        $str = str_replace($m[1], $en, $str);

                        break;
                    }
                }
            }
        }

        return $str;
    }

    private function processingCodes($str)
    {
        // Majorca (PMI): 22:05
        // Moscow (Domodedovo) (DME): ТI 03:30
        if (preg_match("#(.*?)\s*\(\s*([A-Z]{3}\s*)\):\s*(?:.*?)?(\w{1,2}\s+)?(\d+:\d+)#u", $str, $m)) {
            return [
                'Name'     => $m[1],
                'Code'     => ($m[2]) ? $m[2] : TRIP_CODE_UNKNOWN,
                'Terminal' => ($m[3]) ? $m[3] : null,
                'Time'     => $m[4],
            ];
        }

        return $str;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $phrases) {
            $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrases[0] . '")]')->length > 0;
            $condition2 = $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrases[1] . '")]')->length > 0;

            if ($condition1 && $condition2) {
                $this->lang = substr($lang, 0, 2);

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
