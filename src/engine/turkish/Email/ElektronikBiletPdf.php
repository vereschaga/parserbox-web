<?php

namespace AwardWallet\Engine\turkish\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// html parsed in turkish/It3017869
class ElektronikBiletPdf extends \TAccountChecker
{
    public $mailFiles = "turkish/it-28610321.eml";

    public $reFrom = ["eticketitinerary@thy.com"];
    public $reBody = [
        ['ELEKTRONİK BİLET YOLCU SEYAHAT BELGESİ', 'ELECTRONIC TICKET PASSENGER ITINERARY'],
    ];
    public $reSubject = [
        'THY - Elektronik Bilet Yolcu Seyahat Belgesi',
    ];
    private $dateRelative;
    private $pdfNamePattern = "Elektronik Bilet.*\.pdf";
    private $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        foreach ($pdfs as $i => $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if (!$this->detectText($text)) {
                    $this->logger->debug('can\'t detect by Body in ' . $i . '-attach');

                    continue;
                } else {
                    $this->parseEmailPdf($text, $email);
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[normalize-space()='From/To']")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Rezervasyon No / Booking Ref']")->length > 0
        ) {
            $this->logger->debug("got to parse by It3017869.php");

            return false;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if (stripos($text, 'www.thy.com') !== false && $this->detectText($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
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

    public static function getEmailLanguages()
    {
        return ['tr'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        if ($this->http->XPath->query("//text()[normalize-space()='From/To']")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Rezervasyon No / Booking Ref']")->length > 0
        ) {
            $this->logger->debug("got to parse by It3017869.php");

            return true;
        }

        $textPDF = preg_replace("/\n\s+ARNK\n\n\n/", "", $textPDF);

        $r = $email->add()->flight();

        // General
        $dateRes = $this->re("/Issue Date +: +\w+ *\/ *(\w+)\s+/u", $textPDF);

        if (empty($dateRes)) {
            $dateRes = $this->re("/Issue Date\s+\:\s*(\d+\w+\d{2,4})/u", $textPDF);
        }

        $this->dateRelative = $this->normalizeDate($dateRes);

        $traveller = $this->re("/Passenger Name +: +([[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]])(?: (?:MR|MS|MRS))?\s*\n/", $textPDF);
        $r->general()
            ->date($this->dateRelative)
            ->confirmation($this->re("/Rezervasyon No \/ Booking Ref +: +([A-Z\d]{5,})/", $textPDF))
            ->traveller($traveller);

        // Program
        $account = $this->re("/\/ Payment +: +.*-TK(\d{5,})\s*\n/", $textPDF);

        if (!empty($account)) {
            $r->program()
                ->account($account, false);
        }

        // Issued
        $tickets = explode(" ", $this->re("/Bilet No \/ Ticket Number +: +([\d\s]{10,})\n/", $textPDF));

        foreach ($tickets as $ticket) {
            $r->issued()
                ->ticket($ticket, false, $traveller);
        }

        // Price
        $miles = $this->re("/Endorsmen \/ Restr +: +(\d+)MIL\./", $textPDF);

        if (!empty($miles)) {
            $r->price()
                ->spentAwards($miles);
        }
        $currency = $this->re("/ \/ Total +: +([A-Z]{3})\s*\d[\d,.]*A?\s*\n/", $textPDF);
        $total = $this->re("/ \/ Total +: +[A-Z]{3}\s*(\d[\d,.]*)A?\s*\n/", $textPDF);

        if (!empty($total)) {
            $r->price()
                ->currency($currency)
                ->total(PriceHelper::parse($total, $currency))
            ;

            $taxStr = trim(preg_replace('/(?:^|\n) *.* \/ Tax *: */', "\n", $this->re("/ \/ Base Fare +: +.*\n((?:(?: {20,}| *.* \/ Tax *: *).*\n)+) *.*\/ Total +: +/", $textPDF)));
            $taxes = explode(' ', preg_replace(['/(\d+[A-Z][A-Z\d])\s*\n\s*(\d)/', '/\s*\n\s*/'], ['$1 $2', ''], $taxStr));

            foreach ($taxes as $tax) {
                if (preg_match("/^(\d[\d., ]*)([A-Z][A-Z\d])$/", $tax, $m)) {
                    $r->price()
                        ->fee($m[2], PriceHelper::parse($m[1], $currency));
                }
            }
        }
        $cost = $this->re("/ \/ Base Fare +: +(.+)/", $textPDF);

        if (
            (!empty($currency) && preg_match("/^\s*({$currency}) *(\d[\d.,]*)\s*$/", $cost, $m))
            || (empty($currency) && preg_match("/^\s*([A-Z]{3}) *(\d[\d.,]*)\s*$/", $cost, $m))
        ) {
            $r->price()
                ->cost(PriceHelper::parse($m[2], $m[1]))
                ->currency($m[1])
            ;
        }

        $dateFormat = "\d{1,2}[[:alpha:]]+|\d{2}-\d{2}";
        /*
            ISTANBUL/IST                          02NİSAN 0800                                                      D
                             TK    2312     B                      B/     020    OK      OPEN
              IZMIR/ADB                           / 02APR 0920                                                      D
        */
        $regExp = "/"
            . "^[ ]*(?<dName>.+?)\/(?<dCode>[A-Z]{3}) {5,}(?<dDate>{$dateFormat}) (?:\/ )?(?<dHour>\d{2})(?<dMin>\d{2})(?:[ ]{34,}(?<dTerm>[A-Z\d]))?\s+"
            . "(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<flight>\d+)\s+(?<bookngClass>[A-Z]{1,2}).+\n"
            . "\s*(?<aName>.+?)\/(?<aCode>[A-Z]{3})[ ]{5,}(?:\/ *)?(?<aDate>{$dateFormat}) (?<aHour>\d{2})(?<aMin>\d{2})(?:[ ]{34,}(?<aTerm>[A-Z\d]))?"
            . "/u";

        /*
                                                26MART
            1100
            MILAN/MXP                           / 26MAR                                                            1
                  TK    1874    N                      DS0     030    OK      OPEN
            ISTANBUL/IST                          26MART                                                             I
         */
        $regExp2 = "#"
            . "^\s*(?<dHour>\d{2})(?<dMin>\d{2})\\s*\n"
            . "\s*(?<dName>.+?)/(?<dCode>[A-Z]{3})[\s/]{5,}(?<dDate>{$dateFormat})(?:\s{10,}(?<dTerm>[A-Z\d]))?\\s*\n"
            . "\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<flight>\d+)\s+(?<bookngClass>[A-Z]{1,2}).+?\n"
            . "\s*(?<aName>.+?)\/(?<aCode>[A-Z]{3})[ ]{5,}(?<aDate>{$dateFormat})(?:\s{10,}(?<aTerm>[A-Z\d]))?\s+(?<aHour>\d{2})(?<aMin>\d{2})"
            . "#u";
        /*
                        10-01 1520
            *ISTANBUL/IST                                                                                1
                              TK    0191    X                XBP    2P     OK    OPEN
             DALLAS/DFW                                                                                  0
                                                  10-01 1820                                                           I
         */
        $regExp3 = "#"
            . "^\s*(?<dDate>{$dateFormat}) (?<dHour>\d{2})(?<dMin>\d{2})\s*\n"
            . " *(?<dName>.+?)/(?<dCode>[A-Z]{3})(?: {20,}(?<dTerm>[A-Z\d]))?\s*\n"
            . " *(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<flight>\d+)\s+(?<bookngClass>[A-Z]{1,2}).+?\n"
            . " *(?<aName>.+?)\/(?<aCode>[A-Z]{3})(?: {20,}(?<aTerm>[A-Z\d]))?\s*\n"
            . " +(?<aDate>{$dateFormat}) (?<aHour>\d{2})(?<aMin>\d{2})"
            . "#u";
        /*
                      1810
              CHICAGO/ORD                     14-11                                                 1
                             UA     1249 X               XBP    0P     OK    OPEN
             BALTIMORE/BWI                                                                          0
                                                    2112
         */
        $regExp4 = "#"
            . "^\s*(?<dHour>\d{2})(?<dMin>\d{2})\s*\n"
            . " *(?<dName>.+?)/(?<dCode>[A-Z]{3})[ \/]{5,}(?<dDate>{$dateFormat}) (?: {10,}(?<dTerm>[A-Z\d]))?\s*\n"
            . " *(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<flight>\d+)\s+(?<bookngClass>[A-Z]{1,2}).+?\n"
            . " *(?<aName>.+?)\/(?<aCode>[A-Z]{3})(?: {20,}(?<aTerm>[A-Z\d]))?\s*\n"
            . " +(?<aHour>\d{2})(?<aMin>\d{2})"
            . "#u";

        /*ANKARA/ESB
        GAZIANTEP/GZT                                                                                           W
                  TK   4170   U   12-02 0700 12-02 0810 UEF      015     OK      OPEN
                                                                                                        C
        * ANADOLUJET**/

        $regExp5 = "#^[ ]*(?<dName>.+?)\/(?<dCode>[A-Z]{3})\n *(?<aName>.+?)\/(?<aCode>[A-Z]{3})\s*(?<dTerm>[A-Z])\s*\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<flight>\d+)\s+(?<bookngClass>[A-Z]{1,2})\s*(?<dDate>\d+\-\d+)\s*(?<dHour>\d{2})(?<dMin>\d{2})\s*(?<aDate>\d+\-\d+)\s*(?<aHour>\d{2})(?<aMin>\d{2})\s*[A-Z]+\s+\d+\s+[A-Z]+\s*[A-Z]+\s*(?<aTerm>[A-Z])\n#";

        /*
          BOGOTA/BOG                                                                                                W
                   TK    0801 J 14OCT 1635 15OCT 1640 JV3             2P    OK     OPEN 14OCT 14OCT
 ISTA     NBUL/IST                                                                                              C
         * */

        $regExp6 = "#(?<dName>.+?)\/(?<dCode>[A-Z]{3})\s*(?<dTerm>[A-Z])\n*\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<flight>\d+)\s+(?<bookngClass>[A-Z]{1,2})\s*(?<dDate>\d+\w+)\s*(?<dHour>\d{2})(?<dMin>\d{2})\s*(?<aDate>\d+\w+)?\s*(?<aHour>\d{2})(?<aMin>\d{2})\s.+\n\s*(?<aName>.+?)\/(?<aCode>[A-Z]{3})\s*(?<aTerm>[A-Z])#";

        $segmentsText = $this->re("/From\/To [^\n]+\s+(.+?)\n\n\n\n/s", $textPDF);
        $segments = $this->splitter("/^\s*((?:(?:{$dateFormat})? +\d{4}\b.*?\n\s*.+?\/[A-Z]{3}\s{5,}|.+?\/[A-Z]{3} {5,}[ \/]+\d+[[:alpha:]]+[ \/]+\d{4}|.+[A-Z]{3})(?:.*\n){1,3}?.+?\/[A-Z]{3})/um", $segmentsText);

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            if (preg_match($regExp, $segment, $m) || preg_match($regExp2, $segment, $m) || preg_match($regExp3, $segment, $m) || preg_match($regExp4, $segment, $m) || preg_match($regExp5, $segment, $m) || preg_match($regExp6, $segment, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flight']);
                $s->extra()->bookingCode($m['bookngClass']);
                $s->departure()
                    ->name(trim($m['dName'], '*'))
                    ->code($m['dCode'])
                ;
                $s->arrival()
                    ->name(trim($m['aName'], '*'))
                    ->code($m['aCode'])
                ;
                $date = $this->normalizeDate($m['dDate']);

                if (!empty($date)) {
                    $s->departure()
                        ->date(strtotime($m['dHour'] . ':' . $m['dMin'], $date));
                }

                if (empty($m['aDate'])) {
                    $m['aDate'] = $m['dDate'];
                }

                $date = $this->normalizeDate($m['aDate']);

                if (!empty($date)) {
                    $s->arrival()
                        ->date(strtotime($m['aHour'] . ':' . $m['aMin'], $date));
                }

                if (!empty($m['dTerm'])) {
                    $s->setDepTerminal($m['dTerm']);
                }

                if (!empty($m['aTerm'])) {
                    $s->setArrTerminal($m['aTerm']);
                }
            }
        }

        return true;
    }

    private function detectText($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = ' . print_r($date, true));
        $in = [
            // 10NOV21
            '/^\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{2})\s*$/iu',
            // 13JAN
            '/^\s*(\d{1,2})\s*([[:alpha:]]+)\s*$/i',
            // 06-12
            '/^\s*(\d{2})\s*-\s*(\d{2})\s*$/',
        ];
        $out = [
            '$1 $2 20$3',
            '$1 $2',
            '$1.$2',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#^(\d{2})\.(\d{2})\s*$#u", $date, $m)) {
            $date = $m[1] . ' ' . date("F", mktime(0, 0, 0, $m[2], 1, 2011)) . " ";
        }

        if (preg_match("#\d+\s+([[:alpha:]]{3,})(\s+\d{4})?\s*$#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            } elseif ($en = MonthTranslate::translate($m[1], 'tr')) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } elseif (!empty($this->dateRelative)) {
            $date = EmailDateHelper::parseDateRelative($date, $this->dateRelative);
        } else {
            $date = null;
        }

        return $date;
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
}
