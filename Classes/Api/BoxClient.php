<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Hans Hoechtl <hhoechtl@1drop.de>
 *  All rights reserved
 ***************************************************************/
namespace Onedrop\AssetSource\Box\Api;

use GuzzleHttp\Client;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Request;
use Neos\Flow\Log\PsrSystemLoggerInterface;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Utility\Arrays;
use Onedrop\AssetSource\Box\Exception\MissingOAuthException;

final class BoxClient
{
    private static $apiBaseUri = 'https://api.box.com/2.0';
    /**
     * @var PsrSystemLoggerInterface
     * @Flow\Inject()
     */
    protected $systemLogger;
    /**
     * @var UriBuilder
     * @Flow\Inject()
     */
    protected $uriBuilder;
    /**
     * @var array
     * @Flow\InjectConfiguration(package="Neos.Media", path="assetSources")
     */
    protected $assetSources;
    /**
     * @var array
     */
    protected $assetSourceOptions;
    /**
     * @var StringFrontend
     */
    protected $accessTokenCache;
    /**
     * @var StringFrontend
     */
    protected $authTokenCache;
    /**
     * @var string
     */
    protected $assetSourceIdentifier;

    /**
     * @param string $assetSourceIdentifier
     */
    public function setAssetSourceIdentifier(string $assetSourceIdentifier): void
    {
        $this->assetSourceIdentifier = $assetSourceIdentifier;
        $this->assetSourceOptions = $this->assetSources[$assetSourceIdentifier]['assetSourceOptions'];
    }

    /**
     * Check if the box.com application has already been authorized by the user
     * for OAuth2
     *
     * @throws MissingOAuthException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     */
    private function checkAuthorization()
    {
        if (!$this->authTokenCache->has($this->assetSourceIdentifier)) {
            $this->uriBuilder->setRequest(new ActionRequest(Request::createFromEnvironment()));
            $authUri = $this->uriBuilder
                ->setCreateAbsoluteUri(true)
                ->uriFor(
                    'requestToken',
                    ['assetSourceIdentifier' => $this->assetSourceIdentifier],
                    'Authenticate',
                    'Onedrop.AssetSource.Box'
                );
            throw new MissingOAuthException('You must authorize box.com access: ' . $authUri);
        }
    }

    /**
     * @throws MissingOAuthException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @return mixed
     */
    private function newAccessToken()
    {
        if (Arrays::getValueByPath($this->assetSourceOptions, 'useDevToken') === true) {
            return Arrays::getValueByPath($this->assetSourceOptions, 'devToken');
        }
        $this->checkAuthorization();
        
        $responseData = (new Client())->request('POST', $this->getOption('authenticationUrl'), [
            'form_params' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $this->getOption('clientId'),
                'client_secret' => $this->getOption('clientSecret'),
                'code'          => $this->authTokenCache->get($this->assetSourceIdentifier),
            ],
        ])->getBody()->getContents();
        $response = \GuzzleHttp\json_decode($responseData, true);
        list('access_token' => $accessToken, 'expires_in' => $expire) = $response;
        try {
            $this->accessTokenCache->set($this->assetSourceIdentifier . '__access_token', $accessToken, [], $expire);
        } catch (\Neos\Cache\Exception $e) {
            $this->systemLogger->error('Could not store access token in cache', ['error' => $e->getMessage()]);
        }
        return $accessToken;
    }

    /**
     * @param  array                                                       $query
     * @throws MissingOAuthException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @return array
     */
    private function buildRequest($query = []): array
    {
        $accessToken = '';
        try {
            if ($this->accessTokenCache->has($this->assetSourceIdentifier . '__access_token')) {
                $accessToken = $this->accessTokenCache->get($this->assetSourceIdentifier . '__access_token');
            } else {
                $accessToken = $this->newAccessToken();
            }
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $this->systemLogger->error('Request of access token returned an error', ['error' => $e->getMessage()]);
        }
        $request = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'allow_redirects' => true,
            'timeout'         => 2000,
            'http_errors'     => true,
        ];
        if (!empty($query)) {
            $request['query'] = $query;
        }
        return $request;
    }

    /**
     * @param  string                                                      $query
     * @param  int                                                         $limit
     * @param  int                                                         $offset
     * @param  array                                                       $fileExtensions
     * @param  array                                                       $fields
     * @throws MissingOAuthException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @return array
     */
    public function search(string $query, $limit = 100, $offset = 0, $fileExtensions = [], $fields = [])
    {
        $queryParams = [
            'query'  => $query,
            'type'   => 'file',
            'limit'  => $limit,
            'offset' => $offset,
        ];
        if (!empty($fields)) {
            $queryParams['fields'] = implode(',', $fields);
        }
        if (!empty($fileExtensions)) {
            $queryParams['file_extensions'] = implode(',', $fileExtensions);
        }
        $client = new Client();
        return \GuzzleHttp\json_decode($client->request(
            'GET',
            self::$apiBaseUri . '/search',
            $this->buildRequest($queryParams)
        )->getBody()->getContents(), true);
    }

    /**
     * @param  int                                                         $limit
     * @param  int                                                         $offset
     * @throws MissingOAuthException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @return array
     */
    public function findAll($limit = 100, $offset = 0)
    {
        return $this->getFolderItems($this->assetSourceOptions['folder'], $limit, $offset);
    }

    /**
     * @return int
     */
    public function getBaseFolderId(): int
    {
        return $this->assetSourceOptions['folder'];
    }

    /**
     * @param  int                                                         $folderId
     * @throws MissingOAuthException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @return array
     */
    public function getFolderInfo(int $folderId)
    {
        return \GuzzleHttp\json_decode(
            (new Client())->request('GET', self::$apiBaseUri . '/folders/' . $folderId, $this->buildRequest())
                ->getBody()
                ->getContents(),
            true
        );
    }

    /**
     * @param  int                                                         $fileId
     * @throws MissingOAuthException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @return \Psr\Http\Message\StreamInterface
     */
    public function getFile(int $fileId)
    {
        return (new Client())
            ->request('GET', self::$apiBaseUri . '/files/' . $fileId . '/content', $this->buildRequest())
            ->getBody();
    }

    /**
     * @param  int                                                         $fileId
     * @param  string                                                      $fileExtension
     * @throws MissingOAuthException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @return string
     */
    public function getThumbnail(int $fileId, string $fileExtension)
    {
        $params = [
          'min_height' => 160,
          'min_width'  => 160,
          'max_height' => 240,
          'max_width'  => 240,
        ];
        return (new Client())
            ->request('GET', self::$apiBaseUri . '/files/' . $fileId . '/thumbnail.' . $fileExtension, $this->buildRequest($params))
            ->getBody()->getContents();
    }

    /**
     * @param  int                                                         $folderId
     * @param  int                                                         $limit
     * @param  int                                                         $offset
     * @throws MissingOAuthException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @return array
     */
    public function getFolderItems(int $folderId, $limit = 100, $offset = 0)
    {
        return \GuzzleHttp\json_decode(
            (new Client())->request(
                'GET',
                self::$apiBaseUri . '/folders/' . $folderId . '/items',
                $this->buildRequest([
                    'limit'  => $limit,
                    'offset' => $offset,
                    'sort'   => 'date',
                ])
            )->getBody()->getContents(),
            true
        );
    }

    /**
     * @param  int                                                         $fileId
     * @throws MissingOAuthException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @return array
     */
    public function getFileInfo(int $fileId)
    {
        return \GuzzleHttp\json_decode((new Client())->request(
            'GET',
            self::$apiBaseUri . '/files/' . $fileId,
            $this->buildRequest()
        )->getBody()->getContents(), true);
    }

    /**
     * @param  string $propertyPath
     * @return mixed
     */
    public function getOption(string $propertyPath)
    {
        return Arrays::getValueByPath($this->assetSourceOptions, $propertyPath);
    }
}
