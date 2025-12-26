<?php

namespace AwardWallet\Engine\hotwire\Email;

use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Email\Email;

class CarBookingConfirmation2017 extends \TAccountChecker
{
    public $mailFiles = "hotwire/it-48954439.eml, hotwire/it-49323502.eml, hotwire/it-6785146.eml, hotwire/it-7066133.eml, hotwire/it-7133348.eml, hotwire/it-76519130.eml";
    public $reFrom = "@hotwire.com";
    public $reSubject = [
        "en" => "Your car reservation in",
    ];
    public static $dictionary = [
        "en" => [
            "Your Hotwire confirmation number is" => [
                "Your Hotwire confirmation number is",
                "Your Hotwire itinerary number is",
                "Hotwire itinerary #",
            ],
            "Pick up"        => ["Pick up", "Pick-up"],
            "Drop off"       => ["Drop off", "Drop-off"],
            "Driver name"    => ["Driver name", "Driver Information"],
            "Trip total"     => ["Estimated total", "Total"],
            "NO-Trip total"  => ["Estimated subtotal (", "Subtotal ("],
            "Taxes and fees" => ["Taxes and fees", "Estimated taxes and fees", "Estimated Tax Amount", "Tax Amount"],
        ],
    ];

    public $lang = "en";
    private $reBody = 'hotwire';
    private $reBody2 = [
        "en" => [
            "Your Hotwire confirmation number is",
            "Your Hotwire itinerary number is",
            "Your reservation is confirmed",
            "Hotwire itinerary #",
        ],
    ];
    private $subject;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])
            || stripos($headers["from"], $this->reFrom) === false
        ) {
            return false;
        }
        foreach ($this->reSubject as $rSubject) {
            if (stripos($headers["subject"], $rSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query("//node()[{$this->contains($re)}]")->length > 0) {
                return $this->assignLang();
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->subject = $parser->getSubject();
        $this->parseHtml($email);

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
        $types = 2; // info_1 | info_2
        $cnt = $types * count(self::$dictionary);

        return $cnt;
    }

    private function parseHtml(Email $email)
    {
        $r = $email->add()->rental();

        $otaConfNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your Hotwire confirmation number is'))}]/ancestor::tr[1]",
            null, true, "/{$this->opt($this->t('Your Hotwire confirmation number is'))}\s*(\d{5,})/");

        if (!empty($otaConfNo)) {
            $r->ota()->confirmation($otaConfNo, 'Hotwire number');
        }
        $otaPhone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('(24/7, toll-free)'))}]/ancestor::*[1]",
            null, true, "/^([\d\-\+\(\) ]{5,})\s*{$this->opt($this->t('(24/7, toll-free)'))}/");

        if (!empty($otaPhone)) {
            $r->ota()->phone($otaPhone, 'Hotwire ' . $this->t('(24/7, toll-free)'));
        }
        $r->general()
            ->traveller($this->re("/(.+?)(?:\.|$)/", $this->nextText($this->t("Driver name"))), true);

        if (preg_match("/Your car reservation in .+? is (confirmed)$/iu", $this->subject, $m)) {
            $r->general()->status($m[1]);
        }

        $pickUpXPath = "//text()[{$this->eq($this->t("Pick up"))}]/ancestor::td[1]/child::*[string-length(normalize-space())>2]";

        if ($this->http->XPath->query($pickUpXPath . "[{$this->contains($this->t("Drop off"))}]")->length === 0) {
            if (!empty($this->http->FindSingleNode('//text()[normalize-space(.)="Pick up" or normalize-space(.)="Pick-up"]/ancestor::td[1]/child::*[string-length(normalize-space())>2][3]', null, true, '/(\d{2}:\d{2}[A-Z]{2})/'))) {
                $this->parseInfo_3($r);
            } else {
                $this->parseInfo_1($r);
            }
        } else {
            $this->parseInfo_2($r);
        }

        $company = $r->getCompany();

        if (!empty($this->re('/(\d{2}:\d{2}[A-Z]{2})/', $company))) {
            $company = $this->http->FindSingleNode("//text()[normalize-space(.)='Pick up' or normalize-space(.)='Pick-up']/preceding::tr[1][not(contains(.,'Hotwire'))][normalize-space(.)]", null, true, "/(.+)\s+confirmation/");
        }
        $confNo = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(),'{$company}') and ({$this->contains($this->t('confirmation'))})])[1]/following::text()[normalize-space()!=''][1]");
        $r->general()->confirmation($confNo, $company . ' confirmation');
        $phone = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(),'{$company}') and ({$this->contains($this->t('Phone'))})])[1]/following::text()[normalize-space()!=''][1]",
            null, false, "/^[\d\-\+\(\) ]{5,}$/");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Contact phone'))}])[1]/following::text()[normalize-space()!=''][1]",
                null, false, "/^[\d\-\+\(\) ]{5,}$/");
        }

        if (!empty($phone)) {
            $r->program()->phone($phone, $company . ' phone');
        }

        $carType = $this->http->FindSingleNode("//img[contains(@src,'hotwire') and contains(@src,'cartypes')]/ancestor::*[self::td or self::th][1]/following-sibling::*[string-length(.)>3][1]/descendant::text()[normalize-space()!=''][1]");

        $carType .= '. ' . $this->http->FindSingleNode("//*[contains(.,'Your reserved car')]/following::text()[contains(.,'" . $this->t("Features") . "')][1]/ancestor::th[1]");

        if ($carType == '. ') {
            $carType = $this->http->FindSingleNode("//img[contains(@src,'hotwire') and contains(@src,'cartypes')]/ancestor::*[self::td or self::th][1]/following-sibling::*[string-length(.)>3][2]/descendant::text()[normalize-space()!=''][1]");
        }

        if (empty($carType)) {
            $carType = $this->http->FindSingleNode("//text()[normalize-space()='Your reserved car']/following::text()[string-length()>1][1]");
        }

        $model = $this->http->FindSingleNode("//img[contains(@src,'hotwire') and contains(@src,'cartypes')]/ancestor::*[self::td or self::th][1]/following-sibling::*[contains(.,'{$carType}')]/descendant::text()[normalize-space()!=''][2]");

        if (empty($model)) {
            $model = $this->http->FindSingleNode("//text()[normalize-space()='Your reserved car']/following::text()[string-length()>1][2]");
        }

        $r->car()
            ->model($model);

        $image = $this->http->FindSingleNode("//img[contains(@src,'hotwire.com') and contains(@src,'cartypes')]/@src");

        if (!empty($image)) {
            $r->car()
                ->image($image);
        }

        $r->car()->type($carType);

        $total = $this->nextText($this->t("Trip total"));
        $currency = $this->http->FindSingleNode("(.//text()[{$this->contains($this->t("Trip total"))}])[1]", null, true,
            "# \(([A-Z]{3})\)#");

        if (empty($total)) {
            $total = $this->nextText($this->t("NO-Trip total"));
            $currency = $this->http->FindSingleNode("(.//text()[{$this->contains($this->t("NO-Trip total"))}])[1]",
                null, true, "# \(([A-Z]{3})\)#");
        }
        $r->price()
            ->total($this->amount($this->re("#([\d\,\.]+)#", $total)))
            ->currency($currency)
            ->tax($this->amount($this->re("#([\d\,\.]+)#", $this->nextText($this->t("Taxes and fees")))));
    }

    private function parseInfo_3(Rental $r)
    {
        $this->logger->notice(__METHOD__);
        $pickUpXPath = "//text()[{$this->eq($this->t("Pick up"))}]/ancestor::td[1]/child::*[string-length(normalize-space())>2]";
        $pickUp = $this->http->FindNodes($pickUpXPath);

        $timePickup = $this->re('/(\d+:\d+[A-Z]{2})/', $pickUp[2]);

        if (!empty($timePickup)) {
            $r->pickup()
                ->date(isset($pickUp[1]) ? strtotime($this->normalizeDate($pickUp[1] . ' ' . $timePickup)) : false);
        }

        $pickUpLocationNodes = $this->http->FindNodes('//text()[normalize-space(.)="Pick up" or normalize-space(.)="Pick-up"]/ancestor::td[1]/following::tr[1]/descendant::td[contains(normalize-space(),"Map")][2]');

        if (empty($pickUpLocationNodes)) {
            $pickUpLocationNodes = $this->http->FindNodes('//text()[normalize-space(.)="Pick up" or normalize-space(.)="Pick-up"]/ancestor::td[1]/following::tr[1]/td[1]/descendant::text()[normalize-space()]');
        }

        if (count($pickUpLocationNodes) > 1) {
            $pickUpLocationNode = str_replace('\n', ' ', implode('\n', $pickUpLocationNodes));
            $company = $pickUpLocationNodes[0];
        } else {
            $pickUpLocationNode = str_replace('\n', ' ', implode('\n', $pickUpLocationNodes));
            $company = $this->http->FindSingleNode('//text()[normalize-space(.)="Pick up" or normalize-space(.)="Pick-up"]/ancestor::td[1]/following::tr[1]/descendant::td[contains(normalize-space(),"Map")][2]/descendant::text()[normalize-space()][1]');
        }

        $pickupLocation = $this->re("/{$company}\s(.+)\s+\d+[-]\d+[-]/u", $pickUpLocationNode);

        if (!empty($pickupLocation)) {
            $r->pickup()
                ->location($pickupLocation);
        }

        $pickupPhone = $this->re("/(\d+[-]\d+[-].+)\s+[|]/", $pickUpLocationNode);

        if (!empty($pickupPhone)) {
            $r->pickup()
                ->phone($pickupPhone);
        }

        $pickupHours = $this->re("/.*Hours[^:]*: (.+)/", $pickUpLocationNode);

        if (!empty($pickupHours)) {
            $r->pickup()
                ->openingHours($pickupHours);
        }

        $dropOff = $this->http->FindNodes("//text()[{$this->eq($this->t("Drop off"))}]/ancestor::td[1]/child::*[string-length(normalize-space())>2]");

        $timeDropoff = $this->re('/(\d+:\d+[A-Z]{2})/', $dropOff[2]);

        if (!empty($timeDropoff)) {
            $r->dropoff()
                ->date(isset($dropOff[1]) ? strtotime($this->normalizeDate($dropOff[1] . ' ' . $timeDropoff)) : false);
        }

        $dropOffLocationNodes = $this->http->FindNodes('//text()[normalize-space(.)="Pick up" or normalize-space(.)="Pick-up"]/ancestor::td[1]/following::tr[1]/td[2]/descendant::text()[normalize-space()]');

        if (count($dropOffLocationNodes) == 0) {
            $r->dropoff()
                ->same();
        }

        $r->extra()->company($company);
    }

    private function parseInfo_1(Rental $r)
    {
        $this->logger->notice(__METHOD__);
        $pickUpXPath = "//text()[{$this->eq($this->t("Pick up"))}]/ancestor::td[1]/child::*[string-length(normalize-space())>2]";
        $pickUp = $this->http->FindNodes($pickUpXPath);

        if (preg_match("/[\d\:]+\s*A?P?M/", $pickUp[2])) {
            $date = strtotime($this->normalizeDate($pickUp[1] . ', ' . $pickUp[2]));
            $location = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Map')]/preceding::text()[normalize-space()][string-length()>5][2]/ancestor::*[1]");

            $r->pickup()
                ->date($date)
                ->location($location);
        } else {
            $r->pickup()
                ->date(isset($pickUp[1]) ? strtotime($this->normalizeDate($pickUp[1])) : false)
                ->location($pickUp[2] ?? false);
        }

        if (isset($pickUp[3]) && preg_match("#([\d\(\)\-\s\+]+)#", $pickUp[3], $m)) {
            $r->pickup()->phone(trim($m[1]));
        }

        if (isset($pickUp[4]) && preg_match("#.*hours[^:]*: (.+)#i", $pickUp[4], $m)) {
            $r->pickup()->openingHours(trim($m[1]));
        }

        $dropOff = $this->http->FindNodes("//text()[{$this->eq($this->t("Drop off"))}]/ancestor::td[1]/child::*[string-length(normalize-space())>2]");

        // it-76519130
        if (preg_match("/[\d\:]+\s*A?P?M/", $dropOff[2])) {
            $date = strtotime($this->normalizeDate($dropOff[1] . ', ' . $dropOff[2]));
            $location = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Map')]/preceding::text()[normalize-space()][string-length()>5][2]/ancestor::*[1]");

            $r->dropoff()
                ->date($date)
                ->location($location);
        } else {
            $r->dropoff()
                ->date(isset($dropOff[1]) ? strtotime($this->normalizeDate($dropOff[1])) : false)
                ->location($dropOff[2] ?? false);
        }

        if (isset($dropOff[3]) && preg_match("#([\d\(\)\-\s\+]+)#", $dropOff[3], $m)) {
            $r->dropoff()->phone(trim($m[1]));
        }

        if (isset($dropOff[4]) && preg_match("#.*hours[^:]*: (.+)#i", $dropOff[4], $m)) {
            $r->dropoff()->openingHours(trim($m[1]));
        }

        $confirmation = $this->http->FindSingleNode($pickUpXPath . "[3]/descendant::text()[normalize-space()!=''][1]");

        if (preg_match("/[\d\:]+\s*A?P?M/", $confirmation)) {
            $r->extra()->company($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Pick-up')]/preceding::text()[contains(normalize-space(), 'confirmation')][1]", null, true, "/^(\D+)\s*{$this->opt($this->t('confirmation'))}/"));
        } else {
            $r->extra()->company($confirmation);
        }
    }

    private function parseInfo_2(Rental $r)
    {
        $this->logger->notice(__METHOD__);
        $pickUpXPath = "//text()[{$this->eq($this->t("Pick up"))}]/ancestor::*[self::td or self::th][1]/child::*[string-length(normalize-space())>2]";
        $pickUp = $this->http->FindNodes($pickUpXPath);

        $date = isset($pickUp[1]) ? strtotime($this->normalizeDate($pickUp[1])) : false;
        $time = isset($pickUp[2]) ? $this->re("/(\d+:\d+[\sapm]*)$/i", $pickUp[2]) : false;
        $r->pickup()->date(strtotime($time, $date));

        $dropOffXPath = "//text()[{$this->eq($this->t("Drop off"))}]/ancestor::*[self::td or self::th][1]/child::*[string-length(normalize-space())>2]";
        $dropOff = $this->http->FindNodes($dropOffXPath);

        $date = isset($dropOff[1]) ? strtotime($this->normalizeDate($dropOff[1])) : false;
        $time = isset($dropOff[2]) ? $this->re("/(\d+:\d+[\sapm]*)$/i", $dropOff[2]) : false;
        $r->dropoff()->date(strtotime($time, $date));

        $pickUpXPath = "//text()[{$this->eq($this->t("Pick up"))}]/following::tr[not(.//tr)][1]/th[normalize-space()][1]/child::*[string-length(normalize-space())>2]";
        $company = $this->http->FindSingleNode($pickUpXPath . "[1]/descendant::text()[normalize-space()!=''][1]");
        $r->extra()->company($company);

        $pickUp = $this->http->FindNodes($pickUpXPath);
        $company = preg_quote($company, "/");
        $r->pickup()
            ->location(isset($pickUp[0]) ? preg_replace("/^{$company}\s*/", '', $pickUp[0]) : false);

        if (isset($pickUp[1]) && preg_match("#([\w()\-\s+]+)#", $pickUp[1], $m)) {
            $r->pickup()->phone(trim($m[1]));
        }

        if (isset($pickUp[2]) && preg_match("#.*hours[^:]*: (.+)#i", $pickUp[2], $m)) {
            $r->pickup()->openingHours(trim($m[1]));
        }

        $dropOffXPath = "//text()[{$this->eq($this->t("Pick up"))}]/following::tr[not(.//tr)][1]/th[normalize-space()][2]/child::*[string-length(normalize-space())>2]";
        $dropOff = $this->http->FindNodes($dropOffXPath);

        if (isset($dropOff[0])) {
            $r->dropoff()->location(preg_replace("/^{$company}\s*/", '', $dropOff[0]));
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t("Pick up"))}]/following::tr[not(.//tr)][1]/th[normalize-space()]")->length == 1) {
            $r->dropoff()->location($r->getPickUpLocation());
        }

        if (isset($dropOff[1]) && preg_match("#([\w()\-\s+]+)#", $dropOff[1], $m)) {
            $r->dropoff()->phone(trim($m[1]));
        }

        if (isset($dropOff[2]) && preg_match("#.*hours[^:]*: (.+)#i", $dropOff[2], $m)) {
            $r->dropoff()->openingHours(trim($m[1]));
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Pick up"], $words["Drop off"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words["Pick up"])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words["Drop off"])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*([^\d\s]+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+[AP]M)$#", // Mar 18, 2017, 4:30PM
            "#^\s*([^\d\s]+)\s+(\d+),\s+(\d{4})$#", // Feb 17, 2017
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->contains($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]",
            $root);
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

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
