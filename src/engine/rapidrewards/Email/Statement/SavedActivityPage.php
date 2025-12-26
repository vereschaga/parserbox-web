<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

class SavedActivityPage extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/statements/st-777.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = $this->ParseEmail($parser);

        return [
            'parsedData' => $result,
            'emailType'  => 'SavedActivityPage',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], 'Recent Activity') !== false
            && $this->http->XPath->query('//a[contains(@href, "southwest.com")]')->length > 0;
    }

    protected function ParseEmail(\PlancakeEmailParser $parser)
    {
        $activity = [];
        $result = ['Activity' => &$activity];

        $xpath = '//text()[(contains(., "plus") or contains(., "minus")) and contains(., "points")]';
        $roots = $this->http->XPath->query($xpath);
        $found = false;

        for ($j = 0; $j < min(10, $roots->length) && !$found; $j++) {
            $root = $roots->item($j);
            $found = false;
            $parent = null;
            $i = 0;

            while (!$found && $i < 10) {
                if (isset($parent)) {
                    $root = $parent;
                }
                $found = $this->checkItem($root, $fields);
                $parent = $this->http->XPath->query('parent::*', $root)->item(0);
                $i++;
            }

            if ($found) {
                $xpath = $xpath . str_repeat('/parent::*', $i - 1);
            }
        }

        if (!$found) {
            $this->http->Log('history not found');

            return $result;
        }
        $items = $this->http->XPath->query($xpath);

        foreach ($items as $item) {
            if ($this->checkItem($item, $fields)) {
                $activity[] = $fields;
            }
        }

        return $result;
    }

    protected function checkItem(\DOMNode $cell, &$fields)
    {
        $lines = $this->http->FindNodes('*[normalize-space(.) != ""]', $cell);

        if (count($lines) === 4 && preg_match('/^\w{3}\s*\d{1,2},\s*\d{4}$/', $lines[0]) > 0 && strtotime($lines[0]) && preg_match('/^(plus|(?<m>minus))\s*(?<p>[\d\,]+)\s*points/', $lines[3], $m) > 0) {
            $fields = [
                "Posting Date" => strtotime($lines[0]),
                "Description"  => $lines[2],
                "Category"     => $lines[1],
                "Total Miles"  => sprintf('%s%s', !empty($m['m']) ? '-' : '', str_replace(',', '', $m['p'])),
            ];

            return true;
        } else {
            return false;
        }
    }
}
