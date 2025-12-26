<?php

namespace AwardWallet\Engine\thetrainline\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingCancelled extends \TAccountChecker
{
    public $mailFiles = "thetrainline/it-91880044.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Booking'          => ['Booking'],
            'Journey'          => ['Journey'],
            'cancelledPhrases' => [
                "We've cancelled your booking",
                "We've canceled your booking",
                'We have cancelled these journeys and are now processing a refund.',
                'We have canceled these journeys and are now processing a refund.',
            ],
            'statusPhrases'  => 'your booking',
            'statusVariants' => ['cancelled', 'canceled'],
        ],
    ];

    private $subjects = [
        'en' => ["We've cancelled your booking", "We've canceled your booking"],
    ];

    private $detectors = [
        'en' => ["We've cancelled your booking", "We've canceled your booking"],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@info.thetrainline.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
            && $this->http->XPath->query('//img[contains(@src,"info.thetrainline.com/wf/open")]')->length === 0
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
        $email->setType('BookingCancelled' . ucfirst($this->lang));

        $this->parseTrain($email);

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

    private function parseTrain(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $train = $email->add()->train();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $train->general()->cancelled();
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/.+\s({$this->opt($this->t('statusVariants'))})\s+{$this->opt($this->t('statusPhrases'))}/");

        if ($status) {
            $train->general()->status($status);
        }

        $text = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('Journey'))}]/ancestor::tr[ descendant::text()[{$this->starts($this->t('Booking'))}] ][1]"));

        if (preg_match("/^[ ]*{$this->opt($this->t('Dear'))}\s+({$patterns['travellerName']})[ ]*(?:[,:;!?]|$)/mu", $text, $m)) {
            $train->general()->traveller($m[1]);
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('Booking'))})\s+([-A-Z\d]{5,})[ ]*$/m", $text, $m)) {
            $train->general()->confirmation($m[2], $m[1]);
        }

        if (!preg_match_all("/^[ ]*{$this->opt($this->t('Journey'))}[ ]+\d{1,3}[ ]*:[ ]*(.{14,}?)[ ]*$/m", $text, $segmentMatches)) {
            return;
        }

        foreach ($segmentMatches[1] as $sText) {
            $s = $train->addSegment();

            if (preg_match("/^(?<nameDep>.{3,}?)\s+{$this->opt($this->t('to'))}\s+(?<nameArr>.{3,}?)\s+{$this->opt($this->t('travelling on'))}\s+(?<date>.{6,}?)\s+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']})$/", $sText, $m)) {
                // LONDON TERMINALS to SOUTHAMPTON CENTRAL travelling on 06 August 2021 at 00:00
                $s->departure()->name($m['nameDep'])->date2($m['date'] . ' ' . $m['time']);
                $s->arrival()->name($m['nameArr'])->noDate();
                $s->extra()->noNumber();
            }
        }
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
            if (!is_string($lang) || empty($phrases['Booking']) || empty($phrases['Journey'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Booking'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Journey'])}]")->length > 0
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
