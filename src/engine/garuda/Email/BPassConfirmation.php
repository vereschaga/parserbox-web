<?php

namespace AwardWallet\Engine\garuda\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BPassConfirmation extends \TAccountChecker
{
    public $mailFiles = "garuda/it-110003613.eml, garuda/it-1894534.eml, garuda/it-4970081.eml, garuda/it-4970082.eml";

    public $reBody = [
        'en' => ['Please find enclosed your boarding pass and print it', 'Please find enclosed a confirmation document of your journey', 'please fill in the data requested through the following URL'],
    ];

    public $reSubject = [
        'Your Boarding Pass Confirmation',
        'Your Email Confirmation',
    ];

    public $lang = 'en';
    public $bodyPDF;
    public $filePDF;

    public static $dict = [
        'en' => [
            'RecordLocator' => 'Booking Reference',
            'From'          => ['Partida:', 'From:', 'From'],
            'To'            => ['Chegada:', 'To:', 'To'],
        ],
    ];

    public $detectProv = [
        'garuda' => [
            'bodyProv' => ['Thank you for choosing Garuda Indonesia'],
            'from'     => ['@garuda-indonesia.com'],
        ],

        'mea' => [
            'bodyProv' => ['www.mea.com.lb', 'Middle East Airlines'],
            'from'     => ['@mea.com.lb'],
        ],
    ];

    public static function getEmailProviders()
    {
        return ['garuda', 'mea'];
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $pdfs = $parser->searchAttachmentByName('.+\.pdf');

        foreach ($pdfs as $pdf) {
            $this->bodyPDF = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $file = $parser->getAttachment($pdf);
            $this->filePDF = $this->re('/filename[=](.+\.pdf)/', $file['headers']['content-disposition']);
        }
        $this->parseEmail($email);

        if ($code = $this->getProvider()) {
            $email->setProviderCode($code);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->AssignLang($body);

        return
            stripos($body, $this->reBody[$this->lang][0]) !== false
            || stripos($body, $this->reBody[$this->lang][1]) !== false
            || stripos($body, $this->reBody[$this->lang][2]) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
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
        foreach ($this->detectProv as $prov) {
            foreach ($prov['from'] as $word) {
                if (stripos($from, $word) !== false) {
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

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//span[contains(.,'" . $this->t('RecordLocator') . ":')]/following-sibling::span[1]"))
            ->traveller($this->http->FindSingleNode("//span[contains(.,'" . $this->t('Passenger') . ":')]/following-sibling::span[1]"));

        $node = $this->http->FindSingleNode("//span[contains(.,'" . $this->t('Flight') . ":')]/following-sibling::span[1]");

        $s = $f->addSegment();

        if (isset($node) && preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
            $s->airline()
                ->number($m[2])
                ->name($m[1]);
        }

        $s->departure()
            ->name($this->http->FindSingleNode("//span[" . $this->eq($this->t('From')) . "]/following-sibling::span[1]"))
            ->date(strtotime(str_replace("/", ".", str_replace("-", " ", $this->http->FindSingleNode("//span[" . $this->eq($this->t('From')) . "]/following-sibling::span[last()]")))))
            ->noCode();

        $depTerminal = $this->http->FindSingleNode("//span[" . $this->eq($this->t('From')) . "]/following-sibling::span[normalize-space(.)][position() > 1 and position() < last()]");

        if (!empty($depTerminal)) {
            $s->departure()
                ->terminal($depTerminal);
        }

        $s->arrival()
            ->name($this->http->FindSingleNode("//span[" . $this->eq($this->t('To')) . "]/following-sibling::span[1]"))
            ->date(strtotime(str_replace("/", ".", str_replace("-", " ", $this->http->FindSingleNode("//span[" . $this->eq($this->t('To')) . "]/following-sibling::span[last()]")))))
            ->noCode();

        if (empty($s->getArrDate())) {
            $s->arrival()
                ->noDate();
        }

        $arrTerminal = $this->http->FindSingleNode("//span[" . $this->eq($this->t('To')) . "]/following-sibling::span[normalize-space(.)][position() > 1 and position() < last()]");

        if (!empty($arrTerminal)) {
            $s->arrival()
                ->terminal($arrTerminal);
        }

        //prov - mea, it-110003613.eml
        if (!empty($this->bodyPDF) && preg_match("/FLIGHT\s*SEAT\s*BOARDING TIME\s*GATE\n/", $this->bodyPDF)) {
            $boardingInfo = $this->re("/FLIGHT\s*SEAT\s*BOARDING TIME\s*GATE\n+(\s*{$s->getAirlineName()}{$s->getFlightNumber()}.+)FROM.+TRAVEL\s*INFORMATION/s", $this->bodyPDF);
            $boardingTable = $this->splitCols($boardingInfo);
            $s->extra()
                ->seats(array_unique(array_filter(explode("\n", $boardingTable[1]))));

            $account = $this->re("/FREQUENT FLYER\n+.+\s([A-Z]{2}\s*\d{5,})\n/u", $this->bodyPDF);

            if (!empty($account)) {
                $f->program()
                    ->account($account, false);
            }

            $ticket = $this->re("/TICKET\s*ETKT\s*(\d{10,})\n/s", $this->bodyPDF);

            if (!empty($ticket)) {
                $f->issued()
                    ->ticket($ticket, false);
            }

            $b = $email->add()->bpass();
            $b->setAttachmentName($this->filePDF);
            $b->setFlightNumber($s->getFlightNumber());
            $b->setDepDate($s->getDepDate());

            if (!empty($s->getDepCode())) {
                $b->setDepCode();
            }
            $b->setTraveller($f->getTravellers()[0][0]);
        }

        //prov - garuda
        if (!empty($this->bodyPDF) && preg_match("/FLIGHT\s*DATE\s*DEPARTURE\s*SEQ[#]\s*GATE\s*SEAT\s*BOARDING TIME\n/", $this->bodyPDF)) {
            if (preg_match("/[A-Z\d]{2}\d{2,4}.+\s*\D+\s+(\d{2}[A-Z])\s*[\d\:]+\n/", $this->bodyPDF, $m)) {
                $s->extra()
                    ->seat($m[1]);
            }

            if (preg_match("/\n\s*[A-Z\d]{5,}\s*ETKT\s*(?<account>[A-Z\d\/]+)\s*(?<cabin>\w+\s*CLASS)\n\s*(?<ticket>\d{10,})/", $this->bodyPDF, $m)) {
                $s->extra()
                    ->cabin($m['cabin']);

                $f->issued()
                    ->ticket($m['ticket'], false);

                $f->program()
                    ->account($m['account'], false);
            }

            $b = $email->add()->bpass();
            $b->setAttachmentName($this->filePDF);
            $b->setFlightNumber($s->getFlightNumber());
            $b->setDepDate($s->getDepDate());

            if (!empty($s->getDepCode())) {
                $b->setDepCode();
            }

            if (isset($f->getTravellers()[0][0])) {
                $b->setTraveller($f->getTravellers()[0][0]);
            }
        }
    }

    private function eq($field, $node = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
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
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }

    private function getProvider()
    {
        foreach ($this->detectProv as $provName => $prov) {
            foreach ($prov['bodyProv'] as $word) {
                if ($this->http->XPath->query("//text()[contains(normalize-space(), '{$word}')]")->length > 0) {
                    return $provName;
                }
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
