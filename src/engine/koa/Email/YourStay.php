<?php

namespace AwardWallet\Engine\koa\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourStay extends \TAccountChecker
{
    public $mailFiles = "koa/it-168060199.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['YOUR RESERVATION NUMBER IS', 'Your reservation number is'],
            'dates'      => ['Travel Dates'],
        ],
    ];

    private $subjects = [
        'en' => ['Information for your stay at'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Kampgrounds of America') !== false
            || stripos($from, '@email.koa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'KOA Holiday') === false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".koa.com/") or contains(@href,"//koa.com/") or contains(@href,"familycamping.koa.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Check with specific KOA locations for detail") or contains(.,"@twinfallskoa.com") or contains(.,"@crescentcitykoa.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourStay' . ucfirst($this->lang));

        $this->parseHotel($email);

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

    private function parseHotel(Email $email): void
    {
        $patterns = [
            'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        $h = $email->add()->hotel();

        $xpathColumns = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->starts($this->t('confNumber'))}] ]";

        $lColumnText = $this->htmlToText($this->http->FindHTMLByXpath($xpathColumns . "/*[normalize-space()][1]"));
        // $this->logger->notice($lColumnText);

        if (preg_match("/^\s*(?<name>.{2,}?)[ ]*\n[ ]*(?<address>(?:.{2,}\n){1,3})(?:\n|[ ]*{$this->opt($this->t('Phone'))}[ ]*:)/", $lColumnText, $m)) {
            $h->hotel()->name($m['name'])->address(preg_replace('/[ ]*\n+[ ]*/', ', ', trim($m['address'])));
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('Phone'))}[ ]*[:]+[ ]*({$patterns['phone']})(?:[ ]*\n|\s*$)/", $lColumnText, $m)) {
            $h->hotel()->phone($m[1]);
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('Fax'))}[ ]*[:]+[ ]*({$patterns['phone']})(?:[ ]*\n|\s*$)/", $lColumnText, $m)) {
            $h->hotel()->fax($m[1]);
        }

        $timeCheckIn = $this->http->FindSingleNode($xpathColumns . "/*[normalize-space()][1]/descendant::text()[{$this->contains($this->t('Check-in:'))}][1]", null, true, "/{$this->opt($this->t('Check-in:'))}\s*({$patterns['time']})/");

        $timeCheckOut = $this->http->FindSingleNode($xpathColumns . "/*[normalize-space()][1]/descendant::text()[{$this->contains($this->t('Check-out:'))}][last()]", null, true, "/{$this->opt($this->t('Check-out:'))}\s*({$patterns['time']})/");

        $rColumnPp = [];
        $rColumnPNodes = $this->http->XPath->query($xpathColumns . "/*[normalize-space()][2]/descendant::p[normalize-space()]");

        foreach ($rColumnPNodes as $pNode) {
            $rColumnPp[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $pNode));
        }
        $rColumnText = implode("\n\n", $rColumnPp);
        // $this->logger->alert($rColumnText);

        if (preg_match("/^\s*({$this->opt($this->t('confNumber'))})[ ]*\n+[ ]*#[ ]*([-A-Z\d]{5,})[ ]*\n/", $rColumnText, $m)) {
            $h->general()->confirmation($m[2], preg_replace("/^(.{2,})\s+is$/i", '$1', $m[1]));
        }

        $dateCheckIn = $dateCheckOut = null;

        if (preg_match("/\n[ ]*{$this->opt($this->t('dates'))}[ ]*[:]+[ ]*(.*?\d.*?)[ ]+-[ ]+(.*?\d.*?)[ ]*(?:\(|\n)/", $rColumnText, $m)) {
            $dateCheckIn = strtotime($m[1]);
            $dateCheckOut = strtotime($m[2]);
        }

        if ($timeCheckIn && $dateCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        } else {
            $h->booked()->checkIn($dateCheckIn);
        }

        if ($timeCheckOut && $dateCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        } else {
            $h->booked()->checkOut($dateCheckOut);
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['dates'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['dates'])}]")->length > 0
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
