<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UnableToConfirmJunk extends \TAccountChecker
{
    public $mailFiles = "agoda/statements/it-64958097.eml, agoda/statements/it-65220485.eml";
    public $detectSubjects = [
        // en
        'unable to confirm your booking - Booking ID:',
        'Agoda is unable to confirm your flight booking – ',
        // pt
        'não consegue confirmar a sua reserva - ID da Reserva:',
        // fr
        'n\'est pas en mesure de confirmer votre réservation - N° de réservation',
        // ko
        '예약을 확정할 수 없습니다 - 예약 번호:',
        // es
        'no puede confirmar tu reserva - ID de reserva:',
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'detectPhrases' => [
                'You did not receive a booking confirmation and no booking has been made.',
                'you did not receive a booking confirmation and no booking as been made',
                'your booking was not completed due to Flights itinerary is no longer available',
                'We\'re sorry, your booking was not completed due to Payment processing error',
            ],
        ],
        'pt' => [
            'detectPhrases' => [
                'Você não recebeu uma confirmação de reserva e nenhuma reserva foi feita',
            ],
        ],
        'fr' => [
            'detectPhrases' => [
                'Vous n\'avez pas reçu de confirmation de réservation et aucune réservation n\'a été effectuée',
            ],
        ],
        'ko' => [
            'detectPhrases' => [
                '고객님께 예약 확정서가 전송되지 않았으며, 예약이 완료되지 않았습니다.',
            ],
        ],
        'es' => [
            'detectPhrases' => [
                'No se ha enviado ningún email de confirmación de reserva ni se ha realizado ninguna reserva.',
            ],
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
            && $this->http->XPath->query('//*[contains(normalize-space(),"Agoda Company Pte Ltd")]')->length === 0
        ) {
            // return false;
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
            if (!empty($dict['detectPhrases']) && $this->http->XPath->query("//node()[{$this->contains($dict['detectPhrases'])}]")->length > 0
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
            if (!empty($dict['detectPhrases']) && $this->http->XPath->query("//node()[{$this->contains($dict['detectPhrases'])}]")->length > 0
            ) {
                $email->setIsJunk(true, 'Not confirmed reservation');

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
