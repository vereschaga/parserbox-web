<?php

namespace AwardWallet\Engine\korean\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketItinerary extends \TAccountChecker
{
    public $mailFiles = "korean/it-13214825.eml, korean/it-16735045.eml, korean/it-16803579.eml, korean/it-16918827.eml, korean/it-35988205.eml, korean/it-633937833.eml, korean/it-639209420.eml, korean/it-814997564.eml";

    public static $dictionary = [
        "en" => [
            // 'From[ ]+To[ ]+Flight' => '',
            'mainSectionEnd2' => ['Fare Calculation', 'Please review the attached'],
            'segTopEnd'       => ['operates in', 'doing business as', 'Please use the terminal', 'Please use the'],
            // 'Class'           => '',
            // 'Status'          => '',
            // 'Seat number'     => '',
            // 'Aircraft Type'   => '',
            // 'Flight Duration' => '',
            // 'SKYPASS Miles'   => '',
            'Reservation Information' => ['Reservation Information', 'Notice on Pre-Selected Seat Change', 'Confirmed Standby Booking', 'Please refer to the PDF file attached to this mail for details'],
        ],
        "ko" => [
            'From[ ]+To[ ]+Flight' => '출발 From[ ]+도착 To[ ]+편명 Flight',
            'mainSectionEnd2'      => ['Ticket Fare Information', 'Baggage Information'],
            'segTopEnd'            => '운항합니다',
            'Class'                => '예약등급',
            'Status'               => '예약상태',
            'Seat number'          => '좌석번호',
            'Aircraft Type'        => '기종',
            'Flight Duration'      => '비행시간',
            'SKYPASS Miles'        => 'SKYPASS 마일리지',
        ],
        "ja" => [
            'From[ ]+To[ ]+Flight' => '出発地 From[ ]+到着地 To[ ]+便名 Flight',
            'mainSectionEnd2'      => ['Ticket Fare Information', 'Baggage Information'],
            'segTopEnd'            => '',
            'Class'                => '予約クラス',
            'Status'               => '予約状態',
            'Seat number'          => '座席番号',
            'Aircraft Type'        => '機種',
            'Flight Duration'      => '所要時間',
            'SKYPASS Miles'        => 'スカイパスマイル',
        ],
    ];

    private $reFrom = "koreanair";
    private $reSubject = [
        'en' => 'e-Ticket Itinerary/Receipt of Koreanair for',
        'ko' => '님의 대한항공 e-티켓 확인증입니다',
        'ja' => '様の大韓航空e-チケットのお客様控えです。',
    ];

    private $reBody = 'koreanair';
    private $reBody2 = [
        "ja"   => "e-チケットお客様控え",
        "ko"   => "비행시간",
        "ko2"  => "여정",
        "en"   => "Flight Duration",
        "en2"  => "you have completed your ticket purchase",
    ];

    private $namePrefixes = ['MSTR', 'MR', 'MS', 'MST']; // for all languages
    private $pdfPattern = ".*\.pdf";

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                    continue;
                }
                $text = str_replace(chr(194) . chr(160), '', $text);

                foreach ($this->reBody2 as $lang => $re) {
                    if (strpos($text, $re) !== false) {
                        $this->lang = substr($lang, 0, 2);

                        if (preg_match("#" . $this->t("From[ ]+To[ ]+Flight") . "#", $text)) {
                            $this->flight2($email, $text);
                            $type = '2';
                        } else {
                            $this->flight($email, $text);
                            $type = '1';
                        }

                        break;
                    }
                }
            }
        } else {
            $this->flightHTML($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $type);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                    continue;
                }
                $text = str_replace(chr(194) . chr(160), '', $text);

                if (false === strpos($text, $this->reBody)
                    && false === stripos($text, 'Korean Air')
                    && false === stripos($text, 'Operated by KE')
                ) {
                    continue;
                }

                foreach ($this->reBody2 as $re) {
                    if (stripos($text, $re) !== false) {
                        return true;
                    }
                }
            }
        } else {
            if ($this->http->XPath->query("//img[contains(@src, 'koreanair.com')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Information'))}]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Itinerary')]")->length > 0
                && ($this->http->XPath->query("//text()[contains(normalize-space(), 'My Trip')]")->length > 0
                    || $this->http->XPath->query("//text()[contains(normalize-space(), 'Notice')]")->length > 0
                    || $this->http->XPath->query("//text()[contains(normalize-space(), 'you have completed your ticket purchase')]")->length > 0)
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    private function flight(Email $email, string $pdftext): void
    {
        $this->logger->debug(__METHOD__);
        $pos = stripos($pdftext, 'Fare Calculation');

        if (empty($pos)) {
            $pos = stripos($pdftext, 'Please review the attached');
        }

        if (empty($pos)) {
            $this->logger->debug('mainSection not found!');

            return;
        }

        $f = $email->add()->flight();

        if (preg_match("#\n\s*Total Amount[ ]+([A-Z]{3})[ ]*(\d[\d .,]+)#", $pdftext, $m)) {
            $f->price()
                ->total($this->amount($m[2]))
                ->currency($m[1]);
        }

        $text = substr($pdftext, 0, $pos);
        $text = preg_replace("#\n[ ]*http://.+ \d+/\d+[ ]*\n\s*\d{1,2}/\d{1,2}/\d{4}[ ]+.+\n#", "\n", $text);

        if (preg_match("/\n[ ]*Passenger Name[ ]+([^(\n]{2,}\b)\s*(?:\((KE\d+\**)\))?/", $text, $m)) {
            $f->general()->traveller(preg_replace("/^(.{2,}?)\s+{$this->opt($this->namePrefixes)}$/i", '$1', $m[1]), true);

            if (!empty($m[2])) {
                $f->program()->account($m[2], strpos($m[2], '*') !== false);
            }
        }

        if (preg_match("#\n\s*Booking Reference[ \:]+([A-Z\d]{5,})\s*\(([A-Z\d]{5,7})\)\s+#", $text, $m)
         || preg_match("#\s*Booking Reference[ ]+([A-Z\d]{5,})\n#u", $text, $m)) {
            if (isset($m[2]) && !empty($m[2])) {
                $f->general()->confirmation($m[2], 'Booking Reference', true);
            }

            $f->general()->confirmation($m[1], 'Booking Reference');
        }

        if (preg_match("#\n\s*Ticket Number[ ]+([\d\-]{7,})\s+#", $text, $m)) {
            $f->issued()->ticket($m[1], false);
        }

        $segments = $this->split("/\n+[ ]*(Flight[ ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+\b)/", $text);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            if (preg_match("/Flight[ ]+([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)(?:[ ]+Operated by\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\b)?/", $stext, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                if (!empty($m[3])) {
                    $s->airline()
                        ->operator($m[3]);
                }
            }

            if (preg_match("#\n\s*Departure[ ]+(?<name>.+?)\(\s*(?<code>[A-Z]{3})(?:/(?<name2>.+))?\)[ ]+(?<date>\d+\w+\d+\b).*?\s+(?<time>\d{1,2}:\d{1,2})(\D.*)?Terminal[^:]*:(?<term>.*)(\n[ ]{70,}(?<term2>\S.+))?#", $stext, $m)) {
                $s->departure()
                    ->name(trim($m['name']) . (!empty($m['name2']) ? ', ' . trim($m['name2']) : ''))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['time']));

                if (preg_match("#.*\w.*#u", $m['term'])) {
                    $m['term'] = trim($m['term']);

                    if (!empty($m['term2'])) {
                        $m['term'] .= ' ' . trim($m['term2']);
                    }
                    $s->departure()
                        ->terminal(trim($m['term']), true);
                }
            }

            if (preg_match("#\n\s*Arrival[ ]+(?<name>.+?)\(\s*(?<code>[A-Z]{3})(?:/(?<name2>.+))?\)[ ]+(?<date>\d+\w+\d+\b).*?\s+(?<time>\d{1,2}:\d{1,2})(\D.*)?.*Terminal[^:]*:(?<term>.*)(\n[ ]{70,}(?<term2>\S.+))?#", $stext, $m)) {
                $s->arrival()
                    ->name(trim($m['name']) . (!empty($m['name2']) ? ', ' . trim($m['name2']) : ''))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['time']));

                if (preg_match("#.*\w.*#u", $m['term'])) {
                    $m['term'] = trim($m['term']);

                    if (!empty($m['term2'])) {
                        $m['term'] .= ' ' . trim($m['term2']);
                    }
                    $s->arrival()
                        ->terminal(trim($m['term']), true);
                }
            }

            if (preg_match("#\n\s*Flight Duration[ ]+(.+?)([ ]{2,}|\n)#", $stext, $m)) {
                $s->extra()
                    ->duration(trim($m[1]));
            }

            if (preg_match("#\n\s*Booking Class[ ]+([A-Z]{1,2})[ ]{0,2}\(([^\(\n]+)\)#", $stext, $m)) {
                $s->extra()
                    ->bookingCode($m[1])
                    ->cabin(trim($m[2]));
            }

            if (preg_match("#\n\s*Aircraft Type[ ]+(.+?)(\s{2,}|\n|$)#", $stext, $m)) {
                $s->extra()
                    ->aircraft(trim($m[1]));
            }

            if (preg_match("#\n\s*Status[ ]+([^\(\n]+[ ]{0,2}\([^\(\n]+\))#", $stext, $m)) {
                $s->extra()
                    ->status(trim($m[1]));
            }

            if (preg_match("#\s+Seat Number[ ]+(\d{1,3}[A-Z])\b#", $stext, $m)) {
                $s->extra()
                    ->seat($m[1]);
            }
        }
    }

    private function flight2(Email $email, string $pdftext): void
    {
        $this->logger->debug(__METHOD__);
        //it-633937833.eml - remove row From To....
        $pdftext = preg_replace("/(\(Local Time\)\n)(\s*From\s*To\s*Flight\n+)(\s*Terminal No)/", "$1$3", $pdftext);

        $sectionEnd = (array) $this->t('mainSectionEnd2');
        $positions = [];

        foreach ($sectionEnd as $value) {
            $positions[] = stripos($pdftext, $value);
        }
        $positions = array_filter($positions);

        if (empty($positions)) {
            $this->logger->debug('mainSection not found!');

            return;
        }
        $pos = min($positions);

        $pdftext = preg_replace("#.*$#", '', $pdftext);

        $f = $email->add()->flight();

        if (preg_match("#\n\s*(?:\w+ ){0,2}[ ]*Total Amount[ ]+([A-Z]{3})[ ]*(\d[\d .,]*)#u", $pdftext, $m)
                || preg_match("#\n\s*(?:\w+ ){0,2}[ ]*Total Amount[ ]{20,}.*\n[ ]+([A-Z]{3})[ ]*(\d[\d .,]*)#u", $pdftext, $m)) {
            $f->price()
                ->total($this->amount($m[2]))
                ->currency($m[1]);

            if (preg_match("#\n\s*(?:\w+ ){0,2}[ ]*(?:Fare Amount|Equivalent Fare Amount)[ ]+(" . $m[1] . ")[ ]*(\d[\d .,]*)#u", $pdftext, $m2)
                    || preg_match("#\n\s*(?:\w+ ){0,2}[ ]*(?:Fare Amount|Equivalent Fare Amount)[ ]{20,}.*\n[ ]+(" . $m[1] . ")[ ]*(\d[\d .,]*)#u", $pdftext, $m2)) {
                $f->price()
                    ->cost($this->amount($m2[2]));
            }
            $fees = ['Carrier Imposed Fees', 'Service Fees', 'Fuel Surcharge'];

            foreach ($fees as $fee) {
                if (preg_match("#\n\s*(?:\w+ ){0,2}[ ]*{$fee}[ ]+(" . $m[1] . ")[ ]*(\d[\d .,]*)#u", $pdftext, $m2)
                    || preg_match("#\n\s*(?:\w+ ){0,2}[ ]*{$fee}[ ]{20,}.*\n[ ]+(" . $m[1] . ")[ ]*(\d[\d .,]*)#u",
                        $pdftext, $m2)
                ) {
                    $sum = $this->amount($m2[2]);

                    if ($sum !== null) {
                        $f->price()
                            ->fee($fee, $sum);
                    }
                }
            }

            if (preg_match("#^[ ]*[*] ?(?:\w+ ){0,2}Taxes[ ]+(" . $m[1] . ")[ ]*(\d.+)#mu", $pdftext, $m2)
                && preg_match_all("#\b(\d[\d.,]*)([A-Z][A-Z\d]|[A-Z\d][A-Z])(?:[ ]+|$)#", $m2[2], $taxesMatches, PREG_SET_ORDER)
            ) {
                // * Taxes USD 3.96XA 7.00XY 5.77YC 18.60US 18.60US 5.60AY 16.74BP 2.00C4 25.00JC 4.50XF
                foreach ($taxesMatches as $fee) {
                    $sum = $this->amount($fee[1]);

                    if ($sum !== null) {
                        $f->price()->fee($fee[2], $sum);
                    }
                }
            } elseif (preg_match("#\n\s*(?:\w+ ){0,2}[ ]*Taxes[ ]+(" . $m[1] . ")[ ]*(\d[\d .,]*)#u", $pdftext, $m2)
                || preg_match("#\n\s*(?:\w+ ){0,2}[ ]*Taxes[ ]{20,}.*\n[ ]+(" . $m[1] . ")[ ]*(\d[\d .,]*)#u", $pdftext, $m2)
            ) {
                $f->price()->tax($this->amount($m2[2]));
            }
        }

        $text = substr($pdftext, 0, $pos);
        $ticketFareInfo = stristr($pdftext, 'Ticket Fare Information', false);

        if (preg_match("#\n([ ]*(?:\w+ ){0,2}[ ]*Passenger Name[ ]+[^\n]+(\s+[\s\S]+?))\n\s*(?:\w+ ){0,2}[ ]*Itinerary#u", $text, $m)) {
            $table = $this->SplitCols($m[2], $this->TableHeadPos($this->inOneRow($m[1])));

            if (isset($table[0]) && preg_match("#^\s*([\.[:alpha:]][-.'’[:alpha:] /]*[[:alpha:]])\s*(?:\(?([A-Z\d*]+)\))?.*$#s", $table[0], $mat)) {
                $f->general()->traveller(preg_replace(['/\n+/', "/^(.{2,}?)\s+{$this->opt($this->namePrefixes)}$/i"], ['', '$1'], $mat[1]), true);

                if (!empty($mat[2])) {
                    $acc = $this->re('/([A-Z]+\d)/', $mat[2]);

                    if (!empty($ticketFareInfo) && preg_match("/\s*(?<account>{$acc}\d+\**)[ ]*-[ ]*M(?<spent>\d+)/", $ticketFareInfo, $math)) {
                        $f->program()->account($math['account'], strpos($math['account'], '*') !== false, preg_replace(['/\n+/', "/^(.{2,}?)\s+{$this->opt($this->namePrefixes)}$/i"], ['', '$1'], $mat[1]));
                        $f->price()->spentAwards($math['spent']);
                    } else {
                        $f->program()->account($mat[2], strpos($mat[2], '*') !== false, preg_replace(['/\n+/', "/^(.{2,}?)\s+{$this->opt($this->namePrefixes)}$/i"], ['', '$1'], $mat[1]));
                    }
                }
            }

            if (isset($table[1]) && preg_match("#^\s*[\d\- ]+\s*$#", $table[1])) {
                $ticket = trim(preg_replace("#\s+#", '', $table[1]));
                $pax = preg_replace(['/\n+/', "/^(.{2,}?)\s+{$this->opt($this->namePrefixes)}$/i"], ['', '$1'], $this->re("/[ ]*([[:alpha:]][-.\/'’[:alpha:] ]*[[:alpha:]])\s.+$ticket/", $text));

                if (!empty($pax)) {
                    $f->issued()->ticket($ticket, false, $pax);
                } else {
                    $f->issued()->ticket($ticket, false);
                }
            }

            if (isset($table[2]) && preg_match("#([A-Z\d\-]{5,})\s*\(([A-Z\d]{5,7})\)(?:\s|$)#", $table[2], $mat)
                || preg_match("#\s*([A-Z\d]{6})(?:\n|$)#u", $text, $mat)
            ) {
                if (isset($mat[2]) && !empty($mat[2])) {
                    $f->general()->confirmation($mat[2], 'Booking Reference', true);
                }
                $f->general()->confirmation($mat[1], 'Booking Reference');
            }
        }

        //Remove the title if it's a new page
        $text = preg_replace("/(Operated by\s*[A-Z\d]+\n)(\s+From\s+To\s+Flight\n)/", "$1", $text);

        $earnedAwards = 0;
        $segments = $this->split("#\n([ ]*" . $this->t("From[ ]+To[ ]+Flight") . ")#", $text);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            // remove garbage
            $stext = preg_replace("#\(Local Time\)#u", str_pad('', 12), $stext);

            if (!preg_match("/{$this->t("From[ ]+To[ ]+Flight")}\n+((?:.+\n+){3,}?).*(?:{$this->opt($this->t('segTopEnd'))}|{$this->opt($this->t('Class'))}|$)/u", $stext, $m)) {
                $this->logger->debug('Wrong flight segment!');

                continue;
            }

            $tableRoute = $this->SplitCols($m[1]);

            if (count($tableRoute) !== 3) {
                $this->logger->debug('Error parsing tableRoute!');
                $f->removeSegment($s);

                continue;
            }

            if (preg_match("#(?<code>[A-Z]{3})\s+(?<name>[\s\S]+?)\n(?<date>\d{1,2}\w+\d{2}.+)\s+Terminal No\s*:\s*(?<term>.+)#u", $tableRoute[0], $m)) {
                $s->departure()
                    ->name(trim(preg_replace("#\s+#", ' ', $m['name'])))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));

                if (trim($m['term']) != '-') {
                    $s->departure()
                        ->terminal(trim($m['term']), true);
                }
            }

            if (preg_match("#(?<code>[A-Z]{3})\s+(?<name>[\s\S]+?)\n(?<date>\d{1,2}\w+\d{2}.+)\s+Terminal No\s*:\s*(?<term>.+)#", $tableRoute[1], $m)
            || preg_match("#(?<code>[A-Z]{3})\s+(?<name>[\s\S]+?)\n(?<date>\d{1,2}\w+\d{2}.+)$#", $tableRoute[1], $m)) {
                $s->arrival()
                    ->name(trim(preg_replace("#\s+#", ' ', $m['name'])))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));

                if (isset($m['term']) && trim($m['term']) != '-') {
                    $s->arrival()
                        ->terminal(trim($m['term']), true);
                }
            }

            if (preg_match("/([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)(?:\s+Operated by\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\b)?/", $tableRoute[2], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                if (!empty($m[3]) && $m[3] !== $m[1]) {
                    $s->airline()
                        ->operator($m[3]);
                }
            }

            if (preg_match("#(?:\n|\s{2,})(?:" . $this->t("Class") . " )?Class[ ]*:[ ]*([A-Z]{1,2})[ ]{0,2}\(([^\(\n]+)\)#", $stext, $m)) {
                $s->extra()
                    ->bookingCode($m[1])
                    ->cabin(trim($m[2]));
            }

            if (preg_match("/(?:\n|[ ]{2,})(?:{$this->t("Aircraft Type")} )?Aircraft Type[ ]*:[ ]*(.+?)(?:[ ]{2,}|\n|\s*$)/", $stext, $m)
                && !preg_match('/^\s*[-]+\s*$/', $m[1])
            ) {
                $s->extra()->aircraft($m[1]);
            }

            if (preg_match("#(?:\n|\s{2,})(?:" . $this->t("Status") . " )?Status[ ]*:[ ]*(.+?)(?:\s{2,}|\n|$)#", $stext, $m)) {
                $s->extra()
                    ->status(trim($m[1]));
            }

            if (preg_match("#(?:\n|\s{2,})(?:" . $this->t("Flight Duration") . " )?Flight Duration[ ]*:[ ]*(.+?)(?:\s{2,}|\n|$)#", $stext, $m)) {
                $s->extra()
                    ->duration(trim($m[1]));
            }

            if (preg_match("#(?:\n|\s{2,})(?:" . $this->t("Seat number") . " )?Seat number[ ]*:[ ]*(\d{1,3}[A-Z])\b#", $stext, $m)) {
                $s->extra()
                    ->seat($m[1]);
            }

            if (preg_match("#(?:\n|\s{2,})(?:" . $this->t("SKYPASS Miles") . " )?SKYPASS Miles[ ]*:[ ]*([\d\,]+)(?:\s{2,}|\n|$)#u", $stext, $m)) {
                $earnedAwards += intval(str_replace(',', '', $m[1]));
            }
        }

        if (!empty($earnedAwards)) {
            $f->program()->earnedAwards($earnedAwards . ' SKYPASS Miles');
        }
    }

    private function flightHTML(Email $email): void
    {
        $this->logger->debug(__METHOD__);
        $f = $email->add()->flight();

        $traveller = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'you have completed your ticket purchase')]/preceding::text()[normalize-space()][1]", null, true, "/^(.+)\s(?:MRS|MR|MS)/");

        if (!empty($traveller)) {
            $f->general()
                ->traveller($traveller);
        }

        $confText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Reference')]/ancestor::tr[1]");

        if (preg_match("/Booking Reference[\s\:]+(?<otaConf>[\d\-]+)\s+\((?<conf>[A-Z\d]{6})\)/", $confText, $m)) {
            $email->ota()
                ->confirmation($m['otaConf']);

            $f->general()
                ->confirmation($m['conf']);
        } elseif (preg_match("/Booking Reference[\s\:]+(?<conf>[A-Z\d]{6})$/", $confText, $m)) {
            $f->general()
                ->confirmation($m['conf']);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Itinerary']/following::img[contains(@src, 'flight')]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = implode("\n", $this->http->FindNodes("./descendant::td[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d+)/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::td[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<year>\d{4})\.(?<month>\d{1,2})\.(?<day>\d{1,2})\s*\(\D*\)\s*(?<time>[\d\:]+)\n(?<depCode>[A-Z]{3})\n(?:Terminal\sNo\.\s*(?<depTerminal>.+)\n)?(?<depName>.+\n*.*)$/", $depInfo, $m)) {
                $s->departure()
                    ->name(str_replace("\n", "", $m['depName']))
                    ->code($m['depCode'])
                    ->date(strtotime($m['day'] . '.' . $m['month'] . '.' . $m['year'] . ', ' . $m['time']));

                if (isset($m['depTerminal']) && !empty($m['arrTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::td[normalize-space()][3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<year>\d{4})\.(?<month>\d{1,2})\.(?<day>\d{1,2})\s*\(\D*\)\s*(?<time>[\d\:]+)\n(?<arrCode>[A-Z]{3})\n(?:Terminal\sNo\.\s*(?<arrTerminal>.+)\n)?(?<arrName>.+\n*.*)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name(str_replace("\n", "", $m['arrName']))
                    ->code($m['arrCode'])
                    ->date(strtotime($m['day'] . '.' . $m['month'] . '.' . $m['year'] . ', ' . $m['time']));

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $extraInfo = implode("\n", $this->http->FindNodes("./descendant::td[normalize-space()][4]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Class\n(?<cabin>\D+)\s+\((?<bookingCode>[A-Z])\)\nStatus\n(?<status>.+)/", $extraInfo, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode'])
                    ->status($m['status']);
            } elseif (preg_match("/Class\n(?<cabin>\D+)\s+Seat Number\n(?<seat>\d+[A-Z]+)\n/", $extraInfo, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->seat($m['seat']);
            }
        }
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $in = [
            // 10NOV18(SAT) 02:00    |    04NOV2019(MON) 00:10
            "#^\s*(\d{1,2})([[:alpha:]]{3,})(\d{2,4})\([[:alpha:]]+\)\s+(\d+:\d+)\s*$#u",
            // 04JAN2025(⼟) 07:55
            "#^(\d+)(\w+)(\d{4})\S+\s+([\d\:]+)$#u",
        ];
        $out = [
            "$1-$2-$3 $4",
            "$1 $2 $3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function amount($s)
    {
        if ($s === null || $s === '') {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function opt($field, bool $addSpaces = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) use ($addSpaces) {
            return $addSpaces ? $this->addSpacesWord($s) : preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function addSpacesWord(string $text): string
    {
        return preg_replace('/(\S)/u', '$1 *', preg_quote($text, '/'));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
