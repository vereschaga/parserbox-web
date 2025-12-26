<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class CarRentalConfirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "@loyaltytravelrewards.com";
    public $reBody = [
        'en' => ['Car Details', 'Product Type'],
    ];
    public $reSubject = [
        'Hilton Honors Car Rental Confirmation',
    ];
    public $lang = '';
    public $date;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->AssignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'loyaltytravelrewards.com/images/')] | //a[contains(@href,'hilton.com') or contains(@href,'hhonors')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\CarRental $it */
        $it = ['Kind' => 'L'];
        $it['Number'] = $this->nextTd($this->t('Reservation Number'));
        $it['ReservationDate'] = $this->normalizeDate($this->nextTd($this->t('Order Date')));

        if (!empty($it['ReservationDate'])) {
            $this->date = $it['ReservationDate'];
        }
        $it['CarType'] = $this->nextTd($this->t('Car Type'));
        $it['CarModel'] = $this->nextTd($this->t('Make/Model'));
        $it['CarImageUrl'] = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Car Type'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][2]//img[starts-with(normalize-space(@src),'http')])[1]/@src");

        $xpath = "//text()[{$this->starts($this->t('Pick-up Information'))}]/ancestor::table[1]";

        if (($node = $this->http->XPath->query($xpath))->length > 0) {
            $root = $node->item(0);
            $it['PickupLocation'] = $this->nextTd($this->t('Address'), $root);

            if (empty($it['PickupLocation'])) {
                $it['PickupLocation'] = $this->nextTd($this->t('Location'), $root);
            }
            $it['PickupPhone'] = $this->nextTd($this->t('Phone'), $root);
            $it['PickupFax'] = $this->nextTd($this->t('Fax'), $root);
            $it['PickupDatetime'] = $this->normalizeDate($this->nextTd($this->t('Date & Time'), $root));
        }

        $xpath = "//text()[{$this->starts($this->t('Drop-off Information'))}]/ancestor::table[1]";

        if (($node = $this->http->XPath->query($xpath))->length > 0) {
            $root = $node->item(0);
            $it['DropoffLocation'] = $this->nextTd($this->t('Address'), $root);

            if (empty($it['DropoffLocation'])) {
                $it['DropoffLocation'] = $this->nextTd($this->t('Location'), $root);
            }
            $it['DropoffPhone'] = $this->nextTd($this->t('Phone'), $root);
            $it['DropoffFax'] = $this->nextTd($this->t('Fax'), $root);
            $it['DropoffDatetime'] = $this->normalizeDate($this->nextTd($this->t('Date & Time'), $root));
        }

        $xpath = "//text()[{$this->starts($this->t('Driver\'s Name'))}]/ancestor::table[1]";

        if (($node = $this->http->XPath->query($xpath))->length > 0) {
            $root = $node->item(0);
            $it['RenterName'] = $this->nextTd($this->t('First Name'),
                    $root) . ' ' . $this->nextTd($this->t('Last Name'), $root);
        }

        $xpath = "//text()[{$this->starts($this->t('Car Rental Cost'))}]/ancestor::table[1]";

        if (($node = $this->http->XPath->query($xpath))->length > 0) {
            $root = $node->item(0);
            $sum = 0;
            $node = $this->nextTd($this->t('Day Enterprise Rental'), $root, 'contains');

            if (preg_match("#(.*?)([\d\.\,]+\s+Points)(.*)#i", $node, $m)) {
                $tot = $this->getTotalCurrency($m[2]);

                if (!empty($tot['Total'])) {
                    $sum += $tot['Total'];
                }
                $tot = $this->getTotalCurrency($m[1] . $m[3]);

                if (!empty($tot['Total'])) {
                    $it['BaseFare'] = $tot['Total'];
                    $it['Currency'] = $tot['Currency'];
                }
            }
            $node = $this->nextTd($this->t('Applied'), $root, 'contains');

            if (preg_match("#(.*?)([\d\.\,]+\s+Points)(.*)#i", $node, $m)) {
                $tot = $this->getTotalCurrency($m[2]);

                if (!empty($tot['Total'])) {
                    $sum += $tot['Total'];
                }
                $tot = $this->getTotalCurrency($m[1] . $m[3]);

                if (!empty($tot['Total'])) {
                    $it['TotalTaxAmount'] = $tot['Total'];
                    $it['Currency'] = $tot['Currency'];
                }
            }
            $tot = $this->getTotalCurrency($this->nextTd($this->t('Charge'), $root, 'contains'));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            if (!empty($sum)) {
                $it['SpentAwards'] = $sum;
            }
        }

        return [$it];
    }

    private function nextTd($filed, $root = null, $type = 'equal')
    {
        switch ($type) {
            case 'contains':
                return $this->http->FindSingleNode(".//text()[{$this->contains($filed)}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                    $root);

                break;

            case 'starts':
                return $this->http->FindSingleNode(".//text()[{$this->starts($filed)}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                    $root);

                break;

            default:
                return $this->http->FindSingleNode(".//text()[{$this->eq($filed)}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                    $root);
        }
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Thu, Apr 5 at 11:00AM
            '#^(\w+),\s+(\w+)\s+(\d+)\s+at\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
            //03/29/2018
            '#^(\d+)\/(\d+)\/(\d{4})$#',
        ];
        $out = [
            '$3 $2 ' . $year . ', $4',
            '$3-$1-$2',
        ];
        $outWeek = [
            '$1',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
