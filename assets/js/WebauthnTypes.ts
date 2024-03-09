import {
	AuthenticationResponseJSON,
	PublicKeyCredentialRequestOptionsJSON,
} from '@simplewebauthn/typescript-types';

export type NotifyFunctionType = ( message: string, isSuccess: boolean ) => void;

export enum NotificationStatus {
  Error = 'error',
  Success = 'success',
  Info = 'info',
}

export type ApiResponse = {
  code: string | number;
  message: string;
  data?: {
    redirectUrl?: string;
    pk_credential_id?: string;
    cardHtml?: string;
  };
};

export type contextType = {
  nonce?: string;
  restEndpoints: {
    [key: string]: string;
  };
};

export type userData = {
  user_login?: string;
  user_email?: string;
  display_name?: string;
};

export interface AuthenticatorInterface {
  generateOptions(): Promise<PublicKeyCredentialRequestOptionsJSON>;

  init( isAutofill: boolean ): Promise<AuthenticationResponseJSON>;

  start( authResp: AuthenticationResponseJSON, id?: string ): Promise<void>;

  verify( attResp: AuthenticationResponseJSON ): Promise<ApiResponse>;
}
