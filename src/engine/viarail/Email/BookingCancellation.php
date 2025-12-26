<?php

namespace AwardWallet\Engine\viarail\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingCancellation extends \TAccountChecker
{
    public $mailFiles = "viarail/it-603881415.eml, viarail/it-604994946.eml, viarail/it-649635989.eml";
    public $subjects = [
        '/^Booking cancellation\s+\|\s+.*\d{4}\s+\-\s+(?:Booking\s*[#:]*)?\s*[A-Z\d]+$/',
        '/^Annulation de réservation\s+\|\s+.*\d{4}\s+\-\s+[A-Z\d]+$/',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ['This email is to confirm that your booking has been cancelled', 'Refund confirmation'],
        "fr" => ['Annulation de réservation'],
    ];

    public static $dictionary = [
        "en" => [
            'Booking cancellation'            => ['Booking cancellation', 'Refund confirmation'],
            'your booking has been cancelled' => ['your booking has been cancelled', 'Here’s your refund confirmation for the booking'],
            'Booking'                         => ['Booking', 'Booking #:'],
        ],
        "fr" => [
            'Booking cancellation'            => 'Annulation de réservation',
            'your booking has been cancelled' => 'que votre réservation a été annulée',
            'Booking'                         => 'N° de réservation',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@viarail.ca') !== false) {
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
        $this->AssignLang();

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'VIA Rail Canada')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking cancellation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('your booking has been cancelled'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]viarail\.ca$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        if ($this->detectEmailByBody($parser) === true) {
            $t = $email->add()->train();
            $t->general()
                ->confirmation($this->re("/\s+(?:\-|\–)\s+(?:{$this->opt($this->t('Booking'))}\s*[#:]*)?\s*([A-Z\d]+)$/u", $parser->getSubject()))
                ->cancelled()
                ->status('cancelled');
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
            return str_replace(' ', '\s+', preg_quote($s));
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

    private function AssignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }
}
