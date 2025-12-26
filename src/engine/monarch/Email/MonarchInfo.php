<?php

namespace AwardWallet\Engine\monarch\Email;

class MonarchInfo extends \TAccountChecker
{
    public $mailFiles = "";

    public $reBody = [
        'en' => ['Booking ', 'Reference'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Record locator' => 'Booking Reference',
            'Flight Details' => 'Your flight',
            'departing'      => 'departing',
            'arriving'       => 'arriving',
            'at'             => 'at',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'MonarchInfo',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//a[contains(@href,".monarch.")]')->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'monarch.co.uk') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'monarch.co.uk') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function ParseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//td[em and contains(.,'" . $this->t('Record locator') . "')]/text()", null, true, "#[A-Z\d]+#");

        $node = $this->http->FindSingleNode("//em[contains(.,'" . $this->t('Flight Details') . "')]");

        if (preg_match("#" . $this->t('Flight Details') . "\s+(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+).+\s+(?<DateFly>\d+\s*\w+\s*\d+)\s+" . $this->t('departing') . "\s+(?<DepName>.+)\s+" . $this->t('at') . "\s+(?<DepTime>\d{2}:\d{2}),\s+" . $this->t('arriving') . "\s+(?<ArrName>.+)\s+" . $this->t('at') . "\s+(?<ArrTime>\d+\:\d+)#", $node, $m)) {
            $segs = [];
            $segs['AirlineName'] = $m['AirlineName'];
            $segs['FlightNumber'] = $m['FlightNumber'];
            $segs['DepCode'] = TRIP_CODE_UNKNOWN;
            $segs['ArrCode'] = TRIP_CODE_UNKNOWN;
            $segs['DepName'] = $m['DepName'];
            $segs['ArrName'] = $m['ArrName'];

            $segs['DepDate'] = strtotime($m['DateFly'] . ' ' . $m['DepTime']);
            $segs['ArrDate'] = strtotime($m['DateFly'] . ' ' . $m['ArrTime']);

            $it['TripSegments'][] = $segs;
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
