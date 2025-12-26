<?php

namespace AwardWallet\Engine\maketrip\Email;

class BusETicket extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-6207034.eml, maketrip/it-6243856.eml";

    public $reFrom = "makemytrip.com";
    public $reBody = [
        'en' => ['Departure Time(Origin)', 'Franchise Partner'],
    ];
    public $reSubject = [
        'Your MakeMyTrip bus e-ticket for booking id',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "BusETicket" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Makemytrip' or contains(@src,'makemytrip')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], "makemytrip") !== false) {
            if (isset($this->reSubject)) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
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
        $it['RecordLocator'] = $this->nextField($this->t('PNR No:'));
        $it['TripCategory'] = TRIP_CATEGORY_BUS;
        $it['Passengers'][] = $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t('Name') . "']/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
        $it['TicketNumbers'][] = $this->nextField('Ticket No:');
        $it['ReservationDate'] = strtotime($this->nextField('Transaction Date & Time'));
        $node = $this->nextField('Total Chargable Amount');

        if (preg_match("#(.{3})\s*([\d\.]+)#", $node, $m)) {
            $it['TotalCharge'] = $m[2];
            $it['Currency'] = $m[1];
        }

        $seg['Seats'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Name']/ancestor::tr[1]/following-sibling::tr[1]/td[5]");
        $seg['DepName'] = $this->nextField('Boarding Point');

        if (strpos($this->nextField('Address'), 'N/A') === false) {
            $seg['DepAddress'] = $this->nextField('Address');
        }
        $seg['ArrName'] = $this->nextField('Alighting Point');
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $seg['DepDate'] = strtotime($this->nextField('Departure Time(Origin)'));
        $seg['ArrDate'] = strtotime($this->nextField('Alighting Time(Approximate)'), $seg['DepDate']);

        if ($seg['ArrDate'] < $seg['DepDate']) {
            $seg['ArrDate'] = strtotime('+ 1 day', $seg['ArrDate']);
        }
        $seg['Vehicle'] = $this->nextField('Bus Service Type');

        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function nextField($field)
    {
        return $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'{$field}')]/ancestor::td[1]/following-sibling::td[1])[1]");
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
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
}
