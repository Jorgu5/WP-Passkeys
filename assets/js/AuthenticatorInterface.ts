import {
    AuthenticationResponseJSON,
    AuthenticatorAssertionResponseJSON,
    PublicKeyCredentialRequestOptionsJSON
} from "@simplewebauthn/typescript-types";

export type NotifyFunctionType = (success: boolean, message: string) => void;

export type VerificationResponse = {
    status?: string,
    statusText?: string,
    redirectUrl?: string
}

export type UserLoginResponse = {
    isExistingUser?: boolean
}

export interface AuthenticatorInterface {
    generateOptions(): Promise<PublicKeyCredentialRequestOptionsJSON>;
    init(isAutofill: boolean): Promise<AuthenticationResponseJSON>

    start(authResp: AuthenticationResponseJSON, id?: string): Promise<void>
    verify(attResp: AuthenticationResponseJSON): Promise<VerificationResponse>
}