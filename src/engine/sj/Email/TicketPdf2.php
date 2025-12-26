<?php

namespace AwardWallet\Engine\sj\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TicketPdf2 extends \TAccountChecker
{
    public $mailFiles = "sj/it-231043742.eml, sj/it-231327995.eml, sj/it-36946043.eml, sj/it-65677103.eml";

    public $reFrom = ["noreply@biljett.sj.se"];
    public $reBody = [
        'sv' => ['Pris för denna resa:', 'Biljetten gäller endast tillsammans med'],
    ];
    public $reSubject = [
        '#E-biljett \- .+?, Bokningsnr: [A-Z\d]{5,}$#',
    ];
    public $trainSegments = [];
    public $bussSegments = [];
    public $travellersTrain = [];
    public $travellersBus = [];

    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'sv' => [
            'Biljettnummer' => 'Biljettnummer',
            'Avgång'        => 'Avgång',
            'Tåg'           => ['Tåg', 'T-bana'],
        ],
    ];
    private $keywordProv = ['.sj.se', 'SJ'];
    private $subject;

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

    public function ParseTrain(Email $email, $textPDF, $trainSegment, $date, \AwardWallet\Schema\Parser\Common\Train $r)
    {
        $this->logger->debug(__FUNCTION__);

        $segment = $trainSegment['text'];

        if (!empty($trainSegment['car'])) {
            $car = implode(', ', array_unique(array_filter($trainSegment['car'])));
        }

        if (!empty($trainSegment['seat'])) {
            $seats = array_unique(array_filter($trainSegment['seat']));
        }
        $tickets = array_unique(array_filter($trainSegment['ticket']));

        $regExp = "#\s*(?<dep>.+?) \- (?<arr>.+?)[ ]{3,}(?<type>.+?)? (?<cabin>\d kl)? (?<service>.+)\s+" .
            "{$this->opt($this->t('Avgång'))}[ ]+{$this->opt($this->t('Ankomst'))}[ ]+{$this->opt($this->t('Tåg'))}" .
            "(?:[ ]+{$this->opt($this->t('Vagn'))}[ ]+{$this->opt($this->t('Plats'))}|[^\n]*)\s+" .
            "(?<timeDep>\d+[:\.]\d+)[ ]+(?<timeArr>\d+[:\.]\d+)[ ]+(?<number>\d+[A-Z]*)(?:[ ]+(?<car>\d+)[ ]+(?<seat>\d+)|\n)" .
            "#";

        if (preg_match($regExp, $segment, $m)) {
            $dateDep = strtotime($this->normalizeTime($m['timeDep']), $date);
            $dateArr = strtotime($this->normalizeTime($m['timeArr']), $date);

            if ($dateDep > $dateArr) {
                $dateArr = strtotime('+1 day', $dateArr);
            }

            $s = $r->addSegment();
            $s->departure()
                ->date($dateDep)
                ->name('Sweden, ' . $m['dep']);
            $s->arrival()
                ->date($dateArr)
                ->name('Sweden, ' . $m['arr']);

            if (isset($m['type']) && !empty(trim($m['type']))) {
                $s->extra()
                    ->type($m['type']);
            }

            if (isset($m['cabin']) && !empty(trim($m['cabin']))) {
                $s->extra()
                    ->cabin($m['cabin']);
            }

            $s->extra()
                ->number($m['number']);

            if (!empty($car)) {
                $s->extra()
                    ->car($car);
            }

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
        }

        if (preg_match("#({$this->opt($this->t('SJ Kundservice'))}): ([\d\-\+ \(\)]+)\s+({$this->opt($this->t('Organisationsnummer'))}): ([\d\-\+ \(\)]+)#",
            $textPDF, $m)) {
            $r->program()
                ->phone($m[2], $m[1])
                ->phone($m[4], $m[3]);
        }

        if (preg_match_all("#{$this->t('Resenär:')}[ ]+(.+?)(?: SJ Prio Vit|\n)#", $textPDF, $m)) {
            foreach ($m[1] as $pax) {
                if (in_array($pax, $this->travellersTrain) == false) {
                    $this->travellersTrain[] = $pax;
                    $r->general()
                        ->traveller($pax, true);
                }
            }
        }

        if (count($tickets) > 0) {
            $r->setTicketNumbers($tickets, false);
        }

        return true;
    }

    public function ParseBus(Email $email, $textPDF, $busSegment, $date, \AwardWallet\Schema\Parser\Common\Bus $b)
    {
        $this->logger->debug(__FUNCTION__);

        $segment = $busSegment['text'];

        $seats = array_unique(array_filter($busSegment['seat']));
        $tickets = array_unique(array_filter($busSegment['ticket']));

        $regExp = "#\s*(?<dep>.+?) \- (?<arr>.+?)[ ]{3,}(?<type>.+?)? (?<cabin>\d kl)? (?<service>.+)\s+" .
            "{$this->opt($this->t('Avgång'))}[ ]+{$this->opt($this->t('Ankomst'))}[ ]+{$this->opt($this->t('Buss'))}" .
            "(?:[ ]+{$this->opt($this->t('Plats'))}|[^\n]*)\s+" .
            "(?<timeDep>\d+[:\.]\d+)[ ]+(?<timeArr>\d+[:\.]\d+)[ ]+(?<number>\d+)(?:[ ]+(?<seat>[\dA-Z]+)|\n)" .
            "#";

        if (preg_match($regExp, $segment, $m)) {
            $dateDep = strtotime($this->normalizeTime($m['timeDep']), $date);
            $dateArr = strtotime($this->normalizeTime($m['timeArr']), $date);

            if ($dateDep > $dateArr) {
                $dateArr = strtotime('+1 day', $dateArr);
            }

            $s = $b->addSegment();
            $s->departure()
                ->date($dateDep)
                ->name('Sweden, ' . $m['dep']);
            $s->arrival()
                ->date($dateArr)
                ->name('Sweden, ' . $m['arr']);

            if (isset($m['cabin']) && !empty(trim($m['cabin']))) {
                $s->extra()
                    ->cabin($m['cabin']);
            }

            $s->extra()
                ->number($m['number']);

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
        }

        if (preg_match("#({$this->opt($this->t('SJ Kundservice'))}): ([\d\-\+ \(\)]+)\s+({$this->opt($this->t('Organisationsnummer'))}): ([\d\-\+ \(\)]+)#",
            $textPDF, $m)) {
            $b->program()
                ->phone($m[2], $m[1])
                ->phone($m[4], $m[3]);
        }

        if (preg_match_all("#{$this->t('Resenär:')}[ ]+(.+?)(?: SJ Prio Vit|\n)#", $textPDF, $m)) {
            foreach ($m[1] as $pax) {
                if (in_array($pax, $this->travellersBus) == false) {
                    $this->travellersBus[] = $pax;
                    $b->general()
                        ->traveller($pax, true);
                }
            }
        }

        if (count($tickets) > 0) {
            $b->setTicketNumbers($tickets, false);
        }
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        if (preg_match_all("#{$this->t('Pris för denna resa')}: (?<total>.+?) varav (?<tax>.+?)\s+moms och bokningsavgift\s+(?<booking>.+)#",
            $textPDF, $m)) {
            $total = $tax = $fee = 0.0;
            $cur = null;

            foreach ($m['total'] as $t) {
                $sum = $this->getTotalCurrency($t);
                $total += $sum['Total'];

                if (!isset($cur)) {
                    $cur = $sum['Currency'];
                }
            }

            foreach ($m['tax'] as $t) {
                $sum = $this->getTotalCurrency($t);
                $tax += $sum['Total'];

                if (!isset($cur)) {
                    $cur = $sum['Currency'];
                }
            }

            if (isset($m['booking']) && !empty(trim($m['booking'][0]))) {
                foreach ($m['booking'] as $t) {
                    $sum = $this->getTotalCurrency($t);
                    $fee += $sum['Total'];

                    if (!isset($cur)) {
                        $cur = $sum['Currency'];
                    }
                }
            }

            if (!empty($total)) {
                $email->price()
                    ->total($total)
                    ->tax($tax)
                    ->fee($this->t('bokningsavgift'), $fee)
                    ->currency($cur);
            }
        }

        $nodes = $this->splitter("#({$this->opt($this->t('Personlig'))}\s+{$this->opt($this->t('BILJETT'))})#",
            "ctrlStr\n" . $textPDF);

        if (count($nodes) == 0) {
            return false;
        }

        foreach ($nodes as $ticket) {
            $segments = $this->splitText($ticket, "#({$this->t('Biljettnummer')}.+)#", true);
            $date = $this->re("#\n[ ]*{$this->opt($this->t('Giltig'))}:[ ]+(.+?)[ ]{3,}#", $ticket);

            //it-231327995.eml
            if (stripos($date, '--') !== false) {
                $date = strtotime($this->re("/^(\d{4}\-\d+\-\d+)\s*\-\-/", $date));
            } else {
                $date = strtotime($date);
            }

            foreach ($segments as $segment) {
                if (preg_match("/{$this->opt($this->t('Tåg'))}/", $segment)) {
                    if (preg_match("/\s*(?<dep>\d+\.\d+)\s*(?<arr>\d+\.\d+)\s*(?<number>\d+[A-Z]*)(?:[ ]+(?<car>\d+)[ ]+(?<seat>\d+[A-Z]*)|\n)/", $segment, $m)) {
                        $key = $m['dep'] . '-' . $m['arr'] . '-' . $m['number'];

                        if (in_array($key, $this->trainSegments) == false) {
                            $this->trainSegments[$key]['text'] = $segment;

                            $ticket = $this->re("/^\s*{$this->t('Biljettnummer')}[ ]+([A-Z\d]{7,})/m", $segment);

                            if (!empty($ticket)) {
                                $this->trainSegments[$key]['ticket'][] = $ticket;
                            }

                            if (isset($m['car']) && !empty($m['car'])) {
                                $this->trainSegments[$key]['car'][] = $m['car'];
                            }

                            if (isset($m['seat']) && !empty($m['seat'])) {
                                $this->trainSegments[$key]['seat'][] = $m['seat'];
                            }
                        }
                    }
                } elseif (preg_match("/{$this->opt($this->t('Buss'))}/", $segment)) {
                    if (preg_match("/\s*(?<dep>\d+\.\d+)\s*(?<arr>\d+\.\d+)\s*(?<number>\d+[A-Z]*)(?:[ ]+(?<seat>\d+[A-Z]*)|\n)/", $segment, $m)) {
                        $key = $m['dep'] . '-' . $m['arr'] . '-' . $m['number'];

                        if (in_array($key, $this->bussSegments) == false) {
                            $this->bussSegments[$key]['text'] = $segment;

                            $ticket = $this->re("/^\s*{$this->t('Biljettnummer')}[ ]+([A-Z\d]{7,})/m", $segment);

                            if (!empty($ticket)) {
                                $this->bussSegments[$key]['ticket'][] = $ticket;
                            }

                            if (isset($m['seat']) && !empty($m['seat'])) {
                                $this->bussSegments[$key]['seat'][] = $m['seat'];
                            }
                        }
                    }
                }
            }
        }

        if (count($this->trainSegments) > 0) {
            $r = $email->add()->train();

            if (preg_match("#E-biljett \- .+?, ({$this->t('Bokningsnr')}): ([A-Z\d]{5,})$#", $this->subject, $m)) {
                $r->general()
                    ->confirmation($m[2], $m[1]);
            } else {
                $r->general()
                    ->noConfirmation();
            }

            foreach ($this->trainSegments as $trainSegment) {
                $this->ParseTrain($email, $textPDF, $trainSegment, $date, $r);
            }
        }

        if (count($this->bussSegments) > 0) {
            $b = $email->add()->bus();

            if (preg_match("#E-biljett \- .+?, ({$this->t('Bokningsnr')}): ([A-Z\d]{5,})$#", $this->subject, $m)) {
                $b->general()
                    ->confirmation($m[2], $m[1]);
            } else {
                $b->general()
                    ->noConfirmation();
            }

            foreach ($this->bussSegments as $busSegment) {
                $this->ParseBus($email, $textPDF, $busSegment, $date, $b);
            }
        }

        return true;
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
            if (isset($words['Biljettnummer'], $words['Avgång'])) {
                if (strpos($body, $words["Biljettnummer"]) !== false && strpos($body, $words['Avgång']) !== false) {
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

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
