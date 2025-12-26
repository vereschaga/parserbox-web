<?php

namespace AwardWallet\Engine\ticketmaster\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "ticketmaster/it-236207440.eml, ticketmaster/it-236208296.eml";
    public $subjects = [
        'Confirmation de votre commande',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ['This is an order confirmation email'],
        "fr" => ['billet électronique pour', 'Nous avons le plaisir de vous confirmer'],
    ];

    public static $dictionary = [
        "en" => [
            //'ORDER CONFIRMATION' => '',
            //'YOUR ORDER' => '',
            //'Hello' => '',
            //'Reference order' => '',
            //'Amount:' => '',
            //'Event' => '',
            //'ASSURANCE' => '',
        ],

        "fr" => [
            'ORDER CONFIRMATION' => 'CONFIRMATION DE COMMANDE',
            'YOUR ORDER'         => 'VOTRE COMMANDE',
            'Hello'              => 'Bonjour',
            'Reference order'    => 'Référence commande:',
            'Amount:'            => 'Montant total:',
            'Event'              => 'Manifestation',
            'ASSURANCE'          => 'ASSURANCE',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ticketmaster.') !== false) {
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

        if ($this->http->XPath->query("//a[contains(@href, '.ticketmaster.')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('ORDER CONFIRMATION'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR ORDER'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Event'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ticketmaster\.$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $priceText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Amount:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Amount:'))}\s*([\d\,\.]+\s*\D{1,3})/");

        if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})\s*$/", $priceText, $m)) {
            $email->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Event'))}]/ancestor::tr[1]/following-sibling::tr[not({$this->contains($this->t('ASSURANCE'))})]");

        foreach ($nodes as $root) {
            $e = $email->add()->event();

            $e->general()
                ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference order'))}]/following::text()[normalize-space()][1]"))
                ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Hello'))}\s*\w*\s*([A-Z\s]+)\,/"));

            $e->setName($this->http->FindSingleNode("./descendant::td[1]", $root))
                ->setEventType(3)
                ->setAddress($this->http->FindSingleNode("./descendant::td[2]", $root))
                ->setStartDate(strtotime($this->http->FindSingleNode("./descendant::td[3]", $root)))
                ->setNoEndDate(true);

            $e->booked()
                ->guests($this->http->FindSingleNode("./descendant::td[4]", $root));

            $e->setSeats([$this->http->FindSingleNode("./descendant::td[5]", $root)]);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseEmail($email);

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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $wordArray) {
            foreach ($wordArray as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
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

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
