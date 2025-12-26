<?php

namespace AwardWallet\Engine\redbus\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TicketCancelledPlain extends \TAccountChecker
{
    public $mailFiles = "redbus/it-208589387.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'dateDep'        => ['Date of journey'],
            'timeDep'        => ['Boarding time'],
            'statusPhrases'  => ['Your ticket has been'],
            'statusVariants' => ['cancelled', 'canceled'],
            'ticketNumber'   => ['Ticket number', 'Ticket number (TIN)'],
        ],
    ];

    private $subjects = [
        'en' => ['ticket cancelled', 'ticket canceled'],
    ];

    private $detectors = [
        'en' => ['Your ticket has been cancelled', 'Your ticket has been canceled'],
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
            && $this->http->XPath->query('//*[contains(normalize-space(),"Team redBus")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        // checking cancelled format!
        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('TicketCancelledPlain' . ucfirst($this->lang));

        $bus = $email->add()->bus();
        $bus->general()->cancelled();

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,.\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $bus->general()->traveller($traveller);
        }

        $status = null;
        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s+successfully\b|\s+and\b|\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $bus->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking trip id for this ticket is'))}]", null, true, "/^{$this->opt($this->t('Your booking trip id for this ticket is'))}\s+([-A-Z\d]{5,}?)[,.;!\s]*$/");
        $bus->general()->confirmation($confirmation, 'trip id');

        $s = $bus->addSegment();

        $text = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('dateDep'))}]/ancestor::*[{$this->contains($this->t('statusPhrases'))}][1]"));
        $this->logger->debug($text);

        if (strlen($text) > 8000) {
            $this->logger->debug('Format is very long!');

            return $email;
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('ticketNumber'))}\s*[:]+\s*([-A-Z\d ]{5,}?)[ ]*$/im", $text, $m)) {
            $bus->addTicketNumber($m[1], false);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('From'))}\s*[:]+\s*(?<from>.{3,}?)[ ]*$\s+^[ ]*{$this->opt($this->t('To'))}\s*[:]+\s*(?<to>.{3,}?)[ ]*$/im", $text, $m)) {
            $s->departure()->address($m['from']);
            $s->arrival()->address($m['to']);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('dateDep'))}\s*[:]+\s*(.*\d.*?)[ ]*$/im", $text, $dateMatches)
            && preg_match("/^[ ]*{$this->opt($this->t('timeDep'))}\s*[:]+\s*(\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)[ ]*$/imu", $text, $timeMatches)
        ) {
            $dateDep = strtotime($this->normalizeDate($dateMatches[1]));
            $s->departure()->date(strtotime($timeMatches[1], $dateDep));
        }

        if (!empty($s->getDepDate())) {
            $s->arrival()->noDate();
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Bus type'))}\s*[:]+\s*(.{2,}?)[ ]*$/im", $text, $m)) {
            $s->extra()->type($m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Seat number'))}\s*[:]+\s*([-A-Z\d, ]+?)[ ]*$/im", $text, $m)) {
            $s->extra()->seats(preg_split('/\s*[,]+\s*/', $m[1]));
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
            if (!is_string($lang) || empty($phrases['dateDep']) || empty($phrases['timeDep'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['dateDep'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['timeDep'])}]")->length > 0
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
