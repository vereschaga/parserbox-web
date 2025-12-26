<?php

namespace AwardWallet\Engine\redlion\Email;

class ResConfirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "Lexington@lexington.";
    public $reFromH = "Lexington";
    public $reBody = [
        'en' => ["//a[contains(@href,'redlion.com')]", 'Confirmation info'],
    ];
    public $reSubject = [
        'Reservation Confirmation',
    ];
    public $lang = '';
    public $reLang = [
        'en' => ['Reserved For', 'Guests'],
    ];
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ResConfirmation" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'red_lion_corporation')]")->length > 0 && $this->AssignLang()) {
            $flag = true;

            foreach ($this->reBody[$this->lang] as $search) {
                if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                    && !(stripos($search, '//') === false
                        && $this->http->XPath->query("//*[contains(normalize-space(.),'{$search}')]")->length > 0)
                ) {
                    $flag = false;
                }
            }

            return $flag;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFromH) !== false && isset($this->reSubject)) {
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
        $it = ['Kind' => 'R'];

        $it['ConfirmationNumber'] = $this->nextText('confirmation number');
        $it['HotelName'] = $this->http->FindSingleNode("//img[contains(@src,'red_lion_corporation') and contains(@src,'header')]/ancestor::a[1]/preceding::text()[normalize-space(.)][1]", null, true, "#(.+?)\s+Reservation#");

        $node = implode(" ", $this->http->FindNodes("//img[contains(@src,'red_lion_corporation') and contains(@src,'header')]/ancestor::a[1]/following::text()[normalize-space(.)][1]/ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space(.)]"));

        if (preg_match("#(.+?)\s+Tel\.?:\s+([\d\(\)\+\s-]+)#s", $node, $m)) {
            $it['Address'] = $m[1];
            $it['Phone'] = $m[2];
        }

        $it['RoomType'] = $this->nextText('room type');
        $it['Rooms'] = $this->nextText('rooms');

        $it['GuestNames'][] = $this->nextText('reserved for');
        $it['Guests'] = $this->re("#^\s*(\d+)#", $this->nextText('guests'));
        $it['Kids'] = $this->re("#^\s*\d+\s+Adults,?\s+(\d+)#i", $this->nextText('guests'));

        $it['CheckInDate'] = strtotime($this->nextText('check in'));
        $it['CheckOutDate'] = strtotime($this->nextText('check out'));

        $it['Cost'] = $this->re("#^\s*[A-Z]{3}\s+(\d[\d\.]+)#", $this->nextText('total before'));

        if (preg_match_all("#(\d[\d\.]+)#", $this->nextText('estimated taxes'), $m)) {
            $it['Taxes'] = array_sum(array_map("floatval", $m[1]));
        }
        $it['Total'] = $this->re("#^\s*[A-Z]{3}\s+(\d[\d\.]+)#", $this->nextText('total:'));
        $it['Currency'] = $this->re("#^\s*([A-Z]{3})#", $this->nextText('total:'));

        return [$it];
    }

    private function nextText($field)
    {
        return $this->http->FindSingleNode("//text()[starts-with(translate(normalize-space(.),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'{$field}')]/following::text()[normalize-space(.)][1]");
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
        if (isset($this->reLang)) {
            foreach ($this->reLang as $lang => $re) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$re[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$re[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
