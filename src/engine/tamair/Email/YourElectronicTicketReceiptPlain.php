<?php

namespace AwardWallet\Engine\tamair\Email;

class YourElectronicTicketReceiptPlain extends \TAccountChecker
{
    public $mailFiles = "tamair/it-6101689.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'nao-responda@tam.com.br') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tam.com.br') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $textBody = empty($parser->getPlainBody()) ? text($parser->getHTMLBody()) : $parser->getPlainBody();

        return stripos($textBody, 'www.tam.com.br') !== false
            && stripos($textBody, 'CÓDIGO DA RESERVA') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];
        $textBody = empty($parser->getPlainBody()) ? text($parser->getHTMLBody()) : $parser->getPlainBody();
        $it = $this->ParseEmail($textBody);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'YourElectronicTicketReceiptPlain',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function priceNormalize($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function ParseEmail($textBody)
    {
        $start = strpos($textBody, 'NOME:');
        $end = strpos($textBody, 'INFORMAÇÕES GERAIS');

        if ($start === false || $end === false) {
            return null;
        }
        $textUnfiltered = substr($textBody, $start, $end - $start);
        $text = preg_replace('/<[^<]+>/', '', $textUnfiltered);
        $it = [];
        $it['Kind'] = 'T';

        if (preg_match('/^[>\s]*NOME[:\s]+([^:]+?)\s*$/umi', $text, $matches)) {
            $it['Passengers'] = [$matches[1]];
        }

        if (preg_match('/^[>\s]*Data\s+de\s+emissão[:\s]+(\d{1,2}[^\d]{3}\d{2,4})/umi', $text, $matches)) {
            $it['ReservationDate'] = strtotime($matches[1]);
        }

        if (preg_match('/^[>\s]*CÓDIGO\s+DA\s+RESERVA[:\s]+([A-Z\d]{5,7})\s*$/umi', $text, $matches)) {
            $it['RecordLocator'] = $matches[1];
        }

        if (preg_match('/^[>\s]*NÚMERO\s+DO\s+E-TICKET[:\s]+([-\d\s]+\S)\s*$/umi', $text, $matches)) {
            $it['TicketNumbers'] = [$matches[1]];
        }
        $it['TripSegments'] = [];
        $fragments = [
            'date'       => 'Data\s*:[>\s\n]+(?<date>\d{1,2}[^\d]{3})',
            'flight'     => 'Vôo\s*:[>\s\n]+(?<airlinename>[A-Z\d]{2})\s*(?<flightnumber>\d+)',
            'operator'   => '[-\s]*Operado\s+por\s+(?<operator>[^\n]+)',
            'timeDep'    => 'Saída\s*:[>\s\n]+(?<timedep>\d{1,2}:\d{2})',
            'airportDep' => '(?<airportdep>[^\n]+)',
            'timeArr'    => 'Chegada\s*:[>\s\n]+(?<timearr>\d{1,2}:\d{2})',
            'airportArr' => '(?<airportarr>[^\n]+)',
            'class'      => 'Classe\s*:[>\s\n]+(?<cabin>[^\n)(]+)\s*\(\s*(?<class>[A-Z]{1,2})\s*\)',
            'aircraft'   => 'Aeronave\s*:[>\s\n]+(?<aircraft>[^\n]+)',
        ];
        $pattern = "/^[>\s]*{$fragments['date']}[>\s\n]+{$fragments['flight']}[>\s\n]+{$fragments['operator']}[>\s\n]+{$fragments['timeDep']}[>\s\n]+{$fragments['airportDep']}[>\s\n]+{$fragments['timeArr']}[>\s\n]+{$fragments['airportArr']}[>\s\n]+{$fragments['class']}[>\s\n]+{$fragments['aircraft']}[>\s\n]+Bagagem\s*:$/umi";
        preg_match_all($pattern, $text, $segmentsMatches, PREG_SET_ORDER);
        $airportDelimiter = ', TERMINAL';
        $airportPatterns = [
            'nameAndCode' => '/^\s*(?:([^\n]+)\s([A-Z]{3})|([^\n]+))\s*$/',
            'terminal'    => '/^\s*([A-Z\d]{1,2})\s*$/',
        ];

        foreach ($segmentsMatches as $matches) {
            $seg = [];
            $seg['AirlineName'] = $matches['airlinename'];
            $seg['FlightNumber'] = $matches['flightnumber'];
            $seg['Operator'] = $matches['operator'];
            $airportDepParts = explode($airportDelimiter, strtoupper($matches['airportdep']));

            if (preg_match($airportPatterns['nameAndCode'], $airportDepParts[0], $matchesAD)) {
                $seg['DepName'] = $matchesAD[1] ? $matchesAD[1] : $matchesAD[3];
                $seg['DepCode'] = $matchesAD[2] ? $matchesAD[2] : TRIP_CODE_UNKNOWN;
            }

            if (preg_match($airportPatterns['terminal'], $airportDepParts[1], $matchesTD)) {
                $seg['DepartureTerminal'] = $matchesTD[1];
            }
            $airportArrParts = explode($airportDelimiter, strtoupper($matches['airportarr']));

            if (preg_match($airportPatterns['nameAndCode'], $airportArrParts[0], $matchesAA)) {
                $seg['ArrName'] = $matchesAA[1] ? $matchesAA[1] : $matchesAA[3];
                $seg['ArrCode'] = $matchesAA[2] ? $matchesAA[2] : TRIP_CODE_UNKNOWN;
            }

            if (preg_match($airportPatterns['terminal'], $airportArrParts[1], $matchesTA)) {
                $seg['ArrivalTerminal'] = $matchesTA[1];
            }
            $seg['Cabin'] = $matches['cabin'];
            $seg['BookingClass'] = $matches['class'];
            $seg['Aircraft'] = $matches['aircraft'];

            if ($matches['date'] && $matches['timedep'] && $matches['timearr']) {
                if (isset($it['ReservationDate'])) {
                    $date = strtotime($matches['date'], $it['ReservationDate']);

                    if ($date < $it['ReservationDate']) {
                        $date = strtotime('+1 years', $date);
                    }
                } else {
                    $date = strtotime($matches['date'] . $this->year);
                }
                $seg['DepDate'] = strtotime($matches['timedep'], $date);
                $seg['ArrDate'] = strtotime($matches['timearr'], $date);
            }
            $it['TripSegments'][] = $seg;
        }

        if (preg_match('/^[>\s]*TOTAL\n+[>\s]*([A-Z]{3})\s*([,.\d]+)\s*$/um', $text, $matches)) {
            $it['Currency'] = $matches[1];
            $it['TotalCharge'] = $this->priceNormalize($matches[2]);

            if (preg_match('/^[>\s]*Tarifa\s+Aerea[:\s]+\n+[>\s]*' . $it['Currency'] . '\s*([,.\d]+)\s*\n+[>\s]*Taxas[:\s]+$/umi', $text, $matches)) {
                $it['BaseFare'] = $this->priceNormalize($matches[1]);
            }

            if (preg_match('/^[>\s]*Total[:\s]+\n+[>\s]*' . $it['Currency'] . '\s*([,.\d]+)\s*\n+[>\s]*TOTAL\s*$/um', $text, $matches)) {
                $it['Tax'] = $this->priceNormalize($matches[1]);
            }
        }

        return $it;
    }
}
