<?php

namespace AwardWallet\Engine\mileageplus\Email;

/*
subject: (MileagePlus)? eTicket Itinerary and Receipt for Confirmation ABC123
PT lang
it-3979327
*/

class ETicketReceiptPT extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-3979327.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'eTicketReceipt',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], 'Confirmação') !== false
            && stripos($this->http->Response['body'], 'Número do bilhete eletrônico') !== false
            && stripos($this->http->Response['body'], 'Cidade e horário da partida') !== false
            && stripos($this->http->Response['body'], 'united.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'eTicket Itinerary and Receipt for Confirmation') !== false
            || isset($headers['from']) && stripos($headers['from'], 'unitedairlines@united.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'unitedairlines@united.com') !== false;
    }

    protected function ParseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => [], 'Passengers' => []];
        $root = $this->http->XPath->query('//*[text()[contains(., "Confirmação:")]]')->item(0);

        for ($i = 0; $i < 5; $i++) {
            $parent = $this->http->XPath->query('parent::*', $root)->item(0);

            if (isset($parent->nodeValue) && preg_match('/^Confirmação:\s*([A-Z\d]{6})/', CleanXMLValue($parent->nodeValue), $m)) {
                $it['RecordLocator'] = $m[1];

                break;
            }
            $root = $parent;
        }
        $rows = $this->http->XPath->query('(//th[contains(., "Cidade e horário da partida")] | //td[contains(., "Cidade e horário da partida") and not(.//td)])/parent::tr/following-sibling::tr');

        foreach ($rows as $row) {
            $tds = $this->http->FindNodes('td', $row);

            if (count($tds) === 7 && preg_match('/^\w{3}, (\d+[A-Z]{3}\d+)$/', $tds[0])) {
                $segment = [];
                $date = null;

                if (preg_match('/^\w{3}, (\d+[A-Z]{3}\d+)$/', $tds[0], $m)) {
                    $date = $m[1];
                }

                if (preg_match('/^([A-Z\d]{2})(\d+)$/', $tds[1], $m)) {
                    $segment['AirlineName'] = $m[1];
                    $segment['FlightNumber'] = $m[2];
                }

                if (strlen($tds[2]) < 3) {
                    $segment['BookingClass'] = $tds[2];
                }

                if (isset($date) && preg_match('/^(?<name>.+)\s*\((?<code>[A-Z]{3})[^)]*\)\s*(?<time>\d+:\d+ [AP]M)/', $tds[3], $m)) {
                    $segment['DepCode'] = $m['code'];
                    $segment['DepName'] = $m['name'];
                    $segment['DepDate'] = $this->makeDate($date . ' ' . $m['time']);
                } elseif (isset($date) && preg_match('/^(?<code>[A-Z]{3})\s*(?<time>\d+:\d+ [AP]M)$/', $tds[3], $m)) {
                    $segment['DepCode'] = $m['code'];
                    $segment['DepDate'] = $this->makeDate($date . ' ' . $m['time']);
                } elseif (isset($date) && preg_match('/^(?<name>[A-Z\s,]+)\s*(?<time>\d+:\d+ [AP]M)$/', $tds[3], $m)) {
                    $segment['DepCode'] = TRIP_CODE_UNKNOWN;
                    $segment['DepName'] = $m['name'];
                    $segment['DepDate'] = $this->makeDate($date . ' ' . $m['time']);
                }

                if (isset($date) && preg_match('/^(?<name>.+)\s*\((?<code>[A-Z]{3})[^)]*\)\s*(?<time>\d+:\d+ [AP]M)/', $tds[4], $m)) {
                    $segment['ArrCode'] = $m['code'];
                    $segment['ArrName'] = $m['name'];
                    $segment['ArrDate'] = $this->makeDate($date . ' ' . $m['time']);
                } elseif (isset($date) && preg_match('/^(?<code>[A-Z]{3})\s*(?<time>\d+:\d+ [AP]M)$/', $tds[4], $m)) {
                    $segment['ArrCode'] = $m['code'];
                    $segment['ArrDate'] = $this->makeDate($date . ' ' . $m['time']);
                } elseif (isset($date) && preg_match('/^(?<name>[A-Z\s,]+)\s*(?<time>\d+:\d+ [AP]M)$/', $tds[4], $m)) {
                    $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $segment['ArrName'] = $m['name'];
                    $segment['ArrDate'] = $this->makeDate($date . ' ' . $m['time']);
                }

                if (!empty($tds[5])) {
                    $segment['Aircraft'] = $tds[5];
                }

                if (!empty($tds[6])) {
                    $segment['Meal'] = $tds[6];
                }
                $segment['Seats'] = [];
                $it['TripSegments'][] = $segment;
            }
        }
        $rows = $this->http->XPath->query('//tr[not(.//tr) and *[contains(., "Passageiro")] and *[contains(., "Número do bilhete eletrônico")]]/following-sibling::tr');

        foreach ($rows as $row) {
            $tds = $this->http->FindNodes('td', $row);

            if (count($tds) === 4 && stripos($tds[0], '/') !== false) {
                $it['Passengers'][] = $tds[0];
                $seats = explode('/', $tds[3]);

                if (count($seats) === count($it['TripSegments'])) {
                    for ($i = 0; $i < count($seats); $i++) {
                        if (preg_match('/^\d+[A-Z]$/', $seats[$i])) {
                            $it['TripSegments'][$i]['Seats'][] = $seats[$i];
                        }
                    }
                }
            }
        }

        foreach ($it['TripSegments'] as &$segment) {
            if (count($segment['Seats']) > 0) {
                $segment['Seats'] = implode(',', $segment['Seats']);
            } else {
                unset($segment['Seats']);
            }
        }
        $total = $this->http->FindSingleNode('//li[contains(., "Total de bilhetes eletrônicos:")]');
        $this->http->Log('tot ' . $total);

        if (!empty($total) && preg_match('/Total de bilhetes eletrônicos:\s*([\d\,\.]+)\s*([A-Z]+)(\s*Conversion.+)?$/', $total, $m)) {
            $it['TotalCharge'] = str_replace(',', '', $m[1]);
            $it['Currency'] = $m[2];
        }
        $fees = $this->http->FindNodes('//td[contains(., "Análise da tarifa") and not(.//td)]//ul/li');
        $it['Fees'] = [];
        $fee = false;

        for ($i = 0; $i < count($fees); $i++) {
            $this->http->Log($fees[$i]);

            if (strpos($fees[$i], 'Total') !== false) {
                break;
            }

            if (strpos($fees[$i], 'Tarifa aérea:') !== false) {
                $fee = true;

                continue;
            }

            if ($fee && preg_match('/^([^:]+): ([\d,]+\.\d{2})(\s*[A-Z]+\s*Conversion.*)?$/', $fees[$i], $m)) {
                $it['Fees'][] = ['Name' => $m[1], 'Charge' => str_replace(',', '', $m[2])];
            }
        }
        $it['BaseFare'] = str_replace(',', '', $this->http->FindSingleNode('//*[contains(text(), "A tarifa aérea que você pagou neste itinerário totaliza")]', null, true, '/A tarifa aérea que você pagou neste itinerário totaliza:\s*([\d,]+\.\d{2})/'));
        $it['Tax'] = str_replace(',', '', $this->http->FindSingleNode('//*[contains(text(), "Os impostos, as taxas e as sobretaxas pagos totalizam")]', null, true, '/Os impostos, as taxas e as sobretaxas pagos totalizam:\s*([\d,]+\.\d{2})/'));

        return [$it];
    }

    protected function makeDate($date)
    {
        if (preg_match('/(\d{2}):\d{2}\s*PM$/', $date, $m) > 0 && intval($m[1]) > 12) {
            $date = preg_replace('/\s*PM$/', '', $date);
        }

        return strtotime($date);
    }
}
