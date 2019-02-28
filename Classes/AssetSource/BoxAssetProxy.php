<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Hans Hoechtl <hhoechtl@1drop.de>
 *  All rights reserved
 ***************************************************************/
namespace Onedrop\AssetSource\Box\AssetSource;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Media\Domain\Model\AssetSource\AssetProxy\AssetProxyInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxy\HasRemoteOriginalInterface;
use Neos\Media\Domain\Model\AssetSource\AssetSourceInterface;
use Neos\Media\Domain\Model\ImportedAsset;
use Neos\Media\Domain\Repository\ImportedAssetRepository;
use Neos\Utility\Arrays;
use Neos\Utility\MediaTypes;
use Onedrop\AssetSource\Box\Api\BoxClient;
use Psr\Http\Message\UriInterface;

class BoxAssetProxy implements AssetProxyInterface, HasRemoteOriginalInterface
{
    private $possiblePreviewTypes = ['png', 'jpg', 'jpeg', 'svg', 'gif'];
    /**
     * @var array
     */
    private $fileInfo;
    /**
     * @var ImportedAsset
     */
    private $importedAsset;
    /**
     * @var BoxAssetSource
     */
    private $assetSource;
    /**
     * @var VariableFrontend
     */
    protected $fileContentCache;
    /**
     * @Flow\Inject
     * @var ImportedAssetRepository
     */
    protected $importedAssetRepository;
    /**
     * @var BoxClient
     * @Flow\Inject()
     */
    protected $boxClient;

    /**
     * BoxAssetProxy constructor.
     *
     * @param array          $fileInfo
     * @param BoxAssetSource $assetSource
     */
    public function __construct(array $fileInfo, BoxAssetSource $assetSource)
    {
        $this->fileInfo = $fileInfo;
        $this->assetSource = $assetSource;
        $this->importedAsset = (new ImportedAssetRepository)->findOneByAssetSourceIdentifierAndRemoteAssetIdentifier(
            $assetSource->getIdentifier(),
            $this->getIdentifier()
        );
    }

    /**
     * @return AssetSourceInterface
     */
    public function getAssetSource(): AssetSourceInterface
    {
        return $this->assetSource;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->getProperty('id');
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->getProperty('name');
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->getProperty('name');
    }

    /**
     * @throws \Exception
     * @return \DateTimeInterface
     */
    public function getLastModified(): \DateTimeInterface
    {
        return new \DateTime($this->getProperty('modified_at'));
    }

    /**
     * @return int
     */
    public function getFileSize(): int
    {
        return $this->getProperty('size');
    }

    /**
     * @return string
     */
    public function getFileExtension(): string
    {
        return pathinfo($this->getFilename(), PATHINFO_EXTENSION);
    }

    /**
     * @return string
     */
    public function getMediaType(): string
    {
        return MediaTypes::getMediaTypeFromFilename($this->getProperty('name'));
    }

    /**
     * @return int|null
     */
    public function getWidthInPixels(): ?int
    {
        return null;
    }

    /**
     * @return int|null
     */
    public function getHeightInPixels(): ?int
    {
        return null;
    }

    /**
     * @throws \Neos\Cache\Exception
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @throws \Onedrop\AssetSource\Box\Exception\MissingOAuthException
     * @return null|UriInterface
     */
    public function getThumbnailUri(): ?UriInterface
    {
        if (!in_array($this->getFileExtension(), $this->possiblePreviewTypes)) {
            return null;
        }
        $cacheKey = sha1($this->getIdentifier() . '_thumb');
        if ($this->fileContentCache->has($cacheKey)) {
            $data = $this->fileContentCache->get($cacheKey);
        } else {
            $this->boxClient->setAssetSourceIdentifier($this->assetSource->getIdentifier());
            try {
                $data = $this->boxClient->getThumbnail((int)$this->getIdentifier(), $this->getFileExtension());
            } catch (GuzzleException $e) {
                return null;
            }
            $this->fileContentCache->set($cacheKey, $data);
        }
        return new Uri('data:image/' . $this->getFileExtension() . ';base64,' . base64_encode($data));
    }

    /**
     * @throws GuzzleException
     * @throws \Neos\Cache\Exception
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @throws \Onedrop\AssetSource\Box\Exception\MissingOAuthException
     * @return null|UriInterface
     */
    public function getPreviewUri(): ?UriInterface
    {
        if (!in_array($this->getFileExtension(), $this->possiblePreviewTypes)) {
            return null;
        }
        $cacheKey = sha1($this->getIdentifier() . '_preview');
        if ($this->fileContentCache->has($cacheKey)) {
            $data = $this->fileContentCache->get($cacheKey);
        } else {
            $this->boxClient->setAssetSourceIdentifier($this->assetSource->getIdentifier());
            $data = $this->boxClient->getFile((int)$this->getIdentifier());
            $this->fileContentCache->set($cacheKey, $data);
        }
        return new Uri('data:image/' . $this->getFileExtension() . ';base64,' . base64_encode($data));
    }

    /**
     * @throws GuzzleException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @throws \Onedrop\AssetSource\Box\Exception\MissingOAuthException
     * @return resource
     */
    public function getImportStream()
    {
        return $this->boxClient->getFile((int)$this->getIdentifier())->detach();
    }

    /**
     * @return null|string
     */
    public function getLocalAssetIdentifier(): ?string
    {
        $importedAsset = $this->importedAssetRepository->findOneByAssetSourceIdentifierAndRemoteAssetIdentifier(
            $this->assetSource->getIdentifier(),
            $this->getIdentifier()
        );
        return ($importedAsset instanceof ImportedAsset ? $importedAsset->getLocalAssetIdentifier() : null);
    }

    /**
     * Returns true if the binary data of the asset has already been imported into the Neos asset source.
     *
     * @return bool
     */
    public function isImported(): bool
    {
        return true;
    }

    /**
     * @param  string     $propertyPath
     * @return mixed|null
     */
    protected function getProperty(string $propertyPath)
    {
        return Arrays::getValueByPath($this->fileInfo, $propertyPath);
    }
}
