<?php

namespace AwardWallet\Engine\ninja\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PaymentConfirmation extends \TAccountChecker
{
    public $mailFiles = "ninja/it-317318782.eml, ninja/it-317385949.eml, ninja/it-321720446.eml, ninja/it-322660985.eml";
    public $subjects = [
        'Your payment confirmation |',
        'Payment confirmation |',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Thank you for booking your tickets with us' => 'Thank you for booking your tickets with us',
            'Your Payment Details'                       => ['Your Payment Details', 'Your Payment details', 'Payment confirmation'],
            'Amount'                                     => 'Amount',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rail.ninja') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains('Rail.Ninja')}] | //a/@href[{$this->contains(['rail.ninja'])}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Thank you for booking your tickets with us'])
                && !empty($dict['Your Payment Details']) && !empty($dict['Amount'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Thank you for booking your tickets with us'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($dict['Your Payment Details'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->starts($dict['Amount'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rail\.ninja$/', $from) > 0;
    }

    public function ParseRail(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Account:')]/ancestor::tr[1]/descendant::td[2]"), true);

        $priceText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Amount') ]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $priceText, $m)) {
            $t->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Order ID:')]/following::text()[starts-with(normalize-space(), 'Train')]");

        foreach ($nodes as $root) {
            $railInfo = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^Train\s*(?<service>\D+)?\s*(?<number>\d[A-z\d]+)?\s*(?:\|\s*\d+)?\s+(?<depName>[A-z].*)\s+\-\s+(?<arrName>.+)$/u", $railInfo, $m)) {
                $s = $t->addSegment();

                if (isset($m['service']) && !empty($m['service'])) {
                    $s->setServiceName($m['service']);
                }

                if (isset($m['number']) && !empty($m['number'])) {
                    $s->setNumber($m['number']);
                } else {
                    $s->setNoNumber(true);
                }

                $s->departure()
                    ->date(strtotime($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Departure')][1]/ancestor::tr[1]/descendant::td[2]", $root)))
                    ->name($m['depName']);

                $s->arrival()
                    ->date(strtotime($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Arrival')][1]/ancestor::tr[1]/descendant::td[2]", $root)))
                    ->name($m['arrName']);

                $s->extra()
                    ->cabin($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Ticket class:')][1]/ancestor::tr[1]/descendant::td[2]", $root));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//a[contains(normalize-space(), 'My account')]/following::text()[contains(normalize-space(), 'Order ID:')][1]", null, true, "/{$this->opt($this->t('Order ID:'))}\s*(RN[\d\-]+)/"), 'Order ID');

        $this->ParseRail($email);

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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
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
