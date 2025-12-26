<?php

namespace AwardWallet\Engine\worldpoints\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "worldpoints/it-13568311.eml";

    public $reFrom = "@heathrow.com";
    public $reBody = [
        'en' => ['Thank you for booking with Heathrow', 'Booking Confirmation'],
    ];
    public $reSubject = [
        'Heathrow Airport Booking Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
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
        if ($this->http->XPath->query("//img[@alt='Heathrow' or contains(@src,'heathrow.com/')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email): void
    {
        $roots = $this->http->XPath->query("//table[normalize-space()='' and descendant::img[contains(@alt,'Car Park')]]/following-sibling::table[normalize-space()][1]");
        $root = $roots->length === 1 ? $roots->item(0) : null;

        $p = $email->add()->parking();
        $p->general()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('This email confirms the booking you made on'))}]",
                null, false, "#{$this->opt($this->t('This email confirms the booking you made on'))}\s+(.+?)\.#")))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference Number:'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^\s*([A-Z\d]{5,})\s*$#"));
        $p->place()
            ->address($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Airport/Terminal:'))}]/following::text()[normalize-space()][1]", $root))
            ->location($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Car park:'))}]/following::text()[normalize-space()][1]", $root));

        $p->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Entry:'))}]/following::text()[normalize-space()][1]", $root)))
            ->end($this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Exit:'))}]/following::text()[normalize-space()][1]", $root)))
            ->plate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Car registration:'))}]/following::text()[normalize-space()][1]", $root));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Amount paid'))}]/following::text()[normalize-space()][1]", $root));

        if ($tot['Total'] !== null) {
            $p->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //05:00, Sat 18 August 2018
            '#^\s*(\d+:\d+),\s+\w+\s+(\d+)\s+(\w+)\s+(\d{4})\s*$#',
            //19 May 2018 at 16:50
            '#^\s*(\d+)\s+(\w+)\s+(\d{4})\s+at\s+(\d+:\d+)\s*$#',
        ];
        $out = [
            '$2 $3 $4, $1',
            '$1 $2 $3, $4',
        ];
        $outWeek = [
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
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

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
