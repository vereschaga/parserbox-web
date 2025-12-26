<?php

namespace AwardWallet\Engine\monarch\Email;

class TimeChanged extends \TAccountChecker
{
    public $mailFiles = "monarch/it-8508690.eml";

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['We are sorry, but the time of your flight'],
    ];

    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Monarch Info') !== false
            || stripos($from, '@confirmations.monarch.co.uk') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Time change to Monarch flight') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"© Monarch") or contains(normalize-space(.),"Monarch - flights") or contains(.,"@confirmations.monarch.co.uk")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.monarch.co.uk") or contains(@href,"//reporting.flymonarch.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'TimeChanged_' . $this->lang,
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parseEmail()
    {
        $it = [];

        $passenger = $this->http->FindSingleNode('//img[contains(@src,"/dear.") or normalize-space(@alt)="Dear"]/ancestor::td/following-sibling::td[normalize-space(.)][1]');

        if ($passenger) {
            $it['Passengers'] = [$passenger];
        }

        $segments = $this->http->XPath->query('//text()[normalize-space(.)="Flight Number:"]');

        if ($segments->length === 1) {
            $root = $segments->item(0);
            $it['Kind'] = 'T';

            $it['TripSegments'] = [];
            $seg = [];

            $flight = $this->http->FindSingleNode('./following::text()[normalize-space(.)][1]', $root);

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }

            $it['RecordLocator'] = $this->http->FindSingleNode('./ancestor::table[contains(normalize-space(.),"Original Departure:")]/ancestor::tr/following-sibling::tr[normalize-space(.)][1]/descendant::td[contains(normalize-space(.),"Booking Reference:") and not(.//td)]', $root, true, '/^[^:]+:\s*([A-Z\d]{5,})$/');

            $route = $this->http->FindSingleNode('./ancestor::table[contains(normalize-space(.),"Original Departure:")]/ancestor::tr/preceding-sibling::tr[contains(.," from ") and contains(.," to ")][1]', $root);

            if (preg_match('/flight\s+from\s+(?<depName>.+?)\s+to\s+(?<arrName>.+?)\s+has\s+changed/i', $route, $matches)) {
                $seg['DepName'] = $matches['depName'];
                $seg['ArrName'] = $matches['arrName'];
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (count($seg) > 3) {
                $seg['ArrDate'] = $seg['DepDate'] = MISSING_DATE;
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
