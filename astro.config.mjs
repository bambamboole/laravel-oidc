// @ts-check
import { defineConfig } from "astro/config";
import starlight from "@astrojs/starlight";
import starlightLlmsTxt from "starlight-llms-txt";
import mermaid from "astro-mermaid";

const site = process.env.SITE_URL || "https://bambamboole.github.io/laravel-oidc";
// GitHub Pages serves this project site under /laravel-oidc. Override with DOCS_BASE="/"
// if you later point a custom domain (or a user/org page) at it.
const base = process.env.DOCS_BASE || "/laravel-oidc";

export default defineConfig({
  site,
  base,
  srcDir: "./docs",
  outDir: "./dist-docs",
  publicDir: "./docs/public",
  devToolbar: { enabled: false },
  integrations: [
    // Must come before starlight so its remark plugin sees the raw ```mermaid blocks.
    mermaid({ autoTheme: true }),
    starlight({
      title: "laravel-oidc",
      description:
        "An OIDC-capable auth server for Laravel — turn your app into a full OpenID Connect identity provider.",
      lastUpdated: true,
      customCss: ["./docs/styles/custom.css"],
      plugins: [
        starlightLlmsTxt({
          projectName: "laravel-oidc",
          description:
            "laravel-oidc turns a Laravel application into an OIDC-capable auth server: signed id_tokens, discovery, JWKS, userinfo, RP-initiated and back-channel logout, RFC 7662 introspection, RFC 7009 revocation, RFC 9068 access tokens, RFC 8693 token exchange, and a complete auth engine (login, registration, password reset, email verification, TOTP/recovery/passkey multi-factor) with a post-login pipeline emitting acr/amr.",
          details:
            "Targets PHP 8.4. The OAuth2 core is Laravel Passport 13, which the package extends and reconfigures. Open source under the MIT license. Source: https://github.com/bambamboole/laravel-oidc",
        }),
      ],
      social: [
        {
          icon: "github",
          label: "GitHub",
          href: "https://github.com/bambamboole/laravel-oidc",
        },
      ],
      editLink: {
        baseUrl: "https://github.com/bambamboole/laravel-oidc/edit/main/",
      },
      sidebar: [
        {
          label: "Server",
          items: [
            {
              label: "Introduction",
              items: [
                { label: "What is laravel-oidc?", link: "/introduction/what-is-laravel-oidc/" },
                { label: "Installation", link: "/introduction/installation/" },
                { label: "Configuration", link: "/introduction/configuration/" },
                { label: "Route handlers", link: "/introduction/route-handlers/" },
              ],
            },
            {
              label: "OIDC provider",
              collapsed: true,
              items: [
                { label: "Endpoints & discovery", link: "/provider/endpoints/" },
                { label: "Scopes & claims", link: "/provider/scopes-and-claims/" },
                { label: "Custom claims & triggers", link: "/provider/claim-hooks/" },
                { label: "Access tokens (RFC 9068)", link: "/provider/access-tokens/" },
                { label: "Token exchange (RFC 8693)", link: "/provider/token-exchange/" },
                { label: "Logout", link: "/provider/logout/" },
                { label: "Key rotation", link: "/provider/key-rotation/" },
                { label: "Scheduled maintenance", link: "/provider/scheduled-maintenance/" },
              ],
            },
            {
              label: "Auth engine",
              collapsed: true,
              items: [
                { label: "Overview & seams", link: "/auth/overview/" },
                { label: "Login & logout", link: "/auth/login/" },
                { label: "Registration", link: "/auth/registration/" },
                { label: "Password reset & confirmation", link: "/auth/passwords/" },
                { label: "Email verification", link: "/auth/email-verification/" },
                { label: "Multi-factor authentication", link: "/auth/multi-factor/" },
                { label: "Social login", link: "/auth/social-login/" },
                { label: "The post-login pipeline", link: "/auth/post-login-pipeline/" },
              ],
            },
            {
              label: "Advanced",
              collapsed: true,
              items: [
                { label: "Browser-fetch (session tokens)", link: "/advanced/browser-fetch/" },
                { label: "Resource servers (CheckAudience)", link: "/advanced/resource-servers/" },
                { label: "First-party client provisioning", link: "/advanced/first-party-client/" },
                { label: "Extension contracts", link: "/advanced/extension-contracts/" },
                { label: "Octane", link: "/advanced/octane/" },
                { label: "Testing", link: "/advanced/testing/" },
              ],
            },
            {
              label: "Contributing",
              collapsed: true,
              items: [{ label: "Local development", link: "/contributing/local-development/" }],
            },
          ],
        },
        {
          label: "Client",
          collapsed: true,
          items: [
            { label: "Overview", link: "/client/overview/" },
            { label: "Installation", link: "/client/installation/" },
            { label: "Configuration", link: "/client/configuration/" },
            { label: "Login & logout", link: "/client/login-and-logout/" },
            { label: "Back-channel logout", link: "/client/backchannel-logout/" },
            { label: "Testing", link: "/client/testing/" },
          ],
        },
        {
          label: "UI",
          collapsed: true,
          items: [
            { label: "Installation", link: "/ui/installation/" },
            { label: "Frontend setup", link: "/ui/frontend-setup/" },
            { label: "Overriding views", link: "/ui/overriding/" },
            { label: "Security components", link: "/ui/security-components/" },
            { label: "Translations", link: "/ui/translations/" },
          ],
        },
      ],
    }),
  ],
});
