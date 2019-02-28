<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Hans Hoechtl <hhoechtl@1drop.de>
 *  All rights reserved
 ***************************************************************/
namespace Onedrop\AssetSource\Box\AssetSource;

use Neos\Flow\Annotations as Flow;
use Neos\Media\Domain\Model\AssetSource\AssetProxyQueryInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxyQueryResultInterface;
use Onedrop\AssetSource\Box\Api\BoxClient;
use Onedrop\AssetSource\Box\Api\FileIndex;

final class BoxAssetProxyQuery implements AssetProxyQueryInterface
{
    /**
     * @var int
     */
    private $limit = 20;
    /**
     * @var int
     */
    private $offset = 0;
    /**
     * @var string
     */
    private $searchTerm;
    /**
     * @var BoxClient
     * @Flow\Inject()
     */
    protected $boxClient;
    /**
     * @var FileIndex
     * @Flow\Inject()
     */
    protected $fileIndex;
    /**
     * @var BoxAssetSource
     */
    private $assetSource;

    /**
     * BoxAssetProxyQuery constructor.
     */
    public function __construct(BoxAssetSource $assetSource)
    {
        $this->assetSource = $assetSource;
    }

    /**
     * @param int $offset
     */
    public function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param string $searchTerm
     */
    public function setSearchTerm(string $searchTerm)
    {
        $this->searchTerm = $searchTerm;
    }

    /**
     * @return string
     */
    public function getSearchTerm()
    {
        return $this->searchTerm;
    }

    /**
     * @return AssetProxyQueryResultInterface
     */
    public function execute(): AssetProxyQueryResultInterface
    {
        return new BoxAssetQueryProxyResult($this);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Cache\Exception
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @throws \Onedrop\AssetSource\Box\Exception\MissingOAuthException
     * @return BoxAssetProxy[]
     */
    public function getArrayResult(): array
    {
        $this->boxClient->setAssetSourceIdentifier($this->assetSource->getIdentifier());
        $assetProxies = [];
        if (empty($this->searchTerm)) {
            $entries = array_slice($this->fileIndex->getAllFiles($this->boxClient), $this->offset, $this->limit);
        } else {
            $entries = $this->boxClient->search($this->searchTerm, $this->limit, $this->offset)['entries'];
        }
        foreach ($entries as $entry) {
            $assetProxies[] = new BoxAssetProxy($entry, $this->assetSource);
        }
        return $assetProxies;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Cache\Exception
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @throws \Onedrop\AssetSource\Box\Exception\MissingOAuthException
     * @return int
     */
    public function count(): int
    {
        $this->boxClient->setAssetSourceIdentifier($this->assetSource->getIdentifier());
        if (empty($this->searchTerm)) {
            return count($this->fileIndex->getAllFiles($this->boxClient));
        }
        return (int)$this->boxClient->search($this->searchTerm, 1, 0)['total_count'];
    }
}
