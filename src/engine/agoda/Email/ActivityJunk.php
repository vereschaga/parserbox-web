<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ActivityJunk extends \TAccountChecker
{
    public $mailFiles = "";
    public $detectSubjects = [
        // en
        'Booking confirmation with Agoda - Booking ID:',
        'Confirmation for activity Booking ID',
        // ja
        '【Agoda】ご予約確認書 - 予約ID：',
        // pt
        'Agoda está processando sua reserva - ID da Reserva:',
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Activity details'  => 'Activity details',
            'Activity provider' => 'Activity provider',
        ],
        'ja' => [
            'Activity details'  => 'アクティビティ詳細',
            'Activity provider' => 'アクティビティ事業者',
        ],
        'pt' => [
            'Activity details'  => 'Detalhes sobre a atividade',
            'Activity provider' => 'Fornecedor de atividades',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos($parser->getCleanFrom(), 'no-reply@agoda.com') === false
            && $this->http->XPath->query("//a/@href[{$this->contains('www.agoda.com/')}]")->length === 0
            && $this->http->XPath->query("//node()[{$this->contains('Agoda Company Pte')}]")->length === 0
        ) {
            return false;
        }

        $detectedSubject = false;

        foreach ($this->detectSubjects as $subject) {
            if (mb_stripos($parser->getSubject(), $subject) !== false) {
                $detectedSubject = true;

                break;
            }
        }

        if ($detectedSubject === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Activity details']) && $this->http->XPath->query("//node()[{$this->eq($dict['Activity details'])}]")->length > 0
                && !empty($dict['Activity provider']) && $this->http->XPath->query("//node()[{$this->eq($dict['Activity provider'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]agoda\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Activity details']) && $this->http->XPath->query("//node()[{$this->eq($dict['Activity details'])}]")->length > 0
                && !empty($dict['Activity provider']) && $this->http->XPath->query("//node()[{$this->eq($dict['Activity provider'])}]")->length > 0
            ) {
                $email->setIsJunk(true, 'not contains address');

                break;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
