<?php
namespace AwardWallet\Engine\vio\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelCancellation extends \TAccountChecker
{
	public $mailFiles = "vio/it-804896350.eml";
    public $subjects = [
        'Vio.com Cancelled Booking at',
        'Vio.com Booking canceled. Your stay at'
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Your booking was cancelled' => ['You canceled this booking', 'Your booking was cancelled'],
            'Booking overview' => 'Booking overview'
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.vio.com') !== false) {
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
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['vio.com'])}]")->length === 0
            && $this->http->XPath->query("//img/@src[{$this->contains(['vio_logo.png'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your booking was cancelled']) && $this->http->XPath->query("//*[{$this->contains($dict['Your booking was cancelled'])}]")->length > 0
                && !empty($dict['Booking overview']) && $this->http->XPath->query("//*[{$this->contains($dict['Booking overview'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.vio\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->HotelCancellation($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        return $email;
    }

    public function HotelCancellation(Email $email)
    {
        $h = $email->add()->hotel();
        
        $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID'))}]/following::tr[1]", null, true, "/^([A-Z\d\-]+)$/");
        if ($confNumber !== null){
            $h->general()
                ->confirmation($confNumber);
        } else {
            $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID'))}]/preceding::tr[1]", null, true, "/^([A-Z\d\-]+)$/");
            if ($confNumber !== null) {
                $h->general()
                    ->confirmation($confNumber);
            }
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Your booking was cancelled'))}]")->length > 0) {
            $h->general()
                ->cancelled();
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking overview'))}]/following::tr[{$this->starts($this->t('Reserved for'))}][1]", null, false, "/^Reserved\s*for\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*(?:Room|$)/u");
        $h->addTraveller($traveller, true);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Get directions'))}]/preceding::a[1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Get directions'))}]/preceding::tr[1]"));

        $checkinInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/following::tr[1]");

        if (preg_match("/^(?:\w+\s*\,\s*)?(?<date>\w+\s*\d+\,\s*\d{4})\s*from\s*(?<time>[\d\:]+)$/", $checkinInfo, $m)){
            $h->booked()
                ->checkIn(strtotime($m['date'] . $m['time']));
        }

        $checkoutInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/following::tr[1]");

        if (preg_match("/^(?:\w+\s*\,\s*)?(?<date>\w+\s*\d+\,\s*\d{4})\s*(?:before|from)\s*(?<time>[\d\:]+)$/", $checkoutInfo, $m)){
            $h->booked()
                ->checkOut(strtotime($m['date'] . $m['time']));
        }

        $roomInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking overview'))}]/following::div[{$this->contains($this->t('Reserved for'))}][1]/descendant::text()[normalize-space()][2][not({$this->starts($this->t('Reserved for'))})][not({$this->contains($this->t('night'))})]");

        if ($roomInfo !== null){
            $r = $h->addRoom();

            $r->setType($roomInfo);

            $roomRate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price details'))}]/following::div[1]/descendant::div[2]", null, true, '/^(\D{1,3}\s*[\d\.\,\`]+)\s*x\s*\d*\s*nights?\s*x\s*\d*\s*rooms?$/');

            if ($roomRate !== null) {
                $r->setRate($roomRate . '/night');
            }
        }

        $roomsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking overview'))}]/following::div[1]", null, false, "/(\d+)\s*rooms?/");
        if ($roomsCount !== null){
            $h->booked()
                ->rooms($roomsCount);
        }

        $phoneInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Call property'))}]/ancestor::div[3][1]",null, false, '/^([\d\s\+\(\)\-]+)\s*Call\s*property$/');
        if ($phoneInfo !== null){
            $h->hotel()
                ->phone($phoneInfo);
        }

        $guestInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking overview'))}]/following::tr[{$this->starts('Guests')}][1]", null, false, '/(\d+)\s*adults?\s*/');
        if ($guestInfo !== null){
            $h->booked()
                ->guests($guestInfo);
        }

        $kidsInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking overview'))}]/following::tr[{$this->starts('Guests')}][1]", null, false, '/adults?\,?\s*(\d+)\s*child/');
        if ($kidsInfo !== null){
            $h->booked()
                ->kids($kidsInfo);
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

        return '(' . implode(" or ", array_map(function ($s) {
                return "normalize-space(.)=\"{$s}\"";
            }, $field)) . ')';
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }
        return self::$dictionary[$this->lang][$word];
    }
}