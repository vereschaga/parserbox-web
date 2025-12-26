<?php

namespace AwardWallet\Engine\gscope\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Transfer extends \TAccountChecker
{
    public $mailFiles = "gscope/it-193276937.eml"; // +1 bcdtravel(html)[en]

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Booking reference number:', 'Booking reference number :'],
            'meetYourDriver' => ['MEET YOUR DRIVER', 'Meet your driver'],
            'statusPhrases'  => 'Your booking has been',
            'statusVariants' => 'amended',
        ],
    ];

    private $subjects = [
        'en' => ['/(?:Amendment Ref|Booking Ref)\s+[-A-Z\d]{5,}\s+Pickup date/i'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@groundscope.co.uk') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $expressions) {
            foreach ((array) $expressions as $re) {
                if (is_string($re) && preg_match($re, $headers['subject']) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//*[contains(normalize-space(),"When contacting GroundScope") or contains(normalize-space(),"GroundScope Customer Support Team") or contains(.,"@groundscope.co.uk")]')->length === 0
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
        $email->setType('Transfer' . ucfirst($this->lang));

        $t = $email->add()->transfer();
        $s = $t->addSegment();

        if (preg_match("/{$this->opt($this->t('Passenger'))}\s*\(\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*[)+]/u", $parser->getSubject(), $m)) {
            $t->general()->traveller($m[1]);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/");

        if ($status) {
            $t->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $t->general()->confirmation($confirmation, $confirmationTitle);
        }

        $extra = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('confNumber'))}]/following::tr[ count(*)=2 and *[1][normalize-space()='']/descendant::img and *[2][normalize-space()] ][1]/*[2]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]"));

        if (preg_match("/^.{2,},\s*(.+)\n/", $extra, $m)
            && preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $m[1], $matches)
        ) {
            // £132.17
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $t->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (preg_match("/est\.\s*(\d[,.\'\d ]*?)\s*mi/i", $extra, $m)) {
            $s->extra()->miles($m[1]);
        }

        if (preg_match("/\b(\d{1,3}\s*min) ride/i", $extra, $m)) {
            $s->extra()->duration($m[1]);
        }

        $xpathDate = "//*[not(.//tr) and not(.//div) and {$this->eq($this->t('meetYourDriver'))}]/following::tr[not(.//tr) and normalize-space()][1]";

        $dateTime = strtotime($this->http->FindSingleNode($xpathDate, null, true, "/^(.*?\d.*?)(?:\s*\(|$)/"));
        $s->departure()->date($dateTime);

        if ($dateTime) {
            $s->arrival()->noDate();
        }

        $xpathLocation = $xpathDate . "/following::tr[ count(*)=2 and *[1][normalize-space()='']/descendant::img and *[2][normalize-space()] ]";

        $locationDepTexts = $this->http->FindNodes($xpathLocation . "[1]/*[2]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]");

        if (count($locationDepTexts) == 0) {
            $xpathLocation = $xpathDate . "/following::img";
            $locationDepTexts = $this->http->FindNodes($xpathLocation . "[1]/ancestor::tr[normalize-space()][1]/descendant::tr[normalize-space()]");
        }

        if (count($locationDepTexts) === 2) {
            $s->departure()->name($locationDepTexts[0])->address($locationDepTexts[1]);
        }

        $locationArrTexts = $this->http->FindNodes($xpathLocation . "[2]/*[2]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]");

        if (count($locationArrTexts) == 0) {
            $locationArrTexts = $this->http->FindNodes($xpathLocation . "[2]/ancestor::tr[normalize-space()][1]/descendant::tr[normalize-space()]");
        }

        if (count($locationArrTexts) === 2) {
            $s->arrival()->name($locationArrTexts[0])->address($locationArrTexts[1]);
        }

        $cancellation = implode(' ', $this->http->FindNodes("//*[ tr[normalize-space()][1][{$this->eq($this->t('Cancellation policy'))}] ]/tr[normalize-space()][position()>1]"));

        if ($cancellation) {
            $t->general()->cancellation($cancellation);
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['meetYourDriver'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['meetYourDriver'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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
}
