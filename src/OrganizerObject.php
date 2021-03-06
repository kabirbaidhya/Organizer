<?php
/*
 * This file is part of the Organizer package.
 *
 * (c) Kabir Baidhya <kabeer182010@gmail.com>
 *
 */

namespace Gckabir\Organizer;

use Gckabir\AwesomeCache\Cache;

abstract class OrganizerObject
{
    protected $bundle = null;
    protected $includes = array();
    protected $output = null;
    protected $version = null;

    protected $commentDelimeter1 = '/*';
    protected $commentDelimeter2 = '*/';
    protected $commentFormatter = '*';

    public function __construct($bundle, array $includes, $version)
    {
        $this->bundle = $bundle;
        $this->includes = $includes;
        $this->version = $version;

        $objectType = $this->getType();
        $this->config = OZR::getConfig($objectType);
    }

    public function getType()
    {
        $objectType = strtolower(basename(get_called_class()));
        $objectType = explode('\\', $objectType);
        $objectType = array_pop($objectType);

        return $objectType;
    }

    /**
     * include a new file.
     */
    public function add($item)
    {
        if (is_array($item)) {
            foreach ($item as $singleItem) {
                $this->add($singleItem);
            }
        } elseif (is_string($item)) {

            // Add only if the file doesn't exist already
            if (!in_array($item, $this->includes)) {
                $this->includes[] = $item;
            }
        } else {
            throw new Exception\OrganizerException('Invalid filenames provided for add()');
        }

        return $this;
    }

    /**
     * include a file before all other includes.
     */
    public function addBefore($item)
    {
        if (is_string($item) and !in_array($item, $this->includes)) {
            array_unshift($this->includes, $item);
        } else {
            throw new Exception\OrganizerException('Invalid filenames provided for addBefore()');
        }

        return $this;
    }

    /**
     * Add code directly.
     */
    public function addCode($string)
    {
        if (!is_string($string)) {
            throw new Exception\OrganizerException('Invalid code for addCode()');
        }

        $code = (object) array(
            'type' => 'embeded',
            'code' => $string,
            );
        $this->includes[] = $code;
    }

    protected function signature()
    {
        $a = $this->commentDelimeter1.' ';
        $b = ' '.$this->commentFormatter.' ';
        $c = ' '.$this->commentDelimeter2;

        return OZR::getConfig('signature')
        ? (
            $a."\n".
            $b.$this->bundle.' v'.$this->version.' | '.gmdate('M d Y H:i:s')." UTC\n".
            $b.'Organized by Organizer'."\n".
            $b.'https://github.com/kabir-baidhya/organizer'."\n".
            $c."\n"
            ) : null;
    }

    public function merge()
    {
        $mergedCode = '';
        foreach ($this->includes as $singleItem) {
            if (is_string($singleItem)) {
                $code = $this->getSourceCode($singleItem);
            } elseif (isset($singleItem->type) && $singleItem->type == 'embeded') {
                $code = $singleItem->code;
                $path = $this->config['basePath'];
            } else {
                throw new Exception\OrganizerException('Invalid code');
            }

            $code = $this->preMergeProcessCode(@$path, $code);

            $mergedCode .= "\n".$code;
        }

        $this->output = $mergedCode;

        return $this;
    }

    /**
     * Get source code from a file or merged-code from files matched by pattern.
     */
    protected function getSourceCode($fileOrPattern)
    {
        $path = $this->config['basePath'].$fileOrPattern;

        $code = '';

        if (file_exists($path) && is_file($path)) {
            // if its a file get its code
            $code = file_get_contents($path);
        } elseif (($matchedFiles = glob($path)) && !empty($matchedFiles)) {

            // if its pattern, get the merged code of all the files matched
            // $matches = $this->filesByPattern($fileOrPattern);
            foreach ($matchedFiles as $filePath) {
                $code .= "\n".file_get_contents($filePath);
            }
        } else {
            throw new Exception\FileNotFoundException($path.' not found');
        }

        return $code;
    }

    public function build()
    {
        $uniqueString = $this->uniqueBundleString();

        $item = new Cache($uniqueString);
        $isCachingEnabled = $this->config['cache'];

        if (!$isCachingEnabled or !$item->isCachedAndUsable()) {
            $this->merge();
            $content = $this->config['minify'] ? $this->outputMinified() : $this->output();
            $item->putInCache($content);
        }

        return $this->url();
    }

    public function url()
    {
        $serverUrl = OZR::getServerUrl();
        $parameter = $this->config['parameter'];

        $url = $serverUrl.'?'.http_build_query(array(
            $parameter => $this->uniqueBundleString(),
            'ver' => $this->version,
            ));

        return $url;
    }

    protected function uniqueBundleString()
    {
        return base64_encode($this->getType().'-'.$this->bundle);
    }

    protected function preEmbedContent()
    {
        $cacheEnabled = $this->config['cache'];

        $uniqueString = $this->uniqueBundleString();

        $data = new Cache($uniqueString);

        if ($cacheEnabled and $data->isCachedAndUsable()) {
            $content = $data->cachedData();
        } else {
            $this->build();
            $content = $this->config['minify'] ? $this->outputMinified() : $this->output();
        }

        return $content;
    }

    protected function preMergeProcessCode($path, $code)
    {
        return $code;
    }

    abstract public function includeHere();
    abstract public function embedHere();
    abstract public function output();
    abstract public function outputMinified();
}
