<?php

namespace AwardWallet\Engine\hollandamerica\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2 extends \TAccountChecker
{
    public $mailFiles = "hollandamerica/it-133241885.eml, hollandamerica/it-1567335.eml, hollandamerica/it-15956340.eml, hollandamerica/it-15960580.eml, hollandamerica/it-2.eml, hollandamerica/it-642152063.eml, hollandamerica/it-652412213.eml, hollandamerica/it-653054766.eml, hollandamerica/it-696410950.eml, hollandamerica/it-699931476.eml";

    private $subjects = [
        'en' => ['Booking Confirmation for', 'Booking #', 'Important Notification for',
            'Guest Cancellation Notification for', ],
    ];

    private $textForAgent = '';

    private $langDetectorsPdf = [
        "en" => ["Booking #", 'CRUISE DETAILS'],
    ];

    private static $dictionary = [
        "en" => [
            'Day '                            => 'Day ',
            // 'Date' => '',
            'port'                            => [' Itinerary', ' Port'],
            'Arrival '                        => 'Arrival ',
            // 'Departure' => '',
            'From Port:'                      => 'From Port:',
            'To Port:'                        => 'To Port:',
            'Embark Date:'                    => 'Embark Date:',
            // 'Debark Date:' => '',
            'Guest Cancellation Notification' => 'Guest Cancellation Notification',
            'Booking #'                       => ['Booking #', 'Booking#'],
            // 'Booking Status' => '',
            // 'Guest' => '',
            // 'Name' => '',
            // 'Agent' => '',
            // 'Ship' => '',
            // 'Category' => '',
            // 'Stateroom' => '',
            // 'Voyage' => '',
            // 'Sail from' => '',
            // 'Debark Ship' => '',
            'cruising' => ['Scenic Cruising', 'Cruising'],
            // 'Journey:' => '',
            // 'ITINERARY CHANGE NOTIFICATION' => '',
            'priceStart' => ['PRICING BREAKDOWN', 'FINANCIAL BREAKDOWN'],
            'priceEnd'   => ['Total Due:', 'Amount Received:', 'Ships\' Registry:', 'FINANCIAL HISTORY', 'CANCELLATION FEES SCHEDULE'],
            // 'Currency' => '',
            'priceHeaderGuest' => 'Guest',
            'priceHeader1End'  => 'Inclusive Amounts',
            'priceHeaderLast'  => 'Total',
            'priceHeaderTotal' => 'Gross Total',
            'cruiseFare'       => ['Cruise/Tour Fare', 'Cruise/Journey Fare'],
            // 'Booking Total' => '',
        ],
    ];

    private $lang = "en";
    private $code;

    private static $providers = [
        'hollandamerica' => [
            'from' => ['@hollandamerica.com'],
            'body' => [
                'Holland America',
                'www.hollandamerica.com',
                '@hollandamerica.com',
            ],
        ],

        'seabourn' => [
            'from' => ['@seabourn.com'],
            'body' => [
                'Seabourn',
                'www.seabourn.com',
            ],
        ],
    ];

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!empty($this->code)) {
            $email->setProviderCode($this->code);
        }

        $detectLanguage = false;

        $pdfTexts = [];

        $pdfsAgent = $parser->searchAttachmentByName('(?:Agent.*|.*Agent).pdf');

        if (count($pdfsAgent) === 1) {
            $this->textForAgent = \PDF::convertToText($parser->getAttachmentBody($pdfsAgent[0]));
        }

        $pdfs = $parser->searchAttachmentByName('(?:Guest.*|.*Guest|\_[A-Z]{2}).pdf');

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*\.pdf');
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            // Detect Language (PDF)
            if ($this->assignLangPdf($textPdf)) {
                $detectLanguage = true;
                $pdfTexts[] = $textPdf;
            }
        }

        if (!$detectLanguage) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseEmail($email, implode("\n", $pdfTexts));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($this->subjects as $phrases) {
                foreach ($phrases as $phrase) {
                    if (stripos($headers['subject'], $phrase) !== false) {
                        $bySubj = true;
                    }
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectProvider = false;
        $detectLanguage = false;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            // Detect Provider (PDF)
            if (!$detectProvider) {
                $detectProvider = $this->detectProviderPdf($textPdf);
            }

            // Detect Language (PDF)
            if (!$detectLanguage) {
                $detectLanguage = $this->assignLangPdf($textPdf);
            }

            if ($detectProvider && $detectLanguage) {
                return true;
            } elseif ($detectLanguage && strpos($textPdf, 'Ships\' Registry: The Netherlands') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $detects) {
            foreach ($detects['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
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
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function inOneRow(string $text): string
    {
        $textRows = explode("\n", $text);
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                if (isset($row[$l]) && (trim($row[$l]) !== '')) {
                    $notspace = true;
                    $oneRow[$l] = $row[$l];
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
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

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email, string $text): void
    {
        $text = preg_replace("/(\d+\:\d+)n/", "$1PM", $text);

        $patterns = [
            'confNumber'    => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
            'travellerName' => '[A-z][-.\'A-z ]*?[A-z]', // Mr. Hao-Li Huang
        ];

        $c = $email->add()->cruise();

        // General
        if (preg_match("/^.*({$this->opt($this->t('Booking #'))})[ ]*[:]+[ ]*([\w\d\-]{5,})(?:[ ]{2}|[ ]*$)/m", $text, $m)) {
            $c->general()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/\n\s*{$this->opt($this->t('Booking Status'))}\s*[:]+\s*(.+?)(?:[ ]{2}|\n)/", $text, $m)) {
            $c->general()->status($m[1]);
        }

        if ($c->getStatus() === 'Cancelled' || preg_match("/\b{$this->opt($this->t('Guest Cancellation Notification'))}\b/", $text, $m)) {
            $c->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        // travellers
        // accountNumbers
        if (preg_match("/\n\s*{$this->opt($this->t('Guest'))}.*?\b(\d{1,3})\s*\n\s*{$this->opt($this->t('Name'))}\s*[:]+\s*.+/", $text, $m)) {
            $passengerCount = (int) $m[1];
        }

        if (isset($passengerCount) && preg_match("/\n([ ]*{$this->opt($this->t('Name'))}\s*[:]+\s*.+?)\n\s*(?:Mariner ID:|Club Number:)/ms", $text, $m)) {
            $pText = $m[1];
            $columnPos = $this->rowColsPos($this->inOneRow($pText));
            $passengerCols = $this->splitCols($pText, $columnPos);
            unset($passengerCols[0]);

            if ($passengerCount !== count($passengerCols)) {
                unset($passengerCols);
                $pText = preg_replace("# ([A-Z][a-z]{1,3}\.)#", '  $1', $pText);
                $columnPos = $this->rowColsPos(explode("\n", $pText)[0]);
                $newText = '';

                if (count($columnPos) == $passengerCount + 1) {
                    $rows = explode("\n", $pText);
                    $newText = $rows[0];
                    unset($rows[0]);

                    foreach ($rows as $key => $value) {
                        foreach ($columnPos as $i => $n) {
                            if ($i > 0) {
                                $value = substr_replace($value, ' ', $n - 1, 0);
                            }
                        }
                        $newText .= "\n" . $value;
                    }

                    $pText = $newText;
                    $passengerCols = $this->splitCols($pText, $columnPos);
                    unset($passengerCols[0]);
                }
            }

            if (!empty($passengerCols)) {
                $c->general()->travellers(preg_replace("/^\s*(?:Mr|Ms|Miss|Mrs|Mstr|Dr)\. /i", '', array_map(function ($v) {return trim(str_replace("\n", " ", $v)); }, $passengerCols)), true);

                if (preg_match("#\n[ ]*Mariner ID\s*[:]+\s*(\d.+?)\n\s*(?:Air[:]+)?#ms", $text, $m)
                    || preg_match("#\n[ ]*Club Number\s*[:]+\s*(\d.+?)\n\s*(?:Club Membership[:]+)?#ms", $text, $m)) {
                    $accountText = $m[1];
                    $c->program()->accounts(array_filter(explode(' ', $accountText)), false);
                }
            }
        }

        if (
            preg_match("/^[ ]*({$this->opt($this->t('Booking #'))})[ ]*[:]+[ ]+{$this->opt($this->t('Name'))}[ ]*[:]+[ ]+{$this->opt($this->t('Agent'))}[ ]*[:]+(.+?)VISA NOTIFICATION/ims", $text, $m)
            && preg_match_all('/^[ ]*(?<confNumber>' . $patterns['confNumber'] . ')[ ]{2,}(?<traveller>' . $patterns['travellerName'] . ')(?:[ ]{2}|[ ]*$)/m', $m[2], $travellerMatches, PREG_SET_ORDER)
        ) {
            foreach ($travellerMatches as $matches) {
                $c->general()->confirmation($matches[1], $m[1]);
                $c->general()->traveller(preg_replace("/^\s*(?:Mr|Ms|Miss|Mrs|Mstr|Dr)\. /i", '', $matches[2]));
            }
        }

        // Details
        if (preg_match("/(?:\s{2,}|\n\s*){$this->opt($this->t('Ship'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|\n)/", $text, $m)) {
            $c->details()->ship($m[1]);
        }

        if (preg_match("/(?:\s{2,}|\n\s*){$this->opt($this->t('Category'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|\n)/", $text, $m)) {
            $c->details()->roomClass($m[1]);
        }

        if (preg_match("/(?:\s{2,}|\n\s*){$this->opt($this->t('Stateroom'))}[ ]*[:]+[ ]*(\d+?)(?:[ ]{2}|\n)/", $text, $m)) {
            $c->details()->room($m[1]);
        }

        if (preg_match("/(?:\s{2,}|\n\s*){$this->opt($this->t('Voyage'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|\n)/", $text, $m)) {
            $c->details()->number($m[1]);
        }

        if ($c->getCancelled() === true) {
            return;
        }

        if (preg_match("/{$this->opt($this->t('Day '))}\s+{$this->opt($this->t('Date'))}\s+{$this->opt($this->t('port'))}(?:\s+{$this->opt($this->t('Arrival '))}\s*{$this->opt($this->t('Departure'))}|\s*\n)(.+)/s", $text, $m)) {
            $legendNumbers = '';
            $legendText = strstr($m[1], 'Legend');

            if ($legendText && preg_match("/Legend\s*([\s\S]+?)\n\n/", $legendText, $lm)
                && preg_match_all("/^ *(\d+) /m", $lm[1] ?? '', $ln)
            ) {
                $legendNumbers = '(?:' . implode('|', $ln[1]) . ')';
            }

            $m[1] = preg_replace("/[\S\s]*?(\n\s*[A-Z]{3}\s+\d+\w{3}\d+\s+{$this->opt($this->t('Sail from'))})/", '$1', $m[1]);
            $m[1] = preg_replace("/(\n\s*[A-Z]{3}\s+\d+\w{3}\d+\s+{$this->opt($this->t('Debark Ship'))}.+\n(?:.*\n)*?)(?:\n|\s*[A-Z]{3}\s+\d+\w{3}\d+\s+)[\S\s]*/", '$1', $m[1]);
            $segments = $this->splitText($m[1], "/(\n\s*[A-Z]{3}\s+\d+[[:alpha:]]{3}\d+\s+.*?\s+\d+\s*:\s*\d+)/msu", true);
            $cruiseCount = 0;

            $notCompleteSegment = false;

            foreach ($segments as $key => $stext) {
                if (preg_match("#\n\s*([A-Z]{3})\s+(?<date>\d+\w{3}\d+)\s+(?<name>.*?)\s+(?<arr>\d+\s*:\s*\d+\s*\w{2})(?<dep>\s+\d+\s*:\s*\d+\s*\w{2})*#", $stext, $mat)) {
                    $name = $mat['name'];

                    if (preg_match("/^\s*{$this->opt($this->t('cruising'))} /", $name)) {
                        continue;
                    }
                    $name = preg_replace("/(?:{$this->opt($this->t('Sail from'))}|{$this->opt($this->t('Debark Ship'))})\s+/", '', $name);

                    if (!empty($legendNumbers)) {
                        $name = preg_replace("/ " . $legendNumbers . "(," . $legendNumbers . ")*$/", '', $name);
                    }

                    $cruiseCount++;
                    $arr = strtotime($mat['date'] . ', ' . $mat['arr']);

                    $dep = strtotime($mat['date'] . ', ' . trim($mat['dep'] ?? ''));

                    if (!empty($arr) && !empty($dep) && $arr == $dep
                        && (strpos($name, 'Enter ') === 0
                            || strpos($name, 'Exit ') === 0)) {
                        continue;
                    }

                    if ($notCompleteSegment === true) {
                        $notCompleteSegment = false;

                        /** @var \AwardWallet\Schema\Parser\Common\CruiseSegment $s */
                        if ($name === $s->getName() && empty($mat['dep'])) {
                            $s->setAboard($arr);

                            continue;
                        }
                    }

                    if (empty($mat['dep']) && $key !== 0 && $key !== count($segments) - 1) {
                        // Overnight stop
                        $notCompleteSegment = true;
                        $s = $c->addSegment();
                        $s->setName($name);
                        $s->setAshore($arr);

                        continue;
                    }

                    if ($key == 0) {
                        $s = $c->addSegment();
                        $s->setName($name);
                        $s->setAboard($arr);

                        continue;
                    } elseif ($key == count($segments) - 1) {
                        $s = $c->addSegment();
                        $s->setName($name);
                        $s->setAshore($arr);

                        continue;
                    }

                    $s = $c->addSegment();
                    $s->setName($name);
                    $s->setAshore($arr);
                    $s->setAboard($dep);
                }
            }
        } else {
            //it-653054766.eml
            $s = $c->addSegment();

            $aboardDate = strtotime($this->re("/{$this->opt($this->t('Embark Date:'))}\s*(.+,\s*\d{4})/", $text));
            $aboardName = $this->re("/{$this->opt($this->t('From Port:'))}[ ]{1,15}(.+)[ ]{40}/", $text);

            if (empty($aboardName)) {
                $aboardName = $this->re("/{$this->opt($this->t('From Port:'))}\s*(.+)/", $text);
            }

            $s->setName($aboardName)
                ->setAboard($aboardDate);

            $s = $c->addSegment();

            $ashoreDate = strtotime($this->re("/{$this->opt($this->t('Debark Date:'))}\s*(.+,\s*\d{4}\b)/", $text));

            if (empty($ashoreDate) && preg_match("/{$this->opt($this->t('Journey:'))}\s*(\d+)-Day/", $text, $m)) {
                $ashoreDate = strtotime('+' . $m[1] . ' days', $aboardDate);
            }

            $ashoreName = $this->re("/{$this->opt($this->t('To Port:'))}[ ]{1,15}(.+)[ ]{40}/", $text)
                ?? $this->re("/{$this->opt($this->t('To Port:'))}\s*(.+)/", $text);

            $s->setName($ashoreName)
                ->setAshore($ashoreDate);
        }

        if (empty($c->getConfirmationNumbers()) && preg_match("/^\s*{$this->opt($this->t('ITINERARY CHANGE NOTIFICATION'))}\n/", $text, $m)) {
            $c->general()->noConfirmation();
        }

        //it-642152063.eml
        if (empty($this->textForAgent)) {
            $this->priceGuests($c, $text);
        } else {
            $this->priceAgents($c, $this->textForAgent);
        }
    }

    private function priceAgents(\AwardWallet\Schema\Parser\Common\Cruise $c, $text)
    {
        $priceText = $this->re("/{$this->opt($this->t('priceStart'))}\s+(.+?){$this->opt($this->t('priceEnd'))}/s", $text);
        $currencies = [
            'U.S. Dollars'       => 'USD',
            'Canadian Dollars'   => 'CAD',
            'Australian Dollars' => 'AUD',
        ];

        $currencyText = $this->re("/^\s*{$this->opt($this->t('Currency'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)/im", $priceText);
        $currencyCode = $currencyText && array_key_exists($currencyText, $currencies) ? $currencies[$currencyText] : null;

        if (preg_match("/{$this->opt($this->t('cruiseFare'))}.*\s\D{1,3}([\d\.\,\']+)\s*\n/", $priceText, $m)) {
            $c->price()
                ->cost(PriceHelper::parse($m[1], $currencyCode));
        }

        if (preg_match("/{$this->opt($this->t('Booking Total'))}\:?\s*\D{1,3}([\d\.\,\']+)\s+/", $priceText, $m)) {
            $c->price()
                ->total(PriceHelper::parse($m[1], $currencyCode));
        }

        $priceRows = array_filter(explode("\n", $priceText));

        foreach ($priceRows as $priceRow) {
            if (!preg_match("/(?:{$this->opt($this->t('priceHeaderTotal'))}|{$this->opt($this->t('cruiseFare'))}|{$this->opt($this->t('Booking Total'))})/u", $priceRow)
                && preg_match("/^(?<feeName>.+[a-z])[ ]{2}.*\s\D{1,3}\s*(?<feeSumm>[\d\.\,\']+)$/", $priceRow, $m)) {
                $c->price()->fee($m['feeName'], PriceHelper::parse($m['feeSumm'], $currencyCode));
            }
        }

        if (preg_match("/\s+Inclusive Amounts\s+{$this->opt($this->t('priceHeaderTotal'))}\s+(?<feeName>.+)\s+Net Total\n+{$this->opt($this->t('cruiseFare'))}\s+\D{1,3}[\d\.\,\']+\s+\D{1,3}(?<feeSumm>[\d\.\,\']+)\s+\D{1,3}[\d\.\,\']+/", $priceText, $match)) {
            $c->price()->fee($match['feeName'], PriceHelper::parse($match['feeSumm'], $currencyCode));
        }

        if (!empty($currencyCode)) {
            $c->price()->currency($currencyCode);
        }
    }

    private function priceGuests(\AwardWallet\Schema\Parser\Common\Cruise $c, $text)
    {
        // Price
        $priceText = $this->re("/{$this->opt($this->t('priceStart'))}\s+(.+?){$this->opt($this->t('priceEnd'))}/s", $text);
        $tablePos = [0];

        if (preg_match("/^(.+?[ ]{2}{$this->opt($this->t('priceHeaderGuest'))}[- ]+1)\b/im", $priceText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]) - 9;
        } elseif (preg_match("/^(.+?[ ]{2}){$this->opt($this->t('priceHeader1End'))}/im", $priceText, $matches)) {
            // it-642152063.eml
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+[ ]{2}{$this->opt($this->t('priceHeaderLast'))})$/im", $priceText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]) - 13;
        } elseif (preg_match("/^(.+?[ ]{2}){$this->opt($this->t('priceHeaderTotal'))}/im", $priceText, $matches)) {
            // it-642152063.eml
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (count($tablePos) !== 3) {
            $this->logger->debug('Price table is wrong!');

            return;
        }

        $currencies = [
            'U.S. Dollars'       => 'USD',
            'Canadian Dollars'   => 'CAD',
            'Australian Dollars' => 'AUD',
        ];

        $currencyText = $this->re("/^\s*{$this->opt($this->t('Currency'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)/im", $priceText);
        $currencyCode = $currencyText && array_key_exists($currencyText, $currencies) ? $currencies[$currencyText] : null;

        $priceRows = array_reverse($this->splitText($priceText, "/^(.{" . $tablePos[1] . ",}\d\.\d{2}\b)/m", true));
        $currency = null;

        $feeNames = [];

        foreach ($priceRows as $i => $priceRow) {
            $table = $this->splitCols($priceRow, $tablePos);
            $table[2] = preg_replace('/^(.{12,}?)[ ]{2}\S.*$/m', '$1', $table[2]); // for it-642152063.eml

            if ($i === 0) {
                if (preg_match("/^\s*{$this->opt($this->t('Booking Total'))}\b/i", $table[0])) {
                    if (preg_match('/^\s*(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)\s*$/u', $table[2], $matches)) {
                        // $11,935.10
                        $currency = $matches['currency'];
                        $c->price()->total(PriceHelper::parse($matches['amount'], $currencyCode));
                    }
                } else {
                    break;
                }

                continue;
            }

            if (preg_match("/^\s*{$this->opt($this->t('cruiseFare'))}/i", $table[0])) {
                //break;
                if (preg_match('/^\s*(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)\s*$/u', $table[2], $matches)) {
                    // $11,935.10
                    $currency = $matches['currency'];
                    $c->price()->cost(PriceHelper::parse($matches['amount'], $currencyCode));
                }

                continue;
            }

            if (!preg_match("/^\s*{$this->opt($this->t('Booking Total'))}\b/i", $table[0])
                && preg_match('/^\s*(?:' . preg_quote($currency, '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)\s*$/u', $table[2], $m)) {
                //remove junk
                $table[0] = preg_replace("/\D\d[\d\.\,\']+/", "", $table[0]);

                $feeName = trim(preg_replace('/\s+/', ' ', $table[0]), '*: ');

                $feeNames[] = $feeName;

                $c->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $c->price()->currency($currencyCode);
    }

    private function detectProviderPdf($text): bool
    {
        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($text, $search) !== false) {
                        $this->code = $code;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLangPdf($text): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false && isset(self::$dictionary[$lang])
                    && !empty(self::$dictionary[$lang]['Day ']) && strpos($text, self::$dictionary[$lang]['Day ']) !== false
                    && !empty(self::$dictionary[$lang]['port']) && preg_match("/{$this->opt(self::$dictionary[$lang]['port'])}/", $text) > 0
                    && !empty(self::$dictionary[$lang]['Arrival ']) && strpos($text, self::$dictionary[$lang]['Arrival ']) !== false
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }

            //it-653054766.eml
            if (!empty(self::$dictionary[$lang]['From Port:']) && stripos($text, self::$dictionary[$lang]['From Port:']) !== false
                && !empty(self::$dictionary[$lang]['To Port:']) && stripos($text, self::$dictionary[$lang]['To Port:']) !== false
                && !empty(self::$dictionary[$lang]['Embark Date:']) && strpos($text, self::$dictionary[$lang]['Embark Date:']) !== false
                && !empty(self::$dictionary[$lang]['Arrival ']) && strpos($text, self::$dictionary[$lang]['Arrival ']) === false
            ) {
                $this->lang = $lang;

                return true;
            }

            //it-653054766.eml
            if (!empty(self::$dictionary[$lang]['Guest Cancellation Notification']) && stripos($text, self::$dictionary[$lang]['Guest Cancellation Notification']) !== false
                && !empty(self::$dictionary[$lang]['Embark Date:']) && strpos($text, self::$dictionary[$lang]['Embark Date:']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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
