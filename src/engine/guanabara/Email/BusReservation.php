<?php

namespace AwardWallet\Engine\guanabara\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BusReservation extends \TAccountChecker
{
	public $mailFiles = "guanabara/it-787927256.eml, guanabara/it-797905077.eml";
    public $subjects = [
        'Expresso Guanabara - Compra confirmada com sucesso',
        'Expresso Guanabara - Compra pendente de confirmação'
    ];

    public $lang = 'pt';

    public static $dictionary = [
        'pt' => [
            'detectPhrase' => ['confirmada com sucesso!', 'Recebemos o seu pedido!']
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@expressoguanabara.com.br') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Expresso Guanabara'))}]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('detectPhrase'))}]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Dados sobre à Viagem'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]expressoguanabara\.com\.br$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->BusReservation($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function BusReservation(Email $email)
    {
        $b = $email->add()->bus();

        $b->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Localizador do bilhete'))}]/ancestor::td[1]", null, true, "/^{$this->t('Localizador do bilhete')}\s*\:\s*([A-Z\d]{6})$/"));

        $reservationDate = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Data da Compra'))}]/ancestor::td[1]", null, false, "/^{$this->t('Data da Compra')}\s*\:\s*(\d+[\.\-\/]\d+[\.\-\/]\d+)$/");

        if ($reservationDate !== null) {
            $b->general()
                ->date($this->normalizeDate($reservationDate));
        }

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Valor Total da Compra'))}]/ancestor::td[1]", null, true, "/^{$this->t('Valor Total da Compra')}\s*\:\s*(\D{1,3}\s*[\d\.\,\`]+)$/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $b->price()
                ->total(PriceHelper::parse($m['price'], $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passagem'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[1]", null, true, "/^\D{1,3}\s*([\d\.\,\`]+)$/");

            if ($cost !== null) {
                $b->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $feesNodes = $this->http->FindNodes("//text()[{$this->eq($this->t('Passagem'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[position() > 2]", null, '/^\D{1,3}\s*([\d\.\,\`]+)$/');

            if ($feesNodes !== null) {
                $feeNameNum = 1;
                foreach ($feesNodes as $root) {
                    $feeName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passagem'))}]/following::td[$feeNameNum]");

                    if ($feeName !== null) {
                        $b->price()
                            ->fee($feeName, PriceHelper::parse($root, $currency));
                        $feeNameNum++;
                    }
                }
            }
        }

        $traveller = $this->http->FindNodes("//text()[{$this->eq($this->t('Passageiro'))}]/ancestor::tr[1]/following-sibling::tr/descendant::td[1]", null,  "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u");
        $b->setTravellers(array_unique($traveller), true);

        $tickets = $this->http->FindNodes("//text()[{$this->contains($this->t('N. Ticket'))}]/ancestor::tr[1]/following-sibling::tr/descendant::td[last()]", null, "/^(\d+)$/");

        foreach (array_unique($tickets) as $ticket) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($ticket)}]/ancestor::tr[1]/descendant::td[1]", null, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u");
            $b->addTicketNumber($ticket, false, $traveller);
        }

        $segmentNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('→'))}]/ancestor::tr[1]");

        foreach ($segmentNodes as $root) {
            $s = $b->addSegment();

            $cabinInfo = $this->http->FindSingleNode("./following-sibling::tr[2]/descendant::tr[2]/descendant::td[4]", $root, true, '/^(.+)$/');

            if (preg_match("/\d{6}/", $cabinInfo)) {
                $cabinInfo = $this->http->FindSingleNode("./following-sibling::tr[2]/descendant::tr[2]/descendant::td[3]", $root, true, '/^(.+)$/');

                if ($cabinInfo !== null){
                    $s->extra()
                        ->cabin($cabinInfo);
                }
            } else if ($cabinInfo !== null){
                $s->extra()
                    ->cabin($cabinInfo);
            }

            $depDate = $this->http->FindSingleNode("./preceding-sibling::tr[1]", $root, true, "/^.+\,\s*(\d+\s*de\s*\w+\s*de\s*\d{4}\s*às\s*[\d\:]+)$/u");

            if ($depDate !== null) {
                $s->departure()
                    ->date($this->normalizeDate($depDate));
            }

            $s->arrival()
                ->noDate();

            $busInfo = $this->http->FindSingleNode("./descendant::td[1]", $root);

            if (preg_match("/^(?<depName>.+\-\s*\w+)\s*\→\s*(?<arrName>.+\-\s*\w+)/u", $busInfo, $m)) {
                $s->departure()
                    ->name($m['depName']);

                $s->arrival()
                    ->name($m['arrName']);
            }


            $seatsInfo = $this->http->FindNodes("./following-sibling::tr[2]/descendant::tr[position() > 1]/descendant::td[2]", $root, '/^([A-Z\d]+)$/');

            foreach ($seatsInfo as $seat) {
                $traveller = $this->http->FindSingleNode("./following-sibling::tr[2]/descendant::tr[position() > 1]/descendant::td[2][{$this->eq($seat)}]/preceding::td[1]", $root, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u");

                $s->extra()
                    ->seat($seat, true, true, $traveller);
            }
        }
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'EUR' => ['€'],
            'BRL' => ['R$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($date)
    {
        $in = [
            "/^(\d+)\s*de\s*(\w+)\s*de\s*(\d{4})\s*às\s*([\d\:]+)$/u",
            "/^(\d+)\/(\d+)\/(\d{4})$/"
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1.$2.$3"
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/^\d+\s*(\w+)\s*\d{4}\s*\,\s*[\d\:]+/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
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
