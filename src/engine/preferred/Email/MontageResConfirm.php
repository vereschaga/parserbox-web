<?php

namespace AwardWallet\Engine\preferred\Email;

use AwardWallet\Schema\Parser\Email\Email;

class MontageResConfirm extends \TAccountChecker
{
    public $mailFiles = "preferred/it-16605977.eml";

    public $reFrom = ["@montagereservations.com", "@stay.montagehotels.com"];
    public $reBody = [
        'en' => ['welcoming you to Montage', 'Arrival Date'],
    ];
    public $reSubject = [
        '#Montage .+? \| Reservation Confirmation#i',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Average Nightly Rate:' => ['Average Nightly Rate:', 'Average Nightly Rate:*'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'Montage') or contains(@src,'montageinternational.')] | //a[contains(@href,'montageinternational.')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (preg_match($reSubject, $headers["subject"])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function nextField($field, $root = null)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
            $root);
    }

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->nextField($this->t('Confirmation Number:')))
            ->traveller($this->nextField($this->t('Guest Name:')));

        $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CONTACT'))}]/ancestor::table[1][not({$this->contains($this->t('DIRECTIONS'))})]/descendant::td[{$this->contains('|')}][1]");

        if (preg_match("#(.+)\s\|\s*{$this->opt($this->t('CONTACT'))}.+?{$this->opt($this->t('GIFT CARDS'))}\s*(.+?)\s*\|\s*(.+)#",
            $node, $m)) {
            $h->hotel()
                ->name($m[1])
                ->address($m[2])
                ->phone($m[3]);
        }

        $h->booked()
            ->checkIn(strtotime($this->nextField($this->t('Arrival Date:'))))
            ->checkOut(strtotime($this->nextField($this->t('Departure Date:'))))
            ->cancellation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Policy'))}]/following::text()[string-length(normalize-space(.))>2][1]"));

        $r = $h->addRoom();

        $r->setType($this->nextField($this->t('Accommodations:')));
        $r->setRateType($this->nextField($this->t('Rate Name:')));
        $r->setRate($this->nextField($this->t('Average Nightly Rate:')));

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check in'))}]");

        if (preg_match("#{$this->opt($this->t('Check in'))}:\s+(\d+:\d+(?:\s*[ap]m)?)\s+\|\s+{$this->opt($this->t('Check out'))}:\s+(\d+:\d+(?:\s*[ap]m)?)#i",
            $node, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1], $h->getCheckInDate()))
                ->checkOut(strtotime($m[2], $h->getCheckOutDate()));
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
