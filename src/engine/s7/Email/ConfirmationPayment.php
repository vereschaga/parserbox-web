<?php

namespace AwardWallet\Engine\s7\Email;

class ConfirmationPayment extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "s7.ru";
    public $reBody = [
        'ru' => ['Бронь', 'Подтверждение оплаты'],
    ];
    public $reSubject = [
        'Подтверждение оплаты заказа на сайте www.s7.ru',
    ];
    public $lang = '';
    public $textPDF;
    public $pdfNamePattern = "seat.+\.pdf";
    public static $dict = [
        'ru' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $this->textPDF = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));
        }

        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ConfirmationPayment' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'s7.ru')] | //a[contains(@href,'s7.ru')]")->length > 0) {
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Бронь')]/following::text()[normalize-space(.)][1]");
        $it['Passengers'] = $this->http->FindNodes("//img[contains(@src,'arrow-route')]/ancestor::tr[1]/preceding::text()[normalize-space(.)][1]");
        $xpath = "//img[contains(@src,'arrow-route')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $seats = [];

        foreach ($nodes as $root) {
            $seg = [];

            if (!empty($this->textPDF) && $fl = $this->re("#Рейс[^\n]+\s+\w{2}(\d+)\s+#ms", $this->textPDF)) {
                $seg['FlightNumber'] = $fl;
            } else {
                $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            }
            $node = $this->http->FindNodes("./td[normalize-space(.)][1]//text()[normalize-space(.)]", $root);

            if (count($node) === 2) {
                $seg['DepDate'] = strtotime($this->normalizeDate($node[0]));
                $seg['DepName'] = $node[1];
            }
            $node = $this->http->FindNodes("./td[normalize-space(.)][2]//text()[normalize-space(.)]", $root);

            if (count($node) === 2) {
                $seg['ArrDate'] = strtotime($this->normalizeDate($node[0]));
                $seg['ArrName'] = $node[1];
            }

            if (isset($seg['DepName']) && isset($seg['ArrName']) && !empty($this->textPDF)) {
                $str1 = str_replace(')', '\)', str_replace('(', '\(', $seg['DepName']));
                $seg['DepCode'] = $this->re("#^\s*{$str1}.+?([A-Z]{3})#miu", $this->textPDF);

                if (empty($seg['DepCode'])) {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }
                $str2 = str_replace(')', '\)', str_replace('(', '\(', $seg['ArrName']));
                $seg['ArrCode'] = $this->re("#^\s*{$str1}.+?[A-Z]{3}.+?{$str2}.+?([A-Z]{3})#miu", $this->textPDF);

                if (empty($seg['ArrCode'])) {
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            }
            $seats[$seg['DepName'] . '-' . $seg['ArrName']][] = $this->http->FindSingleNode("./following::tr[contains(.,'Бронирование места')][1]/td[contains(.,'Бронирование места')]/following-sibling::td[normalize-space(.)][1]", $root);

            $it['TripSegments'][] = $seg;
        }
        $it['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $it['TripSegments'])));

        foreach ($it['TripSegments'] as &$seg) {
            if (isset($seg['DepName']) && isset($seg['ArrName']) && isset($seats[$seg['DepName'] . '-' . $seg['ArrName']])) {
                $seg['Seats'] = implode(",", $seats[$seg['DepName'] . '-' . $seg['ArrName']]);
            }
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+\s+\w+\d+,\s+\d+:\d+)\s*$#',
        ];
        $out = [
            '$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
            $body = $this->http->Response['body'];

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
