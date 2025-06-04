<?php

namespace Koala\Utils;

class Sanitize
{

    /**
     *
     * @param mixed $data
     * @param array $allowedTags
     * @return mixed
     * allowed tags should be in the format of ['b', 'i', 'u', 'a']
     */
    public function clean($data, array $allowedTags = [], $encoding = 'UTF-8')
    {

        if (is_string($data)) {
            return $this->cleanStrings($data, $allowedTags, $encoding);
        } elseif ($data instanceof Collection) {
            return $this->cleanCollection($data, $allowedTags, $encoding);
        } elseif (is_array($data)) {
            return $this->cleanArray($data, $allowedTags, $encoding);
        } elseif (is_object($data)) {
            return $this->cleanObject($data, $allowedTags, $encoding);
        } else {
            return $data;
        }
    }

    protected function cleanStrings($data, array $allowedTags = [], $encoding = 'UTF-8')
    {
        $data = trim($data);

        $cleanedData = $this->removeNonPrintables($data);

        $cleanedData = empty($allowedTags) ?
            htmlspecialchars(strip_tags($cleanedData)) :
            $this->cleanStringWithTags($cleanedData, $allowedTags);

        return !empty($encoding) ?
            $this->encodeString($cleanedData, $encoding) :
            $cleanedData;
    }

    protected function cleanCollection(Collection $data, array $allowedTags = [], $encoding = 'UTF-8')
    {
        $cleanedData = [];
        foreach ($data as $key => $value) {
            $cleanedData[$key] = $this->clean($value, $allowedTags, $encoding);
        }
        return new Collection($cleanedData);
    }

    protected function cleanStringWithTags($data, array $allowedTags = [], $encoding = 'UTF-8')
    {
        $stripTags = strip_tags($data, '<' . implode('><', $allowedTags) . '>');

        return preg_replace_callback(
            '/(<(?:'
                . implode('|', $allowedTags)
                . ')[^>]*>)(.*?)(<\/(?:'
                . implode('|', $allowedTags)
                . ')>)/is',
            function ($matches) {
                return  $matches[1] . htmlspecialchars($matches[2]) . $matches[3];
            },
            $stripTags
        );
    }

    protected function cleanArray(array $data, array $allowedTags = [], $encoding = 'UTF-8')
    {
        $cleanedData = [];
        foreach ($data as $key => $value) {
            $cleanedData[$key] = $this->clean($value, $allowedTags, $encoding);
        }
        return $cleanedData;
    }

    protected function cleanObject($data, array $allowedTags = [], $encoding = 'UTF-8')
    {
        $cleanedData = new \stdClass();
        foreach ($data as $key => $value) {
            $cleanedData->$key = $this->clean($value, $allowedTags, $encoding);
        }
        return $cleanedData;
    }

    protected function encodeString($data, $encoding = 'UTF-8')
    {
        return \htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, $encoding);
    }

    protected function removeNonPrintables($data)
    {
        return \preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F]/',
            '',
            $data
        );
    }
}
