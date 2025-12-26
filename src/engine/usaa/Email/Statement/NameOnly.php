<?php

namespace AwardWallet\Engine\usaa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NameOnly extends \TAccountChecker
{
    public $mailFiles = "usaa/statements/it-100183339.eml, usaa/statements/it-66060566.eml, usaa/statements/it-66517615.eml, usaa/statements/it-66573485.eml, usaa/statements/it-66867691.eml, usaa/statements/it-66950434.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'bodyText' => [
                'Enhancing Your Account Security',
                'Claim Message',
                'Certificate of Insurance for Renters Policy',
                'Auto Adjustment and ID Card',
                'USAA SECURITY ZONE',
                'Verify Email So You Can Receive Funds',
            ],
            'Dear'                        => ['Dear', 'USAA policyholder:'],
            'Here is your one-time code:' => ['Here is your one-time code:', 'verification code is'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'USAA')]")->count() > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Dear'))}]")->count() > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Named Insured:'))}]")->count() > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('USAA SECURITY ZONE'))}]")->count() > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('bodyText'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mailcenter\.usaa\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $st = $email->add()->statement();

        $name = $number = null;

        $headerHtml = $this->http->FindHTMLByXpath("//tr[not(.//tr)]/*[ descendant::text()[normalize-space()][1][{$this->eq($this->t('USAA SECURITY ZONE'))}] and descendant::text()[normalize-space()][last()][{$this->starts($this->t('USAA # ending in'))}] ]");
        $headerText = $this->htmlToText($headerHtml);

        /*
            USAA SECURITY ZONE
            Josh
            Rothstein
            USAA # ending in: 0537
        */

        if (preg_match("/{$this->opt($this->t('USAA SECURITY ZONE'))}[ ]*\n+(?<name>(?:[ ]*{$patterns['travellerName']}[ ]*\n+){1,2})[ ]*{$this->opt($this->t('USAA # ending in'))}/u", $headerText, $m)) {
            $name = preg_replace('/\s+/', ' ', rtrim($m['name']));
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s+({$patterns['travellerName']})(?:[ ]*[,:;!?]|$)/u");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]/ancestor::td[1]/following::td[1]", null, true, "/^{$patterns['travellerName']}$/u");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('USAA number:'))}]/preceding::text()[{$this->starts($this->t('Dear'))}][1]/ancestor::td[1]/following::td[1]", null, true, "/^{$patterns['travellerName']}$/u");
        }

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('USAA number:'))}]/ancestor::td[1]/following::td[1]", null, true, '/^\d{5,}$/');

        if ($number === null && preg_match("/{$this->opt($this->t('USAA # ending in'))}[: ]+(?<number>\d+)$/", $headerText, $m)) {
            $number = $m['number'];
        }

        if ($number !== null && strlen($number) < 5) {
            $st->setNumber($number)->masked('left');
        } elseif ($number !== null && strlen($number) > 5) {
            $st->setNumber($number);
        }

        if ($name || $number) {
            $st->setNoBalance(true);
        }

        $code = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Here is your one-time code:'))}]/ancestor::p[1]", null, true, "/{$this->opt($this->t('Here is your one-time code:'))}\s*(\d+)/");

        if (empty($code)) {
            $code = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You can use this security code to access'))}]/preceding::text()[string-length()>1][1]", null, true, "/^(\d+)$/");
        }

        if (!empty($code)) {
            $b = $email->add()->oneTimeCode();
            $b->setCode($code);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
