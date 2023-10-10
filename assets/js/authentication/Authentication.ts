import {AuthenticationResponseJSON, PublicKeyCredentialRequestOptionsJSON} from "@simplewebauthn/typescript-types";
import { startAuthentication, browserSupportsWebAuthn, browserSupportsWebAuthnAutofill, platformAuthenticatorIsAvailable } from "@simplewebauthn/browser";
import {AuthenticatorInterface, NotifyFunctionType} from "../WebauthnTypes";

export default class Authentication implements AuthenticatorInterface {
    private loginForm: HTMLFormElement | null = document.querySelector('#loginform');
    private readonly notify: NotifyFunctionType | undefined;

    constructor(notifyFunction?: NotifyFunctionType) {
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
            const authResp = await startAuthentication(authOptions, isAutofill);

            if (authResp) {
                const { id } = authResp;
                await this.start(authResp, id);
                console.info('User successfully ID verified on server');
            }
        } catch (error: any) {
            if(this.loginForm) {
                this.notify(`Error: ${error.message || error}`, false, this.loginForm);
            }
            if(error.name === 'AbortError') {
                console.warn(error.message);
                return Promise.resolve(null as unknown as AuthenticationResponseJSON);
            }
            throw error;
        }

        return Promise.resolve(null as unknown as AuthenticationResponseJSON);
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

        if(this.loginForm) {
            this.notify(message, !!verificationJSON?.status, this.loginForm);
        }
    }
}