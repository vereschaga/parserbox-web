<?php

namespace AwardWallet\Engine\ajet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightItinerary extends \TAccountChecker
{
    public $mailFiles = "ajet/it-735180372.eml, ajet/it-762876477.eml, ajet/it-771004063.eml";

    public $subjects = [
        'Travel Itinerary',
    ];

    public static $providers = [
        "ajet" => ["AJet"],
        "indigo" => ["IndiGo"],
        "norse" => ["Norse Atlantic Airways"],
        "aa" => ["American Airlines"],
        "turkish" => ["Turkish Airlines"],
        "lufthansa" => ["Lufthansa Group"],
        "golair" => ["Gol Airlines"],
        "hawaiian" => ["HawaiianAirlines", "Hawaiian Airlines"],
        "copaair" => ["Copa Airlines"],
        "tway" => ["TwayAir"],
        "springairlines" => ["Spring Airlines"]
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [],
    ];

    public function detectEmailByHeaders(array $headers): bool
    {
        if (isset($headers['from']) && stripos($headers['from'], '@travelfusion.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser): bool
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Reference/PNR'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Journey details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Contact details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Payment details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Important Terms & Conditions / Rules / Restrictions / Notes'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from): bool
    {
        return preg_match('/[@.]travelfusion\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $subject = $parser->getSubject();
        if (!empty($providerCode = $this->getProviderCode($subject))){
            $email->setProviderCode($providerCode);
        }

        $this->Flight($email, $subject);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email, $subject)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Reference/PNR:'))}]/ancestor::td[normalize-space()][1]", null, false, "/^{$this->opt($this->t('Reference/PNR:'))}\s*([A-Z\d]+)\s*$/"), $this->t('Reference/PNR'))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Book date:'))}]/ancestor::td[normalize-space()][1]", null, false, "/^{$this->opt($this->t('Book date:'))}\s*(\d+\/\d+\/\d{4}\-\d+\:\d+)\s*$/")))
            ->status($this->http->FindSingleNode("//text()[{$this->contains($this->t('Status:'))}]/ancestor::td[normalize-space()][1]", null, false, "/^{$this->opt($this->t('Status:'))}\s*(\w+)$/"));

        $travellers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger(s)'))}]/ancestor::table[1]/descendant::td[not({$this->contains($this->t('Passenger(s)'))})]/descendant::tr[not(contains(normalize-space(),'Ticket Number:'))]", null, "/^[\w\.]+\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");

        if (!empty($travellers)) {
            $f->setTravellers(array_unique($travellers), true);
        }

        foreach ($travellers as $name){
            $ticket = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Passenger(s):'))}]/following::table[1]/descendant::tr[{$this->contains($this->t($name))}]/following-sibling::tr[1]", null,true,"/{$this->opt($this->t('Ticket Number:'))}\s+([\-\d]+)$/");

            if (!empty($ticket)) {
                $f->addTicketNumber($ticket, false, $name);
            }
        }

        $segmentNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Departure time'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('Number'))})]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root);

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))?[a-z]*\s*(?<fNumber>\d{1,4})$/", $airInfo, $m)) {
                if (!empty($m["aName"])){
                    $s->airline()
                        ->name($m["aName"]);

                } elseif (preg_match("/^(.*)\s*\-\s*Travel\s*Itinerary/", $subject, $match)){
                    $s->airline()
                        ->name(str_replace("Group", "", $match[1]));
                }

                if (!empty($m["fNumber"])){
                    $s->airline()
                        ->number($m["fNumber"]);
                }
            }

            $s->setDepCode($this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $root, true, "/^\s*[A-Z]{3}\s*/"));
            $s->setArrCode($this->http->FindSingleNode("./descendant::td[normalize-space()][3]", $root, true, "/^\s*[A-Z]{3}\s*/"));

            $s->setDepDate($this->normalizeDate($this->http->FindSingleNode("./descendant::td[normalize-space()][4]", $root, true, "/^\s*(\d+\/\d+\/\d{4}\-\d+\:\d+)\s*$/")));
            $s->setArrDate($this->normalizeDate($this->http->FindSingleNode("./descendant::td[normalize-space()][5]", $root, true, "/^\s*(\d+\/\d+\/\d{4}\-\d+\:\d+)\s*$/")));
        }

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total paid:'))}]/ancestor::tr[1]", null, true, "/^{$this->t('Total paid:')}\s*([\d\.\,\`]+\s*\D{1,3})$/");

        if (preg_match("/^(?<price>[\d\.\,\']+)\s*(?<currency>\D{1,3})$/", $priceInfo, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight fares:'))}]/ancestor::tr[1]", null, true, "/^{$this->t('Flight fares:')}\s*([\d\.\,\`]+)\s*\D{1,3}$/");

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $feesNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight fares:'))}]/ancestor::tr[1]/following-sibling::tr[not({$this->contains('Total paid:')})]");

            if ($feesNodes !== null) {
                foreach ($feesNodes as $root) {
                    $feeName = $this->http->FindSingleNode("./descendant::td[1]", $root);

                    $feeSum = $this->http->FindSingleNode("./descendant::td[2]", $root, true, '/^([\d\.\,\`]+)\s*\D{1,3}$/');

                    if ($feeName !== null && $feeSum !== null) {
                        $f->price()
                            ->fee($feeName, PriceHelper::parse($feeSum, $m['currency']));
                    }
                }
            }
        }
    }

    public function getProviderCode($subject){
        $provText = $this->re("/^(.+)\s+\-\s+/", $subject);

        foreach (self::$providers as $code => $provider){
            foreach ($provider as $item){
                if (stripos($item, $provText) !== false){
                    return $code;
                }
            }
        }
        return null;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d+)\/(\d+)\/(\d{4})\-(\d+\:\d+)\s*$#u", // 14/05/2024-20:55
        ];
        $out = [
            "$1.$2.$3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }
}
