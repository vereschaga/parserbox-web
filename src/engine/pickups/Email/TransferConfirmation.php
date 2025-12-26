<?php

namespace AwardWallet\Engine\pickups\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TransferConfirmation extends \TAccountChecker
{
    public $mailFiles = "pickups/it-763675661.eml, pickups/it-766051971.eml, pickups/it-768721655.eml, pickups/it-779904258.eml, pickups/it-780865233.eml, pickups/it-784560481.eml";
    public $reSubjects = [
        "/(You booked transportation.+?Order)/u",
    ];

    public $date = null;
    public $lang = 'en';

    public static $dictionary = [
        'en' => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@welcomepickups.com') !== false) {
            foreach ($this->reSubjects as $reSubject) {
                if ($this->re($reSubject, $headers['subject']) !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]welcomepickups\.com$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img/@src[{$this->contains($this->t('welcome_pickups'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->contains($this->t('Get receipt'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Meeting Point'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Pickup and waiting time'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseTransfer(Email $email)
    {
        // collect reservation confirmation
        $bookingInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking сode'))}]/ancestor::td[normalize-space()][1]");

        if (preg_match("/^\s*(?<desc>{$this->opt($this->t('Booking сode'))})\:\s*(?<number>\d+)\s*$/mi", $bookingInfo, $m)) {
            $email->ota()->confirmation($m['number'], $m['desc']);
        }

        // collect transfers
        $transferNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Order #'))}]/ancestor::table[normalize-space()][1]");

        foreach ($transferNodes as $transferNode) {
            $t = $email->add()->transfer();
            $s = $t->addSegment();

            // collect order confirmation
            $orderInfo = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Order #'))}]", $transferNode);

            if (preg_match("/^\s*(?<desc>{$this->opt($this->t('Order #'))})\s*(?<number>[\w\-]+)\s*$/mi", $orderInfo, $m)) {
                $t->addConfirmationNumber($m['number'], $m['desc']);
            }

            // collect department date
            $day = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Order #'))}]/preceding::text()[normalize-space()][1]", $transferNode, true, "/^\s*\D+\,\s+\d+\s+\D{3}\s*$/");
            $time = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Order #'))}]/following::text()[normalize-space()][1]", $transferNode, true, "/^\s*[\d\:]+\s*(?:am|pm)?\s*$/");

            if (!empty($day) && !empty($time)) {
                $s->setDepDate($this->normalizeDate($day . ', ' . $time));
            }

            // collect department place
            $depPlace = $this->http->FindSingleNode("(./descendant::text()[{$this->contains($this->t('Order #'))}]/following::table[normalize-space()])[1]", $transferNode, true, "/^\s*\d+\:\d+\s*(.+?)\s*(?:{$this->opt($this->t('Flight'))}|$)/");

            if (preg_match("/^\s*(?<depName>.+?)\s*\((?<depCode>[A-Z]{3})\)\s*$/", $depPlace, $m)) {
                $s->setDepName($m['depName']);
                $s->setDepCode($m['depCode']);
            } elseif (!empty($depPlace)) {
                $s->setDepAddress($depPlace);
            }

            // collect arrival place
            $arrPlace = $this->http->FindSingleNode("(./descendant::text()[{$this->contains($this->t('Order #'))}]/following::table[normalize-space()])[2]", $transferNode);

            if (preg_match("/^\s*(?<arrName>.+?)\s*\((?<arrCode>[A-Z]{3})\)\s*$/", $arrPlace, $m)) {
                $s->setArrName($m['arrName']);
                $s->setArrCode($m['arrCode']);
            } elseif (!empty($arrPlace)) {
                $s->setArrAddress($arrPlace);
            }

            // collect notes
            $notes = $this->http->FindSingleNode("(./descendant::text()[{$this->eq($this->t('Meeting Point'))}]/following::p[normalize-space()])[1]", $transferNode);

            if (!empty($notes)) {
                $t->setNotes($notes);
            }
        }

        // go to provider's website
        $manageLink = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Manage your booking'))}])[1]/ancestor::a/@href");
        $http2 = clone $this->http;
        $http2->GetURL($manageLink);

        // get url after redirection
        $manageLink = $http2->currentUrl();

        // prepare authorization data
        $authLink = 'https://api.welcomepickups.com/v1/traveler/auth/traveler';
        $authorizationToken = $this->re("/travelers_access_token=(.+?)&/", $manageLink);
        $postData = [
            'token' => $authorizationToken,
        ];

        // make request for API authorization (for receive Bearer-token)
        $http2->PostURL($authLink, $postData);

        // prepare data for API requests
        $apiLink = 'https://api.welcomepickups.com/v1/traveler/trips/' . $this->re("/trip\/(\w+)\//", $manageLink);
        $bearerToken = $http2->getCookieByName('travelers_access_token');
        $headers = [
            'Authorization' => 'Bearer ' . $bearerToken,
        ];

        // make API request for getting main info
        $http2->GetURL($apiLink, $headers);

        // prepare data for API requests (for getting transfer info)
        $transferPrefix = 'https://api.welcomepickups.com/v1/traveler/transfers/';

        $data = json_decode($http2->Response['body'], true);

        $transferIds = [];

        if (!empty($data['data']['relationships']['transfers']['data'])) {
            $transferIds = array_map(function ($value) {
                return $value['id'];
            }, $data['data']['relationships']['transfers']['data']);
        }

        // collect transfers from provider's website
        foreach ($transferIds as $transferId) {
            // make API request for getting transfers info
            $transferLink = $transferPrefix . $transferId;
            $http2->GetURL($transferLink, $headers);
            $data = json_decode($http2->Response['body'], true);

            if (empty($data['data']['attributes'])) {
                continue;
            }

            $transferInfo = $data['data']['attributes'];

            // find parsed transfer with this confirmation number
            $t = null;

            foreach ($email->getItineraries() as $itinerary) {
                if ($itinerary->getConfirmationNumbers()[0][0] == $transferInfo['order_name_id']) {
                    $t = $itinerary;
                    $s = $itinerary->getSegments()[0];

                    break;
                }
            }

            // if no this transfer, add new transfer
            if ($t === null) {
                $t = $email->add()->transfer();
                $s = $t->addSegment();
            }

            // collect transfer info
            if (empty($t->getConfirmationNumbers()) && !empty($transferInfo['order_name_id'])) {
                $t->addConfirmationNumber($transferInfo['order_name_id'], 'Order #');
            }

            if (!empty($transferInfo['from_title'])) {
                $s->setDepName($transferInfo['from_title']);
            }

            if (!empty($transferInfo['from_address'])) {
                $s->setDepAddress($transferInfo['from_address']);
            }

            if ($depCode = $this->re("/\(([A-Z]{3})\)/", $s->getDepAddress())) {
                $s->setDepCode($depCode);
            }

            if (!empty($depDate = $this->re("/^(\d{4}\-\d+\-\d+T\d+\:\d+)\:.+$/", $transferInfo['from_datetime'] ?? ''))) {
                $s->setDepDate(strtotime($depDate));
            }

            if (!empty($transferInfo['to_title'])) {
                $s->setArrName($transferInfo['to_title']);
            }

            if (!empty($transferInfo['to_address'])) {
                $s->setArrAddress($transferInfo['to_address']);
            }

            if ($arrCode = $this->re("/\(([A-Z]{3})\)/", $s->getArrAddress())) {
                $s->setArrCode($arrCode);
            }

            if (!empty($arrDate = $this->re("/^(\d{4}\-\d+\-\d+T\d+\:\d+)\:.+$/", $transferInfo['to_datetime']))) {
                $s->setArrDate(strtotime($arrDate));
            }

            foreach ($transferInfo['passengers'] as $passenger) {
                if (!empty($passenger['full_name'])) {
                    $t->addTraveller($passenger['full_name'], true);
                }
            }

            foreach ($transferInfo['invitations'] as $invitation) {
                if (!empty($invitation['joined_traveler']['full_name'])) {
                    $t->addTraveller($invitation['joined_traveler']['full_name'], true);
                }
            }

            $notes = $transferInfo['sign_text']
                ?? $data['included'][0]['attributes']['description']
                ?? $data['included'][1]['attributes']['description']
                ?? null;

            if (!empty($notes)) {
                $notes = preg_replace("/\{\{traveler_name\}\}/", $t->getTravellers()[0][0], $notes);
                $t->setNotes($notes);
            }

            if (empty($data['included'][0]['attributes'])) {
                continue;
            }
            $carInfo = $data['included'][0]['attributes'];

            if (!empty($carInfo['vehicle_type'])) {
                $s->setCarType($carInfo['vehicle_type']);
            }

            if (!empty($carInfo['vehicle_brand']) && !empty($carInfo['vehicle_model'])) {
                $s->setCarModel($carInfo['vehicle_brand'] . ' ' . $carInfo['vehicle_model']);
            }
        }

        foreach ($email->getItineraries() as $itinerary) {
            if ($itinerary->getSegments()[0]->getArrDate() === null) {
                $itinerary->getSegments()[0]->setNoArrDate(true);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->parseTransfer($email);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str, $date = null)
    {
        $year = date("Y", $date ?? $this->date);

        $in = [
            "#^(\w+)\,\s+(\d+)\s+(\w+)\,\s+(\d+(?:\:\d+)?\s*(?:\w{2})?)$#u", // Tue, 15 Oct, 12:15
        ];
        $out = [
            "$1, $3 $2 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+(\D+)(\s+\d{4})?\,\s+(\d+(?:\:\d+)?\s*(?:\w{2})?)$#u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("/^(?<week>\w{3})\,\s+(?<date>\s+(\d+)\s+(\w+).+)/ui", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        }

        return $str;
    }
}
