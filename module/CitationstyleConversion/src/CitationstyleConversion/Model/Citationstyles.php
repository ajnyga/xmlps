<?php

namespace CitationstyleConversion\Model;

use DirectoryIterator;
use Xmlps\Libxml\Libxml;
use Xmlps\Logger\Logger;
use DOMDocument;
use Zend\Cache\Storage\Adapter\AbstractAdapter as CacheAdapter;

/**
 * Gives access to various citation styles
 *
 * It uses a directory with *.csl files as repository. By default we use a
 * checked out version of https://github.com/citation-style-language/styles
 *
 * See also: http://citationstyles.org/
 */
class Citationstyles
{
    use Libxml;

    protected $config;
    protected $logger;

    /**
     * Constructor
     *
     * @param mixed $config Citationstyle repository config
     * @param Logger $logger Logger
     * @param CacheAdapter $cache Cache adapter
     *
     * @return void
     */
    public function __construct($config, Logger $logger, CacheAdapter $cache)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->cache = $cache;

        if (!is_dir($this->config['repository'])) {
            throw new \Exception('Citationstyle repository doesn\'t exist');
        }

        $this->disableLibxmlErrorDisplay();
    }

    /**
     * Returns a array with the md5 of the file name as a key and the value
     * contains the title and the file name. I.e.:
     *
     * array(
     *      'c6de5efe3294b26391ea343053c19a84' => array(
     *          'title' => [title],
     *          'file' => [file],
     *      ),
     *      ...
     * );
     *
     * @return array Map of file name to title
     */
    public function getStyleMap()
    {
        // Load the list from cache
        $cacheKey = 'citationstyleconversionCytationstylesCitationstylelist';
        $list = $this->cache->getItem($cacheKey);
        if (!empty($list)) return unserialize($list);

        $styleMap = array();

        // Load the title from each of the citation style files
        $files = new DirectoryIterator($this->config['repository']);
        foreach($files as $file) {
            if ($file->isDir()) continue;
            $file = $file->getPathName();
            if (!substr($file, -3, 3) == 'csl') continue;

            // Load the CSL into a DOMDocument
            $dom = new DOMDocument();
            if (!$dom->loadXML(file_get_contents($file))) {
                $this->logger->debugTranslate(
                    'citationstyleconversion.citationstyles.errorLoadingCslLog',
                    $this->libxmlErrors()
                );
                continue;
            }

            // Get the title from the document
            $title = $dom->getElementsByTagName('title');
            if (!$title->length) continue;
            $title = $title->item(0)->nodeValue;

            $styleMap[md5($file)] = array('title' => $title, 'file' => $file);
        }

        // Sort the style map by title
        uasort($styleMap, function($a, $b) {
            if ($a['title'] == $b['title']) return 0;
            return ($a['title'] < $b['title']) ? -1 : 1;
        });

        // Store the list in cache
        $this->cache->setItem($cacheKey, serialize($styleMap));

        return $styleMap;
    }
}