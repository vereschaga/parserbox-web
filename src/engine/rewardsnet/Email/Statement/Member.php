<?php

namespace AwardWallet\Engine\rewardsnet\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Member extends \TAccountChecker
{
    public $mailFiles = "rewardsnet/statements/it-65469913.eml, rewardsnet/statements/it-65607917.eml, rewardsnet/statements/it-79987510.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectEmail = [
        "aa" => [
            'from'   => ['aa@email.rewardsnetwork.com', 'aa@rewardsnetwork.com'],
            'login2' => 'https://www.aadvantagedining.com/',
        ],
        "alaskaair" => [
            'from'   => ['mileageplan@email.rewardsnetwork.com', 'mileageplan@rewardsnetwork.com'],
            'login2' => 'https://mileageplan.rewardsnetwork.com/',
        ],
        "delta" => [
            'from'   => ['skymiles@email.rewardsnetwork.com', 'skymiles@rewardsnetwork.com'],
            'login2' => 'https://skymiles.rewardsnetwork.com/',
        ],
        "harrah" => [
            'from' => ['caesarsrewardsdining@email.rewardsnetwork.com', 'caesarsrewardsdining@rewardsnetwork.com'],
        ],
        "hhonors" => [
            'from'   => ['hiltonhonorsdining@email.rewardsnetwork.com', 'hiltonhonorsdining@rewardsnetwork.com'],
            'login2' => 'https://www.hiltonhonorsdining.com/',
        ],
        "ichotelsgroup" => [
            'from'   => ['ihgrewardsclubdining@email.rewardsnetwork.com', 'ihgrewardsclubdining@rewardsnetwork.com'],
            'login2' => 'https://ihgrewardsclubdining.rewardsnetwork.com/',
        ],
        "jetblue" => [
            'from'   => ['truebluedining@email.rewardsnetwork.com', 'truebluedining@rewardsnetwork.com'],
            'login2' => 'https://truebluedining.com/',
        ],
        "marriott" => [
            'from'   => ['MarriottBonvoyEatAroundTown@email.rewardsnetwork.com', 'MarriottBonvoyEatAroundTown@rewardsnetwork.com'],
            'login2' => 'https://eataroundtown.marriott.com/',
        ],
        "mileageplus" => [
            'from'   => ['mpdining@email.rewardsnetwork.com', 'mpdining@rewardsnetwork.com'],
            'login2' => 'https://mpdining.rewardsnetwork.com/',
        ],
        "rapidrewards" => [
            'from'   => ['rapidrewards@email.rewardsnetwork.com', 'rapidrewards@rewardsnetwork.com'],
            'login2' => 'https://www.rapidrewardsdining.com/',
        ],
        "spirit" => [
            'from'   => ['freespiritdining@email.rewardsnetwork.com', 'freespiritdining@rewardsnetwork.com'],
            'login2' => 'https://www.freespiritdining.com/',
        ],
        "upromise" => [
            'from'   => ['dining@dine.upromise.com'],
            'url'    => ['//click.dine.upromise.com/?', '%2F%2Fclick.dine.upromise.com%2F'],
            'login2' => 'https://dining.upromise.com/',
        ],
        "fuelrewards" => [
            'from' => ['fuelrewardsdining@email.rewardsnetwork.com', 'fuelrewardsdining@rewardsnetwork.com'],
        ],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.rewardsnetwork.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $linkTitle = ["Log in", "Log In", "go to your account", "Go to Your Account", "Go to your account", "Go to your account >", "Go to your account »", "Manage Cards", "GO TO YOUR ACCOUNT"];
        $commonUrl = ['//click.email.rewardsnetwork.com/?', '__click.email.rewardsnetwork.com_-3F', '%2F%2Fclick.email.rewardsnetwork.com%2F%3F'];

        foreach ($this->detectEmail as $de) {
            $cond = $this->contains($commonUrl, '@href');

            if (!empty($de['url'])) {
                $cond = $this->contains($de['url'], '@href') . ' and ancestor::*[' . $this->contains('Rewards Network') . ']';
            }

            if (!empty($de['from']) && $this->striposAll($parser->getCleanFrom(), $de['from']) !== false
                && $this->http->XPath->query("//a[" . $this->eq($linkTitle)
                    . " and ( " . $cond . " )]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $linkTitle = ["Log in", "Log In", "go to your account", "Go to Your Account", "Go to your account", "Go to your account >", "Go to your account »", "Manage Cards", "GO TO YOUR ACCOUNT"];
        $commonUrl = ['//click.email.rewardsnetwork.com/?', '__click.email.rewardsnetwork.com_-3F', '%2F%2Fclick.email.rewardsnetwork.com%2F%3F'];

        foreach ($this->detectEmail as $code => $de) {
            $cond = $this->contains($commonUrl, '@href');

            if (!empty($de['url'])) {
                $cond = $this->contains($de['url'], '@href') . ' and ancestor::*[' . $this->contains('Rewards Network') . ']';
            }

            if (!empty($de['from']) && $this->striposAll($parser->getCleanFrom(), $de['from']) !== false
                && $this->http->XPath->query("//a[" . $this->eq($linkTitle)
                    . " and ( " . $cond . " )]")->length > 0
            ) {
                $st->setMembership(true);

                if (isset($de['login2'])) {
                    $st->setLogin2($de['login2']);
                }

                $name = $this->http->FindSingleNode("//a[" . $this->eq($linkTitle)
                    . " and ( " . $this->contains($de['url'] ?? $commonUrl, '@href') . " )]/ancestor::td[1]/descendant::text()[normalize-space()][1][" . $this->starts(["Hi ", "Hi, "]) . "]", null, true,
                    "/^Hi,? ([[:alpha:] \-]+),$/");

                if (!empty($name)) {
                    $st->addProperty('Name', $name);
                }
            }
        }
        $st
            ->setMembership(true)
            ->setNoBalance(true)
        ;

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
