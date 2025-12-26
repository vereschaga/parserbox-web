<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MobileBoardingPassHtml extends \TAccountChecker
{
    public $mailFiles = "asia/it-7061855.eml, asia/it-7103972.eml, asia/it-7116023.eml, asia/it-7154051.eml, asia/it-7193003.eml, asia/it-7213931.eml, asia/it-7230499.eml, asia/it-7239377.eml, asia/it-7270688.eml, asia/it-762622535.eml, asia/it-8411830.eml, asia/it-8473983.eml, asia/it-8497307.eml";

    public $reFrom = 'boardingpass@cathaypacific.com';

    private $lang = 'en';

    private $detectSubject = [
        // zh
        '的登机牌',
        // en
        'Your boarding pass for flight',
    ];
    private $detects = [
        'en' => ['Please check your flight status online'],
        'it' => ['Ti consigliamo di verificare online'],
        'zh' => ['請於出發前往機場前查核航班狀況', '请于出发前往机场前查核航班状况'],
        'nl' => ['Controleer voor vertrek naar de luchthaven uw vluchtstatus online'],
        'es' => ['Aspectos que conviene recordar'],
        'th' => ['โปรดตรวจสอบสถานะเที่ยวบินของท่านทางออนไลน์ก่อนที่จะออกเดินทางไปสนามบิน'],
    ];

    private static $dict = [
        'en' => [
            'Boarding Pass' => ['Boarding Pass', 'Boarding pass'],
        ],
        'it' => [
            'PASSENGER'     => 'PASSEGGERO',
            // 'FREQUENT FLYER' => '',
            'FLIGHT'        => 'VOLO',
            'DEPARTURE'     => 'PARTENZA',
            'Boarding Pass' => 'Carta d\'imbarco',
            //			'We are pleased to invite' => '',
            'SEAT' => 'POSTO',
            //			'comfort of'
        ],
        'zh' => [
            'PASSENGER'     => ['旅客', '乘客'],
            // 'FREQUENT FLYER' => '',
            'FLIGHT'        => '航班',
            'DEPARTURE'     => ['出發', '出发'],
            'Boarding Pass' => ['登機證', '登机牌'],
            //			'We are pleased to invite' => '',
            'SEAT' => '座位',
            //			'comfort of'
        ],
        'nl' => [
            'PASSENGER'     => 'PASSAGIER',
            // 'FREQUENT FLYER' => '',
            'FLIGHT'        => 'VLUCHT',
            'DEPARTURE'     => 'VERTREK',
            'Boarding Pass' => 'Instapkaart',
            //			'We are pleased to invite' => '',
            'SEAT' => 'STOEL',
            //			'comfort of'
        ],
        'es' => [
            'PASSENGER'     => 'PASAJERO',
            // 'FREQUENT FLYER' => '',
            'FLIGHT'        => 'VUELO',
            'DEPARTURE'     => 'SALIDA',
            'Boarding Pass' => 'Tarjeta de embarque',
            //			'We are pleased to invite' => '',
            'SEAT' => 'ASIENTO',
            //			'comfort of'
        ],
        'th' => [
            'PASSENGER'      => 'ผู้โดยสาร',
            'FREQUENT FLYER' => 'โปรแกรมสะสมไมล์',
            'FLIGHT'         => 'เที่ยวบิน',
            'DEPARTURE'      => 'ขาออก',
            'Boarding Pass'  => 'บัตรผ่านขึ้นเครื่อง',
            //			'We are pleased to invite' => '',
            'SEAT' => 'ที่นั่ง',
            //			'comfort of'
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detects as $lang => $detect) {
            if ($this->http->XPath->query("//node()[{$this->contains($detect)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $lang => $detect) {
            foreach ($detect as $dt) {
                if (stripos($body, $dt) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }
        $itineraries = [];
        $this->parseHtml($itineraries);

        return [
            'emailType'  => 'MobileBoardingPassHtml' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $itineraries],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        if (preg_match("#[A-Z\d]{2}\d{1,4}\/\d{2}[A-Z]{3}\d{2}\/#i", $headers['subject'])) {
            // KA618/14Sep17/029C/HAN
            return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'Boarding Pass') === false || stripos($body, 'cathaypacific.com') === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            foreach ($detect as $dt) {
                if (stripos($body, $dt) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->traveller(preg_replace('/\s+(MR|MISS|MRS|MSTR|DR|MS)\s*$/i', '',
                $this->http->FindSingleNode("//tr[not(.//tr)][*[1][{$this->eq($this->t('PASSENGER'))}]]/following-sibling::tr[1]/td[1]")))
        ;

        // Program
        $account = $this->http->FindSingleNode("//tr[not(.//tr)][*[1][{$this->eq($this->t('FREQUENT FLYER'))}]]/following-sibling::tr[1]/td[1]");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        // Segments
        $s = $f->addSegment();

        // Airline
        $flight = $this->http->FindSingleNode("//tr[not(.//tr)][*[1][{$this->eq($this->t('FLIGHT'))}]]/following-sibling::tr[1]/td[1]");

        if (preg_match('#^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,4})\s*$#', $flight, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;
        }
        //Departure
        $s->departure()
            ->code($this->http->FindSingleNode("//tr[not(.//tr)][*[1][{$this->eq($this->t('FLIGHT'))}]]/following-sibling::tr[3]/td[1]", null, true, "#([A-Z]{3})#"))
            ->name($this->http->FindSingleNode("//tr[not(.//tr)][*[1][{$this->eq($this->t('FLIGHT'))}]]/following-sibling::tr[4]/td[1]"))
            ->date(strtotime($this->http->FindSingleNode("//tr[not(.//tr)][*[1][{$this->eq($this->t('DEPARTURE'))}]]/following-sibling::tr[1]/td[1]", null, true, "#(\d{2}[A-Z]{3}\d{2}\s+\d{2}:\d{2})#i")))
        ;

        //Arrival
        $s->arrival()
            ->code($this->http->FindSingleNode("//tr[not(.//tr)][*[1][{$this->eq($this->t('FLIGHT'))}]]/following-sibling::tr[3]/td[3]", null, true, "#([A-Z]{3})#"))
            ->name($this->http->FindSingleNode("//tr[not(.//tr)][*[1][{$this->eq($this->t('FLIGHT'))}]]/following-sibling::tr[4]/td[2]"))
            ->noDate()
        ;

        // Cabin
        $class = $this->http->FindSingleNode('(.//text()[' . $this->eq($this->t('Boarding Pass')) . '])[1]/following::text()[normalize-space(.)][1]');

        if (stripos($class, 'Connecting from ') === false) {
            $s->extra()
                ->cabin($class);
        }
        //Seats
        $seat = $this->http->FindSingleNode("//tr[not(.//tr)][*[2][{$this->eq($this->t('SEAT'))}]]/following-sibling::tr[1]/td[2]", null, true, "#([\dA-Z]+)#");

        if (!empty($seat)) {
            $s->extra()
                ->seat($seat);
        }

        $imgSrc = $this->http->FindSingleNode("//img/@src[contains(., '.cathaypacific.com/olci/qrcode?text=')]");
        $this->logger->debug('$date = ' . print_r(urldecode($imgSrc), true));

        if (!empty($imgSrc) && !empty($s->getDepCode()) && !empty($s->getArrCode())
            && preg_match("/qrcode\?text=[A-Z\d]{0,3}[A-Z \-]+\/[A-Z \-]+(?: |E)([A-Z\d]{6}) {$s->getDepCode()}{$s->getArrCode()}/", $imgSrc, $m)
        ) {
            $f->general()
                ->confirmation($m[1]);
        } else {
            $f->general()
                ->noConfirmation();
        }

        if (!empty($imgSrc)) {
            $bp = $email->add()->bpass();

            $bp
                ->setTraveller($f->getTravellers()[0][0])
                ->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber())
                ->setDepCode($s->getDepCode())
                ->setDepDate($s->getDepDate())
                ->setUrl($imgSrc);

            if (!empty($f->getConfirmationNumbers())) {
                $bp->setRecordLocator($f->getConfirmationNumbers()[0][0]);
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }
}
