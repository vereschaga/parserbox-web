<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class ConfirmationPlain extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-2316882.eml, rapidrewards/it-6240544.eml, rapidrewards/it-6240550.eml";

    public $reFrom = "southwest.com";
    public $reBodyDetect = [
        'en' => ['Below are your itinerary details and helpful links to online checkin', 'Below you\'ll find your itinerary details and helpful links to online checkin'],
    ];

    public $reBody = [
        'en' => ['Itinerary Details', 'Depart'],
    ];
    public $reSubject = [
        '#Southwest\s+Airlines\s+Confirmation ([A-Z\d]+)#',
    ];
    public $lang = '';
    public $text;
    public static $dict = [
        'en' => [
            'startInfo' => 'Itinerary Details',
            'endInfo'   => ['HELPFUL LINKS'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        $this->AssignLang($body);
        $this->text = text($body);

        $its = $this->parseEmail();
        $name = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($name) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        if (stripos($body, 'Southwest') !== false) {
            $this->AssignLang($body);

            if ($this->AssignLang($body)) {
                if (isset($this->reBodyDetect[$this->lang]) && is_array($this->reBodyDetect[$this->lang])) {
                    $detect = $this->reBodyDetect[$this->lang];

                    foreach ($detect as $item) {
                        if (strpos($body, $item) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
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

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail()
    {
        $info = $this->findСutSection($this->text, $this->t('startInfo'), $this->t('endInfo'));

        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#\(Confirmation\s+([A-Z\d]+)\)#", $info);

        $nodes = $this->splitter("#(.+?\n\s*Depart)#", $info);

        foreach ($nodes as $root) {
            $seg = [];
            $date = strtotime($this->normalizeDate($this->re("#^(.+)\n#", $root)));

            foreach (['Depart' => 'Dep', 'Arrive in' => 'Arr'] as $str => $prefix) {
                $node = $this->re("#{$str}\s+(.+?)(?:\n|Arrive in)#", $root);

                if (preg_match("#(?<name>.+?)\s+\((?<code>[A-Z]{3})\)\s+(?:on\s+Flight\s+(?<flight>\d+)\s+)?at\s+(?<time>.+)#", $node, $m)) {
                    if (isset($m['flight']) && !empty($m['flight'])) {
                        $seg['FlightNumber'] = $m['flight'];
                    }
                    $seg[$prefix . 'Code'] = $m['code'];
                    $seg[$prefix . 'Name'] = $m['name'];
                    $seg[$prefix . 'Date'] = strtotime($m['time'], $date);
                }

                if (stripos($this->text,
                        'Thank you for purchasing travel on Southwest Airlines') !== false
                ) {
                    $seg["AirlineName"] = 'WN';
                }
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //Friday, April 28, 2017
            '#\w+,\s+(\w+)\s+(\d+),\s+(\d+)$#u',
        ];
        $out = [
            '$2 $1 $3',
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

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ((stripos($body, $reBody[0]) !== false) && (stripos($body, $reBody[1]) !== false)) {
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
