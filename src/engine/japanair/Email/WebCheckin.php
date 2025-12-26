<?php

namespace AwardWallet\Engine\japanair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class WebCheckin extends \TAccountChecker
{
    public $mailFiles = "japanair/it-56631504.eml, japanair/it-57297601.eml";

    private $detectFrom = "@jal.com";

    private $detectSubject = [
        'en' => 'Notice of  Web Check-in Result',
        'zh' => '辦理網上登機手續通知',
    ];
    private $detectBodyHtml = [
        'en' => 'Thank you for using Japan Airlines Web Check-in Service',
        'zh' => '日本航空網上辦理登機服務',
    ];
    private $detectBodyPDF = [
        'en' => 'Departure Time:',
    ];

    private $pdfNamePattern = ".*\.pdf";
    private $errorHtmlSegment = false;
    private $errorPdfSegment = false;
    private $lang = 'en';
    private static $dictionary = [
        'en' => [
            //            "Passenger Name" => "",
            //            "Reference" => "",
            //            "Flight" => "",
            //            "Departure Information" => "",
            //            "Arrival Information" => "",
        ],
        'zh' => [
            "Passenger Name"        => "乘客",
            "Reference"             => "訂位編號",
            "Flight"                => "航班",
            "Departure Information" => "出發資訊",
            "Arrival Information"   => "到達資訊",
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $segmentsPdf = [];

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, 'Japan Airlines') === false) {
                continue;
            }

            foreach ($this->detectBodyPDF as $detectBody) {
                if (stripos($textPdf, $detectBody) == true) {
                    $segmentsPdf = array_merge($segmentsPdf, $this->parseFlightPdf($email, $textPdf));
                }
            }
        }

        foreach ($this->detectBodyHtml as $lang => $dBody) {
            if ($this->http->XPath->query('//node()[' . $this->contains($dBody) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $segmentsHtml = $this->parseFlightHtml($email);

        $this->segmentMerge($email, $segmentsHtml, $segmentsPdf);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[' . $this->contains($this->detectBodyHtml) . ']')->length > 0) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, 'Japan Airlines') === false) {
                continue;
            }

            foreach ($this->detectBodyPDF as $detectBody) {
                if (stripos($textPdf, $detectBody) === true) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseFlightHtml(Email $email)
    {
        $segments = [];
        $text = $this->htmlToText($this->http->Response['body']);

        $stexts = [];
        $regexp = "#(?:^|\n)\s*(" . $this->preg_implode($this->t("Passenger Name")) . "[ ]*[:：][ ]*[\s\S]+?" . $this->preg_implode($this->t("Arrival Information")) . "[ ]*[:：].+)#u";

        if (preg_match_all($regexp, $text, $m)) {
            $stexts = $m[1];
        }

        foreach ($stexts as $stext) {
            $seg = [];

            if (preg_match("#" . $this->preg_implode($this->t("Passenger Name")) . "[ ]*[:：][ ]*(.+)#u", $stext, $m)) {
                $seg['travellers'][] = $m[1];
            }

            if (preg_match("#" . $this->preg_implode($this->t("Reference")) . "[ ]*[:：][ ]*(.+)#u", $stext, $m)) {
                $seg['rl'] = $m[1];
            }

            if (preg_match("#" . $this->preg_implode($this->t("Flight")) . "[ ]*[:：][ ]*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})[ ]*/#u", $stext, $m)) {
                $seg['al'] = $m['al'];
                $seg['fn'] = $m['fn'];
            }

            if (preg_match("#" . $this->preg_implode($this->t("Departure Information")) . "[ ]*[:：][ ]*(.+?) - ([^-]*\d{4}.*)#u", $stext, $m)) {
                $seg['from'] = $m[1];
                $seg['ddate'] = $this->normalizeDate($m[2]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Arrival Information")) . "[ ]*[:：][ ]*(.+?) - ([^-]*\d{4}.*)#u", $stext, $m)) {
                $seg['to'] = $m[1];
                $seg['adate'] = $this->normalizeDate($m[2]);
            }

            if (empty($seg['al']) || empty($seg['fn']) || empty($seg['ddate'])) {
                $this->errorHtmlSegment = true;
            }

            $found = false;

            if (!empty($seg['al']) && !empty($seg['fn']) && !empty($seg['ddate'])) {
                foreach ($segments as $sseg) {
                    if ($sseg['al'] == $seg['al']
                        && $sseg['fn'] == $seg['fn']
                        && $sseg['ddate'] == $seg['ddate']
                    ) {
                        foreach (['travellers', 'seats'] as $fieldName) {
                            if (isset($seg[$fieldName])) {
                                $sseg[$fieldName] = (!empty($sseg[$fieldName])) ?
                                    array_merge($sseg[$fieldName], $seg[$fieldName]) : $seg[$fieldName];
                            }
                        }
                        $found = true;
                    }
                }
            }

            if ($found === false) {
                $segments[] = array_filter($seg);
            }
        }

        return $segments;
    }

    private function parseFlightPdf(Email $email, string $text)
    {
        $segments = [];
        $stexts = preg_split("#\n[ ]*" . $this->preg_implode($this->t("Copyright © Japan Airlines")) . ".*#", $text);

        if (count($stexts) > 1) {
            array_pop($stexts);
        }

        foreach ($stexts as $sText) {
            $seg = [];
            unset($date);

            if (preg_match("#\n[ ]+" . $this->preg_implode($this->t("Name:")) . "[ ]{2,}.+\n[ ]+(?<traveller>.+?)[ ]{2,}(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) (?<fn>\d{1,5})[ ]{2,}(?<date>.*\d{4})#", $sText, $m)) {
                $seg['travellers'][] = $m['traveller'];
                $seg['al'] = $m['al'];
                $seg['fn'] = $m['fn'];

                $date = $this->normalizeDate($m['date']);
            }

            if (preg_match("#\n[ ]+" . $this->preg_implode($this->t("FFP Number")) . "[ ]{2,}.+\n{1,3}.{10,}[ ]{2,}(?<ffp>\w{5,})?[ ]{2,}(?:.*)[ ]{2,}(?<ticket>\d{13})\s*\n#", $sText, $m)) {
                if (!empty($m['ffp'])) {
                    $seg['programs'][] = $m['ffp'];
                }
                $seg['tickets'][] = $m['ticket'];
            }

            if (preg_match("#\n[ ]+" . $this->preg_implode($this->t("From:")) . "[ ]{2,}.+\n(?<route>.+)#", $sText, $m)) {
                $routes = preg_split("#\s{2,}#", trim($m['route']));

                if (count($routes) === 2) {
                    $seg['from'] = $routes[0];
                    $seg['to'] = $routes[1];

                    if (preg_match("#\n[ ]*" . $routes[1] . "[ ]{2,}(?<seat>\d{1,3}[A-Z])[ ]{2,}(?<cabin>\S.+?) Class#", $sText, $match)) {
                        $seg['seats'][] = $match['seat'];
                        $seg['cabin'] = $match['cabin'];
                    }

                    if (!empty($date) && preg_match("#\n[ ]*" . $routes[1] . "[ ]{2,}(?:.*\n){1,2}[ ]{0,10}(\d{1,2}:\d{2})\s+#", $sText, $match)) {
                        $seg['ddate'] = strtotime($match[1], $date);
                    }
                }
            }

            if (empty($seg['al']) || empty($seg['fn']) || empty($seg['ddate'])) {
                $this->errorPdfSegment = true;
            }

            $found = false;

            if (!empty($seg['al']) && !empty($seg['fn']) && !empty($seg['ddate'])) {
                foreach ($segments as $sseg) {
                    if ($sseg['al'] == $seg['al']
                        && $sseg['fn'] == $seg['fn']
                        && $sseg['ddate'] == $seg['ddate']
                    ) {
                        foreach (['travellers', 'seats', 'programs', 'tickets'] as $fieldName) {
                            if (isset($seg[$fieldName])) {
                                $sseg[$fieldName] = (!empty($sseg[$fieldName])) ?
                                    array_merge($sseg[$fieldName], $seg[$fieldName]) : $seg[$fieldName];
                            }
                        }
                        $found = true;
                    }
                }
            }

            if ($found === false) {
                $segments[] = array_filter($seg);
            }
        }

        return $segments;
    }

    private function segmentMerge(Email $email, array $segmentsHtml, array $segmentsPdf)
    {
        $segments = [];

        if (empty($segmentsHtml) && empty($segmentsPdf)) {
            return $email;
        } elseif (empty($segmentsHtml) || empty($segmentsPdf)) {
            $segments = (!empty($segmentsHtml)) ? $segmentsHtml : $segmentsPdf;
        } else {
            $usePdf = false;

            if ($this->errorHtmlSegment == true && $this->errorPdfSegment == false) {
                $usePdf = true;
                $segmentsHtml = $segmentsPdf;
            }

            foreach ($segmentsHtml as $key => $sHtml) {
                if ($usePdf == true || empty($sHtml['al']) || empty($sHtml['fn']) || empty($sHtml['ddate'])) {
                    $segments[] = $sHtml;

                    continue;
                }
                $found = false;

                foreach ($segmentsPdf as $sPdf) {
                    if (empty($sPdf['al']) || empty($sPdf['fn']) || empty($sPdf['ddate'])) {
                        continue;
                    }

                    if ($sHtml['al'] == $sPdf['al']
                        && $sHtml['fn'] == $sPdf['fn']
                        && $sHtml['ddate'] == $sPdf['ddate']
                    ) {
                        $sHtml = array_merge($sPdf, $sHtml);

                        break;
                    }
                }
                $segments[] = $sHtml;
            }
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $travellers = [];
        $programs = [];
        $tickets = [];

        foreach ($segments as $seg) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($seg['al'] ?? null)
                ->number($seg['fn'] ?? null)
            ;

            if (!empty($seg['rl'])) {
                $s->airline()->confirmation($seg['rl']);
            }

            // Departure
            $s->departure()
                ->noCode()
                ->name($seg['from'] ?? null)
                ->date($seg['ddate'])
            ;

            // Arrival
            $s->arrival()
                ->noCode()
                ->name($seg['to'] ?? null)
            ;

            if (!empty($seg['adate'])) {
                $s->arrival()->date($seg['adate']);
            } else {
                $s->arrival()->noDate();
            }

            // Extra
            if (isset($seg['cabin'])) {
                $s->extra()->cabin($seg['cabin']);
            }

            if (isset($seg['seats'])) {
                $s->extra()->seats(array_unique($seg['seats']));
            }

            if (isset($seg['travellers'])) {
                $travellers = array_merge($travellers, $seg['travellers']);
            }

            if (isset($seg['programs'])) {
                $programs = array_merge($programs, $seg['programs']);
            }

            if (isset($seg['tickets'])) {
                $tickets = array_merge($tickets, $seg['tickets']);
            }
        }

        if (!empty($travellers)) {
            $travellers = array_unique(array_filter($travellers));
            $f->general()->travellers($travellers);
        }

        if (!empty($programs)) {
            $programs = array_unique(array_filter($programs));
            $f->program()->accounts($programs, false);
        }

        if (!empty($tickets)) {
            $tickets = array_unique(array_filter($tickets));
            $f->issued()->tickets($tickets, false);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
            $s = preg_replace('/&nbsp/', " ", $s);
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{1,2})\s?([^\d\W]+)\s?(\d{4})\s*-\s*(\d{1,2}:\d{1,2})\s*$#u", // 10Mar2020 - 08:20
            "#^\s*(\d{1,2})\s?([^\d\W]+)\s?(\d{4})\s*$#u", // 10MAR 2020
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }
}
