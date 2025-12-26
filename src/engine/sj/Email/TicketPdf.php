<?php

namespace AwardWallet\Engine\sj\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TicketPdf extends \TAccountChecker
{
    public $mailFiles = "sj/it-231326485.eml, sj/it-36939358.eml";

    public $reFrom = ["noreply@biljett.sj.se"];
    public $reBody = [
        'sv' => ['Biljett, giltig', 'Biljetten är personlig och gäller'],
    ];
    public $reSubject = [
        '#E-biljett \- .+?, Bokningsnr: [A-Z\d]{5,}$#',
    ];
    public $lang = '';
    public $subject = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'sv' => [
            'Biljettnr'     => 'Biljettnr',
            'Bokningsnr'    => 'Bokningsnr',
            'Tåg'           => ['Tåg', 'Buss'],
        ],
    ];
    private $keywordProv = ['.sj.se', 'SJ'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->strposArray($text, $this->keywordProv)
                        && $this->detectBody($text)
                        && $this->assignLang($text)
                    ) {
                        $this->parseEmailPdf($text, $email);
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->strposArray($text, $this->keywordProv)
                && $this->detectBody($text)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && preg_match($reSubject, $headers["subject"]) > 0)
                    || $this->strposArray($headers["subject"], $this->keywordProv)
                ) {
                    return true;
                }
            }
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        $roots = [];
        $nodes = $this->splitter("#({$this->opt($this->t('Biljett, giltig'))})#", "ctrlStr\n" . $textPDF);

        if (count($nodes) == 0) {
            return false;
        }

        foreach ($nodes as $ticket) {
            $confNo = $this->re("#{$this->opt($this->t('Bokningsnr'))}:[ ]+([A-Z\d]{5,})\n#", $ticket);

            if (empty($confNo)) {
                $confNo = $this->re("/{$this->opt($this->t('Bokningsnr:'))}\s*([A-Z\d]+)/", $this->subject);
            }
            $roots[$confNo][] = $ticket;
        }

        foreach ($roots as $rl => $tickets) {
            $r = $email->add()->train();
            $r->general()
                ->confirmation($rl, $this->t('Bokningsnr'));

            if (preg_match("#({$this->opt($this->t('SJ Kundservice'))}): ([\d\-\+ \(\)]+), ({$this->opt($this->t('Organisationsnummer'))}): ([\d\-\+ \(\)]+)#",
                $tickets[0], $m)) {
                $r->program()
                    ->phone($m[2], $m[1])
                    ->phone($m[4], $m[3]);
            } elseif (preg_match("#\, ({$this->opt($this->t('Organisationsnummer'))}): ([\d\-\+ \(\)]+)#", $tickets[0], $m)) {
                $r->program()
                    ->phone($m[2], $m[1]);
            }

            foreach ($tickets as $ticket) {
                $info = $this->re("#{$this->opt($this->t('Biljettnr'))}:[^\n]+\n(.+)\n[ ]*(?:\d{3} ){6}\d{3}\s+#s", $ticket);

                $date = $this->re("#{$this->opt($this->t('Biljett, giltig'))}[ ]+(.+)#", $ticket);

                if (stripos($date, ' -') !== false) {
                    $date = strtotime($this->re("/^(\d{4}\-\d+\-\d+)\s*\-/", $date));
                } else {
                    $date = strtotime($date);
                }

                $r->addTicketNumber($this->re("#{$this->opt($this->t('Biljettnr'))}:[ ]+([A-Z\d]{7,})#", $ticket),
                    false);
                $r->addTraveller($this->re("#\n(.+?)(?:,[^\n]+)?\ns*{$this->opt($this->t('Biljetten är personlig och gäller'))}#",
                    $ticket), true);

                $regExp = "#(?<type>.+?), (?<cabin>.+?), .+\s+" .
                    "(?<dep>.+?)[ ]{3,}(?<arr>.+)\s+" .
                    "(?<timeDep>\d+[:\.]\d+)[ ]{3,}(?<timeArr>\d+[:\.]\d+)\s+" .
                    "{$this->opt($this->t('Tåg'))}.*\n\s*" .
                    "(?<number>\d+)(?<info>.*)" .
                    "#";

                if (preg_match($regExp, $info, $m)) {
                    $dateDep = strtotime($this->normalizeTime($m['timeDep']), $date);
                    $dateArr = strtotime($this->normalizeTime($m['timeArr']), $date);

                    if ($dateDep > $dateArr) {
                        $dateArr = strtotime('+1 day', $dateArr);
                    }

                    if (null === ($s = $this->getSegment($r, $m['number'], $m['type'], $m['dep'], $dateDep))) {
                        $s = $r->addSegment();
                        $s->departure()
                            ->date($dateDep)
                            ->name('Sweden, ' . $m['dep']);
                        $s->arrival()
                            ->date($dateArr)
                            ->name('Sweden, ' . $m['arr']);
                        $s->extra()
                            ->type($m['type'])
                            ->number($m['number'])
                            ->cabin($m['cabin'])
                        ;
                    }

                    if (isset($s) && preg_match("/[ ]{3,}(?<car>\d+)[ ]{3,}(?<seat>\d+),/", $m['info'], $mat)) {
                        $s->extra()->seat($mat['seat']);
                        $s->extra()
                            ->car($mat['car']);
                    }
                }
            }
        }

        return true;
    }

    private function getSegment(\AwardWallet\Schema\Parser\Common\Train $r, $number, $type, $dep, $dateDep)
    {
        foreach ($r->getSegments() as $s) {
            if ($s->getDepName() == $dep
                && $s->getNumber() == $number
                && $s->getDepDate() == $dateDep
                && $s->getTrainType() == $type
            ) {
                return $s;
            }
        }

        return null;
    }

    private function normalizeTime($str)
    {
        $in = [
            //12.15
            '#^(\d+)[:\.](\d+)$#u',
        ];
        $out = [
            '$1:$2',
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (strpos($body, $reBody[0]) !== false && strpos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Biljettnr'])) {
                if (strpos($body, $words['Biljettnr']) !== false) {
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

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function strposArray($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
