<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ChangeTime extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-689384737.eml, tapportugal/it-689828332.eml";
    public $subjects = [
        'An important message regarding flight',
        'Informação importante sobre o seu voo',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ["Important Flight Information"],
        "pt" => ["Informação importante sobre seu voo"],
    ];

    public static $dictionary = [
        "en" => [
        ],
        "pt" => [
            'Departure Time Change'                    => 'Alteração de horário de voo',
            'Important Flight Information'             => 'Informação importante sobre seu voo',
            'Booking reference:'                       => 'Código de Reserva:',
            'TAP informs you that your flight Flight'  => 'A TAP informa que o seu voo Flight',
            'from'                                     => 'de',
            'to'                                       => 'para',
            'on'                                       => 'no dia',
            'The flight will be departing at'          => 'O seu voo partirá às',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@my-notification.flytap.com') !== false) {
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

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'TAP Air Portugal')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Departure Time Change'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Important Flight Information'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]my\-notification\.flytap\.com$/', $from) > 0;
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]/ancestor::tr[1]", null, true, "/\:\s*([A-Z\d]{6})$/u"));

        $s = $f->addSegment();

        $flightInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TAP informs you that your flight Flight'))}]");

        if (preg_match("/{$this->opt($this->t('Flight'))}\s*(?<aName>([A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})\s+{$this->opt($this->t('from'))}\s+(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s+{$this->opt($this->t('to'))}\s+(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)\s+{$this->opt($this->t('on'))}\s+(?<depDate>\d+\s*\w+\s*\d{4}).*{$this->opt($this->t('The flight will be departing at'))}\s+(?<depTime>[\d\:]+)/", $flightInfo, $m)) {
            $s->airline()
                ->name($m['aName'])
                ->number($m['fNumber']);
        }

        $s->departure()
            ->date(strtotime($m['depDate'] . ', ' . $m['depTime']))
            ->name($m['depName'])
            ->code($m['depCode']);

        $s->arrival()
            ->noDate()
            ->name($m['arrName'])
            ->code($m['arrCode']);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }
}
