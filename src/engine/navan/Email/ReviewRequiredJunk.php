<?php

namespace AwardWallet\Engine\navan\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReviewRequiredJunk extends \TAccountChecker
{
    public $mailFiles = "";

    public $detectSubjects = [
        // en
        'Review required: ',
        // es
        'Revisión requerida: ',
        // de
        'Überprüfung erforderlich: ',
        // nl
        'Beoordeling vereist: ',
    ];

    public $pdfNamePattern = ".*pdf";

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'You have 24 hours to review this booking. If it’s not canceled by' =>
                ' hours to review this booking. If it’s not canceled by',
            ', it will be automatically approved.' => ', it will be automatically approved.',
            'Booking details'                      => 'Booking details',
        ],
        'es' => [
            'You have 24 hours to review this booking. If it’s not canceled by' =>
                ' horas para revisar esta reserva. Si no la apruebas antes del día',
            ', it will be automatically approved.' => ', será aprobada automáticamente.',
            'Booking details'                      => 'Detalles de la reserva',
        ],
        'de' => [
            'You have 24 hours to review this booking. If it’s not canceled by' =>
                ' Stunden Zeit, um diese Buchung zu überprüfen. Wenn sie nicht bis',
            ', it will be automatically approved.' => 'storniert wird, wird sie automatisch genehmigt.',
            'Booking details'                      => 'Buchungsdetails',
        ],
        'nl' => [
            'You have 24 hours to review this booking. If it’s not canceled by' =>
                ' uur om deze boeking te beoordelen. Als de boeking',
            ', it will be automatically approved.' => 'niet is geannuleerd, wordt deze automatisch goedgekeurd.',
            'Booking details'                      => 'Boekingsdetails',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@navan.com') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Navan Inc')]")->length > 0) {
            foreach (self::$dictionary as $dict) {
                if (!empty($dict['You have 24 hours to review this booking. If it’s not canceled by'])
                    && !empty($dict[', it will be automatically approved.'])
                    && $this->http->XPath->query("//text()[{$this->contains($dict['You have 24 hours to review this booking. If it’s not canceled by'])}]/ancestor::td[1][{$this->contains($dict[', it will be automatically approved.'])}]")->length > 0
                    && !empty($dict['Booking details']) && $this->http->XPath->query("//text()[{$this->contains($dict['Booking details'])}]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]navan\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Navan Inc')]")->length > 0) {
            foreach (self::$dictionary as $dict) {
                if (!empty($dict['You have 24 hours to review this booking. If it’s not canceled by'])
                    && !empty($dict[', it will be automatically approved.'])
                    && $this->http->XPath->query("//text()[{$this->contains($dict['You have 24 hours to review this booking. If it’s not canceled by'])}]/ancestor::td[1][{$this->contains($dict[', it will be automatically approved.'])}]")->length > 0
                    && !empty($dict['Booking details']) && $this->http->XPath->query("//text()[{$this->contains($dict['Booking details'])}]")->length > 0
                ) {
                    $email->setIsJunk(true);

                    break;
                }
            }
        }

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

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) {
            return $text . "=\"{$s}\"";
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
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
