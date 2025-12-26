<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReturnReceiptJunk extends \TAccountChecker
{
    public $mailFiles = "sixt/it-427055783.eml, sixt/it-71013973.eml";
    public $subjects = [
        '/(?:^|.:\s*)Return Receipt - Sixt Rental agreement\s*:\s*\d{5,}\s*#?\s*$/',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ["RESERVATION NUMBER", "Rental Agreement"],
        "fr" => ["Contrat de location", "LIEU DE RETOUR"],
    ];

    public static $dictionary = [
        "en" => [
            'Your vehicle return summary'            => ['Your vehicle return summary', 'YOUR VEHICLE RETURN SUMMARY', "INFORMATION ABOUT YOUR VEHICLE RETURN"],
            'Estimated Rental Cost (Not an Invoice)' => [
                'Estimated Rental Cost (Not an Invoice)',
                'ESTIMATED RENTAL COST (NOT AN INVOICE)',
                'More detailed information can be found in the attachment.',
            ],
            'THANK YOU FOR CHOOSING SIXT - YOUR RETURN RECEIPT' => ['YOU HAVE SUCCESSFULLY RETURNED YOUR VEHICLE', 'THANK YOU FOR CHOOSING SIXT - YOUR RETURN RECEIPT'],
        ],
        "fr" => [
            'Your vehicle return summary'                       => 'INFORMATIONS SUR LA RESTITUTION DE VOTRE VÉHICULE',
            'Estimated Rental Cost (Not an Invoice)'            => 'Vous trouverez des informations plus détaillées dans la pièce jointe.',
            'Sixt Team'                                         => ['Votre équipe SIXT'],
            'THANK YOU FOR CHOOSING SIXT - YOUR RETURN RECEIPT' => 'VOUS AVEZ RESTITUÉ VOTRE VÉHICULE AVEC SUCCÈS',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@sixt.com') !== false) {
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

        $this->logger->warning("//text()[{$this->contains($this->t('THANK YOU FOR CHOOSING SIXT - YOUR RETURN RECEIPT'))} or {$this->contains($this->t('Sixt Team'))}]");

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('THANK YOU FOR CHOOSING SIXT - YOUR RETURN RECEIPT'))} or {$this->contains($this->t('Sixt Team'))}]")->length > 0
            && $this->isJunk()) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sixt\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        if ($this->isJunk()) {
            $email->setIsJunk(true);
        }

        return $email;
    }

    public function AssignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->logger->error($lang);
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function isJunk(): bool
    {
        $this->logger->debug("//tr[{$this->eq($this->t('Your vehicle return summary'))}]");
        $this->logger->debug("//tr[{$this->eq($this->t('Estimated Rental Cost (Not an Invoice)'))}]");

        return $this->http->XPath->query("//tr[{$this->eq($this->t('Your vehicle return summary'))}]")->length > 0 && $this->http->XPath->query("//tr[{$this->eq($this->t('Estimated Rental Cost (Not an Invoice)'))}]")->length > 0;
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

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
