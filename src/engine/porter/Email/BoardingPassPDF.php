<?php

namespace AwardWallet\Engine\porter\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "porter/it-685896686.eml, porter/it-761145145.eml, porter/it-764044258.eml, porter/it-782472419.eml, porter/it-782488507.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $subjects = [
        "Boarding pass / Carte d'embarquement",
        'You are checked in',
    ];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@notifications.flyporter.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "Porter Airlines") === false
                && strpos($text, "PorterClassic") === false
                && $this->http->XPath->query("//node()[contains(., 'www.flyporter.com') or contains(@href, 'flyporter.com')]")->length === 0
            ) {
                continue;
            }

            if ((strpos($text, 'Boarding / Embarquement') !== false || strpos($text, 'Airport Handling / Services a l’aeroport') !== false)
                && strpos($text, 'This boarding pass must be printed on paper') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]notifications\.flyporter.com$/', $from) > 0;
    }

    public function ParseBoardingPassPDF(Email $email, $text, $travellersNamesFile)
    {
        $f = $email->add()->flight();

        $conf = $this->re("/Reservation \/ Reservation\n*.*\n*.*\s([A-Z\d]{6})\n/", $text);
        $traveller = null;
        $travellerRows = $this->re("/\n {0,10}Name\s*\/\s*(?: +.*\n)?\s*Nom.*\n([\s\S]+?)\n *From \/ De/u", $text);

        preg_match_all("/^ {0,10}(\S+( \S+)*)( {2,}.*)?/mu", $travellerRows, $m);
        $travellerPart1 = $m[1][0] ?? '';
        $travellerText = trim(implode(" ", $m[1] ?? []));
        $travellerText = preg_replace("/ \d{1,2}:\d{2}( [ap]m)?$/im", '', $travellerText);

        if (preg_match("/^\s*(?<traveller1>[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]])\s*(?<notCompleted>\.\.\.)?\s*$/u", $travellerText, $m2)
        ) {
            if (!empty($m2['notCompleted'])) {
                foreach ($travellersNamesFile as $fName) {
                    if (stripos(preg_replace('/\W+/', '', $fName), preg_replace('/\W+/', '', $travellerText)) === 0) {
                        $traveller = $fName;

                        break;
                    }
                }
            } else {
                $traveller = preg_replace('/\s+/', ' ', $travellerText);
            }
        }

        $f->general()
            ->confirmation($conf)
            ->traveller($traveller);

        $accountText = preg_replace('/[^\d\n: ]+/', ' ', $travellerRows);
        $accountText = preg_replace('/ +\d{1,2}:\d{2} *$/m', ' ', $accountText);
        $account = preg_replace("/\s+/", " ", $this->re("/^\s*(\d{3}\s+\d{3}\s+\d{4})\s*$/", $accountText));

        if (!empty(trim($account))) {
            $f->addAccountNumber($account, false, $traveller, 'VIPorter Member');
        }

        // Segments
        $s = $f->addSegment();

        if (preg_match("/From \/ De.*(?:\n {20,}.*)?\n+ {0,10}(?<depName>\S.+)[ ]{10,}(?<arrName>[A-Z].+)(?:[ ]{10,}|\n {30,})(?:\-|[A-Z\d\-]+)[ ]+(?:(?<seat>\d+[A-Z])|STBY)/", $text, $m)) {
            $s->departure()
                ->name($m['depName']);

            $s->arrival()
                ->name($m['arrName']);

            if (!empty($m['seat'])) {
                $s->extra()
                    ->seat($m['seat'], false, false, $traveller);
            }
        }

        if (preg_match("/Reservation \/ Reservation.*\n*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s+(?<day>\d+)(?<month>\w+)(?<year>\d{2})\s+/u", $text, $m)) {
            $s->airline()
                ->name($m['aName'])
                ->number($m['fNumber']);

            $s->departure()
                ->day(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year']))
                ->noDate()
                ->noCode();

            $s->arrival()
                ->noCode()
                ->noDate();
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $applePkpass = $parser->searchAttachmentByName('PASSBOOK_.*');
        $names = [];

        foreach ($applePkpass as $ap) {
            $header = $parser->getAttachmentHeader($ap, 'Content-Disposition');

            if (preg_match('/name=[\"\']*PASSBOOK_([A-Z\-_]+)_([A-Z\d][A-Z]|[A-Z][A-Z\d])\d{1,4}_/i', $header, $m)) {
                $names[] = str_replace('_', ' ', $m[1]);
            }
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseBoardingPassPDF($email, $text, $names);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
