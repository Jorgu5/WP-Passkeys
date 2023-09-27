import {AuthenticationResponseJSON, PublicKeyCredentialRequestOptionsJSON} from "@simplewebauthn/typescript-types";
import { startAuthentication, browserSupportsWebAuthn, browserSupportsWebAuthnAutofill, platformAuthenticatorIsAvailable } from "@simplewebauthn/browser";
import { AuthenticatorInterface } from "./AuthenticatorInterface";
import { NotifyFunctionType } from "./AuthenticatorInterface";

export class AuthenticationHandler implements AuthenticatorInterface {
    private readonly notify: NotifyFunctionType;

    constructor(notifyFunction: NotifyFunctionType) {
        this.notify = notifyFunction;
    }

    async generateOptions(): Promise<any> {
        try {
            const response: Response = await fetch('/wp-json/wp-passkeys/authenticator/options');
            return await response.json();
        } catch (error: any) {
            console.error("Error fetching authentication options:", error);
            throw error;
        }
    }

    async verify(authResp: AuthenticationResponseJSON): Promise<any> {
        try {
            const verificationResp: Response = await fetch('/wp-json/wp-passkeys/authenticator/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(authResp),
            });
            return await verificationResp.json();
        } catch (error: any) {
            console.error("Error verifying authentication:", error);
            throw error;
        }
    }

    async start(): Promise<void> {
        try {
            const authOptions = await this.generateOptions();
            const authResp: AuthenticationResponseJSON = await startAuthentication(authOptions);
            let verificationJSON: { status?: string, statusText?: string, redirectUrl?: string } = {};
            try {
                verificationJSON = await this.verify(authResp);
                console.log(verificationJSON);
                if (verificationJSON.redirectUrl) {
                    window.location.href = verificationJSON.redirectUrl;
                }
            } catch (error: any) {
                console.error("Error in authentication verification:", error);
            }

            const message = verificationJSON?.status === 'Verified'
                ? 'Authentication successful'
                : 'Authentication failed';

            this.notify(!!verificationJSON?.status, message);
        } catch (error: any) {
            this.notify(false, `Error: ${error.message || error}`);
            throw error;
        }
    }
}
