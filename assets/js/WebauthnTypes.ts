import {
    AuthenticationResponseJSON,
    PublicKeyCredentialRequestOptionsJSON
} from "@simplewebauthn/typescript-types";

export type NotifyFunctionType = (message: string, isSuccess: boolean, target: HTMLElement) => void;

export type VerificationResponse = {
    code: string,
    message: string,
    data?: {
        redirectUrl?: string,
        pk_credential_id?: string,
    }
}

export type userData = {
    user_login?: string,
    user_email?: string,
    display_name?: string,
}

export interface AuthenticatorInterface {
    generateOptions(): Promise<PublicKeyCredentialRequestOptionsJSON>;
    init(isAutofill: boolean): Promise<AuthenticationResponseJSON>

    start(authResp: AuthenticationResponseJSON, id?: string): Promise<void>
    verify(attResp: AuthenticationResponseJSON): Promise<VerificationResponse>
}