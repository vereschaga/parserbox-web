<?php

namespace AwardWallet\Engine\mozio\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TransferReservation extends \TAccountChecker
{
    public $mailFiles = "mozio/it-649920655.eml, mozio/it-770159210.eml, mozio/it-783686353.eml";
    public $subjects = [
        'Your Mozio Reservation with',
        'Your Ground Transportation Reservation with',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Summary:'                 => 'Summary:',
            'Name:'                    => 'Name:',
            'From:'                    => 'From:',
            'To:'                      => 'To:',
            'With:'                    => 'With:',
            'At:'                      => 'At:',
            'On:'                      => 'On:',
            'Your Reservation Number:' => 'Your Reservation Number:',
        ],
        "pt" => [
            'Summary:' => 'Resumo:',
            // 'Name:' => 'Name:',
            'From:'                    => 'De:',
            'To:'                      => 'Para:',
            'With:'                    => 'Com:',
            'At:'                      => 'Em:',
            'On:'                      => 'Em:',
            'Your Reservation Number:' => 'Seu número de reserva:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mozio.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains(['Mozio Inc.', 'The Mozio Team', 'info@mozio.com'])}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Summary:']) && !empty($dict['With:'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Summary:'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['With:'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mozio\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Summary:']) && !empty($dict['With:'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Summary:'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['With:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->ParseTransfer($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseTransfer(Email $email)
    {
        $t = $email->add()->transfer();

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Reservation Number:'))}]",
                null, true, "/{$this->opt($this->t('Your Reservation Number:'))}\s*([\dA-Z]{5,})\s*$/"));

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Summary:'))}]/following::text()[normalize-space()][position() < 5][{$this->starts($this->t('Name:'))}]",
            null, true, "/{$this->opt($this->t('Name:'))}\s*(.+)/");

        if (!empty($traveller)) {
            $t->general()
                ->traveller($traveller);
        }
        $s = $t->addSegment();
        $depText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('From:'))}]", null, true, "/{$this->opt($this->t('From:'))}\s*(.+)/");

        if (preg_match("/^\s*([A-Z]{3})\s*$/", $depText, $m)) {
            $s->departure()
                ->code($m[1]);
        } else {
            $s->departure()
                ->name($depText);
        }

        $dateTransfer = $this->http->FindSingleNode("//text()[{$this->eq($this->t('On:'))}]/following::text()[normalize-space()][1]");
        $timeTransfer = $this->http->FindSingleNode("//text()[{$this->eq($this->t('At:'))}]/following::text()[normalize-space()][1]");

        if ($this->lang == 'pt') {
            $dateTransfer = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Summary:'))}]/following::text()[{$this->eq($this->t('On:'))}])[2]/following::text()[normalize-space()][1]");
            $timeTransfer = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Summary:'))}]/following::text()[{$this->eq($this->t('At:'))}])[1]/following::text()[normalize-space()][1]");
        }
        $s->departure()
            ->date($this->normalizeDate($dateTransfer . ', ' . $timeTransfer));

        $arrText = trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('To:'))}]/following::text()[normalize-space()][1]"));

        if (preg_match("/^\s*([A-Z]{3})\s*$/", $arrText, $m)) {
            $s->arrival()
                ->code($m[1]);
        } else {
            $s->arrival()
                ->name($arrText);
        }

        $s->arrival()
            ->noDate();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate($date)
    {
        $in = [
            // Sexta-feira, 24 Novembro 2023, 15:55
            '/^\s*[[:alpha:]\-]+\s*,\s*(\d+\s*[[:alpha:]]+\s*\d{4})\s*,\s*(\d{1,2}:\d{2}(\s*[AP]M))\s*$/ui',
        ];
        $out = [
            '$1, $2',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }
}
