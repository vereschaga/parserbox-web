<?php

namespace AwardWallet\Engine\wagonlit\Email;

class UpcomingTripHtml2016 extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-4149776.eml, wagonlit/it-4178430.eml, wagonlit/it-8770988.eml";

    public $reFrom = "reply@email1.carlsonwagonlit.com";
    public $reProvider = "@email1.carlsonwagonlit.com";
    public $reSubject = [
        "en" => "Your upcoming trip to",
        "es" => "Su próximo viaje a",
        "fi" => "Seuraava matkasi",
    ];
    public $reBody = 'carlsonwagonlit.com';
    public $reBody2 = [
        "en" => "Information to facilitate your online check-in",
        "es" => "Información para facilitar su check-in en línea",
        "fi" => "Tiedot lähtöselvitystä varten",
    ];

    public static $dictionary = [
        "en" => [
            'dear'    => 'Dear',
            'trip'    => 'Information to facilitate your online check-in',
            'flight'  => 'Your flight details',
            'locator' => 'Booking\s+reference\s+\(PNR\):',
            'ticket'  => 'E-Ticket\s+Number:',
            'deparr'  => 'Departure|Arrival',
        ],
        "es" => [
            'dear'    => 'Apreciado/a',
            'trip'    => 'Información para facilitar su check-in en línea',
            'flight'  => 'Detalles de su vuelo',
            'locator' => 'Código de la Reserva \(PNR\):',
            'ticket'  => null,
            'deparr'  => 'Salida|Llegada',
        ],
        "fi" => [
            'dear'    => 'Hyvä',
            'trip'    => 'Tiedot lähtöselvitystä varten',
            'flight'  => 'Lentosi tiedot',
            'locator' => 'Varausnumero\s*\(PNR\):',
            'ticket'  => 'E-Ticket\s+Number:',
            'deparr'  => 'Lähtö|Saapuminen',
        ],
    ];

    public $lang = "";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $its = [$this->parseEmail()];
        $name = explode('\\', __CLASS__);

        return [
            'emailType'  => end($name) . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function parseEmail()
    {
        $this->result['Kind'] = 'T';
        $this->result['Passengers'] = [$this->http->FindSingleNode('//*[contains(text(), "' . $this->t('dear') . '")]', null, false, '#' . $this->t('dear') . '\s+(.*),#')];
        $this->parseTrip('//*[contains(text(), "' . $this->t('trip') . '")]/ancestor::table[1]');
        $this->parseSegments('//*[contains(text(), "' . $this->t('flight') . '")]/ancestor::table[1]');

        return $this->result;
    }

    protected function parseTrip($query)
    {
        $text = $this->http->FindSingleNode($query);

        if ($this->t('locator') && preg_match('#' . $this->t('locator') . '\s*([A-Z\d]{5,6})#u', $text, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }

        if ($this->t('ticket') && preg_match('/' . $this->t('ticket') . '\s*(\d+)/u', $text, $matches)) {
            $this->result['TicketNumbers'][] = $matches[1];
        }
    }

    protected function parseSegments($xpath)
    {
        foreach ($this->http->XPath->query($xpath) as $value) {
            $this->result['TripSegments'][] = $this->segment(preg_replace('/[\r\n]+|\s{2,}/', '  ', $value->nodeValue));
        }
    }

    protected function segment($text)
    {
        $segment = [];

        if (preg_match('#' . $this->t('flight') . '\s+.*?([A-Z]{2})\s*(\d{2,4})#u', $text, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
        }

        // Departure National Airport (BRU) Terminal: 1 14:00 PM - 31 July 2016
        // Arrival Pearson International Airport (YYZ) 18:55 - 31 July 2016
        if (preg_match_all('#'
                        . '(?:' . $this->t('deparr') . ')\s+'
                        . '(.*?)\s*\(([A-Z]{3})\)(.*?)\s+'
                        . '(\d+:\d+\s*[AP]?M?)\s*-\s*(\d+\s*\w+\s*\d{4})#u', $text, $matches, PREG_SET_ORDER)) {
            $segment['DepName'] = $matches[0][1];
            $segment['DepCode'] = $matches[0][2];
            $segment['DepartureTerminal'] = trim($matches[0][3]);
            $segment['DepDate'] = strtotime($matches[0][5] . ' ' . $matches[0][4]);

            $segment['ArrName'] = $matches[1][1];
            $segment['ArrCode'] = $matches[1][2];
            $segment['ArrivalTerminal'] = trim($matches[1][3]);
            $segment['ArrDate'] = strtotime($matches[1][5] . ' ' . $matches[1][4]);
        }

        return $segment;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
