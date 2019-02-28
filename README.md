# Neos Asset source box.com

Register an OAuth2 client application in the box.com developer portal.

Specify this as redirect uri:
```
https://mysite.com/neos/assetsource/box/authenticate/receivetoken
```

Or if you develop locally, you can use a dev token which is 60 minutes
valid and doesn't require a valid OAuth2 redirect.

Fill in the credentials you just gained like this:

```yaml
Neos:
  Media:
    assetSources:
      box_com:
        assetSource: 'Onedrop\AssetSource\Box\AssetSource\BoxAssetSource'
        assetSourceOptions:
          label: Box.com
          folder: folder id
          authenticationUrl: https://api.box.com/oauth2/token
          clientId: the client id
          clientSecret: the client seecret
          enterpriseID: 0
          devToken: dev token
          useDevToken: false
```
