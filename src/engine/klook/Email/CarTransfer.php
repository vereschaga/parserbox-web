<?php

namespace AwardWallet\Engine\klook\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarTransfer extends \TAccountChecker
{
    public $mailFiles = "klook/it-447475935.eml, klook/it-648738830.eml, klook/it-764608654.eml, klook/it-763461328-cancelled.eml";
    public $subjects = [
        'Your booking confirmation for Airport Transfers - ',
        'Booking cancelled for Airport Transfers - ',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'hi'               => ['Hey there', 'Hey'],
            'statusPhrases'    => ['your booking has been'],
            'statusVariants'   => ['confirmed'],
            'cancelledPhrases' => ['has been cancelled', 'has been canceled'],
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@klook.com') !== false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".klook.com/") or contains(@href,"click.klook.com")] | //text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Klook Travel")] | //*[contains(normalize-space(),"Thanks for booking with Klook") or contains(normalize-space(),"Website: www.klook.com")]')->length > 0 && ($this->findRoot1()->length > 0 || $this->findRoot2()->length > 0)) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if ($this->detectPdf2($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]klook\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->findRoot1()->length === 1) {
            $this->parseTransferHtml1($email, $this->findRoot1()->item(0));
        } elseif ($this->findRoot2()->length === 1) {
            $this->parseTransferHtml2($email, $this->findRoot2()->item(0));
        } else {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (empty($text)) {
                    continue;
                }

                if ($this->detectPdf2($text)) {
                    $this->parseTransferPdf2($email, $text);
                }
            }
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

    private function detectPdf1($text): bool
    {
        // TODO: need to add

        return false;
    }

    private function detectPdf2($text): bool
    {
        if (empty($text)) {
            return false;
        }

        if (strpos($text, "Klook") === false) {
            return false;
        }

        if ($this->containsText($text, 'Airport Transfers') === true
            && ($this->containsText($text, 'Vehicle & service details') === true)
        ) {
            return true;
        }

        return false;
    }

    private function parseTransferPdf1(Email $email, string $text): void
    {
        // examples: it-764608654.eml
        $this->logger->debug(__FUNCTION__);

        $t = $email->add()->transfer();

        // TODO: need to add
    }

    private function parseTransferPdf2(Email $email, string $text): void
    {
        // examples: it-447475935.eml
        $this->logger->debug(__FUNCTION__);

        $t = $email->add()->transfer();

        $t->general()
            ->confirmation($this->re("/Booking reference ID.*\n+\s*([A-Z\d]{6,})\s*/i", $text), 'Booking reference ID')
            ->traveller($this->re("/{$this->addSpacesWord('Lead participant')}.*\n+\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]{10,}/i", $text), true);

        $s = $t->addSegment();

        $locationText = $this->re("/{$this->addSpacesWord('From')}\s*To\n+((?:.+\n+){1,})\s*{$this->addSpacesWord('Lead participant')}/", $text);

        $locationTable = $this->splitCols($locationText);

        if (count($locationTable) !== 2) {
            $locationTable = $this->splitCols($locationText, [0, 38]);
        }

        if (count($locationTable) == 2) {
            $locationTable[0] = $this->normalizeLocation(preg_replace('/\s+/', ' ', $locationTable[0]));

            if (preg_match($pattern = "/^(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*$/s", $locationTable[0], $m)) {
                $s->departure()->name($m['name'])->code($m['code']);
            } else {
                $s->departure()->name($locationTable[0]);
            }

            $s->departure()
                ->date(strtotime($this->re("/{$this->addSpacesWord('Date & time')}.*\n+.+[ ]{10,}(\w+.*[\d\:]+\s*A?P?M?)\s*\(/iu", $text)));

            $locationTable[1] = $this->normalizeLocation(preg_replace('/\s+/', ' ', $locationTable[1]));

            if (preg_match($pattern, $locationTable[1], $m)) {
                $s->arrival()->name($m['name'])->code($m['code']);
            } else {
                $s->arrival()->name($locationTable[1]);
            }

            $s->arrival()
                ->noDate();
        }
        $s->extra()
            ->model($this->re("/Airport Transfers\s*\-.*\n+(.+)/", $text))
            ->adults($this->re("/(?:Passengers No. of Passenger\(s\)|No\. of passengers No\. of Passenger\(s\)|{$this->addSpacesWord('No . o f passengers')} ).*\n+\s*(\d+)/", $text));
    }

    private function parseTransferHtml1(Email $email, \DOMNode $root): void
    {
        // examples: it-764608654.eml
        $this->logger->debug(__FUNCTION__);

        $otaConfirmation = $otaConfirmationTitle = null;

        if (preg_match("/^({$this->opt($this->t('Booking reference ID'))})[:\s]+([-A-Z\d]{4,40})$/", $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference ID'))}]"), $m)) {
            $otaConfirmationTitle = $m[1];
            $otaConfirmation = $m[2];
        }

        $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

        $t = $email->add()->transfer();

        if ($otaConfirmation) {
            $t->general()->noConfirmation();
        }

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('hi'))}]", null, "/^{$this->opt($this->t('hi'))}[,\s]+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $t->general()->traveller($traveller);
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $t->general()->status($status);
        }

        $s = $t->addSegment();

        $carType = $this->http->FindSingleNode("preceding::text()[{$this->contains($this->t('or similar'))}]/ancestor-or-self::node()[ preceding-sibling::node()[normalize-space() and not(self::comment())] ][1]/preceding-sibling::node()[normalize-space() and not(self::comment())]", $root);
        $carImage = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1]/descendant::text()[{$this->eq($this->t('Pick Up Date & Time'), "translate(.,':','')")}] ]/*[2]/descendant::img[normalize-space(@src)]/@src");

        $dateAndTimeVal = '';
        $dateAndTimeValues = array_filter($this->http->FindNodes("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Pick Up Date & Time'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]", null, "/^.*{$this->patterns['time']}.*$/"));

        if (count(array_unique($dateAndTimeValues)) === 1) {
            $dateAndTimeVal = array_shift($dateAndTimeValues);
        }

        if (preg_match("/^(?<date>.{4,}?\b\d{4})\s*,\s*(?<time>{$this->patterns['time']})/", $dateAndTimeVal, $m)) {
            $s->departure()->date(strtotime($m['time'], strtotime($m['date'])));
            $s->arrival()->noDate();
        }

        $pointDep = $pointArr = '';
        $fromToVal = $this->normalizeLocation($this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('From - To'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]") ?? '');

        if (preg_match("/^(.{3,}?\(\s*[A-Z]{3}\s*\))-(.{3,})$/", $fromToVal, $m)
            || preg_match("/^(.{3,}?\))-([[:upper:]][[:lower:]].+)$/u", $fromToVal, $m)
            || preg_match("/^([^\-]{3,}?)\s*-\s*([^\-]{3,})$/", $fromToVal, $m)
        ) {
            // Singapore Changi Airport (SIN)-Pasir Ris Close, Singapore(D'Resort @ Downtown East)
            // 6-chōme-10-3 Roppongi, Minato City, Tokyo 106-0032, Japan(Grand Hyatt Tokyo)-Tokyo Haneda International Airport (HND)
            $pointDep = $m[1];
            $pointArr = $m[2];
        }

        if (preg_match($pattern = "/^(?<name>.{2,}?)[\s(]+(?<code>[A-Z]{3})[\s)]*$/", $pointDep, $m)) {
            $s->departure()->name($m['name'])->code($m['code']);
        } elseif ($pointDep) {
            $s->departure()->name($pointDep);
        }

        if (preg_match($pattern, $pointArr, $m)) {
            $s->arrival()->name($m['name'])->code($m['code']);
        } elseif ($pointArr) {
            $s->arrival()->name($pointArr);
        }

        $noOfPassengers = $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('No. of Passenger(s)'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]", null, true, "/^\d{1,3}$/");
        $s->extra()->adults($noOfPassengers, false, true);

        $carModel = $this->http->FindSingleNode("//*[ node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Vehicle Information'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]", null, true, "/^{$this->opt($this->t('Type'))}[:\s]+(.{2,})$/");
        $s->extra()->type($carType, false, true)->image($carImage, false, true)->model($carModel, false, true);

        $cancellation = $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Cancellation policy'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]");
        $t->general()->cancellation($cancellation, false, true);
    }

    private function parseTransferHtml2(Email $email, \DOMNode $root): void
    {
        // examples: it-648738830.eml
        $this->logger->debug(__FUNCTION__);

        $otaConfirmation = $otaConfirmationTitle = null;

        if (preg_match("/^({$this->opt($this->t('Booking reference ID'))})[:\s]+([-A-Z\d]{4,40})$/", $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference ID'))}]"), $m)) {
            $otaConfirmationTitle = $m[1];
            $otaConfirmation = $m[2];
        }

        $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

        $t = $email->add()->transfer();

        $dateBooking = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booked:')]", null, true, "/{$this->opt($this->t('Booked:'))}\s*(.+)/");

        if (!empty($dateBooking)) {
            $t->general()
                ->date(strtotime($dateBooking));
        }

        if ($otaConfirmation) {
            $t->general()->noConfirmation();
        }

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('hi'))}]", null, "/^{$this->opt($this->t('hi'))}[,\s]+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $t->general()->traveller($traveller);
        }

        $s = $t->addSegment();

        $depName = $this->normalizeLocation($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('From:'))}\s*(.+)/")
            ?? $this->http->FindSingleNode("ancestor-or-self::node()[ normalize-space() and not(self::comment()) and not(preceding-sibling::node()[normalize-space() and not(self::comment())]) and following-sibling::node()[normalize-space() and not(self::comment())] ][1]/following-sibling::node()[normalize-space() and not(self::comment())]", $root));

        if (preg_match($pattern = "/^(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3}\b).*\)$/", $depName, $m)) {
            $s->departure()
                ->name($m['name'])
                ->code($m['code']);
        } else {
            $s->departure()
                ->name($depName);
        }

        $depDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pick Up Date & Time'))}]", null, true, "/{$this->opt($this->t('Pick Up Date & Time'))}[:\s]+(.+?)\s*\(/")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Pick Up Date & Time'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]");
        $depDate = str_replace("fin", ":", $depDate);
        $depDate = str_replace("un", " ", $depDate);
        $depDate = preg_replace("/(\d{1,2}:\d{2}):\d{2}/", '$1', $depDate); // 22:10:00  ->  22:10

        if (preg_match("/^(?<date>.{4,}?\b\d{4}|\d{4}-.{3,}?)[,\s]+(?<time>{$this->patterns['time']})/", $depDate, $m)) {
            // 29 Jul 2023 1:25pm    |    2024-06-27 22:10:00
            $s->departure()->date(strtotime($m['time'], strtotime($m['date'])));
            $s->arrival()->noDate();
        }

        $arrName = $this->normalizeLocation($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'To:')]", null, true, "/{$this->opt($this->t('To:'))}\s*(.+)/")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('To:'))}] ]/node()[normalize-space() and not(self::comment())][2]"));

        if (preg_match($pattern, $arrName, $m)) {
            $s->arrival()
                ->name($m['name'])
                ->code($m['code']);
        } else {
            $s->arrival()
                ->name($arrName);
        }

        $adults = $this->http->FindSingleNode("//text()[{$this->starts($this->t('No. of Passenger(s)'))}]", null, true, "/{$this->opt($this->t('No. of Passenger(s)'))}[:\s]+(\d{1,3})\b/")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('No. of Passenger(s)'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]", null, true, "/^\d{1,3}\b/");

        if (!empty($adults)) {
            $s->setAdults($adults);
        }

        $carInfo = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $root);

        if (preg_match("/(?<type>.+)\s*\((?<model>.+or\s*similar)\)/", $carInfo, $m)
            || preg_match("/(?<type>.+)\s*\(Model guaranteed:(?<model>.+)\)/", $carInfo, $m)
            || preg_match("/^(?<model>.+(?:or\s*similar)?)/", $carInfo, $m)) {
            $s->setCarModel($m['model']);

            if (isset($m['type']) && !empty($m['type'])) {
                $s->setCarType($m['type']);
            } else {
                $carType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle Information'), "translate(.,':','')")}]/following::text()[normalize-space()][1][starts-with(normalize-space(), 'Type:')]", null, true, "/{$this->opt($this->t('Type:'))}\s*(.+)/");

                if (!empty($carType)) {
                    $s->setCarType($carType);
                }
            }
        }

        $carImage = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]/preceding::*[1]/@src", $root);

        if (!empty($carImage)) {
            $s->setCarImageUrl($carImage);
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $t->general()->cancelled();

            return;
        }

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Amount:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Amount:'))}\s*([A-Z\D]{1,3}\s*[\d\.\,]+)/");

        if (preg_match("/^(?<currency>[A-Z\D]{1,3})\s*(?<total>[\d\.\,]+)$/", $total, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $t->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }
    }

    private function findRoot1(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[{$this->eq($this->t('From - To'), "translate(.,':','')")}]");
    }

    private function findRoot2(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[ {$this->starts($this->t('From:'))} and following::text()[{$this->starts($this->t('To:'))}]/following::text()[{$this->eq($this->t('Vehicle Information'), "translate(.,':','')")}] and not(following::text()[{$this->contains($this->t('Booking reference ID'))}]) ]");
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function addSpacesWord($text): string
    {
        return preg_replace("#(\w)#u", '$1 *', $text);
    }

    private function splitCols($text, $pos = false): array
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'USD' => ['US$'],
            'SGD' => ['S$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function normalizeLocation(?string $s): ?string
    {
        if ($s !== null) {
            $s = str_ireplace(['Ō', 'ō'], ['O', 'o'], $s);
        }

        return $s;
    }
}
