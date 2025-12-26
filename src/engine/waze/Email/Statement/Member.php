<?php

namespace AwardWallet\Engine\waze\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Member extends \TAccountChecker
{
    public $mailFiles = "waze/it-79931912.eml, waze/it-79934084.eml, waze/it-79992352.eml, waze/it-79993862.eml, waze/it-80056904.eml, waze/it-80078208.eml, waze/it-80122685.eml, waze/it-80289653.eml, waze/it-85197810.eml, waze/it-85814988.eml, waze/it-86139626.eml, waze/it-86151849.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = '@waze.com';

    private $detectSubject = [
        'Welcome to Waze Carpool',
        'Your year with Waze',
    ];

    private $detectBody = [
        'map issue you are following',
        'map issue you reported',
        'as you are an active editor of Waze Map',
        'received a new private message from a Waze community member',
        'You requested to reset your Waze password.',
        'Here\'s your drive summary for the month',
        'because of your activity on the Waze Map Editor',
        'You are receiving this email as you are an active editor of Waze Map',
        'You have received this email because someone sent your a ride offer/request',
        'You have received this email because someone sent you a ride request',
        // pt
        'Este é um e-mail de serviço obrigatório do Waze.',
        'Você recebeu este e-mail porque alguém enviou um pedido de carona',
        'Você recebeu esta mensagem como um resumo de sua atividade no aplicativo Waze',

        // Carpool
        'You\'re all set for your carpool. Here are the details',
        'Didn’t carpool this month? No worries',
        'offered you a ride',
        'You’ve carpooled together',
        'This is a mandatory service email from Waze.',
        // pt
        'Aqui está o resumo dos seus percursos para o mês',
        'ofereceu uma carona',
        'Tudo pronto para a sua carona. Veja os detalhes',
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->detectBody) . "])[1]"))
            || $this->striposAll($parser->getSubject(), $this->detectSubject) == true
        ) {
            $st = $email->add()->statement();

            $st
                ->setMembership(true)
                ->setNoBalance(true)
            ;

            $prefix = ["Hi", "Hello", "Hey", "Ei"];
            $nameRe = "[[:alpha:]][[:alpha:]\-]*(?: [[:alpha:]][[:alpha:]\-]*){0,4}";
            $name = $this->http->FindSingleNode("//text()[normalize-space()][position()<5][" . $this->starts($prefix) . "]",
                null, true, "/^\s*" . $this->preg_implode($prefix) . "[ ,]+(" . $nameRe . ")[.,]*\s*$/u");

            if (!empty($name) && !preg_match('/\beditor\b/i', $name)) {
                $st->addProperty("Name", $name);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
