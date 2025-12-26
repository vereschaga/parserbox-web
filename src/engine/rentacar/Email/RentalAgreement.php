<?php

namespace AwardWallet\Engine\rentacar\Email;

use AwardWallet\Schema\Parser\Email\Email;

class RentalAgreement extends \TAccountChecker
{
    use \PriceTools;
    public $mailFiles = "rentacar/it-5189374.eml, rentacar/it-5232887.eml";

    public $reSubject = [
        "Enterprise Rental Agreement",
    ];
    public $reBody = [
        ["REF#", "SUMMARY OF CHARGES"],
    ];

    public $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(translate(., 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'),'ENTERPRISE')]")->length > 0) {
            $body = $parser->getHTMLBody();

            foreach ($this->reBody as $re) {
                if (stripos($body, $re[0]) !== false && stripos($body, $re[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@enterprise.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->traveller($this->getField("RENTER"))
            ->confirmation($this->getField("REF#", 2));

        $pickUpDatetime = $this->getField("DATE & TIME OUT");
        $DropOffDatetime = $this->getField("DATE & TIME IN");

        $dateAll = $this->DateFormatForRental($pickUpDatetime, $DropOffDatetime);

        $r->pickup()
            ->date(strtotime($dateAll[0]));

        $r->dropoff()
            ->date(strtotime($dateAll[1]));

        $node = $this->getField("RENTAL AGREEMENT", 1, null, true);
        $this->logger->warning($node);

        if (preg_match("#(.+?)\s*(\(\d+.+)#", $node, $m)) {
            $r->pickup()
                ->location($m[1])
                ->phone($m[2]);
            $r->dropoff()
                ->location($m[1])
                ->phone($m[2]);
        } else {
            $node = $this->getField("RENTAL AGREEMENT", 1, null, true);
            $r->pickup()
                ->phone($node);
            $r->dropoff()
                ->phone($node);
            $node = $this->getField("RENTAL AGREEMENT", 2, null, true);
            $r->pickup()
                ->location($node);
            $r->dropoff()
                ->location($node);
        }
        $node = $this->getField("VIN", 1, null, true);

        if (preg_match("#VEH \#\d\s*(.+)#", $node, $m)) {
            $r->car()
                ->model($m[1]);
        }

        $node = $this->getField("Total Charges");
        $r->price()
            ->total($this->cost($node))
            ->currency($this->currency($node));

        return true;
    }

    private function getField($field, $step = 1, $root = null, $back = false)
    {
        if ($back) {
            $xpath = ".//text()[starts-with(normalize-space(.),'{$field}')]/preceding::text()[string-length(normalize-space(.))>3][{$step}]";
        } else {
            $xpath = ".//text()[starts-with(normalize-space(.),'{$field}')]/following::text()[string-length(normalize-space(.))>3][{$step}]";
        }

        return $this->http->FindSingleNode($xpath, $root);
    }

    private function DateFormatForRental($dateIN, $dateOut)
    {
        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateIN, $m)) {
            $dateIN = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateIN));
        }

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateOut, $m)) {
            $dateOut = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateOut));
        }

        $this->logger->notice('$dateIN-' . $dateIN);
        $this->logger->notice('$dateOut-' . $dateOut);

        if ($this->identifyDateFormat($dateIN, $dateOut) === 1) {
            $this->logger->notice('11111');
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)\s+([\d\:]+\s+A?P?M)$#", "$2.$1.$3, $4", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)\s+([\d\:]+\s+A?P?M)$#", "$2.$1.$3, $4", $dateOut);
        } else {
            $this->logger->notice('2222');
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)\s+([\d\:]+\s+A?P?M)$#", "$1.$2.$3, $4", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)\s+([\d\:]+\s+A?P?M)$#", "$1.$2.$3, $4", $dateOut);
        }

        return [$dateIN, $dateOut];
    }

    private function identifyDateFormat($date1, $date2)
    {
//    define("DATE_MONTH_FIRST", "1");
//    define("DATE_DAY_FIRST", "0");
//    define("DATE_UNKNOWN_FORMAT", "-1");
        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1, $m) && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return 0;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return 1;
            } else {
                //try to guess format
                $diff = [];

                foreach (['$3-$2-$1', '$3-$1-$2'] as $i => $format) {
                    $tempdate1 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date1);
                    $tempdate2 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date2);

                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                $min = min($diff);

                return array_flip($diff)[$min];
            }
        }

        return -1;
    }
}
