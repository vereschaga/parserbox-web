<?php

namespace AwardWallet\Engine\aurigny\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Common\Parser\Util\PriceHelper;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "aurigny/it-862515351.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Booking reference'],
            'Seat Selection' => ['Seat Selection'],
            'statusVariants' => ['Confirmed'],
        ]
    ];

    private $travellers = [];
    private $patterns = [
        'date' => '\b\d{4}-\d{1,2}-\d{1,2}\b', // 2024-08-28
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]aurigny\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return array_key_exists('subject', $headers)
            && stripos($headers['subject'], 'Your Aurigny booking confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $href = ['.aurigny.com/', 'www.aurigny.com'];

        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true
            && $this->http->XPath->query("//a[{$this->contains($href, '@href')} or {$this->contains($href, '@originalsrc')}]")->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"© Aurigny")]')->length === 0
        ) {
            return false;
        }
        return $this->findSegments()->length > 0;
    }

    private function findSegments(): \DOMNodeList
    {
        $xpathTime = 'contains(translate(.,"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';
        return $this->http->XPath->query("//tr[ count(*)=3 and *[1][{$xpathTime}] and *[3][{$xpathTime}] ]");
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('YourBooking' . ucfirst($this->lang));

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,10}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = null;

            $preRoots = $this->http->XPath->query("preceding::*[../self::tr and normalize-space() and not(.//tr[normalize-space()])][1]", $root);
            $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;

            while ($preRoot) {
                $dateVal = $this->htmlToText( $this->http->FindHTMLByXpath(".", null, $preRoot) );

                if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,5})\s+(?<date>{$this->patterns['date']})/", $dateVal, $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                    $date = strtotime($m['date']);

                    $status = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $preRoot, true, "/^{$this->opt($this->t('statusVariants'))}$/i");
                    $s->extra()->status($status, false, true);

                    $this->parseSeats($s, $preRoot);

                    break;
                }

                $preRoots = $this->http->XPath->query("preceding::*[../self::tr and normalize-space() and not(.//tr[normalize-space()])][1]", $preRoot);
                $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;
            }

            $timeDep = $timeArr = null;

            $departure = $this->htmlToText( $this->http->FindHTMLByXpath("*[1]", null, $root) );
            $arrival = $this->htmlToText( $this->http->FindHTMLByXpath("*[3]", null, $root) );

            $pattern = "/^\s*(?<name>\S.+?)[ ]*\n+[ ]*(?<time>{$this->patterns['time']})/s";

            if (preg_match($pattern, $departure, $m)) {
                $s->departure()->name(preg_replace('/\s+/', ' ', $m['name']))->noCode();
                $timeDep = $m['time'];
            }

            if (preg_match($pattern, $arrival, $m)) {
                $s->arrival()->name(preg_replace('/\s+/', ' ', $m['name']))->noCode();
                $timeArr = $m['time'];
            }

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }
        }

        if (count($this->travellers) > 0) {
            $f->general()->travellers($this->travellers, true);
        }

        /* price */

        $amounts = $currencies = [];

        $paymentRows = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Item'))}] and *[normalize-space()][2][{$this->contains($this->t('Amount'))}] ]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following::*[count(*[normalize-space()])=2][ following::a[{$this->eq($this->t('VIEW BREAKDOWN'))}] ]");

        foreach ($paymentRows as $row) {
            $amountVal = $this->http->FindSingleNode("*[normalize-space()][2]/descendant-or-self::*[ count(*)=2 and *[2][normalize-space()] ][1]/*[2]", $row);

            if (preg_match('/^(?<currency>[^\-\d)(]{1,11}?)[ ]*(?<amount>\d[,.’‘\'\d ]*)$/u', $amountVal, $matches)) {
                // £415.96
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $amounts[] = PriceHelper::parse($matches['amount'], $currencyCode);
                $currencies[] = $matches['currency'];
            }
        }

        if (count(array_unique($currencies)) === 1) {
            $currency = array_shift($currencies);
            $f->price()->currency($currency)->total(array_sum($amounts));
        }

        return $email;
    }

    private function parseSeats(FlightSegment $s, \DOMNode $segIdNode): void
    {
        $segId = $this->http->FindSingleNode(".", $segIdNode, false);

        if ($segId === null) {
            return;
        }

        $seatRows = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($segId)}] and *[normalize-space()][2][{$this->contains($this->t('Seat Selection'))}] ]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following::*[count(*[normalize-space()])=2]");

        foreach ($seatRows as $row) {
            if ($this->http->XPath->query("*[normalize-space()][2][{$this->contains($this->t('Seat Selection'))}]", $row)->length > 0) {
                break;
            }

            $seatVal = $this->http->FindSingleNode("*[normalize-space()][2]/descendant-or-self::*[ count(*)=2 and *[2][normalize-space()] ][1]/*[2]", $row, true, "/^(?:\d+[A-Z]|(?i){$this->opt($this->t('Seat not assigned'))})$/");

            $passengerName = $seatVal === null ? null
                : $this->normalizeTraveller($this->http->FindSingleNode("*[normalize-space()][1]", $row, true, "/^{$this->patterns['travellerName']}$/u"));

            if ($passengerName && !in_array($passengerName, $this->travellers)) {
                $this->travellers[] = $passengerName;
            }

            if ($seatVal !== null && preg_match("/^\d+[A-Z]$/", $seatVal)) {
                $s->extra()->seat($seatVal, false, false, $passengerName);
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) ) {
                continue;
            }
            if (!empty($phrases['confNumber']) && $this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                || !empty($phrases['Seat Selection']) && $this->http->XPath->query("//*[{$this->contains($phrases['Seat Selection'])}]")->length > 0
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$1',
            '$1',
            '$2 $1',
        ], $s);
    }
}
