<?php

namespace AwardWallet\Engine\skywards\Email;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "skywards/it-10425855.eml";

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['Flight Details'],
    ];

    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'skywards@vibe.travel') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        return stripos($headers['subject'], 'Your Reward Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $from = $parser->getHeader('from');
        $subject = $parser->getHeader('subject');

        $condition1 = $this->http->XPath->query('//a[contains(@href,"www.emirates.com")]')->length === 0;
        $condition2 = self::detectEmailFromProvider($from) || self::detectEmailByHeaders(['from' => $from, 'subject' => $subject]);

        if ($condition1 && $condition2 === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'YourFlight_' . $this->lang,
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

    protected function parseEmail()
    {
        $patterns = [
            'nameCode' => '/^(.+)\s+\(([A-Z]{3})\)$/',
            'date'     => '\d{1,2}\s+[^,.\d\s]{3,}\s+\d{4}',
            'time'     => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?',
        ];

        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Your Booking Reference Number")]', null, true, '/([A-Z\d]{5,})$/');

        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//tr[./descendant::text()[normalize-space(.)="Outbound" or normalize-space(.)="Return"] and not(.//tr)]/following-sibling::tr[1]/td[contains(.,"Dep")]');

        foreach ($segments as $segment) {
            $seg = [];

            $segmentTexts = $this->http->FindNodes('./descendant::text()[normalize-space(.)]', $segment);
            $textSegment = implode("\n", $segmentTexts);

            if (preg_match('/^(.+)\sTo\s(.+)$/m', $textSegment, $matches)) {
                if (preg_match($patterns['nameCode'], $matches[1], $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];
                } else {
                    $seg['DepName'] = $matches[1];
                }

                if (preg_match($patterns['nameCode'], $matches[2], $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrCode'] = $m[2];
                } else {
                    $seg['ArrName'] = $matches[1];
                }
            }

            if (preg_match('/^Dep\s+.*?(' . $patterns['date'] . ')\s+(' . $patterns['time'] . ')$/m', $textSegment, $matches)) {
                $seg['DepDate'] = strtotime($matches[1] . ', ' . $matches[2]);
            }

            if (preg_match('/^Arr\s+.*?(' . $patterns['date'] . ')\s+(' . $patterns['time'] . ')$/m', $textSegment, $matches)) {
                $seg['ArrDate'] = strtotime($matches[1] . ', ' . $matches[2]);
            }

            if (preg_match('/^Flight\s*(\d+)$/mi', $textSegment, $matches)) {
                $seg['FlightNumber'] = $matches[1];
            }

            if (!empty($this->http->FindSingleNode('(//text()[contains(normalize-space(.),"receive a confirmation email from easyJet")])[1]'))) {
                $seg['AirlineName'] = 'U2';
            } else {
                $seg['AirlineName'] = AIRLINE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        $passenger = $this->http->FindSingleNode('//text()[normalize-space(.)="Passenger Details"]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]/descendant::text()[normalize-space(.)][1]', null, true, '/^([^}{]+)$/');

        if ($passenger) {
            $it['Passengers'] = [$passenger];
        }

        if (!empty($miles = $this->http->FindSingleNode('//text()[normalize-space(.)="Total Travel"]/ancestor::*[self::td or self::th][1]/following-sibling::*[self::td or self::th][normalize-space(.)!=""][1]'))) {
            $it['SpentAwards'] = $miles . ' miles';
        }

        $tripNumber = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Please use the reference number")]/ancestor::*[1]', null, true, '/Please use the reference number\s*([A-Z\d]{5,})/');

        if ($tripNumber) {
            $it['TripNumber'] = $tripNumber;
        }

        return $it;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
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
