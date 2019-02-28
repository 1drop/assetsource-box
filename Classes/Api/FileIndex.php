<?php
namespace Onedrop\AssetSource\Box\Api;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class FileIndex
{
    private $batchSize = 100;
    /**
     * @var VariableFrontend
     */
    protected $cache;
    /**
     * @var BoxClient
     */
    protected $boxClient;

    /**
     * @param  BoxClient                             $boxClient
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Cache\Exception
     * @return array
     */
    public function getAllFiles(BoxClient $boxClient): array
    {
        $this->boxClient = $boxClient;
        $cacheKey = md5($this->boxClient->getBaseFolderId());
        $allFiles = $this->cache->get($cacheKey);
        if (is_array($allFiles)) {
            return $allFiles;
        }
        $allFiles = $this->buildIndex();
        $this->cache->set($cacheKey, $allFiles);
        return $allFiles;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @return array
     */
    protected function buildIndex(): array
    {
        $allFolderItems = $this->getAllFolderItems($this->boxClient->getBaseFolderId());
        while (($idxFirstFolder = $this->getFirstFolderPosition($allFolderItems)) !== false) {
            $currentFolderId = (int)$allFolderItems[$idxFirstFolder]['id'];
            unset($allFolderItems[$idxFirstFolder]);
            $allFolderItems = array_merge($allFolderItems, $this->getAllFolderItems($currentFolderId));
        }
        return $allFolderItems;
    }

    /**
     * @param  array    $items
     * @return bool|int
     */
    protected function getFirstFolderPosition(array $items)
    {
        foreach ($items as $idx => $item) {
            if ($item['type'] === 'folder') {
                return $idx;
            }
        }
        return false;
    }

    /**
     * @param  int                                   $folderId
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @return array
     */
    protected function getAllFolderItems(int $folderId): array
    {
        $itemCount = (int)$this->boxClient->getFolderItems($folderId, 1, 0)['total_count'];
        $offset = 0;
        $items = [];
        while ($offset < $itemCount) {
            $items = array_merge($items, $this->boxClient->getFolderItems($folderId, $this->batchSize, $offset)['entries']);
            $offset += $this->batchSize;
        }
        return $items;
    }
}
