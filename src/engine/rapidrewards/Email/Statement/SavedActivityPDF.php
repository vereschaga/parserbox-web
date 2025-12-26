<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

class SavedActivityPDF extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/statements/st-888.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = $this->ParseEmail($parser);

        return [
            'parsedData' => $result,
            'emailType'  => 'SavedActivityPDF',
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
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return stripos($text, 'Rapid') !== false && stripos($text, 'Activity') !== false; // cant figure out characters issues
        }

        return false;
    }

    // st-6322833.eml

    protected function ParseEmail(\PlancakeEmailParser $parser)
    {
        $props = [];
        $activity = [];
        $result = ['Properties' => &$props, 'Activity' => &$activity];

        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));
        } else {
            return $result;
        }
        $lines = explode("\n", $text);
        $lines = array_map(function ($s) {
            return CleanXMLValue($s);
        }, $lines);
        $lines = array_filter($lines, function ($s) {
            return strlen($s) > 0 && strpos($s, 'DATE') !== 0;
        });
        $line = '';

        while (count($lines) > 0 && preg_match('/PTS$/', $line) === 0) {
            $line = array_shift($lines);
        }

        while (count($lines) > 0) {
            $shifted = false;
            $line = preg_replace('/,(\d+ PTS)$/', 'a\\1', $line);

            if (preg_match('/^(?<date>\w{3}\s*\d{1,2}\s*\,\s*\d{4})(?<desc>.+)\s*\b(?<sign>.+)\b\s*(?<total>[\da]+) PTS$/', $line, $m) > 0 && strtotime($m['date'])) {
                $current = [
                    'Posting Date' => strtotime($m['date']),
                    'Total Miles'  => sprintf('%s%s', trim($m['sign']) === '+' ? '' : '-', str_replace('a', '', $m['total'])),
                ];
                $desc = trim($m['desc']);

                if (preg_match('/^.{0,5}(?<type>Flight|Credit Card|Other)(?<desc>.+)$/', $desc, $m) > 0) {
                    $current['Category'] = $m['type'];
                    $descLines = [trim($m['desc'])];
                    $line = array_shift($lines);
                    $shifted = true;

                    for ($i = 0; $i < 5 && count($lines) > 0 && strpos($line, 'Page') === false && strpos($line, 'PTS') === false; $i++) {
                        $descLines[] = $line;
                        $line = array_shift($lines);
                    }
                    $current['Description'] = implode(' ', $descLines);
                    $activity[] = $current;
                } else {
                    $this->http->Log('unknown activity type for sw', LOG_LEVEL_ERROR);
                }
            }

            if (!$shifted) {
                $line = array_shift($lines);
            }
        }

        return $result;
    }
}
