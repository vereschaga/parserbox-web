<?php

namespace AwardWallet\Engine\vivaaerobus\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "vivaaerobus/it-10934875.eml, vivaaerobus/it-11011685.eml";

    public $reFrom = "vivaaerobus.com";
    public $reBody = [
        'en'  => ['Get ready for your flight', 'Print your boarding pass now'],
        'en2' => ['It\'s time to check-in', 'are you ready for take-off'],
        'es'  => ['Es tiempo de que hagas tu check-in', 'Haz clic en el botón de Check-in'],
    ];
    public $reSubject = [
        'print your boarding pass now',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();
        //$its = $this->parseEmailUrl();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $url = $this->http->FindSingleNode("(//a[starts-with(@href,'http://email.vivaaerobus.com/c/')]/@href)[1]");

        if (!empty($url) && $this->http->XPath->query("//text()[normalize-space(.)='Flight Details']")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking reference')]", null, true, "#:\s+([A-Z\d]{5,})#");

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];
        $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Flight date')]");

        if (preg_match("#Flight date:\s+(.+)\s+\|\s+Flight Number: ([A-Z]{3})\s*(\d+)#", $node, $m)) {
            $date = $this->normalizeDate($m[1]);
            $seg['AirlineName'] = $m[2];
            $seg['FlightNumber'] = $m[3];
        }
        $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Origin')]/ancestor::tr[1]/following-sibling::tr[1]");

        if (preg_match("#(.+?)\s+-\s+(\d+\s*:\s*\d+.+)#i", $node, $m)) {
            $seg['DepName'] = $m[1];

            if (isset($date)) {
                $seg['DepDate'] = strtotime(preg_replace("#\s+#", '', $m[2]), $date);
            }
        }
        $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Destination')]/ancestor::tr[1]/following-sibling::tr[1]");

        if (preg_match("#(.+?)\s+-\s+(\d+\s*:\s*\d+.+)#i", $node, $m)) {
            $seg['ArrName'] = $m[1];

            if (isset($date)) {
                $seg['ArrDate'] = strtotime(preg_replace("#\s+#", '', $m[2]), $date);
            }
        }
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#[\S\s]*(\d{2})[\.\/]*(\d{2})[\.\/]*(\d{2})#',
            '#[\S\s]*(\d{2})-(\D{3,})-(\d{2})[.]*#',
        ];
        $out = [
            '$2/$1/$3',
            '$2 $1 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $reBody[0] . '")]')->length > 0
                    && $this->http->XPath->query('//*[contains(normalize-space(.),"' . $reBody[1] . '")]')->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    // for future
//	private function parseEmailUrl()
//	{
//		$url = $this->http->FindSingleNode("(//a[starts-with(@href,'http://email.vivaaerobus.com/c/')]/@href)[1]");
//		if (empty($url))
//			return [];
//		$this->http->LogHeaders = true;
//		$this->http->setRandomUserAgent();
//		$headers = [
//			"Accept"       => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
//			"Accept-Encoding"       => "gzip, deflate",
//			"Upgrade-Insecure-Requests" => 1,
//			"Accept-Language" => "ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7"
//		];
//		$this->http->GetURL($url, $headers);
//
//		$it = ['Kind' => 'T', 'TripSegments' => []];
//		if (preg_match("#[\?\&]locator=([A-Z\d]{5,})\&#", $this->http->currentUrl(), $m)) {
//			$it['RecordLocator'] = $m[1];
//		}
//		else return null;
//
//		$xpath = "//text()[normalize-space(.)='Salida']/ancestor::table[1]";
//		$nodes = $this->http->XPath->query($xpath);
//		foreach ($nodes as $root) {
//			/** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
//			$seg = [];
//			$node = $this->http->FindSingleNode("./descendant::tr[2]/td[2]", $root);
//			if (preg_match("#Vuelo\s+([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
//				$seg['AirlineName'] = $m[1];
//				$seg['FlightNumber'] = $m[2];
//			}
//			$date = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[3]/td[2]", $root));
//			$seg['DepDate'] = strtotime($this->http->FindSingleNode("./descendant::tr[4]/td[3]", $root, true, "#(\d+:\d+)\s*HRS#i"), $date);
//			$seg['ArrDate'] = strtotime($this->http->FindSingleNode("./descendant::tr[5]/td[3]", $root, true, "#(\d+:\d+)\s*HRS#i"), $date);
//			$node = $this->http->FindSingleNode("./descendant::tr[4]/td[2]", $root);
//			if (preg_match("#(.+?)(?:\s+-\s+Terminal\s+(.+)|$)#i", $node, $m)) {
//				$seg['DepName'] = $m[1];
//				if (isset($m[2]) && !empty($m[2]))
//					$seg['DepartureTerminal'] = $m[2];
//			}
//			$node = $this->http->FindSingleNode("./descendant::tr[5]/td[2]", $root);
//			if (preg_match("#(.+?)(?:\s+-\s+Terminal\s+(.+)|$)#i", $node, $m)) {
//				$seg['ArrName'] = $m[1];
//				if (isset($m[2]) && !empty($m[2]))
//					$seg['ArrivalTerminal'] = $m[2];
//			}
//
//			$it['TripSegments'][] = $seg;
//		}
//
//		return [$it];
//	}
}
