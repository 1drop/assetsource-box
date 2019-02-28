<?php
namespace Onedrop\AssetSource\Box\Controller;

use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Uri;
use Neos\Flow\Mvc\Controller\ActionController;
use Ramsey\Uuid\Uuid;

/**
 * @Flow\Scope("singleton")
 */
class AuthenticateController extends ActionController
{
    /**
     * @var StringFrontend
     */
    protected $accessTokenCache;
    /**
     * @var StringFrontend
     */
    protected $authTokenCache;
    /**
     * @var array
     * @Flow\InjectConfiguration(package="Neos.Media", path="assetSources")
     */
    protected $assetSources;

    /**
     * @param  string                                                      $assetSourceIdentifier
     * @throws \Neos\Cache\Exception
     * @throws \Neos\Cache\Exception\InvalidDataException
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     * @throws \Neos\Flow\Mvc\Exception\UnsupportedRequestTypeException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     */
    public function requestTokenAction(string $assetSourceIdentifier)
    {
        $assetSourceOptions = $this->assetSources[$assetSourceIdentifier]['assetSourceOptions'];
        $secureTempToken = Uuid::uuid4()->toString();
        $this->accessTokenCache->set($assetSourceIdentifier . '_tempToken', $secureTempToken);
        $forwardUri = $this->uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(true)
            ->uriFor(
                'index',
                [
                    'module'          => 'management/media',
                    'moduleArguments' => [
                        'assetSourceIdentifier' => $assetSourceIdentifier,
                    ],
                ],
                'Backend\Module',
                'Neos.Neos'
            );
        $redirectUri = $this->uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(true)
            ->uriFor('receiveToken', [
                'assetSourceIdentifier' => $assetSourceIdentifier,
                'forwardUri'            => $forwardUri,
            ]);
        $params = [
            'response_type' => 'code',
            'client_id'     => $assetSourceOptions['clientId'],
            'redirect_uri'  => $redirectUri,
            'state'         => $secureTempToken,
        ];
        $this->redirectToUri(new Uri('https://account.box.com/api/oauth2/authorize?' . http_build_query($params)));
    }

    /**
     * @throws \Neos\Flow\Mvc\Exception\NoSuchArgumentException
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    public function initializeReceiveTokenAction()
    {
        if ($this->request->getHttpRequest()->hasArgument('code')) {
            $this->arguments->getArgument('code')->setValue($this->request->getHttpRequest()->getArgument('code'));
        }
    }

    /**
     * @param  string                                                   $assetSourceIdentifier
     * @param  string                                                   $forwardUri
     * @param  string                                                   $code
     * @throws \Neos\Cache\Exception
     * @throws \Neos\Cache\Exception\InvalidDataException
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     * @throws \Neos\Flow\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function receiveTokenAction(string $assetSourceIdentifier, string $forwardUri, string $code)
    {
        // TODO: validate temp security token
        $this->authTokenCache->set($assetSourceIdentifier, $code);
        $this->redirectToUri($forwardUri);
    }
}
