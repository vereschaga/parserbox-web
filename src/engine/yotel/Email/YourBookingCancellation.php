<?php

namespace AwardWallet\Engine\yotel\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourBookingCancellation extends \TAccountChecker
{
    public $mailFiles = "yotel/it-688120490.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [],
    ];

    private $subjects = [
        // en
        'Your booking cancellation (CANCELLATION)',
    ];
    private $detectBody = [
        // en
        'All sorted, your booking has been cancelled',
    ];

    private $detectors = [
        'en' => ['ABOUT YOUR STAY', 'About Your Stay', 'About your stay'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@yotel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".yotel.com/") or contains(@href,"email.yotel.com") or contains(@href,"www.yotel.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.yotel.com") or contains(.,"@yotel.com")]')->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
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

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation number'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation number'))}]", null, true, "/({$this->opt($this->t('Reservation number'))})$/u");
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('All sorted, your booking has been cancelled'))}]/ancestor::*[{$this->starts($this->t('All sorted, your booking has been cancelled'))}][last()]",
            null, true, "/{$this->opt($this->t('All sorted, your booking has been cancelled'))}\s+(\D+)$/");
        $h->general()
            ->traveller($traveller);

        $h->general()
            ->status('Cancelled')
            ->cancelled()
        ;

        // Booked
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('successfully cancelled your booking from'))}]/following::text()[normalize-space()][1]/ancestor::*[{$this->contains($this->t('successfully cancelled your booking from'))}][1]");
        $this->logger->debug('$text = ' . print_r($text, true));

        if (preg_match("/{$this->opt($this->t('successfully cancelled your booking from'))}\s*(.+) to (.+)/", $text, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime($m[2]))
            ;
        }
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
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
