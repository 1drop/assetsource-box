<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Hans Hoechtl <hhoechtl@1drop.de>
 *  All rights reserved
 ***************************************************************/
namespace Onedrop\AssetSource\Box\AssetSource;

use Neos\Media\Domain\Model\AssetSource\AssetProxyRepositoryInterface;
use Neos\Media\Domain\Model\AssetSource\AssetSourceInterface;
use Neos\Utility\Arrays;

class BoxAssetSource implements AssetSourceInterface
{
    /**
     * @var string
     */
    private $assetSourceIdentifier;
    /**
     * @var array|string[]
     */
    private $assetSourceOptions;
    /**
     * @var AssetProxyRepositoryInterface
     */
    private $assetProxyRepository;

    /**
     * @param string   $assetSourceIdentifier
     * @param string[] $assetSourceOptions
     */
    public function __construct(string $assetSourceIdentifier, array $assetSourceOptions)
    {
        $this->assetSourceIdentifier = $assetSourceIdentifier;
        $this->assetSourceOptions = $assetSourceOptions;
    }

    /**
     * This factory method is used instead of a constructor in order to not dictate a __construct() signature in this
     * interface (which might conflict with an asset source's implementation or generated Flow proxy class).
     *
     * @param  string               $assetSourceIdentifier
     * @param  array                $assetSourceOptions
     * @return AssetSourceInterface
     */
    public static function createFromConfiguration(
        string $assetSourceIdentifier,
        array $assetSourceOptions
    ): AssetSourceInterface {
        return new static($assetSourceIdentifier, $assetSourceOptions);
    }

    /**
     * A unique string which identifies the concrete asset source.
     * Must match /^[a-z][a-z0-9-]{0,62}[a-z]$/
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->assetSourceIdentifier;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->getOption('label');
    }

    /**
     * @return AssetProxyRepositoryInterface
     */
    public function getAssetProxyRepository(): AssetProxyRepositoryInterface
    {
        if ($this->assetProxyRepository === null) {
            $this->assetProxyRepository = new BoxAssetProxyRepository($this);
        }
        return $this->assetProxyRepository;
    }

    /**
     * @return bool
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * @param  string $optionPath
     * @return mixed
     */
    public function getOption(string $optionPath)
    {
        return Arrays::getValueByPath($this->assetSourceOptions, $optionPath);
    }
}
