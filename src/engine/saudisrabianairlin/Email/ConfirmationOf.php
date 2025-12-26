<?php

namespace AwardWallet\Engine\saudisrabianairlin\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationOf extends \TAccountChecker
{
    public $mailFiles = "saudisrabianairlin/it-258785173.eml, saudisrabianairlin/it-267834283.eml";
    public $subjects = [
        'Confirmation of order:',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Departure'],
        'ar' => ['رحلة المغادرة'],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "ar" => [
            'Saudia Airlines'          => 'بواسطة الخطوط السعودية',
            'Departure Flight Details' => 'تفاصيل رحلة المغادرة',
            'Flight:'                  => 'الرحلة:',
            'Duration:'                => 'المدة الزمنية:',

            'Booking Reference'     => 'مرجع الحجز',
            'Booking Date'          => 'تاريخ الحجز',
            'Passenger information' => 'معلومات المسافر',
            'Total amount'          => 'المبلغ الإجمالي',
            'Saudi Alfursan:'       => '',
            'operated by'           => 'مشغلة',
            'Departure'             => 'المغادرة',
            'Terminal'              => 'الصالة',
            'Arrival'               => 'الوصول',
            'Date:'                 => 'التاريخ:',
            'Cabin:'                => 'الدرجة:',
            'Fare class:'           => 'درجة السعر:',
            'ticket number'         => 'وثيقة التذكرة الإلكترونية:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@saudia.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Saudia Airlines'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Departure Flight Details'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Duration:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]saudia.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference'))}]/following::text()[normalize-space()][1]", null, true, "/[A-Z\d]{6}[\-\s]+(\d+)/"), 'Booking Reference')
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Date'))}]/following::text()[normalize-space()][1]")));

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger information'))}]/following::img[contains(@src, 'circle')]/following::text()[normalize-space()][1]/ancestor::tr[1][contains(normalize-space(), '(')]", null, "/^(.+)\(/");

        if (count($travellers) > 0) {
            $f->general()
                ->travellers(preg_replace("/^(?:Mrs\.\s*|Ms\.\s*|Mr\.\s*)/", "", $travellers), true);
        }

        $priceText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total amount'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total amount'))}\s*(.+)/");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)$/", $priceText, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $accounts = $this->http->FindNodes("//text()[normalize-space()='Saudi Alfursan:']/following::text()[normalize-space()][1]", null, "/^([A-Z\d\-]+)$/");

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, false);
        }

        $tickets = $this->http->FindNodes("//text()[{$this->contains($this->t('ticket number'))}]/following::text()[normalize-space()][1]", null, "/^([\d\-]+)$/");

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Flight:'))}]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./preceding::text()[{$this->eq($this->t('Date:'))}][1]/following::text()[normalize-space()][1]", $root);

            $s->airline()
                ->name($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Flight:'))}\s*([A-Z\d]{2})/"))
                ->number($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Flight:'))}\s*[A-Z\d]{2}(\d{2,4})/"));

            $operator = $this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('operated by'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $depText = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Departure'))}][1]/ancestor::tr[1]", $root);

            if (preg_match("/^{$this->opt($this->t('Departure'))}\s*(?<depTime>[\d\:]+).*\((?<depCode>[A-Z]{3})\)\s*(?:{$this->opt($this->t('Terminal'))}\s*(?<depTerminal>.+))?$/ui", $depText, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($date . ', ' . $m['depTime']));

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $arrText = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Arrival'))}][1]/ancestor::tr[1]", $root);

            if (preg_match("/^{$this->opt($this->t('Arrival'))}\s*(?<arrTime>[\d\:]+).*\((?<arrCode>[A-Z]{3})\)\s*(?:{$this->opt($this->t('Terminal'))}\s*(?<arrTerminal>.+))?$/ui", $arrText, $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($date . ', ' . $m['arrTime']));

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $cabin = $this->http->FindSingleNode("./preceding::text()[{$this->eq($this->t('Date:'))}][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Cabin:'))}\s*(.+)/us");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $bookingCode = $this->http->FindSingleNode("./ancestor::table[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Fare class:'))}\s*([A-Z])/su");

            if (!empty($bookingCode)) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    protected function assignLang()
    {
        foreach ($this->detectLang as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//text()[contains(normalize-space(), '{$phrase}')]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            //الجمعة 02 ديسمبر 2022, 17
            "#^\D+\s(\d+)\s*(\w+)\s*(\d{4})\s*\,?\s*([\d\:]*)$#u",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#^\d+\s*(\w+)\s*\d{4}\s*\,?\s*[\d\:]*$#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
