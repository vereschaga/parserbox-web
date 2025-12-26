<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\lner\Email;

class TrainDetails extends \TAccountChecker
{
    public $mailFiles = "lner/it-8128366.eml";

    public $reFrom = "virgintrains";

    public $reBody = [
        'en' => [
            'just a quick confirmation that your booking has gone through',
        ],
    ];

    public $reSubject = [
        '',
    ];

    public $lang = '';

    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ($this->http->XPath->query("//a[contains(@href,'virgintrains')] | //img[contains(@src,'virgintrains')] | //text()[contains(normalize-space(), 'Virgin Trains East Coast')]")->length > 0) {
            foreach ($this->reBody as $lang => $detects) {
                foreach ($detects as $detect) {
                    if (stripos($body, $detect) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false;
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

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        $total = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Total payment')]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (!empty($total)) {
            $it['TotalCharge'] = $this->amount($total);
            $it['Currency'] = $this->currency($total);
        }
        $xpath = "//tr[contains(., 'Journey')]/following-sibling::tr[contains(., 'Reservations')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            $seg = [];

            $date = $this->http->FindSingleNode('preceding-sibling::tr[1]', $root, true, '/(\d+\/\d+\/\d{2,4})/');

            $seg['DepDate'] = $this->normalizeDate($date . ', ' . $this->getNode($root));

            $seg['ArrDate'] = $this->normalizeDate($date . ', ' . $this->getNode($root, 1, 2));

            $seg['DepName'] = $this->getNode($root, 3, 1);

            $seg['ArrName'] = $this->getNode($root, 3, 2);

            $seatsType = $this->http->FindSingleNode("descendant::td[contains(., 'seats') and not(.//td)]", $root);

            if (preg_match('/coach\s+([A-Z\d]{1,3}),\s+seats:\s+([A-Z\d\s]+)/i', $seatsType, $m)) {
                $seg['Type'] = $m[1];
                $seg['Seats'] = explode(' ', trim($m[2]));
            }

            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

            if (!empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '/(\d+)\/(\d+)\/(\d+),\s+(\d+:\d+)/',
        ];
        $out = [
            '$2/$1/$3, $4',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }

    private function getNode(\DOMNode $root, $td = 1, $tr = 1, $re = null)
    {
        return $this->http->FindSingleNode("descendant::*[count(td)=2]/td[1]/descendant::*[count(td)=3]/td[" . $td . "]//tr[" . $tr . "]", $root, true, $re);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
