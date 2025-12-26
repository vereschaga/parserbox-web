<?php

namespace AwardWallet\Engine\mileageplus\Email;

class Farelock extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-2928876.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "Farelock",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && false !== stripos($headers['from'], 'unitedairlines@united.com')
            || isset($headers['subject']) && false !== stripos($headers['subject'], 'FareLock Reminder');
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//img[contains(@src, "farelock-logo")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return false !== stripos($from, 'unitedairlines@united.com');
    }

    protected function ParseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode('//td[contains(., "Confirmation Number:") and not(.//td)]/following-sibling::td[1]', null, true, '/^[A-Z\d]{6}$/');
        $date = null;
        $segReg = '/^(?<time>\d{1,2}:\d{2} [ap]\.m\.)\s*(?<name>.+) \((?<code>[A-Z]{3})[^\)]*\)$/';
        $rows = $this->http->XPath->query('(//tr[td[contains(text(), "Departing")] and td[contains(text(), "Arriving")] and td[contains(text(), "Duration")] and td[contains(text(), "Flight Information")]])[1]/ancestor::table[1]/parent::*//tr[not(.//tr)]');

        foreach ($rows as $row) {
            $tds = $this->http->FindNodes('./td', $row);

            if (4 === count($tds) && preg_match($segReg, $tds[0], $dep) && preg_match($segReg, $tds[1], $arr) && isset($date)) {
                $segment = [
                    'DepCode' => $dep['code'],
                    'DepName' => $dep['name'],
                    'DepDate' => strtotime($date . ' ' . $dep['time']),
                    'ArrCode' => $arr['code'],
                    'ArrName' => $arr['name'],
                    'ArrDate' => strtotime($date . ' ' . $arr['time']),
                ];
                $duration = implode("\n", $this->http->FindNodes('td[3]//text()[normalize-space()]', $row));

                if (preg_match("#Flight Time:\s*(\d.+)#", $duration, $m)) {
                    $segment['Duration'] = $m[1];
                } elseif (preg_match("#Travel Time:\s*(\d.+)#", $duration, $m)) {
                    $segment['Duration'] = $m[1];
                }

                $info = $this->http->FindNodes('td[4]//text()[normalize-space()]', $row);

                if (!empty($info[0]) && preg_match('/^(?<code>[A-Z\d]{2})(?<number>\d+)$/', $info[0], $m)) {
                    $segment['AirlineName'] = $m['code'];
                    $segment['FlightNumber'] = $m['number'];
                }

                if (!empty($info[1]) && preg_match('/\|\s*([^|]+)$/', $info[1], $m)) {
                    $segment['Aircraft'] = $m[1];
                }

                if (!empty($info[2]) && preg_match('/^(?<cabin>[^\(]+) \((?<class>[A-Z]{1,3})\) \| (?<meal>\w+)$/', $info[2], $m)) {
                    $segment['Cabin'] = $m['cabin'];
                    $segment['BookingClass'] = $m['class'];
                    $segment['Meal'] = $m['meal'];
                }
                $it['TripSegments'][] = $segment;
            }

            if (preg_match('/^(?<date>\w{3}\.\, \w{3}\. \d+\, \d{4})/', CleanXMLValue($row->nodeValue), $m)) {
                $date = $m['date'];
            }
        }
        $payment = array_filter($this->http->FindNodes('//tr[contains(., "Additional Taxes/Fees") and not(.//tr)]/parent::*/tr/td[2]'));

        if (3 === count($payment)) {
            $cost = array_shift($payment);

            if (preg_match('/^([\d\,]+) Miles$/', $cost, $m)) {
                $it['SpentAwards'] = str_replace(',', '', $m[1]);
            } elseif (preg_match('/([\d\,\.]+)$/', $cost, $m)) {
                $it['BaseFare'] = str_replace(',', '', $m[1]);
            }
            $tax = array_shift($payment);

            if (preg_match('/^(\D+)([\d\.\,]+)$/', $tax, $m)) {
                $it['Currency'] = $m[1];
                $it['Tax'] = str_replace(',', '', $m[2]);
            }
            $total = array_shift($payment);

            if (preg_match('/([\d\.\,]+)$/', $total, $m)) {
                $it['TotalCharge'] = str_replace(',', '', $m[1]);
            }
        }
        $passengers = $this->http->FindSingleNode('//td[contains(., "Traveler Information") and not(.//td)]/*[not(contains(., "Traveler Information"))]');

        if (null !== $passengers) {
            $it['Passengers'] = array_map(function ($s) {return trim(preg_replace('/\([^\)]+\)/', '', $s)); }, explode(',', $passengers));
        }

        return [$it];
    }
}
