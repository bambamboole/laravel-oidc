# Changelog

## [0.2.0](https://github.com/bambamboole/laravel-oidc-client/compare/v0.1.0...v0.2.0) (2026-07-15)


### Features

* add authorization redirect with pkce and login route ([ffe7d65](https://github.com/bambamboole/laravel-oidc-client/commit/ffe7d658a32cc8862616559fcb42a5e901ab243e))
* add client manager, facade, and user-resolution seam ([98cdfd9](https://github.com/bambamboole/laravel-oidc-client/commit/98cdfd95fa90bb5fb38c4d10aff816a71b993636))
* add provider discovery and jwks caching ([7d98889](https://github.com/bambamboole/laravel-oidc-client/commit/7d98889387b746081409cbe8eaac2cb660fea779))
* add rp-initiated logout with end-session redirect ([f0d8b2b](https://github.com/bambamboole/laravel-oidc-client/commit/f0d8b2b3083d924ba50153ec54d602b05a5a3dd4))
* add strict id_token validation against jwks ([e35cf37](https://github.com/bambamboole/laravel-oidc-client/commit/e35cf371e150e0ad522f67b3fd25203c6f124c4d))
* back-channel logout endpoint ([3f5cb52](https://github.com/bambamboole/laravel-oidc-client/commit/3f5cb52cad3da1587740913eb3d528a684ba37c1))
* complete the oidc callback with code exchange and guard login ([0d51024](https://github.com/bambamboole/laravel-oidc-client/commit/0d51024fb80b9f15ffdaeafe81efd8e6d46d9bef))
* enforcement middleware auto-appended when enabled ([e3a0ded](https://github.com/bambamboole/laravel-oidc-client/commit/e3a0ded4bba6a79061f07ecd9d28921e050d4e4a))
* LogoutTokenValidator + back-channel logout config ([48572c5](https://github.com/bambamboole/laravel-oidc-client/commit/48572c5bbf81d0e25edeba7eb3633cb039dc05a5))
* OIDC relying-party module (discovery, PKCE, callback, id_token validation, guard login) ([d3d753e](https://github.com/bambamboole/laravel-oidc-client/commit/d3d753ea20d6601c41951480826829fef6c8e268))
* Phase 3b — back-channel logout (relying party) ([db45afb](https://github.com/bambamboole/laravel-oidc-client/commit/db45afb35ae5732da4013c755696c56f32a3aa8b))
* record sid and session pointer at login ([7c64946](https://github.com/bambamboole/laravel-oidc-client/commit/7c649465021483cb7629c7474c20612a6df9ca5f))


### Bug Fixes

* address whole-branch review findings ([2c342f8](https://github.com/bambamboole/laravel-oidc-client/commit/2c342f8a5ed4d3dd758131521eb5de0b6e93cca7))
* fail closed across oidc session boundaries ([585f7f5](https://github.com/bambamboole/laravel-oidc-client/commit/585f7f5328a13d69a8ebbb5ed7d5ee900ef40a03))
* fail closed across oidc session boundaries ([170cf16](https://github.com/bambamboole/laravel-oidc-client/commit/170cf1649a724a45737a31d07e1db230df83f5eb))
* harden logout token validation and cover disabled backchannel-logout paths ([dd350bf](https://github.com/bambamboole/laravel-oidc-client/commit/dd350bf58f95190fc3b5a2d24e4b8895d32ec78c))


### Refactoring

* configure routes via unified handler config ([8ee4448](https://github.com/bambamboole/laravel-oidc-client/commit/8ee4448568e8c07c0f3aedccf3ca71c0b77906d7))
* extract shared token validation, backchannel store, and guard accessors ([07d53df](https://github.com/bambamboole/laravel-oidc-client/commit/07d53df9a369c89d362069b4a8b6dce5856fcf6c))
* register routes via OidcClientManager::routes() ([11b71cc](https://github.com/bambamboole/laravel-oidc-client/commit/11b71cc60c9b8113b36550ea1a506f893adcb59f))


### Documentation

* add Starlight docs content, published through the laravel-oidc docs site ([5f53d3c](https://github.com/bambamboole/laravel-oidc-client/commit/5f53d3cac94329377283060357de921067ec4add))
