<?php

namespace AwardWallet\Engine\transavia\Email;

class Itinerary extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "transavia/it-4003287.eml, transavia/it-4011952.eml, transavia/it-4013463.eml";
    public $reBody = [
        'en' => ['My Transavia', 'Booking'],
        'it' => ['La Mia Transavia', 'della prenotazione'],
        'fr' => ['Mon Transavia', 'votre'],
        'nl' => ['Mijn Transavia', 'Boekingsgegevens'],
        'es' => ['Mi Transavia', 'reserva'],
        'pt' => ['Minha Transavia', 'reserva'],
    ];
    public $pdf;
    public $lang = '';
    public $dict = [
        'en' => [],
        'es' => [
            //            Record Locator
            'booking confirmation number' => 'número de confirmación de la reserva',
            //            Flight Number
            'flight number' => 'número de vuelo',
            //            Dep
            'from:' => 'de:',
            //            Arr
            'to:' => 'a:',
            //            Passengers
            'adults' => 'adultos',
            //            Total
            'total cost' => 'precio total',
            //            Time Arr
            'arrival at'       => 'llegada a las',
            'date of booking'  => 'fecha de la reserva',
            'seat reservation' => '',
        ],
        'it' => [
            //            Record Locator
            'booking confirmation number' => 'numero di conferma della prenotazione',
            //            Flight Number
            'flight number' => 'numero di volo',
            //            Dep
            'from:' => 'da:',
            //            Arr
            'to:' => 'a:',
            //            Passengers
            'adults' => 'adulti',
            //            Total
            'total cost' => 'prezzo totale',
            //            Time Arr
            'arrival at'       => 'arrivo alle ore',
            'date of booking'  => 'data di prenotazione',
            'seat reservation' => 'Posto',
        ],
        'fr' => [
            //            Record Locator
            'booking confirmation number' => 'numéro de confirmation de réservation',
            //            Flight Number
            'flight number' => 'numéro de vol',
            //            Dep
            'from:' => 'de:',
            //            Arr
            'to:' => 'à:',
            //            Passengers
            'adults' => 'adultes',
            //            Total
            'total cost' => 'prix total',
            //            Time Arr
            'arrival at'      => 'arrivée à',
            'date of booking' => 'date de réservation',
        ],
        'nl' => [
            //            Record Locator
            'booking confirmation number' => 'bevestigingsnummer van uw boeking',
            //            Flight Number
            'flight number' => 'vluchtnummer',
            //            Dep
            'from:' => 'van:',
            //            Arr
            'to:' => 'naar:',
            //            Passengers
            'adults' => 'volwassenen',
            //            Total
            'total cost' => 'totaal',
            //            Time Arr
            'arrival at'      => 'aankomst om',
            'date of booking' => 'boekingsdatum',
        ],
        'pt' => [
            //            Record Locator
            'booking confirmation number' => 'número da confirmação da reserva',
            //            Flight Number
            'flight number' => 'número do voo',
            //            Dep
            'from:' => 'de:',
            //            Arr
            'to:' => 'para:',
            //            Passengers
            'adults' => 'adultos',
            //            Total
            'total cost' => 'preço total',
            //            Time Arr
            'arrival at'      => 'chegada às',
            'date of booking' => 'data da reserva',
        ],
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                } else {
                    $this->lang = 'en';
                }
            }
        }
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $this->pdf = clone $this->http;
                    $this->pdf->SetBody($html);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            //            'pdf' => $html,
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "noreply@booking.transavia.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "noreply@booking.transavia.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'it', 'fr', 'nl', 'es', 'pt'];
    }

    public static function getEmailTypesCount()
    {
        return 6;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//*[contains(normalize-space(text()), '" . $this->t('booking confirmation number') . "')]/following-sibling::text()[normalize-space(.)!=''][1]");
        $it['Passengers'] = $this->pdf->FindNodes("//*[contains(normalize-space(.), '" . $this->t('adults') . "')]/following-sibling::text()[contains(., 'MR') or contains(., 'MRS')]");
        $total = $this->pdf->FindSingleNode("//text()[contains(normalize-space(.), '" . $this->t('total cost') . "')]/following-sibling::*[normalize-space(.)!=''][1]");
        $it['TotalCharge'] = cost($total);
        $it['Currency'] = currency($total);
        $reservDate = ($this->pdf->FindSingleNode("//text()[contains(normalize-space(.), '" . $this->t('date of booking') . "')]"));

        if (preg_match("#" . $this->t('date of booking') . "\s*:\s*[\w|\D]+\s+(\d{1,2})\s+(\w+),\s+(\d{4})#", $reservDate, $math)) {
            $it['ReservationDate'] = strtotime($this->monthNameToEnglish($math[2]) . ' ' . $math[1] . ' ' . $math[3]);
        }
        $xpath = "//text()[contains(normalize-space(.), '" . $this->t('flight number') . "')]";
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length == 0) {
            $this->pdf->Log("roots not found {$xpath}", LOG_LEVEL_NORMAL);
        }

        foreach ($roots as $root) {
            $seg = [];

            $seg['FlightNumber'] = $this->pdf->FindSingleNode(".", $root, true, "#:.+\w{2}\s+(\d+)#");

            $seg['AirlineName'] = $this->pdf->FindSingleNode(".", $root, true, "#:.+(\w{2})\s+\d+#");

            $date = $this->pdf->FindSingleNode("preceding-sibling::*[normalize-space(.)!=''][1]", $root);

            if (preg_match("#[\w|\D]+\s+(\d+)\s+([\w|\D]+),\s*(\d{4})#", $date, $m)) {
                $date = $this->monthNameToEnglish($m[2]) . ' ' . $m[1] . ' ' . $m[3];
            }
            $seg['DepName'] = $this->getNode($this->t('from:'), $root);

            $seg['DepDate'] = strtotime($date . ' ' . $this->getNode($this->t('from:'), $root, 2, '*', '#(\d+:\d{2})\s*.*#'));

            $seg['DepCode'] = $this->getNode($this->t('from:'), $root, 1, 'text()', "#\(\s*(\w{3})\s*\).+#");

            $seg['ArrName'] = $this->getNode($this->t('to:'), $root);

            $seg['ArrCode'] = $this->getNode($this->t('to:'), $root, 1, 'text()', "#\(\s*(\w{3})\s*\).+#");

            $arrTime = $this->getNode($this->t('to:'), $root, 1, 'text()', "#\(\s*\w{3}\s*\),\s+" . $this->t('arrival at') . "\s+(\d+:\d+)#");

            if ($this->lang === 'it' && strpos($this->dict['it']['from:'], $this->dict['it']['to:']) !== false) {
                $seg['ArrName'] = $this->setNameSame($root);
                $seg['ArrCode'] = $this->setNameSame($root, "#\(\s*(\w{3})\s*\).+#", 'text()');
                $arrTime = $this->setNameSame($root, "#\(\s*\w{3}\s*\),\s+" . $this->t('arrival at') . "\s+(\d+:\d+)#", 'text()');
            }

            $seg['ArrDate'] = strtotime($date . ' ' . $arrTime);

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function setNameSame($root, $regExp = null, $text = '*')
    {
        return $this->pdf->FindSingleNode("(following-sibling::text()[contains(., '" . $this->t('to:') . "') and not(contains(., 'da:'))]/following-sibling::{$text}[normalize-space(.)!=''][1])[1]", $root, true, $regExp);
    }

    private function getNode($str, $root, $xOffset = 1, $text = '*', $regExp = null)
    {
        if ($regExp === null) {
            return $this->pdf->FindSingleNode("(following-sibling::text()[contains(., '{$str}')]/following-sibling::{$text}[normalize-space(.)!=''][$xOffset])[1]", $root);
        } elseif ($regExp !== null) {
            return $this->pdf->FindSingleNode("(following-sibling::text()[contains(., '{$str}')]/following-sibling::{$text}[normalize-space(.)!=''][$xOffset])[1]", $root, true, $regExp);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
    }
}
