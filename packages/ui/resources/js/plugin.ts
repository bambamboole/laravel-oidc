import { eagerComponent, type Plugin } from "@lattice-php/core/registry";
import PasskeyRegistration from "./passkey-registration";
import PasskeyVerify from "./passkey-verify";

export default {
    name: "oidc-ui",
    components: {
        "oidc.passkey-verify": eagerComponent(PasskeyVerify),
        "oidc.passkey-registration": eagerComponent(PasskeyRegistration),
    },
    i18n: {
        namespace: "oidc-ui",
    },
} satisfies Plugin;
