<?php

namespace AwardWallet\Engine\thsr\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Train extends \TAccountChecker
{
	public $mailFiles = "thsr/it-782523872.eml, thsr/it-782996425.eml, thsr/it-784273015.eml";

    public $subjects = [
        'Taiwan High Speed Rail Online Booking Confirmation',
        'Taiwan High Speed Rail Online Booking Payment Confirmation',
        'Taiwan High Speed Rail Confirmation Notice for Online Booking Change',
        '台灣高鐵T Express訂票確認通知/T Express Booking Confirmation',
        '台灣高鐵T Express付款成功通知/T Express Payment Confirmation',
        '台灣高鐵T Express訂票行程異動通知/Confirmation Notice for T Express Booking Change'
    ];

    public $lang = '';

    public $detectLang = [
        'zh' => ['乘客資訊'],
        'en' => ['Passenger(s) Information'],
    ];

    public static $dictionary = [
        'zh' => [
            'Taiwan High Speed Rail Corporation' => '台灣高速鐵路股份有限公司Taiwan High Speed Rail Corporation',
            'Reservation Number' => ['訂位代號 Reservation Number', '訂位代號Reservation Number'],
            'Total Amount' => '總票價 Total Amount',
            'Status' => ['交易狀態 Status', '交易狀態Status'],
            'Passenger(s) Information' => '乘客資訊 Passenger',
            'Outbound Date' => '去程日期 Outbound Date',
            'Outbound Train' => '去程車次 Outbound Train',
            'Outbound Car' => '去程車廂 Outbound Car',
            'Outbound Seats' => '去程座位 Outbound Seats',
            'Inbound Date' => '回程日期 Inbound Date',
            'Inbound Train' => '回程車次 Inbound Train',
            'Inbound Car' => '回程車廂 Inbound Car',
            'Inbound Seats' => '回程座位 Inbound Seats',
        ],
        'en' => [
            'Taiwan High Speed Rail Corporation' => 'Taiwan High Speed Rail Corporation',
            'Reservation Number' => 'Reservation Number',
            'Total Amount' => 'Total Amount',
            'Status' => 'Status',
            'Passenger(s) Information' => 'Passenger',
            'Outbound Date' => 'Outbound Date',
            'Outbound Train' => 'Outbound Train',
            'Outbound Car' => 'Outbound Car',
            'Outbound Seats' => 'Outbound Seats',
            'Inbound Date' => 'Inbound Date',
            'Inbound Train' => 'Inbound Train',
            'Inbound Car' => 'Inbound Car',
            'Inbound Seats' => 'Inbound Seats',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@thsrc.com.tw') !== false) {
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
        if ($this->http->XPath->query("//*[{$this->contains($this->t('Taiwan High Speed Rail Corporation'))}]")->length > 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('Reservation Number'))}]")->length > 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('Trip Details'))}]")->length > 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('thsrc.com.tw'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]thsrc\.com\.tw/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->assignLang();

        $text = preg_replace('/<br\s*\/?>/', "\n", $parser->getBody());

        $this->TrainReservation($email, $text);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function TrainReservation(Email $email, $text)
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->re("/{$this->opt($this->t('Reservation Number'))}\s*[\：\:]+\s*(\d{8})\n+/", $text), 'Reservation Number');

        $t->general()
            ->status($this->re("/{$this->opt($this->t('Status'))}\s*[\：\:]+\s*(.+)\n+/", $text));

        $priceInfo = $this->re("/{$this->t('Total Amount')}\s*[\：\:]+\s*(\D{1,3}\s*[\d\.\,\`]+)\n+/", $text);

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)) {
            $t->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);
        }

        $tripDetails = $this->re("/{$this->t('Trip Details')}\n+(.+)\n+\-+\n+{$this->t('Passenger(s) Information')}/s", $text);

        $segmentNodes = preg_split("/\n+\s*.*(?={$this->t('Inbound Date')})/", $tripDetails);

        foreach ($segmentNodes as $root) {
            $s = $t->addSegment();
            $root = preg_replace("/\：/", " : ", $root);

            $trainDate = preg_replace("/\//",'.', $this->re("/(?:{$this->t('Outbound Date')}|{$this->t('Inbound Date')})\s*\:\s*(\d+[\/\.]\d+[\/\.]\d+)\n+/", $root));

            $routeInfo = $this->re("/(?:{$this->t('Outbound Train')}|{$this->t('Inbound Train')})\s*\:\s*(\d+\s*\（.+\s*\d{2}\:\d{2}\s*\-\s*.+\s*\d{2}\:\d{2}\）)\n+/", $root);

            if (preg_match("/(?<trainNumber>\d+)\s*\（(?<depCity>.+)\s*(?<depTime>\d{2}\:\d{2})\s*\-\s*(?<arrCity>.+)\s*(?<arrTime>\d{2}\:\d{2})\）/", $routeInfo, $m)){
                $s->extra()
                    ->number($m['trainNumber']);

                $s->departure()
                    ->geoTip('tw')
                    ->date($this->normalizeDate(($trainDate . ' ' . $m['depTime'])))
                    ->name($m['depCity']);

                $s->arrival()
                    ->geoTip('tw')
                    ->date($this->normalizeDate($trainDate . ' ' . $m['arrTime']))
                    ->name($m['arrCity']);
            }

            $cabinInfo = $this->re("/(?:{$this->t('Outbound Car')}|{$this->t('Inbound Car')})\s*\:\s*(.+)\n+/", $root);

            if (!empty($cabinInfo)){
                $s->extra()
                    ->cabin($cabinInfo);
            }

            $seatsInfo = $this->re("/(?:{$this->t('Outbound Seats')}|{$this->t('Inbound Seats')})\s*\:\s*(.+)$/", $root);

            $s->extra()
                ->car($this->re("/Car(\d+)\-\d+\D+$/", $seatsInfo));

            $seatsInfo = preg_split('/\,/', $seatsInfo);

            foreach ($seatsInfo as $seat){
                $s->extra()
                    ->seat($this->re("/Car\d+\-(\d+\D+)$/", $seat));
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $dBody) {
            foreach ($dBody as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            // 2024.10.29 11:11
            "/^(\d+)\.(\d+)\.(\d+)\s*([\d\:]+)$/",

        ];
        $out = [
            "$3.$2.$1 $4",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }
}
