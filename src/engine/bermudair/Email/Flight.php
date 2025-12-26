<?php

namespace AwardWallet\Engine\bermudair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "bermudair/it-735411740.eml, bermudair/it-751713501.eml, bermudair/it-759839625.eml";

    public $subjects = [
        'Booking Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Terminal' => ['Terminal', 'Terminal/Concourse:', 'Terminal:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flybermudair.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('@flybermudair.com'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Flights'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Fare Conditions'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Can we lend a hand?'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flybermudair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->Flight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains('BOOKING REF:')}]", null, true, "/^\D+\:\s*([A-Z\d]+)$/"));

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq('TOTAL')}]/ancestor::td[1]/following-sibling::td[1]", null, true, "/^(\D{1,3}[\d\.\,\`]+)$/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq('Fare (w/o Tax)')}]/ancestor::td[1]/following-sibling::td[1]", null, true, "/^\D{1,3}([\d\.\,\`]+)$/");

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[{$this->eq('Fare Total Tax')}]/ancestor::td[1]/following-sibling::td[1]", null, true, "/^\D{1,3}([\d\.\,\`]+)$/");

            if ($tax !== null) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $ancillariesTotal = $this->http->FindSingleNode("//text()[{$this->eq('Ancillaries Total')}]/ancestor::td[1]/following-sibling::td[1]", null, true, "/^\D{1,3}([\d\.\,\`]+)$/");

            if ($ancillariesTotal !== null) {
                $f->price()
                    ->fee('Ancillaries Total', PriceHelper::parse($ancillariesTotal, $m['currency']));
            }
        }

        $travellers = $this->http->FindNodes("//text()[{$this->eq('Travellers')}]/ancestor::table[1]/descendant::tr[position() >= 3]/descendant::tr", null, "/^[\w\.]+\s*[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]$/u");
        $f->setTravellers(preg_replace("/^(?:Ms\.|Mr\.|Mrs\.|Child|BOY|GIRL|MR|MS)/", "", $travellers), true);

        $segmentNodes = $this->http->XPath->query("//text()[{$this->contains('From')}]/ancestor::table[1]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./descendant::tr[1]/descendant::td[2]", $root, true, '/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4})\,\s*/');

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $flightDate = str_replace('/', '.', $this->http->FindSingleNode("./descendant::tr[1]/descendant::td[2]", $root, true, '/\,\s*(\d+[\/\.\-]\d+[\/\.\-]\d+)$/'));

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::tr[2]/descendant::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depName>.*)\s*\((?<depCode>[A-Z]{3})\)\n\s*(?:(?:{$this->opt($this->t('Terminal'))})?[\/\:\s]*(?<depTerminal>.+)\s*(?:Terminal)?)?$/", $depInfo, $m)
                || preg_match("/^(?<depName>.*)\n\s*(?:(?:{$this->opt($this->t('Terminal'))})?\s*(?<depTerminal>\w+)\s*(?:Terminal)?)?$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName']);

                if (isset($m['depCode']) && !empty($m['depCode'])) {
                    $s->departure()
                        ->code($m['depCode']);
                } else {
                    $s->departure()
                        ->noCode();
                }

                if (!empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $depDate = $this->http->FindSingleNode("./descendant::tr[3]/descendant::td[1]/descendant::text()[normalize-space()]", $root);

            if ($depDate !== null) {
                $s->departure()
                    ->date(strtotime($depDate . ' ' . $flightDate));
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::tr[2]/descendant::td[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<arrName>.*)\s*\((?<arrCode>[A-Z]{3})\)\n\s*(?:(?:{$this->opt($this->t('Terminal'))})?[\/\:\s]*(?<arrTerminal>.+)\s*(?:Terminal)?)?$/", $arrInfo, $m)
               || preg_match("/^(?<arrName>.*)\n\s*(?:(?:{$this->opt($this->t('Terminal'))})?\s*(?<arrTerminal>\w+)\s*(?:Terminal)?)?$/", $arrInfo, $m)
            ) {
                $s->arrival()
                    ->name($m['arrName']);

                if (isset($m['arrCode']) && !empty($m['arrCode'])) {
                    $s->arrival()
                        ->code($m['arrCode']);
                } else {
                    $s->arrival()
                        ->noCode();
                }

                if (!empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $arrDate = $this->http->FindSingleNode("./descendant::tr[3]/descendant::td[3]", $root);

            if ($arrDate !== null) {
                $s->arrival()
                    ->date(strtotime($arrDate . ' ' . $flightDate));
            }

            $extraInfo = $this->http->FindSingleNode("./descendant::tr[2]/descendant::td[2]", $root, true, '/^(.*)$/');

            if (preg_match("/^(?<durInfo>[\d\:]*\s*\w+)\s*(?<cabinInfo>.*)$/", $extraInfo, $m)) {
                if (!empty($m['cabinInfo'])) {
                    $s->extra()
                        ->cabin($m['cabinInfo']);
                }

                if (!empty($m['durInfo'])) {
                    $s->extra()
                        ->duration($m['durInfo']);
                }
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
}
