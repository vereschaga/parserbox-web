<?php

namespace AwardWallet\Engine\hostelworld\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "hostelworld/it-1.eml, hostelworld/it-1389514.eml, hostelworld/it-1393694.eml, hostelworld/it-1410592.eml, hostelworld/it-1813789.eml, hostelworld/it-2.eml, hostelworld/it-3.eml, hostelworld/it-7246283.eml, hostelworld/it-7487007.eml, hostelworld/it-7545034.eml, hostelworld/it-9013798.eml";

    private $curLang;

    private $dict = [
        'Dear' => [
            'pt' => 'Caro(a)',
            'es' => 'Estimado/a',
            'nl' => 'Beste',
        ],
        'Your reference number is' => [
            'pt' => 'Seu número de referência é',
            'es' => 'Tu número de referencia es',
            'nl' => 'Je referentienummer is',
        ],
        'booking information' => [
            'pt' => 'Informações sobre a reserva',
            'es' => 'Información de reserva',
            'nl' => 'boekingsinformatie',
        ],
        'Date' => [
            'pt' => 'Data',
            'es' => 'Fecha',
            'nl' => 'Datum',
        ],
        'You\s+are\s+due\s+to\s+arrive\s+here\s+at' => [
            'es' => 'Su\s+llegada\s+esta\s+programada\s+para\s+las',
            'nl' => 'Aankomsttijd',
            'pt' => '(?:Você deverá chegar aqui as|Você deve chegar aqui às)',
        ],
        'Room Details' => [
            'pt' => 'Detalhes do quarto',
            'es' => 'Detalles de la habitación',
            'nl' => 'kamerbijzonderheden',
        ],
        'Total:' => [
            'pt' => 'Total:',
            'es' => ['Coste total:', 'Total:'],
            'en' => ['Total:', 'TOTAL (taxes included):', 'Total Cost:'],
            'nl' => 'TOTAAL (inclusief belastingen):',
        ],
        'Cancellations must' => [
            'pt' => 'Os cancelamentos devem',
            'es' => 'Las cancelaciones deben',
            'nl' => 'Annuleringen moeten rechtstreeks',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && $this->checkMails($headers["from"]))
            || isset($headers['subject']) && (stripos($headers['subject'], 'Confirmed booking from hostelworld.com') !== false || stripos($headers['subject'], 'Reserva confirmada Hostelworld.com') !== false);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/bookings@hostelworld\.com/i", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'alt="Hostelworld -') !== false
            || stripos($parser->getHTMLBody(), 'please use the change booking function in My Account on hostelworld.com') !== false; // || $this->checkMails($parser->getHTMLBody());
    }

    public function checkMails($input = '')
    {
        preg_match('/[\.@]hostelworld\.com/ims', $input, $match);

        return (isset($match[0])) ? true : false;
    }

    public function tr($text)
    {
        if (isset($this->dict[$text])) {
            if ($this->curLang == 'en') {
                if (isset($this->dict[$text][$this->curLang])) {
                    return $this->dict[$text][$this->curLang];
                } else {
                    return $text;
                }
            } elseif (isset($this->dict[$text][$this->curLang])) {
                return $this->dict[$text][$this->curLang];
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = null;

        $it['Kind'] = 'R';

        if (stripos($this->http->Response['body'], 'Sua reserva está confirmada') !== false) {
            $this->curLang = 'pt';
        } elseif (stripos($this->http->Response['body'], 'Tu reserva ya está confirmada') !== false) {
            $this->curLang = 'es';
        } elseif (stripos($this->http->Response['body'], 'Je boeking is nu bevestigd') !== false) {
            $this->curLang = 'nl';
        } elseif (stripos($this->http->Response['body'], 'Your booking is confirmed') !== false || stripos($this->http->Response['body'], 'Your booking is now confirmed') !== false) {
            $this->curLang = 'en';
        } else {
            return;
        }

        $it['GuestNames'] = [re('#(?:' . implode("|", array_map("preg_quote", (array) $this->tr('Dear'))) . ')\s+([\s\w+\-]+),#', $this->http->Response['body'])];

        $it['ConfirmationNumber'] = re('#(?:' . implode("|", array_map("preg_quote", (array) $this->tr('Your reference number is'))) . ')\s+([\d\-]+)#', $this->http->Response['body']);

        $xpath = "//text()[contains(., '" . $this->tr('booking information') . "')]/ancestor::node()/following-sibling::table[1]/descendant::tr[1]/td[1]//text()";
        $infoNodes = $this->http->FindNodes($xpath);
        $infoNodes = array_values(array_filter($infoNodes));
        $currentFieldName = 'HotelName';

        foreach ($infoNodes as $n) {
            $value = $n;

            if (stripos($n, 'p.') !== false) {
                $currentFieldName = 'Phone';
                $value = re('#p\.\s+([\s\d\(\)\+\-,]+)#', $n);

                if (empty($value)) {
                    continue;
                }
            }

            if ($currentFieldName !== null) {
                if (!isset($it[$currentFieldName])) {
                    $it[$currentFieldName] = $value;
                } else {
                    $it[$currentFieldName] .= ", $value";
                }
            }

            if ($currentFieldName == 'HotelName') {
                $currentFieldName = 'Address';
            } elseif ($currentFieldName == 'Phone') {
                $currentFieldName = null;
            }
        }

        $xpath = "//tr[contains(., '" . $this->tr('Date') . "') and contains(., '" . $this->tr('Room Details') . "') and count(th) = 5]/../following-sibling::*[1]/tr/td[1]";
        $dates = $this->http->FindNodes($xpath);

        if (empty($dates)) {
            $dates = $this->http->FindNodes("//tr[contains(., 'Date') and contains(normalize-space(.), 'Room Details') and count(td) = 5]/following-sibling::tr[count(td)=5]/td[1]");
        }
        $regex = '#(?P<Day>\d+)\w*\s+(?P<Month>\w+)\s+(?P<Year>\d+)#';

        if (preg_match($regex, $dates[0], $m)) {
            $s = $m['Day'] . ' ' . en($m['Month']) . ' ' . $m['Year'];

            if ($time = $this->http->FindPreg('#' . $this->tr('You\s+are\s+due\s+to\s+arrive\s+here\s+at') . '\s+(\d+:\d+)#i')) {
                $s .= ', ' . str_replace('.', ':', $time);
            } elseif ($time = $this->http->FindPreg('#(?:Check[\-\s]+In:?|Check[\-\s]+in\s+from)\s+(\d+[:\.]\d+\s*(?:am|pm)?)#i')) {
                $s .= ', ' . str_replace('.', ':', $time);
            }
            $it['CheckInDate'] = strtotime($s);
        }

        if (preg_match($regex, end($dates), $m)) {
            $s = $m['Day'] . ' ' . en($m['Month']) . ' ' . $m['Year'];

            if ($time = $this->http->FindPreg('#(?:Check[\-\s]+Out:?|Check[\-\s]+out\s+before|Check[\-\s]+out time:?(?:\s*until)?|Salida antes de las)\s+(?:\d+[:\.]\d+\s*(?:am|pm)?)?[\s\-]*(\d+[:\.]\d+\s*(?:am|pm)?)#i')) {
                $s .= ', ' . str_replace('.', ':', $time);
            } elseif ($time = $this->http->FindPreg('#check[\-\s]+out (?:until|is at|time is) (\d+)\s*am#i')) {
                $s .= ', ' . $time . ':00';
            }
            $it['CheckOutDate'] = strtotime("+1 day", strtotime($s));
        }

        $total = str_replace("€", "EUR ", substr(re('#(?:' . implode("|", array_map("preg_quote", (array) $this->tr('Total:'))) . ')\s+([^\n]*)#', html_entity_decode($this->http->Response['body'])), 0, 10));
        $total = str_replace("CA$", "CAD", $total);
        $it['Total'] = cost($total);
        $it['Currency'] = currency($total);

        if ($it['Currency'] === null and stripos($total, '฿') !== false) {
            $it['Currency'] = 'THB';
        }

        $rts = array_unique($this->http->FindNodes("//text()[normalize-space(.)='Room Details']/ancestor::table[1]/descendant::tr[./td][1]/../tr/td[2]"));

        if (count($rts) == 1) {
            $it['RoomType'] = $rts[0];
        }

        $it['CancellationPolicy'] = $this->http->FindSingleNode('//text()[contains(., "' . $this->tr('Cancellations must') . '")]');

        $classParts = explode('\\', __CLASS__);

        return [
            'emailType'  => end($classParts) . ucfirst($this->curLang),
            "parsedData" => [
                "Itineraries" => [$it],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en', 'pt', 'es', 'nl'];
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }
}
