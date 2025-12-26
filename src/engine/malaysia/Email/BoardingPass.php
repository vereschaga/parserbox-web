<?php

namespace AwardWallet\Engine\malaysia\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "malaysia/it-22254752.eml, malaysia/it-26609491.eml, malaysia/it-6278538.eml, malaysia/it-6278834.eml";

    public $reFrom = "@malaysiaairlines.";
    public $reFromH = "Malaysia Airlines Pass";
    public $reBody = [
        'en' => ['you are checked-in on flight', 'THANK YOU FOR FLYING WITH MALAYSIA AIRLINES'],
    ];
    public $reSubject = [
        '#.+? is checked in - [A-Z\d]{2}\s*\d+, departing#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'mobileBP' => ['Your mobile boarding pass is', 'available online'],
        ],
    ];

    public $date;

    protected $its = [];
    protected $bps = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        if (!$this->AssignLang()) {
            if ($this->http->XPath->query("//a[contains(@href,'/www.boxbe.com/')]")->length > 0 && $this->http->XPath->query("//*[contains(normalize-space(.),'Malaysia Airlines Pass')]")->length > 0) {
                $this->changeBody($parser);
                $this->AssignLang();
            }
        }

        if (empty($this->lang)) {
            $this->http->Log("can't determinate language");

            return null;
        }

        $this->parseEmail();
        $result = [
            'emailType' => 'BoardingPass' . ucfirst($this->lang),
        ];

        if (!empty($this->its)) {
            $result['parsedData']['Itineraries'] = $this->its;
        }

        if (!empty($this->bps)) {
            $result['parsedData']['BoardingPass'] = $this->bps;
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'malaysiaairlines.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], $this->reFromH) !== false) {
            foreach ($this->reSubject as $re) {
                if (preg_match($re, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
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

        $it['RecordLocator'] = $this->re("#^\s*([A-Z\d]+)\s*$#", $this->nextText('PNR'));
        $it['Passengers'] = $this->http->FindNodes("//text()[starts-with(.,'Date')]/preceding::text()[normalize-space(.)][3]");
        $it['AccountNumbers'][] = $this->re("#^\s*([\w\s-]+)\s*$#", $this->nextText('FQTV'));
        $it['TicketNumbers'][] = $this->re("#^\s*([\w\s-]+)\s*$#", $this->nextText('TKNE'));

        $seg = [];
        $node = $this->http->FindSingleNode("//text()[starts-with(.,'Date')]/preceding::text()[normalize-space(.)][2]");

        if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
        }
        $node = $this->http->FindSingleNode("//text()[starts-with(.,'Date')]/preceding::text()[normalize-space(.)][1]");

        if (preg_match("#([A-Z]{3})\s+to\s+([A-Z]{3})#", $node, $m)) {
            $seg['DepCode'] = $m[1];
            $seg['ArrCode'] = $m[2];
        }

        $date = $this->nextText('Date');
        $time = $this->nextText('Depart', null, "#^\s*(\d+:\d+)\s*$#");

        if (!empty($date)) {
            $seg['DepDate'] = (!empty($time)) ? strtotime($this->normalizeDate($date . ' ' . $time)) : MISSING_DATE;
        }
        $time = $this->nextText('Arrive', null, "#^\s*(\d+:\d+)\s*$#");

        if (!empty($date)) {
            $seg['ArrDate'] = (!empty($time)) ? strtotime($this->normalizeDate($date . ' ' . $time)) : MISSING_DATE;
        }

        $seg['Seats'] = $this->nextText('Seat');
        $seg['Cabin'] = $this->nextText('Cabin');

        $it['TripSegments'][] = $seg;

        //BoardingPass info
        if (isset($seg['FlightNumber']) && isset($seg['DepCode']) && isset($seg['DepDate']) && isset($it['RecordLocator']) && isset($it['Passengers'])) {
            $node = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'{$this->t('mobileBP')[0]}')]/following::a[contains(.,'{$this->t('mobileBP')[1]}')]/@href");

            if (!empty($node)) {
                $bp = [];
                $bp['FlightNumber'] = $seg['FlightNumber'];
                $bp['DepCode'] = $seg['DepCode'];
                $bp['DepDate'] = $seg['DepDate'];
                $bp['RecordLocator'] = $it['RecordLocator'];
                $bp['Passengers'] = $it['Passengers'];
                $bp['BoardingPassURL'] = $node;
                $this->bps[] = $bp;
            }
        }

        $it = array_filter($it);
        $this->its[] = $it;

        return true;
    }

    private function nextText($field, $root = null, $regexp = null)
    {
        return $this->http->FindSingleNode("//text()[starts-with(.,'{$field}')]/following::text()[normalize-space(.)][1]", $root, true, $regexp);
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
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0 && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function changeBody($parser)
    {
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/html') > 1) {
            $texts = preg_replace("#------=_NextPart.*#", "\n", $texts);
            $texts = preg_replace("#\n--_\d{3}_.*#", "\n", $texts);
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

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);

        $in = [
            //08Aug  14:00
            '#(\d{2})\s*(\w+)\s+(\d+:\d+)#',
        ];
        $out = [
            '$1 $2 ' . $year . ' $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
