# Changelog

All notable changes to `bambamboole/laravel-oidc` are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) (pre-1.0: minor versions may carry
breaking changes).

## [0.4.0](https://github.com/bambamboole/laravel-oidc/compare/v0.3.0...v0.4.0) (2026-07-19)


### Features

* add capability-scoped token triggers ([5bca438](https://github.com/bambamboole/laravel-oidc/commit/5bca4384c5a9515c09707225560962f8fa754a26))
* add oidc:install-self command for one-shot self-SSO setup ([d9806a9](https://github.com/bambamboole/laravel-oidc/commit/d9806a9992461f6f9d21a550b989eaf06b578378))
* oidc:install-self command for one-shot self-SSO setup ([0386155](https://github.com/bambamboole/laravel-oidc/commit/0386155560b6a0232846cc5904eb560ab932c055))


### Bug Fixes

* align token trigger guidance and coverage ([c5073e8](https://github.com/bambamboole/laravel-oidc/commit/c5073e8b84762a3fb663266d4a676f7e5278d30f))
* clean up OIDC publishing defaults ([a9b2076](https://github.com/bambamboole/laravel-oidc/commit/a9b207665d886f78fee6077b0882d3ed20543e50))
* clean up OIDC publishing defaults ([f6e67dd](https://github.com/bambamboole/laravel-oidc/commit/f6e67dd67763856eaa4d217e6cc7fe507318a032))
* make access-token warning context-neutral ([d835934](https://github.com/bambamboole/laravel-oidc/commit/d83593436c5d183fa58c47a0a9cd83c5134c1325))
* preserve OIDC metadata for programmatic logins ([3c6036b](https://github.com/bambamboole/laravel-oidc/commit/3c6036b45b3b8c6e793add758c7b3fe00343cfa1))
* reserve the oidc session claim ([d6c10df](https://github.com/bambamboole/laravel-oidc/commit/d6c10dfee3f5b062672fec0049924d4f9ef84e29))
* retain sessions until logout delivery is safe ([c6631e7](https://github.com/bambamboole/laravel-oidc/commit/c6631e7690ab794341191168b099df1284dc6f8b))


### Refactoring

* centralize access-token claim decisions ([b72fc6c](https://github.com/bambamboole/laravel-oidc/commit/b72fc6c4250931cab5dfde69ca2b877abfc5b864))
* replace legacy claim hooks with token triggers ([1a1fea3](https://github.com/bambamboole/laravel-oidc/commit/1a1fea3462e5db243448d9adb2b32951837ce71a))


### Documentation

* clarify session maintenance ordering ([67f37e1](https://github.com/bambamboole/laravel-oidc/commit/67f37e13e064dcb57febd19a653b31ffb4735966))

## [0.3.0](https://github.com/bambamboole/laravel-oidc/compare/v0.2.0...v0.3.0) (2026-07-18)


### ⚠ BREAKING CHANGES

* the eleven incremental migrations are replaced by seven consolidated ones (one create per oidc_* table plus a single oauth_clients alter migration). Existing installs must delete previously published oidc_* package migrations and re-publish; the resulting schema is identical.

### Features

* add Apple social provider with ES256 client secret and form_post ([7d1ae4a](https://github.com/bambamboole/laravel-oidc/commit/7d1ae4a7cd48f6800192ac3afd6649fd077237ac))
* add authorizeAndApprove flow helper with PKCE and result DTO ([fd65f72](https://github.com/bambamboole/laravel-oidc/commit/fd65f72c0ee34e5c778450e621739beb44abc56c))
* add generic OIDC social provider with discovery and id_token verification ([c022165](https://github.com/bambamboole/laravel-oidc/commit/c022165f0e42a817c5345681fd2e16a9c8776382))
* add Google and GitHub social providers ([98936e0](https://github.com/bambamboole/laravel-oidc/commit/98936e0e6fe0240505b8f76a90b8bbb3eb95f7e1))
* add InteractsWithOidc testing trait with identity and client helpers ([0139e48](https://github.com/bambamboole/laravel-oidc/commit/0139e4856faa149c7b1b91420061939fa5433897))
* add issueTokenFor test helper minting real signed tokens ([68d72f7](https://github.com/bambamboole/laravel-oidc/commit/68d72f777cbb1a0fca5043af0f9df4f43ca34121))
* add oidc_social_accounts table and SocialAccount model ([0ac31c2](https://github.com/bambamboole/laravel-oidc/commit/0ac31c20d4be88050f544d41760517d23bd4b8db))
* add public atomic env-writer service ([edf1869](https://github.com/bambamboole/laravel-oidc/commit/edf18693e356d626b634714ba6d1a62250e3a518))
* add rollback and provider env accessors to provisioning result ([bc21ee7](https://github.com/bambamboole/laravel-oidc/commit/bc21ee7ba70aa0f76c7713cc2f9125acf91a5618))
* add social account linking and unlinking ([dcd9ba8](https://github.com/bambamboole/laravel-oidc/commit/dcd9ba80f81eb7091e085aa88e31782c83818cfb))
* add social account resolution (linked, link-by-email, JIT provisioning) ([40bd95c](https://github.com/bambamboole/laravel-oidc/commit/40bd95cddda85f3b2f70813f7140a4d666c864d7))
* add social login core (contract, OAuth2 base provider, routes scaffold) ([7dc36f6](https://github.com/bambamboole/laravel-oidc/commit/7dc36f6e4f0783983368452401a8c6f4c8e09282))
* add social provider registry with config wiring and facade seams ([5cbe167](https://github.com/bambamboole/laravel-oidc/commit/5cbe1671e3f017060056e2fb57037efb0eeb9778))
* add social redirect endpoint and form_post callback bounce ([71fe29b](https://github.com/bambamboole/laravel-oidc/commit/71fe29bc36be52183414634adedeb0b1a08c5068))
* complete social login callback with pipeline, MFA parity, and session claims ([36c590e](https://github.com/bambamboole/laravel-oidc/commit/36c590ecdd0f37e2076a6171dfd90f6011706e50))
* composable installer primitives (provisioning + env writing as services) ([84b04b1](https://github.com/bambamboole/laravel-oidc/commit/84b04b1224013790f1350f33f816c60e7855de91))
* consolidate package migrations into one per table ([48fa807](https://github.com/bambamboole/laravel-oidc/commit/48fa80782e1102ed014db7e96967b50a4da1cfc8))
* extract signing-key generation primitive ([8fd4192](https://github.com/bambamboole/laravel-oidc/commit/8fd41923d2e151fbe948ee51c48e973278fb4222))
* testing kit + keyless-boot safety ([5e6c3ca](https://github.com/bambamboole/laravel-oidc/commit/5e6c3caf63f499c48d21a2544459c8c3f47e85b0))


### Bug Fixes

* fail actionably when the authorization view returns non-JSON ([4ed5f29](https://github.com/bambamboole/laravel-oidc/commit/4ed5f29511b3b12231ff0d4b64bea96478329867))
* harden testing kit failure affordances ([76f9c24](https://github.com/bambamboole/laravel-oidc/commit/76f9c24e984aa08b2526ad38fd8830a448517464))
* preserve .env file permissions across atomic write ([8b57ec6](https://github.com/bambamboole/laravel-oidc/commit/8b57ec636bb03d7fa21f98b2082edf7512a61407))
* read first-party client config at call time instead of construction ([48d5a18](https://github.com/bambamboole/laravel-oidc/commit/48d5a189c6a67a7271c5894327aa2a9ec9a8ddb1))
* reject non-string state and code callback parameters without warnings ([fd8bffc](https://github.com/bambamboole/laravel-oidc/commit/fd8bffc25b9ec3f7929c53b2abb6b848ba6b7a97))
* resolve the token encrypter lazily so keyless boots survive ([5f89f39](https://github.com/bambamboole/laravel-oidc/commit/5f89f39eaeea11014c88de8ed41625a4dd1dfe1e))
* wrap GitHub profile fetch failures in the social auth exception ([1fcd61a](https://github.com/bambamboole/laravel-oidc/commit/1fcd61ad0014a7451cee27eb7b4db04294c0c9b1))
* wrap OIDC metadata transport failures and cover fail-closed verification paths ([a479caa](https://github.com/bambamboole/laravel-oidc/commit/a479caa85c0906efaf3fc5a70a913bb39a409d05))


### Refactoring

* consume env-writer service and drop WritesEnvFile trait ([679d0f6](https://github.com/bambamboole/laravel-oidc/commit/679d0f6f1958bcb3c73af9d18916825b9d73b9ad))
* tighten testing kit comments to repo guidelines ([1150457](https://github.com/bambamboole/laravel-oidc/commit/11504575dabe4529625cb6003e0039d852ac935c))


### Documentation

* add social login documentation ([9e8b77d](https://github.com/bambamboole/laravel-oidc/commit/9e8b77d5a28ccf3149613eadc7b40e0960d5003e))
* correct the unlink JSON response description ([2a5e39b](https://github.com/bambamboole/laravel-oidc/commit/2a5e39b12cd117fd43544bb6adad733a2cc46faa))
* document testing kit side effects and audience-token scope ([8eae818](https://github.com/bambamboole/laravel-oidc/commit/8eae818de344787d5d885116b0e4d1567e739899))
* document the testing kit and keyless-boot guarantee ([38ec079](https://github.com/bambamboole/laravel-oidc/commit/38ec079b5b57fc9d651a066e47992f2166479997))
* drop keyless-safety callouts ([b77083c](https://github.com/bambamboole/laravel-oidc/commit/b77083c3bb678ff4647486eb69e13a49b70e172f))
* warn against firstOrCreate in social provisioning actions ([4841288](https://github.com/bambamboole/laravel-oidc/commit/4841288d7b483fe4c07365c2ebcd3a6a8452dc2e))

## [0.2.0](https://github.com/bambamboole/laravel-oidc/compare/v0.1.0...v0.2.0) (2026-07-15)


### ⚠ BREAKING CHANGES

* oidc:rotate-keys writes OIDC_* variables instead of PASSPORT_*, and the PassportKeys/PassportScopeRepository classes were renamed to SigningKeys/DefaultScopeRepository.

### Features

* access-token to context link side table ([d0d5e36](https://github.com/bambamboole/laravel-oidc/commit/d0d5e36d1eaa3eb476053c3a010c5139637e8f38))
* add AuthenticationContext amr accumulator + acr derivation ([8ce779a](https://github.com/bambamboole/laravel-oidc/commit/8ce779a16d675e05a4556647f5546da41ed0ce00))
* add credential-aware client reconciliation ([221bc45](https://github.com/bambamboole/laravel-oidc/commit/221bc45674c9eb956eb77e5e42c08ca9c67e5e29))
* add DeviceRecognizer contract and LoginEvent ([05a6ffe](https://github.com/bambamboole/laravel-oidc/commit/05a6ffe2f5c1dda9266bd4b4ab6660d010bc37d5))
* add first-party client provisioning identity ([4b177da](https://github.com/bambamboole/laravel-oidc/commit/4b177dab4b0eb027194160e05b977ce17cd118be))
* add first-party oidc client command ([cd37935](https://github.com/bambamboole/laravel-oidc/commit/cd37935bb5583dac98bc3bbc2d75bd6779bd2c35))
* add LoginApi decision and claim buffer ([2cc6a63](https://github.com/bambamboole/laravel-oidc/commit/2cc6a630ab3223cf11fa75d17e66badbecdfa62c))
* add persisted authentication-context store ([57fbce4](https://github.com/bambamboole/laravel-oidc/commit/57fbce4673a0659c13d7ea6ad02e57f0bd9987e4))
* add PostLoginPipeline with fail-closed error handling ([d3862a8](https://github.com/bambamboole/laravel-oidc/commit/d3862a82ac002d385ce82c897cfc6c1376d18ada))
* append verified factor amr after two-factor challenge ([950675e](https://github.com/bambamboole/laravel-oidc/commit/950675e71a1727815a83005ce78ca2ad02e88328))
* **auth:** add extensible multi-factor authentication ([d62d229](https://github.com/bambamboole/laravel-oidc/commit/d62d229cffa39bddcb010532d0c968e85cf489ac))
* **auth:** add extensible multi-factor authentication ([be4d3e9](https://github.com/bambamboole/laravel-oidc/commit/be4d3e9f730889ed3a9f857dba4fdcc67abff3d8))
* **auth:** add package account view seams ([a51965a](https://github.com/bambamboole/laravel-oidc/commit/a51965aef54b81465b0bd78c4d1aa7e446711d81))
* **auth:** add server self-SSO enablers ([ad27928](https://github.com/bambamboole/laravel-oidc/commit/ad27928060b714400784896c63ddc1e6879b80bb))
* **auth:** add server self-SSO enablers ([b9a8eff](https://github.com/bambamboole/laravel-oidc/commit/b9a8efffbdbcfe8b9a3e93b09cc86130e568647e))
* **auth:** emit acr/amr authentication-context claims in the id_token ([d0d039e](https://github.com/bambamboole/laravel-oidc/commit/d0d039ead6c949f5656c812892cbe7ed9cc5e99e))
* **auth:** Oidc::postLogin decision trigger + authentication-context store (Phase 2a-1) ([72ac879](https://github.com/bambamboole/laravel-oidc/commit/72ac879c60a007df1effc1f4b326a990ed937728))
* **auth:** own email verification flow ([2bc0411](https://github.com/bambamboole/laravel-oidc/commit/2bc0411f8322de734b70650200411f40ed519287))
* **auth:** own login, logout, and password confirmation ([f67da35](https://github.com/bambamboole/laravel-oidc/commit/f67da3525a9e08f0db5710c7b710a38742f1f71a))
* **auth:** own password reset flow ([5bfcf21](https://github.com/bambamboole/laravel-oidc/commit/5bfcf2140f0c9eac5b4f0090eb56b80991cb567d))
* **auth:** own registration through package action seam ([ebe5f69](https://github.com/bambamboole/laravel-oidc/commit/ebe5f6903403888407b8d691900f168235d4196e))
* **auth:** package login, logout, and password confirmation ([f6e78eb](https://github.com/bambamboole/laravel-oidc/commit/f6e78ebf2fc93ebe1f1657182b956bc6b51603ee))
* **auth:** package-owned email verification flow ([ced7901](https://github.com/bambamboole/laravel-oidc/commit/ced79016328fb50f4d3f503a958c1936dc850b04))
* back-channel logout client registration and discovery ([0b23f2d](https://github.com/bambamboole/laravel-oidc/commit/0b23f2d13d48da18c66daaa39e08f18c3cafe2ad))
* back-channel logout fan-out job and notifier ([9bebc6d](https://github.com/bambamboole/laravel-oidc/commit/9bebc6d078e1593cb0cc16b8dc1dec9a5172663b))
* bake amr into the authorization code and thread it to the id_token ([cfd14ef](https://github.com/bambamboole/laravel-oidc/commit/cfd14ef01cbae420c8601f8dc58ffa20b5b9b226))
* buffer access-token claims through the login ceremony ([be917ac](https://github.com/bambamboole/laravel-oidc/commit/be917ac67d96e9afef5d230fba2974075880feda))
* carry amr through IdTokenResponse with reset-after-read ([880a19b](https://github.com/bambamboole/laravel-oidc/commit/880a19b089e91d57dd42582bf54d21a6e7d12fc3))
* emit amr and derived acr claims in IdTokenBuilder ([8ea4ade](https://github.com/bambamboole/laravel-oidc/commit/8ea4aded3e0826b5a8ab5c9f8650c6d3f79442d9))
* emit and link access-token claims on fresh issuance ([cc209f6](https://github.com/bambamboole/laravel-oidc/commit/cc209f65fa96e18bc526b0f58dff034befb4f56e))
* emit the sid claim in id_tokens ([47e6ae5](https://github.com/bambamboole/laravel-oidc/commit/47e6ae5e08d708a81feb9325326766e97867507f))
* expiry sweep and session pruning ([4ee49aa](https://github.com/bambamboole/laravel-oidc/commit/4ee49aad1d08102daacc0f4dbcff743c57adc106))
* expose credential-aware reconciliation ([6e5211f](https://github.com/bambamboole/laravel-oidc/commit/6e5211f7d31f51da81e430ffaf4b844115d33a50))
* expose first-party client provisioning ([1ca7e30](https://github.com/bambamboole/laravel-oidc/commit/1ca7e30ecc5f8b645e2682a6353320f3df483bc3))
* expose Oidc::postLogin and bind the pipeline ([65ed07f](https://github.com/bambamboole/laravel-oidc/commit/65ed07fe31ced7e33309effeb8ff282e142ee8b6))
* fan out back-channel logout on logout and end-session ([7ca9f5d](https://github.com/bambamboole/laravel-oidc/commit/7ca9f5db7a2e2f39269ed754b3c3597850d45f29))
* first-class oidc_sessions with SessionRegistry ([77ed711](https://github.com/bambamboole/laravel-oidc/commit/77ed711c652b11263516935274d8d7b408c1340b))
* LoginApi::setAccessTokenClaim with shared protocol guard ([59ad46b](https://github.com/bambamboole/laravel-oidc/commit/59ad46b6d03d4a434abf546efb76689ae09a939b))
* LogoutTokenBuilder ([af42a9c](https://github.com/bambamboole/laravel-oidc/commit/af42a9c76f79fdf92fd0f68478e71a59aa2ff2f7))
* make OIDC_PRIVATE_KEY/OIDC_PUBLIC_KEY the canonical signing key env vars ([beab4fc](https://github.com/bambamboole/laravel-oidc/commit/beab4fc5de658d82f543e67f7cba9716d7559176))
* OIDC conformance pass + env-based key rotation ([9dc4ea5](https://github.com/bambamboole/laravel-oidc/commit/9dc4ea5e43cf4209e9691c4d042eec5dbf97e18c))
* oidc:prune-authentication-contexts command ([5d42d86](https://github.com/bambamboole/laravel-oidc/commit/5d42d86d9c072629df6b0eb3f83df91c5fd6888f))
* **oidc:** add CheckAudience resource-server middleware ([134e964](https://github.com/bambamboole/laravel-oidc/commit/134e9643deb8a77783d096a1b71404f5e9f5f609))
* **oidc:** add claims bag with per-artifact protected-claim filtering ([a0846e3](https://github.com/bambamboole/laravel-oidc/commit/a0846e39a30d02a500f0c8ce9b65deea2176b02b))
* **oidc:** add claims resolver contract with attribute-based default ([59c6c1a](https://github.com/bambamboole/laravel-oidc/commit/59c6c1a322ccfd81089e38959e5975b06c9d6e89))
* **oidc:** add Oidc::issueScopedToken facade method ([47c3fa7](https://github.com/bambamboole/laravel-oidc/commit/47c3fa7599cb3090a6c62d5a9540c9c4e946c6a5))
* **oidc:** add openid-configuration discovery document ([6a70905](https://github.com/bambamboole/laravel-oidc/commit/6a70905f2d9cd0f3e7174ce16aa23ad34beef181))
* **oidc:** add per-trigger claim hook registry, contexts, and facade ([5acb597](https://github.com/bambamboole/laravel-oidc/commit/5acb597f114f7206a8a7aea35e2a0afd430fe2d4))
* **oidc:** add programmatic access-token minter ([7cb3cdd](https://github.com/bambamboole/laravel-oidc/commit/7cb3cdd2058b12dbfbdf2704c210b80815309cea))
* **oidc:** add rfc 7662 introspection and rfc 7009 revocation ([021f2d7](https://github.com/bambamboole/laravel-oidc/commit/021f2d7455228f6a9486d3614100d67e08eccd40))
* **oidc:** add rfc 8693 token-exchange grant with policy gating ([21162cf](https://github.com/bambamboole/laravel-oidc/commit/21162cf23a0a12cf712dfca2e45bbf616c5ed375))
* **oidc:** add rp-initiated logout with registered redirect uris ([da34bfb](https://github.com/bambamboole/laravel-oidc/commit/da34bfbdf2d28afdc2a8e39221d24be3e5d0c7aa))
* **oidc:** add scope contract with passport-backed default repository ([455f9dc](https://github.com/bambamboole/laravel-oidc/commit/455f9dc7c5bd1bb1a8a84a80326016db481231ad))
* **oidc:** add scope-filtered userinfo endpoint ([14592dd](https://github.com/bambamboole/laravel-oidc/commit/14592dd38de2913a6f48a71b9743b22681c87ba5))
* **oidc:** add session token provider seam with minting default ([f715621](https://github.com/bambamboole/laravel-oidc/commit/f71562120723c777850922a8197da80ed8871ab9))
* **oidc:** add token exchanger service and issued-token value object ([a98a70d](https://github.com/bambamboole/laravel-oidc/commit/a98a70d79210f67d17bb4bb55925215273b2f31d))
* **oidc:** add token-exchange policy contract and default with audience allowlist ([466cf81](https://github.com/bambamboole/laravel-oidc/commit/466cf811062c0958374c69bea646aae8ad393095))
* **oidc:** bridge the scope contract into league's authorization server ([181fd3e](https://github.com/bambamboole/laravel-oidc/commit/181fd3e82aa4356f4963931db93f984ee4d6f302))
* **oidc:** build rs256 id_tokens with full claim set ([08b8acc](https://github.com/bambamboole/laravel-oidc/commit/08b8acca347c7d8b6fab31cc3057d6b2e8069296))
* **oidc:** complete discovery metadata and derive endpoints from the issuer ([b1e15bb](https://github.com/bambamboole/laravel-oidc/commit/b1e15bbc0886613561c485433287d8b15c68f1f0))
* **oidc:** emit rfc 9068 at+jwt access tokens ([5b6f777](https://github.com/bambamboole/laravel-oidc/commit/5b6f777e9bfaba73c787369b736c21e937e25b8b))
* **oidc:** enforce max_age and record auth_time on login ([5a01605](https://github.com/bambamboole/laravel-oidc/commit/5a01605ec4778a2f5733935d7e0aab4b368d0cb2))
* **oidc:** env-based signing key rotation via oidc:rotate-keys ([450a7b4](https://github.com/bambamboole/laravel-oidc/commit/450a7b424d3281d29301a1d0688f2421db28a4fe))
* **oidc:** fire access-token claim hook by grant type ([e4a2c24](https://github.com/bambamboole/laravel-oidc/commit/e4a2c24fc468ab3577c62bf87d554ff73c25fdb9))
* **oidc:** fire id_token and userinfo claim hooks ([440a736](https://github.com/bambamboole/laravel-oidc/commit/440a7366d3f600c12d48f92f1adf13b1c2ba68d8))
* **oidc:** issue id_tokens via oidc auth-code grant with owned oauth routes ([2771724](https://github.com/bambamboole/laravel-oidc/commit/27717245d9cb6861fa8e85b69db8fcbeaee7483f))
* **oidc:** make oauth route prefix and resource guard configurable ([c57333a](https://github.com/bambamboole/laravel-oidc/commit/c57333afda93e37a563fdc3bd04e7bf6aad84ad0))
* **oidc:** scaffold laravel-oidc package with testbench harness ([c7e7caf](https://github.com/bambamboole/laravel-oidc/commit/c7e7caf231a395d69ee9fbed1a7964e61b7bc424))
* **oidc:** serve passport public keys as jwks with rfc 7638 kids ([a19de5a](https://github.com/bambamboole/laravel-oidc/commit/a19de5a9a72361440e6b80427a9e753d106596cb))
* per-flow access-token TTLs and shortened interactive default ([8b1bf10](https://github.com/bambamboole/laravel-oidc/commit/8b1bf106f311105968da47578a531f69194a0ed1))
* per-flow token lifetime config ([d64eef7](https://github.com/bambamboole/laravel-oidc/commit/d64eef7929977ac6679db9e195267c94c99e6855))
* persist auth context and emit id_token claims through it ([3625e92](https://github.com/bambamboole/laravel-oidc/commit/3625e928f34c4c574e0bea009aaebe61e17de095))
* Phase 2a-2 — refresh reissue + access-token claims + session lifetime ([2283c31](https://github.com/bambamboole/laravel-oidc/commit/2283c31191cda85ecb218b481940d5c89b165823))
* Phase 3a — back-channel logout (provider) + first-class sessions ([560f5e5](https://github.com/bambamboole/laravel-oidc/commit/560f5e5baf6dce37dcd2dc1dae41f3f6336e26fb))
* pin auth context to its login session ([6824ee9](https://github.com/bambamboole/laravel-oidc/commit/6824ee97130db0baa6f929fa57f7437c32acdaae))
* provision first-party oidc clients ([7dd3b3d](https://github.com/bambamboole/laravel-oidc/commit/7dd3b3d91afc0f4b38efb5cf04145fc978546054))
* record pwd authentication method on password login ([b30ecfc](https://github.com/bambamboole/laravel-oidc/commit/b30ecfca2df783b8476f57a0a779e11f4c2c2d48))
* record session participants on authorization ([659430d](https://github.com/bambamboole/laravel-oidc/commit/659430d141be596e9098b945b067ecc57ba035ce))
* refresh reissue with deny-on-expiry via OidcRefreshTokenGrant ([6673eac](https://github.com/bambamboole/laravel-oidc/commit/6673eacb919ecf001f3f40d2b5f8b337a2c069b1))
* **routing:** unified config-driven route handlers ([150dff9](https://github.com/bambamboole/laravel-oidc/commit/150dff9fa3d06547171993e7eb9521705de843dd))
* **routing:** unified config-driven route handlers ([5464606](https://github.com/bambamboole/laravel-oidc/commit/5464606fa48966908942adb3b31c0d37e0916110))
* run the postLogin pipeline in the login ceremony ([e26ceba](https://github.com/bambamboole/laravel-oidc/commit/e26ceba00bf5c243b1a17ac063f7be141d85b2c4))
* stamp expires_at and always persist the auth context ([c8205c2](https://github.com/bambamboole/laravel-oidc/commit/c8205c24da04050a798bebbe8d6de7f8b230f1bc))
* start an oidc session on login ([57a734f](https://github.com/bambamboole/laravel-oidc/commit/57a734f4941c66fb51de024fa1cc1901551ecc11))
* tie refresh deny-on-expiry to the login session ([00a198e](https://github.com/bambamboole/laravel-oidc/commit/00a198ee98ec5a01348c6366b222a6ee84a66d2b))
* verify first-party client credentials ([8f5493d](https://github.com/bambamboole/laravel-oidc/commit/8f5493de8f80089975c9b255ed925fd36f5dae09))


### Bug Fixes

* allow valid urn optional components ([b5ff9eb](https://github.com/bambamboole/laravel-oidc/commit/b5ff9ebada354697f97cc1646f4cdfc3ab75b4bc))
* **auth:** satisfy password reset static analysis ([c71b280](https://github.com/bambamboole/laravel-oidc/commit/c71b280990ee7fe55e693b36bd4c9738b09f175c))
* clear pendingContext at entry to stop cross-request leaks under Octane ([a8424f0](https://github.com/bambamboole/laravel-oidc/commit/a8424f068e6db6e98b6f13fdf869b0cb0181e1a9))
* **docs:** prepend base to the Get started hero link so it doesn't 404 ([19132ac](https://github.com/bambamboole/laravel-oidc/commit/19132ac511672130af76a4ed9acfd372111d32bf))
* fully validate audience URIs ([85cf516](https://github.com/bambamboole/laravel-oidc/commit/85cf516963b826a29807b069621c1df098ba7322))
* harden first-party provider configuration ([71a63d4](https://github.com/bambamboole/laravel-oidc/commit/71a63d4e2396ad5e472589a2b84edd53ef78be9f))
* harden OIDC token-exchange and resource-server security ([ffaa09b](https://github.com/bambamboole/laravel-oidc/commit/ffaa09b9e6e36eca6fa89e42e6819c12b947d60f))
* make BackChannelLogoutNotifier::notify idempotent ([9c12e77](https://github.com/bambamboole/laravel-oidc/commit/9c12e77c4d0e608375205b9c96452d4d4558b362))
* **oidc:** correct namespace typo in claims resolver test ([6054d2d](https://github.com/bambamboole/laravel-oidc/commit/6054d2d0ec6e8ada09bd64a5d22ce399e55896e5))
* **oidc:** harden logout, max_age, wildcard scope, and id_token state ([6f5bcec](https://github.com/bambamboole/laravel-oidc/commit/6f5bcec24245720070e2b9571c9107eaa301bd30))
* **oidc:** harden session-token listener and bind root token to user ([a5be8bc](https://github.com/bambamboole/laravel-oidc/commit/a5be8bca05efb3fee4d44c6c1abd55e60f78eedc))
* **oidc:** nest prior act claim on chained token exchange per RFC 8693 ([f6200fb](https://github.com/bambamboole/laravel-oidc/commit/f6200fb63faccb8daa837ff03a988a689b9e7068))
* **oidc:** preserve existing query strings in post-logout redirects ([147eaa8](https://github.com/bambamboole/laravel-oidc/commit/147eaa8437c7192223c6b0902dc17d35e4782b22))
* **oidc:** preserve subject scopes when exchange omits the scope param ([27b313a](https://github.com/bambamboole/laravel-oidc/commit/27b313ab399c2492c7042d94c927165b21aabb29))
* **oidc:** reject expired subject tokens in token exchange ([c6703b0](https://github.com/bambamboole/laravel-oidc/commit/c6703b0d1f320565e68f0d1c1a04810271a83b32))
* **oidc:** require PKCE for all clients per OAuth 2.1 ([3cb5189](https://github.com/bambamboole/laravel-oidc/commit/3cb51893fa0cc39f2fe86778c1fcac88fa30f5ab))
* **oidc:** return RFC 6750 bearer + RFC 6749 client-auth error responses ([d9acf91](https://github.com/bambamboole/laravel-oidc/commit/d9acf91dc48df96b3cc7b006df4af0ee48ea710c))
* **oidc:** support both passport session-request storage formats; raise dep floors ([00ae69e](https://github.com/bambamboole/laravel-oidc/commit/00ae69eaf392f52ce0ab0620ac37f825d8fd8fbd))
* preserve adoption target during recovery ([55d71fb](https://github.com/bambamboole/laravel-oidc/commit/55d71fb240b13a0c4a6a74a6fa5d4b7037d59789))
* prevent extra access-token claims from overriding structural claims ([572c3f1](https://github.com/bambamboole/laravel-oidc/commit/572c3f1b9c3c30a3dee2a5cc3357a31573cf57c3))
* redact client secrets from traces ([483e635](https://github.com/bambamboole/laravel-oidc/commit/483e635245f1a623b1e3fdfa84f88301c717b094))
* reset buffered id_token claims per login and cover postLogin claims e2e ([2f24ded](https://github.com/bambamboole/laravel-oidc/commit/2f24ded7e2e59eca1c209503600e12fe0f6d5aa2))
* restore session isStarted guard, safe-chunk expiry sweep, cover job delivery ([a2d18dd](https://github.com/bambamboole/laravel-oidc/commit/a2d18dd4849a03f6409fc56dfebe55ea7a2a7a7c))
* **test:** use array sessions in package harness ([ec6d403](https://github.com/bambamboole/laravel-oidc/commit/ec6d4037e84559dc3bfbdfcb3496814812f19511))
* validate urn audiences compatibly ([0a7e78f](https://github.com/bambamboole/laravel-oidc/commit/0a7e78f71a9ffbb574c8e123341edb26727284f1))


### Performance

* slim oidc_access_token_contexts for scale ([f8cc97d](https://github.com/bambamboole/laravel-oidc/commit/f8cc97d393e1166cc25fa39b93260353ef376120))


### Refactoring

* **auth:** use invokable authentication actions ([c7c3edd](https://github.com/bambamboole/laravel-oidc/commit/c7c3eddb858f0c67ad1b79802594892097f3ec0c))
* drop hook paths superseded by the context store ([58f565d](https://github.com/bambamboole/laravel-oidc/commit/58f565d815e08423e1c6b7cdcfac511dd3eee592))
* **oidc:** delegate token-exchange grant to the exchanger service ([94511e7](https://github.com/bambamboole/laravel-oidc/commit/94511e77fd8b2a873e7407f9371508f263f1c3bf))
* **oidc:** derive jwks via phpseclib for multi-format key parsing ([d99c9e4](https://github.com/bambamboole/laravel-oidc/commit/d99c9e42f17223b9fd6088423c3c344e74015ba7))
* **oidc:** make CheckAudience a self-contained resource-server validator ([4dbf1ec](https://github.com/bambamboole/laravel-oidc/commit/4dbf1ec949405ebbd6485ae74b5b2ca8923ebe79))
* **oidc:** share request-grant-type resolver and verify tokens once in CheckAudience ([a55bf93](https://github.com/bambamboole/laravel-oidc/commit/a55bf93e50901d55299e8130ea62c23ae957f307))
* rename AuthenticationContext accumulator to AuthenticationMethods ([b3aa03c](https://github.com/bambamboole/laravel-oidc/commit/b3aa03c5bc19549ed52ca08ce5ae288033243023))
* **routing:** strip prefixes and unify handler names under oidc ([169bc65](https://github.com/bambamboole/laravel-oidc/commit/169bc65263e6ad373431cc025ce6eafaca915272))
* unify first-party client configuration ([44aee67](https://github.com/bambamboole/laravel-oidc/commit/44aee67645ab8b13bc8e5a3f3e11527628e19cbc))


### Documentation

* add self-SSO + auth-engine design and handoff notes ([314aa6e](https://github.com/bambamboole/laravel-oidc/commit/314aa6e7fae3daab08aaaadf572f406adcf6115c))
* **auth:** plan package account flows ([97e0d30](https://github.com/bambamboole/laravel-oidc/commit/97e0d30142836fa9fbe3fefd03ababf4609af768))
* clarify Reconciled outcome when a client secret is supplied ([8f7d0d3](https://github.com/bambamboole/laravel-oidc/commit/8f7d0d322ab61ebb3d369a71ac34d40ff72a3c20))
* document Oidc::postLogin ([eeaad0b](https://github.com/bambamboole/laravel-oidc/commit/eeaad0bbf02da425b5dadeee67a5f8cfa595e893))
* document scheduled pruning of token-path tables ([6ba8b39](https://github.com/bambamboole/laravel-oidc/commit/6ba8b3957854147b9b7cd87717eb6add014b1342))
* explain credential-aware reconciliation ([12f1e1a](https://github.com/bambamboole/laravel-oidc/commit/12f1e1ac0fcb8a1c0ca27ea9ff3f1caa9ac8bd6b))
* **oidc:** add package readme ([09bc566](https://github.com/bambamboole/laravel-oidc/commit/09bc5665d792be518bb0036bf6496a5cc0f969e4))
* **oidc:** annotate integration tests with governing RFC/spec references ([1052d4a](https://github.com/bambamboole/laravel-oidc/commit/1052d4a683c3a4c23bdd11789dd7f54c783539b2))
* **oidc:** document conformance batch (pkce, error responses, discovery, config) ([90306ef](https://github.com/bambamboole/laravel-oidc/commit/90306efb493bac00cf5c66727a2c31e6e9ab6204))
* **oidc:** document env-based key rotation ([6491a1d](https://github.com/bambamboole/laravel-oidc/commit/6491a1d02c137dfb0dca4f4c8f3c1a32cc153626))
* **oidc:** document session token provider and scoped-token issuance ([58c1467](https://github.com/bambamboole/laravel-oidc/commit/58c14673449125222ed6cb2f3f5471d5827ec1ab))
* **oidc:** document token exchange; test: starter-kit exchange integration ([a83ef13](https://github.com/bambamboole/laravel-oidc/commit/a83ef13af7a73b7173cb2e4f056f0423182cf077))
* **oidc:** note max_age forced-relogin residual in threat model ([5e7614a](https://github.com/bambamboole/laravel-oidc/commit/5e7614aea8101b246c6360ce6b8d4ce739ccc42b))
* **oidc:** note RefreshContext access-token writer ([10f9b3a](https://github.com/bambamboole/laravel-oidc/commit/10f9b3a22e431c4b56a5560b624218207ea313f7))
* reframe as an OIDC auth server, widen tables, and add mermaid flow diagrams ([64eace8](https://github.com/bambamboole/laravel-oidc/commit/64eace8c954d23277b10b5ba5ff0e84134b781ab))
* schedule the back-channel logout sweep ([bea7ed4](https://github.com/bambamboole/laravel-oidc/commit/bea7ed43be30a71b6e0f0abae6820f25b9bc11e8))

## [Unreleased]

### Added

- **Auth engine (Fortify-equivalent):** package-owned login, registration, password reset,
  email verification, and password confirmation, driven by view seams (`Oidc::loginView()`,
  `registerView()`, …) and action seams (`Oidc::createUsersUsing()`,
  `resetUserPasswordsUsing()`). All routes are named `identity.*` and run through a dedicated
  `identity` session guard.
- **Multi-factor authentication:** a `FactorProvider` registry shipping TOTP
  (`pragmarx/google2fa`), recovery codes, and passkeys (`laravel/passkeys`), with challenge and
  management flows.
- **Post-login pipeline:** `Oidc::postLogin()` decision hook (`requireMfa()` / `deny()` /
  `setIdTokenClaim()`), fail-closed, plus `acr` / `amr` emission on the `id_token`.
- **OIDC back-channel logout:** relying parties that register a `backchannel_logout_uri` are
  notified when a session ends or hits its absolute lifetime, dispatched by
  `oidc:dispatch-expired-session-logouts`. Advertised in discovery.
- **First-party client provisioning:** `oidc:client --first-party` and
  `Oidc::provisionFirstPartyClient()` for idempotent provisioning of the confidential client
  used by the browser-fetch flow.
- **Documentation site** built with Starlight (`npm run docs:dev`), covering the OIDC provider,
  the auth engine, and advanced topics.
- **Discovery document completeness:** `/.well-known/openid-configuration` now advertises
  `client_credentials` in `grant_types_supported`, `response_modes_supported: ["query"]`,
  `claims_parameter_supported: false`, `request_parameter_supported: false`,
  `request_uri_parameter_supported: false`, and
  `introspection_endpoint_auth_methods_supported` / `revocation_endpoint_auth_methods_supported`.
  All advertised endpoint URLs are now derived from the configured `issuer` origin rather than
  the incoming request.
- **Configurable route prefix:** the `/oauth/*` routes this package registers now honour
  `config('passport.path', 'oauth')` instead of hardcoding `oauth`.
- **`oidc.api_guard` config** (`OIDC_API_GUARD`, default `api`): the guard the userinfo
  endpoint authenticates against, previously hardcoded to `api`.
- **`oidc:rotate-keys` command:** generates a new RSA signing keypair and writes
  `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY`, and `OIDC_PREVIOUS_PUBLIC_KEY` into `.env`
  (or, with `--print`, to stdout for a secrets manager), rolling the current public key into
  `OIDC_PREVIOUS_PUBLIC_KEY`. That previous key is served in JWKS via
  `config('oidc.additional_public_keys')` (deduplicated by `kid`) so tokens signed before the
  rotation keep validating until they expire; remove it once they have. Keys live entirely in
  env variables — no key files, no database.

### Fixed

- **PKCE required for all clients** (OAuth 2.1 §4.1.1/§7.6): the authorization endpoint now
  rejects any authorization request missing a `code_challenge`, for confidential clients too,
  not only public ones.
- **RFC-shaped error responses:** the userinfo endpoint and the `CheckAudience` middleware now
  return RFC 6750 bearer-token errors (`WWW-Authenticate: Bearer error="..."` plus a JSON
  `{"error": "invalid_token"}` / `{"error": "insufficient_scope"}` body) instead of bare
  `401`/`403` aborts; introspection and revocation now return RFC 6749 §5.2-shaped
  `{"error": "invalid_client"}` JSON bodies (still `401` with `WWW-Authenticate: Basic`) instead
  of a bare string/no body.
- **Chained token exchange nests `act`** (RFC 8693 §4.1): exchanging an already-exchanged token
  now nests the prior `act` claim (`act.act`) instead of overwriting it, preserving the full
  actor chain.

### Changed

- Internal dedup: `AccessTokenHookRunner` and `IdTokenResponse` now share a single
  `ResolvesRequestGrantType` trait, and `CheckAudience` verifies the token's signature once
  and reuses the parsed result instead of re-parsing it to look up the token record.
- Internal cleanup pass: removed dead hook scaffolding (the unused `PostLogin`/`Refresh`
  triggers and their context classes — per-login claims are written via the post-login
  pipeline instead), and extracted shared helpers to remove duplication across the auth
  controllers (guard/home resolution), the token emitters (JWT signing config + `kid`), the
  factor providers, the introspection/revocation endpoints, and the `.env`-writing console
  commands. No behavior change.

## [0.1.0]

Initial release — an OpenID Connect provider layer on top of Laravel Passport 13.

### Added

- **OIDC core:** RS256 `id_token`s (with `at_hash`, `auth_time`, `azp`, `nonce`), a
  `/.well-known/openid-configuration` discovery document, a `/.well-known/jwks.json` endpoint
  (RFC 7638 `kid`s, phpseclib-based multi-format key parsing), a `userinfo` endpoint,
  RP-initiated logout (`/oauth/logout`), RFC 7662 introspection, and RFC 7009 revocation.
- **Full `/oauth/*` route ownership** via `Passport::ignoreRoutes()`, with a required
  `Passport::authorizationView()` consent view and `max_age` / `prompt` handling.
- **RFC 9068 access tokens:** access tokens are `application/at+jwt` JWTs carrying `iss`, `aud`,
  `sub`, `client_id`, `iat`, `nbf`, `exp`, `jti`, and a space-delimited `scope` (the legacy
  `scopes` array is retained for Passport's guard).
- **Claim hooks:** per-trigger registration (`Oidc::onPostLogin`, `onRefresh`,
  `onClientCredentials`, `onTokenExchange`, `onUserinfo`) with per-artifact writers and a
  protected-claim blocklist. Hooks must be pure claim writers.
- **RFC 8693 token exchange:** the `urn:ietf:params:oauth:grant-type:token-exchange` grant
  (confidential clients only, gated by the client's `grant_types` and an
  `allowed_exchange_audiences` allowlist), a swappable `ExchangePolicy` (audience reciprocity,
  target allowlist, monotonic scope narrowing, same-subject, lifetime cap, `act` delegation),
  and a self-contained `CheckAudience` resource-server middleware.
- **Session-token issuance:** a `SessionTokenProvider` seam (default mints a first-party root
  token at login) and `Oidc::issueScopedToken($audience, $scopes)` to derive short-lived,
  audience-scoped browser tokens — designed to power browser-direct data fetching.
- Extension contracts: `ScopeRepository`, `ClaimsResolver`, `ExchangePolicy`,
  `SessionTokenProvider`.

### Notes

- Requires PHP `^8.4`, `laravel/passport ^13.4`, `lcobucci/jwt ^5`, `phpseclib/phpseclib ^3.0.15`.
- The GitHub Actions test workflow (`.github/workflows/tests.yml`) runs the suite across
  Laravel 11/12/13 on the standalone repository.
- Known limitations (see the README): no back-channel logout, no `acr`/`amr` claims, refresh
  `id_token`s omit `auth_time`, and revoking a subject token does not cascade to already-issued
  exchanged tokens.

[Unreleased]: https://github.com/bambamboole/laravel-oidc/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/bambamboole/laravel-oidc/releases/tag/v0.1.0
