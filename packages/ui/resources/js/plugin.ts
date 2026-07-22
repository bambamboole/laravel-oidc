import { createPlugin, eagerComponent } from "@lattice-php/lattice";
import PasskeyRegistration from "./passkey-registration";
import PasskeyVerify from "./passkey-verify";

export default createPlugin({
    name: "oidc-ui",
    components: {
        "oidc.passkey-verify": eagerComponent(PasskeyVerify),
        "oidc.passkey-registration": eagerComponent(PasskeyRegistration),
    },
    i18n: {
        namespace: "oidc-ui",
    },
});
