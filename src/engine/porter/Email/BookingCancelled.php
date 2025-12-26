<?php

namespace AwardWallet\Engine\porter\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingCancelled extends \TAccountChecker
{
    public $mailFiles = "porter/it-715324358.eml, porter/it-716640524.eml, porter/it-771382672.eml";
    public $subjects = [
        'Your booking has been successfully cancelled',
        'Votre réservation a été annulée avec succès',
    ];

    public $lang = 'en';
    public $lastDate = '';
    public $countSegments = 1;

    public static $dictionary = [
        "en" => [
            'Your booking has been successfully cancelled' => 'Your booking has been successfully cancelled',
            'booking for the following passengers:'        => 'booking for the following passengers:',
        ],
        "fr" => [
            'Your booking has been successfully cancelled' => 'Votre réservation a bel et bien été annulée.',
            'booking for the following passengers:'        => 'réservation pour les passagers suivants.',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@notifications.flyporter.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains(['flyporter.com'])}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your booking has been successfully cancelled']) && !empty($dict['booking for the following passengers:'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['Your booking has been successfully cancelled'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['booking for the following passengers:'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]notifications\.flyporter\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your booking has been successfully cancelled']) && !empty($dict['booking for the following passengers:'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['Your booking has been successfully cancelled'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['booking for the following passengers:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confs = $this->http->FindNodes("//text()[{$this->eq($this->t('Confirmation number'))}]/following::text()[normalize-space()][1]");

        foreach ($confs as $conf) {
            $confDesc = $this->http->FindSingleNode("//text()[{$this->eq($conf)}]/preceding::text()[normalize-space()][1]");
            $f->general()
                ->confirmation($conf, $confDesc);
        }

        $f->general()
            ->status('Cancelled')
            ->cancelled()
        ;

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('booking for the following passengers:'))}]/following::text()[normalize-space()][1]/ancestor::*[not({$this->contains($this->t('booking for the following passengers:'))})][last()]/descendant::tr[not(.//tr)][normalize-space()]");

        foreach ($nodes as $pRoot) {
            if (preg_match("/^\D+$/", $pRoot->nodeValue)) {
                $traveller = $pRoot->nodeValue;
                $f->general()
                    ->traveller($traveller, true);
            } elseif (preg_match("/^\s*(\D+)\s*(\d{5,})\s*$/", $pRoot->nodeValue, $m)) {
                $f->program()
                    ->account($m[2], false, $traveller, $m[1]);
            }
        }
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
