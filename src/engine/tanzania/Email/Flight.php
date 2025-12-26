<?php

namespace AwardWallet\Engine\tanzania\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "tanzania/it-728136427.eml, tanzania/it-785133090.eml, tanzania/it-792330324.eml";
    public $subjects = [
        'Online Ticket Details',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Thank you for'],
        'fr' => ["Merci d'avoir"],
    ];

    public static $dictionary = [
        "en" => [
            'detectPhrase' => ['Thank you for choosing Nouvelair', 'Thank you for choosing Air Tanzania'],
            'PASSENGERS'   => ['PASSENGER(S)', 'PASSENGERS'],
        ],
        "fr" => [
            'RESERVATION NUMBER (PNR)' => 'Numéro de dossier(PNR)',
            'detectPhrase'             => "Merci d'avoir choisi Nouvelair",
            'PASSENGERS'               => 'Passagers',
            'FLIGHT'                   => 'Vol',
            'FROM'                     => 'De',
            'FLIGHT NUMBER'            => 'Vol',
        ],
    ];

    public $emails = ['/[@.]airtanzania\.co\.tz$/', '/[@.]nouvelair\.com$/'];

    public static $providers = [
        "nouvelair" => 'Nouvelair',
        "tanzania"  => 'Air Tanzania',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        return $this->http->XPath->query("//text()[{$this->contains($this->t('detectPhrase'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('RESERVATION NUMBER (PNR)'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('FLIGHT'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('PASSENGERS'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->emails as $email) {
            if (preg_match($email, $from) > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (!empty($providerCode = $this->getProviderCode())) {
            $email->setProviderCode($providerCode);
        }

        $this->parseFlight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('RESERVATION NUMBER (PNR)'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('RESERVATION NUMBER (PNR)'))}\:\s*([A-Z\d]{6})$/"))
            ->travellers(preg_replace("/^(?:Mrs|Mr|Ms|Miss|M)\./", "", $this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGERS'))}]/ancestor::table[1]/following::table[1]/descendant::tr/descendant::h2")));

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('FROM'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('FROM'))})]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[{$this->eq($this->t('FLIGHT'))}][1]/following::text()[normalize-space()][1]", $root, true, "/^(\d+\s*\w+\s*\d{4})/"));

            $depArrInfo = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depCode>[A-Z]{3})\n(?<depTime>[\d\:]+)\n(?<depName>.+)\n(?<arrCode>[A-Z]{3})\n(?<arrTime>[\d\:]+)\n(?<arrName>.+)$/", $depArrInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date(strtotime($date . ', ' . $m['depTime']));

                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date(strtotime($date . ', ' . $m['arrTime']));
            }

            $flightInfo = implode("\n", $this->http->FindNodes("./following::table[1]/descendant::tr[normalize-space()][not({$this->contains($this->t('FLIGHT NUMBER'))})]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\n(?<bookingCode>[A-Z]{1,2})$/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->extra()
                    ->bookingCode($m['bookingCode']);
            }
        }
    }

    public function getProviderCode()
    {
        foreach (self::$providers as $code => $provider) {
            if ($this->http->XPath->query("//*[{$this->contains($provider)}]")->length > 0) {
                return $code;
            }
        }

        return null;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $dBody) {
            if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
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

    private function normalizeDate($date)
    {
        /*$in = [
            // Tuesday 15-Feb-2022 15:20:06
            "/^\s*(?:\w+\s+)?(\d+)\s*-?\s*([[:alpha:]]+)\s*-?\s*(\d{4})\s+(\d{1,2}:\d{2})(?::\d{2})?\s*$/",
            // 18Jun22, 07:20
            "/^\s*(?:\w+\s+)?(\d+)\s*-?\s*([[:alpha:]]+)\s*-?\s*(\d{2})[\s,]+(\d{1,2}:\d{2})\s*$/",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 20$3, $4",
        ];
        $date = preg_replace($in, $out, $date);*/

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return $date;
    }
}
