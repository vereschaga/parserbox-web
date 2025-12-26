<?php

namespace AwardWallet\Engine\royalcaribbean\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: royalcaribbean/AgentGuestBooking, celebritycruises/InvoiceAgentGuestPdf, princess/Itinerary, mta/POCruisesPdf

class It2 extends \TAccountChecker
{
    public $mailFiles = "royalcaribbean/it-120666697.eml, royalcaribbean/it-16158743.eml, royalcaribbean/it-2.eml, royalcaribbean/it-58901585.eml, royalcaribbean/it-714650931.eml, royalcaribbean/it-76501835.eml, royalcaribbean/it-8566420.eml";

    private $langDetectors = [
        'zh' => [
            '賓客預訂確認書',
            '遊輪名稱',
        ],
        'en' => [
            'Booking Confirmation - Agent Copy',
            'Booking Confirmation - Guest Copy',
            'Booking Confirmation – Guest Copy', //other dash
            'Booking Confirmation –Guest Copy',
            'Confirmation Invoice – Guest Copy',
            'CRUISE VACATION RECEIPT',
            'CRUISE CONFIRMATION INVOICE',
            'CRUISE ITINERARY',
        ],
    ];

    private $isAgent = false;
    private $agentDetector = [
        'pdfText' => ['- Agent Copy', 'Total Comm/Admin'],
    ];

    private $providerCode = '';
    private $lang = '';
    private $date = 0;
    private static $dictionary = [
        'en' => [
            'Booking Date'           => ['Booking Date', 'Issue Date'],
            'Stateroom'              => ['Stateroom Number', 'Stateroom'],
            'Total Charge'           => ['Total Charge', 'Gross Charges'],
            'Amount Paid'            => ['Amount Paid', 'A service', 'Total Charges All Sailings'],
            'Taxes, fees, and port'  => ['Taxes, fees, and port', 'Taxes and Fees'],
        ],
        'zh'=> [
            'Ship'           => '遊輪名稱',
            'Departure Date' => '出發日期',
            'Booking Date'   => '賬單日期',
            'Stateroom'      => '房號及房型',
            'Itinerary'      => '行程',
            //            'Booking Status' => '',
            'Cruise Fare'  => ['船費 Cruise Fare'],
            'Total Charge' => ['價格合共', 'Total Charge', '價格合共 Total Charge'],
            'Amount Paid'  => ['已付金額', 'Amount Paid'],
            'Guest Name'   => '賓客姓名',
        ],
    ];

    public static function getEmailProviders()
    {
        return ['celebritycruises', 'royalcaribbean'];
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 1) {
            $pdfs = $parser->searchAttachmentByName('Agent_.*pdf');

            if (!empty($pdfs)) {
                $this->isAgent = true;
            }
        }

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('Guest_.*pdf');
        }

        if (empty($pdfs)) {
            $pdfs = $parser->searchAttachmentByName('.*_receipt\.pdf');
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                foreach ($this->agentDetector['pdfText'] as $adetect) {
                    if (strpos($textPdf, $adetect) !== false) {
                        $this->isAgent = true;

                        break;
                    }
                }

                $this->assignProviderPdf($textPdf);
                $this->parseCruise($email, $textPdf);
                //break;
            }
        }

        $email->setProviderCode($this->providerCode);
        $email->setType('GuestCopyPdf' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

        if (empty($textPdf)) {
            return false;
        }

        // Detecting Provider
        if ($this->assignProviderPdf($textPdf) === false) {
            return false;
        }

        // Detecting Format/Language
        return $this->assignLang($textPdf);
    }

    public function sOpt($fields, $addSpace = true, $quote = true)
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        if ($quote == true) {
            $fields = array_map(function ($s) {
                return preg_quote($s, '#');
            }, $fields);
        }

        if ($addSpace == true) {
            $fields = array_map([$this, 'addSpace'], $fields);
        }

        return '(?:' . implode('|', $fields) . ')';
    }

    public function addSpace($text)
    {
        return preg_replace("#([^\s\\\])#u", "$1 ?", $text);
    }

    private function parseCruise(Email $email, string $text): void
    {
        $c = $email->add()->cruise();

        if (preg_match("/(Reservation ID:?)[ ]*([A-Z\d]{5,})(?:[ ]*\(|[ ]{2}|$)/m", $text, $m)) {
            $c->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        if (preg_match("/(?:^[ ]*|[ ]{2}){$this->sOpt($this->t('Ship'))}[: ]+(.+?)(?:[ ]{2}|$)/mu", $text, $m)) {
            $c->details()->ship($m[1]);
        }

        $departureDate = preg_match("/" . $this->opt($this->t('Departure Date')) . "[\s:]+\b(.{6,}?)(?:[ ]{2}|$)/im", $text, $m) ? strtotime($m[1]) : null;

        if ($departureDate) {
            $this->date = $departureDate;
        }

        foreach ((array) $this->t('Booking Date') as $phrase) {
            if (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($phrase)}\b[: ]*(.*?)(?:[ ]{2}|$)/mu", $text, $m)) {
                if (preg_match('/^.{6,}$/', $m[1])) {
                    $c->general()->date2($m[1]);
                }

                break;
            }
        }

        if (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Stateroom'))}[: ]+(.+?)(?:[ ]{2}|$)/mu", $text, $m)) {
            if (preg_match("/^\s*([A-Z\d]{1,5}-)(\d+) ([A-Z].+)$/", $m[1], $mat)) {
                $c->details()
                    ->room($mat[2])
                    ->roomClass($mat[1] . $mat[3])
                ;
            } else {
                $c->details()->room($m[1]);
            }
        }

        if (preg_match("/(?:^[ ]*|[ ]{2}){$this->sOpt($this->t('Itinerary'))}[: ]+(.+?)(?:[ ]{2}|$)/mu", $text, $m)) {
            $c->details()->description($m[1]);
        }

        if (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Booking Status'))}[: ]+(.+?)(?:[ ]{2}|$)/m", $text, $m)) {
            $c->general()->status($m[1]);
        }

        if (preg_match("/Currency:\s*([A-Z]{3})\s.+Admin\s*Rate/su", $text, $m)
            || preg_match("/Currency\:\s*([A-Z]{3})/u", $text, $m)
            || preg_match("/\n[ ]*港幣 (HKD)[ ]{2,}/u", $text, $m)
            || preg_match("/\n[ ]{0,40}([A-Z]{3})[ ]{2,}{$this->opt($this->t('Total Charge'))}/", $text, $m)
            || preg_match("/Booking Charges[-\s]+Currency:[ ]+([A-Z]+)/", $text, $m)
            || preg_match("/^[ ]*([A-Z]{3})\s+Guest[ ]*#[ ]*\d/m", $text, $m)
        ) {
            $c->price()->currency($m[1]);
        }

        if (preg_match("/\n(?<paddingLeft>(?:[ ]{0,40}[A-Z]{3}|[ ]{40})[ ]{2,}){$this->opt($this->t('Total Charge'))}(?<paddingRight>[ ]+{$this->opt($this->t('Commission Rate'))}[ ]+{$this->opt($this->t('Commission'))})\n+(?<content>.+)/s", $text, $m)) {
            // version 2023
            $paddingLeft = mb_strlen($m['paddingLeft']);
            $paddingRight = mb_strlen($m['paddingRight']);

            if (preg_match("/^[ ]{0,40}TOTAL[ ]{2," . ($paddingLeft - 7) . "}(.+).{" . $paddingRight . "}$/m", $m['content'], $m2)
                && preg_match('/^\s*(\d+\.\d{2})\s*$/', $m2[1], $m3)
            ) {
                $c->price()->total($m3[1]);
            }

            if (preg_match("/^[ ]{0,40}Taxes, fees, and port expenses[ ]{2," . ($paddingLeft - 32) . "}(.+).{" . $paddingRight . "}$/im", $m['content'], $m2)
                && preg_match('/^\s*(\d+\.\d{2})\s*$/', $m2[1], $m3)
            ) {
                $c->price()->tax($m3[1]);
            }
        } elseif ($total = $this->re("/Total Charges All Sailings.*\s+([\d\.\,]+)\n/", $text)) {
            $currency = $c->getPrice()->getCurrencyCode();
            $c->price()->total(PriceHelper::parse($total, $currency));
        } else {
            // other version
            $totalPrice = preg_match("/{$this->opt($this->t('Total Charge'))}(?:s)?(?: Cruise 1)?[ ]{2}.+[ ]{2}(\d[.\d]*)(?:" . ($this->isAgent == true ? ' {2,}\d+%(?:\n.+){0,5}' : '') . "\n[ ]*(?:(?:\S ?)* )?{$this->opt($this->t('Amount Paid'))}|\n\n)/", $text, $m) ? $m[1] : null;
            $c->price()->total($totalPrice);

            $priceTable = $this->re("/\n *{$this->opt($this->t('Cruise Fare'))} {2,}.+\n([\s\S]+?\n *{$this->opt($this->t('Taxes, fees, and port'))}.+)\n *{$this->opt($this->t('Total Charge'))} {2,}/", $text);
            $priceTable = preg_replace('/ {2,}\d+%\s*$/m', '', $priceTable);

            if (preg_match_all("/^ {0,5}(?<name>(?:\S ?)+) {2,}.+ {2,}(?<value>\d[.\d]*)$/m", $priceTable, $m)) {
                foreach ($m[0] as $i => $v) {
                    if (preg_match("/^ *((\S ?)* )?{$this->opt($this->t('Cruise Fare'))}/", $m['name'][$i], $mv)
                        && !preg_match("/\bNon Comm\b/", $mv[1])) {
                        continue;
                    }
                    $c->price()
                        ->fee($m['name'][$i], $m['value'][$i]);
                }
            }
            // $priceTable = $this->re("/\n *{$this->opt($this->t('Cruise Fare'))} {2,}.+\n([\s\S]+?)\n *{$this->opt($this->t('Total Charge'))} {2,}/", $text);
            if (preg_match_all("/^ {0,5}(?:\S ?)+ {2,}.+ {2,}-(?<value>\d[.\d]*)$/m", $priceTable, $m)) {
                $discount = array_sum($m['value']);

                if (!empty($discount)) {
                    $c->price()->discount($discount);
                }
            }

            if (preg_match_all("/^ *((?:\S ?)* )?{$this->opt($this->t('Cruise Fare'))}[ ]{2}.+ {2,}(\d[\.\d]*)(?: {2,}\d+%)?$/mu", $text, $m)) {
                $cost = 0.0;

                foreach ($m[1] as $i => $name) {
                    if (!preg_match("/\bNon Comm\b/", $name)) {
                        $cost += $m[2][$i];
                    }
                }
                $c->price()->cost($cost);
            }

            if ($this->isAgent === true && preg_match("/^ *({$this->opt($this->t('Total Comm/Admin'))})[ ]{2}.+ {2,}(\d[.\d]*)$/m", $text, $m)) {
                $c->price()
                    ->fee($m[1], $m[2]);
            }
        }

        $guestsText = preg_match("/^([ ]*" . $this->opt($this->t("Guest Name")) . "[:\s]+[A-Z\d\s]+(?:Guest Name[A-Z\d\s]+)?)\n/m", $text, $m) ? $m[1] : null;
        $tableGuests = [];

        if (empty($guestsText) && stripos($text, 'First Name') !== false) {
            $textGuests = $this->re('/GUEST\s*INFORMATION\n\s+Guest\s*[#].+\n(First Name\:.+)\nCrown and Anchor Number/s', $text);

            if (empty($textGuests)) {
                $textGuests = $this->re('/GUEST\s*INFORMATION\n\s+Guest\s*[#].+\n(First Name\:.+)\n(?:Dining|Health Acknowledgment)/s', $text);
            }
            $tableGuests = $this->splitCols($textGuests);
        } else {
            $tableGuests = $this->splitCols($guestsText);
        }

        if (count($tableGuests) > 1) {
            array_shift($tableGuests);

            foreach ($tableGuests as $gCell) {
                $c->general()->traveller(preg_replace('/\s+/', ' ', $this->re("/^([A-Z\s]+)/", $gCell)));
            }
        }

        if (preg_match("/^[ ]*Captain's Club Number[ ]+(.{5,})$/m", $text, $m)) {
            $c->program()->accounts(preg_split("/[ ]{2,}/", $m[1]), false);
        }

        $patterns['portLocation'] = '(?:Port Location|Port Locaton|Port)';
        $patterns['atSea'] = 'AT SEA|CRUISING';
        $patterns['time'] = '\d{1,2}[:]+\d{2}(?:[ ]*[AaPp]\.?[Mm]\.?)?';

        $segmentsText = preg_match("/Date\s+{$patterns['portLocation']}\s+Arrive\s+Depart\s+Date\s+{$patterns['portLocation']}\s+Arrive\s+Depart\s+([\s\S]*?)\s+Post Cruise\s+Arrangements/", $text, $m)
        || preg_match("/Cruise Itinerary:([\s\S]+?)\n\s*.* *Post Cruise Arrangements/u", $text, $m)
        || preg_match("/(?:^|\n)[ ]*CRUISE ITINERARY\s+([\s\S]*?)\s+(?:Traveling with booking number|WE LOOK FORWARD TO WELCOMING YOU|\n{4})/", $text, $m)
               ? $m[1] : null;

        //24   SEP                     CRUISING
        $segmentsText = preg_replace("/\d{2}\s*[A-Z]{3}\s*CRUISING/", "", $segmentsText);
        $segmentsText = preg_replace("/( {2,}\d{1,2}) (\d{2} [AP]M(?: {2,}|\n|$))/", '$1:$2', $segmentsText);

        // 25 DEC    COZUMEL, MEXICO    7:00 AM 4:00 PM
        $patterns['embarkation'] = "\d{2}[ ]{0,4}[[:upper:]]{3}[ ]+(?:\w.{10,}?|{$patterns['atSea']})";

        if (preg_match_all("/^[ ]{0,40}({$patterns['embarkation']}?|[ ]{40})(?:[ ]{2,}({$patterns['embarkation']}))?$/mu", $segmentsText, $matches)) {
            $segmentsText = implode("\n", $matches[1]) . "\n" . implode("\n", $matches[2]);
        }

        if (preg_match_all("/\s*(\d{2}\s*[[:upper:]]{3}\s+\w+.*)/u", $segmentsText, $sMatches)) {
            foreach ($sMatches[1] as $sText) {
                if (preg_match("/[ ]{2}(?:{$patterns['atSea']})/i", $sText)) {
                    continue;
                }

                if (!preg_match("/^(?<date>\d{1,2}\s*\w+)\s+(?<port>\D+)\s+(?<time1>[\d\:]+\s*A?P?M)\s*(?<time2>[\d\:]+\s*A?P?M)\s*$/u", $sText, $m)
                    && !preg_match("/^(?<date>\d{1,2}\s*\w+)\s+(?<port>\D+)\s+(?<time1>[\d\:]+\s*A?P?M)\s*$/u", $sText, $m)

                    && !preg_match("/^(?<date>\d{1,2}\s*\w+)\s+(?<port>\D+)\s+(?<time1>[\d\:]+)\s*(?<time2>[\d\:]+)\s*$/u", $sText, $m)
                    && !preg_match("/^(?<date>\d{1,2}\s*\w+)\s+(?<port>\D+)\s+(?<time1>[\d\:]+)\s*$/u", $sText, $m)
                ) {
                    continue;
                }

                $date = EmailDateHelper::parseDateRelative($m['date'], $this->date);

                $arrive = $depart = null;

                if (!empty($m['time1']) && !empty($m['time2'])) {
                    $arrive = strtotime($m['time1'], $date);
                    $depart = strtotime($m['time2'], $date);
                } elseif (!isset($s)
                    || isset($s) && empty($s->getAboard())
                ) {
                    // it-58901585.eml
                    $depart = strtotime($m['time1'], $date);
                } else {
                    $arrive = strtotime($m['time1'], $date);
                }

                if (!isset($s)
                    || $m['port'] !== $s->getName()
                ) {
                    $s = $c->addSegment();
                    $s->setName($m['port']);
                }

                if ($arrive) {
                    $s->setAshore($arrive);
                }

                if ($depart) {
                    $s->setAboard($depart);
                }
            }
        }
    }

    private function assignProviderPdf(?string $text): bool
    {
        if (stripos($text, 'Celebrity Cruises Inc.') !== false
            || stripos($text, 'www.celebritycruises.com') !== false
        ) {
            $this->providerCode = 'celebritycruises';

            return true;
        }

        if (stripos($text, 'Royal Caribbean Cruise Ltd.') !== false
            || stripos($text, 'www.RoyalCaribbean.com') !== false
            || stripos($text, 'Royal Caribbean Travel Protection Program') !== false
            || stripos($text, 'Royal Caribbean International') !== false
            || stripos($text, 'Royal Caribbean Group.') !== false
            || stripos($text, 'by RCL Cruises Ltd.') !== false
            || stripos($text, 'This holiday is provided by Royal Caribbean Cruises Ltd') !== false
            || stripos($text, 'Royal Caribbean Cruises') !== false
            || stripos($text, 'The booking office is RCL Cruises') !== false
            || stripos($text, 'or royalcaribbean.com/health') !== false
        ) {
            $this->providerCode = 'royalcaribbean';

            return true;
        }

        return false;
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset($this->langDetectors, $this->lang)) {
            return false;
        }

        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
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
