<?php

namespace AwardWallet\Engine\nomad\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Train extends \TAccountChecker
{
    public $mailFiles = "nomad/it-12260858.eml, nomad/it-136328228.eml";
    public $subjects = [
        'INFO SNCF NOMAD TRAIN – Confirmation de réservation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@cloud.sqills.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'SNCF NOMAD TRAIN TEAM')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for your booking with NOMAD trains'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your train'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('you can collect your ticket at the station'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]cloud\.sqills\.com$/', $from) > 0;
    }

    public function ParseTrain(Email $email)
    {
        $xpath = "//text()[starts-with(normalize-space(), 'Your train')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $t = $email->add()->train();

            $t->general()
                ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'booking number* :')]", null, true, "/{$this->opt($this->t('booking number* :'))}\s*([A-Z\d]{6})\./"))
                ->traveller($this->http->FindSingleNode("./following::text()[contains(normalize-space(), ': coach')][1]", $root, true, "/^\s*([A-Z\s]+)\s*\:/su"));

            $segInfo = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^Your\s*train\s*(?<number>\d{2,4})\s*on\s*(?<date>[\d\-]+)\s*will leave from\s*(?<depName>[A-Z\s]+)\s*at\s*(?<depTime>[\d\:]+)\s*and will arrive at\s*(?<arrTime>[\d\:]+)\s*at your destination\s*(?<arrName>[A-Z\s\']+)\.$/", $segInfo, $m)) {
                $s = $t->addSegment();

                $s->setNumber($m['number']);

                $depDate = str_replace('-', '.', $m['date'] . ', ' . $m['depTime']);
                $arrDate = str_replace('-', '.', $m['date'] . ', ' . $m['arrTime']);

                $s->departure()
                    ->date(strtotime($depDate))
                    ->name($m['depName']);

                $s->arrival()
                    ->date(strtotime($arrDate))
                    ->name($m['arrName']);

                $accommodation = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), ': coach')][1]", $root);

                if (preg_match("/coach\s*(?<car>\d+)\,\s*seat\s*(?<seat>\d+)$/", $accommodation, $m)) {
                    $s->setCarNumber($m['car']);
                    $s->extra()
                        ->seat($m['seat']);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseTrain($email);

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
}
