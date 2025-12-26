<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationCancelled extends \TAccountChecker
{
    public $mailFiles = "triprewards/it-59495428.eml";

    private $lang = '';
    private $reFrom = ['wyndhamhotels.com'];
    private $reProvider = ['Wyndham Hotel'];
    private $reBody = [
        'en' => [
            'Your Reservation Has Been Canceled',
            'Your Reservation Has Been Cancelled',
        ],
    ];
    private $reSubject = [
        'Your Reservation Has Been Canceled',
    ];
    private static $dictionary = [
        'en' => [
            'Child' => ['Child', 'Kids'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();
        $h->hotel()->name($this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Number:'))}]/ancestor::tr[1]/preceding-sibling::tr[2]"));

        $addr = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Number:'))}]/ancestor::tr[1]/preceding-sibling::tr[1]//a[1]");
        $h->hotel()->address($addr);

        $h->booked()->checkIn2($this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-In'))}]/ancestor::td[1]", null, false, "/{$this->t('Check-In')}\s+(.+)/"));
        $h->booked()->checkOut2($this->http->FindSingleNode("//text()[{$this->contains($this->t('Checkout'))}]/ancestor::td[1]", null, false, "/{$this->t('Checkout')}\s+(.+)/"));

        $num = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Number of Guests'))}]/ancestor::td[1]");
        $h->booked()->guests($this->http->FindPreg("/(\d+) {$this->t('Adult')}/i", false, $num));
        $h->booked()->kids($this->http->FindPreg("/(\d+) {$this->opt('Child')}/i", false, $num), false, true);

        $h->general()->cancellationNumber(
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Number:'))}]/following-sibling::a/b"),
            $this->t('Cancellation Number')
        )->noConfirmation();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your Reservation Has Been Canceled'))}]")->length == 1) {
            $h->general()->status($this->t('Canceled'));
            $h->general()->cancelled();
        }
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            $this->logger->error($this->http->XPath->query("//text()[{$this->contains($value)}]")->length);

            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $this->t($field);

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
