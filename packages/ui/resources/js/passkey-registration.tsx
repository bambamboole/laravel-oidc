import { browserSupportsWebAuthn, startRegistration } from "@simplewebauthn/browser";
import { LATTICE_EVENT, type RendererComponent } from "@lattice-php/lattice";
import { useT } from "@lattice-php/lattice/i18n";
import { Button, Input, InputError, Label } from "@lattice-php/lattice/ui";
import { useState } from "react";

declare module "@lattice-php/lattice" {
    interface ComponentProps {
        "oidc.passkey-registration": {
            beginUrl: string;
            confirmUrl: string;
        };
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : "";
}

async function postJson<T>(url: string, body: unknown): Promise<T> {
    const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-XSRF-TOKEN": xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        let message = `Request failed with status ${response.status}`;
        try {
            const data = (await response.json()) as { message?: string };
            if (typeof data?.message === "string") {
                message = data.message;
            }
        } catch {
            // Keep the status-based message.
        }
        throw new Error(message);
    }

    return (await response.json()) as T;
}

const PasskeyRegistration: RendererComponent<"oidc.passkey-registration"> = ({ node }) => {
    const { t } = useT("oidc-ui");
    const [name, setName] = useState("");
    const [showForm, setShowForm] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function register(passkeyName: string): Promise<void> {
        setIsLoading(true);
        setError(null);

        try {
            // The generic webauthn enrollment ceremony: begin returns the
            // creation options in the pending enrollment's metadata, confirm
            // takes the attestation credential.
            const begin = await postJson<{ metadata: { options: never } }>(node.props.beginUrl, {
                name: passkeyName,
            });
            const credential = await startRegistration({ optionsJSON: begin.metadata.options });
            await postJson(node.props.confirmUrl, {
                enrollment_id: "pending",
                credential,
            });

            setName("");
            setShowForm(false);
            window.dispatchEvent(
                new CustomEvent(LATTICE_EVENT.reloadComponent, {
                    detail: { component: "oidc.two-factor.methods" },
                }),
            );
        } catch (caught) {
            setError(
                caught instanceof Error
                    ? caught.message
                    : t("passkey.error", "Passkey registration failed. Please try again."),
            );
        } finally {
            setIsLoading(false);
        }
    }

    async function handleSubmit(event: React.FormEvent): Promise<void> {
        event.preventDefault();

        await register(name);
    }

    if (!browserSupportsWebAuthn()) {
        return (
            <div className="text-sm text-lt-muted-fg">
                {t("passkey.not-supported", "Passkeys are not supported in this browser.")}
            </div>
        );
    }

    if (!showForm) {
        return (
            <Button emphasis="outline" onClick={() => setShowForm(true)}>
                {t("passkey.add", "Add passkey")}
            </Button>
        );
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="space-y-4 rounded-lt border border-lt-border bg-lt-muted/50 p-4"
        >
            <div className="grid gap-2">
                <Label htmlFor="passkey-name">{t("passkey.name-label", "Passkey name")}</Label>
                <Input
                    id="passkey-name"
                    type="text"
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                    placeholder={t("passkey.name-placeholder", "e.g., MacBook Pro, iPhone")}
                    className="mt-1 block w-full"
                    autoFocus
                    required
                />
                <p className="text-xs text-lt-muted-fg">
                    {t("passkey.name-help", "A name helps you identify this passkey later.")}
                </p>
            </div>

            {error && <InputError message={error} />}

            <div className="flex gap-2">
                <Button type="submit" disabled={isLoading}>
                    {isLoading
                        ? t("passkey.registering", "Registering...")
                        : t("passkey.register", "Register passkey")}
                </Button>
                <Button
                    type="button"
                    emphasis="ghost"
                    onClick={() => {
                        setShowForm(false);
                        setName("");
                    }}
                >
                    {t("passkey.cancel", "Cancel")}
                </Button>
            </div>
        </form>
    );
};

export default PasskeyRegistration;
