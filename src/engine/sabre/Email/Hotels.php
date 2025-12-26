<?php

namespace AwardWallet\Engine\sabre\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotels extends \TAccountChecker
{
    public $mailFiles = "sabre/it-223986474.eml";
    public $subjects = [
        'Trip',
    ];

    public $date;
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@sabre.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Sabre'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel Option'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel policies'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Room Details'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sabre\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Hotel Option ')]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotel Option 1')]/preceding::p[contains(@align, 'right')]", null, true, "/^([A-Z\d]{6,})$/"));

            $cancellation = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[2]/descendant::text()[contains(normalize-space(),'Cancellation')][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }

            $priceText = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[last()]", $root);

            if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)$/", $priceText, $m)) {
                $h->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }

            $dateInOut = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::td[1]", $root);

            if (preg_match("/^(\w+\,\s*\w+\s*\d+)[\s\-]+(\w+\,\s*\w+\s*\d+)$/", $dateInOut, $m)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m[1]))
                    ->checkOut($this->normalizeDate($m[2]));
            }

            $hotelInfo = implode("\n", $this->http->FindNodes("./ancestor::tr[1]/following::tr[2]/descendant::tr[1]/descendant::table[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<hotelName>.+)\n+\S\n*(?<rooms>\d+)\s*Room\(s\)\n*\S*\n*(?<guests>\d+)\s*Guest\(s\)\n*(?<address>.*)\n+Phone\:\n*(?<phone>[\d\-]+)\n*Fax number\:\n(?<fax>[\d\-]+)/u", $hotelInfo, $m)) {
                $h->hotel()
                    ->name($m['hotelName'])
                    ->address($m['address'])
                    ->phone($m['phone'])
                    ->fax($m['fax']);

                $h->booked()
                    ->rooms($m['rooms'])
                    ->guests($m['guests']);
            }

            $this->detectDeadLine($h);

            $room = $h->addRoom();
            $room->setDescription($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[2]/descendant::text()[contains(normalize-space(),'Room Details')][1]/following::text()[normalize-space()][1]", $root));

            $rate = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Nightly Rate'))}\s*([\d\.\,]+)/");

            if (!empty($rate)) {
                $room->setRate($rate . ' ' . $h->getPrice()->getCurrencyCode() . ' / night');
            } elseif ($this->http->XPath->query("./ancestor::tr[1]/following::tr[2]/descendant::text()[starts-with(normalize-space(), 'Rate Breakdown')]", $root)->length > 0) {
                $rates = [];
                $nodesRoom = $this->http->XPath->query("./ancestor::tr[1]/following::tr[2]/descendant::text()[starts-with(normalize-space(), 'Rate Breakdown')]/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'Total Price'))]", $root);

                foreach ($nodesRoom as $rootRoom) {
                    $rates[] = implode(' - ', $this->http->FindNodes("./descendant::text()[normalize-space()]", $rootRoom)) . ' ' . $h->getPrice()->getCurrencyCode() . ' / night';
                }

                $room->setRate(implode("; ", $rates));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->ParseHotel($email);

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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            //Fri, Mar 10
            "#^(\w+)\,\s*(\w+)\s*(\d+)$#i",
        ];
        $out = [
            "$1, $3 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
            preg_match('/CANCELLATION DEADLINE: (?<prior>\d+\s*HOURS?) PRIOR TO ARRIVAL/', $cancellationText, $m)
            || preg_match('/CANCELLATION DEADLINE: (?<prior>\d+\s*DAYS?) PRIOR TO ARRIVAL/', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior']);
        }
    }
}
