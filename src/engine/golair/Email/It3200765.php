<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class It3200765 extends \TAccountCheckerExtended
{
    public $mailFiles = "golair/it-3200765.eml"; // +1 bcdtravel(html)[pt]

    public $reBody = "//www.voegol.com.br";
    public $reBody2 = "novo_voo_ptbr.png";
    public $reSubject = "Alerta Gol - Alteração/Cancelamento de Vôo";

    protected $lang = '';

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $patterns = [
                    'airportCode' => '/^([A-Z]{3})$/',
                    'time'        => '/(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)/',
                ];

                $it = [];
                $it['Kind'] = 'T';

                $dateRelative = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"do seu voo do dia")]', null, true, '/do seu voo do dia\s*(\d{1,2}\/\d{1,2}\/\d{2,4})/i');

                if ($dateRelative) {
                    $dateRelative = strtotime($this->normalizeDate($dateRelative));
                }

                // Passengers
                // Seats
                $passengers = [];
                $seats = [];
                $passengerRows = $this->http->XPath->query('//tr[ ./td[2][contains(normalize-space(.),"Nome")] and ./td[3][contains(normalize-space(.),"Voo")] and ./td[4][contains(normalize-space(.),"Assento")] ]/following-sibling::tr[ normalize-space(.) and ./td[4] ]');

                foreach ($passengerRows as $passengerRow) {
                    if ($passengerName = $this->http->FindSingleNode('./td[2]', $passengerRow, true, '/^(?:\d+\.)?\s*(.+)/')) {
                        $passengers[] = $passengerName;
                    }

                    if ($flight = $this->http->FindSingleNode('./td[3]', $passengerRow, true, '/^([A-Z\d]{2}-\d+)$/')) {
                        if ($seat = $this->http->FindSingleNode('./td[4]', $passengerRow, true, '/^(\d{1,2}[A-Z])$/')) {
                            $seats[$flight][] = $seat;
                        }
                    }
                }

                if (empty($passengers[0])) {
                    if ($passenger = $this->http->FindSingleNode('//*[(contains(@style,"#ff5a00") or contains(@style,"#FF5A00")) and starts-with(normalize-space(.),"Olá ")]', null, true, '/^Olá\s*(\w[^,]*\w)/ui')) {
                        $passengers = [$passenger];
                    }
                }

                if (!empty($passengers[0])) {
                    $it['Passengers'] = $passengers;
                }

                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'Localizador:')]", null, true, "#Localizador\s*:\s*([A-Z0-9]{6})$#");

                // TripSegments
                $xpath = '//img[contains(@src,"novo_voo_ptbr.png") or normalize-space(@alt)="NOVO VOO"]/ancestor::tr[1]/following::tr[ ./td[4][contains(normalize-space(.),"Saida")] and ./td[6] ]';
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length === 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($nodes as $root) {
                    $itsegment = [];

                    $date = 0;
                    $dateDep = $this->http->FindSingleNode('./td[2]/descendant::text()[string-length(normalize-space(.))>1][1]', $root);

                    if ($dateRelative && preg_match('/^(?<day>\d{1,2})\s*(?<month>[^,.\d\s]{3,})/', $dateDep, $matches)) {
                        if (($monthNew = MonthTranslate::translate($matches['month'], $this->lang)) !== false) {
                            $matches['month'] = $monthNew;
                        }
                        $date = EmailDateHelper::parseDateRelative($matches['day'] . ' ' . $matches['month'], $dateRelative);
                    }

                    // AirlineName
                    // FlightNumber
                    $flight = $this->http->FindSingleNode('./td[2]', $root);

                    if (preg_match('/(([A-Z\d]{2})-(\d+))/', $flight, $matches)) {
                        $itsegment['AirlineName'] = $matches[2];
                        $itsegment['FlightNumber'] = $matches[3];
                        // Seats
                        if (!empty($seats[$matches[1]])) {
                            $itsegment['Seats'] = $seats[$matches[1]];
                        }
                    }

                    // DepCode
                    $itsegment['DepCode'] = $this->http->FindSingleNode('./td[3]', $root, true, $patterns['airportCode']);

                    // DepDate
                    $timeDep = $this->http->FindSingleNode('./td[4]', $root, true, $patterns['time']);

                    if ($date && $timeDep) {
                        $itsegment['DepDate'] = strtotime($timeDep, $date);
                    }

                    // ArrCode
                    $itsegment['ArrCode'] = $this->http->FindSingleNode('./td[5]', $root, true, $patterns['airportCode']);

                    // ArrDate
                    $timeArr = $this->http->FindSingleNode('./td[6]', $root, true, $patterns['time']);

                    if ($date && $timeArr) {
                        $itsegment['ArrDate'] = strtotime($timeArr, $date);
                    }

                    $it['TripSegments'][] = $itsegment;
                }

                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@voegol.com.br') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        return stripos($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false
            && strpos($body, $this->reBody2) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $this->lang = 'pt';

        $itineraries = [];

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'AlertFlight_' . $this->lang,
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    protected function normalizeDate($string = '')
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) { // 22/04/2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
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
}
