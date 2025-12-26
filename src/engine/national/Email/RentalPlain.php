<?php

namespace AwardWallet\Engine\national\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalPlain extends \TAccountChecker
{
    public $mailFiles = "national/it-288541835.eml";
    public $subjects = [
        'National Car Rental Pick-up,',
        'National Car Rental Drop-off,',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.egencia.com') !== false) {
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
        $body = $parser->getPlainBody();

        if (stripos($body, 'National Car Rental') !== false
            && stripos($body, 'Car type:') !== false
            && stripos($body, 'Pick-up') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.egencia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getPlainBody();

        $otaConf = $this->re("/{$this->opt($this->t('Itinerary number:'))}\s*(\d+)\n/", $text);

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $this->ParseRental($email, $text);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseRental(Email $email, $text)
    {
        $r = $email->add()->rental();

        $r->general()
            ->traveller($this->re("/{$this->opt($this->t('Traveller:'))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n/", $text), true)
            ->confirmation($this->re("/{$this->opt($this->t('National Car Rental confirmation number:'))}\s*([A-Z\d]{5,})\n/", $text));

        if (preg_match("/{$this->opt($this->t('Pick-up'))}\n(?<date>.+\d+\:\d+(?:\s*a?p?m?)?)\n(?<location>.+)/", $text, $m)) {
            $r->pickup()
                ->date(strtotime($m['date']))
                ->location($m['location']);
        }

        if (preg_match("/{$this->opt($this->t('Drop-off'))}\n(?<date>.+\d+\:\d+(?:\s*a?p?m?)?)\n(?<location>.+)/", $text, $m)) {
            $r->dropoff()
                ->date(strtotime($m['date']))
                ->location($m['location']);
        }

        $r->car()
            ->type($this->re("/{$this->opt($this->t('Car type:'))}\s*(.+)\n/", $text));
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
}
