<?php

namespace AwardWallet\Engine\thsr\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainCancellation extends \TAccountChecker
{
	public $mailFiles = "thsr/it-772843432.eml, thsr/it-784669002.eml";
    public $subjects = [
        'Taiwan High Speed Rail Notice of Online Booking Cancellation',
        '台灣高鐵T Express行程取消通知/Notice of T Express Booking Cancellation',
    ];

    public $lang = '';

    public $detectLang = [
        'zh' => ['台灣高速鐵路股份有限公司'],
        'en' => ['Taiwan High Speed Rail Corporation'],
    ];

    public static $dictionary = [
        'zh' => [
            'Taiwan High Speed Rail Corporation' => '台灣高速鐵路股份有限公司Taiwan High Speed Rail Corporation',
            'cancellationStatus' => 'has been cancelled via T Express',
            'Total Amount' => '總票價 Total Amount',
            'Status' => ['交易狀態 Status', '交易狀態Status'],
            'Passenger(s) Information' => '乘客資訊 Passenger',
            'Outbound Date' => '去程日期 Outbound Date',
            'Outbound Train' => '去程車次 Outbound Train',
        ],
        'en' => [
            'Taiwan High Speed Rail Corporation' => 'Taiwan High Speed Rail Corporation',
            'cancellationStatus' => 'has been cancelled on the THSR Online Booking system',
            'Reservation Number' => 'Reservation Number',
            'Total Amount' => 'Total Amount',
            'Status' => 'Status',
            'Passenger(s) Information' => 'Passenger',
            'Outbound Date' => 'Outbound Date',
            'Outbound Train' => 'Outbound Train',
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
            && $this->http->XPath->query("//*[{$this->contains($this->t('cancellationStatus'))}]")->length > 0
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
        $this->date = strtotime($parser->getHeader('date'));

        $this->Train($email, $text);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Train(Email $email, $text)
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->re("/{$this->t('Reservation No')}\s*\.\s*(\d{8})/s", $text), 'Reservation Number');

        $cancellationStatus = $this->re("/({$this->t('cancellationStatus')})/", $text);
        if(!empty($cancellationStatus)){
            $t->general()
                ->cancelled();
        }

        $priceInfo = $this->re("/\n+(\D{1,3}\s*[\d\.\,\`]+)\s*[\(\（]/", $text);

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)) {
            $t->price()
                ->cost(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);
        }

        $routeInfo = $this->re("/{$this->t('Your booking')}\s*[\(\（]+.+\s*(Train.*)\s*[\)\）]+\s*{$this->t('cancellationStatus')}/", $text);
        if (!empty($routeInfo)){
            $s = $t->addSegment();

            if (preg_match("/^Train\s*\#(?<trainNum>\d+)\s*from(?<depCity>.+)\s*to(?<arrCity>.+)\s*on\s*(?<depDate>\d+\s*\/\s*\d+)\s*$/", $routeInfo, $m)){
                $s->extra()
                    ->number($m['trainNum']);

                $s->departure()
                    ->geoTip('tw')
                    ->date($this->normalizeDate($m['depDate']))
                    ->name($m['depCity']);

                $s->arrival()
                    ->geoTip('tw')
                    ->noDate()
                    ->name($m['arrCity']);
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
        $year = date("Y", $this->date);

        $in = [
            // 10 / 29
            "/^(\d+)\s*\/\s*(\d+)$/",

        ];
        $out = [
            "$1.$2.$year",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }
}
