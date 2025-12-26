<?php

namespace AwardWallet\Engine\viarail\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CancellationConfirmation extends \TAccountChecker
{
    public $mailFiles = "viarail/it-30181722.eml, viarail/it-79480000.eml";

    public $reFrom = ["@viarail.ca"];
    public $reBody = [
        'en' => ['Cancellation Confirmation'],
        'fr' => ['Confirmation d\'annulation'],
    ];
    public $reSubject = [
        // en
        'Your VIA Cancellation Confirmation',
        // fr
        'Votre numéro de confirmation d\'annulation est',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            //            'Refund Details' => '',
            //            'has been cancelled' => '',
            //            'Cancellation Number' => '',
            'Booking number'       => ['Booking number', 'The booking'],
            'after Booking number' => ['has been cancelled', 'paid for with the'],
        ],
        'fr' => [
            'Refund Details'       => 'Description détaillée du remboursement',
            'has been cancelled'   => 'a été annulée',
            'Cancellation Number'  => 'Numéro d\'annulation',
            'Booking number'       => 'La réservation',
            'after Booking number' => 'payée avec la carte',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'https://reservia.viarail.ca')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $flagPrv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $flagPrv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($flagPrv && stripos($headers["subject"], $reSubject) !== false) || strpos($headers["subject"],
                        'VIA') !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Refund Details'))}]")->length > 0) {
            $t = $email->add()->train();

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('has been cancelled'))}]")->length > 0) {
                $t->general()->cancelled();
            }
            $cancelNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Number'))}]/following::text()[normalize-space()!=''][1]");
            $t->general()
                ->cancellationNumber($cancelNumber);
            $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking number'))}]");

            if (preg_match("/{$this->opt($this->t('Booking number'))}\s+([A-Z\d]+)\s+{$this->opt($this->t('after Booking number'))}/",
                $node, $m)) {
                $t->general()
                    ->confirmation($m[1], $this->t('Booking Number'));
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
