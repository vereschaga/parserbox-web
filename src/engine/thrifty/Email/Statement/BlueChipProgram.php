<?php

namespace AwardWallet\Engine\thrifty\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class BlueChipProgram extends \TAccountChecker
{
    public $mailFiles = "thrifty/statements/it-65847547.eml, thrifty/statements/it-65976349.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'number' => ['Blue Chip number is', 'Blue Chip number'],
        ],
    ];

    private $subjects = [
        'en' => [
            'Welcome to the Blue Chip Express Rental Program',
            'Blue Chip Express Rental Program Update',
            'Blue Chip Rewards Program Update',
        ],
    ];

    private $detectors = [
        'en' => [
            'Thank you for visiting the Thrifty Car Rental interactive website and updating your Blue Chip Member profile',
            'Thank you for choosing Thrifty Car Rental, and for your membership in the Blue Chip program',
            'Welcome to the Thrifty Car Rental Blue Chip Express Rental Program',
            'Your Blue Chip number has been activated',
            'Thank you for choosing Thrifty Car Rental and becoming a Blue Chip member',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thrifty.com') !== false || stripos($from, '@emails.thrifty.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
        if ($this->http->XPath->query('//a[contains(@href,".thrifty.com/") or contains(@href,"click.emails.thrifty.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"All rights reserved. Thrifty") or contains(.,"www.thrifty.com") or contains(.,"@thrifty.com") or contains(.,"@emails.thrifty.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->lang = 'en';

        $st = $email->add()->statement();

        $number = null;
        $numbers = array_values(array_unique(array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('number'))}]", null, "/{$this->opt($this->t('number'))}[:\s]+([A-Z\d]{5,})(?:\s*[.,;!?]|$)/"))));

        if (count($numbers) === 1) {
            $number = $numbers[0];
        }

        $st->setNumber($number)
            ->setLogin($number);

        if ($number) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
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
