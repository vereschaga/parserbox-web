<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class MobilePass extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-27791689.eml, rapidrewards/it-3925235.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@\.]southwest\.com/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'])) {
            return false;
        }

        return stripos($headers['from'], 'reply@mbp.southwest.com') !== false
            || preg_match("/Mobile Boarding Pass \([A-Z\d]{6}\)/", $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->changeBody($parser);

        if ($this->http->XPath->query("//*[contains(normalize-space(.),'Before departure please review our') and ./a[contains(@href,'southwest')]]")->length > 0) {
            return true;
        } else {
            $body = $parser->getPlainBody();

            return strpos($body, 'Before departure please review our') !== false && stripos($body, 'southwest') !== false;
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->changeBody($parser);
        $its = [$this->ParseFlight()];

        if (!isset($its[0]["TripSegments"])) {
            $text = $parser->getPlainBody();

            return [
                'parsedData' => [
                    'BoardingPass' => [$this->ParseBPPlain($text)],
                    'Itineraries'  => [$this->ParseFlightPlain($text)],
                ],
                'emailType' => 'BoardingPass',
            ];
        }

        return [
            'parsedData' => [
                'BoardingPass' => [$this->ParseBP()],
                'Itineraries'  => $its,
            ],
            'emailType' => 'BoardingPass',
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

    protected function ParseBP()
    {
//        $this->logger->info('Used method: ' . __FUNCTION__);
        $result = [];
        $result['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'Conf#')]", null, true, '/Conf#\s+([A-Z\d]{6,})$/');
        $result['Passengers'] = [$this->http->FindSingleNode("//td[normalize-space(.)='Passenger']/following-sibling::td[1]")];
        $root = $this->http->XPath->query('//tr[contains(.,"Flight") and not(.//tr) and following-sibling::tr[contains(.,"Departs")]]/parent::*');

        if ($root->length === 0) {
            return [];
        }
        $root = $root->item(0);
        $result['FlightNumber'] = $this->http->FindSingleNode('./tr[contains(.,"Flight")]', $root, true, '/^Flight (\d+)$/');
        $result['DepCode'] = $this->http->FindSingleNode('./tr[contains(.,"Flight")]/following-sibling::tr[1]', $root, true, '/^.+\(([A-Z]{3})\) - .+ \([A-Z]{3}\)$/');
        $date = $this->http->FindSingleNode('.//td[contains(.,"Date")]/following-sibling::td');
        $time = $this->http->FindSingleNode('.//td[contains(.,"Departs")]/following-sibling::td');

        if ($date && $time) {
            $result['DepDate'] = strtotime($date . ', ' . $time);
        }
        $result['BoardingPassURL'] = $this->http->FindSingleNode('//*[text()[contains(normalize-space(.),"If this boarding pass is not displayed properly, please use our")]]/a[contains(normalize-space(.),"online version")]/@href');

        return $result;
    }

    protected function ParseBPPlain($body)
    {
//        $this->logger->info('Used method: ' . __FUNCTION__);
        $result = [];
        $body = preg_replace("#^(>+)#m", '', $body);

        if (preg_match("#\n\s*Flight\s*(\d{1,5})\s*\n#", $body, $m)) {
            $result['FlightNumber'] = $m[1];
        }

        if (preg_match("/\n\s*(?<depname>.+) \((?<depcode>[A-Z]{3})\)\s+-\s+(?<arrname>.+) \((?<arrcode>[A-Z]{3})\)\s*\n/", $body, $m)) {
            $result['DepCode'] = $m["depcode"];
        }

        if (preg_match("#\n\s*Date\s+\w+,\s*(\w+\s*\d{1,2},\s*\d{4})\s+#", $body, $mat1) && preg_match("#\n\s*Departs\s+(\d{1,2}:\d{2}(\s*[AP]M)?)\s*\n#", $body, $mat2)) {
            $result['DepDate'] = strtotime($mat1[1] . ', ' . $mat2[1]);
        }
        $result['BoardingPassURL'] = $this->http->FindSingleNode('//*[text()[contains(normalize-space(.),"If this boarding pass is not displayed properly, please use our")]]/a[contains(normalize-space(.),"online version")]/@href');

        return $result;
    }

    protected function ParseFlight()
    {
//        $this->logger->info('Used method: ' . __FUNCTION__);
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'Conf#')]", null, true, '/Conf#\s+([A-Z\d]{6,})$/');
        $it['AccountNumbers'] = array_filter([$this->http->FindSingleNode("//text()[starts-with(normalize-space(),'RR#')]", null, true, '/RR#\s*([\d]{6,})$/')]);
        $it['Passengers'] = [$this->http->FindSingleNode("//td[normalize-space(.)='Passenger']/following-sibling::td[1]")];
        $table = $this->http->XPath->query("//*[tr[1][contains(.,'Flight') and not(.//tr)]]");

        if ($table->length === 1) {
            $table = $table->item(0);
            $segment = [];

            if ($number = $this->http->FindSingleNode("tr[1]", $table, true, "/^Flight (\d+)$/")) {
                $segment['FlightNumber'] = $number;
                $segment['AirlineName'] = 'WN';
            }

            if (preg_match("/^(?<depname>.+) \((?<depcode>[A-Z]{3})\) - (?<arrname>.+) \((?<arrcode>[A-Z]{3})\)$/", $this->http->FindSingleNode("tr[2]", $table), $m)) {
                $segment = array_merge($segment, [
                    "DepCode" => $m["depcode"],
                    "DepName" => $m["depname"],
                    "ArrCode" => $m["arrcode"],
                    "ArrName" => $m["arrname"],
                ]);
            }
            $date = $this->http->FindSingleNode("tr/td[1][contains(.,'Date')]/following-sibling::td[1]", $table);
            $time = $this->http->FindSingleNode("tr/td[1][contains(.,'Departs')]/following-sibling::td[1]", $table);

            if ($date && $time) {
                $segment['DepDate'] = strtotime($date . ', ' . $time);
            }

            if (count($segment) > 5 && !empty($segment['DepDate'])) {
                $segment['ArrDate'] = MISSING_DATE;
            }
            $it['TripSegments'] = [$segment];
        }

        return $it;
    }

    protected function ParseFlightPlain($body)
    {
//        $this->logger->info('Used method: ' . __FUNCTION__);
        $it = ['Kind' => 'T'];
        $body = preg_replace("#^(>+)#m", '', $body);

        if (preg_match("#Conf\#\s+([A-Z\d]{5,7})\s+#", $body, $m)) {
            $it["RecordLocator"] = $m[1];
        }

        if (preg_match("#Passenger\s+(.+\s*\/\s*.+)\s+BOARDING#", $body, $m)) {
            $it["Passengers"][] = trim($m[1]);
        }

        if (preg_match("#\n\s*Flight\s*(\d{1,5})\s*\n#", $body, $m)) {
            $segment["FlightNumber"] = $m[1];
        }

        if (preg_match("/\n\s*(?<depname>.+) \((?<depcode>[A-Z]{3})\)\s+-\s+(?<arrname>.+) \((?<arrcode>[A-Z]{3})\)\s*\n/", $body, $m)) {
            $segment = array_merge($segment, [
                "DepCode" => $m["depcode"],
                "DepName" => $m["depname"],
                "ArrCode" => $m["arrcode"],
                "ArrName" => $m["arrname"],
            ]);
        }

        if (preg_match("#\n\s*Date\s+\w+,\s*(\w+\s*\d{1,2},\s*\d{4})\s+#", $body, $mat1) && preg_match("#\n\s*Departs\s+(\d{1,2}:\d{2}(\s*[AP]M)?)\s*\n#", $body, $mat2)) {
            $segment['DepDate'] = strtotime($mat1[1] . ', ' . $mat2[1]);

            if (!empty($segment['DepDate'])) {
                $segment['ArrDate'] = MISSING_DATE;
            }
        }

        if (isset($segment)) {
            $it['TripSegments'] = [$segment];
        }

        return $it;
    }

    private function changeBody($parser)
    {
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/html') > 1) {
            $texts = preg_replace("#------=_NextPart.*#", "\n", $texts);
            $texts = preg_replace("#\n--_\d{3}_.*#", "\n", $texts);
            $texts = preg_replace("#\n--Apple-Mail-.*#", "\n", $texts);
            $text = '';
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $i = 0;

            while ($posBegin1 !== false && $i < 50) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $str = substr($texts, $posBegin1, $posBegin - $posBegin1);

                $posEnd = stripos($texts, "Content-Type: ", $posBegin);
                $block = substr($texts, $posBegin, $posEnd - $posBegin);
                $posEnd = strripos($block, "\n\n");
                $block = substr($texts, $posBegin, $posEnd);

                if (preg_match("#: base64#is", $str)) {
                    $block = trim($block);
                    $block = htmlspecialchars_decode(base64_decode($block));

                    if (($blockBegin = stripos($block, '<blockquote')) !== false) {
                        $blockEnd = strripos($block, '</blockquote>', $blockBegin) + strlen('</blockquote>');
                        $block = substr($block, $blockBegin, $blockEnd - $blockBegin);
                    }
                    $text .= $block;
                } elseif (preg_match("#quoted-printable#s", $str)) {
                    $text .= quoted_printable_decode($block);
                } else {
                    $text .= htmlspecialchars_decode($block);
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }

            $this->http->SetEmailBody($text, true);
        }
    }
}
