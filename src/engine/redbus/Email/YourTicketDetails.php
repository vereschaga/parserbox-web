<?php

namespace AwardWallet\Engine\redbus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourTicketDetails extends \TAccountChecker
{
    public $mailFiles = "redbus/it-211515060.eml, redbus/it-209095684.eml, redbus/it-214360910.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'boardingPoint'  => ['Boarding Point'],
            'droppingPoint'  => ['Dropping Point'],
            'ticketNumber'   => ['Ticket Number:', 'Ticket Number :'],
            'pnr'            => ['PNR No:', 'PNR No :'],
            'statusPhrases'  => ['Your booking has been'],
            'statusVariants' => ['confirmed'],
        ],
    ];

    private $subjects = [
        'en' => ['Ticket -', 'Ticket Information -'],
    ];

    private $detectors = [
        'en' => ['Your ticket details', 'Onward Trip', 'Ticket Details'],
    ];

    private $enDatesInverted = true;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@redbus.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'redBus') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".redbus.com/") or contains(@href,"www.redbus.com") or contains(@href,"s.redbus.com") or contains(@href,"b.redbus.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing redBus") or contains(normalize-space(),"redBus Ticket Information")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourTicketDetails' . ucfirst($this->lang));

        $xpathParagraph = "(not(.//p) and not(.//tr))";

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $bus = $email->add()->bus();

        $topRow = implode(' ', $this->http->FindNodes("descendant::*[{$xpathParagraph} and {$this->starts($this->t('ticketNumber'))} and {$this->contains($this->t('pnr'))}][last()]/descendant::text()[normalize-space()]"));
        $this->logger->debug('TOP ROW: ' . $topRow);

        if (preg_match("/{$this->opt($this->t('ticketNumber'))}\s*([-A-Z\d ]{5,}?)(?:\s*\|.+|$)/", $topRow, $m)) {
            $bus->addTicketNumber($m[1], false);
        }

        if (preg_match("/({$this->opt($this->t('pnr'))})\s*(?-i)([A-Z\d ]{5,}?)(?:[ ]*[-#][-()A-z\d\/ ]+)?$/i", $topRow, $m)) {
            // PNR No: 16085452#OSME20221028RBYJG25F/OSME20221028RBYH2V1S
            // PNR No: ATV112375-ARBCVR(NDL-BNGL1)
            // PNR No: BRS189166-BRS Allagada
            $bus->general()->confirmation($m[2], trim($m[1], ': '));
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $bus->general()->status($status);
        }

        $s = $bus->addSegment();

        $dateTimeDep = $this->http->FindSingleNode("//*[{$xpathParagraph} and {$this->eq($this->t('Journey Date and Time'))}]/following::*[{$xpathParagraph} and normalize-space()][1]");

        if (preg_match("/^(?<date>.*?\d.*?)\s*[,]+\s*(?<time>{$patterns['time']})/u", $dateTimeDep, $m)) {
            $dateDep = strtotime($this->normalizeDate($m['date']));

            if ($dateDep) {
                $s->departure()->date(strtotime($m['time'], $dateDep));
            }
        }

        $amountPaid = $this->http->FindSingleNode("//*[{$xpathParagraph} and {$this->eq($this->t('Amount Paid'))}]/following::*[{$xpathParagraph} and normalize-space()][1]", null, true, '/^(.*?\d.*?)(?:\s*\(|$)/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $amountPaid, $matches)) {
            // IDR 352785.83    |    Rs. 945.0
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $bus->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $pattern = '/^\s*.{3,}((?:\n+[ ]*.{3,}){1,2})\s*$/';

        $boardingPointRows = [];
        $boardingPointNodes = $this->http->XPath->query("//*[{$xpathParagraph} and {$this->eq($this->t('Boarding Point'))}]/following-sibling::*[normalize-space()][1]/descendant::*[ div[normalize-space()][2] ][1]/div[normalize-space()]");

        if ($boardingPointNodes->length === 0) {
            // it-211515060.eml
            $boardingPointNodes = $this->http->XPath->query("//*[{$xpathParagraph} and {$this->eq($this->t('Boarding Point'))}]/following-sibling::node()[normalize-space()][1]");
        }

        foreach ($boardingPointNodes as $bpNode) {
            $boardingPointRows[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $bpNode));
        }

        $boardingPoint = implode("\n", $boardingPointRows);
        $boardingPoint = preg_replace("/^([\s\S]{3,}?)\n+{$this->opt($this->t('Landmark:'))}[\s\S]*/", '$1', $boardingPoint);
        $this->logger->debug("BOARDING POINT:\n" . $boardingPoint);

        if (preg_match($pattern, $boardingPoint, $m) && preg_match('/[[:alpha:]]/u', $m[1])) {
            $s->departure()->address(preg_replace('/[, ]*\n+[, ]*/', ', ', trim($m[1])));
        }

        $droppingPointRows = [];
        $droppingPointNodes = $this->http->XPath->query("//*[{$xpathParagraph} and {$this->eq($this->t('Dropping Point'))}]/following-sibling::*[normalize-space()][1]/descendant::*[ div[normalize-space()][2] ][1]/div[normalize-space()]");

        if ($droppingPointNodes->length === 0) {
            // it-211515060.eml
            $droppingPointNodes = $this->http->XPath->query("//*[{$xpathParagraph} and {$this->eq($this->t('Dropping Point'))}]/following-sibling::node()[normalize-space()][1]");
        }

        foreach ($droppingPointNodes as $dpNode) {
            $droppingPointRows[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $dpNode));
        }

        $droppingPoint = implode("\n", $droppingPointRows);

        if (preg_match("/^\s*([\s\S]{3,}?)[ ]*\n+[ ]*{$this->opt($this->t('DROPPING DATE & TIME'))}[ ]*[:]+\s*([\s\S]+)$/i", $droppingPoint, $m)) {
            $droppingPoint = $m[1];
            $dateTimeArr = $m[2];
        } else {
            $dateTimeArr = null;
        }

        $this->logger->debug("DROPPING POINT:\n" . $droppingPoint);

        if (preg_match($pattern, $droppingPoint, $m) && preg_match('/[[:alpha:]]/u', $m[1])) {
            $s->arrival()->address(preg_replace('/[, ]*\n+[, ]*/', ', ', trim($m[1])));
        }

        if ($dateTimeArr) {
            if (preg_match("/^(?<date>.*?\d.*?)\s*[,]+\s*(?<time>{$patterns['time']})/u", $dateTimeArr, $m)) {
                $dateArr = strtotime($this->normalizeDate($m['date']));

                if ($dateArr) {
                    $s->arrival()->date(strtotime($m['time'], $dateArr));
                }
            }
        } elseif (!empty($s->getDepDate())) {
            $s->arrival()->noDate();
        }

        $travellers = $seats = [];

        $passengerRows = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Passenger Details'))}] and *[2][{$this->eq($this->t('Seat no'))}] ]/following-sibling::tr[normalize-space()]");

        foreach ($passengerRows as $pRow) {
            $pName = $this->http->FindSingleNode("*[1]/descendant::text()[normalize-space() and not(contains(.,','))]", $pRow, true, "/^{$patterns['travellerName']}$/u");

            if ($pName) {
                $travellers[] = $pName;
            }
            $pSeat = $this->http->FindSingleNode("*[2]", $pRow, true, '/^[A-Z\d][-\/ A-Z\d]*$/');

            if ($pSeat) {
                $seats[] = $pSeat;
            }
        }

        if (count($travellers) > 0) {
            $bus->general()->travellers($travellers);
        }

        if (count($seats) > 0) {
            $s->extra()->seats($seats);
        }

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $bus->general()->traveller($traveller);
        }

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

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['boardingPoint']) || empty($phrases['droppingPoint'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['boardingPoint'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['droppingPoint'])}]")->length > 0
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 21/10/2022
            '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',
        ];
        $out[0] = $this->enDatesInverted ? '$2/$1/$3' : '$1/$2/$3';

        return preg_replace($in, $out, $text);
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'INR' => ['Rs.'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
