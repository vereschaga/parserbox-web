<?php

namespace AwardWallet\Engine\sonesta\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationCancellation extends \TAccountChecker
{
    public $mailFiles = "";

    public $detectSubjects = [
        // en
        // Reservation Cancellation (31893SE067970X) (CANCELLATION)
        'Reservation Cancellation',
    ];

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Confirmation Number:' => 'Confirmation Number:',
            'has been cancelled'   => 'has been cancelled',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sonesta\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".sonesta.com/") or contains(@href,".sonesta.com%2f") or contains(@href,"www.sonesta.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"SONESTA TRAVEL PASS") or contains(.,"@sonesta.com") or contains(.,"www.sonesta.com")]')->length === 0
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

        $this->parseHotel($email);

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

    private function parseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number:'))}]",
                null, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\d]{5,})\s*$/"))
            ->cancellationNumber($this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Number:'))}]",
                null, true, "/{$this->opt($this->t('Cancellation Number:'))}\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]",
                null, true, "/^\s*{$this->opt($this->t('Dear '))}\s*([[:alpha:] \-]{3,}),\s*$/"), true)
            ->status('Cancelled')
            ->cancelled()
        ;

        // Hotel
        $hXpath = "//a[contains(., '.sonesta.com')][preceding::text()[normalize-space()][4]/ancestor::strong]";
        $h->hotel()
            ->name($this->http->FindSingleNode($hXpath . '/preceding::text()[normalize-space()][4]'))
            ->address($this->http->FindSingleNode($hXpath . '/preceding::text()[normalize-space()][3]') . ', '
                . $this->http->FindSingleNode($hXpath . '/preceding::text()[normalize-space()][2]'))
            ->phone($this->http->FindSingleNode($hXpath . '/preceding::text()[normalize-space()][1]'))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival Date:'))}]",
                null, true, "/{$this->opt($this->t('Arrival Date:'))}\s*(.+)/")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure Date:'))}]",
                null, true, "/{$this->opt($this->t('Departure Date:'))}\s*(.+)/")))
        ;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Confirmation Number:']) || empty($phrases['has been cancelled'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Confirmation Number:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['has been cancelled'])}]")->length > 0
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

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Friday, September 8, 2023
            "/^\s*[[:alpha:]]+,\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})\s*$/i",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date = '.print_r( $date,true));

        return strtotime($date);
    }
}
