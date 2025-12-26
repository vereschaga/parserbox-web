<?php

namespace AwardWallet\Engine\mabuhay\Email;

class EBoardingPass extends \TAccountChecker
{
    public $mailFiles = "mabuhay/it-9453847.eml";

    public $from = "webcheckin@philippineairlines.com";
    public $provider = "@philippineairlines.com";

    public $reSubject = [
        'en' => 'e-boarding pass',
    ];
    public $reBody = [
        'en' => 'Please review boarding pass information',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $plainText = $parser->getPlainBody();
        $its = $this->parseEmail($plainText);

        return [
            'emailType'  => "EBoardingPass",
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->from) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        foreach ($this->reBody as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->provider) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail($plainText)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        if (preg_match("#(.+)\n+\s*PNR\s*([A-Z\d]{6})#", $plainText, $m)) {
            $it['RecordLocator'] = $m[2];
            $it['Passengers'][] = trim($m[1]);
        }
        $seg = [];

        if (preg_match("#\n+\s*Seat\s*(\d{1,3}[A-Z])\s+#", $plainText, $m)) {
            $seg['Seats'][] = $m[1];
        }

        if (preg_match("#\n+\s*Class\s*([A-Z]{1,2})\s+#", $plainText, $m)) {
            $seg['BookingClass'] = $m[1];
        }

        if (preg_match("#\n+\s*Flight\s*(\d{1,5})\s+#", $plainText, $m)) {
            $seg['FlightNumber'] = $m[1];
            $seg['AirlineName'] = 'PR';
        }

        if (preg_match("#\n+\s*DEP\s+(.+)#", $plainText, $m)) {
            $seg['DepName'] = trim($m[1]);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        if (preg_match("#\n+\s*ARR\s+(.+)#", $plainText, $m)) {
            $seg['ArrName'] = trim($m[1]);
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        if (preg_match("#\n+\s*Date\s*(\d{1,2})([A-Z]{3,10})(\d{4})\s+Time\s*(\d{1,2})(\d{2})\s+#", $plainText, $m)) {
            $seg['DepDate'] = strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . $m[4] . ':' . $m[5]);
            $seg['ArrDate'] = MISSING_DATE;
        }

        $it['TripSegments'][] = $seg;

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
