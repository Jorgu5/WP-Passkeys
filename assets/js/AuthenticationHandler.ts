import {AuthenticationResponseJSON, PublicKeyCredentialRequestOptionsJSON} from "@simplewebauthn/typescript-types";
import { startAuthentication, browserSupportsWebAuthn, browserSupportsWebAuthnAutofill, platformAuthenticatorIsAvailable } from "@simplewebauthn/browser";
import {AuthenticatorInterface, NotifyFunctionType} from "./AuthenticatorInterface";

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

    async verify(authResp: AuthenticationResponseJSON, id?: string): Promise<any> {
        try {
            const verificationResp: Response = await fetch('/wp-json/wp-passkeys/authenticator/verify' + (id ? '?id='+id : ''), {
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

    async init(isAutofill: boolean): Promise<AuthenticationResponseJSON> {
        try {
            const authOptions = await this.generateOptions();
            return await startAuthentication(authOptions, isAutofill);
        } catch (error: any) {
            this.notify(false, `Error: ${error.message || error}`);
            if(error.name === 'AbortError') {
                console.warn(error.message);
                return Promise.resolve(null as unknown as AuthenticationResponseJSON);
            }
            throw error;
        }
    }

    async start(authResp: AuthenticationResponseJSON, id?: string): Promise<void> {
        let verificationJSON: { status?: string, statusText?: string, redirectUrl?: string } = {};
        try {
            verificationJSON = await this.verify(authResp, id);
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
    }
}

const authHandler = new AuthenticationHandler((success: boolean, message: string) => {
    const notification = document.querySelector('.notification');
    if (notification) {
        notification.innerHTML = message;
    }
})

authHandler.init(true).then((authResp: AuthenticationResponseJSON) => {
    if(authResp) {
        const {id} = authResp;
        authHandler.start(authResp, id).then(() => {
            console.log('User ID verified on server');
        });
    }
});