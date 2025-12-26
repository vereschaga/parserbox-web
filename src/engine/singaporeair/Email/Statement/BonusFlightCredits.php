<?php

namespace AwardWallet\Engine\singaporeair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BonusFlightCredits extends \TAccountChecker
{
    public $mailFiles = "singaporeair/statements/it-73734069.eml, singaporeair/statements/it-73840604.eml, singaporeair/statements/it-78433010.eml, singaporeair/statements/it-78910877.eml, singaporeair/statements/it-87228637.eml, singaporeair/statements/it-884184885.eml";
    public $subjects = [
        '/^Bonus Flight Credits and COVID-19 Travel Info$/',
        '/^Happy Birthday(?:!| from all of us at KrisFlyer)$/',
    ];

    public $lang = '';

    public $detectLang = [
        'id' => ['Khusus anggota', 'dari sekarang', 'Selama musim', 'Penawaran'],
        'en' => ['Dear'],
    ];

    public static $dictionary = [
        "en" => [
            'TRAVEL INFORMATION'                     => ['TRAVEL INFORMATION', 'BONUS KRISFLYER MILES', 'HOW TO SHOP ON KRISFLYER SPREE', 'NEW KRISFLYER MICROSITE'],
            'Bonus Flight Credits and Travel Waiver' => ['Bonus Flight Credits and Travel Waiver', 'bonus KrisFlyer miles', 'bonus miles', 'redeem KrisFlyer miles'],
        ],

        "id" => [
            'TRAVEL INFORMATION'                     => ['Cara berbelanja di', 'Kejutan ulang tahun KrisFlyer', 'mingguan masing-masing akan mendapatkan', 'Penawaran berakhir pada', 'Syarat dan ketentuan berlaku', 'Selamat berbelanja'],
            'Bonus Flight Credits and Travel Waiver' => ['bonus miles', 'bonus KrisFlyer miles'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.singaporeair.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->assignLang() == true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Singapore Airlines')]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('TRAVEL INFORMATION'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Bonus Flight Credits and Travel Waiver'))}]")->count() > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.singaporeair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership No:'))}]", null, true, "/{$this->opt($this->t('Membership No:'))}\s*(\d+)$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }
        $name = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Dear '))}])[1]", null, true, "/^{$this->opt($this->t('Dear'))}\s+(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim(preg_replace("/^\s*(Mr|Mrs|Ms|Dr|Miss)\s+/", '', $name), ','));
        }
        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tier Status:'))}]", null, true, "/{$this->opt($this->t('Tier Status:'))}\s*(\D+)$/");

        if (!empty($status)) {
            $st->addProperty('CurrentTier', $status);
        }

        // it's not a balance
//        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total KrisFlyer miles earned')]", null, true, "/{$this->opt($this->t('Total KrisFlyer miles earned'))}\s*\–\s*([\d\,]+)\s*miles/");
//        if (!empty($balance)) {
//            $st->setBalance(str_replace(',', '', $balance));
//        } else
        $st->setNoBalance(true);

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
        return 0;
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
