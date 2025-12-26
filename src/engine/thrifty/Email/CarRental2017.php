<?php

namespace AwardWallet\Engine\thrifty\Email;

class CarRental2017 extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "@thrifty";
    public $reBody = [
        'en' => ['Booking status', 'Reference Number'],
    ];
    public $reSubject = [
        'Thrifty Car Rental',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "CarRental2017",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'thrifty')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
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

    private function parseEmail()
    {
        $it = ['Kind' => 'L'];
        $it['Number'] = $this->nextText('Reference Number');
        $it['Status'] = $this->nextText('Booking status');
        $it['ReservationDate'] = strtotime($this->nextText('Date of order'));
        $it['RenterName'] = $this->nextText('Full name', null, true);
        $it['PickupDatetime'] = strtotime($this->nextText('Pickup date'));
        $it['PickupLocation'] = $this->nextText('Pickup location');
        $it['DropoffDatetime'] = strtotime($this->nextText('Drop off date'));
        $it['DropoffLocation'] = $this->nextText('Drop off location');
        $it['CarModel'] = $this->nextText('Vehicle Rented', null, true);
        $node = $this->nextText('Reservation Total');

        if (preg_match("#R([\d\.]+)#", $node, $m)) {
            $it['SpentAwards'] = $m[1];
        } else {
            $tot = $this->getTotalCurrency($node);

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
        }
        $node = $this->nextText('Balance due on collection');

        if (preg_match("#R([\d\.]+)#", $node, $m)) {
            $it['EarnedAwards'] = $m[1];
        }

        return [$it];
    }

    private function nextText($text, $root = null, $after2point = false)
    {
        if ($after2point) {
            return $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$text}')]", $root, true, "#:\s*(.+)#");
        } else {
            return $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$text}')]/following::text()[normalize-space(.)][1]", $root);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
