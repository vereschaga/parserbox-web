<?php

namespace AwardWallet\Engine\skyair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "skyair/it-662832823.eml";
    public $subjects = [
        'You have generated a reservation with us!',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@skyairline.com') !== false) {
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
        return $this->http->XPath->query("//a[contains(@href, 'skyairline.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Active reservation code:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Itinerary'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]skyairline\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Active reservation code: ')]", null, true, "/{$this->opt($this->t('Active reservation code: '))}([A-Z\d]{6})/"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Name: ')]", null, true, "/{$this->opt($this->t('Name: '))}(.+)/"));

        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total:')]/ancestor::tr[1]/td[2]");

        if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Departure:') or starts-with(normalize-space(), 'Arrive:')]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $segInfo = implode("\n", $this->http->FindNodes("./ancestor::div[1]/descendant::text()[normalize-space()]", $root));

            if (!preg_match("/\d+\:\d+/", $segInfo)) {
                $segInfo = implode("\n", $this->http->FindNodes("./ancestor::div[2]/descendant::text()[normalize-space()]", $root));
            }

            $this->logger->debug($segInfo);

            if (preg_match("/^.+\:\n(?<date>\w+\s*\d+\,\s*\d{4})\n(?<depTime>\d+\:\d+)\n(?<depName>.+)\n(?<arrTime>\d+\:\d+)\n(?<arrName>.+)/", $segInfo, $m)) {
                $s->airline()
                    ->noName()
                    ->noNumber();

                $s->departure()
                    ->name($m['depName'])
                    ->date(strtotime($m['date'] . ', ' . $m['depTime']))
                    ->noCode();

                $s->arrival()
                    ->name($m['arrName'])
                    ->date(strtotime($m['date'] . ', ' . $m['arrTime']))
                    ->noCode();
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

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
}
