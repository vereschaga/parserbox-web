<?php

namespace AwardWallet\Engine\skywards\Email;

use AwardWallet\Engine\MonthTranslate;

class It1 extends \TAccountChecker
{
    public $mailFiles = "skywards/It-5.eml, skywards/it-1.eml, skywards/it-10.eml, skywards/it-11.eml, skywards/it-12234069.eml, skywards/it-1730831.eml, skywards/it-1828277.eml, skywards/it-1846018.eml, skywards/it-3.eml, skywards/it-4.eml, skywards/it-4234699.eml, skywards/it-4291639.eml, skywards/it-4292304.eml, skywards/it-4304535.eml, skywards/it-4345051.eml, skywards/it-4364590.eml, skywards/it-4396277.eml, skywards/it-4399512.eml, skywards/it-4420990.eml, skywards/it-4439922.eml, skywards/it-4603889.eml, skywards/it-4684753.eml, skywards/it-4694105.eml, skywards/it-4895844.eml, skywards/it-4909451.eml, skywards/it-5380646.eml, skywards/it-5456866.eml, skywards/it-5523122.eml, skywards/it-5636440.eml, skywards/it-5660687.eml, skywards/it-5660745.eml, skywards/it-6163919.eml, skywards/it-6180446.eml, skywards/it-7.eml, skywards/it-8310312.eml, skywards/it-8324142.eml";

    protected $lang = '';
    protected $subject;
    protected $detectSubject = [
        'en'  => 'Emirates Itinerary',
        'en2' => 'Emirates travel itinerary',
        'en3' => 'Booking Pending',
        'en4' => 'Your itinerary',
        'en5' => 'Incomplete Booking',
        'en6' => 'Booking confirmation',
        'da'  => 'Emirates-rejseplan',
        'it'  => 'itinerario Emirates',
        'fr'  => 'itinéraire Emirates',
        'pt'  => 'itinerário Emirates',
        'de'  => 'Emirates Reiseplan',
        'zh'  => '你的阿聯酋航空行程',
        'ru'  => 'Ваш маршрут Эмирейтс',
    ];

    protected $detectBody = [
        'en'   => 'Thank you for making your booking on-line with Emirates',
        'en2'  => 'This email has been sent to you by Emirates',
        'en3'  => 'for your nearest Emirates office',
        'en4'  => ['Your Emirates Itinerary', 'You have been sent this itinerary from'],
        'en5'  => ['ve held your fare', 'The Emirates team'],
        'en6'  => ['please contact your local Emirates office', 'Arrive'],
        'en7'  => ['Thank you for booking online with', 'emirates.com'],
        'en8'  => ['Your card payment is currently being reviewed', 'emirates.com'],
        'en9'  => 'Thank you for booking with Emirates',
        'en10' => 'view the contact details for your local Emirates office',
        'da'   => 'dit nærmeste Emirates kontor',
        'da2'  => ['Bookingbekræftelse', 'fordi du foretog din booking online på Emirates'],
        'es'   => 'sujeto a las condiciones de transporte de Emirates',
        'es2'  => ['Hemos guardado su tarifa', 'El equipo de Emirates'],
        'es3'  => 'Gracias por hacer su reserva a través de la página web Emirates',
        'es4'  => ' Su itinerario de Emirates',
        'fr'   => ['Ceci est un message automatique', 'Emirates'],
        'fr2'  => ['Booking Confirmation', "coordonnées de l'agence Emirates la plus proche"],
        'fr3'  => "Merci d'avoir réservé votre voyage en ligne sur Emirates",
        'pt'   => ['Observe que o transporte de todos os passageiros e bagagens', 'Emirates'],
        'pt2'  => 'Obrigado por fazer a reserva on-line no Emirates',
        'de'   => ['Dieser Reiseplan wurde Ihnen von', 'Ihre Reise mit Emirates'],
        'de2'  => 'Vielen Dank für Ihre Buchung auf Emirates',
        'zh'   => '所有旅客和行李的承運應遵循阿聯酋航空承運條款規定',
        'zh2'  => '請你查看離你最近的阿聯酋航空辦事處的',
        'zh3'  => '多謝您使用 Emirates',
        'zh4'  => '多謝您在 Emirates.com 預訂航班',
        'ru'   => ['Подтверждение бронирования', 'вы можете обратиться в местный офис Эмирейтс'],
        'ru2'  => ['Подтверждение бронирования', 'вы являетесь участником программы Эмирейтс Skywards'],
        'ru3'  => ['Ваш маршрут Эмирейтс', 'данные местного офиса Эмирейтс'],
        'ru4'  => ['Подтверждение внесенные изменений', 'офиса Эмирейтс'],
        'it'   => 'Grazie per aver effettuato la prenotazione online con emirates',
        'it2'  => ['vostro itinerario Emirates', 'Ti auguriamo buon viaggio'],
        'th'   => 'ขอบคุณที่ดำเนินการจองผ่านระบบออนไลน์กับ Emirates',
        'ja'   => 'Emirates.comのオンライン予約をご利用いただきありがとうございます',
        'cs'   => 'Děkujeme za provedení Vaší rezervace online na webu emirates',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@emirates.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@emirates.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $detect) {
            if (stripos($headers['subject'], $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $textBody = empty($parser->getPlainBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();
        $pdfs = $parser->searchAttachmentByName('EmiratesE.*\.pdf'); //EmiratesEtiket to TicketPdf.php or ETicketPdf.php

        if (0 < count($pdfs)) {
            return false;
        }

        foreach ($this->detectBody as $key => $detect) {
            if (is_array($detect) && count($detect) === 2) {
                if (stripos($textBody, $detect[0]) !== false && stripos($textBody, $detect[1]) !== false) {
                    $this->lang = substr($key, 0, 2);

                    return true;
                }
            }

            if (is_string($detect) && stripos($textBody, $detect) !== false) {
                $this->lang = substr($key, 0, 2);

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'https://www.boxbe.com/')]")->length > 0) {
            // many letters with information in html-attachments (www.boxbe.com)
            $htmls = implode("\n", $this->getHtmlAttachments($parser));
            $this->http->SetEmailBody($htmls);
        }

        $textBody = empty($parser->getPlainBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();
        $pdfs = $parser->searchAttachmentByName('EmiratesE.*\.pdf'); //EmiratesEtiket to TicketPdf.php or ETicketPdf.php

        if (0 < count($pdfs)) {
            $this->logger->debug('Email contains pdf. Should parse it');
            return false;
        }

        foreach ($this->detectBody as $key => $detect) {
            if (is_array($detect) && count($detect) === 2) {
                if (stripos($textBody, $detect[0]) !== false && stripos($textBody, $detect[1]) !== false) {
                    $this->lang = substr($key, 0, 2);

                    break;
                }
            }

            if (is_string($detect) && stripos($textBody, $detect) !== false) {
                $this->lang = substr($key, 0, 2);

                break;
            }
        }

        return $this->ParseEmail();
    }

    public static function getEmailLanguages()
    {
        return ['en', 'fr', 'es', 'da', 'pt', 'de', 'zh', 'ru', 'it', 'th', 'ja', 'cs'];
    }

    public static function getEmailTypesCount()
    {
        return 12;
    }

    protected function priceNormalize($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function normalizeDate($string)
    {
        $day = $month = $year = null;

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        } elseif (preg_match('/(\d{1,2})[.\s]+([^\-\d\s]{3,})[.\s]+(\d{2})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        } elseif (preg_match('/(\d{1,2})[.\s]+([^\-\d\s]{3,})[.\s]+(\d{4})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/(\d{1,2})([^-\d\s]{3,})(\d{2})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        } elseif (preg_match('/(\d{2})[^\d]+(\d{1,2})[^\d]+(\d{1,2})[^\d]+$/u', $string, $matches)) { // zh, ja
            $year = '20' . $matches[1];
            $month = $matches[2];
            $day = $matches[3];
        } elseif (preg_match('/(\d{1,2})-([^-\d\s]{3,})-(\d{2})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        }

        if (!empty($day) && !empty($month) && !empty($year)) {
            if (preg_match('/^\s*\d{1,2}\s*$/', $month)) {
                return $day . '.' . $month . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }

    protected function ParseEmail()
    {
        $dict = [
            'BookingReference' => [
                'en'  => 'normalize-space(.)="Booking reference"',
                'en2' => 'normalize-space(.)="Booking Reference"',
                'en3' => 'normalize-space(.)="BOOKING REFERENCE"',
                'en4' => 'normalize-space(.)="Bookingreference"',
                'da'  => 'normalize-space(.)="Reservationsreference"',
                'fr'  => 'normalize-space(.)="Réservation"',
                'fr2' => 'normalize-space(.)="Référence de la réservation"',
                'pt'  => 'normalize-space(.)="Código da Reserva"',
                'pt2' => 'normalize-space(.)="Referência de reserva"',
                'es'  => 'normalize-space(.)="Código de reserva"',
                'es2' => 'normalize-space(.)="Código de referencia de la reserva"',
                'es3' => 'normalize-space(.)="Referencia de la reserva"',
                'es4' => 'normalize-space(.)="Referencia de reserva"',
                'de'  => 'normalize-space(.)="Buchungsnummer"',
                'zh'  => 'normalize-space(.)="預訂參考編號"',
                'ru'  => 'normalize-space(.)="Код бронирования"',
                'it'  => 'normalize-space(.)="Codice di prenotazione"',
                'it2' => 'normalize-space(.)="Codice Prenotazione"',
                'ja'  => 'normalize-space(.)="ご予約番号"',
                'cs'  => 'normalize-space(.)="Rezervační kód"',
            ],
            'Terminal' => [
                'en' => 'normalize-space(.)="Terminal"',
                'zh' => 'normalize-space(.)="客運大樓"',
                'ru' => 'normalize-space(.)="Терминал"',
            ],
            'Flight' => [
                'en' => 'normalize-space(.)="Flight"',
                'da' => 'normalize-space(.)="Flyrejse"',
                'fr' => 'normalize-space(.)="Vol"',
                'es' => '(normalize-space(.)="Vuelo" or normalize-space(.)="VUELO")',
                'pt' => 'normalize-space(.)="Voo"',
                'de' => 'normalize-space(.)="Flug"',
                'zh' => 'contains(.,"停站")',
                'ru' => 'normalize-space(.)="Рейс"',
                'it' => 'normalize-space(.)="Volo"',
                'th' => 'normalize-space(.)="เที่ยวบิน"',
                'ja' => 'normalize-space(.)="フライト"',
                'cs' => 'normalize-space(.)="Let"',
            ],
            'Duration' => [
                '\d{1,2}\s*(?:hr|hrs|h|t|小時|std\.|ч|ชม.|時間|hod)\s*\d{1,2}\s*(?:min|分鐘|мин|นาที|分)',
            ],
            'Stops' => [
                'en' => 'stop',
                'da' => 'Mellemlandinger',
                'es' => 'Escalas|Paradas',
                'fr' => 'Escales',
                'de' => 'Zwischenstopps',
                'zh' => '停站',
                'ru' => 'Остановки',
                'it' => 'Scali',
                'th' => 'จุดจอด',
                'ja' => '経由地',
                'cs' => 'Mezipřistání',
            ],
            'Passengers' => [
                'en' => 'contains(.,"Passengers")',
                'da' => 'contains(.,"Passagerer")',
                'es' => 'contains(.,"Pasajeros")',
                'pt' => 'contains(.,"Passageiros")',
                'fr' => 'contains(.,"Passagers")',
                'de' => 'contains(.,"Passagiere")',
                'zh' => 'contains(.,"旅客")',
                'ru' => '(contains(.,"Пассажиры") or contains(.,"Сведения о пассажире"))',
                'it' => 'contains(.,"Passeggeri")',
                'th' => 'contains(.,"ผู้โดยสาร")',
                'ja' => 'contains(.,"搭乗者")',
                'cs' => 'contains(.,"Cestující")',
            ],
            'PassengerRow' => [
                'en' => 'starts-with(normalize-space(.),"Passenger")',
                'es' => 'starts-with(normalize-space(.),"Pasajero")',
                'pt' => 'starts-with(normalize-space(.),"Passageiro")',
                'fr' => 'starts-with(normalize-space(.),"Passager")',
                'de' => 'starts-with(normalize-space(.),"Passagier")',
                'zh' => 'starts-with(normalize-space(.),"旅客")',
                'ru' => 'starts-with(normalize-space(.),"Пассажир")',
                'it' => 'starts-with(normalize-space(.),"Passeggero")',
                'th' => 'starts-with(normalize-space(.),"ผู้โดยสาร")',
                'ja' => 'starts-with(normalize-space(.),"搭乗者")',
                'cs' => 'starts-with(normalize-space(.),"Cestující")',
            ],
            'PassengerDetails' => [
                'en' => '( .//text()[normalize-space(.)="Flight number" or normalize-space(.)="Flight" or normalize-space(.)="Flight Number"] and .//text()[normalize-space(.)="Route"] )',
                'da' => '( .//text()[normalize-space(.)="Flynummer"] and .//text()[normalize-space(.)="Rute"] )',
                'es' => '( .//text()[normalize-space(.)="Número de vuelo"] and .//text()[normalize-space(.)="Ruta"] )',
                'fr' => '( .//text()[normalize-space(.)="Numéro du vol"] and .//text()[normalize-space(.)="Itinéraire"] )',
                'pt' => '( .//text()[normalize-space(.)="Número do voo"] and .//text()[normalize-space(.)="Rota"] )',
                'de' => '( .//text()[normalize-space(.)="Flugnummer"] and .//text()[normalize-space(.)="Strecke"] )',
                'zh' => '( .//text()[normalize-space(.)="航班編號"] and .//text()[normalize-space(.)="航線"] )',
                'ru' => '( .//text()[normalize-space(.)="Номер рейса"] and .//text()[normalize-space(.)="Маршрут"] )',
                'it' => '( .//text()[normalize-space(.)="Numero di volo" or normalize-space(.)="Volo n."] and .//text()[normalize-space(.)="Rotta"] )',
            ],
            'Payment' => [
                'en' => '(starts-with(normalize-space(.),"Total price") or starts-with(normalize-space(.),"Total"))',
                'da' => 'starts-with(normalize-space(.),"Samlet pris")',
                'es' => 'starts-with(normalize-space(.),"Precio total")',
                'pt' => 'starts-with(normalize-space(.),"Preço total")',
                'fr' => 'starts-with(normalize-space(.),"Prix total")',
                'de' => 'starts-with(normalize-space(.),"Gesamtpreis")',
                'zh' => '(starts-with(normalize-space(.),"總價") or starts-with(normalize-space(.),"總計"))',
                'ru' => '(starts-with(normalize-space(.),"Общая стоимость") or starts-with(normalize-space(.),"Всего"))',
                'it' => 'starts-with(normalize-space(.),"Prezzo totale")',
                'th' => 'starts-with(normalize-space(.),"ราคารวมทั้งหมด")',
                'ja' => 'starts-with(normalize-space(.),"総額")',
                'cs' => 'starts-with(normalize-space(.),"Celková cena")',
            ],
        ];
        $patterns = [
            'date'        => '/(\d{1,2}[.\s]*[^\d\s\.]{3,}[.\s]*\d{2,4}|\d{2}[^\d]+\d{1,2}[^\d]+\d{1,2}[^\d]+|\d{1,2}[.]\d{2}[.]\d{2})\s*$/',
            'time'        => '/\d{1,2}:\d{2}/',
            'nameAndCode' => '/^(.+)\s+\(([A-Z]{3})\)\s*$/',
            'passenger'   => '/^\s*(?:Passenger|Pasajero|Passageiro|Passager|Passagier|旅客|Пассажир|Passeggero|ผู้โดยสาร|搭乗者|Cestující)\s+(?:-\s+\d+|\d+\s+-)\s+([^({]+)/i',
        ];

        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode('//td[.//text()[' . implode(' or ', $dict['BookingReference']) . '] and count(.//text()[string-length(normalize-space(.))>4])>1 and not(.//td)]/descendant::*[string-length(normalize-space(.))>4 and position()>1][last()]', null, true, '/[A-Z\d]{5,7}/');
        if (!$it['RecordLocator']) {
            $it['RecordLocator'] = $this->http->FindSingleNode('//tr[.//text()[' . implode(' or ', $dict['BookingReference']) . '] and not(.//tr)]/following-sibling::tr/td[string-length(normalize-space(.))>4 and string-length(normalize-space(.))<8][1]', null, true, '/[A-Z\d]{5,7}/');
        }
        if (!$it['RecordLocator']) {
            $it['RecordLocator'] = $this->http->FindSingleNode('//text()[' . implode(' or ', $dict['BookingReference']) . ']/following::text()[normalize-space(.)][1]', null, true, '/^\s*[A-Z\d]{5,7}\s*$/');
        }
        if (!$it['RecordLocator']) {
            $it['RecordLocator'] = $this->http->FindSingleNode('//td[./ancestor::tr[1]/preceding-sibling::tr[.//img[contains(@src,"Booking_reference")] and position()=1] and count(.//text()[string-length(normalize-space(.))>4])>1 and not(.//td)]/descendant::*[string-length(normalize-space(.))>4 and position()>1][last()]',
                null, true, '/[A-Z\d]{5,7}/');
        }

        if ($this->http->XPath->query('//node()[contains(.,"Incomplete Booking") and contains(.,"Sorry, your online transaction has been declined")]')->length > 0) {
            if (!$it['RecordLocator']) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }
            $it['Status'] = 'Declined';
        } elseif ($this->http->XPath->query('//node()[contains(.,"modification to your booking is incomplete") and contains(.,"please contact your local Emirates office to complete the process") and not(.//*)]')->length > 0) {
            if (!$it['RecordLocator']) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }
            $it['Status'] = 'modification to your booking is incomplete - please contact your local Emirates office to complete the process';
        } elseif ($this->http->XPath->query('//node()[contains(.,"fare for your booking is on hold and guarante")]')->length > 0) {
            if (!$it['RecordLocator']) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }
            $it['Status'] = 'Hold';
        } elseif ($this->http->XPath->query('//node()[contains(.,"your purchase is complete and your booking has been changed")]')->length > 0) {
            if (!$it['RecordLocator']) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }
            $it['Status'] = 'Changed';
        } elseif ($this->http->XPath->query('//node()[contains(normalize-space(.),"Sorry, seat availability has changed")]')->length > 0) {
            if (!$it['RecordLocator']) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }
            $it['Status'] = 'Changed';
        }

        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//text()[' . implode(' or ', $dict['Flight']) . ']/ancestor::tr[./*[6]][1]/following-sibling::tr[./*[6] and ./td[3]/descendant::text()[contains(.,":") and string-length(normalize-space(.))>3]]');

        foreach ($segments as $segment) {
            $seg = [];
            $flight = $this->http->FindSingleNode('./td[1]', $segment);

            if (preg_match('/(([A-Z\d]{2})(\d+))/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[2];
                $seg['FlightNumber'] = $matches[3];
                $mealCells = array_unique($this->http->FindNodes('//tr[./td[normalize-space(.)="' . $matches[1] . '" and position()=1] and ./preceding::tr[' . implode(' or ', $dict['PassengerDetails']) . '] and not(.//tr)]/td[3]'));
                $mealValues = array_values(array_filter($mealCells));

                if (!empty($mealValues[0])) {
                    $seg['Meal'] = str_replace('*', '', implode(', ', $mealValues));
                }
                $seatCells = $this->http->FindNodes('//tr[./td[normalize-space(.)="' . $matches[1] . '" and position()=1] and ./preceding::tr[' . implode(' or ', $dict['PassengerDetails']) . '] and not(.//tr)]/td[4]', null, '/^\s*([\dA-Z]{1,4})\s*$/');
                $seatValues = array_values(array_filter($seatCells));

                if (!empty($seatValues[0])) {
                    $seg['Seats'] = implode(', ', $seatValues);
                }
            }
            $offset = 0;
            $terminalColumn = $this->http->XPath->query('./preceding-sibling::tr[./*[6] and .//text()[' . implode(' or ', $dict['Flight']) . '] and .//text()[' . implode(' or ', $dict['Terminal']) . ']]', $segment);

            if ($terminalColumn->length > 0) {
                $offset = 1;
            }
            $formatType = $this->http->XPath->query('./td[3]/descendant::text()[contains(.,":") and string-length(normalize-space(.))>3]', $segment)->length; // 1 or 2

            if ($formatType === 1) {
                $dateDep = $this->http->FindSingleNode('./td[2]', $segment, true, $patterns['date']);
                $timeDep = $this->http->FindSingleNode('./td[3]', $segment, true, $patterns['time']);
                $dateArr = $this->http->FindSingleNode('./following-sibling::tr[1]/td[1]', $segment, true, $patterns['date']);
                $timeArr = $this->http->FindSingleNode('./following-sibling::tr[1]/td[2]', $segment, true, $patterns['time']);
                $airportDep = $this->http->FindSingleNode('./td[4]', $segment);
                $airportArr = $this->http->FindSingleNode('./following-sibling::tr[1]/td[3]', $segment);

                if ($offset === 1) {
                    $seg['DepartureTerminal'] = $this->http->FindSingleNode('./td[5]', $segment);
                    $seg['ArrivalTerminal'] = $this->http->FindSingleNode('./following-sibling::tr[1]/td[4]', $segment);
                }
            } elseif ($formatType === 2) {
                $dateDep = $this->http->FindSingleNode('./td[2]/*[1]', $segment, true, $patterns['date']);
                $dateArr = $this->http->FindSingleNode('./td[2]/*[last()]', $segment, true, $patterns['date']);
                $timeDep = $this->http->FindSingleNode('./td[3]/*[1]', $segment, true, $patterns['time']);
                $timeArr = $this->http->FindSingleNode('./td[3]/*[last()]', $segment, true, $patterns['time']);
                $airportDep = $this->http->FindSingleNode('./td[4]/*[1]', $segment);
                $airportArr = $this->http->FindSingleNode('./td[4]/*[last()]', $segment);
                if ($offset === 1) {
                    $seg['DepartureTerminal'] = $this->http->FindSingleNode('./td[5]/*[1]', $segment);
                    $seg['ArrivalTerminal'] = $this->http->FindSingleNode('./td[5]/*[last()]', $segment);
                }
            } else {
                return null;
            }

            if ($dateDep && $timeDep) {
                if ($dateDep = $this->normalizeDate($dateDep)) {
                    $seg['DepDate'] = strtotime($dateDep . ', ' . $timeDep);
                }
            }

            if ($dateArr && $timeArr) {
                if ($dateArr = $this->normalizeDate($dateArr)) {
                    $seg['ArrDate'] = strtotime($dateArr . ', ' . $timeArr);
                }
            }

            if (preg_match($patterns['nameAndCode'], $airportDep, $matchesAD)) {
                $seg['DepName'] = $matchesAD[1];
                $seg['DepCode'] = $matchesAD[2];
            } else {
                $seg['DepName'] = $airportDep;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match($patterns['nameAndCode'], $airportArr, $matchesAA)) {
                $seg['ArrName'] = $matchesAA[1];
                $seg['ArrCode'] = $matchesAA[2];
            } else {
                $seg['ArrName'] = $airportArr;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match("#^Departure Airport\s+(.+)#", $seg['DepName'], $m)) {
                $seg['DepName'] = $m[1];
            }

            if (preg_match("#^Arrival Airport\s+(.+)#", $seg['ArrName'], $m)) {
                $seg['ArrName'] = $m[1];
            }

            if (empty($seg['FlightNumber']) && empty($seg['DepDate']) && empty($seg['ArrDate']) && empty($seg['DepName']) && empty($seg['ArrName']) && empty($seg['DepCode']) && empty($seg['ArrCode'])) {
                continue;
            }
            $seg['Duration'] = $this->http->FindSingleNode('./td[' . (5 + $offset) . ']', $segment, true, '/^\s*(' . implode('|', $dict['Duration']) . ')/ui');
            $seg['Stops'] = $this->http->FindSingleNode('./td[' . (5 + $offset) . ']/descendant::text()[normalize-space(.)!=""][last()]', $segment, true, '/(\d+)\s*(?:' . implode('|', $dict['Stops']) . ')/ui');
            $seg['Cabin'] = $this->http->FindSingleNode('./td[' . (6 + $offset) . ']/descendant::text()[normalize-space(.)!=""][1]', $segment);
            $seg['Aircraft'] = $this->http->FindSingleNode('./td[' . (6 + $offset) . ']/descendant::text()[normalize-space(.)!="" and position()>1][last()]', $segment);
            $it['TripSegments'][] = $seg;
        }

        $passengers = $this->http->FindNodes('//tr[(' . implode(' or ', $dict['PassengerDetails']) . ') and not(.//tr)]/ancestor::table[1]/preceding-sibling::table[count(.//tr)=1][1]', null, '/^([^}{]+)$/');

        if (empty($passengers[0])) {
            $passengers = $this->http->FindNodes('//table[(' . implode(' or ', $dict['Passengers']) . ') and count(.//tr)=1 and not(.//table)]/ancestor::tr[1]/following-sibling::tr/descendant::tr[(' . implode(' or ', $dict['PassengerRow']) . ') and not(.//tr)][1]', null, $patterns['passenger']);
        }

        if (empty($passengers[0])) {
            $passengers = $this->http->FindNodes('//table[(' . implode(' or ', $dict['Passengers']) . ') and count(.//tr)=1 and not(.//table)]/following::table[' . implode(' or ', $dict['PassengerRow']) . ']/descendant::tr[(' . implode(' or ', $dict['PassengerRow']) . ') and count(./*)=1 and not(.//tr)]', null, $patterns['passenger']);
        }

        if (empty($passengers[0])) {
            $passengers = $this->http->FindNodes('//table[(' . implode(' or ', $dict['Passengers']) . ') and count(.//tr)=1 and not(.//table)]/following::table[' . implode(' or ', $dict['PassengerRow']) . ']/descendant::tr[' . implode(' or ', $dict['PassengerRow']) . ']/following-sibling::tr[1]/td[1]');
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        $result = [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'YouConfirmationItinerary_' . $this->lang,
        ];
        $payment = $this->http->FindSingleNode('(//tr[(' . implode(' or ', $dict['Payment']) . ') and not(.//tr)]/td/*[string-length(normalize-space(.))>2])[last()]');

        if (preg_match('/([A-Z]{1,3})\s+([,.\d\s]+)\s*$/', $payment, $matches)) {
            $result['parsedData']['TotalCharge'] = [
                'Currency' => $matches[1],
                'Amount'   => $this->priceNormalize($matches[2]),
            ];
        } elseif (preg_match('/([,.\d\s]+)\s+([A-Z]{1,3})\s*$/', $payment, $matches)) {
            $result['parsedData']['TotalCharge'] = [
                'Amount'   => $this->priceNormalize($matches[1]),
                'Currency' => $matches[2],
            ];
        }

        return $result;
    }

    private function getHtmlAttachments(\PlancakeEmailParser $parser, $length = 6000)
    {
        $result = [];
        $altCount = $parser->countAlternatives();

        for ($i = 0; $i < $parser->countAttachments() + $altCount; $i++) {
            $html = $parser->getAttachmentBody($i);
            $info = $parser->getAttachmentHeader($i, 'content-type');

            if (preg_match("#^text/html;#", $info) && is_string($html) && strlen($html) > $length) {
                $result[] = $html;
            }
        }

        return $result;
    }
}
