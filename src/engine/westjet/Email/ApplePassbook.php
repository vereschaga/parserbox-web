<?php

namespace AwardWallet\Engine\westjet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ApplePassbook extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "westjet/it-141056555.eml, westjet/it-142209022.eml, westjet/it-4626710.eml, westjet/it-4629168.eml, westjet/it-4723186.eml";

    public $reBody = [
        'en' =>[
            'Attached is your Apple Passbook',
            'Attached is your electronic boarding pass',
            'Attached are your electronic boarding passes',
            'your boarding pass is attached for your flight',
        ],
    ];
    public $reSubject = [
        // en
        'Passbook',
        'e-BP',
        'Electronic Boarding Pass and Apple Wallet',
        'Electronic boarding pass',
    ];
    public $lang = 'en';
    public $imagesNames;
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $images = $parser->searchAttachmentByName('.*[A-Z]{3}-[A-Z]{3}\s*\(\s*[A-Z\d]{5,7}\s*\)\s*\.jpg');

        foreach ($images as $img) {
            $this->imagesNames[] = $this->getAttachmentName($parser, $img);
        }

        $type = '';
        if ($this->http->XPath->query("//img/@src")->length > 3
        || $this->http->XPath->query("//node()[count(.//text()[normalize-space()]) < 10 and count(.//text()[contains(., '•')]) > 1]")->length > 0) {
            $this->parseEmail2($email, $parser->getSubject());
            $type = '2';
        } else {
            $this->parseEmail($email, $parser->getSubject());
            $type = '1';
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $type);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->AssignLang($body);

        return $this->AssignLang($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], "@eBP.westjet.com") === false) {
            return false;
        }
        foreach ($this->reSubject as $subj) {
            if (strpos($headers["subject"], $subj) !== false) {
                return true;
            }
        }
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@eBP.westjet.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 2;
    }

    private function parseEmail(Email $email, $emailSubject)
    {
        $f = $email->add()->flight();
        $isbp = false;
        // General
        $travellers = array_filter([$this->http->FindSingleNode("(//body//text()[string-length(normalize-space(.))>1])[1]",
            null, true, "/^\s*[A-Z][A-Z \-]+\s*$/")]);
        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("(//body//ul)[1]/li",
                null, "/^\s*[A-Z][A-Z \-]+\s*$/");
        }
        $f->general()
            ->travellers($travellers);

        if (count($this->imagesNames) == count($travellers)) {
            $isbp = true;
            $bp = $email->add()->bpass();
        }

        // Issued
        $ticket = $this->http->FindSingleNode("//table//td[contains(.,'" . $this->t('Ticket') . "')]/ancestor::tr[1]/td[2]");
        if (!empty($ticket)) {
            $f->issued()
                ->ticket($ticket, false);
        }

        // Program
        $node = $this->http->FindSingleNode("//table//td[contains(.,'" . $this->t('WESTJET REWARDS') . "')]/ancestor::tr[1]/td[2]");
        if ($node !== null) {
            $f->program()
                ->account($node, false);
        }

        $s = $f->addSegment();

        $node = $this->http->FindSingleNode("//table//td[contains(.,'" . $this->t('Flight') . "')]/ancestor::tr[1]/td[2]");

        if (($node !== null) && (preg_match('#([A-Z\d]{2})\s*(\d+)#', trim($node), $m))) {
            $s->airline()
                ->name($m[1])
                ->number($m[2])
            ;
            if ($isbp) {
                $bp->setFlightNumber($m[1].' '. $m[2]);
            }
        }

        if (preg_match("#\s+([A-Z]{3})\s*\-\s*([A-Z]{3})\s*\((.+)\)#", $emailSubject, $m)) {
            $s->departure()
                ->code($m[1]);
            $s->arrival()
                ->code($m[2]);

            $f->general()
                ->confirmation($m[3]);
            if ($isbp) {
                $bp
                    ->setDepCode($m[1])
                    ->setRecordLocator($m[3])
                ;

            }
        }
        $datefly = $this->http->FindSingleNode("//table//td[contains(.,'" . $this->t('Date') . "')]/ancestor::tr[1]/td[2]");
        $timeDep = $this->http->FindSingleNode("//table//td[contains(.,'" . $this->t('Depart') . "')]/ancestor::tr[1]/td[2]");

        if (!empty($datefly) && !empty($timeDep)) {
            $s->departure()
                ->date(strtotime($datefly . " " . $timeDep));

            $s->arrival()
                ->noDate();
            if ($isbp) {
                $bp
                    ->setDepDate(strtotime($datefly . " " . $timeDep))
                ;
            }
        }

        if ($isbp) {
            $bpMain = $bp->toArray();
            foreach ($travellers as $i => $traveller) {
                if ($i > 0) {
                    $bp = $email->add()->bpass();
                    $bp = $bp->fromArray($bpMain);
                }

                if (preg_match("/.*{$m[1]}-{$m[2]}\s*\(\s*{$m[3]}\s*\)\s*\.jpg/", $this->imagesNames[$i])) {
                    $bp->setAttachmentName($this->imagesNames[$i]);
                }
                $bp->setTraveller($travellers[$i]);
            }

        }

        //        if ($isbp) {
//            $bp->setTraveller($travellers);
//        }

        return $email;
    }

    private function parseEmail2(Email $email, $emailSubject)
    {
        $f = $email->add()->flight();
        $isbp = false;
        // General
        $travellers = $this->http->FindNodes("//text()[normalize-space() = 'GUESTS']/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[normalize-space() = 'GUESTS'])][last()]//text()[normalize-space()]",null, "/^\s*[A-Z][A-Z \-]+\s*$/");
        $f->general()
            ->travellers($travellers);

        if (count($this->imagesNames) == count($travellers)) {
            $isbp = true;
            $bp = $email->add()->bpass();
        }


        $s = $f->addSegment();

        $node = $this->http->FindSingleNode("//img[contains(@src, 'aircraft.png')]/ancestor::*[normalize-space()][1]/following::text()[normalize-space()][1]/ancestor::*[position() < 3][contains(., '•')][1]");
        if (preg_match("/^\s*(?<date>[^•]+)\s*•\s*(?<time>\d{1,2}:\d{2}(?:[ap]m)?)\s*•\s*(?<al>[A-Z\d]{2})\s*(?<fn>\d{1,5})\s*$/", $node, $m)) {
            // Feb 17, 2022 • 09:30 • WS 2652

            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;

            $s->departure()
                ->date(strtotime($m['date'] . " " . $m['time']));

            $s->arrival()
                ->noDate();

            if ($isbp) {
                $bp
                    ->setFlightNumber($m['al'].' '. $m['fn'])
                    ->setDepDate(strtotime($m['date'] . " " . $m['time']))
                ;
            }
        }

        if (preg_match("#\s+([A-Z]{3})\s*\-\s*([A-Z]{3})\s*\((.+)\)#", $emailSubject, $m)) {
            $s->departure()
                ->code($m[1]);
            $s->arrival()
                ->code($m[2]);

            $f->general()
                ->confirmation($m[3]);
            if ($isbp) {
                $bp
                    ->setDepCode($m[1])
                    ->setRecordLocator($m[3])
                ;

            }
        }

        if ($isbp) {
            $bpMain = $bp->toArray();
            foreach ($travellers as $i => $traveller) {
                if ($i > 0) {
                    $bp = $email->add()->bpass();
                    $bp = $bp->fromArray($bpMain);
                }

                if (preg_match("/.*{$m[1]}-{$m[2]}\s*\(\s*{$m[3]}\s*\)\s*\.jpg/", $this->imagesNames[$i])) {
                    $bp->setAttachmentName($this->imagesNames[$i]);
                }
                $bp->setTraveller($travellers[$i]);
            }

        }

        //        if ($isbp) {
//            $bp->setTraveller($travellers);
//        }

        return $email;
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
        foreach ($this->reBody as $lang => $reBody) {
            foreach ($reBody as $rB) {
                if (stripos($body, $rB) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.jpg)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }
}
