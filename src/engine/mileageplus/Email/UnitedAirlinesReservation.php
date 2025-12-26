<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UnitedAirlinesReservation extends \TAccountCheckerExtended
{
    public $mailFiles = "mileageplus/it-2286171.eml, mileageplus/it-2286173.eml, mileageplus/it-27837937.eml, mileageplus/it-27849867.eml, mileageplus/it-28344498.eml, mileageplus/it-6721908.eml";

    public $reFrom = "unitedairlines@united.com";
    public $reSubject = [
        'united.com reservation for',
    ];

    public $reBody = [
        'en' => ['Thank you for choosing United Airlines'],
    ];

    public $lang = 'en';
    public $date;
    public $textSubject;
    public static $dict = [
        "en" => [],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $reBody) {
            foreach ($reBody as $re) {
                if (strpos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()->confirmation($this->http->FindSingleNode("//text()[normalize-space(.) = 'Confirmation Number:']/following::text()[normalize-space()!=''][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#"), 'United Confirmation Number');

        $f->general()->travellers($this->http->FindNodes("//text()[normalize-space(.) = 'Traveler Details']/ancestor::table[1]/following::table[1]//text()[normalize-space() = 'Seats:']/preceding::text()[normalize-space()!=''][1]"), true);

        $xpath = "//tr[count(./descendant::td)=4 and normalize-space(./descendant::td[1])='' and not(contains(.,'Confirmation Number:'))]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Date
            $date = $this->normalizeDate($this->http->FindSingleNode("./td[2]", $root));

            if (!empty($date)) {
                $s->departure()->noDate();
                $s->departure()->day($date);
                $s->arrival()->noDate();
            }

            $route = implode("\n", $this->http->FindNodes("./td[4]//text()[normalize-space()!='']", $root));

            if (preg_match("#(?<depname1>.+?)\((?<depcode>[A-Z]{3})(?:\s*-\s*(?<depname2>.+?))?\)\s+to\s+(?<arrname1>.+?)\((?<arrcode>[A-Z]{3})(?:\s*-\s*(?<arrname2>.+?))?\)\s*(?:Connecting[\s\S]+?)?$#s",
                $route, $m)) {
                if ($node = $this->http->FindSingleNode("./descendant::text()[starts-with(.,'Connecting in')]", $root,
                    false, "/\(([A-Z]{3})\b.*\)/")
                ) {
                    $s->airline()
//                        ->noName()
                        ->name('UA')
                        ->noNumber();
                    $s->departure()
                        ->code($m['depcode'])
                        ->name((!empty($m['depname2']) ? $m['depname2'] . ', ' : '') . $m['depname1']);
                    $s->arrival()->code($node);

                    $seats = array_filter($this->http->FindNodes("//text()[normalize-space()='Seats:']/following::td[1]/descendant::text()[starts-with(normalize-space(),'{$s->getDepCode()}') and contains(.,'{$s->getArrCode()}')]",
                        null, "/:\s*(\d+[A-Z])\s*$/"));

                    if (count($seats) > 0) {
                        $s->extra()->seats($seats);
                    }
                    $s = $f->addSegment();
                    $s->departure()->code($node);

                    if (!empty($date)) {
                        $s->departure()->noDate();
                        $s->departure()->day($date);
                        $s->arrival()->noDate();
                    }
                    $s->arrival()
                        ->code($m['arrcode'])
                        ->name((!empty($m['arrname2']) ? $m['arrname2'] . ', ' : '') . $m['arrname1']);
                    $seats = array_filter($this->http->FindNodes("//text()[normalize-space()='Seats:']/following::td[1]/descendant::text()[starts-with(normalize-space(),'{$s->getDepCode()}') and contains(.,'{$s->getArrCode()}')]",
                        null, "/:\s*(\d+[A-Z])\s*$/"));

                    if (count($seats) > 0) {
                        $s->extra()->seats($seats);
                    }
                } else {
                    $s->departure()
                        ->code($m['depcode'])
                        ->name((!empty($m['depname2']) ? $m['depname2'] . ', ' : '') . $m['depname1']);
                    $s->arrival()
                        ->code($m['arrcode'])
                        ->name((!empty($m['arrname2']) ? $m['arrname2'] . ', ' : '') . $m['arrname1']);
                }
                $s->airline()
                    ->name('UA')
//                    ->noName()
                    ->noNumber();
            }
        }

        return $email;
    }

    private function normalizeDate($str)
    {
        //		 $this->http->log($str);
        $str = str_replace('.', '', $str);
        $in = [
            "#^\s*(\d{1,2}:\d{1,2}(?:\s*[ap]m)?)(?:\s*\+\d+[ ]*\w+)?\s+[^\d\s\.,]+,\s*([^\d\s\.\,]+)\s+(\d{1,2}),\s+(\d{4})\s*$#i", //4:10 pm  Thu, Nov 22, 2018
            "#^\s*(\d{1,2}:\d{1,2}(?:\s*[ap]m)?)(?:\s*\+\d+[ ]*\w+)?\s+[^\d\s\.,]+,\s*(\d{1,2})\s+([^\d\s\,\.]+),\s+(\d{4})\s*$#i", //4:10 pm  Thu, 22 Nov, 2018
            "#^\s*[^\d\s]+,\s*([^\d\s\.\,]+)[.]?\s+(\d{1,2})\s*,\s*(\d{4})\s*$#i", //Wed., Sep. 9, 2015
            "#^\s*[^\d\s]+,\s*(\d{1,2})\s*([^\d\s\.\,]+)[.]?\s*,\s*(\d{4})\s*$#i", //Fri., 5 Apr., 2019
        ];
        $out = [
            "$3 $2 $4 $1",
            "$2 $3 $4 $1",
            "$2 $1 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
