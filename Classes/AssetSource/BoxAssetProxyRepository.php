<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Hans Hoechtl <hhoechtl@1drop.de>
 *  All rights reserved
 ***************************************************************/
namespace Onedrop\AssetSource\Box\AssetSource;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Media\Domain\Model\AssetSource\AssetProxy\AssetProxyInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxyQueryResultInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxyRepositoryInterface;
use Neos\Media\Domain\Model\AssetSource\AssetSourceConnectionExceptionInterface;
use Neos\Media\Domain\Model\AssetSource\AssetTypeFilter;
use Neos\Media\Domain\Model\Tag;
use Onedrop\AssetSource\Box\Api\BoxClient;

class BoxAssetProxyRepository implements AssetProxyRepositoryInterface
{
    /**
     * @var BoxClient
     * @Flow\Inject()
     */
    protected $boxClient;
    /**
     * @var BoxAssetSource
     */
    private $assetSource;
    /**
     * @var VariableFrontend
     */
    protected $assetProxyCache;

    /**
     * BoxAssetProxyRepository constructor.
     *
     * @param BoxClient $boxClient
     */
    public function __construct(BoxAssetSource $assetSource)
    {
        $this->assetSource = $assetSource;
    }

    /**
     * @param  string                                $identifier
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Cache\Exception
     * @return AssetProxyInterface
     */
    public function getAssetProxy(string $identifier): AssetProxyInterface
    {
        $cacheKey = sha1($identifier);
        if ($this->assetProxyCache->has($cacheKey)) {
            $fileInfo = $this->assetProxyCache->get($cacheKey);
        } else {
            $this->boxClient->setAssetSourceIdentifier($this->assetSource->getIdentifier());
            $fileInfo = $this->boxClient->getFileInfo((int)$identifier);
            $this->assetProxyCache->set($cacheKey, $fileInfo);
        }
        return new BoxAssetProxy($fileInfo, $this->assetSource);
    }

    /**
     * @param AssetTypeFilter $assetType
     */
    public function filterByType(AssetTypeFilter $assetType = null): void
    {
    }

    /**
     * @throws AssetSourceConnectionExceptionInterface
     * @return AssetProxyQueryResultInterface
     */
    public function findAll(): AssetProxyQueryResultInterface
    {
        return (new BoxAssetProxyQuery($this->assetSource))->execute();
    }

    /**
     * @param  string                         $searchTerm
     * @return AssetProxyQueryResultInterface
     */
    public function findBySearchTerm(string $searchTerm): AssetProxyQueryResultInterface
    {
        $query = new BoxAssetProxyQuery($this->assetSource);
        $query->setSearchTerm($searchTerm);
        return $query->execute();
    }

    /**
     * @param  Tag                            $tag
     * @throws \Exception
     * @return AssetProxyQueryResultInterface
     */
    public function findByTag(Tag $tag): AssetProxyQueryResultInterface
    {
        throw new \Exception(__METHOD__ . ' to filter ' . $tag->getLabel() . 'is not yet implemented');
    }

    /**
     * @throws \Exception
     * @return AssetProxyQueryResultInterface
     */
    public function findUntagged(): AssetProxyQueryResultInterface
    {
        throw new \Exception(__METHOD__ . 'is not yet implemented');
    }

    /**
     * Count all assets, regardless of tag or collection
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @return int
     */
    public function countAll(): int
    {
        $this->boxClient->setAssetSourceIdentifier($this->assetSource->getIdentifier());
        $apiResponse = $this->boxClient->findAll();
        return $apiResponse['total_count'];
    }
}
