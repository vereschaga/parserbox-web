<?php

namespace AwardWallet\Engine\priceline\Email;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "priceline/it-6723153.eml";

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@priceline.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'trans@priceline.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//a[contains(@href,"//jump.priceline.com") and contains(@href,"//www.priceline.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//img[contains(@alt,"Priceline.com")]')->length === 0;
        $condition3 = $this->http->XPath->query('//node()[contains(.,"priceline.com")]')->length === 0;

        if ($condition1 && $condition2 && $condition3) {
            return false;
        }

        if ($this->http->XPath->query('//node()[contains(.,"Arrives:")]')->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'CheckIn',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function normalizeDate($string)
    {
//        $this->logger->debug('$string = '.print_r( $string,true));
        if (preg_match('/([^\d\s]{3,})\s+(\d{1,2})[,]?\s+(\d{4})\s+at\s+(\d{1,2}:\d{2}\s*[AP]M)/', $string, $matches)) {
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
            $time = $matches[4];
        }

        if ($day && $month && $year && $time) {
            return $day . ' ' . $month . ' ' . $year . ', ' . $time;
        }

        return false;
    }

    protected function parseEmail()
    {
        $patterns = [
            'airport'  => '/(.+)\(([A-Z]{3})\)$/',
            'dateTime' => '//i',
        ];

        $it = [];
        $it['Kind'] = 'T';

        $passenger = $this->http->FindSingleNode('//td[not(.//td)]/descendant::text()[starts-with(normalize-space(.),"Hello")]', null, true, '/Hello\s*([^,]{2,}),/');

        if ($passenger) {
            $it['Passengers'] = [$passenger];
        }

        $it['RecordLocator'] = $this->http->FindSingleNode('//td[contains(normalize-space(.),"confirmation number is") and not(.//td)]', null, true, '/confirmation\s+number\s+is\s*([A-Z\d]{5,})/i');

        $it['TripSegments'] = [];
        $seg = [];

        $xpathFragment1 = '//tr[starts-with(normalize-space(.),"Departs:")]';
        $flight = $this->http->FindSingleNode($xpathFragment1 . '/preceding::*[normalize-space(.) and not(.//*)][1]');

        if (preg_match('/(.+)\s+Flight\s+(\d+)$/i', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }

        $xpathFragment2 = $xpathFragment1 . '/td[normalize-space(.) and position()>1][last()]/descendant::text()[normalize-space(.)!="Departs" and normalize-space(.)]';
        $airportDep = $this->http->FindSingleNode($xpathFragment2 . '[last()-1]');

        if (preg_match($patterns['airport'], $airportDep, $matches)) {
            $seg['DepName'] = trim($matches[1]);
            $seg['DepCode'] = $matches[2];
        } elseif ($airportDep) {
            $seg['DepName'] = $airportDep;
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        }
        $dateDep = $this->http->FindSingleNode($xpathFragment2 . '[last()]');

        if ($dateDep) {
            $seg['DepDate'] = strtotime($this->normalizeDate($dateDep));
        }
        $seg['DepartureTerminal'] = trim(preg_replace("#\s*Terminal\s*#i", '', $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Terminal:')]/ancestor::td[1]/following-sibling::td[1]")));

        $xpathFragment3 = '//tr[starts-with(normalize-space(.),"Arrives:")]/td[normalize-space(.) and position()>1][last()]/descendant::text()[normalize-space(.)!="Arrives" and normalize-space(.)]';
        $airportArr = $this->http->FindSingleNode($xpathFragment3 . '[last()-1]');

        if (preg_match($patterns['airport'], $airportArr, $matches)) {
            $seg['ArrName'] = trim($matches[1]);
            $seg['ArrCode'] = $matches[2];
        } elseif ($airportArr) {
            $seg['ArrName'] = $airportArr;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        }
        $dateArr = $this->http->FindSingleNode($xpathFragment3 . '[last()]');

        if ($dateArr) {
            $seg['ArrDate'] = strtotime($this->normalizeDate($dateArr));
        }

        $it['TripSegments'][] = $seg;

        $it['TripNumber'] = $this->http->FindSingleNode('//td[contains(normalize-space(.),"trip number is") and not(.//td)]', null, true, '/trip\s+number\s+is\s*([-\d]+)/i');

        return $it;
    }
}
