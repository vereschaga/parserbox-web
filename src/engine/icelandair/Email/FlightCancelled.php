<?php

namespace AwardWallet\Engine\icelandair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FlightCancelled extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-66969934.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'booking reference' => ['booking reference'],
            'Date'              => ['Date'],
            'statusVariants'    => ['cancelled', 'canceled'],
            'cancelledPhrases'  => ['has been cancelled', 'has been canceled'],
        ],
    ];

    private $detectors = [
        'en' => ['has been cancelled', 'has been canceled'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.icelandair.is') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Icelandair flight is cancelled') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".icelandair.is/") or contains(@href,"email.icelandair.is")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for your patience and understanding, The Icelandair team") or contains(normalize-space(),"Icelandair. All rights reserved")]')->length === 0
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
        $email->setType('FlightCancelled' . ucfirst($this->lang));

        $this->parseFlight($email);

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

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('booking reference'))}]/ancestor::tr[1]");

        if (preg_match("/({$this->opt($this->t('booking reference'))})\s+([A-Z\d]{5,})\b/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('has been'))}]", null, true, "/\b({$this->opt($this->t('statusVariants'))})\b/");
        $f->general()->status($status);

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $f->general()->cancelled();
        }

        $segments = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Flight'))}] and *[2][{$this->eq($this->t('Date'))}] and *[3][{$this->eq($this->t('From'))}] and *[4][{$this->eq($this->t('To'))}] ]/following-sibling::tr[ *[normalize-space()][2] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode('*[1]', $segment);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $date = $this->http->FindSingleNode('*[2]', $segment);

            if (preg_match('/\b\d{1,2}[:：]\d{2}\b/', $date)) {
                // 03 Sep 2020 15:45
                $s->departure()
                    ->date2($date);
                $s->arrival()->noDate();
            } else {
                // 03 Sep 2020
                $s->departure()
                    ->day2($date)
                    ->noDate();
                $s->arrival()->noDate();
            }

            $from = $this->http->FindSingleNode('*[3]', $segment);

            if (preg_match('/^[A-Z]{3}$/', $from, $m)) {
                $s->departure()->code($from);
            } else {
                $s->departure()
                    ->name($from)
                    ->noCode();
            }

            $to = $this->http->FindSingleNode('*[4]', $segment);

            if (preg_match('/^[A-Z]{3}$/', $to, $m)) {
                $s->arrival()->code($to);
            } else {
                $s->arrival()
                    ->name($to)
                    ->noCode();
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

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['booking reference']) || empty($phrases['Date'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['booking reference'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Date'])}]")->length > 0
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
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
